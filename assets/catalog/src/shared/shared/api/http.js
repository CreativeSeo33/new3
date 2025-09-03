const base = '/api';
const defaultHeaders = {
  'X-Requested-With': 'XMLHttpRequest',
  'Content-Type': 'application/json'
};

export async function http(path, { method = 'GET', headers = {}, body } = {}) {
  const url = base + path;
  const config = {
    method,
    credentials: 'same-origin',
    headers: { ...defaultHeaders, ...headers }
  };

  if (body) {
    config.body = body instanceof FormData ? body : JSON.stringify(body);
    if (body instanceof FormData) {
      delete config.headers['Content-Type'];
    }
  }

  const res = await fetch(url, config);

  if (!res.ok) {
    const errorText = await res.text().catch(() => 'HTTP Error');
    throw new Error(`HTTP ${res.status}: ${errorText}`);
  }

  const contentType = res.headers.get('content-type') || '';
  return contentType.includes('application/json') ? res.json() : res.text();
}

export async function get(path, options = {}) {
  return http(path, { ...options, method: 'GET' });
}

export async function post(path, data, options = {}) {
  return http(path, { ...options, method: 'POST', body: data });
}

export async function patch(path, data, options = {}) {
  return http(path, { ...options, method: 'PATCH', body: data });
}

export async function del(path, options = {}) {
  return http(path, { ...options, method: 'DELETE' });
}
