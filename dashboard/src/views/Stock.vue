<template>
  <section class="page-view active">
    <div class="section-header">
      <div>
        <h1 class="section-title">Inventaris & Stok Gudang</h1>
      </div>
    </div>

    <div class="card">
      <div class="table-toolbar">
        <div class="font-caption" style="color: #71717a;">
          {{ session.loggedIn ? 'Stok diambil dari katalog backend (/api/admin/menu).' : 'Masuk sebagai admin untuk melihat inventaris.' }}
        </div>
        <div class="topbar-search table-search-input">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input v-model="search" type="text" placeholder="Filter stok produk...">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nama Produk</th>
              <th>Sisa Unit</th>
              <th>Status Ketersediaan</th>
              <th>Kondisi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="5" style="text-align: center; padding: 28px; color: #71717a;">
                {{ session.loggedIn ? 'Tidak ada barang yang cocok.' : 'Belum ada data — silakan masuk.' }}
              </td>
            </tr>
            <tr v-for="prod in rows" :key="prod.id">
              <td class="font-mono" style="font-size: 12px; width: 95px;">{{ prod.id }}</td>
              <td style="max-width: 180px;" :title="prod.name"><span class="text-truncate"><strong>{{ prod.name }}</strong></span></td>
              <td style="width: 100px;"><strong>{{ prod.stock }}</strong> unit</td>
              <td style="width: 120px;">
                <span class="badge" :class="Number(prod.stock) <= 5 ? 'badge-danger' : 'badge-success'">
                  <span class="badge-dot"></span>
                  {{ Number(prod.stock) <= 5 ? 'Stok Kritis' : 'Stok Aman' }}
                </span>
              </td>
              <td class="font-mono" style="font-size: 12px; color: #71717a;">{{ Number(prod.stock) <= 5 ? 'Segera Restock' : 'Cukup' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { session } from '../store/session.js';
import { api } from '../api/client.js';
import { notify } from '../store/alert.js';

const search = ref('');
const menus = ref([]);

async function fetchMenus() {
  if (!session.loggedIn || !session.apiOnline) {
    menus.value = [];
    return;
  }
  try {
    const res = await api.listMenus();
    menus.value = res.data || [];
  } catch (e) {
    notify('Gagal memuat inventaris: ' + e.message);
  }
}

watch(() => session.apiOnline, fetchMenus, { immediate: true });

const rows = computed(() => {
  const q = (search.value || session.globalSearch).trim().toLowerCase();
  return menus.value.filter((p) => {
    if (q && !(p.name.toLowerCase().includes(q) || String(p.id).includes(q))) return false;
    return true;
  });
});
</script>
