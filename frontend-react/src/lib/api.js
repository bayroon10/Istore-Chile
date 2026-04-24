/**
 * API Client centralizado para iStore.
 *
 * Agrega automáticamente:
 * - Authorization: Bearer <token>  (si el usuario está logueado)
 * - X-Session-Id: <uuid>           (para carritos de invitado)
 * - Accept: application/json
 * - Content-Type: application/json
 */

const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
// -------------------------------------------------------
// Session ID para guest carts
// -------------------------------------------------------
function getSessionId() {
  let sessionId = localStorage.getItem('istore_session_id');
  if (!sessionId) {
    sessionId = crypto.randomUUID();
    localStorage.setItem('istore_session_id', sessionId);
  }
  return sessionId;
}

// -------------------------------------------------------
// Token helpers
// -------------------------------------------------------
function getToken() {
  // Unificamos: usamos un solo key para TODOS los tokens
  return localStorage.getItem('token_istore') || localStorage.getItem('cliente_token');
}

// -------------------------------------------------------
// Core fetch wrapper
// -------------------------------------------------------
async function apiRequest(endpoint, options = {}) {
  const token = getToken();
  const sessionId = getSessionId();

  const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Session-Id': sessionId,
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
  });

  // Si el servidor devolvió 204 No Content, retornamos null
  if (response.status === 204) {
    return { ok: true, data: null, status: 204 };
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

// -------------------------------------------------------
// Métodos HTTP públicos
// -------------------------------------------------------
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

  delete: (endpoint) => apiRequest(endpoint, {
    method: 'DELETE',
  }),

  // Exportar helpers para uso externo
  getSessionId,
  getToken,
  API_BASE,
};

export default api;
