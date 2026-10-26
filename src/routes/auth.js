const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const User = require('../models/User');

const router = express.Router();
const signToken = (user) =>
  jwt.sign({ id: user._id.toString(), email: user.email, role: user.role }, process.env.JWT_SECRET || 'ganti-secret-ini', {
    expiresIn: process.env.JWT_EXPIRES_IN || '12h',
  });

// POST /api/v1/auth/register — buat akun vendor (untuk demo/seed UTS).
router.post('/register', async (req, res) => {
  try {
    const { name, email, password } = req.body || {};
    if (!name || !email || !password) {
      return res.status(422).json({ success: false, message: 'name, email, dan password wajib diisi.' });
    }
    const exists = await User.findOne({ email: String(email).toLowerCase() });
    if (exists) return res.status(409).json({ success: false, message: 'Email sudah terdaftar.' });
    const user = await User.create({
      name,
      email: String(email).toLowerCase(),
      passwordHash: await bcrypt.hash(password, 10),
      role: 'vendor',
    });
    return res.status(201).json({
      success: true,
      data: { id: user._id, name: user.name, email: user.email, token: signToken(user) },
    });
  } catch (e) {
    return res.status(500).json({ success: false, message: 'Gagal registrasi.' });
  }
});

// POST /api/v1/auth/login — kredensial valid vs invalid (diuji Jest).
router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body || {};
    if (!email || !password) {
      return res.status(422).json({ success: false, message: 'email dan password wajib diisi.' });
    }
    const user = await User.findOne({ email: String(email).toLowerCase() });
    if (!user || !(await bcrypt.compare(password, user.passwordHash))) {
      return res.status(401).json({ success: false, message: 'Email atau password salah.' });
    }
    return res.json({
      success: true,
      data: { id: user._id, name: user.name, email: user.email, role: user.role, token: signToken(user) },
    });
  } catch (e) {
    return res.status(500).json({ success: false, message: 'Gagal login.' });
  }
});

module.exports = router;
