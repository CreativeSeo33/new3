import { PriceCalculator } from './ui/calculator';

/**
 * Инициализирует калькулятор цены товара
 */
export function init(
  root: HTMLElement,
  opts: Record<string, any> = {}
): () => void {
  const calculator = new PriceCalculator(root, opts);
  return () => calculator.destroy();
}

// Экспортируем для внешнего использования
export { PriceCalculator };
