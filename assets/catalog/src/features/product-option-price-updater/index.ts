import { ProductOptionPriceUpdater } from './ui/updater';

/**
 * Инициализирует обновление цены товара при изменении опций
 */
export function init(
  root: HTMLElement,
  opts: Record<string, any> = {}
): () => void {
  const updater = new ProductOptionPriceUpdater(root, opts);
  return () => updater.destroy();
}

// Экспортируем для внешнего использования
export { ProductOptionPriceUpdater };
