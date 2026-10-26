const express = require('express');
const Table = require('../models/Table');

const router = express.Router();

router.get('/', async (req, res) => {
  const tables = await Table.find({ is_active: true }).sort({ code: 1 });
  return res.json({ success: true, data: tables });
});

router.get('/:code', async (req, res) => {
  const table = await Table.findOne({ code: String(req.params.code).toUpperCase(), is_active: true });
  if (!table) return res.status(404).json({ success: false, message: 'Meja tidak ditemukan atau nonaktif.' });
  return res.json({ success: true, data: table });
});

module.exports = router;
