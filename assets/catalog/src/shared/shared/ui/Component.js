/**
 * Базовый класс для компонентов
 */
export class Component {
  /**
   * @param {HTMLElement} el - Корневой элемент компонента
   * @param {Object} opts - Опции компонента
   */
  constructor(el, opts = {}) {
    this.el = el;
    this.opts = opts;
    this.destroyed = false;
  }

  /**
   * Инициализация компонента
   */
  init() {
    // Переопределяется в наследниках
  }

  /**
   * Уничтожение компонента
   */
  destroy() {
    this.destroyed = true;
  }

  /**
   * Проверка, уничтожен ли компонент
   * @returns {boolean}
   */
  isDestroyed() {
    return this.destroyed;
  }

  /**
   * Получение элемента по селектору внутри компонента
   * @param {string} selector - CSS селектор
   * @returns {HTMLElement|null}
   */
  $(selector) {
    return this.el.querySelector(selector);
  }

  /**
   * Получение всех элементов по селектору внутри компонента
   * @param {string} selector - CSS селектор
   * @returns {NodeList}
   */
  $$(selector) {
    return this.el.querySelectorAll(selector);
  }

  /**
   * Добавление обработчика события
   * @param {string} event - Название события
   * @param {Function} handler - Обработчик
   * @param {Object} options - Опции addEventListener
   */
  on(event, handler, options = {}) {
    if (this.destroyed) return;

    this.el.addEventListener(event, handler, options);
    this._storeListener(event, handler);
  }

  /**
   * Удаление обработчика события
   * @param {string} event - Название события
   * @param {Function} handler - Обработчик
   */
  off(event, handler) {
    this.el.removeEventListener(event, handler);
  }

  /**
   * Хранение слушателей для автоматической очистки
   * @private
   */
  _listeners = new Map();

  /**
   * Сохранение слушателя для автоматической очистки
   * @private
   */
  _storeListener(event, handler) {
    if (!this._listeners.has(event)) {
      this._listeners.set(event, new Set());
    }
    this._listeners.get(event).add(handler);
  }

  /**
   * Автоматическая очистка всех слушателей при уничтожении
   */
  destroy() {
    super.destroy();

    // Удаляем все слушатели
    for (const [event, handlers] of this._listeners) {
      for (const handler of handlers) {
        this.off(event, handler);
      }
    }
    this._listeners.clear();
  }
}
