<template>
  <section class="page-view active">
    <div class="section-header">
      <div>
        <h1 class="section-title">Kasir & Riwayat Pembayaran</h1>
      </div>
    </div>

    <div v-if="isApi" class="metrics-grid" style="margin-bottom: var(--spacing-xl);">
      <div class="metric-card accent-lime">
        <div class="metric-header">
          <span class="metric-label">Kasir Lunas (Rekap)</span>
          <span class="badge badge-success">{{ recapSummary.paid_count }} trx</span>
        </div>
        <div class="metric-value">{{ rupiah(recapSummary.paid_total) }}</div>
        <div class="metric-footer"><span>Dari endpoint /admin/payments</span></div>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--spacing-xl);">
      <div class="section-header">
        <div>
          <h2 class="font-card-title">Pesanan Menunggu Pembayaran</h2>
          <p class="section-subtitle">Transaksi aktif yang siap diproses dan dicetak struknya.</p>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>No. Pesanan</th>
              <th>Nama Pelanggan</th>
              <th>Jumlah Tagihan</th>
              <th>Status</th>
              <th style="text-align: right;">Tindakan Kasir</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="pending.length === 0">
              <td colspan="5" style="text-align: center; padding: 24px; color: #71717a;">
                {{ isApi ? 'Semua pesanan saat ini telah lunas.' : 'Masuk sebagai admin untuk melihat kasir.' }}
              </td>
            </tr>
            <tr v-for="ord in pending" :key="ord.id">
              <td><strong>{{ ord.id }}</strong></td>
              <td :title="`Meja ${ord.tableCode}`">
                {{ ord.customer !== '-' ? ord.customer : `Meja ${ord.tableCode}` }}
                <div v-if="ord.customer !== '-'" class="font-caption" style="color: #71717a;">Meja {{ ord.tableCode }}</div>
              </td>
              <td><strong>{{ rupiah(ord.total) }}</strong></td>
              <td>
                <span class="badge badge-warning">
                  <span class="badge-dot"></span>
                  {{ PREP_LABEL[ord.prepStatus] || ord.prepStatus }}
                </span>
              </td>
              <td style="text-align: right;">
                <button class="btn btn-primary btn-sm" @click="openQris(ord)">Bayar</button>
                <button class="btn btn-secondary btn-sm" @click="markPaid(ord)">Tandai Lunas</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="section-header">
        <div>
          <h2 class="font-card-title">Riwayat Transaksi Selesai</h2>
          <p class="section-subtitle">Semua pembayaran yang telah lunas dan struk dapat dicetak ulang.</p>
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>No. Pesanan</th>
              <th>Waktu Pembayaran</th>
              <th>Pelanggan</th>
              <th>Metode Pembayaran</th>
              <th>Nominal</th>
              <th style="text-align: right;">Struk</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="paid.length === 0">
              <td colspan="6" style="text-align: center; padding: 24px; color: #71717a;">
                {{ isApi ? 'Belum ada riwayat transaksi lunas.' : 'Masuk sebagai admin untuk melihat riwayat.' }}
              </td>
            </tr>
            <tr v-for="ord in paid" :key="ord.id">
              <td><strong>{{ ord.id }}</strong></td>
              <td class="font-mono" style="font-size: 12px; color: #555;">{{ ord.date }}</td>
              <td :title="`Meja ${ord.tableCode}`">
                {{ ord.customer !== '-' ? ord.customer : `Meja ${ord.tableCode}` }}
                <div v-if="ord.customer !== '-'" class="font-caption" style="color: #71717a;">Meja {{ ord.tableCode }}</div>
              </td>
              <td class="font-mono" style="font-size: 12px;">{{ ord.method || 'Kasir' }}</td>
              <td><strong>{{ rupiah(ord.total) }}</strong></td>
              <td style="text-align: right;">
                <button class="btn btn-secondary btn-sm" @click="showReceipt(ord)">Lihat Struk</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="isApi" class="card">
      <div class="section-header">
        <div>
          <h2 class="font-card-title">Rekap Pembayaran (Backend)</h2>
          <p class="section-subtitle">Semua transaksi pembayaran dari /api/admin/payments.</p>
        </div>
        <div class="topbar-search table-search-input">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="recapSearch" type="text" placeholder="Cari kode / nomor / metode...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Kode Bayar</th>
              <th>No. Pesanan</th>
              <th>Metode</th>
              <th>Nominal</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredRecap.length === 0">
              <td colspan="5" style="text-align: center; padding: 24px; color: #71717a;">Belum ada data pembayaran.</td>
            </tr>
            <tr v-for="p in filteredRecap" :key="p.payment_code">
              <td class="font-mono" style="font-size: 12px;"><strong>{{ p.payment_code }}</strong></td>
              <td>{{ p.order_number }}</td>
              <td class="font-mono" style="font-size: 12px;">{{ p.method_label || p.method }}</td>
              <td><strong>{{ rupiah(p.amount) }}</strong></td>
              <td>
                <span class="badge" :class="p.status === 'paid' ? 'badge-success' : (p.status === 'pending' ? 'badge-warning' : 'badge-danger')">
                  <span class="badge-dot"></span>{{ p.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <QrisModal :open="qrisOpen" :order-number="qrisNumber" :order-total="qrisTotal" @close="qrisOpen = false" @paid="onQrisPaid" />
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { rupiah } from '../utils/format.js';
import { PREP_LABEL, adaptApiOrder, toReceiptOrder } from '../utils/orders.js';
import { session, refreshApi } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';
import QrisModal from '../components/QrisModal.vue';

const emit = defineEmits(['receipt']);
const qrisOpen = ref(false);
const qrisNumber = ref('');
const qrisTotal = ref(0);
const recapRows = ref([]);
const recapSummary = ref({ paid_total: 0, paid_count: 0 });
const recapSearch = ref('');

const filteredRecap = computed(() => {
  const q = (recapSearch.value || session.globalSearch || '').trim().toLowerCase();
  if (!q) return recapRows.value;
  return recapRows.value.filter((p) => `${p.payment_code || ''} ${p.order_number || ''} ${p.method || ''} ${p.method_label || ''}`.toLowerCase().includes(q));
});

const isApi = computed(() => session.loggedIn && session.apiOnline);

async function fetchRecap() {
  if (!isApi.value) {
    recapRows.value = [];
    recapSummary.value = { paid_total: 0, paid_count: 0 };
    return;
  }
  try {
    const res = await api.listPayments();
    recapRows.value = res.data || [];
    recapSummary.value = res.summary || { paid_total: 0, paid_count: 0 };
  } catch (e) {
    notify('Gagal memuat rekap pembayaran: ' + e.message);
  }
}

watch(() => session.apiOnline, fetchRecap, { immediate: true });

const allRows = computed(() => {
  if (!isApi.value) return [];
  return session.apiOrders.map(adaptApiOrder);
});

const pending = computed(() => allRows.value.filter((o) => o.payStatus === 'pending'));
const paid = computed(() => allRows.value.filter((o) => o.payStatus === 'paid'));

async function markPaid(ord) {
  if (!window.confirm(`Tandai pesanan ${ord.id} sebagai LUNAS (kasir)?`)) return;
  try {
    await api.updatePayment(ord.id, 'paid');
    await refreshApi();
    await fetchRecap();
    notifySuccess(`Pesanan ${ord.id} ditandai lunas.`);
  } catch (e) {
    notify('Gagal memperbarui pembayaran: ' + e.message);
  }
}

function openQris(ord) {
  qrisNumber.value = ord.id;
  qrisTotal.value = ord.total || 0;
  qrisOpen.value = true;
}

async function onQrisPaid() {
  await refreshApi();
  await fetchRecap();
}

async function showReceipt(ord) {
  try {
    const res = await api.getOrder(ord.id);
    emit('receipt', toReceiptOrder(adaptApiOrder(res.data)));
  } catch (e) {
    notify('Gagal memuat struk: ' + e.message);
  }
}
</script>
