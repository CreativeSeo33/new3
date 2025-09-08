import { post, get, patch, del } from '@shared/api/http';
import type { Cart } from '@shared/types/api';

// Экспортируем типы для использования в других модулях
export type { Cart } from '@shared/types/api';

// Типы для различных режимов ответов
export type CartResponseMode = 'full' | 'summary' | 'delta';

export interface CartSummary {
  version: number;
  itemsCount: number;
  subtotal: number;
  discountTotal: number;
  total: number;
}

export interface CartDelta {
  version: number;
  changedItems: Array<{
    id: number;
    qty: number;
    rowTotal: number;
    effectiveUnitPrice: number;
  }>;
  removedItemIds: number[];
  totals: CartSummary;
}

export interface BatchOperation {
  op: 'add' | 'update' | 'remove';
  productId?: number;
  itemId?: number;
  qty?: number;
  optionAssignmentIds?: number[];
}

export interface BatchResult {
  version: number;
  results: Array<{
    index: number;
    status: 'ok' | 'error';
    itemId?: number;
    error?: string;
  }>;
  changedItems: CartDelta['changedItems'];
  removedItemIds: number[];
  totals: CartSummary;
}

/**
 * Опции для запросов к корзине
 */
export interface CartRequestOptions {
  responseMode?: CartResponseMode;
  idempotencyKey?: string;
  ifMatchVersion?: number | string;
}

/**
 * Создает заголовки для управления режимом ответа
 */
export function createCartHeaders(options: CartRequestOptions = {}) {
  const headers: Record<string, string> = {};

  if (options.responseMode) {
    switch (options.responseMode) {
      case 'summary':
        headers['Prefer'] = 'return=representation; profile="cart.summary"';
        break;
      case 'delta':
        headers['Prefer'] = 'return=minimal; profile="cart.delta"';
        break;
      case 'full':
        headers['Prefer'] = 'return=representation; profile="cart.full"';
        break;
    }
  }

  if (options.idempotencyKey) {
    headers['Idempotency-Key'] = options.idempotencyKey;
  }

  if (options.ifMatchVersion !== undefined) {
    headers['If-Match'] = `"${options.ifMatchVersion}"`;
  }

  return headers;
}

/**
 * Добавляет товар в корзину
 */
export async function addToCart(
  productId: number,
  qty: number = 1,
  optionAssignmentIds: number[] = [],
  options: CartRequestOptions = {}
): Promise<Cart | CartDelta> {
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
  const headers = createCartHeaders(options);

  if (options.responseMode === 'delta') {
    return post<CartDelta>('/api/cart/items', requestBody, { headers });
  }

  return post<Cart>('/api/cart/items', requestBody, { headers });
}

/**
 * Добавляет товар с оптимизированным ответом (delta/summary)
 */
export async function addToCartOptimized(
  productId: number,
  qty: number = 1,
  optionAssignmentIds: number[] = []
): Promise<CartDelta> {
  const headers = createCartHeaders({ responseMode: 'delta' });
  const requestBody = { productId, qty };

  if (optionAssignmentIds.length > 0) {
    (requestBody as any).optionAssignmentIds = optionAssignmentIds;
  }

  return post<CartDelta>('/api/cart/items', requestBody, { headers });
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
export async function updateCartItem(
  itemId: number,
  qty: number,
  options: CartRequestOptions = {}
): Promise<Cart | CartDelta> {
  const headers = createCartHeaders(options);

  if (options.responseMode === 'delta') {
    return patch<CartDelta>(`/api/cart/items/${itemId}`, { qty }, { headers });
  }

  return patch<Cart>(`/api/cart/items/${itemId}`, { qty }, { headers });
}

/**
 * Обновляет количество товара с оптимизированным ответом
 */
export async function updateCartItemOptimized(itemId: number, qty: number): Promise<CartDelta> {
  const headers = createCartHeaders({ responseMode: 'delta' });
  return patch<CartDelta>(`/api/cart/items/${itemId}`, { qty }, { headers });
}

/**
 * Удаляет товар из корзины
 */
export async function removeCartItem(
  itemId: number,
  options: CartRequestOptions = {}
): Promise<Cart | CartDelta> {
  const headers = createCartHeaders(options);

  if (options.responseMode === 'delta') {
    return del<CartDelta>(`/api/cart/items/${itemId}`, { headers });
  }

  return del<Cart>(`/api/cart/items/${itemId}`, { headers });
}

/**
 * Удаляет товар из корзины с оптимизированным ответом
 */
export async function removeCartItemOptimized(itemId: number): Promise<CartDelta> {
  const headers = createCartHeaders({ responseMode: 'delta' });
  return del<CartDelta>(`/api/cart/items/${itemId}`, { headers });
}

/**
 * Выполняет батч-операции над корзиной
 */
export async function executeBatchOperations(
  operations: BatchOperation[],
  atomic: boolean = true,
  options: CartRequestOptions = {}
): Promise<BatchResult> {
  const headers = createCartHeaders(options);
  const body = { operations, atomic };

  return post<BatchResult>('/api/cart/batch', body, { headers });
}

/**
 * Очищает корзину
 */
export async function clearCart(options: CartRequestOptions = {}): Promise<void> {
  const headers = createCartHeaders(options);
  return del<void>('/api/cart', { headers });
}

/**
 * Очищает корзину с оптимизированным ответом
 */
export async function clearCartOptimized(): Promise<CartDelta> {
  const headers = createCartHeaders({ responseMode: 'delta' });
  return del<CartDelta>('/api/cart', { headers });
}
