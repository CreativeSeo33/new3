import { get, post } from '@shared/api/http';

export interface ProductStockInfo {
  productId: number;
  availableQuantity: number;
  hasOptions: boolean;
  options?: Array<{
    assignmentId: number;
    quantity: number;
    name: string;
  }>;
}

export interface CartQuantityInfo {
  productId: number;
  currentInCart: number;
  maxAllowed: number;
}

/**
 * Получает информацию о доступном количестве товара
 */
export async function getProductStock(productId: number): Promise<ProductStockInfo> {
  try {
    const response = await get<ProductStockInfo>(`/api/products/${productId}/stock`);
    return response;
  } catch (error) {
    console.warn('Failed to get product stock, using fallback', error);
    // Fallback: возвращаем большое число, если API недоступен
    return {
      productId,
      availableQuantity: 999,
      hasOptions: false
    };
  }
}

/**
 * Получает информацию о количестве товара в корзине
 */
export async function getCartQuantityInfo(productId: number): Promise<CartQuantityInfo> {
  try {
    const cart = await get('/api/cart');
    const item = cart.items.find((item: any) => item.productId === productId);

    return {
      productId,
      currentInCart: item ? item.qty : 0,
      maxAllowed: 999 // Будет обновлено после получения stock
    };
  } catch (error) {
    console.warn('Failed to get cart quantity info, using fallback', error);
    return {
      productId,
      currentInCart: 0,
      maxAllowed: 999
    };
  }
}

/**
 * Рассчитывает максимально допустимое количество для добавления
 */
export function calculateMaxAllowed(
  stockInfo: ProductStockInfo,
  cartInfo: CartQuantityInfo
): number {
  // Максимум = доступный stock - уже в корзине
  const maxAllowed = stockInfo.availableQuantity - cartInfo.currentInCart;
  return Math.max(0, maxAllowed);
}

/**
 * Проверяет, можно ли добавить указанное количество товара
 */
export function canAddQuantity(
  quantity: number,
  stockInfo: ProductStockInfo,
  cartInfo: CartQuantityInfo
): boolean {
  const maxAllowed = calculateMaxAllowed(stockInfo, cartInfo);
  return quantity <= maxAllowed;
}

/**
 * Получает рекомендуемое максимальное количество для UI
 */
export function getRecommendedMax(
  stockInfo: ProductStockInfo,
  cartInfo: CartQuantityInfo
): number {
  const maxAllowed = calculateMaxAllowed(stockInfo, cartInfo);
  // Ограничиваем UI до разумного максимума (например, 99)
  return Math.min(maxAllowed, 99);
}
