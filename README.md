# MejaKu Backend — Express + MongoDB

Backend UTS Pemrograman Web Lanjutan. Node.js Express + Mongoose (MongoDB Atlas).

## Jalankan lokal

```bash
npm install
cp .env.example .env   # isi MONGODB_URI Atlas + JWT_SECRET
npm run seed           # data awal (vendor@mejaku.local / vendor123)
npm run dev            # http://localhost:8002
```

Dokumentasi API (Swagger UI): **http://localhost:8002/api/docs**

## Uji

```bash
npm test   # Jest + Supertest + mongodb-memory-server (12 test)
```

## Struktur

```
src/
  app.js          # buildApp() + mount /api/v1/* + /api/docs
  server.js       # konek Atlas lalu listen
  seed.js         # npm run seed
  config/db.js
  middleware/auth.js   # JWT Bearer area vendor
  models/         # User, MenuItem, Table, Order (split-billing), Promo
  routes/         # auth, menu, tables, orders, payments, promos
swagger.yaml      # OpenAPI 3.0 — 17 endpoint
tests/            # auth, menu (JWT guard), checkout (split-billing)
```

## Split-billing

`POST /api/v1/orders/checkout` menerima `payments[]` gabungan metode
(`cash`/`qris`/`transfer`). Jumlah `amount` **wajib pas** dengan total
(subtotal − diskon + service fee + pajak), kalau tidak → `422`.
Status order otomatis `paid` bila jumlah pembayaran lunas.
