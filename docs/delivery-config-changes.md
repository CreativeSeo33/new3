## Доставка: конфигурация по умолчанию (внедрение)

Изменения внедрены без ломки существующего кода.

### 1) Конфигурация Symfony

Добавлены параметры и env-переменные по умолчанию в `config/services.yaml`:

```yaml
parameters:
    # Defaults via env
    env(DELIVERY_FREE_THRESHOLD_DEFAULT): '3000'
    env(DELIVERY_TYPES): 'pvz,courier'
    app.delivery.free_threshold_default: '%env(int:DELIVERY_FREE_THRESHOLD_DEFAULT)%'
    app.delivery.types: '%env(csv:DELIVERY_TYPES)%'

services:
    App\Service\Delivery\Method\PvzDeliveryMethod:
        arguments:
            $defaultFreeThreshold: '%app.delivery.free_threshold_default%'

    App\Service\Delivery\Method\CourierDeliveryMethod:
        arguments:
            $defaultFreeThreshold: '%app.delivery.free_threshold_default%'
```

Опционально, можно переопределить значения в `.env`:

```dotenv
DELIVERY_FREE_THRESHOLD_DEFAULT=3000
DELIVERY_TYPES=pvz,courier
```

### 2) Логика использования default порога

В `PvzDeliveryMethod` и `CourierDeliveryMethod` добавлен fallback: если в `PvzPrice.free` нет значения или оно не положительное, используется `app.delivery.free_threshold_default`. При превышении `subtotal` корзины этого порога — доставка бесплатная.

Затронутые файлы:
- `src/Service/Delivery/Method/PvzDeliveryMethod.php`
- `src/Service/Delivery/Method/CourierDeliveryMethod.php`
- `config/services.yaml`

Назначение: централизованная настройка порога бесплатной доставки и списка типов доставки без хардкода в коде.


