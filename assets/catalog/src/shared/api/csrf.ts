/**
 * CSRF токен сервис для защиты API запросов
 */

interface CsrfResponse {
  csrfToken: string;
}

let csrfToken: string | null = null;
let tokenPromise: Promise<string> | null = null;

/**
 * Получает CSRF токен с сервера
 */
async function fetchCsrfToken(): Promise<string> {
  const response = await fetch('/api/csrf', {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch CSRF token: ${response.status}`);
  }

  const data: CsrfResponse = await response.json();
  return data.csrfToken;
}

/**
 * Получает CSRF токен (кеширует его для повторного использования)
 */
export async function getCsrfToken(): Promise<string> {
  // Если токен уже есть, возвращаем его
  if (csrfToken) {
    return csrfToken;
  }

  // Если запрос уже в процессе, ждем его
  if (tokenPromise) {
    return tokenPromise;
  }

  // Запрашиваем новый токен
  tokenPromise = fetchCsrfToken()
    .then(token => {
      csrfToken = token;
      tokenPromise = null;
      return token;
    })
    .catch(error => {
      tokenPromise = null;
      throw error;
    });

  return tokenPromise;
}

/**
 * Очищает кеш CSRF токена (принудительно получит новый при следующем запросе)
 */
export function clearCsrfToken(): void {
  csrfToken = null;
  tokenPromise = null;
}

/**
 * Проверяет, нужен ли CSRF токен для данного метода
 */
export function requiresCsrfToken(method: string): boolean {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());
}
