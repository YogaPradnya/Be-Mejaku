# Dashboard Vue (MejaKu)

Proyek Vue 3 + Vite + vue-router hasil migrasi dari dashboard `.html` vanilla.
**Seluruh CSS dipakai ulang dari `FE/css/*` tanpa perubahan** sehingga tetap
selaras dengan token `FE/DESIGN.md` (warna blok pastel, pill, tipografi, radius, spacing).

## Struktur per fitur

```
dashboard-vue/
├── index.html              # root #app + CDN QRCode.js
├── vite.config.js          # base './', proxy /api → localhost:8000 (dev)
├── src/
│   ├── main.js             # createApp + router + import CSS DESIGN.md
│   ├── App.vue             # shell: backdrop, Sidebar, Topbar, RouterView, modal global
│   ├── router/index.js     # hash-history: overview, orders, stock, menu-management, payments
│   ├── api/client.js       # klien backend (login, orders, stats, payments)
│   ├── store/
│   │   ├── session.js      # sesi login + cache apiOrders/stats (reactive, dibagi semua view)
│   │   └── local.js        # fallback localStorage (port data.js lama)
│   ├── utils/
│   │   ├── format.js       # rupiah()
│   │   └── orders.js       # adaptApiOrder, toReceiptOrder, label dapur/bayar
│   ├── components/
│   │   ├── Sidebar.vue     # navigasi + status user
│   │   ├── Topbar.vue      # search global, toggle toko (API bila login), badge API, Masuk/Keluar
│   │   ├── LoginModal.vue  # login admin + API base URL
│   │   ├── PaymentModal.vue# kasir lokal 3 metode + QR + kalkulator tunai
│   │   ├── QrisModal.vue   # QRIS dinamis: generate QR, countdown, cek status, simulasi
│   │   ├── ReceiptModal.vue# struk + cetak thermal
│   │   ├── OrderDetailModal.vue # rincian item, biaya, timeline 4 tahap
│   │   └── MenuModal.vue   # tambah/ubah menu: merchant, porsi, addons, toggle pedas
│   └── views/
│       ├── Overview.vue    # metrik, Chart.js tren, produk terlaris
│       ├── Orders.vue      # tabel pesanan API/lokal, aksi dapur, QRIS, detail, struk
│       ├── Stock.vue       # inventaris & stok
│       ├── MenuManagement.vue # mode API (merchant, porsi, pedas) / fallback lokal
│       ├── Tables.vue      # kelola meja QR (butuh login)
│       ├── Promos.vue      # kelola promo (butuh login)
│       └── Payments.vue    # kasir pending + riwayat + rekap /admin/payments
```

## Cara menjalankan

```sh
cd FE/dashboard-vue
npm install
npm run dev      # http://localhost:5173 (API di-proxy ke :8000)
npm run build    # output dist/
npm run preview  # pratinjau hasil build
```

Backend Laravel harus jalan (`php artisan serve`) lalu login di dashboard
dengan akun admin (`ADMIN_EMAIL` di `.env` backend).

Dashboard ini **full-API tanpa data fallback**: tanpa login, semua halaman
menampilkan ajakan masuk (tidak ada data demo localStorage). Satu-satunya
pengecualian adalah grafik tren (dataset statis placeholder) karena belum ada
endpoint tren penjualan di backend.

Semua error dan aksi penting tampil sebagai **popup** (`store/alert.js` +
`components/AlertPopup.vue`) di kanan bawah: gagal login, gagal CRUD,
gagal QRIS, dsb. Tidak ada `window.alert` tersisa.
