/**
 * Примеры использования модуля модального окна
 *
 * Модуль использует Fancybox 6.0 для отображения модальных окон.
 * Все примеры показывают, как использовать data-атрибуты для декларативного
 * использования или JavaScript API для программного управления.
 */

/**
 * 1. ПРОСТОЙ INLINE КОНТЕНТ
 *
 * HTML:
 * <div id="my-modal" style="display:none;">
 *   <h2>Мой контент</h2>
 *   <p>Это модальное окно</p>
 * </div>
 * <button data-module="modal" data-src="#my-modal" data-type="inline">
 *   Открыть модальное окно
 * </button>
 */

/**
 * 2. HTML КОНТЕНТ (генерируется динамически)
 *
 * HTML:
 * <button data-module="modal"
 *         data-type="html"
 *         data-html="<h2>Динамический контент</h2><p>HTML генерируется на лету</p>">
 *   Открыть с HTML
 * </button>
 */

/**
 * 3. AJAX КОНТЕНТ
 *
 * HTML:
 * <button data-module="modal"
 *         data-type="ajax"
 *         data-src="/api/content.html">
 *   Загрузить контент AJAX
 * </button>
 *
 * Или с фильтром (только часть страницы):
 * <button data-module="modal"
 *         data-type="ajax"
 *         data-src="/api/full-page.html"
 *         data-filter="#content">
 *   Загрузить только #content
 * </button>
 */

/**
 * 4. ПРОГРАММНОЕ ИСПОЛЬЗОВАНИЕ
 *
 * JavaScript:
 * import { Modal } from '@shared/ui/modal-simple.js';
 *
 * // Создание модального окна
 * const modal = new Modal(document.querySelector('#my-button'), {
 *   type: 'html',
 *   html: '<h2>Привет!</h2><p>Это программно созданное окно</p>',
 *   width: 500,
 *   height: 300,
 *   onOpen: () => console.log('Модальное окно открыто'),
 *   onClose: () => console.log('Модальное окно закрыто'),
 *   onError: (error) => console.error('Ошибка:', error)
 * });
 *
 * // Управление
 * modal.open();
 * modal.close();
 * console.log(modal.isOpen()); // true/false
 *
 * // Обновление контента
 * modal.updateContent({
 *   html: '<h2>Новый контент</h2>'
 * });
 *
 * // Уничтожение
 * modal.destroy();
 */

/**
 * 5. КОНФИГУРАЦИЯ ОПЦИЙ
 *
 * Все доступные опции:
 * {
 *   type: 'inline' | 'html' | 'ajax' | 'clone',  // Тип контента
 *   src: string,                                // Источник контента (для inline/ajax)
 *   html: string,                               // HTML контент (для type: 'html')
 *   filter: string,                             // Селектор для фильтрации AJAX контента
 *   width: number,                              // Ширина модального окна
 *   height: number,                             // Высота модального окна
 *   closeOnOverlay: boolean,                    // Закрывать при клике на overlay (default: true)
 *   closeOnEscape: boolean,                     // Закрывать по Escape (default: true)
 *   showCloseButton: boolean,                   // Показывать кнопку закрытия (default: true)
 *   onOpen: () => void,                         // Callback при открытии
 *   onClose: () => void,                        // Callback при закрытии
 *   onError: (error) => void                    // Callback при ошибке
 * }
 */

/**
 * 6. СТИЛИЗАЦИЯ
 *
 * Fancybox использует CSS классы для стилизации.
 * Основные классы:
 * - .fancybox__container      // Контейнер модального окна
 * - .fancybox__backdrop       // Фон (overlay)
 * - .fancybox__content        // Контент модального окна
 * - .fancybox__close          // Кнопка закрытия
 *
 * Кастомные стили можно добавить через CSS:
 *
 * .modal-fancybox .fancybox__content {
 *   background: white;
 *   border-radius: 8px;
 *   padding: 20px;
 * }
 *
 * .modal-fancybox .fancybox__close {
 *   background: #f0f0f0;
 *   border-radius: 50%;
 * }
 */

/**
 * 7. ДОСТУПНОСТЬ
 *
 * Модуль автоматически обеспечивает:
 * - Управление фокусом внутри модального окна
 * - Возврат фокуса на trigger элемент при закрытии
 * - Поддержку клавиатуры (Tab, Escape)
 * - ARIA атрибуты для скринридеров
 *
 * Для лучшей доступности добавляйте:
 * - aria-label на trigger элементы
 * - role="dialog" на контент
 * - aria-labelledby для заголовков
 * - aria-describedby для описаний
 */

/**
 * 8. РЕАЛЬНЫЕ ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
 *
 * Пример 1: Модальное окно с формой
 * <button data-module="modal"
 *         data-type="html"
 *         data-html="<form><input type='text' placeholder='Имя'><button type='submit'>Отправить</button></form>">
 *   Открыть форму
 * </button>
 *
 * Пример 2: Галерея изображений
 * <button data-module="modal"
 *         data-type="ajax"
 *         data-src="/api/gallery.html">
 *   Открыть галерею
 * </button>
 *
 * Пример 3: Подтверждение действия
 * <button data-module="modal"
 *         data-type="html"
 *         data-html="<p>Вы уверены?</p><button>Да</button><button>Нет</button>"
 *         data-width="300"
 *         data-height="150">
 *   Удалить элемент
 * </button>
 */
