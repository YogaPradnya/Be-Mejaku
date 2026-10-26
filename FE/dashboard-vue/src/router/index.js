import { createRouter, createWebHashHistory } from 'vue-router';
import Overview from '../views/Overview.vue';
import Orders from '../views/Orders.vue';
import Stock from '../views/Stock.vue';
import MenuManagement from '../views/MenuManagement.vue';
import Tables from '../views/Tables.vue';
import Promos from '../views/Promos.vue';
import Payments from '../views/Payments.vue';

import CustomerOrder from '../views/CustomerOrder.vue';
import Login from '../views/Login.vue';
import { isLoggedIn } from '../api/client.js';

const routes = [
  {
    path: '/',
    redirect: () => (isLoggedIn() ? '/overview' : '/login')
  },
  { path: '/login', name: 'login', component: Login, meta: { title: 'Masuk Akun', public: true } },
  { path: '/pesan/:code', name: 'customer-order', component: CustomerOrder, meta: { title: 'Pesan di Meja', public: true } },
  { path: '/overview', name: 'overview', component: Overview, meta: { title: 'Ringkasan' } },
  { path: '/orders', name: 'orders', component: Orders, meta: { title: 'Pesanan' } },
  { path: '/stock', name: 'stock', component: Stock, meta: { title: 'Stok Barang' } },
  { path: '/menu-management', name: 'menu-management', component: MenuManagement, meta: { title: 'Kelola Menu' } },
  { path: '/tables', name: 'tables', component: Tables, meta: { title: 'Kelola Meja' } },
  { path: '/promos', name: 'promos', component: Promos, meta: { title: 'Kelola Promo' } },
  { path: '/payments', name: 'payments', component: Payments, meta: { title: 'Kasir & Pembayaran' } }
];

// Hash history agar hasil build tetap jalan dari file/preview statis.
const router = createRouter({ history: createWebHashHistory(), routes });

router.beforeEach((to, from, next) => {
  const loggedIn = isLoggedIn();

  // Jika sudah login dan mencoba mengakses halaman login, alihkan ke dashboard overview
  if (to.name === 'login' && loggedIn) {
    return next({ path: '/overview' });
  }

  // Jika rute memerlukan autentikasi (bukan public) dan belum login, lempar ke /login
  if (!to.meta.public && !loggedIn) {
    return next({
      path: '/login',
      query: to.fullPath && to.fullPath !== '/' ? { redirect: to.fullPath } : {}
    });
  }

  next();
});

export default router;
