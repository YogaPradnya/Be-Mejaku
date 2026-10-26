# Dashboard Toko - Sistem Penjualan & Kasir

Dashboard administrasi dan kasir modern untuk toko/kafe yang dibangun menggunakan HTML, CSS vanilla, dan JavaScript murni dengan acuan desain editorial Figma monokrom dan aksen blok warna pastel.

---

## Daftar Fitur

1. **Ringkasan Kinerja (Overview Cards)**:
   - Total Pendapatan bulan berjalan
   - Total Pesanan masuk
   - Produk Terjual hari ini
   - Indikator Stok Menipis (&le; 5 unit)

2. **Grafik Penjualan**:
   - Visualisasi grafik garis interaktif dengan Chart.js
   - Toggle periode: Penjualan Mingguan dan Bulanan

3. **Manajemen Pesanan (Order List)**:
   - Filter status: Semua, Menunggu, Diproses, Lunas, Selesai
   - Pencarian real-time berdasarkan nomor pesanan atau nama pelanggan
   - Tombol bayar untuk pesanan aktif dan tombol lihat struk untuk pesanan lunas

4. **Katalog & Manajemen Menu**:
   - Tambah menu baru melalui modal dialog (Nama, Kategori, Harga, Stok, Deskripsi)
   - Hapus menu dengan konfirmasi pencegahan ketidaksengajaan
   - Filter kategori (Makanan, Minuman, Camilan) dan pencarian nama menu

5. **Gudang & Stok Barang**:
   - Monitoring ketersediaan produk
   - Status otomatis: Stok Kritis (&le; 5 unit) dan Stok Aman

6. **Sistem Pembayaran (3 Pilihan Metode)**:
   - **QR Code**: Menghasilkan kode QR otomatis secara dinamis melalui pustaka `qrcode.js`.
   - **Transfer Bank**: Menampilkan data bank tujuan (BCA) dan nominal transfer.
   - **Tunai / Offline**: Dilengkapi kalkulator kembalian otomatis saat kasir menginput uang yang diterima.

7. **Cetak Struk Transaksi**:
   - Pratinjau struk transaksi toko
   - Format cetak printer thermal (80mm) menggunakan `window.print()` dan aturan `@media print` khusus

8. **Penyimpanan Data Lokal**:
   - Menggunakan `localStorage` browser sehingga data tetap tersimpan saat halaman ditutup atau dimuat ulang tanpa ketergantungan server eksternal.

---

## Struktur Berkas

```
WebLanjutan/
├── index.html              -- Halaman utama dashboard dan seluruh modal dialog
├── DESIGN.md               -- Panduan dan spesifikasi desain Figma
├── README.md               -- Dokumentasi proyek
├── css/
│   ├── variables.css       -- Definisi token desain (warna, tipografi, radius, jarak)
│   ├── base.css            -- Reset CSS dan utilitas tipografi global
│   ├── components.css      -- Komponen reusable (tombol, kartu, form, tabel, modal, badge)
│   ├── dashboard.css       -- Tata letak layout dashboard, sidebar, topbar, dan chart
│   └── print.css           -- Pengaturan layout cetak struk kasir 80mm
└── js/
    ├── data.js             -- Database lokal (localStorage), inisialisasi data, operasi CRUD
    ├── charts.js           -- Konfigurasi grafik Chart.js
    ├── payment.js          -- Logika proses 3 metode pembayaran dan pembuatan struk
    └── dashboard.js        -- Logika event listener, filter real-time, dan render antarmuka
```

---

## Cara Menjalankan

1. Buka berkas [index.html](file:///home/archara/Documents/kodingan/Tugas/WebLanjutan/index.html) langsung di browser Anda, atau gunakan live server lokal (seperti ekstensi Live Server di editor atau `npx serve .`).
2. Data awal (produk dan pesanan contoh) akan secara otomatis tersimpan di `localStorage` peramban Anda pada pembukaan pertama kali.
