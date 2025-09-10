import { get, patch, del } from '@shared/api/http';
import {
  createCartHeaders,
  type Cart,
  type CartDelta,
  type CartRequestOptions,
  type CartSummary
} from '../../add-to-cart/api';

/**
 * Утилита для парсинга HTTP статуса из error.message
 */
function parseHttpStatus(error: any): number | null {
  if (!error?.message) return null;

  const match = error.message.match(/^\s*HTTP\s+(\d{3})\b/);
  return match ? parseInt(match[1], 10) : null;
}

/**
 * Утилита для проверки является ли ответ полным объектом корзины
 */
function isFullCartResponse(response: any): response is Cart {
  return response &&
         (typeof response.id === 'string' || typeof response.id === 'number') &&
         typeof response.currency === 'string' &&
         typeof response.subtotal === 'number';
}

/**
 * Нормализует полный ответ корзины в delta-формат
 */
function normalizeFullCartToDelta(cart: Cart, changes?: {
  changedItemId?: number;
  changedQty?: number;
  removedItemId?: number;
}): CartDelta {
  let changedItems: CartDelta['changedItems'] = [];

  // Если есть изменения, попробуем найти реальные данные товара в корзине
  if (changes?.changedItemId && cart.items) {
    const cartItem = cart.items.find(item => Number(item.id) === Number(changes.changedItemId));
    if (cartItem) {
      // Создаем объект с опциональными полями - не подставляем фиктивные значения
      const itemData: any = {
        id: changes.changedItemId,
        qty: changes.changedQty || cartItem.qty
      };
      if (cartItem.rowTotal !== undefined) {
        itemData.rowTotal = cartItem.rowTotal;
      }
      if (cartItem.effectiveUnitPrice !== undefined) {
        itemData.effectiveUnitPrice = cartItem.effectiveUnitPrice;
      }
      changedItems = [itemData];
    } else if (changes.changedQty !== undefined) {
      // Товар не найден в корзине, но указано количество - создаем без прайсинга
      changedItems = [{
        id: changes.changedItemId,
        qty: changes.changedQty
      } as any]; // workaround: required fields not available
    }
  }

  const removedItemIds = changes?.removedItemId ? [changes.removedItemId] : [];

  return {
    version: (cart as any).version || Date.now(),
    changedItems,
    removedItemIds,
    totals: {
      itemsCount: cart.items?.length || 0,
      subtotal: cart.subtotal,
      discountTotal: cart.discountTotal || 0,
      total: cart.total,
      version: (cart as any).version || Date.now() // workaround for CartSummary type
    } as any
  };
}


/**
 * Обновляет количество товара в корзине с оптимизированным ответом
 */
export async function updateCartItemQuantity(
  itemId: string | number,
  qty: number,
  options: CartRequestOptions = {}
): Promise<CartDelta> {
  try {
    // По умолчанию используем delta-режим
    const requestOptions = {
      ...options,
      responseMode: options.responseMode || 'delta'
    };
    if (options.ifMatchVersion !== undefined) {
      requestOptions.ifMatchVersion = options.ifMatchVersion;
    }
    const headers = createCartHeaders(requestOptions);

    // Выполняем PATCH запрос
    const response = await patch(`/api/cart/items/${Number(itemId)}`, { qty }, { headers });

    // Обрабатываем различные типы ответов
    if (isFullCartResponse(response)) {
      // 200 OK с полным объектом корзины
      return normalizeFullCartToDelta(response, { changedItemId: Number(itemId), changedQty: qty });
    } else if (response === null || response === undefined || response === '') {
      // 204 No Content - получаем актуальные данные из полного API для корректного обновления
      console.log('204 No Content received, fetching full cart data for accurate pricing...');
      try {
        const fullCartResponse = await get('/api/cart');
        return normalizeFullCartToDelta(fullCartResponse, { changedItemId: Number(itemId), changedQty: qty });
      } catch (fullCartError) {
        console.error('Failed to fetch full cart after 204 response:', fullCartError);
        // Fallback: возвращаем минимальные данные
        return {
          version: Date.now(),
          changedItems: [{
            id: Number(itemId),
            qty
          } as any],
          removedItemIds: [],
          totals: {
            itemsCount: 0,
            subtotal: 0,
            discountTotal: 0,
            total: 0,
            version: Date.now()
          } as any
        };
      }
    } else {
      // Предполагаем что это корректный delta-ответ
      if (!response.totals) {
        throw new Error('Invalid delta response format for quantity update');
      }
      return response;
    }
  } catch (error: any) {
    const status = parseHttpStatus(error);
    console.warn('Delta mode failed for updateCartItemQuantity:', { error: error.message, status });

    // Обработка конфликтов версий - не делаем фолбэк
    if (status === 412 || status === 409 || status === 428) {
      console.warn('Version conflict detected, throwing precondition_failed');
      throw new Error('precondition_failed');
    }

    // Обработка специальных случаев
    if (status === 404) {
      console.warn('Item not found during quantity update, fetching summary');
      // Товар не найден - получаем summary для актуальных данных
      const summary = await getCartSummary();
      return {
        version: summary.version,
        changedItems: [],
        removedItemIds: [Number(itemId)], // Позиция исчезла
        totals: {
          itemsCount: summary.itemsCount,
          subtotal: summary.subtotal,
          discountTotal: summary.discountTotal,
          total: summary.total,
          version: summary.version
        } as any
      };
    }

    // Fallback: используем полный режим и нормализуем результат
    const fullCart = await updateCartItemQuantityFull(itemId, qty, options);
    return normalizeFullCartToDelta(fullCart, { changedItemId: Number(itemId), changedQty: qty });
  }
}

/**
 * Обновляет количество товара в корзине с полным ответом (для обратной совместимости)
 */
export async function updateCartItemQuantityFull(
  itemId: string | number,
  qty: number,
  options: CartRequestOptions = {}
): Promise<Cart> {
  try {
    // Выполняем PATCH с полными заголовками
    const fullOptions = {
      ...options,
      responseMode: 'full' as const
    };
    if (options.ifMatchVersion !== undefined) {
      fullOptions.ifMatchVersion = options.ifMatchVersion;
    }
    const headers = createCartHeaders(fullOptions);
    const response = await patch(`/api/cart/items/${Number(itemId)}`, { qty }, { headers });

    // Обрабатываем ответ
    if (isFullCartResponse(response)) {
      // 200 OK с полным объектом корзины
      return response;
    } else if (response === null || response === undefined || response === '') {
      // 204 No Content - получаем полную корзину (fallback)
      return get<Cart>('/api/cart');
    } else {
      // Предполагаем, что это уже полный объект корзины
      if (isFullCartResponse(response)) {
        return response;
      } else {
        // Неожиданный ответ - получаем полную корзину
        console.warn('Unexpected response format in full mode, fetching full cart');
        return get<Cart>('/api/cart');
      }
    }
  } catch (error: any) {
    const status = parseHttpStatus(error);
    console.warn('Full mode failed for updateCartItemQuantityFull:', { error: error.message, status });

    // Обработка конфликтов версий
    if (status === 412 || status === 409 || status === 428) {
      console.warn('Version conflict detected in full mode, throwing precondition_failed');
      throw new Error('precondition_failed');
    }

    if (status === 404) {
      // Товар не найден - возвращаем актуальную корзину
      console.warn('Item not found during full quantity update');
      return get<Cart>('/api/cart');
    }

    // Для других ошибок пробрасываем дальше
    throw error;
  }
}

/**
 * Удаляет товар из корзины с оптимизированным ответом
 */
export async function removeCartItem(
  itemId: string | number,
  options: CartRequestOptions = {}
): Promise<CartDelta> {
  try {
    // По умолчанию используем delta-режим
    const requestOptions = {
      ...options,
      responseMode: options.responseMode || 'delta'
    };
    if (options.ifMatchVersion !== undefined) {
      requestOptions.ifMatchVersion = options.ifMatchVersion;
    }
    const headers = createCartHeaders(requestOptions);

    // Выполняем DELETE запрос
    const response = await del(`/api/cart/items/${Number(itemId)}`, { headers });

    // Обрабатываем различные типы ответов
    if (isFullCartResponse(response)) {
      // 200 OK с полным объектом корзины
      return normalizeFullCartToDelta(response, { removedItemId: Number(itemId) });
    } else if (response === null || response === undefined || response === '') {
      // 204 No Content - получаем актуальные данные из полного API для корректного обновления
      console.log('204 No Content received for removal, fetching full cart data for accurate pricing...');
      try {
        const fullCartResponse = await get('/api/cart');
        return normalizeFullCartToDelta(fullCartResponse, { removedItemId: Number(itemId) });
      } catch (fullCartError) {
        console.error('Failed to fetch full cart after 204 response for removal:', fullCartError);
        // Fallback: возвращаем минимальные данные
        return {
          version: Date.now(),
          changedItems: [],
          removedItemIds: [Number(itemId)],
          totals: {
            itemsCount: 0,
            subtotal: 0,
            discountTotal: 0,
            total: 0,
            version: Date.now()
          } as any
        };
      }
    } else {
      // Предполагаем что это корректный delta-ответ
      if (!response.totals) {
        throw new Error('Invalid delta response format for removal');
      }
      return response;
    }
  } catch (error: any) {
    const status = parseHttpStatus(error);
    console.warn('Delta mode failed for removeCartItem:', { error: error.message, status });

    // Обработка конфликтов версий - не делаем фолбэк
    if (status === 412 || status === 409 || status === 428) {
      console.warn('Version conflict detected, throwing precondition_failed');
      throw new Error('precondition_failed');
    }

    // Обработка специальных случаев
    if (status === 404) {
      console.warn('Item not found during removal, fetching summary');
      // Товар уже удален - получаем summary для актуальных данных
      const summary = await getCartSummary();
      return {
        version: summary.version,
        changedItems: [],
        removedItemIds: [Number(itemId)],
        totals: {
          itemsCount: summary.itemsCount,
          subtotal: summary.subtotal,
          discountTotal: summary.discountTotal,
          total: summary.total,
          version: summary.version
        } as any
      };
    }

    // Fallback: используем полный режим и нормализуем результат
    const fullCart = await removeCartItemFull(itemId, options);
    return normalizeFullCartToDelta(fullCart, { removedItemId: Number(itemId) });
  }
}

/**
 * Удаляет товар из корзины с полным ответом (для обратной совместимости)
 */
export async function removeCartItemFull(
  itemId: string | number,
  options: CartRequestOptions = {}
): Promise<Cart> {
  try {
    // Выполняем DELETE с полными заголовками
    const fullOptions = {
      ...options,
      responseMode: 'full' as const
    };
    if (options.ifMatchVersion !== undefined) {
      fullOptions.ifMatchVersion = options.ifMatchVersion;
    }
    const headers = createCartHeaders(fullOptions);
    const response = await del(`/api/cart/items/${Number(itemId)}`, { headers });

    // Обрабатываем ответ
    if (isFullCartResponse(response)) {
      // 200 OK с полным объектом корзины
      return response;
    } else if (response === null || response === undefined || response === '') {
      // 204 No Content - получаем полную корзину (fallback)
      return get<Cart>('/api/cart');
    } else {
      // Предполагаем, что это уже полный объект корзины
      if (isFullCartResponse(response)) {
        return response;
      } else {
        // Неожиданный ответ - получаем полную корзину
        console.warn('Unexpected response format in full removal mode, fetching full cart');
        return get<Cart>('/api/cart');
      }
    }
  } catch (error: any) {
    const status = parseHttpStatus(error);
    console.warn('Full mode failed for removeCartItemFull:', { error: error.message, status });

    // Обработка конфликтов версий
    if (status === 412 || status === 409 || status === 428) {
      console.warn('Version conflict detected in full removal mode, throwing precondition_failed');
      throw new Error('precondition_failed');
    }

    if (status === 404) {
      // Товар не найден - возвращаем актуальную корзину
      console.warn('Item not found during full removal');
      return get<Cart>('/api/cart');
    }

    // Для других ошибок пробрасываем дальше
    throw error;
  }
}

/**
 * Получает актуальные данные корзины
 */
export async function getCart(): Promise<Cart> {
  return get<Cart>('/api/cart');
}

/**
 * Получает summary данные корзины (оптимизированный вариант)
 */
export async function getCartSummary(): Promise<CartSummary> {
  const headers = createCartHeaders({ responseMode: 'summary' });
  return get<CartSummary>('/api/cart', { headers });
}
