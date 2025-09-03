import { patch, delWithStatus, get } from '@shared/api/http';
import type { Cart, CartItem } from '@shared/types/api';

/**
 * Обновляет количество товара в корзине
 */
export async function updateCartItemQuantity(itemId: string | number, qty: number): Promise<Cart> {
  return patch<Cart>(`/api/cart/items/${itemId}`, { qty });
}

/**
 * Удаляет товар из корзины и возвращает актуальные данные корзины
 */
export async function removeCartItem(itemId: string | number): Promise<Cart> {
  const response = await delWithStatus(`/api/cart/items/${itemId}`);

  // Если удаление успешно, получаем актуальные данные корзины
  if (response.status === 204) {
    return await get<Cart>('/api/cart');
  }

  throw new Error('Ошибка при удалении товара');
}

/**
 * Получает актуальные данные корзины
 */
export async function getCart(): Promise<Cart> {
  return get<Cart>('/api/cart');
}
