const express = require('express');
const Order = require('../models/Order');

const router = express.Router({ mergeParams: true });

// POST /api/v1/orders/:orderNumber/payments — tambah pembayaran susulan
// (mis. bayar sisa setelah split-billing awal) atau tandai lunas.
router.post('/', async (req, res) => {
  const order = await Order.findOne({ order_number: req.params.orderNumber });
  if (!order) return res.status(404).json({ success: false, message: 'Pesanan tidak ditemukan.' });
  const { method, amount } = req.body || {};
  if (!['cash', 'qris', 'transfer'].includes(method)) {
    return res.status(422).json({ success: false, message: 'Metode harus cash/qris/transfer.' });
  }
  if (!amount || amount < 1) {
    return res.status(422).json({ success: false, message: 'Nominal minimal Rp1.' });
  }
  // Pembayaran yang dicatat = uang sudah diterima.
  order.payments.push({ method, amount, status: 'paid', paid_at: new Date() });
  order.refreshPaymentStatus();
  await order.save();
  return res.status(201).json({ success: true, data: order });
});

// GET /api/v1/orders/:orderNumber/payments/status — ringkasan bayar.
router.get('/status', async (req, res) => {
  const order = await Order.findOne({ order_number: req.params.orderNumber });
  if (!order) return res.status(404).json({ success: false, message: 'Pesanan tidak ditemukan.' });
  const paidSum = order.payments.filter((p) => p.status === 'paid').reduce((a, p) => a + p.amount, 0);
  return res.json({
    success: true,
    data: {
      order_number: order.order_number,
      payment_status: order.payment_status,
      total: order.total,
      paid: paidSum,
      remaining: Math.max(0, order.total - paidSum),
      parts: order.payments.map((p) => ({ method: p.method, amount: p.amount, status: p.status })),
    },
  });
});

module.exports = router;
