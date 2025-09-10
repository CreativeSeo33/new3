# Компонент Spinner

Компонент загрузочного индикатора (spinner) для отображения состояния загрузки.

## Особенности

- Полностью типизированный TypeScript
- Адаптивные размеры (small, medium, large)
- Разные цветовые схемы (primary, secondary, white)
- Поддержка overlay режима
- Интеграция с архитектурой проекта

## Быстрый старт

### Декларативное использование

```html
<!-- Простой спиннер -->
<div data-module="spinner"></div>

<!-- Спиннер с overlay -->
<div data-module="spinner" data-overlay="true"></div>

<!-- Маленький спиннер -->
<div data-module="spinner" data-size="small"></div>

<!-- Белый спиннер -->
<div data-module="spinner" data-color="white"></div>

<!-- Скрытый спиннер -->
<div data-module="spinner" data-visible="false"></div>
```

### Программное использование

```typescript
import { Spinner } from '@shared/ui/spinner';

// Создание спиннера
const spinnerEl = document.getElementById('my-spinner');
const spinner = new Spinner(spinnerEl, {
  size: 'large',
  color: 'primary',
  visible: true,
  overlay: false
});

// Управление спиннером
spinner.show();
spinner.hide();
spinner.setSize('small');
spinner.setColor('secondary');

// Проверка состояния
if (spinner.isVisible()) {
  console.log('Спиннер виден');
}

// Уничтожение
spinner.destroy();
```

## API

### Опции конструктора

```typescript
interface SpinnerOptions {
  size?: 'small' | 'medium' | 'large';  // Размер спиннера
  color?: 'primary' | 'secondary' | 'white';  // Цветовая схема
  visible?: boolean;                     // Начальная видимость
  overlay?: boolean;                     // Режим overlay
}
```

### Методы

- `show()` - Показать спиннер
- `hide()` - Скрыть спиннер
- `setVisible(visible: boolean)` - Установить видимость
- `setSize(size: 'small' | 'medium' | 'large')` - Изменить размер
- `setColor(color: 'primary' | 'secondary' | 'white')` - Изменить цвет
- `isVisible(): boolean` - Проверить видимость
- `destroy()` - Уничтожить компонент

## Стилизация

Компонент использует CSS переменные для кастомизации:

```css
/* Кастомные размеры */
.spinner-container {
  --spinner-size: 60px;  /* Размер спиннера */
}

/* Кастомные цвета */
.spinner-container {
  --spinner-color-1: #ffffff;  /* Цвет первого шара */
  --spinner-color-2: #ff3d00;  /* Цвет второго шара */
}
```

## Data атрибуты

Для декларативного использования:

- `data-module="spinner"` - Обязательный, инициализирует компонент
- `data-size="small|medium|large"` - Размер спиннера
- `data-color="primary|secondary|white"` - Цветовая схема
- `data-visible="true|false"` - Начальная видимость
- `data-overlay="true|false"` - Режим overlay

## Примеры

### Спиннер при загрузке данных

```html
<div id="loading-spinner" data-module="spinner" data-visible="false"></div>
<button onclick="loadData()">Загрузить данные</button>
```

```javascript
function loadData() {
  const spinner = document.getElementById('loading-spinner');
  spinner.show();

  fetch('/api/data')
    .then(response => response.json())
    .then(data => {
      // Обработка данных
    })
    .finally(() => {
      spinner.hide();
    });
}
```

### Overlay спиннер для всей страницы

```html
<div id="page-spinner"
     data-module="spinner"
     data-overlay="true"
     data-size="large"
     data-visible="false">
</div>
```

```javascript
// Показать во время AJAX запроса
const spinner = document.getElementById('page-spinner');
spinner.show();

// После завершения
spinner.hide();
```

## Интеграция

Компонент полностью интегрирован в архитектуру проекта:

- Использует базовый класс `Component`
- Поддерживает автоматическую инициализацию через `data-module`
- Следует принципам Feature-Sliced Design
- Типизирован для TypeScript
