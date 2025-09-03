/**
 * Примеры использования модуля модального окна
 *
 * Все примеры показывают, как использовать data-атрибуты для декларативного
 * использования или JavaScript API для программного управления.
 */

/**
 * 1. ПРОСТОЙ INLINE КОНТЕНТ
 *
 * HTML:
 * <div id="my-modal" style="display:none;">
 *   <h2>Мой модальный контент</h2>
 *   <p>Это простой inline контент</p>
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
 *         data-src="/api/modal-content.html">
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
 * import { Modal } from '@shared/ui/Modal';
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
 * // Открытие
 * modal.open();
 *
 * // Закрытие
 * modal.close();
 *
 * // Проверка статуса
 * if (modal.isOpen()) {
 *   console.log('Модальное окно открыто');
 * }
 *
 * // Обновление контента
 * modal.updateContent({
 *   html: '<h2>Обновленный контент</h2>'
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
 * Модуль автоматически:
 * - Управляет фокусом внутри модального окна
 * - Возвращает фокус на trigger элемент при закрытии
 * - Поддерживает клавиатуру (Tab, Escape)
 * - Читает текстовый контент для скринридеров
 *
 * Для лучшей доступности добавляйте:
 * - aria-label на trigger элементы
 * - role="dialog" на контент
 * - aria-labelledby для заголовков
 * - aria-describedby для описаний
 */

/**
 * 8. ИНТЕГРАЦИЯ С ДРУГИМИ МОДУЛЯМИ
 *
 * Модальное окно можно комбинировать с другими модулями:
 *
 * // Внутри модального окна инициализировать другие модули
 * modal.updateContent({
 *   html: `
 *     <div data-module="product-options" data-product-id="123"></div>
 *     <div data-module="add-to-cart" data-product-id="123"></div>
 *   `,
 *   onOpen: () => {
 *     // Инициализировать модули внутри модального окна
 *     document.querySelectorAll('[data-module]').forEach(el => {
 *       initModule(el as HTMLElement);
 *     });
 *   }
 * });
 */
