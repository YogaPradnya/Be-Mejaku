<template>
  <div class="customer-page">
    <header class="cust-header">
      <div class="brand-mark">M</div>
      <div>
        <div class="brand-title">Merchants</div>
        <div class="font-caption cust-sub">Pesan dari meja</div>
      </div>
      <div class="cust-header-actions">
        <button
          v-if="historyOrders.length > 0"
          type="button"
          class="btn btn-secondary btn-sm"
          title="Lihat riwayat pesanan Anda"
          @click="showHistoryModal = true"
        >
          Riwayat ({{ historyOrders.length }})
        </button>
        <span v-if="table" class="badge badge-neutral cust-table">Meja {{ table.code }}</span>
      </div>
    </header>

    <div v-if="fatal" class="card cust-card">
      <h2 class="font-card-title">Meja tidak valid</h2>
      <p class="section-subtitle">{{ fatal }}</p>
      <p class="font-caption">Pindai ulang QR yang tertempel di meja Anda.</p>
    </div>

    <template v-else>
      <!-- STEP 1: MENU + KERANJANG -->
      <section v-if="step === 'menu'" class="card cust-card">
        <h1 class="section-title">Menu</h1>
        <p class="section-subtitle" style="margin-bottom: var(--spacing-md);">
          {{ table ? (table.floor ? `Meja ${table.code} • ${table.floor}` : `Meja ${table.code}`) : 'Memuat meja…' }}
        </p>

        <div v-if="loadingMenu" class="font-body-sm" style="color:#71717a;">Memuat menu…</div>

        <div v-for="m in menus" :key="m.id" class="cust-menu-block">
          <div class="cust-menu-row">
            <img v-if="m.image" :src="m.image" :alt="m.name" loading="lazy" style="width:56px; height:56px; object-fit:cover; border-radius:10px; flex-shrink:0;" />
            <div class="cust-menu-info">
              <div class="cust-menu-name">{{ m.name }}</div>
              <div class="cust-rating-row">
                <span class="cust-star">★</span>
                <span class="cust-score">{{ Number(m.rating || 0).toFixed(1) }}</span>
                <span class="cust-count">({{ m.rating_count || 0 }})</span>
                <button type="button" class="cust-review-link" @click.stop="openReviews(m)">Lihat Ulasan</button>
              </div>
              <div class="font-caption" style="color:#71717a;">{{ m.merchant?.name || '' }}</div>
              <div class="cust-menu-price">{{ rupiah(m.base_price) }}<span v-if="cartQty(m.id) > 0" class="font-caption"> • {{ cartQty(m.id) }} di keranjang</span></div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" @click="toggleOptions(m)">{{ expandedId === m.id ? 'Tutup' : 'Atur' }}</button>
          </div>

          <div v-if="expandedId === m.id" class="cust-opts">
            <div v-if="!details[m.id]" class="font-body-sm" style="color:#71717a;">Memuat opsi…</div>
            <template v-else>
              <div v-if="(details[m.id].portions || []).length" class="form-group">
                <label class="form-label">Porsi</label>
                <div class="cust-radio-row">
                  <label><input type="radio" :value="''" v-model="cfg[m.id].portion_id"> Biasa</label>
                  <label v-for="p in details[m.id].portions" :key="p.id">
                    <input type="radio" :value="p.id" v-model="cfg[m.id].portion_id"> {{ p.name }} (+{{ rupiah(p.surcharge) }})
                  </label>
                </div>
              </div>
              <div v-if="details[m.id].has_spice_option && (details[m.id].spice_levels || []).length" class="form-group">
                <label class="form-label">Level pedas</label>
                <select v-model="cfg[m.id].spice_id" class="form-select">
                  <option value="">Tanpa pilihan</option>
                  <option v-for="s in details[m.id].spice_levels" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div v-if="(details[m.id].addons || []).length" class="form-group">
                <label class="form-label">Pelengkap</label>
                <label v-for="a in details[m.id].addons" :key="a.id" class="cust-check">
                  <input type="checkbox" :value="a.id" v-model="cfg[m.id].addon_ids"> {{ a.name }} (+{{ rupiah(a.price) }})
                </label>
              </div>

              <!-- Ulasan terbaru -->
              <div v-if="details[m.id].recent_reviews && details[m.id].recent_reviews.length" class="cust-recent-reviews">
                <div class="font-caption" style="color: #71717a; margin-bottom: 4px;">ULASAN TERBARU</div>
                <div v-for="rev in details[m.id].recent_reviews.slice(0, 2)" :key="rev.id" class="cust-review-snippet">
                  <div style="display:flex; justify-content:space-between; font-size:12px;">
                    <strong>{{ rev.customer_name }}</strong>
                    <span style="color:#f59e0b;">★ {{ rev.rating }}</span>
                  </div>
                  <div v-if="rev.review" class="font-caption" style="color:#4b5563;">{{ rev.review }}</div>
                </div>
                <button type="button" class="cust-review-link" style="margin-top: 4px; padding: 0;" @click="openReviews(m)">Lihat semua ulasan →</button>
              </div>

              <div class="cust-addrow">
                <div class="cust-qty">
                  <button type="button" class="btn btn-secondary btn-sm" @click="cfgQty(m.id, -1)">−</button>
                  <strong>{{ cfg[m.id].qty }}</strong>
                  <button type="button" class="btn btn-secondary btn-sm" @click="cfgQty(m.id, 1)">+</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" @click="addLine(m)">Tambah • {{ rupiah(lineEstimate(m)) }}</button>
              </div>
            </template>
          </div>
        </div>

        <div v-if="cartLines.length" class="cust-cart">
          <div class="font-caption" style="color: #71717a; margin-bottom: 4px;">KERANJANG</div>
          <div v-for="l in cartLines" :key="l.key" class="cust-cart-row">
            <div>
              <div><strong>{{ l.qty }}x {{ l.name }}</strong></div>
              <div v-if="l.desc" class="font-caption" style="color:#71717a;">{{ l.desc }}</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <strong>{{ rupiah(l.unit * l.qty) }}</strong>
              <button type="button" class="btn btn-secondary btn-sm" @click="removeLine(l.key)">×</button>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top: var(--spacing-md);">
          <label class="form-label" for="cust-name">Nama / catatan (opsional)</label>
          <input id="cust-name" v-model="customerName" type="text" class="form-input" maxlength="150" placeholder="cth: Budi — tidak pedas">
        </div>

        <div class="cust-total-row">
          <span>Total ({{ cartCount }} item)</span>
          <strong>{{ rupiah(cartTotal) }}</strong>
        </div>
        <button type="button" class="btn btn-primary cust-cta" :disabled="cartCount === 0 || placing" @click="placeOrder">
          {{ placing ? 'Membuat pesanan…' : 'Buat Pesanan' }}
        </button>
        <p v-if="orderError" class="font-caption" style="color: var(--color-semantic-danger, #dc2626); margin-top: 8px;">{{ orderError }}</p>
      </section>

      <!-- STEP 2: PILIH PEMBAYARAN -->
      <section v-if="step === 'pay'" class="card cust-card">
        <h1 class="section-title">Pembayaran</h1>
        <p class="section-subtitle" style="margin-bottom: var(--spacing-md);">
          Pesanan <strong class="font-mono">{{ order.order_number }}</strong> • {{ rupiah(order.total) }}
        </p>

        <button type="button" class="cust-paybtn" @click="payViaMidtrans('qris')">
          <strong>QRIS</strong>
          <span class="font-caption">Pindai QR via Midtrans (e-wallet / m-banking)</span>
        </button>
        <button type="button" class="cust-paybtn" @click="payViaMidtrans('va')">
          <strong>Transfer Bank (VA)</strong>
          <span class="font-caption">Virtual Account via Midtrans</span>
        </button>
        <button type="button" class="cust-paybtn" @click="payManual">
          <strong>Bayar di Kasir</strong>
          <span class="font-caption">Tunjukkan nomor pesanan ke kasir, tanpa bayar online</span>
        </button>

        <p v-if="payError" class="font-caption" style="color: var(--color-semantic-danger, #dc2626); margin-top: 8px;">{{ payError }}</p>
        <button type="button" class="btn btn-secondary btn-sm" style="margin-top: var(--spacing-md);" @click="step = 'track'">Lihat status pesanan →</button>
      </section>

      <!-- STEP 3: STATUS -->
      <section v-if="step === 'track'" class="card cust-card">
        <h1 class="section-title">Status Pesanan</h1>
        <p class="section-subtitle" style="margin-bottom: var(--spacing-md);">
          <strong class="font-mono">{{ order.order_number }}</strong> • Meja {{ tableCode }}
        </p>

        <div class="cust-timeline">
          <div v-for="(s, i) in timeline" :key="i" class="cust-tl-row">
            <span class="cust-tl-dot" :class="{ done: i < stageIndex, now: i === stageIndex }"></span>
            <span>{{ s }}</span>
            <span v-if="i === stageIndex" class="badge badge-info" style="margin-left:auto;">Aktif</span>
            <span v-else-if="i < stageIndex" class="badge badge-success" style="margin-left:auto;">OK</span>
          </div>
        </div>

        <div class="cust-paystate">
          Pembayaran:
          <span class="badge" :class="payBadgeClass">{{ payLabel }}</span>
        </div>

        <div v-if="order.payment_status !== 'paid'" style="display:flex; gap:8px; flex-wrap:wrap; margin-top: var(--spacing-md);">
          <button type="button" class="btn btn-primary btn-sm" :disabled="checking" @click="refreshStatus(true)">{{ checking ? 'Mengecek…' : 'Cek Pembayaran' }}</button>
          <button v-if="snapToken" type="button" class="btn btn-secondary btn-sm" :disabled="checking" @click="reopenSnap">Buka Lagi Pembayaran</button>
        </div>
        <p v-if="manualMode" class="font-body-sm" style="margin-top: var(--spacing-md);">Tunjukkan nomor <strong class="font-mono">{{ order.order_number }}</strong> ke kasir untuk membayar manual.</p>

        <div style="margin-top: var(--spacing-md); display: flex; gap: 8px;">
          <button type="button" class="btn btn-secondary btn-sm" @click="step = 'menu'">
            ← Tambah Pesanan Lain
          </button>
        </div>

        <!-- Section Berikan Ulasan Hidangan Pesanan -->
        <div v-if="order && order.items && order.items.length" class="cust-rate-order-section">
          <div class="font-caption" style="color: #71717a; margin-bottom: 6px;">ULASAN HIDANGAN</div>
          <div class="font-body-sm" style="margin-bottom: 10px; color: #52525b;">
            Bantu merchant meningkatkan rasa dan layanan dengan menilai menu yang Anda pesan:
          </div>
          <div class="cust-ordered-items-rate">
            <div v-for="it in order.items" :key="it.menu_item.id" class="cust-rate-item-row">
              <div>
                <strong>{{ it.menu_item.name }}</strong>
                <div v-if="it.portion" class="font-caption" style="color: #71717a;">{{ it.portion.name }}</div>
              </div>
              <button
                type="button"
                class="btn btn-secondary btn-sm"
                @click="openRateOrder(it.menu_item)"
              >
                Beri Nilai ★
              </button>
            </div>
          </div>
        </div>
      </section>
    </template>

    <ReviewModal
      :open="reviewModalOpen"
      :menu="selectedMenuForReview"
      :order-number="order ? order.order_number : ''"
      :initial-customer-name="customerName"
      :initial-tab="reviewInitialTab"
      @close="reviewModalOpen = false; selectedMenuForReview = null"
      @reviewed="onMenuReviewed"
    />

    <CustomerHistoryModal
      :open="showHistoryModal"
      :orders="historyOrders"
      @close="showHistoryModal = false"
      @select-order="selectOrderFromHistory"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/client.js';
import { rupiah } from '../utils/format.js';
import { ensureSnapLoaded, payWithSnapToken } from '../utils/midtrans.js';
import ReviewModal from '../components/ReviewModal.vue';
import CustomerHistoryModal from '../components/CustomerHistoryModal.vue';
import { getCustomerOrders, saveCustomerOrder, updateCustomerOrderStatus } from '../utils/orderHistory.js';

const route = useRoute();
const tableCode = computed(() => String(route.params.code || '').toUpperCase());

const table = ref(null);
const fatal = ref('');
const menus = ref([]);
const loadingMenu = ref(true);
const details = ref({});
const expandedId = ref(null);
const cfg = ref({});
const cartLines = ref([]);
const customerName = ref('');
const placing = ref(false);
const orderError = ref('');

const step = ref('menu');
const order = ref(null);
const payError = ref('');
const manualMode = ref(false);
const snapToken = ref('');
const snapMeta = ref({ clientKey: '', isProduction: false });
const checking = ref(false);
let pollTimer = null;

const reviewModalOpen = ref(false);
const selectedMenuForReview = ref(null);
const reviewInitialTab = ref('list');

const historyOrders = ref([]);
const showHistoryModal = ref(false);

function loadHistory() {
  historyOrders.value = getCustomerOrders();
}

async function selectOrderFromHistory(orderNumber) {
  showHistoryModal.value = false;
  try {
    const res = await api.getPublicOrder(orderNumber);
    order.value = res.data;
    step.value = 'track';
    startPoll();
  } catch (e) {
    orderError.value = 'Gagal memuat detail pesanan: ' + e.message;
  }
}

function openReviews(m) {
  selectedMenuForReview.value = m;
  reviewInitialTab.value = 'list';
  reviewModalOpen.value = true;
}

function openRateOrder(menuItem) {
  const fullMenu = menus.value.find((m) => m.id === menuItem.id) || menuItem;
  selectedMenuForReview.value = fullMenu;
  reviewInitialTab.value = 'write';
  reviewModalOpen.value = true;
}

function onMenuReviewed(payload) {
  const m = menus.value.find((item) => item.id === payload.menuId);
  if (m) {
    m.rating = payload.averageRating;
    m.rating_count = payload.ratingCount;
  }
}

const cartCount = computed(() => cartLines.value.reduce((a, l) => a + l.qty, 0));
const cartTotal = computed(() => cartLines.value.reduce((a, l) => a + l.unit * l.qty, 0));

function cartQty(menuId) {
  return cartLines.value.filter((l) => l.menu_item_id === menuId).reduce((a, l) => a + l.qty, 0);
}

function ensureCfg(id) {
  if (!cfg.value[id]) cfg.value[id] = { portion_id: '', spice_id: '', addon_ids: [], qty: 1 };
  return cfg.value[id];
}

async function toggleOptions(m) {
  if (expandedId.value === m.id) {
    expandedId.value = null;
    return;
  }
  expandedId.value = m.id;
  ensureCfg(m.id);
  if (details.value[m.id]) return;
  try {
    const res = await api.getPublicMenu(m.slug);
    details.value[m.id] = res.data;
  } catch (e) {
    orderError.value = 'Gagal memuat opsi menu: ' + e.message;
    expandedId.value = null;
  }
}

function cfgQty(id, d) {
  const c = ensureCfg(id);
  c.qty = Math.max(1, c.qty + d);
}

function lineEstimate(m) {
  const c = ensureCfg(m.id);
  const d = details.value[m.id] || {};
  let unit = m.base_price;
  const portion = (d.portions || []).find((p) => p.id === c.portion_id);
  if (portion) unit += portion.surcharge;
  (d.addons || []).forEach((a) => { if (c.addon_ids.includes(a.id)) unit += a.price; });
  return unit * c.qty;
}

function lineDesc(m) {
  const c = ensureCfg(m.id);
  const d = details.value[m.id] || {};
  const parts = [];
  const portion = (d.portions || []).find((p) => p.id === c.portion_id);
  if (portion) parts.push(portion.name);
  const spice = (d.spice_levels || []).find((s) => s.id === c.spice_id);
  if (spice) parts.push(spice.name);
  (d.addons || []).forEach((a) => { if (c.addon_ids.includes(a.id)) parts.push('+ ' + a.name); });
  return parts.join(' • ');
}

function addLine(m) {
  const c = ensureCfg(m.id);
  const d = details.value[m.id] || {};
  let unit = m.base_price;
  const portion = (d.portions || []).find((p) => p.id === c.portion_id);
  if (portion) unit += portion.surcharge;
  (d.addons || []).forEach((a) => { if (c.addon_ids.includes(a.id)) unit += a.price; });
  cartLines.value.push({
    key: Date.now() + Math.random(),
    menu_item_id: m.id,
    name: m.name,
    desc: lineDesc(m),
    qty: c.qty,
    unit,
    portion_id: c.portion_id || null,
    spice_level_id: c.spice_id || null,
    addon_ids: [...c.addon_ids]
  });
  c.qty = 1;
}

function removeLine(key) {
  cartLines.value = cartLines.value.filter((l) => l.key !== key);
}

const timeline = ['Pesanan Diterima', 'Pembayaran Lunas', 'Sedang Disiapkan', 'Siap Disajikan', 'Selesai'];
const stageIndex = computed(() => {
  if (!order.value) return 0;
  if (order.value.preparation_status === 'completed') return 4;
  if (order.value.preparation_status === 'ready') return 3;
  if (order.value.preparation_status === 'preparing') return order.value.payment_status === 'paid' ? 2 : 1;
  return order.value.payment_status === 'paid' ? 1 : 0;
});
const payLabel = computed(() => {
  const m = { pending: 'Belum Bayar', checking: 'Dicek', paid: 'Lunas', failed: 'Gagal', expired: 'Kedaluarsa' };
  return m[order.value?.payment_status] || order.value?.payment_status || '-';
});
const payBadgeClass = computed(() => order.value?.payment_status === 'paid' ? 'badge-success' : 'badge-warning');

onMounted(async () => {
  try {
    const t = await api.getTablePublic(tableCode.value);
    table.value = t.data;
  } catch (e) {
    fatal.value = e.data?.message || e.message || 'Meja tidak ditemukan.';
  }
  try {
    const m = await api.listPublicMenu();
    menus.value = m.data || [];
  } catch (e) {
    fatal.value = fatal.value || ('Gagal memuat menu: ' + e.message);
  } finally {
    loadingMenu.value = false;
  }
  loadHistory();
});

onUnmounted(stopPoll);

function stopPoll() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
}

async function placeOrder() {
  placing.value = true;
  orderError.value = '';
  try {
    const res = await api.createPublicOrder({
      table_code: tableCode.value,
      notes: customerName.value.trim() || undefined,
      idempotency_key: `cust-${tableCode.value}-${Date.now()}`,
      items: cartLines.value.map((l) => ({
        menu_item_id: l.menu_item_id,
        quantity: l.qty,
        portion_id: l.portion_id || undefined,
        spice_level_id: l.spice_level_id || undefined,
        addon_ids: l.addon_ids
      }))
    });
    order.value = res.data;
    saveCustomerOrder(order.value);
    loadHistory();
    step.value = 'pay';
    startPoll();
  } catch (e) {
    orderError.value = e.data?.message || e.message || 'Gagal membuat pesanan.';
  } finally {
    placing.value = false;
  }
}

async function payViaMidtrans(kind) {
  payError.value = '';
  checking.value = true;
  try {
    // QRIS & VA sama-sama diproses lewat Snap Midtrans; kirim channel agar
    // popup Snap hanya menampilkan metode yang relevan.
    const res = await api.createPayment(order.value.order_number, 'midtrans', { channel: kind === 'va' ? 'va' : 'qris' });
    order.value = { ...order.value, ...res.data, payment_status: res.data.payment_status };
    snapToken.value = res.data.snap_token;
    snapMeta.value = { clientKey: res.data.midtrans_client_key, isProduction: res.data.midtrans_is_production };
    if (!snapToken.value) throw new Error('Token Midtrans tidak diterima.');
    await ensureSnapLoaded(snapMeta.value.clientKey, snapMeta.value.isProduction);
    payWithSnapToken(snapToken.value, {
      onSuccess: async () => { await refreshStatus(true); },
      onPending: async () => { await refreshStatus(true); },
      onClose: async () => { await refreshStatus(true); step.value = 'track'; }
    });
    step.value = 'track';
  } catch (e) {
    payError.value = (e.data?.message || e.message || 'Gagal memulai pembayaran.') + (kind === 'va' ? ' (Anda tetap bisa memilih VA di popup Snap.)' : '');
  } finally {
    checking.value = false;
  }
}

function payManual() {
  manualMode.value = true;
  step.value = 'track';
}

async function reopenSnap() {
  if (!snapToken.value) return;
  checking.value = true;
  try {
    await ensureSnapLoaded(snapMeta.value.clientKey, snapMeta.value.isProduction);
    payWithSnapToken(snapToken.value, {
      onSuccess: async () => { await refreshStatus(true); },
      onPending: async () => { await refreshStatus(true); },
      onClose: async () => { await refreshStatus(true); }
    });
  } finally {
    checking.value = false;
  }
}

async function refreshStatus(fresh = false) {
  if (!order.value) return;
  checking.value = true;
  try {
    // fresh=true memaksa sinkron Midtrans (dipakai tombol manual),
    // polling rutin tetap memakai throttle agar tidak menghantam API.
    const res = await api.getPublicOrder(order.value.order_number, fresh);
    order.value = res.data;
    updateCustomerOrderStatus(order.value.order_number, {
      payment_status: order.value.payment_status,
      preparation_status: order.value.preparation_status,
      total: order.value.total
    });
    loadHistory();
  } catch (e) {
    payError.value = e.data?.message || e.message;
  } finally {
    checking.value = false;
  }
}

function startPoll() {
  stopPoll();
  pollTimer = setInterval(refreshStatus, 5000);
}
</script>

<style scoped>
.customer-page {
  max-width: 640px;
  margin: 0 auto;
  padding: var(--spacing-lg) var(--spacing-md) 64px;
  min-height: 100vh;
}
.cust-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: var(--spacing-lg);
}
.cust-header-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
}
.cust-sub { color: #71717a; }
.cust-card { margin-bottom: var(--spacing-lg); }
.cust-menu-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-hairline);
}
.cust-menu-name { font-weight: 700; }
.cust-menu-price { font-weight: 700; margin-top: 4px; }
.cust-qty { display: flex; align-items: center; gap: 10px; }
.cust-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: var(--spacing-md) 0;
  font-size: 15px;
}
.cust-cta { width: 100%; }
.cust-menu-block { border-bottom: 1px solid var(--color-hairline); padding: 4px 0; }
.cust-opts { padding: 4px 0 12px 4px; }
.cust-radio-row { display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
.cust-radio-row label, .cust-check { display: flex; align-items: center; gap: 8px; font-size: 14px; padding: 4px 0; cursor: pointer; }
.cust-addrow { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 8px; }
.cust-cart { margin-top: var(--spacing-md); border-top: 2px solid var(--color-hairline); padding-top: 8px; }
.cust-cart-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--color-hairline); }
.cust-paybtn {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  text-align: left;
  padding: 14px 16px;
  margin-bottom: 10px;
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
  background: var(--color-canvas);
  cursor: pointer;
}
.cust-paybtn:hover { border-color: var(--color-ink); }
.cust-timeline { display: flex; flex-direction: column; gap: 4px; margin: var(--spacing-md) 0; }
.cust-tl-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--color-hairline); }
.cust-tl-dot { width: 12px; height: 12px; border-radius: 50%; background: #e4e4e7; flex-shrink: 0; }
.cust-tl-dot.done { background: var(--color-semantic-success, #16a34a); }
.cust-tl-dot.now { background: var(--color-semantic-info, #2563eb); box-shadow: 0 0 0 4px rgba(37,99,235,.15); }
.cust-paystate { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.cust-rating-row { display: flex; align-items: center; gap: 4px; font-size: 12px; margin: 2px 0 4px; }
.cust-star { color: #f59e0b; font-size: 13px; }
.cust-score { font-weight: 700; color: var(--color-ink); }
.cust-count { color: #71717a; }
.cust-review-link {
  background: none;
  border: none;
  color: var(--color-ink);
  font-size: 11px;
  text-decoration: underline;
  cursor: pointer;
  padding: 0 4px;
}
.cust-review-link:hover { color: #2563eb; }
.cust-recent-reviews {
  background: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
  padding: 8px 10px;
  margin: 8px 0;
}
.cust-review-snippet {
  padding: 4px 0;
  border-bottom: 1px dashed var(--color-hairline);
}
.cust-review-snippet:last-of-type {
  border-bottom: none;
}
.cust-rate-order-section {
  margin-top: var(--spacing-lg);
  padding-top: var(--spacing-md);
  border-top: 1px solid var(--color-hairline);
}
.cust-ordered-items-rate {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cust-rate-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
}
</style>
