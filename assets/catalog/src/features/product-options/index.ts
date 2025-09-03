import { ProductOptionsManager } from './ui/manager';

/**
 * Инициализирует управление опциями товара
 */
export function init(
  root: HTMLElement,
  opts: Record<string, any> = {}
): () => void {
  const manager = new ProductOptionsManager(root, opts);
  return () => manager.destroy();
}

// Экспортируем для внешнего использования
export { ProductOptionsManager };
