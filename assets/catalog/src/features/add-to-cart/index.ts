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
  // Проверяем, не инициализирован ли уже модуль на этом элементе
  if ((root as any).__addToCartButton) {
    return () => {};
  }

  const button = new AddToCartButton(root, opts);

  // Сохраняем ссылку на экземпляр
  (root as any).__addToCartButton = button;

  return () => {
    if ((root as any).__addToCartButton) {
      button.destroy();
      delete (root as any).__addToCartButton;
    }
  };
}

// Экспортируем API для внешнего использования
export {
  addToCart,
  getCart,
  updateCartItem,
  removeCartItem
} from './api';
export { AddToCartButton };
