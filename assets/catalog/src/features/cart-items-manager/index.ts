import { CartItemsManager, CartItemsManagerOptions } from './ui/manager';

export function init(
  root: HTMLElement,
  opts: CartItemsManagerOptions = {}
): () => void {
  const manager = new CartItemsManager(root, opts);
  return () => manager.destroy();
}

// Экспортируем API функции
export { updateCartItemQuantity, removeCartItem, getCart } from './api';

// Экспортируем класс для продвинутого использования
export { CartItemsManager };
export type { CartItemsManagerOptions };
