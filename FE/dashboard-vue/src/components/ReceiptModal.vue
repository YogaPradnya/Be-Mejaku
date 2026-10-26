<template>
  <div v-if="order" class="modal-backdrop active" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">Struk Transaksi</h3>
        <button class="modal-close-btn" @click="emit('close')">&times;</button>
      </div>

      <div id="receipt-printable-area">
        <div class="receipt-wrapper">
          <div class="receipt-header">
            <div class="receipt-store-title">MERCHANTS STORE</div>
            <div class="receipt-store-sub">Jl. Perintis Kemerdekaan No. 42</div>
            <div class="receipt-store-sub">Telp: 0812-3456-7890</div>
          </div>

          <div class="receipt-meta-grid">
            <div class="receipt-meta-row">
              <span>No. Faktur:</span>
              <strong>{{ order.id }}</strong>
            </div>
            <div class="receipt-meta-row">
              <span>Waktu:</span>
              <span>{{ order.date || '-' }}</span>
            </div>
            <div class="receipt-meta-row">
              <span>Pelanggan:</span>
              <span>{{ order.customer }}</span>
            </div>
            <div class="receipt-meta-row">
              <span>Metode:</span>
              <span>{{ order.method || '-' }}</span>
            </div>
          </div>

          <table class="receipt-items-table">
            <thead>
              <tr>
                <th>Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in (order.items || [])" :key="i">
                <td>{{ item.name }}</td>
                <td style="text-align: center;">{{ item.qty }}x</td>
                <td style="text-align: right;">{{ rupiah(item.price * item.qty) }}</td>
              </tr>
            </tbody>
          </table>

          <div class="receipt-totals">
            <div class="receipt-totals-row">
              <span>Subtotal:</span>
              <span>{{ rupiah(order.subtotal ?? order.total) }}</span>
            </div>
            <div v-if="(order.discount || 0) > 0" class="receipt-totals-row">
              <span>Diskon:</span>
              <span>-{{ rupiah(order.discount) }}</span>
            </div>
            <div class="receipt-totals-row">
              <span>Service fee:</span>
              <span>{{ rupiah(order.serviceFee || 0) }}</span>
            </div>
            <div class="receipt-totals-row">
              <span>Pajak (10%):</span>
              <span>{{ rupiah(order.tax || 0) }}</span>
            </div>
            <div class="receipt-totals-row grand-total">
              <span>TOTAL:</span>
              <span>{{ rupiah(order.total) }}</span>
            </div>
            <div v-if="order.paidAmount > 0" class="receipt-totals-row">
              <span>Bayar Tunai:</span>
              <span>{{ rupiah(order.paidAmount) }}</span>
            </div>
            <div v-if="order.paidAmount > 0" class="receipt-totals-row">
              <span>Kembalian:</span>
              <span>{{ rupiah(order.changeAmount || 0) }}</span>
            </div>
          </div>

          <div class="receipt-footer">
            <div>Terima kasih atas kunjungan Anda!</div>
            <div>Barang yang dibeli tidak dapat ditukar</div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Tutup</button>
        <button type="button" class="btn btn-primary btn-sm" @click="window.print()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
          Cetak Struk
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { rupiah } from '../utils/format.js';

defineProps({ order: { type: Object, default: null } });
const emit = defineEmits(['close']);
</script>
