import { AddToCartButton } from './ui/button';

/**
 * Опции для кнопки добавления в корзину
 */
export interface AddToCartButtonOptions {
  productId?: number;
  qty?: number;
}

/**
 * Инициализирует кнопку добавления в корзину
 */
export function init(
  root: HTMLElement,
  opts: AddToCartButtonOptions = {}
): () => void {
  const button = new AddToCartButton(root, opts);
  return () => button.destroy();
}

// Экспортируем API для внешнего использования
export {
  addToCart,
  getCart,
  updateCartItem,
  removeCartItem
} from './api';
export { AddToCartButton };
