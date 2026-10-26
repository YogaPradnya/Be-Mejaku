/**
 * Klien HTTP Backend MejaKu (port dari FE/js/api.js).
 * Base URL default memakai proxy dev Vite (/api) bila tersedia,
 * fallback ke http://localhost:8000/api. Bisa diubah & tersimpan.
 */

const LS_BASE = 'mejaku_api_base';
const LS_TOKEN = 'mejaku_admin_token';

function defaultBase() {
  // Saat jalan via `vite dev`, /api di-proxy ke Laravel (bebas CORS).
  if (typeof window !== 'undefined' && window.location.port === '5173') return '/api';
  return 'http://localhost:8000/api';
}

export function baseUrl() {
  return (localStorage.getItem(LS_BASE) || defaultBase()).replace(/\/+$/, '');
}

export function setBaseUrl(url) {
  localStorage.setItem(LS_BASE, String(url).replace(/\/+$/, ''));
}

export function getToken() {
  return localStorage.getItem(LS_TOKEN) || '';
}

export function setToken(token) {
  if (token) localStorage.setItem(LS_TOKEN, token);
  else localStorage.removeItem(LS_TOKEN);
}

export function isLoggedIn() {
  return getToken().length > 0;
}

async function request(path, options = {}) {
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;

  let res;
  try {
    res = await fetch(baseUrl() + path, {
      method: options.method || 'GET',
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined
    });
  } catch (e) {
    const err = new Error('Tidak dapat menjangkau API backend.');
    err.code = 'NETWORK';
    throw err;
  }

  let data = null;
  try {
    data = await res.json();
  } catch (e) {
    data = null;
  }

  if (!res.ok) {
    const err = new Error((data && data.message) || `API error ${res.status}`);
    err.code = res.status === 401 ? 'UNAUTHENTICATED' : 'API_ERROR';
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

function query(params = {}) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') q.append(k, v);
  });
  const s = q.toString();
  return s ? `?${s}` : '';
}

export const api = {
  baseUrl,
  setBaseUrl,
  getToken,
  setToken,
  isLoggedIn,
  login: (email, password) => request('/admin/auth/login', { method: 'POST', body: { email, password } }),
  logout: () => request('/admin/auth/logout', { method: 'POST' }),
  me: () => request('/admin/auth/me'),
  listOrders: (params = {}) => request('/admin/orders' + query({ per_page: 50, ...params })),
  getOrder: (orderNumber, fresh = false) => request(`/admin/orders/${encodeURIComponent(orderNumber)}${fresh ? '?fresh=1' : ''}`),
  updatePreparation: (orderNumber, preparation_status) =>
    request(`/admin/orders/${encodeURIComponent(orderNumber)}/preparation`, { method: 'PATCH', body: { preparation_status } }),
  updatePayment: (orderNumber, payment_status) =>
    request(`/admin/orders/${encodeURIComponent(orderNumber)}/payment`, { method: 'PATCH', body: { payment_status } }),
  cancelOrder: (orderNumber) =>
    request(`/admin/orders/${encodeURIComponent(orderNumber)}/cancel`, { method: 'POST' }),
  getStats: (params = {}) => request('/admin/stats' + query(params)),

  // ---- Menu ----
  listMenus: (params = {}) => request('/admin/menu' + query({ per_page: 50, ...params })),
  getMenu: (id) => request(`/admin/menu/${id}`),
  createMenu: (payload) => request('/admin/menu', { method: 'POST', body: payload }),
  updateMenu: (id, payload) => request(`/admin/menu/${id}`, { method: 'PUT', body: payload }),
  toggleMenu: (id) => request(`/admin/menu/${id}/toggle`, { method: 'PATCH' }),
  deleteMenu: (id) => request(`/admin/menu/${id}`, { method: 'DELETE' }),
  // Upload foto menu ke Cloudinary via backend (multipart, bukan JSON).
  uploadMenuImage: async (file) => {
    const form = new FormData();
    form.append('image', file);
    const headers = { Accept: 'application/json' };
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
    let res;
    try {
      res = await fetch(baseUrl() + '/admin/menu/upload-image', { method: 'POST', headers, body: form });
    } catch (e) {
      throw new Error('Tidak dapat menjangkau API backend.');
    }
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    if (!res.ok) {
      const err = new Error((data && data.message) || `API error ${res.status}`);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  },

  // ---- Merchant ----
  listMerchants: (params = {}) => request('/admin/merchants' + query({ per_page: 50, ...params })),

  // ---- Meja ----
  listTables: (params = {}) => request('/admin/tables' + query({ per_page: 50, ...params })),
  createTable: (payload) => request('/admin/tables', { method: 'POST', body: payload }),
  updateTable: (id, payload) => request(`/admin/tables/${id}`, { method: 'PUT', body: payload }),
  toggleTable: (id) => request(`/admin/tables/${id}/toggle`, { method: 'PATCH' }),
  deleteTable: (id) => request(`/admin/tables/${id}`, { method: 'DELETE' }),

  // ---- Promo ----
  listPromos: (params = {}) => request('/admin/promos' + query({ per_page: 50, ...params })),
  createPromo: (payload) => request('/admin/promos', { method: 'POST', body: payload }),
  updatePromo: (id, payload) => request(`/admin/promos/${id}`, { method: 'PUT', body: payload }),
  deletePromo: (id) => request(`/admin/promos/${id}`, { method: 'DELETE' }),

  // ---- Kasir / pembayaran (endpoint customer, dipakai kasir QRIS) ----
  listPayments: (params = {}) => request('/admin/payments' + query({ per_page: 20, ...params })),
  createPayment: (orderNumber, method, extra = {}) => request(`/payments/${encodeURIComponent(orderNumber)}`, { method: 'POST', body: { method, ...extra } }),
  paymentStatus: (orderNumber) => request(`/payments/${encodeURIComponent(orderNumber)}/status`),
  simulatePayment: (orderNumber, action) => request(`/payments/${encodeURIComponent(orderNumber)}/simulate`, { method: 'POST', body: { action } }),

  // ---- Customer publik (tanpa login, dipakai halaman pesan meja) ----
  getTablePublic: (code) => request(`/tables/${encodeURIComponent(code)}`),
  listPublicMenu: () => request('/menu'),
  getPublicMenu: (slug) => request(`/menu/${encodeURIComponent(slug)}`),
  createPublicOrder: (payload) => request('/orders', { method: 'POST', body: payload }),
  getPublicOrder: (orderNumber, fresh = false) => request(`/orders/${encodeURIComponent(orderNumber)}${fresh ? '?fresh=1' : ''}`),
  submitMenuRating: (id, payload) => request(`/menu/${id}/rate`, { method: 'POST', body: payload }),
  getMenuReviews: (id, params = {}) => request(`/menu/${id}/reviews` + query(params)),

  // ---- Toko ----
  getStoreStatus: () => request('/admin/store/status'),
  setStoreStatus: (isOpen) => request('/admin/store/status', { method: 'PUT', body: { is_open: isOpen } })
};

