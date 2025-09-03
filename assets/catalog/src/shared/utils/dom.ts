// Утилиты для работы с DOM согласно рекомендациям

/**
 * Безопасное извлечение данных из dataset элемента
 */
export function fromDataset(element: HTMLElement) {
  return {
    /**
     * Извлекает целое число из dataset
     */
    int: (key: string, defaultValue = 0): number => {
      const value = element.dataset[key];
      if (!value) return defaultValue;

      const number = Number(value);
      return Number.isFinite(number) ? number : defaultValue;
    },

    /**
     * Извлекает строку из dataset
     */
    str: (key: string, defaultValue = ''): string => {
      return element.dataset[key] ?? defaultValue;
    },

    /**
     * Извлекает булево значение из dataset
     */
    bool: (key: string, defaultValue = false): boolean => {
      const value = element.dataset[key];
      if (value === undefined) return defaultValue;
      return value === 'true';
    },

    /**
     * Извлекает значение как есть
     */
    raw: (key: string): string | undefined => {
      return element.dataset[key];
    }
  };
}

/**
 * Безопасное получение элемента по селектору
 */
export function $(selector: string, context: Document | Element = document): HTMLElement | null {
  return context.querySelector(selector);
}

/**
 * Безопасное получение элементов по селектору
 */
export function $$(selector: string, context: Document | Element = document): NodeListOf<HTMLElement> {
  return context.querySelectorAll(selector);
}

/**
 * Типобезопасная проверка типа элемента
 */
export function isHTMLElement(element: any): element is HTMLElement {
  return element instanceof HTMLElement;
}

/**
 * Безопасное добавление класса
 */
export function addClass(element: HTMLElement, className: string): void {
  element.classList.add(className);
}

/**
 * Безопасное удаление класса
 */
export function removeClass(element: HTMLElement, className: string): void {
  element.classList.remove(className);
}

/**
 * Безопасное переключение класса
 */
export function toggleClass(element: HTMLElement, className: string, force?: boolean): void {
  element.classList.toggle(className, force);
}

/**
 * Безопасная установка текста
 */
export function setText(element: HTMLElement, text: string): void {
  element.textContent = text;
}

/**
 * Безопасное получение текста
 */
export function getText(element: HTMLElement): string {
  return element.textContent || '';
}
