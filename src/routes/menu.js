const express = require('express');
const { requireAuth } = require('../middleware/auth');
const MenuItem = require('../models/MenuItem');

const router = express.Router();
const slugify = (s) =>
  String(s || 'menu').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'menu';

// Semua endpoint vendor wajib JWT Bearer (diuji: tanpa token -> 401).
router.use(requireAuth);

router.get('/', async (req, res) => {
  const items = await MenuItem.find({ is_available: true }).sort({ name: 1 });
  return res.json({ success: true, data: items });
});

router.get('/:id', async (req, res) => {
  const item = await MenuItem.findById(req.params.id).catch(() => null);
  if (!item) return res.status(404).json({ success: false, message: 'Menu tidak ditemukan.' });
  return res.json({ success: true, data: item });
});

router.post('/', async (req, res) => {
  try {
    const { name, description, base_price, stock, image, portions, addons } = req.body || {};
    if (!name || base_price === undefined) {
      return res.status(422).json({ success: false, message: 'name dan base_price wajib diisi.' });
    }
    const base = slugify(name);
    let slug = base;
    let i = 2;
    while (await MenuItem.findOne({ slug })) slug = `${base}-${i++}`;
    const item = await MenuItem.create({
      name, slug, description: description || '', base_price, stock: stock || 0,
      image: image || null, portions: portions || [], addons: addons || [],
    });
    return res.status(201).json({ success: true, data: item });
  } catch (e) {
    return res.status(500).json({ success: false, message: 'Gagal menambah menu.' });
  }
});

router.put('/:id', async (req, res) => {
  const item = await MenuItem.findByIdAndUpdate(req.params.id, req.body || {}, { new: true }).catch(() => null);
  if (!item) return res.status(404).json({ success: false, message: 'Menu tidak ditemukan.' });
  return res.json({ success: true, data: item });
});

router.delete('/:id', async (req, res) => {
  // Soft delete agar histori order aman.
  const item = await MenuItem.findByIdAndUpdate(req.params.id, { is_available: false }, { new: true }).catch(() => null);
  if (!item) return res.status(404).json({ success: false, message: 'Menu tidak ditemukan.' });
  return res.json({ success: true, message: 'Menu dinonaktifkan.' });
});

module.exports = router;
