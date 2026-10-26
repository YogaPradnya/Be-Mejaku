<template>
  <div class="alert-stack" aria-live="polite">
    <div
      v-for="a in alerts.items"
      :key="a.id"
      class="alert-card"
      :class="a.type === 'success' ? 'alert-success' : 'alert-error'"
      role="alert"
    >
      <span class="badge" :class="a.type === 'success' ? 'badge-success' : 'badge-danger'">
        <span class="badge-dot"></span>
        {{ a.type === 'success' ? 'OK' : 'Error' }}
      </span>
      <span class="alert-msg">{{ a.message }}</span>
      <button class="modal-close-btn alert-x" aria-label="Tutup notifikasi" @click="dismiss(a.id)">&times;</button>
    </div>
  </div>
</template>

<script setup>
import { alerts, dismiss } from '../store/alert.js';
</script>

<style scoped>
.alert-stack {
  position: fixed;
  right: var(--spacing-lg);
  bottom: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  z-index: 2000;
  max-width: min(420px, calc(100vw - 32px));
}
.alert-card {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
  background-color: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
  box-shadow: var(--shadow-modal);
  padding: var(--spacing-sm) var(--spacing-md);
}
.alert-success {
  border-color: var(--color-semantic-success);
}
.alert-error {
  border-color: var(--color-semantic-danger);
}
.alert-msg {
  flex: 1;
  font-size: 13px;
  line-height: 1.45;
}
.alert-x {
  font-size: 18px;
  line-height: 1;
}
</style>
