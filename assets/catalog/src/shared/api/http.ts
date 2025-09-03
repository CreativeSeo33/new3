import type { HttpOptions, HttpGetOptions, HttpPostOptions, HttpPutOptions, HttpPatchOptions, HttpDeleteOptions } from '../types/api';

const base = '';
const defaultHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  'Content-Type': 'application/json'
} as const;

/**
 * Основная функция для HTTP запросов с типизацией
 */
export async function http<T = any>(
  path: string,
  options: HttpOptions = {}
): Promise<T> {
  const { method = 'GET', headers = {}, body } = options;
  const url = base + path;

  const config: RequestInit = {
    method,
    credentials: 'same-origin',
    headers: { ...defaultHeaders, ...headers }
  };

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

  try {
    const response = await fetch(url, config);

    if (!response.ok) {
      const errorText = await response.text().catch(() => 'Unknown error');
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    const contentType = response.headers.get('content-type') || '';

    if (contentType.includes('application/json')) {
      return await response.json();
    }

    return await response.text() as T;
  } catch (error) {
    if (error instanceof Error) {
      throw error;
    }
    throw new Error('Network error');
  }
}

/**
 * GET запрос
 */
export async function get<T = any>(
  path: string,
  options: HttpGetOptions = {}
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

  const config: RequestInit = {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { ...defaultHeaders, ...headers }
  };

  try {
    const response = await fetch(url, config);

    if (!response.ok) {
      const errorText = await response.text().catch(() => 'Unknown error');
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    console.log(`DELETE ${url}: status ${response.status}`);

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
