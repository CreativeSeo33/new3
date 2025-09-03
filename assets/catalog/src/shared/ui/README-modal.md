# Модуль модального окна (Modal)

Модуль модального окна на базе Fancybox для отображения различного контента в модальных окнах.

## Особенности

- Поддержка различных типов контента: HTML, inline, AJAX
- Полная интеграция с Fancybox 6.0
- TypeScript поддержка
- Автоматическая очистка ресурсов
- Следование архитектуре проекта (Feature-Sliced Design)

## Быстрый старт

### Декларативное использование

```html
<!-- Inline контент -->
<div id="my-modal" style="display:none;">
  <h2>Мой контент</h2>
  <p>Это модальное окно</p>
</div>
<button data-module="modal" data-src="#my-modal" data-type="inline">
  Открыть модальное окно
</button>

<!-- HTML контент -->
<button data-module="modal"
        data-type="html"
        data-html="<h2>Привет!</h2><p>Динамический контент</p>">
  Открыть с HTML
</button>

<!-- AJAX контент -->
<button data-module="modal"
        data-type="ajax"
        data-src="/api/content.html">
  Загрузить контент
</button>
```

### Программное использование

```typescript
import { Modal } from '@shared/ui/Modal';

// Создание модального окна
const modal = new Modal(document.querySelector('#trigger'), {
  type: 'html',
  html: '<h2>Привет!</h2><p>Это программное окно</p>',
  width: 500,
  height: 300,
  onOpen: () => console.log('Открыто'),
  onClose: () => console.log('Закрыто')
});

// Управление
modal.open();
modal.close();
modal.isOpen(); // true/false

// Обновление контента
modal.updateContent({
  html: '<h2>Новый контент</h2>'
});

// Уничтожение
modal.destroy();
```

## API

### ModalOptions

```typescript
interface ModalOptions {
  type?: 'inline' | 'html' | 'ajax' | 'clone';
  src?: string;              // Источник контента
  html?: string;             // HTML контент
  filter?: string;           // Селектор для фильтрации AJAX
  width?: number;            // Ширина окна
  height?: number;           // Высота окна
  closeOnOverlay?: boolean;  // Закрывать по клику на overlay
  closeOnEscape?: boolean;   // Закрывать по Escape
  showCloseButton?: boolean; // Показывать кнопку закрытия
  onOpen?: () => void;       // Callback при открытии
  onClose?: () => void;      // Callback при закрытии
  onError?: (error) => void; // Callback при ошибке
}
```

### Методы Modal

- `open()` - Открыть модальное окно
- `close()` - Закрыть модальное окно
- `isOpen()` - Проверить статус
- `updateContent(options)` - Обновить содержимое
- `destroy()` - Уничтожить экземпляр

## Стилизация

Fancybox использует собственные CSS классы. Для кастомизации:

```css
/* Основной контейнер */
.fancybox__container {
  /* Ваши стили */
}

/* Фон (overlay) */
.fancybox__backdrop {
  background: rgba(0, 0, 0, 0.8);
}

/* Контент */
.fancybox__content {
  background: white;
  border-radius: 8px;
  padding: 20px;
}

/* Кнопка закрытия */
.fancybox__close {
  background: #f0f0f0;
  border-radius: 50%;
}
```

## Доступность

Модуль автоматически обеспечивает:
- Управление фокусом внутри модального окна
- Возврат фокуса на trigger элемент
- Поддержку клавиатуры (Tab, Escape)
- ARIA атрибуты для скринридеров

## Примеры

Смотрите файл `modal-examples.ts` для подробных примеров использования.
