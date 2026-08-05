/**
 * Cliente HTTP centralizado para la SPA iStore.
 *
 * Todas las rutas de API pasan por el origen actual para usar los rewrites de
 * Vercel. El Bearer almacenado se conserva solo mientras dura la transición
 * a sesiones stateful; la autenticación de la interfaz no depende de él.
 */

const API_BASE = '/api';
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
let csrfPromise = null;

function getSessionId() {
  let sessionId = localStorage.getItem('istore_session_id');
  if (!sessionId) {
    sessionId = crypto.randomUUID();
    localStorage.setItem('istore_session_id', sessionId);
  }
  return sessionId;
}

function getToken() {
  return localStorage.getItem('token_istore') || localStorage.getItem('cliente_token');
}

function getXsrfToken() {
  if (typeof document === 'undefined') {
    return null;
  }

  const cookie = document.cookie
    .split('; ')
    .find((entry) => entry.startsWith('XSRF-TOKEN='));

  if (!cookie) {
    return null;
  }

  const value = cookie.slice('XSRF-TOKEN='.length);

  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}


export async function ensureCsrf() {
  const existingToken = getXsrfToken();
  if (existingToken) {
    return existingToken;
  }

  if (!csrfPromise) {
    csrfPromise = fetch(CSRF_COOKIE_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
      .then((response) => {
        if (!response.ok) {
          const error = new Error('No se pudo inicializar la protección CSRF.');
          error.status = response.status;
          throw error;
        }

        const token = getXsrfToken();
        if (!token) {
          throw new Error('El servidor no entregó la cookie XSRF-TOKEN.');
        }

        return token;
      })
      .finally(() => {
        csrfPromise = null;
      });
  }

  return csrfPromise;
}

function apiUrl(endpoint) {
  const path = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

  return path === API_BASE || path.startsWith(`${API_BASE}/`)
    ? path
    : `${API_BASE}${path}`;
}

async function apiRequest(endpoint, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const xsrfToken = MUTATING_METHODS.has(method) ? await ensureCsrf() : getXsrfToken();
  const token = getToken();
  const sessionId = getSessionId();

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Session-Id': sessionId,
    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(apiUrl(endpoint), {
    ...options,
    headers,
    credentials: 'include',
  });

  if (response.status === 204) {
    return { ok: true, data: null, status: 204 };
  }

  if (response.status === 401 && token) {
    localStorage.removeItem('token_istore');
    localStorage.removeItem('cliente_token');
    localStorage.removeItem('role_istore');
    localStorage.removeItem('usuario_istore');

    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('auth:expired'));
    }

    const error = new Error('Sesión expirada. Por favor inicia sesión nuevamente.');
    error.status = 401;
    throw error;
  }

  const data = await response.json();

  if (!response.ok) {
    const error = new Error(data.error || data.message || 'Error del servidor');
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

const api = {
  get: (endpoint) => apiRequest(endpoint),

  post: (endpoint, body) => apiRequest(endpoint, {
    method: 'POST',
    body: JSON.stringify(body),
  }),

  put: (endpoint, body) => apiRequest(endpoint, {
    method: 'PUT',
    body: JSON.stringify(body),
  }),

  patch: (endpoint, body) => apiRequest(endpoint, {
    method: 'PATCH',
    body: JSON.stringify(body),
  }),

  delete: (endpoint) => apiRequest(endpoint, {
    method: 'DELETE',
  }),

  ensureCsrf,
  getSessionId,
  getToken,
  getXsrfToken,
  API_BASE,
};

export default api;
