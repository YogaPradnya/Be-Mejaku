<template>
  <aside class="sidebar" :class="{ open: session.sidebarOpen, collapsed: session.sidebarCollapsed }">
    <div class="sidebar-header">
      <div class="brand-mark">M</div>
      <div class="brand-text">
        <div class="brand-title">Merchants</div>
        <div class="font-caption" style="color: #71717a;">Store System</div>
      </div>
      <button class="btn-icon sidebar-close-btn" aria-label="Tutup Sidebar" @click="session.sidebarOpen = false">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Utama</div>
      <RouterLink
        v-for="item in mainMenu"
        :key="item.route"
        :to="{ name: item.route }"
        class="nav-item"
        active-class="active"
        @click="session.sidebarOpen = false"
      >
        <span class="nav-icon" v-html="item.icon"></span>
        <span class="nav-text">{{ item.label }}</span>
      </RouterLink>

      <div class="nav-section-title" style="margin-top: 16px;">Kelola</div>
      <RouterLink
        v-for="item in manageMenu"
        :key="item.route"
        :to="{ name: item.route }"
        class="nav-item"
        active-class="active"
        @click="session.sidebarOpen = false"
      >
        <span class="nav-icon" v-html="item.icon"></span>
        <span class="nav-text">{{ item.label }}</span>
      </RouterLink>

      <div class="nav-section-title" style="margin-top: 16px;">Transaksi</div>
      <RouterLink :to="{ name: 'payments' }" class="nav-item" active-class="active" @click="session.sidebarOpen = false">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2" /><line x1="1" y1="10" x2="23" y2="10" /></svg>
        </span>
        <span class="nav-text">Kasir & Pembayaran</span>
      </RouterLink>
    </nav>

    <div class="sidebar-footer">
      <div class="user-avatar">{{ session.loggedIn ? 'ADM' : 'GST' }}</div>
      <div class="user-info">
        <div class="user-name">{{ session.loggedIn ? 'Administrator' : 'Tamu (Lokal)' }}</div>
        <div class="user-role">Kasir & Toko</div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { session } from '../store/session.js';

const mainMenu = [
  {
    route: 'overview',
    label: 'Ringkasan',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'
  },
  {
    route: 'orders',
    label: 'Pesanan',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>'
  },
  {
    route: 'stock',
    label: 'Stok Barang',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'
  },
  {
    route: 'menu-management',
    label: 'Kelola Menu',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'
  }
];

const manageMenu = [
  {
    route: 'tables',
    label: 'Kelola Meja',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><line x1="3" y1="11" x2="21" y2="11"/><line x1="7" y1="7" x2="7" y2="17"/><line x1="17" y1="7" x2="17" y2="17"/></svg>'
  },
  {
    route: 'promos',
    label: 'Kelola Promo',
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L11 3H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>'
  }
];
</script>
