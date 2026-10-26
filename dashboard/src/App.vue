<template>
  <!-- Halaman publik / mandiri: tanpa sidebar/topbar (Login, Customer) -->
  <div v-if="route.meta.public" class="public-shell" :class="{ 'customer-shell': route.name === 'customer-order' }">
    <RouterView />
    <AlertPopup />
  </div>
  <div v-else class="app-container">
    <div class="sidebar-backdrop" :class="{ active: session.sidebarOpen }" @click="session.sidebarOpen = false"></div>

    <Sidebar />

    <div class="main-wrapper" :class="{ expanded: session.sidebarCollapsed }">
      <Topbar :title="route.meta.title || 'Ringkasan'" />
      <main class="content-area">
        <RouterView @receipt="receiptOrder = $event" />
      </main>
    </div>

    <ReceiptModal :order="receiptOrder" @close="receiptOrder = null" />
    <AlertPopup />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from './components/Sidebar.vue';
import Topbar from './components/Topbar.vue';
import ReceiptModal from './components/ReceiptModal.vue';
import AlertPopup from './components/AlertPopup.vue';
import { session, checkSession } from './store/session.js';

const route = useRoute();
const receiptOrder = ref(null);

onMounted(() => {
  checkSession();
});
</script>

<style>
.public-shell {
  width: 100%;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-surface-soft, #fafafa);
}

.public-shell.customer-shell {
  display: block;
}
</style>
