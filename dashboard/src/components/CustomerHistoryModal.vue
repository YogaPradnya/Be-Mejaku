<template>
  <div v-if="open" class="modal-backdrop active" @click.self="emit('close')">
    <div class="modal-card history-modal">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">Riwayat Pesanan Saya</h3>
          <div class="font-caption" style="color: #71717a;">Daftar pesanan dari perangkat ini</div>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">&times;</button>
      </div>

      <div class="history-content">
        <div v-if="orders.length === 0" class="history-empty">
          <p class="font-body-sm" style="color: #71717a;">Belum ada riwayat pesanan di perangkat ini.</p>
        </div>

        <div v-else class="history-list">
          <div v-for="ord in orders" :key="ord.order_number" class="history-item card-soft">
            <div class="history-item-top">
              <div>
                <strong class="font-mono" style="font-size: 13px;">{{ ord.order_number }}</strong>
                <span v-if="ord.table_code && ord.table_code !== '-'" class="badge badge-neutral" style="margin-left: 6px;">
                  Meja {{ ord.table_code }}
                </span>
              </div>
              <div class="history-badges">
                <span class="badge" :class="payBadgeClass(ord.payment_status)">
                  {{ payLabel(ord.payment_status) }}
                </span>
                <span class="badge" :class="prepBadgeClass(ord.preparation_status)">
                  {{ prepLabel(ord.preparation_status) }}
                </span>
              </div>
            </div>

            <div class="history-item-summary font-caption" style="color: #52525b; margin: 6px 0;">
              {{ ord.items_summary || (ord.items_count ? ord.items_count + ' hidangan' : '-') }}
            </div>

            <div class="history-item-bottom">
              <div>
                <span class="font-caption" style="color: #71717a;">{{ formatDate(ord.created_at) }}</span>
                <div class="history-price"><strong>{{ rupiah(ord.total) }}</strong></div>
              </div>

              <div class="history-actions">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  @click="emit('selectOrder', ord.order_number)"
                >
                  Pantau Pesanan →
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer" style="margin-top: 16px;">
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Tutup</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { rupiah } from '../utils/format.js';

defineProps({
  open: { type: Boolean, default: false },
  orders: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'selectOrder']);

function payLabel(status) {
  const map = {
    pending: 'Belum Bayar',
    checking: 'Dicek',
    paid: 'Lunas',
    failed: 'Gagal',
    expired: 'Kedaluarsa',
    cancelled: 'Batal'
  };
  return map[status] || status || '-';
}

function payBadgeClass(status) {
  return status === 'paid' ? 'badge-success' : 'badge-warning';
}

function prepLabel(status) {
  const map = {
    received: 'Diterima',
    preparing: 'Disiapkan',
    ready: 'Siap',
    completed: 'Selesai'
  };
  return map[status] || status || '-';
}

function prepBadgeClass(status) {
  if (status === 'completed') return 'badge-success';
  if (status === 'ready' || status === 'preparing') return 'badge-info';
  return 'badge-neutral';
}

function formatDate(isoStr) {
  if (!isoStr) return '-';
  try {
    const d = new Date(isoStr);
    return d.toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return isoStr;
  }
}
</script>

<style scoped>
.history-modal {
  max-width: 520px;
  width: 92vw;
}

.history-content {
  max-height: 420px;
  overflow-y: auto;
  padding-right: 4px;
}

.history-empty {
  text-align: center;
  padding: 32px 0;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.history-item {
  padding: 12px 14px;
}

.history-item-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.history-badges {
  display: flex;
  gap: 4px;
}

.history-item-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 8px;
  padding-top: 6px;
  border-top: 1px dashed var(--color-hairline);
}

.history-price {
  font-size: 14px;
  margin-top: 2px;
}

.history-actions {
  display: flex;
  gap: 6px;
}
</style>
