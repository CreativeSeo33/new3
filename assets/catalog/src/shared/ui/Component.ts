/**
 * Базовый класс для компонентов с типизацией
 */
export abstract class Component {
  protected el: HTMLElement;
  protected options: Record<string, any>;
  protected destroyed = false;
  private listeners = new Map<string, Set<EventListener>>();

  constructor(el: HTMLElement, options: Record<string, any> = {}) {
    this.el = el;
    this.options = options;
  }

  /**
   * Инициализация компонента
   */
  abstract init(): void;

  /**
   * Уничтожение компонента
   */
  destroy(): void {
    this.destroyed = true;

    // Удаляем все слушатели событий
    for (const [event, handlers] of this.listeners) {
      for (const handler of handlers) {
        this.el.removeEventListener(event, handler);
      }
    }
    this.listeners.clear();
  }

  /**
   * Проверка, уничтожен ли компонент
   */
  isDestroyed(): boolean {
    return this.destroyed;
  }

  /**
   * Получение элемента по селектору внутри компонента
   */
  protected $<T extends HTMLElement = HTMLElement>(selector: string): T | null {
    return this.el.querySelector(selector);
  }

  /**
   * Получение всех элементов по селектору внутри компонента
   */
  protected $$<T extends HTMLElement = HTMLElement>(selector: string): NodeListOf<T> {
    return this.el.querySelectorAll(selector);
  }

  /**
   * Добавление обработчика события с автоматической очисткой
   */
  protected on(event: string, handler: EventListener, options?: boolean | AddEventListenerOptions): void {
    if (this.destroyed) return;

    this.el.addEventListener(event, handler, options);

    // Сохраняем слушатель для автоматической очистки
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(handler);
  }

  /**
   * Удаление обработчика события
   */
  protected off(event: string, handler: EventListener): void {
    this.el.removeEventListener(event, handler);

    // Удаляем из сохраненных слушателей
    const handlers = this.listeners.get(event);
    if (handlers) {
      handlers.delete(handler);
      if (handlers.size === 0) {
        this.listeners.delete(event);
      }
    }
  }

  /**
   * Получение данных из dataset с типизацией
   */
  protected get dataset() {
    return {
      int: (key: string, defaultValue = 0): number => {
        const value = this.el.dataset[key];
        if (!value) return defaultValue;

        const number = Number(value);
        return Number.isFinite(number) ? number : defaultValue;
      },

      str: (key: string, defaultValue = ''): string => {
        return this.el.dataset[key] ?? defaultValue;
      },

      bool: (key: string, defaultValue = false): boolean => {
        const value = this.el.dataset[key];
        if (value === undefined) return defaultValue;
        return value === 'true';
      },

      raw: (key: string): string | undefined => {
        return this.el.dataset[key];
      }
    };
  }
}
