<template>
  <section class="page-view active">
    <div class="section-header align-inline">
      <h1 class="section-title">Manajemen Menu</h1>
      <button class="btn btn-primary btn-sm" :disabled="!session.loggedIn" @click="openAdd">+ Tambah Menu</button>
    </div>

    <div class="card">
      <div class="table-toolbar">
        <div class="font-caption" style="color: #71717a;">
          {{ merchants.length ? `Menu milik ${merchants[0].name}.` : 'Daftar menu merchant.' }}
        </div>

        <div class="topbar-search table-search-input">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="search" type="text" placeholder="Cari nama menu...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Foto</th>
              <th>Nama Menu</th>
              <th>Harga Dasar</th>
              <th>Jumlah Stok</th>
              <th>Rating</th>
              <th>Opsi</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="8" style="text-align: center; padding: 28px; color: #71717a;">
                {{ session.loggedIn ? 'Tidak ada menu yang ditemukan.' : 'Masuk sebagai admin untuk mengelola menu.' }}
              </td>
            </tr>
            <tr v-for="prod in rows" :key="prod.id">
              <td class="font-mono" style="font-size: 12px; font-weight: 600; width: 85px;">{{ prod.id }}</td>
              <td style="width: 64px;">
                <img v-if="prod.image" :src="prod.image" :alt="prod.name" style="width:48px; height:48px; object-fit:cover; border-radius:8px; border:1px solid var(--color-hairline);" loading="lazy" />
                <div v-else style="width:48px; height:48px; display:flex; align-items:center; justify-content:center; border:1px dashed var(--color-hairline); border-radius:8px; color:#a1a1aa; font-size:11px;">—</div>
              </td>
              <td style="max-width: 150px;" :title="prod.name">
                <span class="text-truncate"><strong>{{ prod.name }}</strong></span>
                <div v-if="!prod.is_available" class="font-caption" style="color: var(--color-semantic-danger);">Nonaktif</div>
              </td>
              <td style="width: 120px;"><strong>{{ rupiah(prod.base_price) }}</strong></td>
              <td style="width: 95px;">
                <span :class="Number(prod.stock) <= 5 ? 'badge badge-danger' : 'font-mono'">{{ prod.stock }} unit</span>
              </td>
              <td style="width: 110px;">
                <button
                  type="button"
                  style="display: flex; align-items: center; gap: 4px; background: none; border: none; cursor: pointer; padding: 0; font-size: 13px;"
                  title="Klik untuk melihat ulasan pelanggan"
                  @click="openReviews(prod)"
                >
                  <span style="color: #f59e0b;">★</span>
                  <strong>{{ Number(prod.rating || 0).toFixed(1) }}</strong>
                  <span class="font-caption" style="color: #71717a;">({{ prod.rating_count || 0 }})</span>
                </button>
              </td>
              <td style="max-width: 200px;">
                <span class="font-caption" style="color: #71717a;">
                  {{ (prod.portions_count ?? (prod.portions || []).length) }} porsi •
                  {{ (prod.addons_count ?? (prod.addons || []).length) }} addon •
                  {{ prod.has_spice_option ? 'Pedas Ya' : 'Tanpa Pedas' }}
                </span>
              </td>
              <td class="col-action">
                <button class="btn btn-secondary btn-sm" @click="openEdit(prod)">Ubah</button>
                <button class="btn btn-secondary btn-sm" @click="toggle(prod)">{{ prod.is_available ? 'Off' : 'On' }}</button>
                <button class="btn btn-secondary btn-sm" style="color: var(--color-semantic-danger); border-color: rgba(220, 38, 38, 0.3);" @click="remove(prod)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <MenuModal
      ref="modalRef"
      :open="showModal"
      :merchants="merchants"
      :editing="editing"
      @close="showModal = false; editing = null"
      @save="onSave"
    />

    <ReviewModal
      :open="showReviewModal"
      :menu="selectedReviewMenu"
      @close="showReviewModal = false; selectedReviewMenu = null"
      @reviewed="onAdminReviewed"
    />
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { rupiah } from '../utils/format.js';
import { session } from '../store/session.js';
import { api } from '../api/client.js';
import { notify, notifySuccess } from '../store/alert.js';
import MenuModal from '../components/MenuModal.vue';
import ReviewModal from '../components/ReviewModal.vue';

const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const modalRef = ref(null);
const showReviewModal = ref(false);
const selectedReviewMenu = ref(null);

function openReviews(prod) {
  selectedReviewMenu.value = prod;
  showReviewModal.value = true;
}

function onAdminReviewed(payload) {
  const p = menus.value.find((item) => item.id === payload.menuId);
  if (p) {
    p.rating = payload.averageRating;
    p.rating_count = payload.ratingCount;
  }
}

const menus = ref([]);
const merchants = ref([]);


async function fetchAll() {
  if (!session.loggedIn || !session.apiOnline) {
    menus.value = [];
    merchants.value = [];
    return;
  }
  try {
    const [mRes, mcRes] = await Promise.all([api.listMenus(), api.listMerchants()]);
    menus.value = mRes.data || [];
    merchants.value = mcRes.data || [];
  } catch (e) {
    notify('Gagal memuat menu: ' + e.message);
  }
}

watch(() => session.apiOnline, fetchAll, { immediate: true });

const rows = computed(() => {
  const q = (search.value || session.globalSearch).trim().toLowerCase();
  return menus.value.filter((p) => {
    if (q && !p.name.toLowerCase().includes(q)) return false;
    return true;
  });
});

function openAdd() {
  editing.value = null;
  showModal.value = true;
}

async function openEdit(prod) {
  try {
    const res = await api.getMenu(prod.id);
    editing.value = res.data;
    showModal.value = true;
  } catch (e) {
    notify('Gagal memuat menu: ' + e.message);
  }
}

async function onSave({ payload, editingId }) {
  try {
    if (editingId) {
      await api.updateMenu(editingId, payload);
      notifySuccess('Menu berhasil diperbarui.');
    } else {
      await api.createMenu(payload);
      notifySuccess('Menu berhasil ditambahkan.');
    }
    await fetchAll();
    showModal.value = false;
    editing.value = null;
  } catch (e) {
    notify(e.data?.message || e.message || 'Gagal menyimpan menu.');
    modalRef.value?.resetBusy?.();
  }
}

async function toggle(prod) {
  try {
    await api.toggleMenu(prod.id);
    await fetchAll();
  } catch (e) {
    notify('Gagal mengubah status: ' + e.message);
  }
}

async function remove(prod) {
  if (!window.confirm(`Nonaktifkan menu "${prod.name}"? (soft delete)`)) return;
  try {
    await api.deleteMenu(prod.id);
    await fetchAll();
    notifySuccess(`Menu "${prod.name}" dinonaktifkan.`);
  } catch (e) {
    notify('Gagal menghapus: ' + (e.data?.message || e.message));
  }
}
</script>
