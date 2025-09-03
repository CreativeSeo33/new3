# Добавление нового модуля

## 📋 Шаблоны для быстрого создания

### Feature модуль (с API)

```bash
# 1. Создать структуру
mkdir -p src/features/my-feature/{api,ui}

# 2. Создать файлы
touch src/features/my-feature/api/index.ts
touch src/features/my-feature/ui/component.ts
touch src/features/my-feature/index.ts
```

**api/index.ts:**
```typescript
import { post } from '@shared/api/http';
import type { MyData, MyResponse } from '@shared/types/api';

export async function myApiFunction(data: MyData): Promise<MyResponse> {
  return post<MyResponse>('/api/my-endpoint', data);
}
```

**ui/component.ts:**
```typescript
import { Component } from '@shared/ui/Component';
import { myApiFunction } from '../api';

export class MyComponent extends Component {
  init(): void {
    this.on('click', this.handleClick);
  }

  private handleClick = async () => {
    try {
      const result = await myApiFunction({ /* data */ });
      this.updateUI(result);
    } catch (error) {
      console.error(error);
    }
  };

  private updateUI(data: any): void {
    // Update DOM
  }
}
```

**index.ts:**
```typescript
import { MyComponent } from './ui/component';

export function init(root: HTMLElement): () => void {
  const component = new MyComponent(root);
  return () => component.destroy();
}

export { myApiFunction } from './api';
```

### Widget модуль (без API)

```bash
# 1. Создать папку
mkdir -p src/widgets/my-widget

# 2. Создать файл
touch src/widgets/my-widget/index.ts
```

**index.ts:**
```typescript
import { Component } from '@shared/ui/Component';

export class MyWidget extends Component {
  init(): void {
    // Widget logic
  }
}

export function init(root: HTMLElement): () => void {
  const widget = new MyWidget(root);
  return () => widget.destroy();
}
```

## 🔧 Регистрация модуля

**app/registry.ts:**
```typescript
export const registry = {
  // ... existing modules
  'my-feature': () => import('@features/my-feature'),
  'my-widget': () => import('@widgets/my-widget'),
};
```

## 🎨 Использование в HTML

```html
<!-- Feature -->
<div data-module="my-feature" data-my-param="value">
  <!-- HTML -->
</div>

<!-- Widget -->
<div data-module="my-widget">
  <!-- HTML -->
</div>
```

## ✅ Чек-лист

- [ ] Структура папок создана
- [ ] API слой (если нужен) реализован
- [ ] UI компонент наследует Component
- [ ] Главный экспорт создан
- [ ] Модуль зарегистрирован
- [ ] HTML обновлен
- [ ] TypeScript типы добавлены
- [ ] Очистка ресурсов в destroy()
- [ ] Сборка проходит без ошибок
