### Аудит процесса сохранения заказа (Checkout → Order)

Этот документ фиксирует проблемы, уязвимости и конкретные улучшения с примерами кода. Основано на текущей реализации `CheckoutController::submit` и сущностях `Order*`, `Cart*`.

---

### Резюме ключевых рисков

- Номер заказа `orderId`: вычисляется как `MAX(orderId)+1` без уникального индекса → гонки, дубликаты.
- Отсутствует идемпотентность POST `/checkout` → двойные заказы при повторной отправке/долгих ответах.
- Нет финального пересчёта корзины перед оформлением → цены/скидки/доставка могут быть устаревшими.
- Нет финальной валидации стока на момент оформления → oversell при гонках.
- `paymentMethod` сохраняется в сессии, но не переносится в заказ.
- `OrderDelivery` не заполняется из `DeliveryContext`/`Cart`.
- Опции товара (`CartItem.options*`) не переносятся в `OrderProductOptions`.
- Нет явной транзакции, нет блокировки/закрытия корзины → возможны дубль-заказы из одной корзины.
- API Platform для `Order` не ограничен по безопасности (Delete/Patch публичны по умолчанию) → риск несанкционированных операций.
- Валидация входа минимальна (нет длины, enum для `paymentMethod`, нормализации телефона).
- Нет rate limiting/anti-bot на `/checkout`.

---

### Улучшения и примеры кода

#### 1) Надёжный генератор `orderId` + уникальный индекс

- Добавить уникальный индекс на колонку `order.orderId`.
- Перевести генерацию на атомарную БД-последовательность через отдельную таблицу.

Миграция (пример, MySQL/InnoDB):

```php
// migrations/VersionYYYYMMDDHHMMSS.php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE order_seq (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ENGINE=InnoDB');
    $this->addSql('ALTER TABLE `order` ADD UNIQUE INDEX uniq_order_order_id (order_id)');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE `order` DROP INDEX uniq_order_order_id');
    $this->addSql('DROP TABLE order_seq');
}
```

Репозиторий:

```php
// src/Repository/OrderRepository.php
public function getNextOrderId(): int
{
    $conn = $this->_em->getConnection();
    $conn->executeStatement('INSERT INTO order_seq VALUES (NULL)');
    /** @var int $id */
    $id = (int)$conn->lastInsertId();
    return $id; // при необходимости добавить префикс/офсет из конфига
}
```

Примечание: при существующем трафике сначала включите уникальный индекс и добавьте ретраи на конфликт уникальности.

#### 2) Идемпотентность `/checkout`

Использовать `IdempotencyService` с ключом из заголовка `Idempotency-Key` (или fallback на `cartId + hash(payload)`).

```php
// src/Controller/Catalog/CheckoutController.php (фрагмент)
public function submit(Request $request, IdempotencyService $idem, CartManager $carts, /* ... */): Response
{
    $payload = (string)$request->getContent();
    $cart = $carts->getOrCreateCurrent($userId);
    $cartId = $cart->getIdString();
    $key = $request->headers->get('Idempotency-Key') ?: 'checkout:' . $cartId;
    $hash = hash('sha256', $payload);

    $begin = $idem->begin($key, $cartId, '/checkout', $hash, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    if ($begin->type === 'replay') {
        return $this->json($begin->responseData, $begin->httpStatus);
    }
    if ($begin->type === 'in_flight') {
        return $this->json(['error' => 'Processing'], 409, ['Retry-After' => (string)($begin->retryAfter ?? 5)]);
    }
    if ($begin->type === 'conflict') {
        return $this->json(['error' => 'Idempotency conflict'], 409);
    }

    // ... реальная обработка ...
    $response = [ 'id' => $order->getId(), 'orderId' => $order->getOrderId(), 'redirectUrl' => $url ];
    $idem->finish($key, 200, $response);
    return $this->json($response);
}
```

#### 3) Пересчёт корзины и доставка перед сохранением

Заменить `getOrCreateCurrent()` на `getOrCreateForWrite()` в `submit`, чтобы синхронизировать доставку и пересчитать цены/скидки.

```php
$cart = $cartManager->getOrCreateForWrite($userId); // sync DeliveryContext + recalc + flush
```

#### 4) Финальная валидация стока

Дополнительно проверять наличие на складе перед созданием позиций заказа:

```php
foreach ($cart->getItems() as $it) {
    $inventory->assertAvailable($it->getProduct(), $it->getQty(), /* optionAssignmentIds */ []);
}
```

При необходимости ввести резервирование (decrement или отдельная таблица резервов) в одной транзакции с заказом.

#### 5) Транзакция и закрытие корзины

Обернуть оформление в транзакцию и «закрыть» корзину, чтобы исключить повторное использование.

```php
$em->wrapInTransaction(function() use ($em, $cart, /* ... */) {
    // создать Order/OrderProducts/OrderCustomer
    // при успехе: пометить корзину как закрытую/устаревшую
    $cart->setExpiresAt(new \DateTimeImmutable('-1 second')); // или статус
    $em->flush();
});
$checkout->clear();
```

Альтернатива: колонка `cart.closed:boolean` с уникальным индексом на (closed=false) и проверкой.

#### 6) Перенос `paymentMethod` и доставки в заказ

- Добавить поле в `Order`:

```php
// src/Entity/Order.php
#[ORM\Column(length: 32, nullable: true)]
private ?string $paymentMethod = null;
public function getPaymentMethod(): ?string { return $this->paymentMethod; }
public function setPaymentMethod(?string $m): self { $this->paymentMethod = $m; return $this; }
```

- Конфиг допустимых методов оплаты:

```yaml
# config/services.yaml
parameters:
    app.checkout.allowed_payment_methods: ['cod', 'card', 'sbp']
```

- В контроллере: валидировать `paymentMethod` ∈ параметрам, переносить `DeliveryContext` в `OrderDelivery`.

```php
$allowed = $this->getParameter('app.checkout.allowed_payment_methods');
if ($paymentMethod && !in_array($paymentMethod, $allowed, true)) {
    return $this->json(['error' => 'Unsupported payment method'], 400);
}
$order->setPaymentMethod($paymentMethod);

$dc = $deliveryContext->get();
$delivery = (new OrderDelivery())
    ->setType($dc['methodCode'] ?? null)
    ->setCity($dc['cityName'] ?? null)
    ->setAddress($dc['address'] ?? null)
    ->setCost($cart->getShippingCost());
$order->setDelivery($delivery);
```

#### 7) Перенос опций из корзины

Если `CartItem.optionsSnapshot/selectedOptionsData` заполнены, переносить в `OrderProductOptions`.

```php
foreach ($cart->getItems() as $it) {
    $op = new OrderProducts();
    // ... price/productId/qty
    $snapshot = $it->getOptionsSnapshot() ?? [];
    foreach (($snapshot['options'] ?? []) as $opt) {
        $oo = (new OrderProductOptions())
            ->setOptionName($opt['name'] ?? null)
            ->setValue($opt['values'] ?? [])
            ->setPrice($opt['price'] ?? null);
        $op->addOption($oo);
    }
}
```

#### 8) Безопасность API Platform для `Order`

Ограничить операции правами администратора.

```php
// src/Entity/Order.php
#[ApiResource(
  operations: [
    new Get(security: "is_granted('ROLE_ADMIN')"),
    new GetCollection(security: "is_granted('ROLE_ADMIN')"),
    new Patch(security: "is_granted('ROLE_ADMIN')"),
    new Delete(security: "is_granted('ROLE_ADMIN')"),
  ],
  normalizationContext: ['groups' => ['order:get']],
)]
```

#### 9) Улучшенная валидация входа (DTO + Validator)

```php
final class CheckoutRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Regex('/^\+?\d[\d\s\-\(\)]{7,20}$/')]
    public string $phone;

    #[Assert\Email(allowEmptyString: true)]
    #[Assert\Length(max: 120)]
    public ?string $email = null;

    #[Assert\Length(max: 500)]
    public ?string $comment = null;
}
```

В контроллере: десериализация + `validator->validate($dto)`.

#### 10) Rate limiting / Anti-bot

```yaml
# config/packages/framework.yaml
framework:
  rate_limiter:
    checkout:
      policy: 'fixed_window'
      limit: 20
      interval: '1 minute'
```

Применение лимитера в контроллере до обработки.

---

### Мелкие/технические замечания

- Установить статус по умолчанию при создании заказа (например, `STATUS_NEW`), хранить список статусов в параметрах.
- После успешного оформления: `CheckoutContext::clear()`.
- Логировать `orderId`, `cartId`, сумму и метод оплаты (без PII) через Monolog.
- Проверить GDPR-политику: `ip`/`userAgent` — PII.
- В `OrderProducts` использовать `salePrice` при наличии скидки, копировать `effectiveUnitPrice`.

---

### Оценка влияния и приоритет

- Высокий приоритет: генерация `orderId` + уникальный индекс; идемпотентность; пересчёт перед оформлением; финальная проверка стока; безопасность API.
- Средний: перенос доставки/опций/метода оплаты; транзакция и закрытие корзины.
- Низкий: расширенная валидация, rate limiting, логирование.


