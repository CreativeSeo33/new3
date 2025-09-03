# Руководство по организации JavaScript кода

## 📋 Введение

Этот проект использует современную модульную архитектуру JavaScript/TypeScript, основанную на принципах Feature-Sliced Design (FSD). Такой подход обеспечивает:

- **Масштабируемость** - легкое добавление новых функций
- **Поддерживаемость** - четкая структура и разделение ответственности
- **Типобезопасность** - полный TypeScript с строгой типизацией
- **Модульность** - независимые компоненты с четкими интерфейсами

## 🏗️ Архитектура проекта

### Структура папок

```
assets/catalog/src/
├── shared/           # Общие компоненты и утилиты
│   ├── api/         # HTTP клиент и API функции
│   ├── lib/         # Утилиты (formatPrice, etc.)
│   ├── types/       # TypeScript типы и интерфейсы
│   └── utils/       # Вспомогательные функции
├── features/        # Бизнес-логика (features)
│   ├── add-to-cart/           # Функционал корзины
│   ├── product-options/       # Опции товара
│   └── product-price-calculator/ # Калькулятор цены
├── widgets/         # UI виджеты
│   └── cart-counter/          # Счетчик корзины
├── entities/        # Бизнес-сущности (пока пустые)
└── pages/           # Страничные компоненты (пока пустые)
```

### Слои архитектуры

1. **shared** - переиспользуемые компоненты, утилиты, типы
2. **entities** - бизнес-сущности (товары, пользователи, заказы)
3. **features** - конкретная бизнес-логика
4. **widgets** - составные UI компоненты
5. **pages** - страницы приложения

## ⚙️ Модульная система

### Как работает система

1. **Bootstrap** - инициализирует все модули на странице
2. **Registry** - реестр всех доступных модулей
3. **Data-module атрибуты** - декларативная инициализация

### Пример работы

```html
<!-- HTML с data-module атрибутами -->
<button data-module="add-to-cart" data-product-id="123">
  Добавить в корзину
</button>

<div data-module="cart-counter">
  <span data-cart-counter>0</span>
</div>
```

```typescript
// Модульная система автоматически инициализирует компоненты
document.addEventListener('DOMContentLoaded', () => {
  bootstrap(); // Запуск системы
});
```

## 🚀 Создание нового модуля

### Шаг 1: Выберите тип модуля

| Тип | Когда использовать | Пример |
|-----|-------------------|--------|
| **Feature** | Бизнес-логика, API вызовы | add-to-cart, product-options |
| **Widget** | UI компонент с состоянием | cart-counter, product-gallery |
| **Shared** | Переиспользуемая логика | http-client, formatPrice |

### Шаг 2: Создайте структуру папок

Для **новой feature**:

```
assets/catalog/src/features/my-new-feature/
├── api/           # API вызовы
│   └── index.ts   # export функций API
├── ui/            # UI компоненты
│   └── component.ts # UI логика
└── index.ts       # Главный экспорт
```

Для **нового widget**:

```
assets/catalog/src/widgets/my-new-widget/
├── index.ts       # Реализация виджета
```

### Шаг 3: Создайте API слой (для features)

```typescript
// features/my-new-feature/api/index.ts
import { post } from '@shared/api/http';
import type { MyResponse } from '@shared/types/api';

export async function doSomething(data: MyData): Promise<MyResponse> {
  return post<MyResponse>('/api/something', data);
}

export async function getSomething(id: number): Promise<MyResponse> {
  return post<MyResponse>(`/api/something/${id}`, null, { method: 'GET' });
}
```

### Шаг 4: Создайте UI компонент

```typescript
// features/my-new-feature/ui/component.ts
import { Component } from '@shared/ui/Component';
import { doSomething } from '../api';
import type { MyData } from '@shared/types/api';

interface MyComponentOptions {
  someParam?: string;
}

export class MyComponent extends Component {
  private options: MyComponentOptions;

  constructor(el: HTMLElement, opts: MyComponentOptions = {}) {
    super(el, opts);
    this.options = opts;
    this.init();
  }

  init(): void {
    // Инициализация компонента
    this.on('click', this.handleClick.bind(this));
  }

  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();

    try {
      const result = await doSomething({ /* данные */ });
      this.updateUI(result);
    } catch (error) {
      console.error('Error:', error);
      this.showError();
    }
  }

  private updateUI(data: any): void {
    // Обновление UI
  }

  private showError(): void {
    // Показать ошибку
  }
}
```

### Шаг 5: Создайте главный экспорт

```typescript
// features/my-new-feature/index.ts
import { MyComponent } from './ui/component';

interface MyFeatureOptions {
  someParam?: string;
}

export function init(
  root: HTMLElement,
  opts: MyFeatureOptions = {}
): () => void {
  const component = new MyComponent(root, opts);
  return () => component.destroy();
}

// Экспорт API для внешнего использования
export { doSomething, getSomething } from './api';
```

### Шаг 6: Зарегистрируйте модуль

```typescript
// app/registry.ts
export const registry: Record<string, () => Promise<ModuleInitFunction>> = {
  // ... существующие модули
  'my-new-feature': () => import('@features/my-new-feature').then(m => m.init),
};
```

### Шаг 7: Используйте в HTML

```html
<!-- templates/catalog/some-page.html.twig -->
<div data-module="my-new-feature" data-some-param="value">
  <!-- HTML компонента -->
</div>
```

## 📝 Типы и интерфейсы

### Создание типов

```typescript
// shared/types/my-types.ts
export interface MyEntity {
  id: number;
  name: string;
  createdAt: Date;
}

export interface MyApiResponse<T> {
  data: T;
  success: boolean;
  message?: string;
}
```

### Использование типов

```typescript
import type { MyEntity, MyApiResponse } from '@shared/types/my-types';

async function fetchEntity(id: number): Promise<MyEntity> {
  const response: MyApiResponse<MyEntity> = await get(`/api/entities/${id}`);
  return response.data;
}
```

## 🎯 Лучшие практики

### 1. Типобезопасность
- Всегда используйте строгую типизацию
- Избегайте `any` типов
- Создавайте интерфейсы для API ответов

### 2. Разделение ответственности
- API слой только для сетевых запросов
- UI слой только для DOM манипуляций
- Бизнес-логика в соответствующих модулях

### 3. Обработка ошибок
```typescript
try {
  const result = await apiCall();
  updateUI(result);
} catch (error) {
  console.error('API Error:', error);
  showErrorMessage(error.message);
}
```

### 4. События
```typescript
// Отправка события
window.dispatchEvent(new CustomEvent('my:event', {
  detail: { data: result }
}));

// Прослушивание события
window.addEventListener('my:event', (e: CustomEvent) => {
  const { data } = e.detail;
  handleData(data);
});
```

### 5. Очистка ресурсов
```typescript
destroy(): void {
  // Удаление обработчиков событий
  window.removeEventListener('my:event', this.handler);

  // Очистка таймеров
  clearTimeout(this.timer);

  // Вызов родительского destroy
  super.destroy();
}
```

## 🔧 Утилиты и помощники

### HTTP клиент
```typescript
import { get, post, patch, del } from '@shared/api/http';

// GET запрос
const users = await get<User[]>('/api/users');

// POST запрос
const newUser = await post<User>('/api/users', userData);

// PATCH запрос
const updatedUser = await patch<User>(`/api/users/${id}`, updateData);

// DELETE запрос
await del(`/api/users/${id}`);
```

### DOM утилиты
```typescript
import { $ } from '@shared/utils/dom';

// Безопасное получение элемента
const button = $('#my-button') as HTMLButtonElement;

// Получение данных из dataset
const productId = this.dataset.int('product-id', 0);
const productName = this.dataset.str('product-name');
```

### Форматирование
```typescript
import { formatPrice } from '@shared/lib/formatPrice';

// Форматирование цены
const priceText = formatPrice(129900); // "1 299 ₽"
```

## 📚 Примеры из проекта

### Добавление товара в корзину

**API слой:**
```typescript
// features/add-to-cart/api/index.ts
export async function addToCart(
  productId: number,
  qty: number = 1,
  optionIds: number[] = []
): Promise<Cart> {
  return post<Cart>('/cart/items', { productId, qty, optionIds });
}
```

**UI слой:**
```typescript
// features/add-to-cart/ui/button.ts
export class AddToCartButton extends Component {
  private async handleClick(e: Event): Promise<void> {
    e.preventDefault();

    const optionIds = this.getSelectedOptions();
    const cartData = await addToCart(this.productId, 1, optionIds);

    // Отправка события для обновления UI
    window.dispatchEvent(new CustomEvent('cart:updated', {
      detail: cartData
    }));

    this.showSuccess();
  }
}
```

**Использование:**
```html
<button data-module="add-to-cart" data-product-id="123">
  Добавить в корзину
</button>
```

## 🚨 Важные моменты

1. **Всегда используйте TypeScript** - он обеспечивает типобезопасность
2. **Следуйте структуре папок** - каждый слой имеет свое место
3. **Тестируйте модули отдельно** - каждый модуль должен быть независимым
4. **Документируйте API** - типы служат документацией
5. **Используйте события** - для коммуникации между модулями
6. **Не забывайте cleanup** - всегда очищайте ресурсы в destroy()

## 🎯 Заключение

Этот подход обеспечивает масштабируемую и поддерживаемую архитектуру. Следуя этому руководству, вы сможете легко добавлять новый функционал и поддерживать существующий код.

При добавлении новых модулей всегда:
1. Определите тип модуля (feature/widget/shared)
2. Создайте правильную структуру папок
3. Реализуйте API слой (для features)
4. Создайте UI компонент с типами
5. Зарегистрируйте модуль
6. Добавьте data-module атрибут в HTML

Удачи в разработке! 🚀
