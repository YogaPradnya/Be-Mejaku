require('dotenv').config();
const bcrypt = require('bcryptjs');
const { connectDB } = require('./app');
const User = require('./models/User');
const MenuItem = require('./models/MenuItem');
const Table = require('./models/Table');
const Promo = require('./models/Promo');

// npm run seed — isi awal agar demo Swagger/presentasi langsung jalan.
(async () => {
  await connectDB();
  console.log('DB terhubung, seeding...');

  await User.deleteMany({});
  await User.create({
    name: 'Vendor Bali Dwi',
    email: 'vendor@mejaku.local',
    passwordHash: await bcrypt.hash('vendor123', 10),
    role: 'vendor',
  });

  await MenuItem.deleteMany({});
  await MenuItem.create({
    name: 'Nasi Campur Bali Agung',
    slug: 'nasi-campur-bali-agung',
    description: 'Nasi campur khas Bali dengan lauk lengkap.',
    base_price: 58000,
    stock: 50,
    image: null,
    portions: [{ name: 'Biasa', surcharge: 0 }, { name: 'Jumbo', surcharge: 15000 }],
    addons: [{ name: 'Ekstra Sambal Matah', price: 5000 }, { name: 'Sate Lilit Tuna', price: 8000 }],
    rating: 4.9,
    rating_count: 128,
  });

  await Table.deleteMany({});
  await Table.create([{ code: 'A12', floor: 'Lantai 2' }, { code: 'B05', floor: 'Lantai 1' }]);

  await Promo.deleteMany({});
  await Promo.create({ code: 'HEMAT5K', type: 'fixed', value: 5000, min_order: 30000, is_active: true });

  console.log('Seed selesai: vendor@mejaku.local / vendor123');
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
