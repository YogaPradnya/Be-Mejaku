/**
 * payment.js - Logika transaksi pembayaran (3 metode) dan pembuatan struk cetak
 */

let currentPayingOrder = null;
let currentPaymentMethod = 'qr';
let qrCodeInstance = null;

function initPaymentModule() {
  setupPaymentTabs();
  setupCashCalculator();
  setupPaymentActions();
}

// Setup Tab Pilihan Pembayaran (QR, Transfer, Tunai)
function setupPaymentTabs() {
  const tabButtons = document.querySelectorAll('.payment-tab-btn');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      currentPaymentMethod = btn.getAttribute('data-payment-method');

      // Tampilkan panel yang sesuai
      document.querySelectorAll('.payment-content-panel').forEach(panel => {
        panel.classList.remove('active');
      });

      const targetPanel = document.getElementById(`payment-panel-${currentPaymentMethod}`);
      if (targetPanel) {
        targetPanel.classList.add('active');
      }

      // Jika tab QR dipilih, pastikan QR di-generate ulang
      if (currentPaymentMethod === 'qr' && currentPayingOrder) {
        generateQrCode(currentPayingOrder);
      }
    });
  });
}

// Kalkulator Hitung Kembalian Uang Tunai
function setupCashCalculator() {
  const cashInput = document.getElementById('cash-received-input');
  const changeDisplay = document.getElementById('cash-change-display');

  if (!cashInput || !changeDisplay) return;

  cashInput.addEventListener('input', () => {
    if (!currentPayingOrder) return;

    const received = Number(cashInput.value) || 0;
    const total = currentPayingOrder.total || 0;
    const change = received - total;

    if (change >= 0) {
      changeDisplay.textContent = formatRupiah(change);
      changeDisplay.style.color = 'var(--color-semantic-success)';
    } else {
      changeDisplay.textContent = 'Kurang ' + formatRupiah(Math.abs(change));
      changeDisplay.style.color = 'var(--color-semantic-danger)';
    }
  });
}

// Buka Modal Pembayaran untuk Pesanan Tertentu
function openPaymentModal(orderId) {
  const order = getOrderById(orderId);
  if (!order) return;

  currentPayingOrder = order;

  // Isi data modal
  document.getElementById('payment-modal-order-id').textContent = order.id;
  document.getElementById('payment-modal-amount').textContent = formatRupiah(order.total);
  document.getElementById('payment-modal-customer').textContent = `Pelanggan: ${order.customer}`;

  // Reset tab ke QR
  currentPaymentMethod = 'qr';
  document.querySelectorAll('.payment-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.payment-tab-btn[data-payment-method="qr"]')?.classList.add('active');
  document.querySelectorAll('.payment-content-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('payment-panel-qr')?.classList.add('active');

  // Reset input tunai
  const cashInput = document.getElementById('cash-received-input');
  if (cashInput) cashInput.value = '';
  const changeDisplay = document.getElementById('cash-change-display');
  if (changeDisplay) changeDisplay.textContent = 'Rp 0';

  // Generate QR
  generateQrCode(order);

  // Tampilkan modal
  const modal = document.getElementById('modal-payment');
  if (modal) modal.classList.add('active');
}

// Generate QR Code via qrcode.js
function generateQrCode(order) {
  const container = document.getElementById('qrcode-container');
  if (!container) return;

  container.innerHTML = '';
  const qrText = `MERCHANTS-PAY:${order.id}:${order.total}:${Date.now()}`;

  if (typeof QRCode !== 'undefined') {
    qrCodeInstance = new QRCode(container, {
      text: qrText,
      width: 160,
      height: 160,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  } else {
    container.textContent = '[QR CODE GENERATOR MEMUAT]';
  }
}

// Konfirmasi Pembayaran & Generate Struk
function setupPaymentActions() {
  const confirmBtn = document.getElementById('btn-confirm-payment');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', () => {
      if (!currentPayingOrder) return;

      let methodName = 'QR Code';
      let paid = currentPayingOrder.total;
      let change = 0;

      if (currentPaymentMethod === 'transfer') {
        methodName = 'Transfer Bank (BCA)';
      } else if (currentPaymentMethod === 'cash') {
        methodName = 'Tunai / Offline';
        const cashInput = document.getElementById('cash-received-input');
        const received = Number(cashInput.value) || 0;

        if (received < currentPayingOrder.total) {
          alert('Nominal uang tunai yang diterima kurang dari total tagihan.');
          return;
        }

        paid = received;
        change = received - currentPayingOrder.total;
      }

      // Update status pesanan ke LUNAS
      const updatedOrder = updateOrderStatus(currentPayingOrder.id, 'LUNAS', methodName, paid, change);

      // Tutup modal pembayaran
      const payModal = document.getElementById('modal-payment');
      if (payModal) payModal.classList.remove('active');

      // Render ulang tampilan dashboard
      if (typeof renderAllDashboardData === 'function') {
        renderAllDashboardData();
      }

      // Tampilkan struk pembayaran
      showReceiptModal(updatedOrder);
    });
  }

  // Tombol Cetak Struk
  const printBtn = document.getElementById('btn-print-receipt');
  if (printBtn) {
    printBtn.addEventListener('click', () => {
      window.print();
    });
  }
}

// Render Data ke Modal Struk
function showReceiptModal(order) {
  if (!order) return;

  document.getElementById('receipt-invoice-num').textContent = order.id;
  document.getElementById('receipt-date').textContent = order.date || new Date().toLocaleString('id-ID');
  document.getElementById('receipt-customer').textContent = order.customer;
  document.getElementById('receipt-method').textContent = order.method || 'Tunai';

  // Render daftar item
  const tbody = document.getElementById('receipt-items-tbody');
  tbody.innerHTML = '';

  (order.items || []).forEach(item => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${item.name}</td>
      <td style="text-align: center;">${item.qty}x</td>
      <td style="text-align: right;">${formatRupiah(item.price * item.qty)}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('receipt-subtotal').textContent = formatRupiah(order.total);
  document.getElementById('receipt-grandtotal').textContent = formatRupiah(order.total);

  // Jika pembayaran tunai, tampilkan bayar tunai & kembalian
  const cashRow = document.getElementById('receipt-cash-row');
  const changeRow = document.getElementById('receipt-change-row');

  if (order.paidAmount && order.paidAmount > 0) {
    cashRow.style.display = 'flex';
    document.getElementById('receipt-cash-paid').textContent = formatRupiah(order.paidAmount);

    changeRow.style.display = 'flex';
    document.getElementById('receipt-change').textContent = formatRupiah(order.changeAmount || 0);
  } else {
    cashRow.style.display = 'none';
    changeRow.style.display = 'none';
  }

  // Buka modal struk
  const receiptModal = document.getElementById('modal-receipt');
  if (receiptModal) receiptModal.classList.add('active');
}
