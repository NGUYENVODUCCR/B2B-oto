export function getApiBase() {
  return window.B2B_CONFIG?.apiBase || '/wp-json/b2b/v1';
}

export function getHomeUrl(path = '') {
  const base = (window.B2B_CONFIG?.homeUrl || window.location.origin).replace(/\/+$/, '');
  const suffix = String(path || '').replace(/^\/+/, '');

  return suffix ? `${base}/${suffix}` : base;
}

export function getToken() {
  return localStorage.getItem('access_token');
}

export function apiUrl(path) {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  return `${getApiBase()}${path.startsWith('/') ? path : `/${path}`}`;
}

export async function apiFetch(url, options = {}) {
  const token = getToken();
  const headers = { ...(options.headers || {}) };
  let body = options.body;

  if (body && !(body instanceof FormData) && typeof body !== 'string') {
    body = JSON.stringify(body);
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
  }

  if (!(body instanceof FormData)) {
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return fetch(apiUrl(url), {
    ...options,
    body,
    headers,
  });
}

export async function apiJson(url, options = {}) {
  const transientStatuses = new Set([502, 503, 504]);
  const method = String(options.method || 'GET').toUpperCase();

  const toError = (response, result) => {
    const error = new Error(result?.message || `API error ${response.status}`);
    error.status = response.status;
    error.payload = result;
    return error;
  };

  let response = await apiFetch(url, options);
  let result = await response.json().catch(() => ({}));

  if (method === 'GET' && transientStatuses.has(response.status)) {
    await new Promise((resolve) => window.setTimeout(resolve, 500));
    response = await apiFetch(url, options);
    result = await response.json().catch(() => ({}));
  }

  if (!response.ok || result.success === false) {
    throw toError(response, result);
  }

  return result.data;
}
