# Rencana Pembaruan UI Dashboard MejaKu (Tanpa Integrasi)

Rencana ini difokuskan pada penyesuaian **antarmuka (UI)** dashboard yang sudah ada (`FE/index.html`) agar memiliki struktur visual dan elemen yang selaras dengan data backend MejaKu. Pembaruan ini murni di sisi HTML, CSS, dan JS Frontend (dummy data/UI State).

## Plan 1: Pembaruan UI Halaman Pesanan (Orders)
Menyesuaikan tampilan daftar dan detail pesanan agar menampilkan atribut MejaKu.

1. **Tambahkan Kolom Meja di Tabel Pesanan**
   - Tambahkan label meja pemesan (misal: `Meja A12 (Lantai 2)`).
2. **Perbarui UI Status Pesanan (Preparation Status)**
   - Buat badge status yang selaras dengan PRD MejaKu:
     - `Pesanan Diterima` (Received)
     - `Sedang Disiapkan` (Preparing)
     - `Siap Disajikan` (Ready)
     - `Selesai` (Completed)
3. **Detail Item Pesanan (Addons & Porsi)**
   - Di modal/panel detail pesanan, perbarui UI item barang agar bisa menampilkan sub-item:
     - Badge Porsi (contoh: *Porsi Jumbo*)
     - Badge Level Pedas (contoh: *Sangat Pedas*)
     - List Addons (contoh: *+ Ekstra Sambal Matah, + Sate Lilit*)
4. **Pisahkan UI Status Pembayaran**
   - Tambahkan indikator visual yang jelas membedakan Status Penyiapan (Dapur) dan Status Pembayaran (Pending / Paid).

## Plan 2: Pembaruan UI Halaman Kelola Menu (Menu Management)
Mengubah UI form tambah/edit menu agar mengakomodasi konfigurasi dinamis.

1. **Form Menu Dasar**
   - Pertahankan input nama, deskripsi, harga dasar, dan gambar.
2. **UI Builder untuk Porsi (Portions)**
   - Tambahkan seksi dinamis (Add/Remove form) untuk opsi porsi beserta *surcharge* (tambahan harga).
3. **UI Builder untuk Addons (Pelengkap)**
   - Tambahkan seksi dinamis untuk opsi pelengkap beserta harganya.
4. **UI Toggle Tingkat Pedas**
   - Tambahkan checkbox/toggle untuk mengaktifkan opsi "Level Pedas" pada menu tersebut.

## Plan 3: Pembaruan UI Kasir & Pembayaran (Payments)
Menyesuaikan alur kasir agar mendukung flow QRIS dinamis seperti di MejaKu.

1. **Simulasi QRIS Generate**
   - Saat checkout dari kasir, tampilkan *mockup* popup kode QRIS dan hitung mundur kedaluwarsa (misal 15 menit).
2. **Tombol "Simulasi Pembayaran"**
   - Tambahkan tombol UI "Simulasikan Lunas" (untuk mode demo/uji coba).
3. **Rincian Biaya (Struk)**
   - Sesuaikan rincian harga di UI: Subtotal, Service Fee (Rp 3.000), Pajak (10%), dan Total Akhir.

## Plan 4: Penambahan UI Kelola Meja & Promo (Halaman Baru)
Backend MejaKu memiliki fitur meja dan kode promo yang belum ada di dashboard lama.

1. **Buat Halaman/Tab "Kelola Meja"**
   - Tabel daftar meja: Kode (A12), Lantai, Area (Jakarta Culinary Hall), dan Status Aktif.
   - Tombol "Generate QR Code Meja" (dummy UI).
2. **Buat Halaman/Tab "Kelola Promo"**
   - Tabel kode promo: Kode (HEMAT5K), Tipe (Diskon % atau Nominal), Minimal Order, dan Masa Berlaku.

## Urutan Eksekusi
1. Konfirmasi Rencana di atas.
2. Saya akan mulai dari **Plan 1 (UI Pesanan)** dengan memperbarui HTML/CSS/JS di `FE/` untuk merender data contoh (dummy) berstruktur MejaKu.
3. Setelah Anda menyetujui hasil Plan 1, saya akan lanjut ke Plan 2, dst.
