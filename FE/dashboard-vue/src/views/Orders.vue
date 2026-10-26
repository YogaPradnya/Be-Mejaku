<template>
  <section class="page-view active">
    <div class="section-header align-inline">
      <div>
        <h1 class="section-title">Daftar Pesanan</h1>
      </div>
      <button class="btn btn-primary btn-sm" :disabled="!session.loggedIn" @click="openAddOrder">+ Tambah Pesanan</button>
    </div>

    <div class="card">
      <div class="table-toolbar">
        <div class="tab-group">
          <button
            v-for="t in tabs"
            :key="t.value"
            class="tab-btn"
            :class="{ active: filter === t.value }"
            @click="filter = t.value"
          >{{ t.label }}</button>
        </div>

        <div class="search-wrapper">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="search" type="text" class="form-input search-input" placeholder="Cari nomor pesanan / nama...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th class="col-id">No. Pesanan</th>
              <th class="col-time">Waktu</th>
              <th class="col-customer">Pelanggan</th>
              <th class="col-table">Meja</th>
              <th class="col-items">Rincian Pesanan</th>
              <th class="col-total">Total Biaya</th>
              <th class="col-status">Status</th>
              <th class="col-method">Metode</th>
              <th class="col-action text-left">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="9" class="text-center empty-state">
                {{ session.loggedIn ? 'Tidak ada data pesanan yang cocok.' : 'Masuk sebagai admin untuk melihat pesanan.' }}
              </td>
            </tr>
            <tr v-for="order in rows" :key="order.id">
              <td class="col-id font-bold">{{ order.id }}</td>
              <td class="col-time font-mono text-sm">{{ order.date }}</td>
              <td class="col-customer" :title="order.customer"><span class="text-truncate">{{ order.customer }}</span></td>
              <td class="col-table">
                <span class="badge badge-neutral">{{ order.tableCode }}{{ order.tableFloor ? ' • ' + order.tableFloor : '' }}</span>
              </td>
              <td class="col-items" :title="order.itemsSummary"><span class="text-truncate">{{ order.itemsSummary }}</span></td>
              <td class="col-total font-bold">{{ rupiah(order.total) }}</td>
              <td class="col-status">
                <div class="status-stack">
                  <span class="badge" :class="prepClass(order.prepStatus)" title="Status dapur">
                    <span class="badge-dot"></span>
                    {{ PREP_LABEL[order.prepStatus] || order.prepStatus }}
                  </span>
                  <span class="badge" :class="payClass(order.payStatus)" title="Status pembayaran">
                    <span class="badge-dot"></span>
                    {{ PAY_LABEL[order.payStatus] || order.payStatus }}
                  </span>
                </div>
              </td>
              <td class="col-method font-mono text-sm">{{ order.method || '-' }}</td>
              <td class="col-action text-left">
                <div class="action-buttons">
                  <button v-if="PREP_NEXT[order.prepStatus]" class="btn btn-primary btn-sm" @click="advance(order)">{{ PREP_NEXT[order.prepStatus].label }}</button>
                  <button v-if="order.payStatus === 'pending'" class="btn btn-secondary btn-sm" @click="openQris(order)">Bayar</button>
                  <button class="btn btn-secondary btn-sm menu-trigger" @click="openMenu(order, $event)" title="Aksi lainnya">⋯</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="menuOrder" class="modal-backdrop active" @click.self="menuOrder = null">
      <div class="action-sheet" :style="{ top: menuTop + 'px' }">
        <div class="font-caption" style="color: #71717a; margin-bottom: 4px;">{{ menuOrder.id }}</div>
        <button v-if="menuOrder.payStatus === 'pending'" type="button" class="menu-item" @click="markPaid(menuOrder); menuOrder = null">Tandai Lunas</button>
        <button type="button" class="menu-item" @click="openDetail(menuOrder); menuOrder = null">Detail Pesanan</button>
        <button type="button" class="menu-item" @click="receipt(menuOrder); menuOrder = null">Lihat Struk</button>
        <button v-if="menuOrder.payStatus !== 'paid' && menuOrder.payStatus !== 'cancelled'" type="button" class="menu-item menu-danger" @click="cancelOrder(menuOrder); menuOrder = null">Batalkan Pesanan</button>
        <button type="button" class="menu-item menu-cancel" @click="menuOrder = null">Batal</button>
      </div>
    </div>

    <OrderDetailModal :order="detailOrder" @close="detailOrder = null" />
    <QrisModal :open="qrisOpen" :order-number="qrisNumber" :order-total="qrisTotal" @close="qrisOpen = false" @paid="onQrisPaid" />

    <div v-if="addOpen" class="modal-backdrop active" @click.self="addOpen = false">
      <div class="modal-card">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">Tambah Pesanan Manual</h3>
            <div class="font-caption" style="color: #71717a;">Untuk pembeli langsung di kasir (tanpa scan QR meja)</div>
          </div>
          <button class="modal-close-btn" @click="addOpen = false">&times;</button>
        </div>

        <div class="form-group">
          <label class="form-label" for="add-table">Meja</label>
          <select id="add-table" v-model="addForm.table_code" class="form-select" required>
            <option value="" disabled>Pilih meja</option>
            <option v-for="t in addTables" :key="t.id" :value="t.code">
              {{ t.code }}{{ t.floor ? ' • ' + t.floor : '' }}{{ t.is_active ? '' : ' (nonaktif)' }}
            </option>
          </select>
        </div>

        <div class="font-caption" style="color: #71717a; margin-bottom: var(--spacing-xs);">ITEM PESANAN</div>
        <div v-if="addMenus.length === 0" class="font-body-sm" style="color: #71717a;">Memuat menu…</div>
        <div v-for="m in addMenus" :key="m.id" class="add-menu-row">
          <div>
            <div><strong>{{ m.name }}</strong></div>
            <div class="font-caption" style="color: #71717a;">{{ rupiah(m.base_price) }}</div>
          </div>
          <div class="cust-qty">
            <button type="button" class="btn btn-secondary btn-sm" @click="addDec(m.id)">−</button>
            <strong>{{ addCart[m.id] || 0 }}</strong>
            <button type="button" class="btn btn-secondary btn-sm" @click="addInc(m.id)">+</button>
          </div>
        </div>

        <div class="form-group" style="margin-top: var(--spacing-md);">
          <label class="form-label" for="add-notes">Nama pelanggan / catatan (opsional)</label>
          <input id="add-notes" v-model="addForm.notes" type="text" class="form-input" maxlength="150" placeholder="cth: Ibu Ani — bungkus">
        </div>

        <div class="form-group">
          <label class="form-label" for="add-promo">Kode promo (opsional)</label>
          <input id="add-promo" v-model="addForm.promo_code" type="text" class="form-input font-mono" maxlength="50" placeholder="HEMAT5K">
        </div>

        <div class="cust-total-row">
          <span>Estimasi ({{ addCount }} item)</span>
          <strong>{{ rupiah(addTotal) }}</strong>
        </div>
        <p class="font-caption" style="color: #71717a;">Total final (service fee + pajak + diskon) dihitung server saat disimpan.</p>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="addOpen = false">Batal</button>
          <button type="button" class="btn btn-primary btn-sm" :disabled="addBusy || addCount === 0 || !addForm.table_code" @click="submitAddOrder">
            {{ addBusy ? 'Menyimpan…' : 'Simpan Pesanan' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { rupiah } from '../utils/format.js';
import { PREP_LABEL, PAY_LABEL, PREP_NEXT, adaptApiOrder, toReceiptOrder, buildOrderDetail } from '../utils/orders.js';
import { session, refreshApi } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';
import OrderDetailModal from '../components/OrderDetailModal.vue';
import QrisModal from '../components/QrisModal.vue';

const emit = defineEmits(['receipt']);

const tabs = [
  { value: 'ALL', label: 'Semua' },
  { value: 'MENUNGGU', label: 'Menunggu' },
  { value: 'DIPROSES', label: 'Diproses' },
  { value: 'LUNAS', label: 'Lunas' },
  { value: 'SELESAI', label: 'Selesai' },
  { value: 'DIBATALKAN', label: 'Dibatalkan' }
];

const filter = ref('ALL');
const search = ref('');
const detailOrder = ref(null);
const qrisOpen = ref(false);
const qrisNumber = ref('');
const qrisTotal = ref(0);
const menuOrder = ref(null);
const menuTop = ref(200);

function openMenu(order, ev) {
  menuOrder.value = order;
  const y = ev?.clientY ?? window.innerHeight / 2;
  menuTop.value = Math.max(16, Math.min(y - 40, window.innerHeight - 300));
}

// ---- Tambah pesanan manual (kasir) ----
const addOpen = ref(false);
const addBusy = ref(false);
const addTables = ref([]);
const addMenus = ref([]);
const addCart = ref({});
const addForm = ref({ table_code: '', notes: '', promo_code: '' });

const addCount = computed(() => Object.values(addCart.value).reduce((a, b) => a + b, 0));
const addTotal = computed(() => addMenus.value.reduce((s, m) => s + (m.base_price * (addCart.value[m.id] || 0)), 0));

function addInc(id) { addCart.value[id] = (addCart.value[id] || 0) + 1; }
function addDec(id) {
  addCart.value[id] = Math.max(0, (addCart.value[id] || 0) - 1);
  if (!addCart.value[id]) delete addCart.value[id];
}

async function openAddOrder() {
  addCart.value = {};
  addForm.value = { table_code: '', notes: '', promo_code: '' };
  addOpen.value = true;
  try {
    const [tRes, mRes] = await Promise.all([api.listTables(), api.listMenus()]);
    addTables.value = (tRes.data || []).filter((t) => t.is_active);
    addMenus.value = (mRes.data || []).filter((m) => m.is_available !== false);
  } catch (e) {
    notify('Gagal memuat data pesanan manual: ' + e.message);
  }
}

async function submitAddOrder() {
  addBusy.value = true;
  try {
    const res = await api.createPublicOrder({
      table_code: addForm.value.table_code,
      notes: addForm.value.notes.trim() || undefined,
      promo_code: addForm.value.promo_code.trim() || undefined,
      idempotency_key: (typeof crypto !== 'undefined' && crypto.randomUUID) ? `kasir-${crypto.randomUUID()}` : `kasir-${Date.now()}-${Math.random().toString(36).slice(2)}`,
      items: Object.entries(addCart.value).map(([menu_item_id, quantity]) => ({ menu_item_id: Number(menu_item_id), quantity }))
    });
    addOpen.value = false;
    await refreshApi();
    notifySuccess(`Pesanan ${res.data.order_number} dibuat (${rupiah(res.data.total)}).`);
  } catch (e) {
    notify(e.data?.message || e.message || 'Gagal membuat pesanan.');
  } finally {
    addBusy.value = false;
  }
}

const allRows = computed(() => {
  if (!session.loggedIn || !session.apiOnline) return [];
  return session.apiOrders.map(adaptApiOrder);
});

const rows = computed(() => {
  const q = (search.value || session.globalSearch).trim().toLowerCase();
  return allRows.value.filter((o) => {
    if (filter.value === 'MENUNGGU' && !(o.payStatus === 'pending' && o.prepStatus === 'received')) return false;
    if (filter.value === 'DIPROSES' && !(o.prepStatus === 'preparing' || o.prepStatus === 'ready')) return false;
    if (filter.value === 'LUNAS' && !(o.payStatus === 'paid' && o.prepStatus !== 'completed')) return false;
    if (filter.value === 'SELESAI' && o.prepStatus !== 'completed') return false;
    if (filter.value === 'DIBATALKAN' && !(o.payStatus === 'cancelled' || o.prepStatus === 'cancelled')) return false;
    if (q && !(o.id.toLowerCase().includes(q) || o.customer.toLowerCase().includes(q) || o.tableCode.toLowerCase().includes(q) || o.itemsSummary.toLowerCase().includes(q))) return false;
    return true;
  });
});

function prepClass(prep) {
  return { completed: 'badge-success', ready: 'badge-info', preparing: 'badge-warning', received: 'badge-neutral' }[prep] || 'badge-neutral';
}

function payClass(pay) {
  return { paid: 'badge-success', pending: 'badge-warning', checking: 'badge-warning', cancelled: 'badge-neutral' }[pay] || 'badge-danger';
}

async function advance(order) {
  const step = PREP_NEXT[order.prepStatus];
  if (!step) return;
  try {
    await api.updatePreparation(order.id, step.to);
    await refreshApi();
    notifySuccess(`Pesanan ${order.id} → ${step.label}.`);
  } catch (e) {
    notify('Gagal memperbarui status dapur: ' + e.message);
  }
}

async function markPaid(order) {
  if (!window.confirm(`Tandai pesanan ${order.id} sebagai LUNAS (kasir)?`)) return;
  try {
    await api.updatePayment(order.id, 'paid');
    await refreshApi();
    notifySuccess(`Pesanan ${order.id} ditandai lunas.`);
  } catch (e) {
    notify('Gagal memperbarui pembayaran: ' + e.message);
  }
}

async function cancelOrder(order) {
  if (!window.confirm(`Batalkan pesanan ${order.id}? Stok item akan dikembalikan.`)) return;
  try {
    await api.cancelOrder(order.id);
    await refreshApi();
    notifySuccess(`Pesanan ${order.id} dibatalkan, stok dikembalikan.`);
  } catch (e) {
    notify(e.data?.message || e.message || 'Gagal membatalkan pesanan.');
  }
}

async function receipt(order) {
  try {
    const res = await api.getOrder(order.id);
    emit('receipt', toReceiptOrder(adaptApiOrder(res.data)));
  } catch (e) {
    notify('Gagal memuat struk: ' + e.message);
  }
}

async function openDetail(order) {
  try {
    const res = await api.getOrder(order.id);
    detailOrder.value = buildOrderDetail(res.data, 'api');
  } catch (e) {
    notify('Gagal memuat detail: ' + e.message);
  }
}

function openQris(order) {
  qrisNumber.value = order.id;
  qrisTotal.value = order.total || 0;
  qrisOpen.value = true;
}

async function onQrisPaid() {
  await refreshApi();
}

// Auto-refresh daftar tiap 20 detik agar status lunas dari Midtrans
// terlihat tanpa reload manual. Berhenti saat komponen dilepas.
let pollTimer = null;
onMounted(() => {
  stopPoll();
  pollTimer = setInterval(async () => {
    if (session.loggedIn && session.apiOnline && !document.hidden) {
      try { await refreshApi(); } catch (e) { /* abaikan, refresh berikutnya */ }
    }
  }, 20000);
});
onUnmounted(() => stopPoll());
function stopPoll() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
}
</script>

<style scoped>
.search-wrapper {
  position: relative;
  width: 100%;
  max-width: 320px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #71717a;
  pointer-events: none;
}

.search-input {
  padding-left: 40px;
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
  flex-wrap: wrap;
}

.status-stack {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.action-buttons {
  display: flex;
  gap: 6px;
  justify-content: flex-start;
  align-items: center;
  flex-wrap: nowrap;
}

.menu-trigger {
  padding-left: 12px;
  padding-right: 12px;
  letter-spacing: 1px;
}

.action-sheet {
  position: fixed;
  right: 24px;
  width: min(360px, calc(100vw - 32px));
  background-color: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-lg);
  box-shadow: var(--shadow-modal);
  padding: 12px 8px 8px;
  z-index: 1001;
  display: flex;
  flex-direction: column;
}

.menu-item {
  background: none;
  border: none;
  text-align: left;
  padding: 12px;
  font-size: 14px;
  border-radius: 8px;
  cursor: pointer;
  color: var(--color-ink);
  white-space: nowrap;
  width: 100%;
}

.menu-item:hover {
  background-color: var(--color-surface-soft);
}

.menu-cancel {
  margin-top: 4px;
  border-top: 1px solid var(--color-hairline);
  border-radius: 0;
  text-align: center;
  color: #71717a;
}

.menu-danger {
  color: var(--color-semantic-danger, #dc2626);
}

.add-menu-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-hairline);
}

.cust-qty {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cust-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: var(--spacing-md) 0 var(--spacing-xs);
  font-size: 15px;
}

.font-bold {
  font-weight: 700;
}

.font-mono {
  font-family: var(--font-mono);
}

.text-sm {
  font-size: 12px;
  color: #555;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.empty-state {
  padding: 28px;
  color: #71717a;
}

/* Column Widths */
.col-id { white-space: nowrap; }
.col-time { white-space: nowrap; }
.col-customer { max-width: 120px; }
.col-table { white-space: nowrap; }
.col-items { max-width: 180px; }
.col-total { white-space: nowrap; }
.col-status { width: 130px; }
.col-method { white-space: nowrap; }
.col-action { white-space: nowrap; }

@media (max-width: 768px) {
  .table-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-wrapper {
    max-width: 100%;
  }
  .tab-group {
    overflow-x: auto;
    white-space: nowrap;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
  }
  
  .action-buttons {
    justify-content: flex-start;
  }
  .col-action {
    text-align: left;
  }
}
</style>
