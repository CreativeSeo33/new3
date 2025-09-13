### Аудит схемы доставки (PVZ и Courier) и сохранения в `order_delivery`

Основано на сущностях `OrderDelivery`, `PvzPrice`, `PvzPoints`, `DeliveryType` и описанной бизнес-логике (типы: `pvz`, `courier`).

---

### Текущее состояние (по коду и описанию)

- Типы доставки:
  - `DeliveryType` с уникальным `code` (например, `pvz`, `courier`).
- Данные ПВЗ:
  - `PvzPoints` (список пунктов выдачи) с полями `city`, `code`, `address`, координаты и др.; индекс по `city`.
  - `PvzPrice` (стоимость доставки по городу) с полем `city` и ценой; индекс по `city`.
- Модель доставки в заказе:
  - `OrderDelivery`: `type`, `address`, `city`, `cost`, `pvz`, `isFree`, `isCustomCalculate`, `pvzCode`, `delivery_date`, `delivery_time`.
- Контекст доставки:
  - Есть `DeliveryContext` (в сессии) и в `Cart` поля `shippingMethod`, `shipToCity`, `shippingData`.
- Оформление:
  - В текущем контроллере `CheckoutController::submit` не создаётся `OrderDelivery` и не заполняются поля доставки (факт). Описанная бизнес-логика сохранения доставки в заказ пока не реализована.

---

### Ожидаемый поток (согласно ТЗ)

1) Пользователь выбирает тип доставки из `DeliveryType` (`pvz` или `courier`).
2) Если `pvz`:
   - Ищем в `pvz_points` по полю `city`. Если есть — отдаём список для выбора.
   - Пользователь выбирает конкретный ПВЗ; сохраняем его в `order_delivery.pvz` (и/или `pvzCode`).
3) Если `courier`:
   - Пользователь вводит адрес; сохраняем в `order_delivery.address`.
4) При оформлении считаем цену доставки:
   - Если цена по городу не найдена в `pvz_price` — `order_delivery.is_custom_calculate = 1`.
   - Иначе пишем рассчитанную цену в `order_delivery.cost`.
   - Если доставка бесплатна — `order_delivery.is_free = 1` и `cost = 0`.
5) В заказ сохраняем: `type`, `city`, `cost`, `pvz` (если выбран пункт), флаги `is_free`, `is_custom_calculate`.

---

### Найденные проблемы и уязвимости

- Реализация в контроллере отсутствует:
  - `OrderDelivery` не создаётся и не привязывается к `Order` в `CheckoutController::submit` (неконсистентность между ТЗ и кодом).

- Нормализация города:
  - Везде используется строковое поле `city` (в `pvz_points`, `pvz_price`, `order_delivery`). Нет единого идентификатора города (`cityCode`), возможны расхождения по регистру/написанию/локали, дубляжи.

- Проверка принадлежности ПВЗ городу:
  - В `OrderDelivery.pvz` хранится строка. Нет FK/валидации, что код ПВЗ существует и соответствует городу → риск неконсистентности.

- Отсутствие целостности и ограничений:
  - Поля `isFree`/`isCustomCalculate` nullable. Нет чётких правил, может возникать конфликт между `isFree` и `cost > 0`.
  - Нет уникального индекса по `PvzPoints.code` (если `code` должен быть уникальным), нет композитного индекса `(city, code)`.

- Расчёт цены доставки:
  - По описанию — через `pvz_price.city`. Нет явного сервиса расчёта. Непрозрачно, как определяется бесплатность (по порогу «free»? по акциям?).
  - Нет «следа расчёта» (trace) в заказе — трудно разбираться с инцидентами.

- Безопасность/валидация входа:
  - Адрес (`address`) и `pvz` принимаются от клиента — нужны нормализация и проверка (XSS/инъекции/смайлы/длина/кодировка).

- Сессионная/корзинная рассинхронизация:
  - Часть данных хранится в `DeliveryContext`/`Cart.shipping*`, часть — предполагается в `OrderDelivery`. Нет единого места пересчёта перед оформлением.

- Поля даты/времени доставки:
  - `delivery_date`, `delivery_time` в `OrderDelivery` в snake_case и как отдельные поля; могут быть неконсистентными (часовой пояс). Нужна унификация.

- Производительность/поиск:
  - Поиск по `pvz_points.city`/`pvz_price.city` строковый; нет обезличивания (трим/регистронезависимость), возможны промахи из‑за вариаций написания.

---

### Рекомендации по улучшению (c примерами)

1) Единый сервис расчёта и сборки доставки

Создать `DeliveryCalculator` (или расширить имеющийся контекст), который на вход получает: `Cart`, выбранный `DeliveryType.code`, город (`city`/`cityCode`), опционально `pvzCode`, и возвращает заполненный `OrderDelivery` + «след расчёта».

```php
final class DeliveryCalculator
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @return array{ delivery: OrderDelivery, trace: array } */
    public function calculate(Cart $cart, string $type, string $city, ?string $pvzCode = null): array
    {
        $delivery = new OrderDelivery();
        $delivery->setType($type)->setCity($city);

        $trace = ['source' => null, 'baseCost' => null, 'freeThreshold' => null];

        // Цена по городу
        $price = $this->em->getRepository(PvzPrice::class)
            ->findOneBy(['city' => $city]); // см. нормализацию ниже

        if (!$price) {
            $delivery->setIsCustomCalculate(true);
        } else {
            $base = $price->getCost() ?? 0;
            $threshold = $price->getFree();
            $trace = ['source' => 'pvz_price', 'baseCost' => $base, 'freeThreshold' => $threshold];
            $isFree = $threshold !== null && $cart->getTotal() >= $threshold;
            $delivery->setIsFree($isFree);
            $delivery->setCost($isFree ? 0 : $base);
        }

        if ($type === 'pvz' && $pvzCode) {
            /** @var PvzPoints|null $point */
            $point = $this->em->getRepository(PvzPoints::class)->findOneBy(['code' => $pvzCode]);
            if ($point && strcasecmp((string)$point->getCity(), $city) === 0) {
                $delivery->setPvz($pvzCode);
            } else {
                throw new \DomainException('PVZ code not found or mismatched city');
            }
        }

        return ['delivery' => $delivery, 'trace' => $trace];
    }
}
```

В `CheckoutController::submit`: перед сохранением заказа вызвать калькулятор и присвоить `Order->setDelivery($delivery)`.

2) Нормализация города и ПВЗ

- Ввести единый `cityCode` (КЛАДР/ФИАС/ISO + локальное сопоставление), хранить в `pvz_points`, `pvz_price`, `order_delivery`.
- Добавить индексы/уникальные ограничения:
  - `PvzPoints.code` — `UNIQUE` (если бизнес‑смысл кода уникален) или `UNIQUE(city, code)`.
  - Композитный индекс `PvzPrice(city)` уже есть; добавить `cityCode` при его введении.
- Нормализовать поиск: `LOWER(TRIM(city))` на запись/поиск; лучше — опираться на `cityCode`.

3) Консистентность полей и флагов

- Сделать `isFree`/`isCustomCalculate` not null, с default=false.
- `isFree` должен следовать из логики расчёта (например, порог `free`), а `cost` должен быть 0 при `isFree=true`.
- Добавить поле `pricingSource` (`pvz_price` | `custom` | `external`) и `pricingTrace` (JSON) для аудита.

4) Проверки и безопасность

- Валидировать `pvzCode` → должен существовать и соответствовать городу.
- Адрес курьерской доставки пропускать через нормализацию (трим, длины, запрещённые символы), хранить «как введено» + нормализованную версию.
- Никогда не доверять клиентским `cost`, `is_free`. Всегда пересчитывать на сервере.

5) API и производительность

- Публичные эндпойнты для фронта:
  - `GET /delivery/points?city=...` (с пагинацией и кешированием) — из `PvzPoints`.
  - `GET /delivery/price?city=...` — из `PvzPrice`.
  - Реализация может быть через контроллеры, а не напрямую через ApiResource публично, чтобы контролировать выдачу.
- Кешировать ответы (per-city) 5–15 минут.

6) Модель данных `OrderDelivery`

- Добавить (при необходимости):
  - `cityCode:string|null`, `pvzMetadata:json|null` (snapshot названия/адреса/координат), `pricingSource:string|null`, `pricingTrace:json|null`.
- Привести имена к camelCase в PHP и маппить на snake_case в БД.
- Рассмотреть FK на `DeliveryType` (по `code`) и на `PvzPoints` (по `code`), если нужно жёстко обеспечивать целостность.

7) Интеграция с корзиной и транзакции

- Перед оформлением использовать `CartManager::getOrCreateForWrite()` для синхронизации доставки и пересчёта.
- Оборачивать оформление заказа (включая заполнение `OrderDelivery`) в транзакцию. На успешном завершении — очищать `CheckoutContext` и помечать корзину как «закрытую».

8) Конфиги и фичи

- Порог бесплатной доставки, провайдеры, таймауты и пр. хранить в `config/services.yaml`/env.

```yaml
parameters:
  app.delivery.free_threshold_default: 3000
  app.delivery.types: ['pvz', 'courier']
```

---

### Минимальные шаги внедрения (практично)

1) Реализовать `DeliveryCalculator` и вызов в `CheckoutController::submit` с присвоением `Order->setDelivery(...)`.
2) Валидация `pvzCode` и нормализация города.
3) Сделать `isFree`/`isCustomCalculate` not null с default=false; хранить `pricingSource`/`pricingTrace`.
4) Добавить `UNIQUE` индекс по `PvzPoints.code` (или `(city, code)`), индексы по `cityCode` (после внедрения).
5) Переключить фронт на публичные эндпойнты получения `points`/`price` и включить кеш.


