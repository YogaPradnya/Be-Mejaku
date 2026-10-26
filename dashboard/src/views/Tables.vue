<template>
  <section class="page-view active">
    <div class="section-header align-inline">
      <h1 class="section-title">Kelola Meja</h1>
      <button class="btn btn-primary btn-sm" :disabled="!canManage" @click="openAdd">+ Tambah Meja</button>
    </div>

    <div class="card">
      <div class="table-toolbar">
        <div class="font-caption" style="color: #71717a;">
          {{ canManage ? 'Daftar meja QR beserta status dan pesanan aktifnya.' : 'Masuk sebagai admin untuk mengelola meja.' }}
        </div>
        <div class="topbar-search table-search-input">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="search" type="text" placeholder="Cari kode / lantai...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Lantai</th>
              <th>Status</th>
              <th>Order Aktif</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="5" style="text-align: center; padding: 28px; color: #71717a;">{{ canManage ? 'Tidak ada meja yang cocok.' : 'Belum ada data — silakan masuk.' }}</td>
            </tr>
            <tr v-for="t in rows" :key="t.id">
              <td><strong class="font-mono">{{ t.code }}</strong></td>
              <td>{{ t.floor }}</td>
              <td>
                <span class="badge" :class="t.is_active ? 'badge-success' : 'badge-neutral'">
                  <span class="badge-dot"></span>{{ t.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="font-mono">{{ t.active_orders_count ?? 0 }}</td>
              <td class="col-action">
                <button class="btn btn-secondary btn-sm" @click="openQr(t)">QR</button>
                <button class="btn btn-secondary btn-sm" @click="openEdit(t)">Ubah</button>
                <button class="btn btn-secondary btn-sm" @click="toggle(t)">{{ t.is_active ? 'Off' : 'On' }}</button>
                <button class="btn btn-secondary btn-sm" style="color: var(--color-semantic-danger); border-color: rgba(220, 38, 38, 0.3);" @click="remove(t)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="modal-backdrop active" @click.self="showModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <h3 class="modal-title">{{ editing ? 'Ubah Meja' : 'Tambah Meja' }}</h3>
          <button class="modal-close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="save">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
            <div class="form-group">
              <label class="form-label">Kode (cth: A12)</label>
              <input v-model="form.code" type="text" class="form-input font-mono" required maxlength="20" placeholder="A12">
            </div>
            <div class="form-group">
              <label class="form-label">Lantai</label>
              <input v-model="form.floor" type="text" class="form-input" required maxlength="50" placeholder="Lantai 2">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" @click="showModal = false">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">{{ editing ? 'Simpan' : 'Tambah' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="qrTable" class="modal-backdrop active" @click.self="qrTable = null">
      <div class="modal-card" style="text-align: center;">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">QR Meja {{ qrTable.code }}</h3>
            <div class="font-caption" style="color: #71717a;">Tempel di meja, pelanggan pindai untuk memesan</div>
          </div>
          <button class="modal-close-btn" @click="qrTable = null">&times;</button>
        </div>
        <div class="qr-display-box">
          <div id="table-qr-container" style="display:flex; justify-content:center;"></div>
          <p class="font-mono" style="font-size:12px; margin-top:8px; word-break:break-all;">{{ qrUrl }}</p>
        </div>
        <div class="form-group" style="text-align:left; margin-top: var(--spacing-md);">
          <label class="form-label" for="qr-base-url">URL aplikasi pelanggan (untuk QR cetak)</label>
          <input id="qr-base-url" v-model="qrBase" type="text" class="form-input font-mono" style="font-size:12px;" placeholder="https://mejaku-anda.com">
          <p class="font-caption" style="color: #71717a; margin-top: 4px;">QR mengarah ke <span class="font-mono">/?table=KODE</span> aplikasi pelanggan.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="copyQrUrl">Salin Link</button>
          <button type="button" class="btn btn-primary btn-sm" @click="printQr">Cetak QR</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { session } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';

const tables = ref([]);
const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const busy = ref(false);
const form = reactive({ code: '', floor: '' });
const qrTable = ref(null);
const customerAppBase = import.meta.env.VITE_CUSTOMER_APP_URL || '';
const qrBase = ref(
  typeof window !== 'undefined'
    ? (customerAppBase || window.location.origin)
    : customerAppBase
);
const qrUrl = computed(() => `${String(qrBase.value || '').replace(/\/+$/, '')}/pesan/${qrTable.value?.code || ''}`);

async function openQr(t) {
  qrTable.value = t;
  await nextTick();
  drawTableQr();
}

watch(qrUrl, () => { if (qrTable.value) drawTableQr(); });

async function drawTableQr() {
  const box = document.getElementById('table-qr-container');
  if (!box) return;
  box.innerHTML = '';
  try {
    const url = await QRCode.toDataURL(qrUrl.value, { width: 200, margin: 2 });
    const img = document.createElement('img');
    img.src = url;
    img.alt = `QR Meja ${qrTable.value?.code || ''}`;
    img.style.width = '200px';
    img.style.height = '200px';
    box.appendChild(img);
  } catch (e) {
    box.textContent = qrUrl.value;
  }
}

function copyQrUrl() {
  if (navigator.clipboard && qrUrl.value) navigator.clipboard.writeText(qrUrl.value);
}

function printQr() {
  // Cetak hanya QR (tanpa seluruh dashboard).
  const box = document.getElementById('table-qr-container');
  const img = box ? box.querySelector('img') : null;
  const w = window.open('', '_blank', 'width=420,height=520');
  if (!w) {
    window.print();
    return;
  }
  w.document.write(`<html><head><title>QR Meja ${qrTable.value?.code || ''}</title></head><body style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><h2>Meja ${qrTable.value?.code || ''}</h2>${img ? `<img src="${img.src}" style="width:260px;height:260px;" />` : `<p>${qrUrl.value}</p>`}<p>${qrUrl.value}</p><script>window.onload=function(){window.print();}<\/script></body></html>`);
  w.document.close();
}

const canManage = computed(() => session.loggedIn && session.apiOnline);

const rows = computed(() => {
  const q = (search.value || session.globalSearch).trim().toLowerCase();
  return tables.value.filter((t) => {
    if (q && !(`${t.code} ${t.floor}`.toLowerCase().includes(q))) return false;
    return true;
  });
});

async function fetchTables() {
  if (!canManage.value) {
    tables.value = [];
    return;
  }
  try {
    const res = await api.listTables();
    tables.value = res.data || [];
  } catch (e) {
    notify('Gagal memuat meja: ' + e.message);
  }
}

watch(() => session.apiOnline, fetchTables, { immediate: true });

function openAdd() {
  editing.value = null;
  Object.assign(form, { code: '', floor: '' });
  showModal.value = true;
}

function openEdit(t) {
  editing.value = t;
  Object.assign(form, { code: t.code, floor: t.floor });
  showModal.value = true;
}

async function save() {
  busy.value = true;
  try {
    // Samakan dengan backend (strtoupper+trim) agar tidak 422 duplikat beda case.
    const payload = { code: String(form.code || '').trim().toUpperCase(), floor: String(form.floor || '').trim() };
    if (editing.value) {
      await api.updateTable(editing.value.id, payload);
      notifySuccess(`Meja ${payload.code} diperbarui.`);
    } else {
      await api.createTable(payload);
      notifySuccess(`Meja ${payload.code} ditambahkan.`);
    }
    showModal.value = false;
    await fetchTables();
  } catch (e) {
    notify(e.data?.message || e.message || 'Gagal menyimpan meja.');
  } finally {
    busy.value = false;
  }
}

async function toggle(t) {
  try {
    await api.toggleTable(t.id);
    await fetchTables();
  } catch (e) {
    notify('Gagal mengubah status meja: ' + e.message);
  }
}

async function remove(t) {
  if (!window.confirm(`Hapus meja ${t.code}? Ditolak bila masih ada pesanan aktif.`)) return;
  try {
    await api.deleteTable(t.id);
    await fetchTables();
    notifySuccess(`Meja ${t.code} dihapus.`);
  } catch (e) {
    notify(e.data?.message || e.message);
  }
}
</script>
