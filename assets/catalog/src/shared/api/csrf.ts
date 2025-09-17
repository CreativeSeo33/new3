/**
 * CSRF токен сервис для защиты API запросов
 */

interface CsrfResponse {
  csrfToken: string;
}

let csrfToken: string | null = null;
let tokenPromise: Promise<string> | null = null;

function readTokenFromDom(): string | null {
  try {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    return meta?.content?.trim() || null;
  } catch {
    return null;
  }
}

function readTokenFromStorage(): string | null {
  try {
    return sessionStorage.getItem('csrf:api');
  } catch {
    return null;
  }
}

function writeTokenToStorage(token: string): void {
  try {
    sessionStorage.setItem('csrf:api', token);
  } catch {}
}

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

  // Пробуем sessionStorage
  const stored = readTokenFromStorage();
  if (stored) {
    csrfToken = stored;
    return csrfToken;
  }

  // Пробуем meta-тег, вставленный Twig
  const dom = readTokenFromDom();
  if (dom) {
    csrfToken = dom;
    writeTokenToStorage(dom);
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
      writeTokenToStorage(token);
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
  try { sessionStorage.removeItem('csrf:api'); } catch {}
}

/**
 * Проверяет, нужен ли CSRF токен для данного метода
 */
export function requiresCsrfToken(method: string): boolean {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());
}
