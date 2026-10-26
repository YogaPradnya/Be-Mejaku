<template>
  <section class="page-view active">
    <div class="section-header align-inline">
      <div>
        <h1 class="section-title">Ringkasan Kinerja Toko</h1>
      </div>
    </div>

    <div v-if="!session.loggedIn" class="card" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
      <p class="section-subtitle" style="margin-bottom: 0;">Masuk sebagai admin untuk melihat ringkasan kinerja toko dari backend.</p>
      <router-link to="/login" class="btn btn-primary btn-sm">Masuk Sekarang</router-link>
    </div>

    <template v-else-if="metrics">
      <div class="metrics-grid">
        <div class="color-block-lime metric-panel">
          <div class="metric-header">
            <span class="metric-label">Total Pendapatan</span>
            <span class="badge badge-neutral">hari ini</span>
          </div>
          <div class="metric-value">{{ rupiah(metrics.revenue) }}</div>
          <div class="metric-footer"><span>Dari pesanan lunas</span></div>
        </div>

        <div class="color-block-lilac metric-panel">
          <div class="metric-header">
            <span class="metric-label">Total Pesanan</span>
            <span class="badge badge-neutral">hari ini</span>
          </div>
          <div class="metric-value">{{ metrics.totalOrders }}</div>
          <div class="metric-footer"><span>Seluruh transaksi tercatat</span></div>
        </div>

        <div class="color-block-mint metric-panel">
          <div class="metric-header">
            <span class="metric-label">Produk Terjual</span>
            <span class="badge badge-neutral">hari ini</span>
          </div>
          <div class="metric-value">{{ metrics.soldItems }} <span class="metric-unit">item</span></div>
          <div class="metric-footer"><span>Unit item terjual</span></div>
        </div>

        <div class="color-block-coral metric-panel">
          <div class="metric-header">
            <span class="metric-label">Dapur Aktif</span>
            <span class="badge badge-neutral">{{ metrics.pendingPayments }} menunggu bayar</span>
          </div>
          <div class="metric-value">{{ activeKitchen }} <span class="metric-unit">antre</span></div>
          <div class="metric-footer"><span>Pesanan aktif di dapur (diterima + disiapkan + siap)</span></div>
        </div>
      </div>

      <div class="charts-grid" style="margin-top: var(--spacing-xl);">
        <div class="card chart-card">
          <div class="section-header align-inline">
            <div>
              <h2 class="card-title">Tren Penjualan</h2>
              <p class="section-subtitle">{{ chartLoading ? 'Memuat data backend…' : 'Grafik omset harian dari backend (30 hari terakhir)' }}</p>
            </div>
            <div class="tab-group">
              <button class="tab-btn" :class="{ active: period === 'weekly' }" @click="setPeriod('weekly')">Minggu Ini</button>
              <button class="tab-btn" :class="{ active: period === 'monthly' }" @click="setPeriod('monthly')">Bulan Ini</button>
            </div>
          </div>
          <div class="chart-container">
            <canvas ref="canvas"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="section-header">
            <div>
              <h2 class="card-title">Produk Terlaris</h2>
              <p class="section-subtitle">Daftar item penjualan tertinggi</p>
            </div>
          </div>
          <div class="top-products-list">
            <div v-if="topProducts.length === 0" class="font-caption" style="color: #71717a;">Belum ada penjualan lunas hari ini.</div>
            <div v-for="(prod, i) in topProducts" :key="i" class="product-row-item">
              <div class="product-meta">
                <span class="product-name">{{ prod.name }}</span>
                <span class="product-category">menu #{{ prod.id }}</span>
              </div>
              <div class="product-sales">
                <span class="product-sold-qty">{{ prod.qty }} terjual</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
    <div v-else class="card">
      <p class="section-subtitle">Memuat statistik backend…</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Chart } from 'chart.js/auto';
import { rupiah } from '../utils/format.js';
import { session } from '../store/session.js';
import { api } from '../api/client.js';
import { notify } from '../store/alert.js';

const period = ref('weekly');
const canvas = ref(null);
const chartLoading = ref(false);
let chart = null;

// Data grafik diambil dari BE (GET /admin/stats?date=YYYY-MM-DD).
const chartSets = ref({
  weekly: { labels: [], data: [] },
  monthly: { labels: [], data: [] }
});

function isoDay(offset) {
  const d = new Date();
  d.setDate(d.getDate() - offset);
  return d.toISOString().slice(0, 10);
}

function dayLabel(iso) {
  return new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'short' });
}

async function fetchChartData() {
  if (!session.loggedIn || !session.apiOnline) return;
  chartLoading.value = true;
  try {
    // Ambil 30 hari terakhir dari BE, lalu kelompokkan:
    // mingguan = 7 hari terakhir; bulanan = 4 bucket mingguan.
    const days = Array.from({ length: 30 }, (_, i) => isoDay(29 - i));
    const results = await Promise.all(days.map((d) => api.getStats({ date: d }).catch(() => null)));
    const revenues = results.map((r) => Number(r?.data?.revenue_today || 0));
    const last7 = revenues.slice(-7);
    chartSets.value.weekly = { labels: days.slice(-7).map(dayLabel), data: last7 };
    const buckets = [
      revenues.slice(0, 7).reduce((a, b) => a + b, 0),
      revenues.slice(7, 14).reduce((a, b) => a + b, 0),
      revenues.slice(14, 21).reduce((a, b) => a + b, 0),
      revenues.slice(21, 30).reduce((a, b) => a + b, 0)
    ];
    chartSets.value.monthly = { labels: ['3 mgg lalu', '2 mgg lalu', 'Mgg lalu', 'Minggu ini'], data: buckets };
    applyPeriod();
  } catch (e) {
    notify('Gagal memuat grafik: ' + e.message);
  } finally {
    chartLoading.value = false;
  }
}

const metrics = computed(() => {
  if (!session.stats) return null;
  return {
    revenue: session.stats.revenue_today,
    totalOrders: session.stats.total_orders_today,
    soldItems: session.stats.sold_items_today,
    pendingPayments: session.stats.pending_payments
  };
});

const activeKitchen = computed(() => {
  const k = session.stats?.active_kitchen;
  if (!k) return 0;
  return (k.received || 0) + (k.preparing || 0) + (k.ready || 0);
});

const topProducts = computed(() => {
  const items = session.stats?.top_items;
  if (!items) return [];
  return items.slice(0, 4).map((t) => ({ id: t.menu_item_id, name: t.name, qty: t.qty }));
});

function setPeriod(p) {
  period.value = p;
  applyPeriod();
}

function applyPeriod() {
  if (chart && chartSets.value[period.value]) {
    chart.data.labels = chartSets.value[period.value].labels;
    chart.data.datasets[0].data = chartSets.value[period.value].data;
    chart.update();
  }
}

onMounted(() => {
  if (!canvas.value) return;
  const ctx = canvas.value.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(220, 238, 177, 0.6)');
  gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'Pendapatan (Rp)',
        data: [],
        borderColor: '#000000',
        borderWidth: 2.5,
        backgroundColor: gradient,
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#000000',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#000000',
          titleColor: '#ffffff',
          bodyColor: '#ffffff',
          titleFont: { family: 'JetBrains Mono', size: 11 },
          bodyFont: { family: 'Inter', size: 13 },
          padding: 10,
          cornerRadius: 6,
          displayColors: false,
          callbacks: { label: (c) => 'Rp ' + Number(c.parsed.y).toLocaleString('id-ID') }
        }
      },
      scales: {
        x: { grid: { color: '#f1f1f1', drawBorder: false }, ticks: { font: { family: 'JetBrains Mono', size: 11 }, color: '#71717a' } },
        y: { grid: { color: '#f1f1f1', drawBorder: false }, ticks: { font: { family: 'JetBrains Mono', size: 11 }, color: '#71717a', callback: (v) => (v / 1000) + 'k' } }
      }
    }
  });
  fetchChartData();
});

watch(() => session.apiOnline, fetchChartData);
</script>

<style scoped>
.metric-panel {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  padding: var(--spacing-xl);
  border-radius: var(--rounded-lg);
  box-shadow: none; /* Flatten per design */
}

.metric-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.metric-label {
  font-family: var(--font-mono);
  font-size: var(--text-eyebrow-size, 14px);
  text-transform: uppercase;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.metric-value {
  font-family: var(--font-sans);
  font-size: 32px;
  font-weight: 340;
  letter-spacing: -0.96px;
  line-height: 1.1;
  margin-top: var(--spacing-xs);
  margin-bottom: var(--spacing-xs);
}

@media (min-width: 1024px) {
  .metric-value {
    font-size: 48px; /* Using display-lg slightly scaled */
  }
}

.metric-unit {
  font-size: 18px;
  font-weight: 320;
}

.metric-footer {
  font-family: var(--font-sans);
  font-size: 14px;
  opacity: 0.8;
}

.card-title {
  font-family: var(--font-sans);
  font-size: var(--text-card-title-size, 24px);
  font-weight: 700;
  letter-spacing: 0;
  margin-bottom: 4px;
}

@media (max-width: 768px) {
  .metric-panel {
    padding: var(--spacing-lg);
  }
  .metric-value {
    font-size: 28px;
  }
  .metrics-grid {
    grid-template-columns: 1fr;
  }
}

.align-inline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--spacing-md);
}
</style>
