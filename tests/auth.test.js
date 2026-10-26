require('./setup');
const request = require('supertest');

describe('POST /api/v1/auth/login', () => {
  test('kredensial valid -> 200 + token JWT', async () => {
    await request(global.testApp)
      .post('/api/v1/auth/register')
      .send({ name: 'Vendor Uji', email: 'vendor@uji.local', password: 'rahasia123' })
      .expect(201);

    const res = await request(global.testApp)
      .post('/api/v1/auth/login')
      .send({ email: 'vendor@uji.local', password: 'rahasia123' })
      .expect(200);

    expect(res.body.success).toBe(true);
    expect(res.body.data.token).toBeTruthy();
  });

  test('password salah -> 401', async () => {
    const res = await request(global.testApp)
      .post('/api/v1/auth/login')
      .send({ email: 'vendor@uji.local', password: 'salah' })
      .expect(401);

    expect(res.body.success).toBe(false);
  });

  test('email tak terdaftar -> 401', async () => {
    await request(global.testApp)
      .post('/api/v1/auth/login')
      .send({ email: 'tidak@ada.local', password: 'apapun' })
      .expect(401);
  });

  test('body kosong -> 422', async () => {
    await request(global.testApp).post('/api/v1/auth/login').send({}).expect(422);
  });
});
