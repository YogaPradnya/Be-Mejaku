<template>
  <section class="page-view active">
    <div class="section-header align-inline">
      <h1 class="section-title">Kelola Promo</h1>
      <button class="btn btn-primary btn-sm" :disabled="!canManage" @click="openAdd">+ Tambah Promo</button>
    </div>

    <div class="card">
      <div class="table-toolbar">
        <div class="font-caption" style="color: #71717a;">
          {{ canManage ? 'Kode promo, tipe diskon, dan masa berlakunya.' : 'Masuk sebagai admin untuk mengelola promo.' }}
        </div>
        <div class="topbar-search table-search-input">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="search" type="text" placeholder="Cari kode promo...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Tipe</th>
              <th>Nilai</th>
              <th>Min. Order</th>
              <th>Berlaku s/d</th>
              <th>Status</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="7" style="text-align: center; padding: 28px; color: #71717a;">{{ canManage ? 'Tidak ada promo yang cocok.' : 'Belum ada data — silakan masuk.' }}</td>
            </tr>
            <tr v-for="p in rows" :key="p.id">
              <td><strong class="font-mono">{{ p.code }}</strong></td>
              <td><span class="badge badge-neutral">{{ p.type === 'percentage' ? 'Persen' : 'Nominal' }}</span></td>
              <td><strong>{{ p.type === 'percentage' ? p.value + '%' : rupiah(p.value) }}</strong></td>
              <td class="font-mono" style="font-size: 12px;">{{ rupiah(p.min_order) }}</td>
              <td class="font-mono" style="font-size: 12px;">{{ shortDate(p.valid_until) }}</td>
              <td>
                <span class="badge" :class="p.is_valid_now ? 'badge-success' : 'badge-neutral'">
                  <span class="badge-dot"></span>{{ p.is_valid_now ? 'Berlaku' : 'Tidak Berlaku' }}
                </span>
              </td>
              <td class="col-action">
                <button class="btn btn-secondary btn-sm" @click="openEdit(p)">Ubah</button>
                <button class="btn btn-secondary btn-sm" style="color: var(--color-semantic-danger); border-color: rgba(220, 38, 38, 0.3);" @click="remove(p)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="modal-backdrop active" @click.self="showModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <h3 class="modal-title">{{ editing ? 'Ubah Promo' : 'Tambah Promo' }}</h3>
          <button class="modal-close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="save">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
            <div class="form-group">
              <label class="form-label">Kode</label>
              <input v-model="form.code" type="text" class="form-input font-mono" required maxlength="50" placeholder="HEMAT5K">
            </div>
            <div class="form-group">
              <label class="form-label">Tipe</label>
              <select v-model="form.type" class="form-select" required>
                <option value="fixed">Nominal (Rp)</option>
                <option value="percentage">Persen (%)</option>
              </select>
            </div>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
            <div class="form-group">
              <label class="form-label">{{ form.type === 'percentage' ? 'Persen (1–100)' : 'Nominal (Rp)' }}</label>
              <input v-model="form.value" type="number" class="form-input" required min="1" :max="form.type === 'percentage' ? 100 : undefined" placeholder="5000">
            </div>
            <div class="form-group">
              <label class="form-label">Min. Order (Rp)</label>
              <input v-model="form.min_order" type="number" class="form-input" min="0" placeholder="30000">
            </div>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
            <div class="form-group">
              <label class="form-label">Berlaku Dari</label>
              <input v-model="form.valid_from" type="date" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label">Berlaku Sampai</label>
              <input v-model="form.valid_until" type="date" class="form-input">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input v-model="form.is_active" type="checkbox" style="width: 18px; height: 18px;">
              Promo aktif
            </label>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" @click="showModal = false">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">{{ editing ? 'Simpan' : 'Tambah' }}</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { rupiah } from '../utils/format.js';
import { session } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';

const promos = ref([]);
const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const busy = ref(false);
const form = reactive({ code: '', type: 'fixed', value: '', min_order: 0, valid_from: '', valid_until: '', is_active: true });

const canManage = computed(() => session.loggedIn && session.apiOnline);

const rows = computed(() => {
  const q = (search.value || session.globalSearch).trim().toLowerCase();
  return promos.value.filter((p) => {
    if (q && !p.code.toLowerCase().includes(q)) return false;
    return true;
  });
});

function shortDate(iso) {
  if (!iso) return '-';
  return new Date(iso).toLocaleDateString('id-ID');
}

async function fetchPromos() {
  if (!canManage.value) {
    promos.value = [];
    return;
  }
  try {
    const res = await api.listPromos();
    promos.value = res.data || [];
  } catch (e) {
    notify('Gagal memuat promo: ' + e.message);
  }
}

watch(() => session.apiOnline, fetchPromos, { immediate: true });

function openAdd() {
  editing.value = null;
  Object.assign(form, { code: '', type: 'fixed', value: '', min_order: 0, valid_from: '', valid_until: '', is_active: true });
  showModal.value = true;
}

function openEdit(p) {
  editing.value = p;
  Object.assign(form, {
    code: p.code,
    type: p.type,
    value: p.value,
    min_order: p.min_order || 0,
    valid_from: (p.valid_from || '').slice(0, 10),
    valid_until: (p.valid_until || '').slice(0, 10),
    is_active: !!p.is_active
  });
  showModal.value = true;
}

function payload() {
  const body = {
    code: form.code.trim(),
    type: form.type,
    value: Number(form.value),
    min_order: Number(form.min_order) || 0,
    is_active: !!form.is_active
  };
  if (form.valid_from) body.valid_from = form.valid_from;
  if (form.valid_until) body.valid_until = form.valid_until;
  return body;
}

async function save() {
  busy.value = true;
  try {
    if (editing.value) {
      await api.updatePromo(editing.value.id, payload());
      notifySuccess(`Promo ${form.code} diperbarui.`);
    } else {
      await api.createPromo(payload());
      notifySuccess(`Promo ${form.code} ditambahkan.`);
    }
    showModal.value = false;
    await fetchPromos();
  } catch (e) {
    notify(e.data?.message || e.message || 'Gagal menyimpan promo.');
  } finally {
    busy.value = false;
  }
}

async function remove(p) {
  if (!window.confirm(`Hapus promo ${p.code}?`)) return;
  try {
    await api.deletePromo(p.id);
    await fetchPromos();
    notifySuccess(`Promo ${p.code} dihapus.`);
  } catch (e) {
    notify(e.data?.message || e.message);
  }
}
</script>
