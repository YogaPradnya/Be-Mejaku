const jwt = require('jsonwebtoken');

// Wajib: Authorization: Bearer <token> untuk area vendor.
function requireAuth(req, res, next) {
  const header = req.headers.authorization || '';
  const [scheme, token] = header.split(' ');
  if (scheme !== 'Bearer' || !token) {
    return res.status(401).json({ success: false, message: 'Token JWT Bearer wajib disertakan.' });
  }
  try {
    req.user = jwt.verify(token, process.env.JWT_SECRET || 'ganti-secret-ini');
    return next();
  } catch (e) {
    return res.status(401).json({ success: false, message: 'Token tidak valid atau kedaluwarsa.' });
  }
}

module.exports = { requireAuth };
