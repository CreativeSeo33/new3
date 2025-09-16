### CourierDeliveryMethod: фикc интерпретации free=0/NULL (без бесплатной доставки)

- Дата: 2025-09-16
- Файл: `src/Service/Delivery/Method/CourierDeliveryMethod.php`

Изменение:
- Раньше при `PvzPrice.free === null` или `<= 0` использовался конфигурационный `defaultFreeThreshold`, что могло ошибочно давать бесплатную доставку.
- Теперь `free = 0` и `free = NULL` трактуются одинаково: бесплатной доставки нет (порог = 0, проверка не срабатывает).

Фрагмент кода:
```58:64:src/Service/Delivery/Method/CourierDeliveryMethod.php
$term = $city->getSrok() ?? 'Срок не указан';
$freeDeliveryThreshold = $city->getFree();
$effectiveFreeThreshold = ($freeDeliveryThreshold !== null && $freeDeliveryThreshold > 0)
    ? $freeDeliveryThreshold
    : 0; // 0 и NULL означает, что бесплатной доставки нет
$baseCost = $city->getCost();
```

Поведение:
- Бесплатно только если `free > 0` и `cart.subtotal >= free`.
- Для `free = 0`/`NULL` — всегда платно согласно базовой стоимости + наценка.

Проверка:
- Сценарии с `free=null` и `free=0` не дают статус `isFree=true`.
- Сценарий с `free=5000` даёт `isFree=true`, если `cart.subtotal >= 5000`.


