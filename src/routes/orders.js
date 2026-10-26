const express = require('express');
const MenuItem = require('../models/MenuItem');
const Order = require('../models/Order');
const Promo = require('../models/Promo');
const Table = require('../models/Table');

const router = express.Router();

const rand = (n) => Array.from({ length: n }, () => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[Math.floor(Math.random() * 32)]).join('');

// POST /api/v1/orders/checkout — logika split-billing:
// payments[] wajib menjumlah pas dengan total (422 bila kurang/lebih).
router.post('/checkout', async (req, res) => {
  try {
    const { table_code, notes, promo_code, items, payments } = req.body || {};
    if (!table_code) return res.status(422).json({ success: false, message: 'table_code wajib diisi.' });
    if (!Array.isArray(items) || items.length === 0) {
      return res.status(422).json({ success: false, message: 'items minimal 1.' });
    }
    const table = await Table.findOne({ code: String(table_code).toUpperCase(), is_active: true });
    if (!table) return res.status(422).json({ success: false, message: 'Meja tidak ditemukan atau nonaktif.' });

    let subtotal = 0;
    const orderItems = [];
    for (const it of items) {
      const menu = await MenuItem.findById(it.menu_item_id).catch(() => null);
      if (!menu || !menu.is_available) {
        return res.status(422).json({ success: false, message: `Menu ${it.menu_item_id} tidak tersedia.` });
      }
      const qty = Math.max(1, parseInt(it.quantity, 10) || 1);
      let unit = menu.base_price;
      let portionName = null;
      if (it.portion) {
        const p = (menu.portions || []).find((x) => x.name === it.portion);
        if (!p) return res.status(422).json({ success: false, message: `Porsi "${it.portion}" tidak ada di menu ${menu.name}.` });
        unit += p.surcharge;
        portionName = p.name;
      }
      const addonNames = [];
      for (const an of it.addons || []) {
        const a = (menu.addons || []).find((x) => x.name === an);
        if (!a) return res.status(422).json({ success: false, message: `Addon "${an}" tidak ada di menu ${menu.name}.` });
        unit += a.price;
        addonNames.push(a.name);
      }
      if (menu.stock < qty) {
        return res.status(422).json({ success: false, message: `Stok ${menu.name} kurang (sisa ${menu.stock}).` });
      }
      menu.stock -= qty;
      await menu.save();
      subtotal += unit * qty;
      orderItems.push({
        menu_item_id: menu._id, name: menu.name, quantity: qty,
        portion: portionName, addons: addonNames, unit_price: unit, line_total: unit * qty,
      });
    }

    let discount = 0;
    let promoCode = null;
    if (promo_code) {
      const promo = await Promo.findOne({ code: String(promo_code).toUpperCase() });
      if (promo) {
        discount = promo.discountFor(subtotal);
        if (discount > 0) promoCode = promo.code;
      }
    }

    const serviceFee = parseInt(process.env.SERVICE_FEE || '3000', 10);
    const tax = Math.round((subtotal - discount + serviceFee) * parseFloat(process.env.TAX_RATE || '0.10'));
    const total = subtotal - discount + serviceFee + tax;

    if (!Array.isArray(payments) || payments.length === 0) {
      return res.status(422).json({ success: false, message: 'payments wajib diisi (split-billing: gabungan metode).' });
    }
    const allowed = ['cash', 'qris', 'transfer'];
    let paySum = 0;
    for (const p of payments) {
      if (!allowed.includes(p.method)) {
        return res.status(422).json({ success: false, message: `Metode "${p.method}" tidak didukung. Pakai: cash/qris/transfer.` });
      }
      if (!p.amount || p.amount < 1) {
        return res.status(422).json({ success: false, message: 'Nominal tiap pembayaran minimal Rp1.' });
      }
      paySum += p.amount;
    }
    if (paySum !== total) {
      return res.status(422).json({
        success: false,
        message: `Total split-billing tidak pas: dibayar Rp${paySum}, tagihan Rp${total}.`,
      });
    }

    const order = await Order.create({
      order_number: `MJK-${total}-${rand(4)}`,
      table_code: table.code,
      notes: notes || '',
      promo_code: promoCode,
      items: orderItems,
      subtotal, service_fee: serviceFee, tax, discount, total,
      payments: payments.map((p) => ({ method: p.method, amount: p.amount, status: 'pending' })),
      payment_status: 'pending',
    });

    return res.status(201).json({ success: true, data: order });
  } catch (e) {
    return res.status(500).json({ success: false, message: 'Gagal checkout.', error: e.message });
  }
});

router.get('/:orderNumber', async (req, res) => {
  const order = await Order.findOne({ order_number: req.params.orderNumber });
  if (!order) return res.status(404).json({ success: false, message: 'Pesanan tidak ditemukan.' });
  return res.json({ success: true, data: order });
});

router.get('/:orderNumber/status', async (req, res) => {
  const order = await Order.findOne({ order_number: req.params.orderNumber });
  if (!order) return res.status(404).json({ success: false, message: 'Pesanan tidak ditemukan.' });
  const paidSum = order.payments.filter((p) => p.status === 'paid').reduce((a, p) => a + p.amount, 0);
  return res.json({
    success: true,
    data: {
      order_number: order.order_number,
      payment_status: order.payment_status,
      preparation_status: order.preparation_status,
      total: order.total,
      paid: paidSum,
      remaining: Math.max(0, order.total - paidSum),
    },
  });
});

module.exports = router;
