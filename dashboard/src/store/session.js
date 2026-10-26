/** Status sesi admin + data API (dibagi ke semua view). */
import { reactive } from 'vue';
import { api, setToken, isLoggedIn } from '../api/client.js';

export const session = reactive({
  loggedIn: false,
  apiOnline: false,
  apiOrders: [],
  stats: null,
  storeOpen: true,
  sidebarOpen: false,
  sidebarCollapsed: false,
  globalSearch: ''
});

export async function checkSession() {
  if (!isLoggedIn()) {
    session.loggedIn = false;
    session.apiOnline = false;
    return;
  }
  try {
    await api.me();
    session.loggedIn = true;
    await Promise.all([refreshApi(), fetchStore()]);
  } catch (e) {
    setToken('');
    session.loggedIn = false;
    session.apiOnline = false;
  }
}

export async function fetchStore() {
  try {
    const res = await api.getStoreStatus();
    session.storeOpen = !!res.data.is_open;
  } catch (e) { /* biarkan status terakhir */ }
}

export async function refreshApi() {
  try {
    const [ordersRes, statsRes] = await Promise.all([api.listOrders(), api.getStats()]);
    session.apiOrders = ordersRes.data || [];
    session.stats = statsRes.data || null;
    session.apiOnline = true;
  } catch (e) {
    if (e && e.code === 'UNAUTHENTICATED') {
      setToken('');
      session.loggedIn = false;
    }
    session.apiOnline = false;
  }
}

export async function logout() {
  try {
    await api.logout();
  } catch (e) { /* token tetap dibuang lokal */ }
  setToken('');
  session.loggedIn = false;
  session.apiOnline = false;
  session.apiOrders = [];
  session.stats = null;
}
