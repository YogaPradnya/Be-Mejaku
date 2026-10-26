<template>
  <div v-if="open" class="modal-backdrop active" @click.self="close">
    <div class="modal-card">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">Pembayaran</h3>
          <div class="font-caption" style="color: #71717a;">{{ orderNumber }}{{ orderTotal ? ' • ' + rupiah(orderTotal) : '' }}</div>
        </div>
        <button class="modal-close-btn" @click="close">&times;</button>
      </div>

      <!-- PILIH METODE -->
      <div v-if="!choice" class="pay-choices">
        <button type="button" class="pay-choice" @click="chooseManual">
          <strong>Tunai / Manual</strong>
          <span class="font-caption">Kasir terima uang, catat nominal & kembalian</span>
        </button>
        <button type="button" class="pay-choice" @click="chooseMidtrans('qris')">
          <strong>QRIS</strong>
          <span class="font-caption">Pindai QR via Midtrans (e-wallet / m-banking)</span>
        </button>
        <button type="button" class="pay-choice" @click="choice = 'va'">
          <strong>Transfer Bank (VA)</strong>
          <span class="font-caption">Virtual Account via Midtrans</span>
        </button>
      </div>

      <!-- PILIH BANK VA -->
      <div v-if="choice === 'va' && !payment && !loading">
        <div class="font-caption" style="color: #71717a; margin-bottom: 8px;">PILIH BANK TUJUAN</div>
        <div class="pay-choices">
          <button v-for="b in banks" :key="b.code" type="button" class="pay-choice" @click="chooseMidtrans('va', b.code)">
            <strong>{{ b.label }}</strong>
            <span class="font-caption">Virtual Account {{ b.label }} via Midtrans</span>
          </button>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" @click="backToChoice">← Ganti metode</button>
      </div>

      <div v-if="loading" class="font-body-sm" style="color: #71717a; padding: var(--spacing-md) 0;">Menyiapkan pembayaran…</div>
      <div v-if="!loading && loadError && !payment" class="font-body-sm" style="color: var(--color-semantic-danger, #dc2626); padding: var(--spacing-md) 0;">{{ loadError }}</div>

      <!-- TUNAI / MANUAL -->
      <div v-if="choice === 'manual'">
        <div class="form-group">
          <label class="form-label" for="cash-input">Nominal diterima (Rp)</label>
          <input id="cash-input" v-model.number="cashReceived" type="number" class="form-input" min="0" :placeholder="String(orderTotal || '')">
        </div>
        <div class="card-soft" style="margin-bottom: var(--spacing-md); padding: var(--spacing-md);">
          <div style="display: flex; justify-content: space-between;">
            <span class="font-caption">Total tagihan</span>
            <strong>{{ rupiah(orderTotal) }}</strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-top: 4px;">
            <span class="font-caption">Kembalian</span>
            <strong :style="{ color: cashChange >= 0 ? 'var(--color-semantic-success, #16a34a)' : 'var(--color-semantic-danger, #dc2626)' }">
              {{ cashChange >= 0 ? rupiah(cashChange) : ('Kurang ' + rupiah(Math.abs(cashChange))) }}
            </strong>
          </div>
        </div>
        <button type="button" class="btn btn-primary" style="width: 100%;" :disabled="busy || !(cashReceived >= (orderTotal || 0) && (orderTotal || 0) > 0)" @click="confirmCash">
          {{ busy ? 'Menyimpan…' : 'Konfirmasi Tunai' }}
        </button>
        <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px;" @click="backToChoice">← Ganti metode</button>
      </div>

      <!-- MIDTRANS (QRIS / VA) -->
      <template v-if="payment">
        <div class="card-soft" style="margin-bottom: var(--spacing-md); padding: var(--spacing-md);">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="font-caption">Nominal</span>
            <span class="font-card-title">{{ rupiah(payment.amount) }}</span>
          </div>
          <div class="font-body-sm" style="margin-top: 4px;">
            Status:
            <span class="badge" :class="payment.status === 'paid' ? 'badge-success' : (payment.status === 'pending' ? 'badge-warning' : 'badge-danger')">{{ payment.status }}</span>
            <span v-if="payment.status === 'pending'" class="font-mono" style="font-size: 12px; color: #71717a;"> • {{ countdown }}</span>
          </div>
          <div v-if="payment.payment_type" class="font-body-sm" style="color: #555; margin-top: 4px;">
            via Midtrans: {{ payment.payment_type }}
          </div>
        </div>

        <div v-if="payment.va_number" class="card-soft" style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); text-align: center;">
          <div class="font-caption">VIRTUAL ACCOUNT {{ (payment.va_bank || '').toUpperCase() }}</div>
          <div class="font-mono" style="font-size: 26px; font-weight: 700; letter-spacing: 2px; margin: 8px 0;">{{ payment.va_number }}</div>
          <button type="button" class="btn btn-secondary btn-sm" @click="copyVa">Salin Nomor VA</button>
          <p class="font-caption" style="margin-top: 8px;">Transfer sesuai nominal, lalu tekan Cek Pembayaran</p>
        </div>
        <div v-else-if="isDirectQr" class="qr-display-box">
          <div id="qris-container"></div>
          <p class="font-caption" style="margin-top: 8px;">Pindai QR dengan e-Wallet / Mobile Banking</p>
          <p class="font-caption" style="color:#71717a;">Untuk simulator Midtrans, tempel <strong>string</strong> QR (bukan gambarnya).</p>
          <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 6px;" @click="copyQrString">{{ qrCopied ? 'Tersalin!' : 'Salin QR String' }}</button>
        </div>
        <div v-else-if="payment.snap_token" class="card-soft" style="margin-bottom: var(--spacing-md); padding: var(--spacing-md); text-align: center;">
          <p class="font-body-sm">Popup {{ choice === 'va' ? 'Virtual Account' : 'QRIS' }} Midtrans terbuka…</p>
          <p v-if="snapError" class="font-caption" style="color: var(--color-semantic-danger, #dc2626);">{{ snapError }}</p>
          <a v-if="payment.redirect_url" :href="payment.redirect_url" target="_blank" rel="noopener" class="font-caption">atau buka halaman bayar di tab baru</a>
        </div>

        <div v-else class="qr-display-box">
          <div id="qris-container"></div>
          <p class="font-caption" style="margin-top: 8px;">Pindai QRIS dengan e-Wallet / Mobile Banking (Mode Demo)</p>
        </div>

        <div class="table-toolbar">
          <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" @click="check">Cek Pembayaran</button>
          <button type="button" class="btn btn-secondary btn-sm" @click="backToChoice">← Ganti metode</button>
        </div>

        <div v-if="demoMode && payment && payment.status === 'pending'" class="card-soft" style="margin-top: var(--spacing-md); padding: var(--spacing-md); text-align: center;">
          <div class="font-caption" style="color: #71717a; margin-bottom: 8px;">MODE DEMO — simulasi hasil bayar</div>
          <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" @click="simulate('pay')">Simulasi Lunas</button>
            <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" @click="simulate('fail')">Simulasi Gagal</button>
            <button type="button" class="btn btn-secondary btn-sm" :disabled="busy" @click="simulate('expire')">Simulasi Kedaluwarsa</button>
          </div>
        </div>
      </template>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" @click="close">Tutup</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { rupiah } from '../utils/format.js';
import { api } from '../api/client.js';
import { refreshApi } from '../store/session.js';
import { notify, notifySuccess } from '../store/alert.js';
import { ensureSnapLoaded, payWithSnapToken } from '../utils/midtrans.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  orderNumber: { type: String, default: '' },
  orderTotal: { type: Number, default: 0 }
});
const emit = defineEmits(['close', 'paid']);

const choice = ref(null); // null | 'manual' | 'qris' | 'va'
const payment = ref(null);
const demoMode = ref(false);
const loading = ref(false);
const busy = ref(false);
const countdown = ref('');
const loadError = ref('');
const snapLoading = ref(false);
const snapError = ref('');
const cashReceived = ref(0);
let timer = null;

const cashChange = computed(() => Number(cashReceived.value || 0) - Number(props.orderTotal || 0));
const banks = [
  { code: 'bca', label: 'BCA' },
  { code: 'bri', label: 'BRI' },
  { code: 'bni', label: 'BNI' },
  { code: 'permata', label: 'Permata' }
];
const isDirectQr = computed(() => choice.value === 'qris' && !!payment.value && !payment.value.snap_token && !payment.value.va_number);
const qrCopied = ref(false);

function reset() {
  stopTimer();
  choice.value = null;
  payment.value = null;
  demoMode.value = false;
  loading.value = false;
  busy.value = false;
  loadError.value = '';
  snapError.value = '';
  cashReceived.value = 0;
}

function chooseManual() {
  choice.value = 'manual';
  cashReceived.value = props.orderTotal || 0;
}

async function chooseMidtrans(channel, bank = null) {
  choice.value = channel;
  loading.value = true;
  loadError.value = '';
  payment.value = null;
  demoMode.value = false;
  try {
    const res = await api.createPayment(props.orderNumber, 'midtrans', { channel, ...(bank ? { bank } : {}) });
    payment.value = res.data;
    demoMode.value = !!res.demo_mode;
    await nextTick();
    await drawQr();
    startTimer();
    if (payment.value.snap_token) await payWithSnap();
  } catch (e) {
    loadError.value = e.data?.message || e.message || 'Gagal membuat pembayaran.';
    notify('Gagal membuat pembayaran: ' + loadError.value);
  } finally {
    loading.value = false;
  }
}

function backToChoice() {
  stopTimer();
  choice.value = null;
  payment.value = null;
  loadError.value = '';
}

async function payWithSnap() {
  if (!payment.value?.snap_token) return;
  snapError.value = '';
  snapLoading.value = true;
  try {
    await ensureSnapLoaded(payment.value.midtrans_client_key, payment.value.midtrans_is_production);
    payWithSnapToken(payment.value.snap_token, {
      onSuccess: async () => { await check(); },
      onPending: async () => {
        notifySuccess('Pembayaran tertunda, menunggu konfirmasi Midtrans.');
        await check();
      },
      onError: () => { snapError.value = 'Pembayaran gagal di Midtrans.'; },
      onClose: () => { check(); }
    });
  } catch (e) {
    snapError.value = e.message || 'Gagal membuka popup Midtrans.';
  } finally {
    snapLoading.value = false;
  }
}

async function confirmCash() {
  if (!(cashReceived.value >= (props.orderTotal || 0))) return;
  busy.value = true;
  try {
    await api.updatePayment(props.orderNumber, 'paid');
    await refreshApi();
    notifySuccess(`Pesanan ${props.orderNumber} lunas tunai. Kembalian ${rupiah(cashChange.value)}.`);
    emit('paid', props.orderNumber);
    close();
  } catch (e) {
    notify('Gagal menyimpan pembayaran tunai: ' + (e.data?.message || e.message));
  } finally {
    busy.value = false;
  }
}

async function drawQr() {
  const box = document.getElementById('qris-container');
  if (!box || !payment.value || payment.value.snap_token) return;
  box.innerHTML = '';
  const text = payment.value.qr_data || payment.value.payment_code;
  if (!text) return;
  try {
    const url = await QRCode.toDataURL(text, { width: 200, margin: 2 });
    const img = document.createElement('img');
    img.src = url;
    img.alt = 'QRIS Pembayaran';
    img.style.width = '200px';
    img.style.height = '200px';
    box.appendChild(img);
  } catch (e) {
    box.textContent = text;
  }
}

function tick() {
  if (!payment.value || !payment.value.expires_at) {
    countdown.value = '';
    return;
  }
  const ms = new Date(payment.value.expires_at).getTime() - Date.now();
  if (ms <= 0) {
    countdown.value = 'kedaluarsa';
    return;
  }
  const m = Math.floor(ms / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  countdown.value = `sisa ${m}:${String(s).padStart(2, '0')}`;
}

function startTimer() {
  stopTimer();
  tick();
  timer = setInterval(tick, 1000);
}

function stopTimer() {
  if (timer) clearInterval(timer);
  timer = null;
}

async function check() {
  busy.value = true;
  try {
    const res = await api.paymentStatus(props.orderNumber);
    payment.value = res.data;
    await refreshApi();
    if (res.data.status === 'paid') {
      notifySuccess(`Pesanan ${props.orderNumber} lunas.`);
      emit('paid', props.orderNumber);
      close();
    }
  } catch (e) {
    notify('Gagal cek status: ' + (e.data?.message || e.message));
  } finally {
    busy.value = false;
  }
}

function copyVa() {
  if (payment.value?.va_number && navigator.clipboard) navigator.clipboard.writeText(payment.value.va_number);
}

function copyQrString() {
  const s = payment.value?.qr_data;
  if (s && navigator.clipboard) {
    navigator.clipboard.writeText(s);
    qrCopied.value = true;
    setTimeout(() => { qrCopied.value = false; }, 2000);
  }
}

async function simulate(action) {
  if (!payment.value) return;
  busy.value = true;
  try {
    await api.simulatePayment(props.orderNumber, action);
    await check();
  } catch (e) {
    notify('Simulasi gagal: ' + (e.data?.message || e.message));
  } finally {
    busy.value = false;
  }
}

function close() {
  reset();
  emit('close');
}

watch(() => props.open, (v) => {
  if (v && props.orderNumber) reset();
});

onUnmounted(stopTimer);
</script>

<style scoped>
.pay-choices {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: var(--spacing-md);
}
.pay-choice {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
  padding: 14px 16px;
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
  background: var(--color-canvas);
  cursor: pointer;
}
.pay-choice:hover {
  border-color: var(--color-ink);
}
</style>
