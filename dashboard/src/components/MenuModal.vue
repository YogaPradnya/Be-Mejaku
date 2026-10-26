<template>
  <div v-if="open" class="modal-backdrop active" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title">{{ editing ? 'Ubah Menu' : 'Tambah Menu Baru' }}</h3>
        <button class="modal-close-btn" @click="emit('close')">&times;</button>
      </div>
      <form @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label" for="menu-name">Nama Menu / Produk</label>
          <input id="menu-name" v-model="form.name" type="text" class="form-input" required placeholder="Contoh: Nasi Campur Bali Agung">
        </div>

        <div class="form-group">
          <label class="form-label">Merchant</label>
          <div class="form-input" style="background: var(--color-surface-soft);">{{ merchants.length ? merchants[0].name : 'Memuat…' }}</div>
          <div class="font-caption" style="color:#71717a; margin-top:4px;">Konsep 1 merchant — otomatis ikut merchant ini.</div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
          <div class="form-group">
            <label class="form-label" for="menu-price">Harga Dasar (Rp)</label>
            <input id="menu-price" v-model="form.base_price" type="number" class="form-input" required min="0" step="500" placeholder="58000">
          </div>

          <div class="form-group">
            <label class="form-label" for="menu-stock">Stok</label>
            <input id="menu-stock" v-model="form.stock" type="number" class="form-input" required min="0" placeholder="25">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="menu-desc">Deskripsi Singkat</label>
          <textarea id="menu-desc" v-model="form.description" class="form-textarea" placeholder="Rincian bahan, catatan rasa, atau deskripsi menu..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Foto Menu (Cloudinary)</label>
          <div style="display:flex; gap:12px; align-items:flex-start;">
            <img v-if="form.image" :src="form.image" alt="Foto menu" style="width:96px; height:96px; object-fit:cover; border-radius:8px; border:1px solid var(--color-hairline);" />
            <div v-else style="width:96px; height:96px; display:flex; align-items:center; justify-content:center; border:1px dashed var(--color-hairline); border-radius:8px; color:#71717a; font-size:12px;">Belum ada foto</div>
            <div style="flex:1;">
              <input type="file" accept="image/jpeg,image/png,image/webp" @change="onFileChange" :disabled="uploading" />
              <div class="font-caption" style="color:#71717a; margin-top:4px;">JPG/PNG/WebP, maks 5MB. Diunggah via backend ke Cloudinary.</div>
              <div v-if="uploading" class="font-caption" style="color:#71717a;">Mengunggah…</div>
              <div v-if="uploadError" class="font-caption" style="color: var(--color-semantic-danger);">{{ uploadError }}</div>
              <button v-if="form.image" type="button" class="btn btn-secondary btn-sm" style="margin-top:6px;" @click="form.image = ''">Hapus Foto</button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
            <input v-model="form.has_spice_option" type="checkbox" style="width: 18px; height: 18px;">
            Sediakan pilihan Level Pedas
          </label>
        </div>

        <div class="form-group">
          <label class="form-label">Opsi Porsi (nama + tambahan harga)</label>
          <div v-for="(p, i) in form.portions" :key="'p' + i" style="display: grid; grid-template-columns: 1fr 130px auto; gap: 8px; margin-bottom: 8px;">
            <input v-model="p.name" type="text" class="form-input" placeholder="cth: Jumbo">
            <input v-model="p.surcharge" type="number" class="form-input" min="0" placeholder="+Rp">
            <button type="button" class="btn btn-secondary btn-sm" @click="form.portions.splice(i, 1)">&times;</button>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" @click="form.portions.push({ name: '', surcharge: 0 })">+ Tambah Porsi</button>
        </div>

        <div class="form-group">
          <label class="form-label">Pelengkap / Addons (nama + harga)</label>
          <div v-for="(a, i) in form.addons" :key="'a' + i" style="display: grid; grid-template-columns: 1fr 130px auto; gap: 8px; margin-bottom: 8px;">
            <input v-model="a.name" type="text" class="form-input" placeholder="cth: Ekstra Sambal Matah">
            <input v-model="a.price" type="number" class="form-input" min="0" placeholder="Rp">
            <button type="button" class="btn btn-secondary btn-sm" @click="form.addons.splice(i, 1)">&times;</button>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" @click="form.addons.push({ name: '', price: 0 })">+ Tambah Addon</button>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm" :disabled="busy || uploading">{{ editing ? 'Simpan Perubahan' : 'Simpan Menu' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { api } from '../api/client.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  merchants: { type: Array, default: () => [] },
  editing: { type: Object, default: null }
});
const emit = defineEmits(['close', 'save']);

const form = reactive({
  name: '',
  merchant_id: '',
  base_price: '',
  stock: '',
  description: '',
  image: '',
  has_spice_option: true,
  portions: [],
  addons: []
});
const busy = ref(false);
const uploading = ref(false);
const uploadError = ref('');

function reset() {
  Object.assign(form, {
    name: '',
    merchant_id: props.merchants.length ? props.merchants[0].id : '',
    base_price: '',
    stock: '',
    description: '',
    image: '',
    has_spice_option: true,
    portions: [],
    addons: []
  });
  busy.value = false;
  uploading.value = false;
  uploadError.value = '';
}

watch(() => props.open, (v) => {
  if (!v) return;
  reset();
  if (props.editing) {
    const e = props.editing;
    form.name = e.name || '';
    // Single-merchant: selalu pakai merchant pertama, abaikan merchant_id lama.
    form.merchant_id = props.merchants.length ? props.merchants[0].id : (e.merchant_id || '');
    form.base_price = e.base_price ?? '';
    form.stock = e.stock ?? '';
    form.description = e.description || '';
    form.image = e.image || '';
    form.has_spice_option = e.has_spice_option !== false;
    form.portions = (e.portions || []).map((p) => ({ name: p.name, surcharge: p.surcharge }));
    form.addons = (e.addons || []).map((a) => ({ name: a.name, price: a.price }));
  }
});

async function onFileChange(ev) {
  const file = ev.target.files && ev.target.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    uploadError.value = 'Ukuran file maksimal 5MB.';
    ev.target.value = '';
    return;
  }
  uploading.value = true;
  uploadError.value = '';
  try {
    const res = await api.uploadMenuImage(file);
    form.image = res.data?.url || '';
    if (!form.image) uploadError.value = 'Upload berhasil tapi URL kosong.';
  } catch (e) {
    uploadError.value = e.data?.message || e.message || 'Gagal mengunggah foto.';
  } finally {
    uploading.value = false;
    ev.target.value = '';
  }
}

function submit() {
  if (uploading.value) return;
  busy.value = true;
  try {
    emit('save', {
      payload: {
        merchant_id: Number(form.merchant_id) || undefined,
        name: form.name.trim(),
        description: form.description.trim(),
        // null eksplisit = hapus foto; undefined = biarkan seperti semula.
        image: form.image || null,
        base_price: Number(form.base_price),
        stock: Number(form.stock),
        has_spice_option: !!form.has_spice_option,
        portions: form.portions.filter((p) => p.name.trim()).map((p) => ({ name: p.name.trim(), surcharge: Number(p.surcharge) || 0 })),
        addons: form.addons.filter((a) => a.name.trim()).map((a) => ({ name: a.name.trim(), price: Number(a.price) || 0 }))
      },
      editingId: props.editing ? props.editing.id : null
    });
  } finally {
    // busy hanya direset parent via resetBusy() agar tombol tidak macet
    // bila penyimpanan gagal (422) — modal tetap terbuka.
  }
}

function resetBusy() {
  busy.value = false;
}

defineExpose({ resetBusy });
</script>
