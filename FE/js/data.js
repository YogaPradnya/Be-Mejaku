/**
 * data.js - Manajemen data lokal toko dengan localStorage
 */

const STORAGE_KEYS = {
  PRODUCTS: 'merchants_products',
  ORDERS: 'merchants_orders'
};

const INITIAL_PRODUCTS = [
  {
    id: 'PRD-001',
    name: 'Kopi Susu Gula Aren',
    category: 'Minuman',
    price: 18000,
    stock: 42,
    description: 'Espresso blend pilihan dengan susu segar dan sirup gula aren murni.'
  },
  {
    id: 'PRD-002',
    name: 'Espresso Double Shot',
    category: 'Minuman',
    price: 15000,
    stock: 4,
    description: 'Ekstraksi 60ml biji kopi arabika single origin.'
  },
  {
    id: 'PRD-003',
    name: 'Matcha Latte Panas',
    category: 'Minuman',
    price: 24000,
    stock: 19,
    description: 'Bubuk matcha ceremonial grade dari Uji, Jepang.'
  },
  {
    id: 'PRD-004',
    name: 'Croissant Butter Artisan',
    category: 'Makanan',
    price: 25000,
    stock: 12,
    description: 'Pastry renyah berlapis dengan mentega Prancis autentik.'
  },
  {
    id: 'PRD-005',
    name: 'Roti Bakar Coklat Keju',
    category: 'Makanan',
    price: 20000,
    stock: 3,
    description: 'Roti gandum tebal panggang dengan lelehan coklat dan cheddar parut.'
  },
  {
    id: 'PRD-006',
    name: 'Kentang Goreng Truffle',
    category: 'Camilan',
    price: 26000,
    stock: 15,
    description: 'Kentang potong crinkle dengan taburan garam laut dan minyak truffle.'
  },
  {
    id: 'PRD-007',
    name: 'Singkong Keju Crispy',
    category: 'Camilan',
    price: 16000,
    stock: 22,
    description: 'Singkong pulen goreng gurih dengan topping taburan keju salju.'
  }
];

const INITIAL_ORDERS = [
  {
    id: 'ORD-1001',
    customer: 'Budi Santoso',
    date: '2026-10-06 09:15',
    items: [
      { id: 'PRD-001', name: 'Kopi Susu Gula Aren', qty: 2, price: 18000 },
      { id: 'PRD-004', name: 'Croissant Butter Artisan', qty: 1, price: 25000 }
    ],
    total: 61000,
    status: 'LUNAS',
    method: 'QR Code',
    paidAmount: 61000,
    changeAmount: 0
  },
  {
    id: 'ORD-1002',
    customer: 'Siti Rahma',
    date: '2026-10-06 10:30',
    items: [
      { id: 'PRD-003', name: 'Matcha Latte Panas', qty: 1, price: 24000 },
      { id: 'PRD-006', name: 'Kentang Goreng Truffle', qty: 1, price: 26000 }
    ],
    total: 50000,
    status: 'DIPROSES',
    method: 'Transfer Bank',
    paidAmount: 50000,
    changeAmount: 0
  },
  {
    id: 'ORD-1003',
    customer: 'Hendra Wijaya',
    date: '2026-10-06 11:05',
    items: [
      { id: 'PRD-005', name: 'Roti Bakar Coklat Keju', qty: 2, price: 20000 }
    ],
    total: 40000,
    status: 'MENUNGGU',
    method: 'Belum Dibayar',
    paidAmount: 0,
    changeAmount: 0
  },
  {
    id: 'ORD-1004',
    customer: 'Dian Permata',
    date: '2026-10-06 11:40',
    items: [
      { id: 'PRD-001', name: 'Kopi Susu Gula Aren', qty: 1, price: 18000 },
      { id: 'PRD-007', name: 'Singkong Keju Crispy', qty: 1, price: 16000 }
    ],
    total: 34000,
    status: 'SELESAI',
    method: 'Tunai / Offline',
    paidAmount: 50000,
    changeAmount: 16000
  },
  {
    id: 'ORD-1005',
    customer: 'Farhan Maulana',
    date: '2026-10-06 12:10',
    items: [
      { id: 'PRD-002', name: 'Espresso Double Shot', qty: 2, price: 15000 },
      { id: 'PRD-006', name: 'Kentang Goreng Truffle', qty: 1, price: 26000 }
    ],
    total: 56000,
    status: 'MENUNGGU',
    method: 'Belum Dibayar',
    paidAmount: 0,
    changeAmount: 0
  }
];

// Inisialisasi awal ke localStorage jika belum tersedia
function initStorage() {
  if (!localStorage.getItem(STORAGE_KEYS.PRODUCTS)) {
    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(INITIAL_PRODUCTS));
  }
  if (!localStorage.getItem(STORAGE_KEYS.ORDERS)) {
    localStorage.setItem(STORAGE_KEYS.ORDERS, JSON.stringify(INITIAL_ORDERS));
  }
}

// Operasi Produk
function getProducts() {
  initStorage();
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEYS.PRODUCTS)) || [];
  } catch (e) {
    return INITIAL_PRODUCTS;
  }
}

function saveProducts(products) {
  localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(products));
}

function addProduct(product) {
  const products = getProducts();
  const nextNum = products.length + 1;
  const newId = 'PRD-' + String(nextNum).padStart(3, '0');
  
  const newProduct = {
    id: newId,
    name: product.name,
    category: product.category,
    price: Number(product.price),
    stock: Number(product.stock),
    description: product.description || '-'
  };

  products.push(newProduct);
  saveProducts(products);
  return newProduct;
}

function deleteProduct(productId) {
  let products = getProducts();
  products = products.filter(p => p.id !== productId);
  saveProducts(products);
  return products;
}

// Operasi Pesanan
function getOrders() {
  initStorage();
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEYS.ORDERS)) || [];
  } catch (e) {
    return INITIAL_ORDERS;
  }
}

function saveOrders(orders) {
  localStorage.setItem(STORAGE_KEYS.ORDERS, JSON.stringify(orders));
}

function getOrderById(orderId) {
  const orders = getOrders();
  return orders.find(o => o.id === orderId) || null;
}

function updateOrderStatus(orderId, status, paymentMethod, paidAmount = 0, changeAmount = 0) {
  const orders = getOrders();
  const orderIndex = orders.findIndex(o => o.id === orderId);
  if (orderIndex !== -1) {
    orders[orderIndex].status = status;
    if (paymentMethod) orders[orderIndex].method = paymentMethod;
    if (paidAmount) orders[orderIndex].paidAmount = Number(paidAmount);
    if (changeAmount !== undefined) orders[orderIndex].changeAmount = Number(changeAmount);
    saveOrders(orders);
    return orders[orderIndex];
  }
  return null;
}

// Format Rupiah
function formatRupiah(amount) {
  return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
}

// Hitung Statistik Dashboard
function calculateMetrics() {
  const orders = getOrders();
  const products = getProducts();

  // Total pendapatan dari pesanan LUNAS dan SELESAI
  const revenue = orders
    .filter(o => o.status === 'LUNAS' || o.status === 'SELESAI')
    .reduce((acc, curr) => acc + (curr.total || 0), 0);

  // Total pesanan
  const totalOrders = orders.length;

  // Total produk terjual
  let soldItems = 0;
  orders.forEach(order => {
    if (order.status === 'LUNAS' || order.status === 'SELESAI') {
      order.items.forEach(item => {
        soldItems += Number(item.qty || 0);
      });
    }
  });

  // Stok menipis (<= 5)
  const lowStockProducts = products.filter(p => Number(p.stock) <= 5);

  return {
    revenue,
    totalOrders,
    soldItems,
    lowStockCount: lowStockProducts.length,
    lowStockList: lowStockProducts
  };
}

// Operasi Status Toko
function getStoreStatus() {
  return localStorage.getItem('merchants_store_status') || 'OPEN';
}

function setStoreStatus(status) {
  localStorage.setItem('merchants_store_status', status);
  return status;
}

// Inisialisasi saat script dimuat
initStorage();

