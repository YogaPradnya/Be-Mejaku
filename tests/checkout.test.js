require('./setup');
const request = require('supertest');
const MenuItem = require('../src/models/MenuItem');
const Table = require('../src/models/Table');

let menuId;

beforeAll(async () => {
  const menu = await MenuItem.create({
    name: 'Nasi Campur',
    slug: 'nasi-campur-uji',
    base_price: 50000,
    stock: 20,
    portions: [{ name: 'Jumbo', surcharge: 15000 }],
    addons: [{ name: 'Sambal', price: 5000 }],
  });
  menuId = menu._id.toString();
  await Table.create({ code: 'A12', floor: 'Lantai 2' });
});

function checkout(payments, extra = {}) {
  return request(global.testApp).post('/api/v1/orders/checkout').send({
    table_code: 'A12',
    items: [{ menu_item_id: menuId, quantity: 1, portion: 'Jumbo', addons: ['Sambal'] }],
    payments,
    ...extra,
  });
}

describe('POST /api/v1/orders/checkout (split-billing)', () => {
  // subtotal = 50000+15000+5000 = 70000; fee 3000; tax = round((70000+3000)*0.10) = 7300; total = 80300
  test('split cash + qris pas total -> 201', async () => {
    const res = await checkout([
      { method: 'cash', amount: 40000 },
      { method: 'qris', amount: 40300 },
    ]).expect(201);

    expect(res.body.success).toBe(true);
    expect(res.body.data.total).toBe(80300);
    expect(res.body.data.payments).toHaveLength(2);
  });

  test('dibayar kurang -> 422', async () => {
    const res = await checkout([{ method: 'cash', amount: 10000 }]).expect(422);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toMatch(/tidak pas/);
  });

  test('dibayar lebih -> 422', async () => {
    await checkout([
      { method: 'cash', amount: 80000 },
      { method: 'qris', amount: 10000 },
    ]).expect(422);
  });

  test('tanpa payments -> 422', async () => {
    await request(global.testApp)
      .post('/api/v1/orders/checkout')
      .send({ table_code: 'A12', items: [{ menu_item_id: menuId, quantity: 1 }] })
      .expect(422);
  });

  test('status order mencerminkan sisa tagihan', async () => {
    const created = await checkout([
      { method: 'cash', amount: 50000 },
      { method: 'qris', amount: 30300 },
    ]).expect(201);

    const res = await request(global.testApp)
      .get(`/api/v1/orders/${created.body.data.order_number}/status`)
      .expect(200);

    expect(res.body.data.total).toBe(80300);
    expect(res.body.data.remaining).toBe(80300);
    expect(res.body.data.payment_status).toBe('pending');
  });
});
