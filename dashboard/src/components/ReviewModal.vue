<template>
  <div v-if="open && menu" class="modal-backdrop active" @click.self="close">
    <div class="modal-card review-modal">
      <div class="modal-header">
        <div>
          <h3 class="modal-title">Ulasan & Rating</h3>
          <div class="font-caption" style="color: #71717a;">{{ menu.name }}</div>
        </div>
        <button type="button" class="modal-close-btn" @click="close">&times;</button>
      </div>

      <!-- Rating summary card -->
      <div class="card-soft review-summary-card">
        <div class="rating-big-num">{{ Number(currentRating || 0).toFixed(1) }}</div>
        <div class="rating-summary-meta">
          <div class="star-row">
            <span v-for="i in 5" :key="i" class="star-icon" :class="{ filled: i <= Math.round(currentRating || 0) }">★</span>
          </div>
          <div class="font-caption" style="color: #71717a;">
            Berdasarkan {{ currentCount || 0 }} ulasan pelanggan
          </div>
        </div>
      </div>

      <!-- Tab toggle: Ulasan & Tulis Ulasan -->
      <div class="review-tabs">
        <button
          type="button"
          class="review-tab-btn"
          :class="{ active: activeTab === 'list' }"
          @click="activeTab = 'list'"
        >
          Daftar Ulasan ({{ reviews.length }})
        </button>
        <button
          type="button"
          class="review-tab-btn"
          :class="{ active: activeTab === 'write' }"
          @click="activeTab = 'write'"
        >
          Tulis Ulasan
        </button>
      </div>

      <!-- Tab 1: List Ulasan -->
      <div v-if="activeTab === 'list'" class="review-list-container">
        <div v-if="loadingReviews" class="font-body-sm" style="color: #71717a; text-align: center; padding: 20px 0;">
          Memuat ulasan...
        </div>
        <div v-else-if="reviews.length === 0" class="review-empty">
          <p class="font-body-sm" style="color: #71717a;">Belum ada ulasan untuk menu ini.</p>
          <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px;" @click="activeTab = 'write'">
            Jadilah yang pertama mengulas
          </button>
        </div>
        <div v-else class="review-items-list">
          <div v-for="rev in reviews" :key="rev.id" class="review-item">
            <div class="review-item-header">
              <span class="review-author">{{ rev.customer_name || 'Pelanggan' }}</span>
              <span class="review-stars">
                <span v-for="s in 5" :key="s" class="star-icon-small" :class="{ filled: s <= rev.rating }">★</span>
              </span>
            </div>
            <div v-if="rev.review" class="review-item-body">
              {{ rev.review }}
            </div>
            <div class="review-item-date font-caption">
              {{ formatDate(rev.created_at) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Tulis Ulasan Form -->
      <div v-if="activeTab === 'write'" class="review-form-container">
        <form @submit.prevent="submitReview">
          <div class="form-group" style="text-align: center; margin-bottom: 16px;">
            <label class="form-label" style="margin-bottom: 6px;">Pilih Bintang</label>
            <div class="interactive-star-row">
              <button
                v-for="s in 5"
                :key="s"
                type="button"
                class="star-pick-btn"
                :class="{ active: s <= (hoverRating || selectedRating) }"
                @mouseenter="hoverRating = s"
                @mouseleave="hoverRating = 0"
                @click="selectedRating = s"
              >
                ★
              </button>
            </div>
            <div class="rating-label font-caption">
              {{ ratingLabels[hoverRating || selectedRating] || 'Klik bintang di atas' }}
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="rev-author">Nama Anda</label>
            <input
              id="rev-author"
              v-model="customerName"
              type="text"
              class="form-input"
              maxlength="100"
              placeholder="Contoh: Budi Santoso"
            >
          </div>

          <div class="form-group">
            <label class="form-label" for="rev-text">Ulasan / Masukan (opsional)</label>
            <textarea
              id="rev-text"
              v-model="reviewText"
              class="form-input"
              rows="3"
              maxlength="500"
              placeholder="Ceritakan rasa hidangan, porsi, atau pengalaman Anda..."
            ></textarea>
          </div>

          <div v-if="errorMessage" class="review-error-box font-caption">
            {{ errorMessage }}
          </div>
          <div v-if="successMessage" class="review-success-box font-caption">
            {{ successMessage }}
          </div>

          <div class="review-form-actions">
            <button
              type="submit"
              class="btn btn-primary"
              style="width: 100%;"
              :disabled="submitting || selectedRating === 0"
            >
              {{ submitting ? 'Mengirim ulasan...' : 'Kirim Ulasan' }}
            </button>
          </div>
        </form>
      </div>

      <div class="modal-footer" style="margin-top: 16px;">
        <button type="button" class="btn btn-secondary btn-sm" @click="close">Tutup</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { api } from '../api/client.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  menu: { type: Object, default: null },
  orderNumber: { type: String, default: '' },
  initialCustomerName: { type: String, default: '' },
  initialTab: { type: String, default: 'list' }
});

const emit = defineEmits(['close', 'reviewed']);

const activeTab = ref('list');
const selectedRating = ref(5);
const hoverRating = ref(0);
const customerName = ref('');
const reviewText = ref('');
const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const loadingReviews = ref(false);
const reviews = ref([]);

const currentRating = ref(0);
const currentCount = ref(0);

const ratingLabels = {
  1: '1 Bintang - Sangat Kurang',
  2: '2 Bintang - Kurang Memuaskan',
  3: '3 Bintang - Cukup Baik',
  4: '4 Bintang - Enak & Puas',
  5: '5 Bintang - Sangat Enak & Direkomendasikan'
};

watch(() => props.open, (newVal) => {
  if (newVal && props.menu) {
    activeTab.value = props.initialTab || 'list';
    currentRating.value = Number(props.menu.rating || 0);
    currentCount.value = Number(props.menu.rating_count || 0);
    customerName.value = props.initialCustomerName || '';
    reviewText.value = '';
    selectedRating.value = 5;
    hoverRating.value = 0;
    errorMessage.value = '';
    successMessage.value = '';
    loadReviews();
  }
});

async function loadReviews() {
  if (!props.menu) return;
  loadingReviews.value = true;
  try {
    const res = await api.getMenuReviews(props.menu.id);
    reviews.value = res.data?.reviews || [];
    if (res.data?.menu) {
      currentRating.value = Number(res.data.menu.average_rating || 0);
      currentCount.value = Number(res.data.menu.rating_count || 0);
    }
  } catch (err) {
    reviews.value = [];
  } finally {
    loadingReviews.value = false;
  }
}

async function submitReview() {
  if (!props.menu || selectedRating.value === 0) return;
  submitting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    const payload = {
      rating: selectedRating.value,
      customer_name: customerName.value.trim() || 'Pelanggan',
      review: reviewText.value.trim() || undefined,
      order_number: props.orderNumber || undefined
    };

    const res = await api.submitMenuRating(props.menu.id, payload);
    successMessage.value = 'Terima kasih! Ulasan berhasil dikirim.';
    currentRating.value = Number(res.data?.average_rating || currentRating.value);
    currentCount.value = Number(res.data?.rating_count || (currentCount.value + 1));

    emit('reviewed', {
      menuId: props.menu.id,
      averageRating: currentRating.value,
      ratingCount: currentCount.value,
      newReview: res.data
    });

    // Refresh review list dan alihkan ke tab list setelah 1 detik
    await loadReviews();
    setTimeout(() => {
      activeTab.value = 'list';
      successMessage.value = '';
      reviewText.value = '';
    }, 1200);
  } catch (err) {
    errorMessage.value = err.data?.message || err.message || 'Gagal mengirim ulasan.';
  } finally {
    submitting.value = false;
  }
}

function formatDate(isoStr) {
  if (!isoStr) return '-';
  try {
    const d = new Date(isoStr);
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    });
  } catch (e) {
    return isoStr;
  }
}

function close() {
  emit('close');
}
</script>

<style scoped>
.review-modal {
  max-width: 480px;
  width: 90vw;
}

.review-summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  margin-bottom: 14px;
}

.rating-big-num {
  font-size: 32px;
  font-weight: 800;
  line-height: 1;
  color: var(--color-ink);
}

.star-row {
  display: flex;
  gap: 2px;
  font-size: 18px;
  color: #d1d5db;
}

.star-icon.filled {
  color: #f59e0b;
}

.review-tabs {
  display: flex;
  border-bottom: 1px solid var(--color-hairline);
  margin-bottom: 14px;
  gap: 8px;
}

.review-tab-btn {
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #71717a;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
}

.review-tab-btn.active {
  color: var(--color-ink);
  border-bottom-color: var(--color-ink);
}

.review-list-container {
  max-height: 280px;
  overflow-y: auto;
  padding-right: 4px;
}

.review-items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.review-item {
  padding: 10px 12px;
  border: 1px solid var(--color-hairline);
  border-radius: var(--rounded-md);
  background: var(--color-canvas);
}

.review-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.review-author {
  font-weight: 600;
  font-size: 13px;
}

.star-icon-small {
  color: #d1d5db;
  font-size: 13px;
}

.star-icon-small.filled {
  color: #f59e0b;
}

.review-item-body {
  font-size: 13px;
  color: #374151;
  line-height: 1.4;
  margin-bottom: 6px;
}

.review-item-date {
  color: #9ca3af;
  font-size: 11px;
}

.review-empty {
  text-align: center;
  padding: 24px 0;
}

.interactive-star-row {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin: 6px 0;
}

.star-pick-btn {
  background: none;
  border: none;
  font-size: 32px;
  cursor: pointer;
  color: #d1d5db;
  transition: transform 0.1s ease, color 0.1s ease;
  padding: 0 4px;
}

.star-pick-btn:hover {
  transform: scale(1.15);
}

.star-pick-btn.active {
  color: #f59e0b;
}

.rating-label {
  color: #6b7280;
  margin-top: 4px;
  min-height: 16px;
}

.review-error-box {
  color: var(--color-semantic-danger, #dc2626);
  background: #fef2f2;
  padding: 8px 12px;
  border-radius: var(--rounded-md);
  margin-bottom: 12px;
}

.review-success-box {
  color: var(--color-semantic-success, #16a34a);
  background: #f0fdf4;
  padding: 8px 12px;
  border-radius: var(--rounded-md);
  margin-bottom: 12px;
}
</style>
