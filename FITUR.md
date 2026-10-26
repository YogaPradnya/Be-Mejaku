# Daftar Fitur MejaKu + Status Verifikasi

> Diverifikasi: 7 Okt 2026. Metode: PHPUnit **31/31 lolos** (`backend/`, Midtrans dikosongkan di `phpunit.xml`)
> + probe live ke API lokal (`127.0.0.1:8000`) + build frontend Vue sukses.
> Cocok untuk dibawa ke web utama — tiap fitur mencantumkan lokasi kodenya.

Legenda: ✅ berfungsi (terverifikasi) · ⚠️ berfungsi sebagian / perlu cek manual · ❌ belum ada / rusak

## 1. API Publik (dipakai customer, tanpa login)

| Fitur | Endpoint | Status | Keterangan |
|---|---|---|---|
| Lihat daftar menu | `GET /api/menu` | ✅ | 200, hanya menu `is_available` |
| Detail menu (porsi, addon, level pedas) | `GET /api/menu/{slug}` | ✅ | 200 |
| Info merchant | `GET /api/merchants/{id}` | ✅ | 200 |
| Daftar level pedas | `GET /api/spice-levels` | ✅ | 200 |
| Validasi meja dari QR | `GET /api/tables/{code}` | ✅ | 200; 404 bila kode salah / meja nonaktif |
| Buat pesanan (hitung harga server-side, potong stok, kunci anti-oversell) | `POST /api/orders` | ✅ | 201; 422 bila stok kurang / menu nonaktif |
| Lihat pesanan + **auto-sinkron Midtrans** | `GET /api/orders/{nomor}` | ✅ | 200; dipakai polling halaman customer |
| Update status dapur (demo) | `PATCH /api/orders/{nomor}/status` | ✅ | route terdaftar (masih demo) |
| Buat pembayaran + Snap token | `POST /api/payments/{nomor}` | ✅ | 201; teruji live, token sandbox keluar |
| Cek status + sinkron Midtrans | `GET /api/payments/{nomor}/status` | ✅ | 200 |
| Simulasi bayar/gagal/expired (demo) | `POST /api/payments/{nomor}/simulate` | ✅ | dicover test |
| Landing pasca-Snap | `GET /api/payments/finish` | ✅ | 200 |
| Webhook Midtrans (verifikasi signature SHA512) | `POST /api/midtrans/notification` | ✅ | dicover 4 test; **butuh URL publik** (tidak jalan di localhost) |
| Validasi promo | `POST /api/promo/validate` | ✅ | 422 untuk body kosong / kode nonaktif (perilaku validasi benar). Catatan: 2 kode seed (`HEMAT5K`, `MEJAKU10`) status **nonaktif** |
| Chatbot AI (Groq) | `POST /api/chat`, `POST /api/chat/stream` | ✅ | 200 + balasan live |

## 2. API Admin (`/api/admin/*`, auth Sanctum)

| Fitur | Endpoint | Status | Keterangan |
|---|---|---|---|
| Login / logout / profil | `POST /admin/auth/login`, `logout`, `GET /me` | ✅ | 200, token Bearer |
| Statistik dashboard | `GET /admin/stats` | ✅ | 200 |
| Daftar + detail pesanan (**auto-sinkron Midtrans**) | `GET /admin/orders`, `GET /admin/orders/{nomor}` | ✅ | 200 |
| Update status dapur | `PATCH /admin/orders/{nomor}/preparation` | ✅ | dicover test |
| Tandai lunas manual (kasir tunai) | `PATCH /admin/orders/{nomor}/payment` | ✅ | sinkron ke record payment terakhir |
| Rekap pembayaran + ringkasan lunas | `GET /admin/payments` | ✅ | 200 |
| CRUD meja (+ toggle aktif) | `/admin/tables` | ✅ | 200 |
| CRUD promo | `/admin/promos` | ✅ | 200 |
| CRUD menu (+ toggle, porsi, addon, pedas) | `/admin/menu` | ✅ | 200 |
| CRUD merchant | `/admin/merchants` | ✅ | 200 |
| Status toko buka/tutup | `GET+PUT /admin/store/status` | ✅ | 200 |

## 3. Dashboard Vue (`FE/dashboard-vue`, `npm run dev` → `localhost:5173`)

| Halaman | Fitur | Status | Keterangan |
|---|---|---|---|
| Ringkasan `#/overview` | Metrik, tren penjualan, produk terlaris | ✅ | build OK |
| Pesanan `#/orders` | Filter tab, cari, advance dapur, QRIS, action-sheet (⋯), detail, struk, **+ Tambah Pesanan manual** | ✅ | build OK; tambah manual teruji live |
| Stok `#/stock` | Inventaris dari katalog menu | ✅ | kolom merchant dibuang (1 merchant) |
| Kelola Menu `#/menu-management` | Tabel + modal (porsi/addon/pedas), ubah, on/off, hapus | ✅ | filter merchant dibuang; merchant otomatis |
| Kelola Meja `#/tables` | CRUD, on/off, **QR per meja** (salin link, cetak, domain publik bisa diganti) | ✅ | build OK |
| Kelola Promo `#/promos` | CRUD promo | ✅ | build OK |
| Kasir `#/payments` | Rekap lunas, pending, riwayat + struk, rekap backend, **Bayar via Midtrans (Snap popup)** | ✅ | Snap teruji token live |
| Customer `#/pesan/:kodeMeja` | Validasi meja, menu + keranjang, bayar QRIS / VA (Midtrans) / kasir manual, timeline status live (polling 5 dtk) | ✅ | alur penuh teruji live |
| Global | Login modal, toggle toko, fullscreen konten (tanpa batas 1380px), struk cetak | ✅ | |

## 4. Lintas fitur (cocok dibawa ke web utama)

| Fitur | Lokasi | Status | Keterangan |
|---|---|---|---|
| Midtrans Snap (QRIS, VA, e-wallet, kartu satu pintu) | `backend/app/Services/MidtransService.php`, `config/midtrans.php` | ✅ | token live OK; aktif bila `MIDTRANS_SERVER_KEY` terisi, fallback demo bila kosong |
| Midtrans charge langsung (QR string dirender di modal, nomor VA tampil langsung) | `MidtransService::chargeDirect`, `channel`+`bank` di `POST /api/payments` | ✅ | QRIS + VA BCA teruji live |
| Stok otomatis berkurang + tolak bila kurang (anti-oversell `lockForUpdate`) | `backend/app/Services/OrderService.php` | ✅ | 3 test baru; live 10→9 OK |
| Sinkron status Midtrans tanpa webhook (polling-safe) | `PaymentService::syncOrderNow` / `syncStalePending` | ✅ | order macet `MJK-94600-LX` terbukti sembuh jadi `paid` |
| Kode per meja unik untuk QR (`A12`, `B05`, `C08`) | `tables.code` + `TableController@show` | ✅ | QR → `/#/pesan/{kode}` |
| Single merchant (tanpa filter merchant) | `MenuManagement.vue`, `MenuModal.vue`, `Stock.vue` | ✅ | |
| Idempotency order ganda | `IdempotencyMiddleware` + `idempotency_key` | ✅ | dicover test |
| Rate limit (order 5/mnt, payment 10/mnt, chatbot 10/mnt) | `AppServiceProvider` | ✅ | terdaftar; belum diuji tembus limit |
| Struk cetak | `ReceiptModal.vue`, `print.css` | ⚠️ | kode ada; belum cetak fisik |

## 5. Catatan / belum diverifikasi penuh

- **Webhook Midtrans otomatis**: butuh URL publik (`ngrok`) didaftarkan di dashboard Midtrans; selama itu pakai polling/tombol Cek (sudah cukup).
- **Kunci Midtrans**: yang terpasang format production + `MIDTRANS_IS_PRODUCTION=false` — tetap bisa bikin token sandbox; kalau pembayaran test macet, set `true`.
- **Promo**: kode seed nonaktif — aktifkan via Kelola Promo bila mau dipakai (`promo/validate` + diskon order sudah jalan).
- **Stok tidak kembali** bila order dibatalkan (tidak ada alur batal) — perlu endpoint restore bila fitur batal ditambah.
- **Halaman customer** memakai `base_price` (tanpa pilih porsi/addon/pedas) — pengembangan lanjutan bila perlu.
