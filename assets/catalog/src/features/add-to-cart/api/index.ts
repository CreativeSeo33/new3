import { post, get, patch, del } from '@shared/api/http';
import type { Cart } from '@shared/types/api';

/**
 * Добавляет товар в корзину
 */
export async function addToCart(
  productId: number,
  qty: number = 1,
  optionAssignmentIds: number[] = []
): Promise<Cart> {
  console.log('API addToCart called with:', { productId, qty, optionAssignmentIds });

  const requestBody: {
    productId: number;
    qty: number;
    optionAssignmentIds?: number[];
  } = { productId, qty };

  if (optionAssignmentIds.length > 0) {
    requestBody.optionAssignmentIds = optionAssignmentIds;
  }

  console.log('API request body:', requestBody);
  return post<Cart>('/api/cart/items', requestBody);
}

/**
 * Получает текущую корзину
 */
export async function getCart(): Promise<Cart> {
  return get<Cart>('/api/cart');
}

/**
 * Обновляет количество товара в корзине
 */
export async function updateCartItem(itemId: number, qty: number): Promise<Cart> {
  return patch<Cart>(`/api/cart/items/${itemId}`, { qty });
}

/**
 * Удаляет товар из корзины
 */
export async function removeCartItem(itemId: number): Promise<Cart> {
  return del<Cart>(`/api/cart/items/${itemId}`);
}
