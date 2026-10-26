/**
 * api.js - Klien HTTP untuk Backend MejaKu (admin dashboard).
 * Basis default http://localhost:8000/api, dapat diubah via localStorage.
 * Token Sanctum disimpan di localStorage. Semua kegagalan jaringan/auth
 * dikembalikan sebagai exception agar pemanggil bisa fallback ke data lokal.
 */

const MejaKuAPI = (() => {
  const LS_BASE = 'mejaku_api_base';
  const LS_TOKEN = 'mejaku_admin_token';
  const DEFAULT_BASE = 'http://localhost:8000/api';

  function baseUrl() {
    return (localStorage.getItem(LS_BASE) || DEFAULT_BASE).replace(/\/+$/, '');
  }

  function setBaseUrl(url) {
    localStorage.setItem(LS_BASE, String(url).replace(/\/+$/, ''));
  }

  function getToken() {
    return localStorage.getItem(LS_TOKEN) || '';
  }

  function setToken(token) {
    if (token) localStorage.setItem(LS_TOKEN, token);
    else localStorage.removeItem(LS_TOKEN);
  }

  function isLoggedIn() {
    return getToken().length > 0;
  }

  async function request(path, options = {}) {
    const headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let res;
    try {
      res = await fetch(baseUrl() + path, {
        method: options.method || 'GET',
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
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

  return {
    baseUrl,
    setBaseUrl,
    getToken,
    setToken,
    isLoggedIn,

    login(email, password) {
      return request('/admin/auth/login', { method: 'POST', body: { email, password } });
    },
    logout() {
      return request('/admin/auth/logout', { method: 'POST' });
    },
    me() {
      return request('/admin/auth/me');
    },

    listOrders(params = {}) {
      return request('/admin/orders' + query({ per_page: 50, ...params }));
    },
    getOrder(orderNumber) {
      return request(`/admin/orders/${encodeURIComponent(orderNumber)}`);
    },
    updatePreparation(orderNumber, preparation_status) {
      return request(`/admin/orders/${encodeURIComponent(orderNumber)}/preparation`, {
        method: 'PATCH',
        body: { preparation_status },
      });
    },
    updatePayment(orderNumber, payment_status) {
      return request(`/admin/orders/${encodeURIComponent(orderNumber)}/payment`, {
        method: 'PATCH',
        body: { payment_status },
      });
    },

    getStats(params = {}) {
      return request('/admin/stats' + query(params));
    },

    submitMenuRating(id, payload) {
      return request(`/menu/${id}/rate`, { method: 'POST', body: payload });
    },

    getMenuReviews(id, params = {}) {
      return request(`/menu/${id}/reviews` + query(params));
    },
  };
})();

