<template>
  <div v-if="order" class="modal-backdrop active" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">Detail Pesanan</h3>
          <div class="font-caption" style="color: #71717a;">{{ order.id }}</div>
        </div>
        <button class="modal-close-btn" @click="emit('close')">&times;</button>
      </div>

      <!-- Meja & status -->
      <div class="card-soft" style="margin-bottom: var(--spacing-md); padding: var(--spacing-md);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <span class="badge badge-neutral">{{ order.tableLabel }}</span>
          <span>
            <span class="badge" :class="prepClass" style="margin-right: 4px;">
              <span class="badge-dot"></span>{{ order.prepLabel }}
            </span>
            <span class="badge" :class="payClass">
              <span class="badge-dot"></span>{{ order.payLabel }}
            </span>
          </span>
        </div>
        <div v-if="order.method && order.method !== '-'" class="font-body-sm" style="color: #555; margin-top: 6px;">Metode: {{ order.method }}</div>
      </div>

      <!-- Timeline penyiapan -->
      <div class="font-caption" style="color: #71717a; margin-bottom: var(--spacing-xs);">TAHAP PENYIAPAN</div>
      <div class="top-products-list" style="margin-bottom: var(--spacing-md);">
        <div v-for="(s, i) in stages" :key="i" class="product-row-item">
          <span class="product-name" style="font-size: 13px;">{{ i + 1 }}. {{ s }}</span>
          <span v-if="i < stageIndex" class="badge badge-success">Selesai</span>
          <span v-else-if="i === stageIndex" class="badge badge-info">Aktif</span>
          <span v-else class="badge badge-neutral">Menunggu</span>
        </div>
      </div>

      <!-- Item -->
      <div class="font-caption" style="color: #71717a; margin-bottom: var(--spacing-xs);">ITEM PESANAN</div>
      <table class="receipt-items-table" style="margin-bottom: var(--spacing-md);">
        <thead>
          <tr>
            <th>Item</th>
            <th style="text-align: center;">Qty</th>
            <th style="text-align: right;">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, i) in order.items" :key="i">
            <td>
              <div><strong>{{ it.name }}</strong></div>
              <div v-if="it.detail" class="font-caption" style="color: #71717a;">{{ it.detail }}</div>
            </td>
            <td style="text-align: center;">{{ it.qty }}x</td>
            <td style="text-align: right;">{{ rupiah(it.price * it.qty) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Rincian biaya -->
      <div class="receipt-totals">
        <div class="receipt-totals-row">
          <span>Subtotal:</span>
          <span>{{ rupiah(order.cost.subtotal) }}</span>
        </div>
        <div class="receipt-totals-row">
          <span>Service Fee:</span>
          <span>{{ rupiah(order.cost.serviceFee) }}</span>
        </div>
        <div v-if="order.cost.discount > 0" class="receipt-totals-row">
          <span>Diskon:</span>
          <span>−{{ rupiah(order.cost.discount) }}</span>
        </div>
        <div class="receipt-totals-row">
          <span>Pajak (10%):</span>
          <span>{{ rupiah(order.cost.tax) }}</span>
        </div>
        <div class="receipt-totals-row grand-total">
          <span>TOTAL:</span>
          <span>{{ rupiah(order.cost.total) }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Tutup</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { rupiah } from '../utils/format.js';

const props = defineProps({ order: { type: Object, default: null } });
const emit = defineEmits(['close']);

const stages = ['Pesanan Diterima & Terbayar', 'Sedang Disiapkan di Dapur', 'Siap Disajikan / Dikirim', 'Pesanan Selesai'];
const stageIndex = computed(() => props.order?.stage ?? 0);
const prepClass = computed(() => {
  const m = { completed: 'badge-success', ready: 'badge-info', preparing: 'badge-warning' };
  return m[props.order?.prepKey] || 'badge-neutral';
});
const payClass = computed(() => (props.order?.payKey === 'paid' ? 'badge-success' : 'badge-warning'));
</script>
