# Cart Items Manager

Модуль для управления товарами в корзине покупок.

## Описание

Модуль `cart-items-manager` предоставляет функциональность для:
- Изменения количества товаров в корзине
- Удаления товаров из корзины
- Автоматического обновления цен и сумм
- Отправки событий обновления корзины

## Использование

### HTML разметка

```html
<table>
  <tbody id="cart-items" data-module="cart-items-manager">
    <tr data-item-id="123">
      <td>Название товара</td>
      <td>Цена: <span>1000 ₽</span></td>
      <td>
        <input
          type="number"
          class="qty-input"
          value="1"
          min="1"
          data-item-id="123"
        >
      </td>
      <td class="row-total">1000 ₽</td>
      <td>
        <button class="remove" data-item-id="123">Удалить</button>
      </td>
    </tr>
  </tbody>
</table>

<div>
  Итого: <span id="cart-total">1000 ₽</span>
</div>
```

### Программное использование

```typescript
import { init, updateCartItemQuantity, removeCartItem } from '@features/cart-items-manager';

// Инициализация модуля
const destroy = init(document.getElementById('cart-items'));

// Программное обновление количества
await updateCartItemQuantity(123, 5);

// Программное удаление товара
await removeCartItem(123);

// Очистка модуля
destroy();
```

## API

### Функции

#### `init(root: HTMLElement, options?: CartItemsManagerOptions): () => void`

Инициализирует модуль управления товарами корзины.

**Параметры:**
- `root`: HTML элемент контейнера
- `options`: Опции модуля

**Возвращает:** Функцию для уничтожения модуля

#### `updateCartItemQuantity(itemId: string | number, qty: number): Promise<Cart>`

Обновляет количество товара в корзине.

**Параметры:**
- `itemId`: ID товара
- `qty`: Новое количество

**Возвращает:** Обновленные данные корзины

#### `removeCartItem(itemId: string | number): Promise<Cart>`

Удаляет товар из корзины.

**Параметры:**
- `itemId`: ID товара для удаления

**Возвращает:** Обновленные данные корзины

#### `getCart(): Promise<Cart>`

Получает актуальные данные корзины.

**Возвращает:** Данные корзины

## События

Модуль отправляет следующие события:

### `cart:updated`

Отправляется при изменении содержимого корзины.

```javascript
window.addEventListener('cart:updated', (event) => {
  const cartData = event.detail;
  console.log('Корзина обновлена:', cartData);
});
```

## Опции

```typescript
interface CartItemsManagerOptions {
  formatPrice?: (price: number) => string; // Функция форматирования цены
}
```

## CSS классы

- `.qty-input` - Поле ввода количества
- `.remove` - Кнопка удаления товара
- `.row-total` - Элемент с суммой строки

## Data атрибуты

- `data-item-id` - ID товара
- `data-module="cart-items-manager"` - Атрибут для автоматической инициализации

## Примеры

### Базовое использование

```html
<tbody data-module="cart-items-manager">
  <!-- Строки товаров -->
</tbody>
```

### С кастомным форматированием цен

```javascript
import { init } from '@features/cart-items-manager';

const destroy = init(document.getElementById('cart-items'), {
  formatPrice: (price) => `${price.toFixed(2)} руб.`
});
```

## Зависимости

- `@shared/api/http` - HTTP клиент
- `@shared/lib/formatPrice` - Форматирование цен
- `@shared/ui/Component` - Базовый компонент
- `@shared/types/api` - Типы данных
