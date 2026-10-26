require('dotenv').config();
const { buildApp, connectDB } = require('./app');

const PORT = process.env.PORT || 8000;

(async () => {
  await connectDB();
  console.log('MongoDB terhubung.');
  buildApp().listen(PORT, () => console.log(`MejaKu API jalan di http://localhost:${PORT} (docs: /api/docs)`));
})().catch((e) => {
  console.error('Gagal start:', e.message);
  process.exit(1);
});
