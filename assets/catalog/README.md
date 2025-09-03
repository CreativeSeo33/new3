# Frontend Architecture Guide

## 🏗️ Архитектура

Проект использует модульную архитектуру на основе Feature-Sliced Design с полным TypeScript.

### Структура

```
src/
├── shared/           # Переиспользуемые компоненты
│   ├── api/         # HTTP клиент
│   ├── types/       # TypeScript типы
│   └── utils/       # Утилиты
├── features/        # Бизнес-логика
├── widgets/         # UI компоненты
├── entities/        # Сущности
└── pages/           # Страницы
```

## 🚀 Быстрое создание модуля

### 1. Создайте папку модуля

```bash
# Для feature
mkdir -p src/features/my-feature/{api,ui}

# Для widget
mkdir -p src/widgets/my-widget
```

### 2. Создайте файлы

**API слой** (features/my-feature/api/index.ts):
```typescript
import { post } from '@shared/api/http';

export async function myApiCall(data: MyData): Promise<MyResponse> {
  return post<MyResponse>('/api/my-endpoint', data);
}
```

**UI компонент** (features/my-feature/ui/component.ts):
```typescript
import { Component } from '@shared/ui/Component';

export class MyComponent extends Component {
  init(): void {
    this.on('click', this.handleClick);
  }

  private handleClick = () => {
    // Логика компонента
  };
}
```

**Главный экспорт** (features/my-feature/index.ts):
```typescript
import { MyComponent } from './ui/component';

export function init(root: HTMLElement): () => void {
  const component = new MyComponent(root);
  return () => component.destroy();
}
```

### 3. Зарегистрируйте модуль

**app/registry.ts**:
```typescript
export const registry = {
  // ... существующие
  'my-feature': () => import('@features/my-feature'),
};
```

### 4. Используйте в HTML

```html
<div data-module="my-feature" data-param="value">
  <!-- HTML -->
</div>
```

## 📋 Правила

- ✅ Всегда используйте TypeScript
- ✅ Следуйте структуре папок
- ✅ Создавайте типы для API
- ✅ Очищайте ресурсы в destroy()
- ✅ Используйте события для коммуникации

## 🛠️ Команды

```bash
# Сборка catalog части
npm run build:catalog

# Сборка admin части
npm run build:admin

# Полная сборка
npm run build
```

## 📖 Подробнее

Полная документация: `docs/js-architecture-guide.md`
