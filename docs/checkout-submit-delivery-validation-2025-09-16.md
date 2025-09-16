Короткая заметка: фиксация 400 при оформлении заказа (валидация доставки)

Изменения
- Добавлена фронт‑валидация DeliveryContext в модуле `checkout-form` перед POST `/checkout`.
- Блокируем сабмит, если:
  - метод `pvz`, но не выбран ПВЗ (`pickupPointId` отсутствует);
  - метод `courier`, но не указан адрес.
- Пробрасываем `cityId` из DeliveryContext в payload, если он есть (используется бэкендом для `OrderDelivery::setCityFias`).

Файлы
- `assets/catalog/src/features/checkout-form/ui/component.ts` — добавлен импорт `getDeliveryContext` и проверка контекста в `handleSubmit`; доброшен `cityId` в payload.

Поведение
- Пользователь теперь получает явное предупреждение до отправки, если не выбрана доставка/ПВЗ или не указан адрес для курьера.
- Исключает 400 от `CheckoutController::submit` в сценариях незаполненной доставки.

Бэкенд (контекст)
- Валидация: `PvzDeliveryMethod::validate()` требует `pvzCode`; `CourierDeliveryMethod::validate()` требует непустой `address`.
- Контроллер `/checkout` использует `DeliveryContext` и выбрасывает 400 при некорректных данных доставки.


