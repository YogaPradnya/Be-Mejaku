<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="btn-icon" aria-label="Toggle Sidebar" title="Buka/Tutup Sidebar" @click="toggleSidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
      </button>
      <div class="topbar-page-breadcrumb">
        <span class="font-mono breadcrumb-label" style="font-size: 11px; color: #71717a; text-transform: uppercase;">Halaman</span>
        <span class="topbar-page-title">{{ title }}</span>
      </div>
    </div>

    <div class="topbar-right">
      <div class="topbar-search-wrapper">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input type="text" class="form-input search-input" :value="session.globalSearch" placeholder="Cari di toko..." @input="session.globalSearch = $event.target.value">
      </div>

      <button
        type="button"
        class="btn-store-toggle"
        :class="session.storeOpen ? 'open' : 'closed'"
        :title="session.storeOpen ? 'Status: Toko Buka (Klik untuk menutup toko)' : 'Status: Toko Tutup (Klik untuk membuka toko)'"
        @click="toggleStore"
      >
        <span class="store-status-dot"></span>
        <span>{{ session.storeOpen ? 'Toko Buka' : 'Toko Tutup' }}</span>
      </button>

      <button type="button" class="btn btn-secondary btn-sm" @click="authAction">{{ session.loggedIn ? 'Keluar' : 'Masuk' }}</button>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { session, logout } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';

defineProps({ title: { type: String, default: 'Ringkasan' } });
const router = useRouter();

function toggleSidebar() {
  if (window.innerWidth <= 768) session.sidebarOpen = !session.sidebarOpen;
  else session.sidebarCollapsed = !session.sidebarCollapsed;
}

async function toggleStore() {
  if (!session.loggedIn) {
    notify('Masuk sebagai admin untuk mengubah status toko.');
    router.push({ path: '/login', query: { redirect: window.location.hash.replace(/^#/, '') || '/overview' } });
    return;
  }
  try {
    const res = await api.setStoreStatus(!session.storeOpen);
    session.storeOpen = !!res.data.is_open;
    session.apiOnline = true;
  } catch (e) {
    notify('Gagal mengubah status toko: ' + e.message);
  }
}

async function authAction() {
  if (session.loggedIn) {
    if (!window.confirm('Keluar dari dashboard?')) return;
    await logout();
    notify('Anda telah keluar.');
  } else {
    router.push({ path: '/login', query: { redirect: window.location.hash.replace(/^#/, '') || '/overview' } });
  }
}
</script>

<style scoped>
.topbar-search-wrapper {
  position: relative;
  width: 260px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #71717a;
  pointer-events: none;
}

.search-input {
  padding-left: 36px;
  border-radius: var(--rounded-pill);
  height: 36px;
  padding-top: 6px;
  padding-bottom: 6px;
}

.btn-store-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: var(--rounded-pill);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: var(--text-button-weight);
  white-space: nowrap;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.btn-store-toggle.open {
  background-color: var(--color-block-mint);
  color: var(--color-ink);
}

.btn-store-toggle.closed {
  background-color: var(--color-block-pink);
  color: var(--color-ink);
}

@media (max-width: 768px) {
  .topbar-search-wrapper {
    display: none;
  }
  .btn-store-toggle {
    padding: 6px 12px;
    font-size: 12px;
  }
}
</style>
