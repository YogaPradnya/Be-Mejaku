const express = require('express');
const Promo = require('../models/Promo');

const router = express.Router();

router.get('/', async (req, res) => {
  const promos = await Promo.find({ is_active: true }).sort({ code: 1 });
  return res.json({ success: true, data: promos });
});

router.post('/validate', async (req, res) => {
  const { code, subtotal } = req.body || {};
  if (!code) return res.status(422).json({ success: false, message: 'code wajib diisi.' });
  const promo = await Promo.findOne({ code: String(code).toUpperCase() });
  if (!promo || !promo.is_active) {
    return res.json({ success: true, data: { valid: false, message: 'Promo tidak berlaku.' } });
  }
  const discount = promo.discountFor(Number(subtotal) || 0);
  if (discount <= 0) {
    return res.json({ success: true, data: { valid: false, message: 'Belum memenuhi syarat promo.' } });
  }
  return res.json({ success: true, data: { valid: true, code: promo.code, discount } });
});

module.exports = router;
