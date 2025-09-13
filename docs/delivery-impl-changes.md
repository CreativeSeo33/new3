### Правки реализации доставки (минимальные и безопасные)

Цель: улучшить точность расчёта по городу, валидировать PVZ, и сохранить доставку в заказ на этапе checkout — без ломки текущего UI.

---

### 1) Нормализация поиска города в `PvzPriceRepository`

Добавляем метод поиска с `LOWER(TRIM(...))` — уменьшает промахи из‑за регистра/пробелов.

```php
// src/Repository/PvzPriceRepository.php
public function findOneByCityNormalized(string $cityName): ?PvzPrice
{
    $qb = $this->createQueryBuilder('p')
        ->andWhere('LOWER(TRIM(p.city)) = :city')
        ->setParameter('city', mb_strtolower(trim($cityName)))
        ->setMaxResults(1);

    return $qb->getQuery()->getOneOrNullResult();
}
```

---

### 2) Использовать нормализованный поиск в `DeliveryService`

Заменяем прямой `findOneBy(['city' => $cityName])` на метод выше.

```php
// src/Service/Delivery/DeliveryService.php (внутри calculateForCart)
// было:
// $city = $this->pvzPriceRepository->findOneBy(['city' => $cityName]);
// стало:
$city = $this->pvzPriceRepository->findOneByCityNormalized($cityName);
```

Остальная логика остаётся прежней: стратегии `pvz`/`courier` возвращают `DeliveryCalculationResult` с `cost/term/isFree/ requiresManagerCalculation`.

---

### 3) Привязка доставки к заказу в `CheckoutController::submit`

Добавляем маппинг доставки из `DeliveryContext` и `DeliveryService` в `OrderDelivery`.

```php
// use вверху файла
use App\Service\Delivery\DeliveryService;
use App\Service\DeliveryContext; // уже есть в проекте
use App\Entity\OrderDelivery;
use App\Entity\PvzPoints;

// сигнатура метода submit() дополняется зависимостями
public function submit(
    Request $request,
    CartManager $cartManager,
    CheckoutContext $checkout,
    OrderRepository $orders,
    EntityManagerInterface $em,
    DeliveryService $deliveryService,
    DeliveryContext $deliveryContext,
): Response {
    // ... существующий код ...

    // 1) Контекст доставки из сессии
    $ctx = $deliveryContext->get();
    $method = $ctx['methodCode'] ?? 'pvz';
    $cityName = $ctx['cityName'] ?? null;

    // 2) Расчёт доставки по корзине (с учётом города и метода)
    $calc = $deliveryService->calculateForCart($cart);

    // 3) Сборка OrderDelivery
    $od = new OrderDelivery();
    $od->setType($method);
    if ($cityName) {
        $od->setCity($cityName);
    }
    if ($calc->cost !== null) {
        $od->setCost($calc->cost);
    }
    if ($calc->isFree) {
        $od->setIsFree(true);
        $od->setCost(0);
    }
    if ($calc->requiresManagerCalculation) {
        $od->setIsCustomCalculate(true);
    }

    // 4) Специфика PVZ / курьера
    if ($method === 'pvz' && !empty($ctx['pickupPointId'])) {
        $pvzCode = (string)$ctx['pickupPointId'];
        // Валидация: код PVZ должен существовать и соответствовать городу
        $point = $em->getRepository(PvzPoints::class)->findOneBy(['code' => $pvzCode]);
        if ($point && strcasecmp((string)$point->getCity(), (string)$cityName) === 0) {
            $od->setPvz($pvzCode);
            $od->setPvzCode($pvzCode);
        } else {
            // несоответствие — сохраняем флаг для ручного просчёта
            $od->setIsCustomCalculate(true);
        }
    }

    if ($method === 'courier' && !empty($ctx['address'])) {
        $od->setAddress(substr(trim((string)$ctx['address']), 0, 255));
    }

    // 5) Привязка к заказу (cascade: ['all'] уже настроен)
    $order->setDelivery($od);

    // ... существующий persist/flush ...
}
```

Примечание: поля `pickupPointId`/`address` уже предусмотрены в `DeliveryContext` (см. `DeliveryContext::get()`), поэтому маппинг не требует изменений на фронте.

---

### 4) (Опционально) Строже валидировать вход и сообщения для UI

- Если нужен явный статус для UI: добавьте на слое контроллера перевод `DeliveryCalculationResult` в `{ cost, term, message }`, где `message` = `"Бесплатно" | "Расчет менеджером" | null`.
- Адрес нормализовать на бэке: `trim`, ограничение длины, выкинуть управляющие символы.

---

### 5) Что это даёт

- Снижение промахов при поиске города → меньше случаев «Расчёт менеджером» по причине несовпадения строки.
- Заказ получает полные данные доставки (`type/city/cost/pvz/isFree/isCustomCalculate`).
- PVZ проверяется на соответствие городу — меньше инцидентов.
- Изменения обратносовместимы: фронт не меняется, шаблоны не трогаем.


