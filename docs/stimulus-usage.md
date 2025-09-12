# Stimulus.js в проекте

## Обзор

Symfony UX Stimulus Bundle успешно установлен и настроен в проекте. Stimulus позволяет создавать интерактивные компоненты без необходимости в тяжелых JavaScript фреймворках.

## Установка

Bundle уже установлен через Composer:
```bash
composer require symfony/stimulus-bundle
```

## Структура файлов

```
assets/
├── bootstrap.js              # Основной файл инициализации Stimulus
├── controllers.json          # Конфигурация контроллеров
└── controllers/              # Директория с контроллерами
    ├── hello_controller.js
    ├── click_counter_controller.js
    └── csrf_protection_controller.js
```

## Создание контроллера

### 1. Создайте файл контроллера

Создайте файл в `assets/controllers/` с именем вида `name_controller.js`:

```javascript
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // Определите targets если нужно
    static targets = ["output"];

    connect() {
        console.log('Controller connected!');
    }

    disconnect() {
        console.log('Controller disconnected!');
    }

    // Методы для обработки событий
    doSomething() {
        // Ваш код здесь
    }
}
```

### 2. Используйте в HTML

```html
<div data-controller="your-controller">
    <button data-action="your-controller#doSomething">Кнопка</button>
    <div data-your-controller-target="output"></div>
</div>
```

## Доступные контроллеры

### Hello Controller
Простой пример контроллера для демонстрации.

**HTML:**
```html
<div data-controller="hello"></div>
```

### Click Counter Controller
Контроллер для подсчета кликов с кнопками + и -.

**HTML:**
```html
<div data-controller="click-counter">
    <span data-click-counter-target="count">0</span>
    <button data-action="click-counter#increment">+</button>
    <button data-action="click-counter#decrement">-</button>
    <button data-action="click-counter#reset">Сбросить</button>
</div>
```

### CSRF Protection Controller
Автоматическая защита форм от CSRF атак.

**HTML:**
```html
<form method="post">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token('form_name') }}" data-controller="csrf-protection">
    <!-- остальные поля формы -->
</form>
```

## Сборка assets

После создания новых контроллеров не забудьте пересобрать assets:

```bash
npm run build
```

## Тестовая страница

Посетите `/stimulus/test` для просмотра работающих примеров всех контроллеров.

## Лучшие практики

1. **Именование:** Используйте `snake_case` для имен файлов контроллеров
2. **Targets:** Определяйте targets для элементов, с которыми работает контроллер
3. **Actions:** Используйте data-action для привязки событий
4. **Values:** Для передачи данных из HTML в JavaScript используйте data-атрибуты
5. **Cleanup:** Всегда очищайте ресурсы в методе `disconnect()`

## Примеры использования в проекте

### Модальное окно
```javascript
// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["dialog"];

    open() {
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }
}
```

**HTML:**
```html
<dialog data-modal-target="dialog">
    <button data-action="modal#close">×</button>
    <div>Содержимое модального окна</div>
</dialog>

<button data-action="modal#open">Открыть</button>
```

### AJAX форма
```javascript
// assets/controllers/ajax_form_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["submit", "result"];

    async submit(event) {
        event.preventDefault();

        this.submitTarget.disabled = true;

        try {
            const response = await fetch(this.element.action, {
                method: this.element.method,
                body: new FormData(this.element)
            });

            const result = await response.text();
            this.resultTarget.innerHTML = result;
        } catch (error) {
            console.error('Form submission error:', error);
        } finally {
            this.submitTarget.disabled = false;
        }
    }
}
```

## Конфигурация

### Webpack Encore
Stimulus Bridge уже настроен в `webpack.config.js`:

```javascript
.enableStimulusBridge('./assets/controllers.json')
```

### Bootstrap
Файл `assets/bootstrap.js` автоматически регистрирует все контроллеры из директории `controllers/`.

## Полезные ссылки

- [Официальная документация Stimulus](https://stimulus.hotwired.dev/)
- [Symfony UX Stimulus Bundle](https://symfony.com/bundles/ux-stimulus/current/index.html)
- [SymfonyCasts Stimulus](https://symfonycasts.com/screencast/stimulus)
