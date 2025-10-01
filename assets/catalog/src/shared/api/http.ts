// ai:http-client exports=get,post,patch,del,delWithStatus,http
import type { HttpOptions, HttpGetOptions, HttpPostOptions, HttpPutOptions, HttpPatchOptions, HttpDeleteOptions } from '../types/api';
import { Spinner } from '@shared/ui/spinner';
import { getCsrfToken, requiresCsrfToken } from './csrf';

const base = '';
const defaultHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  'Content-Type': 'application/json'
};

/**
 * Основная функция для HTTP запросов с типизацией
 */
export async function http<T = any>(
  path: string,
  options: HttpOptions & { signal?: AbortSignal } = {}
): Promise<T> {
  const { method = 'GET', headers = {}, body, signal } = options as any;
  const url = base + path;

  // Добавляем CSRF токен для state-changing методов
  const finalHeaders: Record<string, string> = { ...defaultHeaders, ...headers };
  // Пробуем подставить If-Match из sessionStorage для state-changing запросов на cart/delivery
  try {
    const isStateChanging = method !== 'GET' && /\/api\/(cart|delivery)\//.test(path);
    if (isStateChanging) {
      const etag = sessionStorage.getItem('cart:etag');
      if (etag && !finalHeaders['If-Match']) {
        finalHeaders['If-Match'] = etag;
      }
    }
  } catch {}
  if (requiresCsrfToken(method)) {
    try {
      const csrfToken = await getCsrfToken();
      finalHeaders['X-CSRF-Token'] = csrfToken;
    } catch (error) {
      // Failed to get CSRF token
      throw new Error('Unable to obtain CSRF token for secure request');
    }
  }

  const config: RequestInit = {
    method,
    credentials: 'same-origin',
    headers: finalHeaders,
    signal
  };

  // ВАЖНО: для GET отключаем кэш браузера, чтобы не получать 304 с ETag
  // Это критично для /api/cart, иначе при 304 JSON отсутствует и обновление доставки срывается
  if (method === 'GET') {
    (config as any).cache = 'no-store';
  }

  // Обработка тела запроса
  if (body !== undefined) {
    if (body instanceof FormData) {
      config.body = body;
      // Удаляем Content-Type для FormData (браузер установит правильный)
      delete (config.headers as Record<string, string>)['Content-Type'];
    } else {
      config.body = JSON.stringify(body);
    }
  }

  // Показываем глобальный спиннер корзины по флагу или авто-правилу (только cart-эндпоинты)
  let cartSpinner: Spinner | null = null;
  try {
    const explicit = options.showCartSpinner;
    const isCartEndpoint = /\/api\/cart(\/|$)/.test(path);
    const shouldShow = explicit === true || (explicit === undefined && isCartEndpoint);
    if (shouldShow) {
      const spinnerRoot = document.getElementById('cart-spinner');
      if (spinnerRoot) {
        // Инициализируем Spinner поверх существующей разметки (overlay: true)
        cartSpinner = new Spinner(spinnerRoot as HTMLElement, { overlay: true, visible: true });
        cartSpinner.show();
      }
    }
  } catch (_) {
    // игнорируем ошибки инициализации спиннера
  }

  try {
    let response = await fetch(url, config);

    // Авто-ретрай на 412/428: обновим ETag через GET /api/cart и повторим один раз
    if ((response.status === 412 || response.status === 428) && method !== 'GET') {
      try {
        const cartRes = await fetch('/api/cart', { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' });
        const newEtag = cartRes.headers.get('ETag');
        if (newEtag) {
          try { sessionStorage.setItem('cart:etag', newEtag); } catch {}
          (config.headers as Record<string, string>)['If-Match'] = newEtag;
          response = await fetch(url, config);
        }
      } catch {}
    }

    if (!response.ok) {
      const errorText = await response.text().catch(() => 'Unknown error');
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    const contentType = response.headers.get('content-type') || '';

    // Поддерживаем application/json и application/ld+json от API Platform
    if (contentType.includes('json')) {
      return await response.json();
    }

    return await response.text() as T;
  } catch (error) {
    if (error instanceof Error) {
      throw error;
    }
    throw new Error('Network error');
  } finally {
    // Скрываем спиннер после завершения запроса
    try { cartSpinner?.hide(); } catch (_) {}
  }
}

/**
 * GET запрос
 */
export async function get<T = any>(
  path: string,
  options: HttpGetOptions & { signal?: AbortSignal } = {}
): Promise<T> {
  return http<T>(path, { ...options, method: 'GET' });
}

/**
 * POST запрос
 */
export async function post<T = any>(
  path: string,
  data?: any,
  options: HttpPostOptions = {}
): Promise<T> {
  return http<T>(path, { ...options, method: 'POST', body: data });
}

/**
 * PATCH запрос
 */
export async function patch<T = any>(
  path: string,
  data?: any,
  options: HttpPatchOptions = {}
): Promise<T> {
  return http<T>(path, { ...options, method: 'PATCH', body: data });
}

/**
 * DELETE запрос
 */
export async function del<T = any>(
  path: string,
  options: HttpDeleteOptions = {}
): Promise<T> {
  return http<T>(path, { ...options, method: 'DELETE' });
}

/**
 * DELETE запрос с возвратом статуса ответа
 */
export async function delWithStatus(
  path: string,
  options: HttpDeleteOptions = {}
): Promise<{ status: number; data?: any }> {
  const { headers = {} } = options;
  const url = base + path;

  // Добавляем CSRF токен для DELETE запросов
  const finalHeaders: Record<string, string> = { ...defaultHeaders, ...headers };
  try {
    const csrfToken = await getCsrfToken();
    finalHeaders['X-CSRF-Token'] = csrfToken;
  } catch (error) {
    // Failed to get CSRF token for DELETE
    throw new Error('Unable to obtain CSRF token for secure request');
  }

  const config: RequestInit = {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: finalHeaders
  };

  try {
    const response = await fetch(url, config);

    if (!response.ok) {
      const errorText = await response.text().catch(() => 'Unknown error');
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }


    return {
      status: response.status,
      data: response.status === 204 ? null : await response.json()
    };
  } catch (error) {
    console.error(`DELETE ${url}: error`, error);
    if (error instanceof Error) {
      throw error;
    }
    throw new Error('Network error');
  }
}
