/**
 * dashboard.js - Logika navigasi halaman mandiri, buka-tutup sidebar, dan modul kasir
 */

const PAGE_TITLES = {
  'overview': 'Ringkasan',
  'orders': 'Pesanan',
  'stock': 'Stok Barang',
  'menu-management': 'Kelola Menu',
  'payments': 'Kasir & Pembayaran'
};

document.addEventListener('DOMContentLoaded', () => {
  // Inisialisasi komponen visual dan transaksi
  initSalesChart();
  initPaymentModule();
  setupSidebarToggle();
  setupStoreStatusToggle();
  setupPageNavigation();
  setupModalToggles();
  setupMenuForm();
  setupFiltersAndSearch();
  setupAuth();

  // Handle URL hash awal (misal #orders atau default #overview)
  handleInitialRoute();

  // Render seluruh data
  renderAllDashboardData();
});

// ==========================================================================
// 1. ROUTING & NAVIGASI HALAMAN MANDIRI (PAGE VIEWS)
// ==========================================================================

function switchPage(pageId) {
  if (!PAGE_TITLES[pageId]) pageId = 'overview';

  // Sembunyikan semua halaman
  document.querySelectorAll('.page-view').forEach(view => {
    view.classList.remove('active');
  });

  // Tampilkan hanya halaman yang dipilih
  const targetView = document.getElementById(`page-${pageId}`);
  if (targetView) {
    targetView.classList.add('active');
  }

  // Update nav link aktif di sidebar
  document.querySelectorAll('.sidebar-nav .nav-item').forEach(link => {
    link.classList.remove('active');
  });
  const activeLink = document.querySelector(`.sidebar-nav .nav-item[data-page="${pageId}"]`);
  if (activeLink) {
    activeLink.classList.add('active');
  }

  // Update teks breadcrumb header
  const titleEl = document.getElementById('current-page-title');
  if (titleEl) {
    titleEl.textContent = PAGE_TITLES[pageId];
  }

  // Update hash browser tanpa reload
  window.location.hash = pageId;

  // Tutup sidebar otomatis di mobile setelah memilih menu
  if (window.innerWidth <= 768) {
    closeSidebarMobile();
  }

  // Jika masuk ke halaman tertentu, refresh tabel terkait
  if (pageId === 'payments') {
    renderPaymentsPageTables();
  }
}

function handleInitialRoute() {
  const hash = window.location.hash.replace('#', '');
  if (hash && PAGE_TITLES[hash]) {
    switchPage(hash);
  } else {
    switchPage('overview');
  }

  window.addEventListener('hashchange', () => {
    const newHash = window.location.hash.replace('#', '');
    if (newHash && PAGE_TITLES[newHash]) {
      switchPage(newHash);
    }
  });
}

function setupPageNavigation() {
  document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const pageId = item.getAttribute('data-page');
      if (pageId) {
        switchPage(pageId);
      }
    });
  });
}

// ==========================================================================
// 2. LOGIKA BUKA-TUTUP SIDEBAR (COLLAPSIBLE & MOBILE DRAWER)
// ==========================================================================

function setupSidebarToggle() {
  const toggleBtn = document.getElementById('btn-toggle-sidebar');
  const closeBtn = document.getElementById('btn-close-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const sidebar = document.getElementById('app-sidebar');
  const mainWrapper = document.getElementById('main-wrapper');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        // Mode mobile: toggle drawer overlay
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('active');
      } else {
        // Mode desktop: toggle collapse / expand
        sidebar.classList.toggle('collapsed');
        mainWrapper.classList.toggle('expanded');
      }
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebarMobile);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeSidebarMobile);
  }

  // Handle resize window
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      sidebar.classList.remove('open');
      backdrop.classList.remove('active');
    }
  });
}

function closeSidebarMobile() {
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  if (sidebar) sidebar.classList.remove('open');
  if (backdrop) backdrop.classList.remove('active');
}

// ==========================================================================
// 3. LOGIKA TOMBOL BUKA / TUTUP TOKO
// ==========================================================================

function setupStoreStatusToggle() {
  const btn = document.getElementById('btn-toggle-store-status');
  if (!btn) return;

  const currentStatus = getStoreStatus();
  updateStoreStatusUI(currentStatus);

  btn.addEventListener('click', () => {
    const active = getStoreStatus();
    const nextStatus = active === 'OPEN' ? 'CLOSED' : 'OPEN';
    setStoreStatus(nextStatus);
    updateStoreStatusUI(nextStatus);
  });
}

function updateStoreStatusUI(status) {
  const btn = document.getElementById('btn-toggle-store-status');
  const textEl = document.getElementById('store-status-text');
  if (!btn || !textEl) return;

  if (status === 'CLOSED') {
    btn.classList.remove('open');
    btn.classList.add('closed');
    textEl.textContent = 'Toko Tutup';
    btn.title = 'Status: Toko Tutup (Klik untuk membuka toko)';
  } else {
    btn.classList.remove('closed');
    btn.classList.add('open');
    textEl.textContent = 'Toko Buka';
    btn.title = 'Status: Toko Buka (Klik untuk menutup toko)';
  }
}

// ==========================================================================
// 4. RENDER DATA UTAMA DASHBOARD
// ==========================================================================

function renderAllDashboardData() {
  renderMetrics();
  renderTopProducts();
  renderOrdersTable();
  renderMenuTable();
  renderStockTable();
  renderPaymentsPageTables();
}

// Render Metrik Ringkasan
function renderMetrics() {
  const metrics = calculateMetrics();

  const revEl = document.getElementById('metric-total-revenue');
  if (revEl) revEl.textContent = formatRupiah(metrics.revenue);

  const ordEl = document.getElementById('metric-total-orders');
  if (ordEl) ordEl.textContent = metrics.totalOrders;

  const soldEl = document.getElementById('metric-sold-items');
  if (soldEl) soldEl.textContent = `${metrics.soldItems} item`;

  const lowStockCountEl = document.getElementById('metric-low-stock-count');
  if (lowStockCountEl) lowStockCountEl.textContent = metrics.lowStockCount;

  const lowStockBadgeEl = document.getElementById('metric-low-stock-badge');
  if (lowStockBadgeEl) lowStockBadgeEl.textContent = `${metrics.lowStockCount} item`;
}

// Render Produk Terlaris
function renderTopProducts() {
  const container = document.getElementById('top-products-container');
  if (!container) return;

  const products = getProducts();
  const topList = products.slice(0, 4);

  container.innerHTML = '';
  topList.forEach((prod, index) => {
    const qtySold = 45 - (index * 8);
    const rev = qtySold * prod.price;

    const row = document.createElement('div');
    row.className = 'product-row-item';
    row.innerHTML = `
      <div class="product-meta">
        <span class="product-name">${prod.name}</span>
        <span class="product-category">${prod.category}</span>
      </div>
      <div class="product-sales">
        <span class="product-sold-qty">${qtySold} terjual</span>
        <span class="product-sold-rev">${formatRupiah(rev)}</span>
      </div>
    `;
    container.appendChild(row);
  });
}

// Render Tabel Pesanan (API backend bila login, fallback localStorage)
let currentOrderStatusFilter = 'ALL';
let currentOrderSearchQuery = '';
let apiOrderMode = false;

const PREP_LABEL = {
  received: 'Diterima',
  preparing: 'Disiapkan',
  ready: 'Siap',
  completed: 'Selesai'
};

const PAY_LABEL = {
  pending: 'Belum Bayar',
  checking: 'Dicek',
  paid: 'Lunas',
  failed: 'Gagal',
  expired: 'Kedaluarsa'
};

function adaptApiOrder(o) {
  const table = o.table || {};
  const items = (o.items || []).map(it => {
    const parts = [it.menu_item ? it.menu_item.name : 'Item'];
    if (it.portion) parts.push(`Porsi ${it.portion.name}`);
    if (it.spice_level) parts.push(it.spice_level.name);
    (it.addons || []).forEach(a => parts.push(`+ ${a.name}`));
    return {
      name: parts.join(' • '),
      qty: it.quantity,
      price: it.unit_price,
      lineTotal: it.line_total
    };
  });
  return {
    source: 'api',
    id: o.order_number,
    date: o.created_at ? new Date(o.created_at).toLocaleString('id-ID') : '-',
    customer: o.notes || '-',
    tableCode: table.code || '-',
    tableFloor: table.floor || '',
    items,
    itemsSummary: items.map(i => `${i.name} (${i.qty}x)`).join(', '),
    total: o.total,
    subtotal: o.subtotal,
    serviceFee: o.service_fee,
    tax: o.tax,
    discount: o.discount,
    prepStatus: o.preparation_status,
    payStatus: o.payment_status,
    method: (o.payment && o.payment.method) ? o.payment.method : '-'
  };
}

function apiRowMatchesTab(row) {
  switch (currentOrderStatusFilter) {
    case 'MENUNGGU': return row.payStatus === 'pending' && row.prepStatus === 'received';
    case 'DIPROSES': return row.prepStatus === 'preparing' || row.prepStatus === 'ready';
    case 'LUNAS': return row.payStatus === 'paid' && row.prepStatus !== 'completed';
    case 'SELESAI': return row.prepStatus === 'completed';
    default: return true;
  }
}

function prepBadgeClass(prep) {
  if (prep === 'completed') return 'badge-success';
  if (prep === 'ready') return 'badge-info';
  if (prep === 'preparing') return 'badge-warning';
  return 'badge-neutral';
}

function payBadgeClass(pay) {
  if (pay === 'paid') return 'badge-success';
  if (pay === 'pending' || pay === 'checking') return 'badge-warning';
  return 'badge-danger';
}

function nextPrepStep(prep) {
  if (prep === 'received') return { to: 'preparing', label: 'Mulai Siapkan' };
  if (prep === 'preparing') return { to: 'ready', label: 'Siap Disajikan' };
  if (prep === 'ready') return { to: 'completed', label: 'Selesaikan' };
  return null;
}

async function renderOrdersTable() {
  const tbody = document.getElementById('orders-table-body');
  if (!tbody) return;

  // Coba ambil dari API bila sudah login
  if (MejaKuAPI.isLoggedIn()) {
    try {
      const res = await MejaKuAPI.listOrders();
      const rows = (res.data || []).map(adaptApiOrder);
      apiOrderMode = true;
      renderApiOrderRows(tbody, rows);
      return;
    } catch (e) {
      if (e && e.code === 'UNAUTHENTICATED') {
        MejaKuAPI.setToken('');
        updateAuthUI(false);
      }
      apiOrderMode = false;
    }
  } else {
    apiOrderMode = false;
  }

  renderLocalOrderRows(tbody);
}

function renderApiOrderRows(tbody, rows) {
  let filtered = rows.filter(apiRowMatchesTab);

  if (currentOrderSearchQuery.trim() !== '') {
    const q = currentOrderSearchQuery.toLowerCase();
    filtered = filtered.filter(o =>
      o.id.toLowerCase().includes(q) ||
      o.tableCode.toLowerCase().includes(q) ||
      o.itemsSummary.toLowerCase().includes(q)
    );
  }

  tbody.innerHTML = '';

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" style="text-align: center; padding: 28px; color: #71717a;">
          Tidak ada data pesanan yang cocok. <span class="font-caption">(sumber: API backend)</span>
        </td>
      </tr>
    `;
    return;
  }

  filtered.forEach(order => {
    const tr = document.createElement('tr');
    const step = nextPrepStep(order.prepStatus);

    let actionButtons = '';
    if (step) {
      actionButtons += `
        <button class="btn btn-primary btn-sm" onclick="apiAdvancePrep('${order.id}', '${step.to}')">
          ${step.label}
        </button>
      `;
    }
    if (order.payStatus === 'pending') {
      actionButtons += `
        <button class="btn btn-secondary btn-sm" onclick="apiMarkPaid('${order.id}')">
          Tandai Lunas
        </button>
      `;
    }
    actionButtons += `
      <button class="btn btn-secondary btn-sm" onclick="apiShowReceipt('${order.id}')">
        Struk
      </button>
    `;

    tr.innerHTML = `
      <td style="width: 95px;"><strong>${order.id}</strong></td>
      <td class="font-mono" style="font-size: 12px; color: #555; width: 125px;">${order.date}</td>
      <td style="max-width: 120px;" title="${order.customer}"><span class="text-truncate">${order.customer}</span></td>
      <td style="width: 110px;"><span class="badge badge-neutral">${order.tableCode}${order.tableFloor ? ' • ' + order.tableFloor : ''}</span></td>
      <td style="max-width: 180px;" title="${order.itemsSummary}"><span class="text-truncate">${order.itemsSummary}</span></td>
      <td style="width: 120px;"><strong>${formatRupiah(order.total)}</strong></td>
      <td style="width: 130px;">
        <span class="badge ${prepBadgeClass(order.prepStatus)}" title="Status dapur">
          <span class="badge-dot"></span>
          ${PREP_LABEL[order.prepStatus] || order.prepStatus}
        </span>
        <span class="badge ${payBadgeClass(order.payStatus)}" title="Status pembayaran" style="margin-top: 4px;">
          <span class="badge-dot"></span>
          ${PAY_LABEL[order.payStatus] || order.payStatus}
        </span>
      </td>
      <td class="font-mono" style="font-size: 12px; width: 110px;">${order.method || '-'}</td>
      <td class="col-action">${actionButtons}</td>
    `;
    tbody.appendChild(tr);
  });
}

async function apiAdvancePrep(orderNumber, nextStatus) {
  try {
    await MejaKuAPI.updatePreparation(orderNumber, nextStatus);
    renderOrdersTable();
  } catch (e) {
    alert('Gagal memperbarui status dapur: ' + e.message);
  }
}

async function apiMarkPaid(orderNumber) {
  if (!window.confirm(`Tandai pesanan ${orderNumber} sebagai LUNAS (kasir)?`)) return;
  try {
    await MejaKuAPI.updatePayment(orderNumber, 'paid');
    renderOrdersTable();
  } catch (e) {
    alert('Gagal memperbarui pembayaran: ' + e.message);
  }
}

async function apiShowReceipt(orderNumber) {
  try {
    const res = await MejaKuAPI.getOrder(orderNumber);
    const o = adaptApiOrder(res.data);
    showReceiptModal({
      id: o.id,
      date: o.date,
      customer: `Meja ${o.tableCode}`,
      method: o.method,
      items: o.items.map(i => ({ name: i.name, qty: i.qty, price: i.price })),
      total: o.total,
      paidAmount: o.payStatus === 'paid' ? o.total : 0,
      changeAmount: 0
    });
  } catch (e) {
    alert('Gagal memuat struk: ' + e.message);
  }
}

function renderLocalOrderRows(tbody) {
  let orders = getOrders();

  if (currentOrderStatusFilter !== 'ALL') {
    orders = orders.filter(o => o.status === currentOrderStatusFilter);
  }

  if (currentOrderSearchQuery.trim() !== '') {
    const q = currentOrderSearchQuery.toLowerCase();
    orders = orders.filter(o => 
      o.id.toLowerCase().includes(q) || 
      o.customer.toLowerCase().includes(q)
    );
  }

  tbody.innerHTML = '';

  if (orders.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" style="text-align: center; padding: 28px; color: #71717a;">
          Tidak ada data pesanan yang cocok.
        </td>
      </tr>
    `;
    return;
  }

  orders.forEach(order => {
    const tr = document.createElement('tr');

    let badgeClass = 'badge-neutral';
    if (order.status === 'LUNAS' || order.status === 'SELESAI') badgeClass = 'badge-success';
    else if (order.status === 'DIPROSES') badgeClass = 'badge-info';
    else if (order.status === 'MENUNGGU') badgeClass = 'badge-warning';

    const itemsSummary = (order.items || []).map(i => `${i.name} (${i.qty}x)`).join(', ');

    let actionButtons = '';
    if (order.status === 'MENUNGGU' || order.status === 'DIPROSES') {
      actionButtons = `
        <button class="btn btn-primary btn-sm" onclick="openPaymentModal('${order.id}')">
          Bayar
        </button>
      `;
    } else {
      actionButtons = `
        <button class="btn btn-secondary btn-sm" onclick="showReceiptModal(getOrderById('${order.id}'))">
          Lihat Struk
        </button>
      `;
    }

    tr.innerHTML = `
      <td style="width: 95px;"><strong>${order.id}</strong></td>
      <td class="font-mono" style="font-size: 12px; color: #555; width: 125px;">${order.date}</td>
      <td style="max-width: 120px;" title="${order.customer}"><span class="text-truncate">${order.customer}</span></td>
      <td style="width: 110px;"><span class="badge badge-neutral">-</span></td>
      <td style="max-width: 180px;" title="${itemsSummary}"><span class="text-truncate">${itemsSummary}</span></td>
      <td style="width: 120px;"><strong>${formatRupiah(order.total)}</strong></td>
      <td style="width: 105px;">
        <span class="badge ${badgeClass}">
          <span class="badge-dot"></span>
          ${order.status}
        </span>
      </td>
      <td class="font-mono" style="font-size: 12px; width: 110px;">${order.method || '-'}</td>
      <td class="col-action">${actionButtons}</td>
    `;
    tbody.appendChild(tr);
  });
}

// Render Tabel Katalog & Manajemen Menu
let currentMenuCategoryFilter = 'ALL';
let currentMenuSearchQuery = '';

function renderMenuTable() {
  const tbody = document.getElementById('menu-table-body');
  if (!tbody) return;

  let products = getProducts();

  if (currentMenuCategoryFilter !== 'ALL') {
    products = products.filter(p => p.category === currentMenuCategoryFilter);
  }

  if (currentMenuSearchQuery.trim() !== '') {
    const q = currentMenuSearchQuery.toLowerCase();
    products = products.filter(p => 
      p.name.toLowerCase().includes(q) || 
      p.id.toLowerCase().includes(q)
    );
  }

  tbody.innerHTML = '';

  if (products.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align: center; padding: 28px; color: #71717a;">
          Tidak ada menu yang ditemukan.
        </td>
      </tr>
    `;
    return;
  }

  products.forEach(prod => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="font-mono" style="font-size: 12px; font-weight: 600; width: 85px;">${prod.id}</td>
      <td style="max-width: 150px;" title="${prod.name}"><span class="text-truncate"><strong>${prod.name}</strong></span></td>
      <td style="width: 110px;"><span class="badge badge-neutral">${prod.category}</span></td>
      <td style="width: 120px;"><strong>${formatRupiah(prod.price)}</strong></td>
      <td style="width: 95px;">
        <span class="${Number(prod.stock) <= 5 ? 'badge badge-danger' : 'font-mono'}">
          ${prod.stock} unit
        </span>
      </td>
      <td style="max-width: 180px;" title="${prod.description || '-'}"><span class="text-truncate" style="color: #71717a; font-size: 13px;">${prod.description || '-'}</span></td>
      <td class="col-action">
        <button class="btn btn-secondary btn-sm" style="color: var(--color-semantic-danger); border-color: rgba(220, 38, 38, 0.3);" onclick="handleDeleteMenu('${prod.id}', '${encodeURIComponent(prod.name)}')">
          Hapus
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// Render Tabel Stok Barang
let currentStockSearchQuery = '';

function renderStockTable() {
  const tbody = document.getElementById('stock-table-body');
  if (!tbody) return;

  let products = getProducts();

  if (currentStockSearchQuery.trim() !== '') {
    const q = currentStockSearchQuery.toLowerCase();
    products = products.filter(p => 
      p.name.toLowerCase().includes(q) || 
      p.id.toLowerCase().includes(q) ||
      p.category.toLowerCase().includes(q)
    );
  }

  tbody.innerHTML = '';

  if (products.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 28px; color: #71717a;">
          Tidak ada barang yang cocok.
        </td>
      </tr>
    `;
    return;
  }

  products.forEach(prod => {
    const isLow = Number(prod.stock) <= 5;
    const tr = document.createElement('tr');

    tr.innerHTML = `
      <td class="font-mono" style="font-size: 12px; width: 95px;">${prod.id}</td>
      <td style="max-width: 180px;" title="${prod.name}"><span class="text-truncate"><strong>${prod.name}</strong></span></td>
      <td style="width: 110px;">${prod.category}</td>
      <td style="width: 100px;"><strong>${prod.stock}</strong> unit</td>
      <td style="width: 120px;">
        <span class="badge ${isLow ? 'badge-danger' : 'badge-success'}">
          <span class="badge-dot"></span>
          ${isLow ? 'Stok Kritis' : 'Stok Aman'}
        </span>
      </td>
      <td class="font-mono" style="font-size: 12px; color: #71717a;">
        ${isLow ? 'Segera Restock' : 'Cukup'}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// Render Tabel Khusus Halaman Kasir (Pending & Riwayat Lunas)
function renderPaymentsPageTables() {
  const pendingTbody = document.getElementById('pending-payments-tbody');
  const paidTbody = document.getElementById('paid-payments-tbody');
  if (!pendingTbody || !paidTbody) return;

  const orders = getOrders();

  // 1. Pesanan Menunggu Pembayaran
  const pendingOrders = orders.filter(o => o.status === 'MENUNGGU' || o.status === 'DIPROSES');
  pendingTbody.innerHTML = '';

  if (pendingOrders.length === 0) {
    pendingTbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 24px; color: #71717a;">
          Semua pesanan saat ini telah lunas.
        </td>
      </tr>
    `;
  } else {
    pendingOrders.forEach(ord => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><strong>${ord.id}</strong></td>
        <td>${ord.customer}</td>
        <td><strong>${formatRupiah(ord.total)}</strong></td>
        <td>
          <span class="badge badge-warning">
            <span class="badge-dot"></span>
            ${ord.status}
          </span>
        </td>
        <td style="text-align: right;">
          <button class="btn btn-primary btn-sm" onclick="openPaymentModal('${ord.id}')">
            Proses Kasir
          </button>
        </td>
      `;
      pendingTbody.appendChild(tr);
    });
  }

  // 2. Riwayat Pembayaran Selesai
  const paidOrders = orders.filter(o => o.status === 'LUNAS' || o.status === 'SELESAI');
  paidTbody.innerHTML = '';

  if (paidOrders.length === 0) {
    paidTbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 24px; color: #71717a;">
          Belum ada riwayat transaksi lunas.
        </td>
      </tr>
    `;
  } else {
    paidOrders.forEach(ord => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><strong>${ord.id}</strong></td>
        <td class="font-mono" style="font-size: 12px; color: #555;">${ord.date}</td>
        <td>${ord.customer}</td>
        <td class="font-mono" style="font-size: 12px;">${ord.method || 'Kasir'}</td>
        <td><strong>${formatRupiah(ord.total)}</strong></td>
        <td style="text-align: right;">
          <button class="btn btn-secondary btn-sm" onclick="showReceiptModal(getOrderById('${ord.id}'))">
            Lihat Struk
          </button>
        </td>
      `;
      paidTbody.appendChild(tr);
    });
  }
}

// ==========================================================================
// 4. AKSI CRUD MENU & FILTER
// ==========================================================================

function handleDeleteMenu(productId, encodedName) {
  const name = decodeURIComponent(encodedName);
  const confirmed = window.confirm(`Apakah Anda yakin ingin menghapus menu "${name}" (${productId}) dari daftar?`);
  if (confirmed) {
    deleteProduct(productId);
    renderAllDashboardData();
  }
}

function setupMenuForm() {
  const form = document.getElementById('form-add-menu');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const name = document.getElementById('input-menu-name').value.trim();
    const category = document.getElementById('input-menu-category').value;
    const price = document.getElementById('input-menu-price').value;
    const stock = document.getElementById('input-menu-stock').value;
    const description = document.getElementById('input-menu-desc').value.trim();

    if (!name || !price || !stock) return;

    addProduct({
      name,
      category,
      price,
      stock,
      description
    });

    form.reset();
    const modal = document.getElementById('modal-add-menu');
    if (modal) modal.classList.remove('active');

    renderAllDashboardData();
  });
}

function setupFiltersAndSearch() {
  // Tabs status pesanan
  const orderTabs = document.querySelectorAll('#order-status-tabs .tab-btn');
  orderTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      orderTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentOrderStatusFilter = tab.getAttribute('data-filter-status');
      renderOrdersTable();
    });
  });

  // Input search pesanan
  const orderSearch = document.getElementById('order-search-input');
  if (orderSearch) {
    orderSearch.addEventListener('input', (e) => {
      currentOrderSearchQuery = e.target.value;
      renderOrdersTable();
    });
  }

  // Tabs kategori menu
  const menuTabs = document.querySelectorAll('#menu-category-tabs .tab-btn');
  menuTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      menuTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentMenuCategoryFilter = tab.getAttribute('data-menu-category');
      renderMenuTable();
    });
  });

  // Input search menu
  const menuSearch = document.getElementById('menu-search-input');
  if (menuSearch) {
    menuSearch.addEventListener('input', (e) => {
      currentMenuSearchQuery = e.target.value;
      renderMenuTable();
    });
  }

  // Input search stok barang
  const stockSearch = document.getElementById('stock-search-input');
  if (stockSearch) {
    stockSearch.addEventListener('input', (e) => {
      currentStockSearchQuery = e.target.value;
      renderStockTable();
    });
  }

  // Global search input
  const globalSearch = document.getElementById('global-search-input');
  if (globalSearch) {
    globalSearch.addEventListener('input', (e) => {
      const val = e.target.value;
      currentOrderSearchQuery = val;
      currentMenuSearchQuery = val;
      currentStockSearchQuery = val;
      renderOrdersTable();
      renderMenuTable();
      renderStockTable();
    });
  }
}

function setupModalToggles() {
  const btnOpenAdd = document.getElementById('btn-open-add-menu-modal');
  const addModal = document.getElementById('modal-add-menu');

  if (btnOpenAdd && addModal) {
    btnOpenAdd.addEventListener('click', () => addModal.classList.add('active'));
  }

  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modalId = btn.getAttribute('data-close-modal');
      const targetModal = document.getElementById(modalId);
      if (targetModal) targetModal.classList.remove('active');
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('active');
      }
    });
  });
}

// ==========================================================================
// 5. AUTH ADMIN (Backend API) + STATUS KONEKSI
// ==========================================================================

function updateAuthUI(loggedIn) {
  const btn = document.getElementById('btn-auth-action');
  const badge = document.getElementById('api-status-badge');
  const badgeText = document.getElementById('api-status-text');

  if (btn) btn.textContent = loggedIn ? 'Keluar' : 'Masuk';
  if (badge && badgeText) {
    badge.classList.remove('badge-success', 'badge-neutral', 'badge-warning');
    if (loggedIn && apiOrderMode) {
      badge.classList.add('badge-success');
      badgeText.textContent = 'API: Online';
    } else if (loggedIn) {
      badge.classList.add('badge-warning');
      badgeText.textContent = 'API: Offline';
    } else {
      badge.classList.add('badge-neutral');
      badgeText.textContent = 'API: Lokal';
    }
  }
}

function setupAuth() {
  const btn = document.getElementById('btn-auth-action');
  const form = document.getElementById('form-login');
  const baseInput = document.getElementById('input-api-base');
  const errMsg = document.getElementById('login-error-msg');

  if (baseInput) baseInput.value = MejaKuAPI.baseUrl();

  if (btn) {
    btn.addEventListener('click', async () => {
      if (MejaKuAPI.isLoggedIn()) {
        if (!window.confirm('Keluar dari dashboard?')) return;
        try {
          await MejaKuAPI.logout();
        } catch (e) { /* abaikan, token tetap dibuang lokal */ }
        MejaKuAPI.setToken('');
        updateAuthUI(false);
        renderOrdersTable();
      } else {
        const modal = document.getElementById('modal-login');
        if (modal) modal.classList.add('active');
      }
    });
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('input-login-email').value.trim();
      const password = document.getElementById('input-login-password').value;
      const base = baseInput ? baseInput.value.trim() : '';
      if (base) MejaKuAPI.setBaseUrl(base);

      if (errMsg) errMsg.style.display = 'none';
      const submitBtn = document.getElementById('btn-login-submit');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const res = await MejaKuAPI.login(email, password);
        MejaKuAPI.setToken(res.data.token);
        document.getElementById('modal-login')?.classList.remove('active');
        document.getElementById('input-login-password').value = '';
        updateAuthUI(true);
        renderOrdersTable();
      } catch (err) {
        if (errMsg) {
          errMsg.textContent = err.message || 'Login gagal.';
          errMsg.style.display = 'block';
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  // Validasi token tersimpan saat halaman dimuat
  if (MejaKuAPI.isLoggedIn()) {
    MejaKuAPI.me()
      .then(() => updateAuthUI(true))
      .catch(() => {
        MejaKuAPI.setToken('');
        updateAuthUI(false);
      });
  } else {
    updateAuthUI(false);
  }
}
