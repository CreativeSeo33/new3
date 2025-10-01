# Система доставки - Руководство для разработчика

## 📋 Обзор

Система доставки построена на паттерне "Стратегия" с использованием Symfony Dependency Injection Container и Tagged Iterator. Это позволяет гибко управлять методами доставки и их конфигурацией через YAML-файлы.

## 🏗️ Архитектура системы

### Основные компоненты

```
src/Service/Delivery/
├── DeliveryService.php              # Главный сервис управления доставкой
├── Dto/
│   └── DeliveryCalculationResult.php # DTO для результатов расчета
└── Method/
    ├── DeliveryMethodInterface.php  # Интерфейс всех методов доставки
    ├── PvzDeliveryMethod.php        # Метод "Пункт выдачи"
    └── CourierDeliveryMethod.php    # Метод "Курьерская доставка"
```

### Вспомогательные компоненты

- `CartCalculator` - пересчитывает стоимость корзины с учетом доставки
- `DeliveryContext` - управляет контекстом доставки (город, метод и т.д.)
- `PvzPriceRepository` - репозиторий для цен доставки по городам

## 🎯 Типы расчетов доставки

### 1. Расчет за единицу товара (`cost_per_item`)
```
Стоимость = Базовая_цена × Количество_товаров
```
Пример: Базовая цена = 100 руб., товаров = 3 шт. → Стоимость доставки = 300 руб.

### 2. Фиксированная ставка (`flat_rate`)
```
Стоимость = Базовая_цена
```
Пример: Базовая цена = 100 руб., товаров = 3 шт. → Стоимость доставки = 100 руб.

## ⚙️ Конфигурация системы

### Основной файл конфигурации: `config/services.yaml`

```yaml
# Автоматическая регистрация методов доставки
App\Service\Delivery\Method\PvzDeliveryMethod:
    tags: ['app.delivery_method']
    bind:
        $calculationType: 'cost_per_item'  # Тип расчета

App\Service\Delivery\Method\CourierDeliveryMethod:
    tags: ['app.delivery_method']
    bind:
        $calculationType: 'cost_per_item'  # Тип расчета
```

## 🔧 Как редактировать тип расчета

### Изменение через конфигурацию

**Шаг 1:** Откройте файл `config/services.yaml`

**Шаг 2:** Найдите нужный метод доставки

**Шаг 3:** Измените значение `$calculationType`:

```yaml
# Для расчета за единицу товара
$calculationType: 'cost_per_item'

# Для фиксированной ставки
$calculationType: 'flat_rate'
```

**Шаг 4:** Очистите кэш Symfony:
```bash
php bin/console cache:clear
```

### Проверка изменений

```bash
# Проверить текущий тип расчета
php bin/console debug:container App\Service\Delivery\Method\PvzDeliveryMethod
```

## 📦 Добавление нового метода доставки

### Шаг 1: Создайте класс метода доставки

```php
<?php
// src/Service/Delivery/Method/NewDeliveryMethod.php

namespace App\Service\Delivery\Method;

use App\Entity\Cart;
use App\Entity\PvzPrice;
use App\Service\Delivery\Dto\DeliveryCalculationResult;

class NewDeliveryMethod implements DeliveryMethodInterface
{
    private const METHOD_CODE = 'new_method';

    public function __construct(
        private readonly string $calculationType
    ) {}

    public function supports(string $methodCode): bool
    {
        return $methodCode === self::METHOD_CODE;
    }

    public function getCode(): string
    {
        return self::METHOD_CODE;
    }

    public function getLabel(): string
    {
        return 'Новый метод доставки';
    }

    public function getCalculationType(): string
    {
        return $this->calculationType;
    }

    public function calculate(Cart $cart, PvzPrice $city): DeliveryCalculationResult
    {
        $term = $city->getSrok() ?? 'Срок не указан';
        $freeDeliveryThreshold = $city->getFree();

        // 1. Проверка бесплатной доставки
        if ($freeDeliveryThreshold !== null &&
            $freeDeliveryThreshold > 0 &&
            $cart->getSubtotal() >= $freeDeliveryThreshold) {
            return new DeliveryCalculationResult(0.0, $term, 'Бесплатно', true);
        }

        $baseCost = $city->getCost();
        if ($baseCost === null) {
            return new DeliveryCalculationResult(
                null, '', 'Расчет менеджером', false, true
            );
        }

        // 2. Логика расчета стоимости
        $totalCost = 0;
        if ($this->getCalculationType() === self::TYPE_COST_PER_ITEM) {
            $totalCost = $baseCost * $cart->getTotalItemQuantity();
        } else {
            $totalCost = $baseCost;
        }

        return new DeliveryCalculationResult((float) $totalCost, $term);
    }
}
```

### Шаг 2: Зарегистрируйте метод в services.yaml

```yaml
App\Service\Delivery\Method\NewDeliveryMethod:
    tags: ['app.delivery_method']
    bind:
        $calculationType: 'cost_per_item'
```

### Шаг 3: Очистите кэш

```bash
php bin/console cache:clear
```

## 🔍 Как работает расчет доставки

### Последовательность действий

1. **Получение контекста доставки**
   ```php
   $context = $this->deliveryContext->get();
   $cityName = $context['cityName'] ?? null;
   $methodCode = $context['methodCode'] ?? 'pvz';
   ```

2. **Поиск данных города**
   ```php
   $city = $this->pvzPriceRepository->findOneBy(['city' => $cityName]);
   ```

3. **Выбор метода доставки**
   ```php
   $method = $this->methods[$methodCode] ?? null;
   ```

4. **Расчет стоимости**
   ```php
   $result = $method->calculate($cart, $city);
   ```

## 📊 Структура базы данных

### Таблица `pvz_price`

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | INTEGER | Первичный ключ |
| `city` | VARCHAR(255) | Название города |
| `srok` | VARCHAR(255) | Срок доставки |
| `cost` | INTEGER | Базовая стоимость доставки |
| `free` | INTEGER | Порог бесплатной доставки |

## 🧪 Тестирование системы

### Проверка регистрации методов

```bash
php bin/console debug:container --tag=app.delivery_method
```

### Проверка параметров метода

```bash
php bin/console debug:container App\Service\Delivery\Method\PvzDeliveryMethod
```

### Тест расчета доставки

```php
// В контроллере или сервисе
$deliveryService = $this->get(DeliveryService::class);
$result = $deliveryService->calculateForCart($cart);

echo $result->cost;      // Стоимость доставки
echo $result->term;      // Срок доставки
echo $result->message;   // Сообщение (опционально)
```

## 🚨 Особенности и ограничения

### Бесплатная доставка
- Проверяется по полю `free` в таблице `pvz_price`
- Сравнивается с суммой товаров в корзине (`cart.getSubtotal()`)

### Отсутствие данных
- Если город не найден → возвращается "Расчет менеджером"
- Если стоимость не указана → возвращается "Расчет менеджером"

### Курьерская доставка
- Добавляется фиксированная наценка 300 руб.
- Наценка применяется после расчета базовой стоимости

## 🔧 Распространенные задачи

### Изменение наценки для курьерской доставки

```php
// src/Service/Delivery/Method/CourierDeliveryMethod.php
private const SURCHARGE = 500; // Изменить с 300 на 500
```

### Добавление нового типа расчета

1. Добавить константу в интерфейс:
```php
public const TYPE_EXPRESS = 'express_delivery';
```

2. Обновить логику в методах:
```php
if ($this->getCalculationType() === self::TYPE_EXPRESS) {
    // Специальная логика для экспресс-доставки
}
```

### Изменение условий бесплатной доставки

```php
// В методе calculate() любого класса
if ($cart->getSubtotal() >= $freeDeliveryThreshold &&
    $cart->getTotalItemQuantity() >= 5) { // Минимум 5 товаров
    return new DeliveryCalculationResult(0.0, $term, 'Бесплатно', true);
}
```

## 📝 Лучшие практики

1. **Всегда очищайте кэш** после изменений в `services.yaml`
2. **Тестируйте изменения** на разных типах корзин
3. **Документируйте новые методы** доставки
4. **Используйте константы** для магических чисел
5. **Проверяйте граничные случаи** (пустая корзина, неизвестный город)

## 🆘 Устранение неполадок

### Метод не регистрируется
```bash
# Проверить теги
php bin/console debug:container --tag=app.delivery_method

# Проверить синтаксис YAML
php bin/console debug:config
```

### Неправильный расчет стоимости
```bash
# Проверить параметры метода
php bin/console debug:container App\Service\Delivery\Method\PvzDeliveryMethod

# Добавить логирование в метод calculate()
```

### Ошибка "Class not found"
```bash
# Очистить кэш
php bin/console cache:clear

# Проверить namespace и пути
composer dump-autoload
```

---

## 🧩 Интеграция с мини‑корзиной (dropdown) — вывод доставки и итого

Мини‑корзина (dropdown) должна отображать:
- цену доставки `shipping.cost` (или текст "Расчет менеджером", если `cost = null`),
- итоговую сумму: `subtotal + shipping.cost`.

API `/api/cart` возвращает нужные поля:
- `subtotal` — сумма товаров (в рублях),
- `shipping.cost` — стоимость доставки (в рублях, может быть `null`),
- `currency` — валюта (например, `RUB`).

### ⚠️ Важное исправление (2025-01-17)

**Проблема:** При `PvzPrice.cost = null` (индивидуальный расчёт) в API возвращался `cost = 0` вместо `null`, что приводило к показу "0 руб." вместо "Расчет менеджером".

**Причина:** В `CartApiController::serializeCart()` использовался фолбэк:
```php
// БЫЛО (неправильно):
'cost' => $deliveryResult?->cost ?? $cart->getShippingCost(),

// СТАЛО (правильно):
'cost' => $deliveryResult?->cost, // null означает "Расчет менеджером"
```

**Решение:**
1. Убран фолбэк к `Cart::getShippingCost()` в сериализации API
2. Обновлён TypeScript тип: `cost: number | null`
3. Фронт корректно обрабатывает `null` как "Расчет менеджером"

**Поведение после исправления:**
- `cost = null` → "Расчет менеджером"
- `cost = 0` + `isFree = true` → "0 руб." (бесплатная доставка)
- `cost > 0` → "N руб."

### HTML (Twig) — блоки для значений

```html
<div class="border-t p-3 text-sm text-gray-700 space-y-1">
  <div class="flex justify-between">
    <span>Доставка</span>
    <span data-cart-counter-target="shipping">Расчет менеджером</span>
  </div>
  <div class="flex justify-between">
    <span>Итого</span>
    <span data-cart-counter-target="dropdownTotal">0 руб.</span>
  </div>
</div>
```

### JS (Stimulus) — установка значений после загрузки

```js
// после получения data из GET /api/cart
const subtotal = Number(data?.subtotal || 0);
const shippingCost = data?.shipping?.cost; // может быть number или null

// Обновляем доставку
if (this.hasShippingTarget) {
  if (shippingCost === null) {
    this.shippingTarget.textContent = 'Расчет менеджером';
  } else if (shippingCost === 0 && data?.shipping?.data?.isFree) {
    this.shippingTarget.textContent = '0 руб.'; // бесплатная доставка
  } else {
    this.shippingTarget.textContent = this.formatRub(shippingCost);
  }
}

// Обновляем итог (товары + доставка, если известна)
const grandTotal = subtotal + (shippingCost || 0);
if (this.hasDropdownTotalTarget) {
  this.dropdownTotalTarget.textContent = this.formatRub(grandTotal);
}
```

Где `formatRub(amount)` — форматирование "16 600 руб.".

> **Важно:** После исправления `shipping.cost` может быть `null`, `0` или положительным числом. Проверяйте `null` для показа "Расчет менеджером", а `0` с `isFree=true` для бесплатной доставки.

### 🔒 Синхронизация метода доставки и ETag

Чтобы в мини‑корзине и на сервере всегда совпадала логика расчёта (PVZ vs курьер), используется протокол согласования через ETag:

- При выборе метода доставки отправляйте `POST /api/delivery/select-method` (или `POST /api/delivery/select-pvz`) с заголовком `If-Match: <ETag>`, где `<ETag>` — из последнего ответа `GET /api/cart`.
- На фронтенде ETag сохраняется в `sessionStorage` под ключом `cart:etag` при каждом `GET /api/cart` (см. контроллер `cart_counter_controller.js`).
- HTTP‑клиент автоматически подставляет `If-Match` для запросов к `/api/cart` и `/api/delivery` (кроме GET) и делает один авто‑ретрай на коды `412/428`:

```js
// assets/catalog/src/shared/api/http.ts (фрагмент)
const isStateChanging = method !== 'GET' && /\/api\/(cart|delivery)\//.test(path);
if (isStateChanging) {
  const etag = sessionStorage.getItem('cart:etag');
  if (etag && !finalHeaders['If-Match']) finalHeaders['If-Match'] = etag;
}

let response = await fetch(url, config);
if ((response.status === 412 || response.status === 428) && method !== 'GET') {
  const cartRes = await fetch('/api/cart', { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
  const newEtag = cartRes.headers.get('ETag');
  if (newEtag) {
    sessionStorage.setItem('cart:etag', newEtag);
    (config.headers)['If-Match'] = newEtag;
    response = await fetch(url, config);
  }
}
```

### 🔁 Сброс метода при смене города

При выборе нового города метод доставки сбрасывается (и ПВЗ очищается). После `select-city` необходимо заново выбрать метод:

```php
// src/Service/DeliveryContext.php (фрагмент)
unset($delivery['methodCode'], $delivery['pickupPointId']);
```

### 🛡️ Серверный фолбэк выбора метода

На сервере добавлен безопасный фолбэк: если в контексте нет `methodCode`, используется метод из корзины (`Cart::getShippingMethod()`), и только затем дефолт `pvz`.

```php
// src/Service/Delivery/DeliveryService.php (фрагмент)
$methodCode = $context['methodCode'] ?? ($cart->getShippingMethod() ?? 'pvz');
```

Это снижает риск ситуаций, когда UI уже переключился на курьера, а сервер ещё считает ПВЗ.

---

*Документация создана для junior разработчиков. Обновляйте её при внесении изменений в систему доставки.*
