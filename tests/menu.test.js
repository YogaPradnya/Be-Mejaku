require('./setup');
const request = require('supertest');
const MenuItem = require('../src/models/MenuItem');

let token = '';

beforeAll(async () => {
  await request(global.testApp)
    .post('/api/v1/auth/register')
    .send({ name: 'Vendor Menu', email: 'menu@uji.local', password: 'rahasia123' });
  const login = await request(global.testApp)
    .post('/api/v1/auth/login')
    .send({ email: 'menu@uji.local', password: 'rahasia123' });
  token = login.body.data.token;

  await MenuItem.create({
    name: 'Sate Lilit',
    slug: 'sate-lilit',
    base_price: 25000,
    stock: 10,
  });
});

describe('GET /api/v1/vendor/menu', () => {
  test('tanpa JWT Bearer token -> 401', async () => {
    const res = await request(global.testApp).get('/api/v1/vendor/menu').expect(401);
    expect(res.body.success).toBe(false);
  });

  test('token asal -> 401', async () => {
    await request(global.testApp)
      .get('/api/v1/vendor/menu')
      .set('Authorization', 'Bearer token-palsu')
      .expect(401);
  });

  test('dengan JWT valid -> 200 + daftar menu', async () => {
    const res = await request(global.testApp)
      .get('/api/v1/vendor/menu')
      .set('Authorization', `Bearer ${token}`)
      .expect(200);

    expect(res.body.success).toBe(true);
    expect(Array.isArray(res.body.data)).toBe(true);
    expect(res.body.data.length).toBeGreaterThanOrEqual(1);
  });
});
