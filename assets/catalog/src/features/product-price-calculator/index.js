import { PriceCalculator } from './ui/calculator';

/**
 * Инициализирует калькулятор цены товара
 * @param {HTMLElement} root - Корневой элемент с ценой
 * @param {Object} opts - Опции
 * @returns {Function} Функция для уничтожения компонента
 */
export function init(root, opts = {}) {
  const calculator = new PriceCalculator(root, opts);
  return () => calculator.destroy();
}

// Экспортируем для внешнего использования
export { PriceCalculator } from './ui/calculator';
