/**
 * app.js - Dashboard MejaKu berbasis Vue 3 (global build, tanpa build-step).
 *
 * Semua kelas CSS dipakai ulang apa adanya dari css/*.css yang sudah
 * selaras dengan DESIGN.md — file ini hanya menggantikan dashboard.js,
 * payment.js, dan charts.js. Modul api.js (klien backend) dan data.js
 * (fallback localStorage) dipakai ulang tanpa perubahan.
 */

const { createApp } = Vue;

const PREP_LABEL = { received: 'Diterima', preparing: 'Disiapkan', ready: 'Siap', completed: 'Selesai' };
const PAY_LABEL = { pending: 'Belum Bayar', checking: 'Dicek', paid: 'Lunas', failed: 'Gagal', expired: 'Kedaluarsa' };

const CHART_SETS = {
  weekly: { labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'], data: [145000, 210000, 180000, 320000, 280000, 490000, 395000] },
  monthly: { labels: ['Mgg 1', 'Mgg 2', 'Mgg 3', 'Mgg 4'], data: [1250000, 1890000, 2100000, 2450000] }
};

function adaptApiOrder(o) {
  const table = o.table || {};
  const items = (o.items || []).map((it) => {
    const parts = [it.menu_item ? it.menu_item.name : 'Item'];
    if (it.portion) parts.push(`Porsi ${it.portion.name}`);
    if (it.spice_level) parts.push(it.spice_level.name);
    (it.addons || []).forEach((a) => parts.push(`+ ${a.name}`));
    return { name: parts.join(' • '), qty: it.quantity, price: it.unit_price };
  });
  return {
    source: 'api',
    id: o.order_number,
    date: o.created_at ? new Date(o.created_at).toLocaleString('id-ID') : '-',
    customer: o.notes || '-',
    tableCode: table.code || '-',
    tableFloor: table.floor || '',
    items,
    itemsSummary: items.map((i) => `${i.name} (${i.qty}x)`).join(', '),
    total: o.total,
    prepStatus: o.preparation_status,
    payStatus: o.payment_status,
    method: (o.payment && o.payment.method) ? o.payment.method : '-'
  };
}

function toReceiptOrder(row) {
  if (row.source === 'api') {
    return {
      id: row.id,
      date: row.date,
      customer: `Meja ${row.tableCode}`,
      method: row.method,
      items: row.items.map((i) => ({ name: i.name, qty: i.qty, price: i.price })),
      total: row.total,
      paidAmount: row.payStatus === 'paid' ? row.total : 0,
      changeAmount: 0
    };
  }
  return row.raw;
}

createApp({
  data() {
    return {
      page: 'overview',
      sidebarOpen: false,
      sidebarCollapsed: false,
      storeStatus: 'OPEN',

      loggedIn: false,
      apiOnline: false,
      loginForm: { email: '', password: '', base: '' },
      loginError: '',
      loginBusy: false,

      stats: null,
      apiOrders: [],
      orderFilter: 'ALL',
      orderSearch: '',
      localTick: 0,

      products: [],
      menuFilter: 'ALL',
      menuSearch: '',
      stockSearch: '',
      globalSearch: '',

      chartPeriod: 'weekly',
      chart: null,

      menuModal: false,
      menuForm: { name: '', category: 'Makanan', price: '', stock: '', description: '' },

      payModal: false,
      payOrder: null,
      payMethod: 'qr',
      cashReceived: '',

      receiptModal: false,
      receiptOrder: null,
      loginModal: false
    };
  },

  computed: {
    pageTitle() {
      return { overview: 'Ringkasan', orders: 'Pesanan', stock: 'Stok Barang', 'menu-management': 'Kelola Menu', payments: 'Kasir & Pembayaran' }[this.page] || 'Ringkasan';
    },
    PREP_LABEL() {
      return PREP_LABEL;
    },
    PAY_LABEL() {
      return PAY_LABEL;
    },

    metrics() {
      if (this.stats) {
        return {
          revenue: this.stats.revenue_today,
          totalOrders: this.stats.total_orders_today,
          soldItems: this.stats.sold_items_today,
          lowStockCount: this.getProducts().filter((p) => Number(p.stock) <= 5).length
        };
      }
      const m = calculateMetrics();
      return { revenue: m.revenue, totalOrders: m.totalOrders, soldItems: m.soldItems, lowStockCount: m.lowStockCount };
    },

    topProducts() {
      if (this.stats && this.stats.top_items && this.stats.top_items.length) {
        return this.stats.top_items.slice(0, 4).map((t) => ({ name: t.name, category: '-', qty: t.qty, rev: 0, fromApi: true }));
      }
      return this.getProducts().slice(0, 4).map((p, i) => ({ name: p.name, category: p.category, qty: 45 - i * 8, rev: (45 - i * 8) * p.price, fromApi: false }));
    },

    orderRows() {
      // eslint-disable-next-line no-unused-vars
      const t = this.localTick;
      if (this.apiOnline) return this.apiOrders.map(adaptApiOrder);
      return this.getOrders().map((o) => ({
        source: 'local',
        id: o.id,
        date: o.date,
        customer: o.customer,
        tableCode: '-',
        tableFloor: '',
        items: o.items || [],
        itemsSummary: (o.items || []).map((i) => `${i.name} (${i.qty}x)`).join(', '),
        total: o.total,
        status: o.status,
        method: o.method || '-',
        raw: o
      }));
    },

    filteredOrders() {
      const q = (this.orderSearch || this.globalSearch).trim().toLowerCase();
      return this.orderRows.filter((o) => {
        if (o.source === 'api') {
          if (this.orderFilter === 'MENUNGGU' && !(o.payStatus === 'pending' && o.prepStatus === 'received')) return false;
          if (this.orderFilter === 'DIPROSES' && !(o.prepStatus === 'preparing' || o.prepStatus === 'ready')) return false;
          if (this.orderFilter === 'LUNAS' && !(o.payStatus === 'paid' && o.prepStatus !== 'completed')) return false;
          if (this.orderFilter === 'SELESAI' && o.prepStatus !== 'completed') return false;
        } else if (this.orderFilter !== 'ALL' && o.status !== this.orderFilter) {
          return false;
        }
        if (q && !(o.id.toLowerCase().includes(q) || o.customer.toLowerCase().includes(q) || o.tableCode.toLowerCase().includes(q) || o.itemsSummary.toLowerCase().includes(q))) return false;
        return true;
      });
    },

    filteredProducts() {
      const q = (this.menuSearch || this.globalSearch).trim().toLowerCase();
      return this.getProducts().filter((p) => {
        if (this.menuFilter !== 'ALL' && p.category !== this.menuFilter) return false;
        if (q && !(p.name.toLowerCase().includes(q) || String(p.id).toLowerCase().includes(q))) return false;
        return true;
      });
    },

    filteredStock() {
      const q = (this.stockSearch || this.globalSearch).trim().toLowerCase();
      return this.getProducts().filter((p) => {
        if (q && !(p.name.toLowerCase().includes(q) || String(p.id).toLowerCase().includes(q) || String(p.category).toLowerCase().includes(q))) return false;
        return true;
      });
    },

    pendingOrders() {
      return this.orderRows.filter((o) => (o.source === 'api' ? o.payStatus === 'pending' : (o.status === 'MENUNGGU' || o.status === 'DIPROSES')));
    },

    paidOrders() {
      return this.orderRows.filter((o) => (o.source === 'api' ? o.payStatus === 'paid' : (o.status === 'LUNAS' || o.status === 'SELESAI')));
    },

    cashChange() {
      const received = Number(this.cashReceived) || 0;
      const total = this.payOrder ? this.payOrder.total : 0;
      return received - total;
    },

    apiBadgeClass() {
      if (this.loggedIn && this.apiOnline) return 'badge-success';
      if (this.loggedIn) return 'badge-warning';
      return 'badge-neutral';
    },

    apiBadgeText() {
      if (this.loggedIn && this.apiOnline) return 'API: Online';
      if (this.loggedIn) return 'API: Offline';
      return 'API: Lokal';
    }
  },

  methods: {
    rupiah(n) {
      return formatRupiah(n);
    },
    getOrders() {
      return getOrders();
    },
    getProducts() {
      return getProducts();
    },

    // ---- Navigasi & shell ----
    go(page) {
      this.page = page;
      window.location.hash = page;
      if (window.innerWidth <= 768) this.sidebarOpen = false;
    },
    toggleSidebar() {
      if (window.innerWidth <= 768) this.sidebarOpen = !this.sidebarOpen;
      else this.sidebarCollapsed = !this.sidebarCollapsed;
    },
    toggleStore() {
      this.storeStatus = this.storeStatus === 'OPEN' ? 'CLOSED' : 'OPEN';
      setStoreStatus(this.storeStatus);
    },

    // ---- Auth ----
    async checkSession() {
      if (!MejaKuAPI.isLoggedIn()) {
        this.loggedIn = false;
        this.apiOnline = false;
        return;
      }
      try {
        await MejaKuAPI.me();
        this.loggedIn = true;
        await this.refreshApi();
      } catch (e) {
        MejaKuAPI.setToken('');
        this.loggedIn = false;
        this.apiOnline = false;
      }
    },
    async doLogin() {
      this.loginError = '';
      this.loginBusy = true;
      try {
        if (this.loginForm.base.trim()) MejaKuAPI.setBaseUrl(this.loginForm.base.trim());
        const res = await MejaKuAPI.login(this.loginForm.email.trim(), this.loginForm.password);
        MejaKuAPI.setToken(res.data.token);
        this.loginForm.password = '';
        this.loginModal = false;
        this.loggedIn = true;
        await this.refreshApi();
      } catch (e) {
        this.loginError = e.message || 'Login gagal.';
      } finally {
        this.loginBusy = false;
      }
    },
    async doLogout() {
      if (!window.confirm('Keluar dari dashboard?')) return;
      try {
        await MejaKuAPI.logout();
      } catch (e) { /* token tetap dibuang lokal */ }
      MejaKuAPI.setToken('');
      this.loggedIn = false;
      this.apiOnline = false;
      this.apiOrders = [];
      this.stats = null;
    },
    async refreshApi() {
      try {
        const [ordersRes, statsRes] = await Promise.all([MejaKuAPI.listOrders(), MejaKuAPI.getStats()]);
        this.apiOrders = ordersRes.data || [];
        this.stats = statsRes.data || null;
        this.apiOnline = true;
      } catch (e) {
        if (e && e.code === 'UNAUTHENTICATED') {
          MejaKuAPI.setToken('');
          this.loggedIn = false;
        }
        this.apiOnline = false;
      }
    },

    // ---- Pesanan (API) ----
    nextStep(prep) {
      if (prep === 'received') return { to: 'preparing', label: 'Mulai Siapkan' };
      if (prep === 'preparing') return { to: 'ready', label: 'Siap Disajikan' };
      if (prep === 'ready') return { to: 'completed', label: 'Selesaikan' };
      return null;
    },
    prepBadge(prep) {
      return { completed: 'badge-success', ready: 'badge-info', preparing: 'badge-warning', received: 'badge-neutral' }[prep] || 'badge-neutral';
    },
    payBadge(pay) {
      return { paid: 'badge-success', pending: 'badge-warning', checking: 'badge-warning' }[pay] || 'badge-danger';
    },
    async advancePrep(order) {
      const step = this.nextStep(order.prepStatus);
      if (!step) return;
      try {
        await MejaKuAPI.updatePreparation(order.id, step.to);
        await this.refreshApi();
      } catch (e) {
        alert('Gagal memperbarui status dapur: ' + e.message);
      }
    },
    async markPaid(order) {
      if (!window.confirm(`Tandai pesanan ${order.id} sebagai LUNAS (kasir)?`)) return;
      try {
        await MejaKuAPI.updatePayment(order.id, 'paid');
        await this.refreshApi();
      } catch (e) {
        alert('Gagal memperbarui pembayaran: ' + e.message);
      }
    },
    async openReceipt(order) {
      try {
        if (order.source === 'api') {
          const res = await MejaKuAPI.getOrder(order.id);
          this.receiptOrder = toReceiptOrder(adaptApiOrder(res.data));
        } else {
          this.receiptOrder = toReceiptOrder(order);
        }
        this.receiptModal = true;
      } catch (e) {
        alert('Gagal memuat struk: ' + e.message);
      }
    },

    // ---- Kasir lokal (fallback) ----
    openPay(order) {
      this.payOrder = order.raw;
      this.payMethod = 'qr';
      this.cashReceived = '';
      this.payModal = true;
      this.$nextTick(() => this.drawQr());
    },
    drawQr() {
      const box = document.getElementById('qrcode-container');
      if (!box || !this.payOrder) return;
      box.innerHTML = '';
      const text = `MERCHANTS-PAY:${this.payOrder.id}:${this.payOrder.total}:${Date.now()}`;
      if (typeof QRCode !== 'undefined') {
        new QRCode(box, { text, width: 160, height: 160, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
      } else {
        box.textContent = '[QR CODE GENERATOR MEMUAT]';
      }
    },
    confirmPay() {
      if (!this.payOrder) return;
      let methodName = 'QR Code';
      let paid = this.payOrder.total;
      let change = 0;
      if (this.payMethod === 'transfer') {
        methodName = 'Transfer Bank (BCA)';
      } else if (this.payMethod === 'cash') {
        const received = Number(this.cashReceived) || 0;
        if (received < this.payOrder.total) {
          alert('Nominal uang tunai yang diterima kurang dari total tagihan.');
          return;
        }
        methodName = 'Tunai / Offline';
        paid = received;
        change = received - this.payOrder.total;
      }
      const updated = updateOrderStatus(this.payOrder.id, 'LUNAS', methodName, paid, change);
      this.payModal = false;
      this.localTick++;
      this.receiptOrder = updated;
      this.receiptModal = true;
    },
    printReceipt() {
      window.print();
    },

    // ---- Menu lokal ----
    submitMenu() {
      const f = this.menuForm;
      if (!f.name.trim() || !f.price || !f.stock) return;
      addProduct({ name: f.name.trim(), category: f.category, price: f.price, stock: f.stock, description: f.description.trim() });
      this.menuForm = { name: '', category: 'Makanan', price: '', stock: '', description: '' };
      this.menuModal = false;
    },
    deleteMenu(id, name) {
      if (window.confirm(`Apakah Anda yakin ingin menghapus menu "${name}" (${id}) dari daftar?`)) {
        deleteProduct(id);
        this.localTick++;
      }
    },

    // ---- Chart ----
    initChart() {
      const canvas = document.getElementById('salesChart');
      if (!canvas || typeof Chart === 'undefined') return;
      const ctx = canvas.getContext('2d');
      const gradient = ctx.createLinearGradient(0, 0, 0, 300);
      gradient.addColorStop(0, 'rgba(220, 238, 177, 0.6)');
      gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
      this.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: CHART_SETS.weekly.labels,
          datasets: [{
            label: 'Pendapatan (Rp)',
            data: CHART_SETS.weekly.data,
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
    },
    setChartPeriod(p) {
      this.chartPeriod = p;
      if (this.chart && CHART_SETS[p]) {
        this.chart.data.labels = CHART_SETS[p].labels;
        this.chart.data.datasets[0].data = CHART_SETS[p].data;
        this.chart.update();
      }
    }
  },

  mounted() {
    this.storeStatus = getStoreStatus();
    this.loginForm.base = MejaKuAPI.baseUrl();
    const hash = window.location.hash.replace('#', '');
    if (['overview', 'orders', 'stock', 'menu-management', 'payments'].includes(hash)) this.page = hash;
    window.addEventListener('hashchange', () => {
      const h = window.location.hash.replace('#', '');
      if (['overview', 'orders', 'stock', 'menu-management', 'payments'].includes(h)) this.page = h;
    });
    this.initChart();
    this.checkSession();
  }
}).mount('#app');
