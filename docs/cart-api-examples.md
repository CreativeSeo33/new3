# Cart API - Оптимизированные примеры использования

## Обзор оптимизаций

Новая версия Cart API поддерживает несколько режимов ответа для оптимизации производительности:

- **full**: Полная корзина (как раньше)
- **summary**: Минимальные данные для обновления UI
- **delta**: Точечные изменения по позициям

## Управление режимом ответа

### Через Prefer заголовок
```http
Prefer: return=minimal; profile="cart.delta"
Prefer: return=representation; profile="cart.summary"
Prefer: return=representation; profile="cart.full"
```

### Через query параметр
```http
GET /api/cart?view=delta
GET /api/cart?view=summary
GET /api/cart?view=full
```

## Примеры запросов

### 1. Добавление товара с delta-ответом

**Запрос:**
```http
POST /api/cart/items
Prefer: return=minimal; profile="cart.delta"
Idempotency-Key: abc-123-def
Content-Type: application/json

{
  "productId": 123,
  "qty": 2,
  "optionAssignmentIds": [45, 67]
}
```

**Ответ (201 Created):**
```json
{
  "version": 42,
  "changedItems": [
    {
      "id": 15,
      "qty": 2,
      "rowTotal": 2400,
      "effectiveUnitPrice": 1200
    }
  ],
  "removedItemIds": [],
  "totals": {
    "itemsCount": 3,
    "subtotal": 3600,
    "discountTotal": 0,
    "total": 3600
  }
}
```

### 2. Обновление количества с summary-ответом

**Запрос:**
```http
PATCH /api/cart/items/15
Prefer: return=representation; profile="cart.summary"
Content-Type: application/json

{
  "qty": 5
}
```

**Ответ (200 OK):**
```json
{
  "version": 43,
  "itemsCount": 3,
  "subtotal": 6000,
  "discountTotal": 0,
  "total": 6000
}
```

### 3. Удаление товара с 204 ответом

**Запрос:**
```http
DELETE /api/cart/items/15
Prefer: return=minimal; profile="cart.delta"
```

**Ответ (204 No Content):**
```
(no body)
```

Заголовки ответа:
```
Cart-Version: 44
Items-Count: 2
Totals-Subtotal: 2400
Totals-Discount: 0
Totals-Total: 2400
```

### 4. Батч-операции

**Запрос:**
```http
POST /api/cart/batch
Idempotency-Key: batch-uuid-123
Content-Type: application/json

{
  "operations": [
    { "op": "add", "productId": 200, "qty": 1 },
    { "op": "update", "itemId": 10, "qty": 3 },
    { "op": "remove", "itemId": 11 }
  ],
  "atomic": true
}
```

**Ответ (200 OK):**
```json
{
  "version": 45,
  "results": [
    { "index": 0, "status": "ok", "itemId": 16 },
    { "index": 1, "status": "ok" },
    { "index": 2, "status": "ok" }
  ],
  "changedItems": [
    {
      "id": 16,
      "qty": 1,
      "rowTotal": 1500,
      "effectiveUnitPrice": 1500
    },
    {
      "id": 10,
      "qty": 3,
      "rowTotal": 2700,
      "effectiveUnitPrice": 900
    }
  ],
  "removedItemIds": [11],
  "totals": {
    "itemsCount": 3,
    "subtotal": 4200,
    "discountTotal": 0,
    "total": 4200
  }
}
```

### 5. Батч с частичным успехом (atomic: false)

**Запрос:**
```http
POST /api/cart/batch
Content-Type: application/json

{
  "operations": [
    { "op": "add", "productId": 300, "qty": 1 },
    { "op": "update", "itemId": 999, "qty": 2 },  // не существует
    { "op": "remove", "itemId": 16 }
  ],
  "atomic": false
}
```

**Ответ (207 Multi-Status):**
```json
{
  "version": 46,
  "results": [
    { "index": 0, "status": "ok", "itemId": 17 },
    { "index": 1, "status": "error", "error": "cart_item_not_found" },
    { "index": 2, "status": "ok" }
  ],
  "changedItems": [
    {
      "id": 17,
      "qty": 1,
      "rowTotal": 800,
      "effectiveUnitPrice": 800
    }
  ],
  "removedItemIds": [16],
  "totals": {
    "itemsCount": 2,
    "subtotal": 3500,
    "discountTotal": 0,
    "total": 3500
  }
}
```

## Фронтенд использование

### Vue.js примеры

```typescript
import {
  addToCartOptimized,
  updateCartItemOptimized,
  executeBatchOperations,
  type CartDelta,
  type BatchOperation
} from '@features/add-to-cart/api';

// Быстрое добавление с delta ответом
const addItem = async () => {
  try {
    const delta: CartDelta = await addToCartOptimized(123, 1);

    // Обновляем UI только по измененным данным
    updateCartTotals(delta.totals);
    addOrUpdateCartItem(delta.changedItems[0]);
  } catch (error) {
    console.error('Failed to add item:', error);
  }
};

// Батч-обновление нескольких товаров
const updateMultipleItems = async () => {
  const operations: BatchOperation[] = [
    { op: 'update', itemId: 10, qty: 2 },
    { op: 'update', itemId: 15, qty: 0 }, // удаление через qty=0
    { op: 'add', productId: 200, qty: 1 }
  ];

  try {
    const result = await executeBatchOperations(operations, true);

    // Обрабатываем результаты
    result.results.forEach((res, index) => {
      if (res.status === 'error') {
        console.warn(`Operation ${index} failed:`, res.error);
      }
    });

    // Обновляем UI
    updateCartUI(result);
  } catch (error) {
    console.error('Batch operation failed:', error);
  }
};
```

## Заголовки ответа

Все ответы содержат полезные заголовки:

```
ETag: W/"cart:cart_id.45.1634567890"
Cart-Version: 45
Items-Count: 3
Totals-Subtotal: 4200
Totals-Discount: 0
Totals-Total: 4200
```

## Производительность

### Сравнение размеров ответов

| Операция | Full ответ | Delta ответ | Экономия |
|----------|------------|-------------|----------|
| Добавление товара | ~2.5KB | ~0.8KB | 68% |
| Обновление qty | ~2.5KB | ~0.5KB | 80% |
| Удаление товара | ~2.5KB | 0KB (204) | 100% |
| Батч 3 операции | ~7.5KB | ~1.2KB | 84% |

### Сценарии использования

#### Для мобильных приложений
```typescript
// Используем delta для минимального трафика
const delta = await addToCartOptimized(productId, qty, options, {
  idempotencyKey: generateIdempotencyKey()
});
```

#### Для быстрого UI обновления
```typescript
// Summary для обновления счетчиков и итогов
const summary = await updateCartItem(itemId, qty, {
  responseMode: 'summary'
});
updateTotalsOnly(summary);
```

#### Для комплексных операций
```typescript
// Батч для атомарных изменений
const result = await executeBatchOperations(operations, true, {
  idempotencyKey: batchId
});
```

## Обратная совместимость

Все существующие клиенты продолжают работать без изменений - по умолчанию возвращается full ответ.

## Idempotency-Key

Используйте для предотвращения дублирования операций:

```typescript
const key = `cart-add-${productId}-${Date.now()}`;
await addToCart(productId, qty, options, { idempotencyKey: key });
```

Повторные запросы с тем же ключом вернут кэшированный результат.
