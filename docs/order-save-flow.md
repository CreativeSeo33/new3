### Процесс сохранения заказа (checkout → order)

Документ описывает полный жизненный цикл оформления и сохранения заказа: точки входа, цепочки вызовов, модель данных и риски.

---

### Точки входа

- GET `/checkout` → `App\Controller\Catalog\CheckoutController::index`
  - Загружает актуальную корзину через `CartManager::getOrCreateCurrent()`.
  - Рендерит `templates/catalog/checkout/index.html.twig` c данными корзины.

- POST `/checkout` → `App\Controller\Catalog\CheckoutController::submit`
  - Принимает JSON-пейлоад с данными покупателя и комментариями.
  - Создаёт `Order` и связанные сущности, копируя снэпшот данных из корзины.
  - Возвращает `{ id, orderId, redirectUrl }`.

Пример запроса (минимально валидный):

```json
{
  "firstName": "Иван",
  "phone": "+7 999 000-11-22",
  "email": "user@example.com",
  "comment": "Позвонить перед доставкой",
  "paymentMethod": "cod"
}
```

---

### Цепочка вызовов (высокоуровневый сценарий)

1) `CheckoutController::submit(Request, CartManager, CheckoutContext, OrderRepository, EntityManagerInterface)`

- `CartManager::getOrCreateCurrent(userId)`
  - Ищет/создаёт `Cart` через `CartRepository` (по userId/token/legacy ULID).
  - Кладёт ссылку на корзину в сессию: `CheckoutContext::setCartRefFromCart($cart)`.
- Валидация входных данных (имя/телефон/формат email) в контроллере.
- Сохранение черновика формы в сессию:
  - `CheckoutContext::setCustomer([...])`
  - `CheckoutContext::setComment($comment)`
  - `CheckoutContext::setPaymentMethod($paymentMethod)`
- Создание доменной модели заказа:
  - `new Order()`
  - `Order::setOrderId($orders->getNextOrderId())` (см. раздел «Риски конкурентности»)
  - `Order::setComment($comment)`
  - `Order::setTotal($cart->getTotal())`
  - `new OrderCustomer()` + заполнение полей (name/phone/email/ip/userAgent) → привязка к `Order` двусторонне
  - По каждому `CartItem`:
    - `new OrderProducts()` → копируются `productId`, `productName`, `unitPrice` → `price`, `qty` → `quantity`
    - `Order::addProduct($op)` и явный `$em->persist($op)`
- `EntityManager::persist($customer)`, `persist($order)`, затем `flush()`
- Ответ: `id`, `orderId`, `redirectUrl` (`/checkout/success/{orderId}`)

2) `CheckoutController::success(int $orderId)` — рендер страницы успешного оформления.

---

### Участники и ответственность

- `CartManager`
  - Поиск/создание `Cart`, связка сессии checkout.cart, синхронизация доставки и пересчёт (в write-методах).
  - В данном потоке используется только `getOrCreateCurrent()` (без пересчёта).

- `CheckoutContext`
  - Хранит черновики checkout в сессии: customer/comment/paymentMethod, а также ссылку на корзину.

- `OrderRepository`
  - Метод `getNextOrderId()` вычисляет следующий номер заказа по `MAX(orderId)+1`.

- `EntityManager (Doctrine)`
  - Управляет персистентностью: `persist(...)`/`flush()`.

---

### Модель данных заказа (снэпшот на момент покупки)

- `App\Entity\Order`
  - Поля: `id:int`, `orderId:int`, `dateAdded:datetime` (заполняется `@PrePersist`), `comment:string|null`, `status:int|null`, `total:int|null`.
  - Связи:
    - `products`: `OneToMany` → `OrderProducts` (cascade: all)
    - `customer`: `OneToOne` → `OrderCustomer` (cascade: all, LAZY)
    - `delivery`: `OneToOne` → `OrderDelivery` (cascade: all, LAZY)
  - API Platform: `ApiResource(Get, GetCollection, Patch, Delete)`, фильтры `OrderFilter(dateAdded)`, `SearchFilter(orderId/status)`.

- `App\Entity\OrderCustomer`
  - Поля: `id`, `name`, `phone`, `email`, `ip`, `userAgent`, `phoneNormal`, `comment`.
  - Связь: `OneToOne(mappedBy=customer)` к `Order`. Сеттер синхронизирует обратную сторону.

- `App\Entity\OrderProducts`
  - Поля: `id`, `product_id:int`, `product_name:string`, `price:int|null`, `quantity:int`, `salePrice:int|null`.
  - Связи: `ManyToOne` к `Order` (inversedBy `products`), `OneToMany` `options` → `OrderProductOptions`.

- `App\Entity\OrderProductOptions`
  - Поля: `id`, `product_id:int|null`, `optionName:string|null`, `value:json|null`, `price:int|null`.
  - Связь: `ManyToOne` к `OrderProducts`.

- `App\Entity\OrderDelivery` (не заполняется в текущем контроллере)
  - Поля: `type`, `address`, `city`, `cost`, `pvz`, `isFree`, `isCustomCalculate`, `pvzCode`, `delivery_date`, `delivery_time`.
  - Связь: `OneToOne(mappedBy=delivery)` к `Order`.

Примечание: Данные заказа — это снэпшот на момент покупки; связь с каталогом ограничена копированием `productId`/`productName`/цен.

---

### Используемые данные из корзины (`App\Entity\Cart`, `CartItem`)

- Источник сумм: `Cart::getTotal()` переносится в `Order::total`.
- Каждая позиция `CartItem` → один `OrderProducts` с переносом: `product.id` → `product_id`, `productName` → `product_name`, `unitPrice` → `price`, `qty` → `quantity`.
- Опции товара из корзины в текущей реализации в `OrderProducts.options` не копируются (отсутствует логика переноса).

---

### Транзакции и целостность

- Сохранение выполняется одним `flush()`. Явной обёртки в транзакцию (begin/commit/rollback) нет; Doctrine выполнит транзакцию вокруг `flush()`.
- Каскады на связях `Order` обеспечивают сохранение зависимых сущностей, однако в контроллере применяется явный `persist()` и для продуктов/клиента (избыточно, но безопасно).

---

### Идемпотентность и повторные отправки формы

- В контроллере нет защиты от повторной отправки (нет idempotency key, нет проверки дубликатов по сессии/карте).
- `CheckoutContext` хранит черновик, но не предотвращает дубль-заказы.

---

### Риски конкурентности и известные ограничения

- Вычисление `orderId` через `MAX(orderId)+1` (`OrderRepository::getNextOrderId`) подвержено гонкам при конкурентных заказах → потенциальные дубликаты `orderId`.
  - Рекомендация: выделенная последовательность/identity/таблица счётчиков под `orderId` или БД-ограничение уникальности с ретраем.
- `paymentMethod` сохраняется в сессии (`CheckoutContext`), но не переносится в `Order`.
- `OrderDelivery` не заполняется при оформлении (если требуется — добавить перенос из `Cart.shipping*`/DeliveryContext).
- Опции товара не переносятся в `OrderProductOptions` (если критично — добавить копирование из `CartItem.selectedOptionsData/optionsSnapshot`).

---

### Псевдокод сохранения (упрощённо)

```php
$cart = $cartManager->getOrCreateCurrent($userId);
assert($cart->getItems()->count() > 0);

$checkout->setCustomer([...]);
$checkout->setComment($comment);
$checkout->setPaymentMethod($paymentMethod);

$order = (new Order())
  ->setOrderId($orders->getNextOrderId())
  ->setComment($comment)
  ->setTotal($cart->getTotal());

$customer = (new OrderCustomer())
  ->setName($name)->setPhone($phone)->setEmail($email)
  ->setIp($ip)->setUserAgent($ua);
$order->setCustomer($customer);
$customer->setOrders($order);

foreach ($cart->getItems() as $it) {
  $op = (new OrderProducts())
    ->setProductId($it->getProduct()->getId())
    ->setProductName($it->getProductName())
    ->setPrice($it->getUnitPrice())
    ->setQuantity($it->getQty());
  $order->addProduct($op);
  $em->persist($op);
}

$em->persist($customer);
$em->persist($order);
$em->flush();
```

---

### Где что находится в коде

- Контроллер: `src/Controller/Catalog/CheckoutController.php`
- Сущности: `src/Entity/Order*.php`, `src/Entity/Cart*.php`
- Репозитории: `src/Repository/OrderRepository.php`, `src/Repository/CartRepository.php`
- Контексты/сервисы: `src/Service/CheckoutContext.php`, `src/Service/CartManager.php`

---

### Быстрые идеи для улучшений (опционально)

- Надёжная генерация `orderId` (sequence/uuid+человекочитаемый номер).
- Перенос `paymentMethod`/данных доставки из `Cart` в `OrderDelivery`.
- Перенос опций позиции в `OrderProductOptions` из корзины.
- Оборачивание сохранения в явную транзакцию + ретраи на конфликт уникальности номера заказа.


