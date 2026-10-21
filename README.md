# SISTEM INFORMASI PARKIR BERBASIS WEB (PHP NATIVE & MYSQL)

Aplikasi manajemen sistem parkir indoor profesional berbasis PHP Native, MariaDB/MySQL, PDO Prepared Statements, Bootstrap 5, dan QR Code untuk tugas akhir & ujian kompetensi keahlian Rekayasa Perangkat Lunak (RPL).

---

## 1. Persyaratan Sistem
- **Web Server:** Apache 2.4+ (XAMPP 8.1 / 8.2+)
- **Bahasa Pemrograman:** PHP 8.1 / 8.2+ (Ekstensi aktif: `pdo_mysql`, `mbstring`, `openssl`)
- **Basis Data:** MariaDB 10.4+ atau MySQL 8.0+
- **Peramban Web:** Google Chrome, Microsoft Edge, atau Mozilla Firefox
- **Library QR Code:** `chillerlan/php-qrcode` (telah tersedia di folder `vendor/` dan dibundle)
- **Library Scanner Kamera:** HTML5-QRCode (CDN)

---

## 2. Struktur 6 Tabel Utama Basis Data
Aplikasi ini secara ketat mempertahankan **6 tabel utama** sesuai skema yang telah disepakati:
1. `tb_user` &mdash; Akun pengguna, peran (`admin`, `petugas`, `owner`), dan status aktif.
2. `tb_kendaraan` &mdash; Master data kendaraan terdaftar (plat nomor, jenis, warna, pemilik).
3. `tb_tarif` &mdash; Tarif parkir per jam per jenis kendaraan (motor, mobil, lainnya).
4. `tb_area_parkir` &mdash; Master zona parkir, kapasitas maksimum, dan slot terisi.
5. `tb_transaksi` &mdash; Transaksi check-in, check-out, durasi, snapshot tarif, tiket QR, pembayaran tunai/non-tunai, dan status pembayaran.
6. `tb_log_aktivitas` &mdash; Catatan riwayat aktivitas pengguna untuk audit keamanan.

---

## 3. Langkah Instalasi di XAMPP Windows

1. **Salin Folder Proyek:**
   Letakkan folder proyek di dalam direktori `htdocs`:
   ```text
   C:\xampp\htdocs\parkir
   ```
2. **Jalankan Apache & MySQL:**
   Buka panel kontrol XAMPP (*XAMPP Control Panel*) dan klik **Start** pada modul **Apache** dan **MySQL**.
3. **Impor Basis Data:**
   - Buka browser dan akses `http://localhost/phpmyadmin`.
   - Buat basis data baru dengan nama `parkir` (collation: `utf8mb4_unicode_ci`).
   - Pilih database `parkir`, buka tab **Import (Impor)**.
   - Pilih berkas `database/parkir.sql`, lalu klik tombol **Go / Impor**.
   *(Skema sudah mencakup seluruh kolom pembayaran dan stored procedure terkini).*
4. **Konfigurasi Koneksi:**
   Periksa berkas `config/database.php`. Secara bawaan telah disesuaikan untuk XAMPP:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_NAME', 'parkir');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('BASE_URL', '/parkir');
   ```
5. **Akses Aplikasi:**
   Buka peramban dan akses:
   ```text
   http://localhost/parkir/
   ```

---

## 4. Akun Pengguna Demo

Tiga akun bawaan telah dibuat dengan hash password Bcrypt aman:

| Peran (Role) | Username | Password | Wewenang & Hak Akses |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | Kelola akun pengguna, tarif, area, kendaraan, log sistem, laporan, dan backup database manual. |
| **Petugas** | `petugas` | `petugas123` | Registrasi kendaraan baru, transaksi kendaraan masuk & keluar, scan QR tiket, perhitungan tarif & pembayaran tunai/non-tunai, cetak tiket & struk thermal. |
| **Owner** | `owner` | `owner123` | Pengawasan eksekutif (100% read-only): Dashboard analitik, direktori kendaraan, audit transaksi, laporan omset terverifikasi lunas, statistik & tren interaktif, monitoring kapasitas area, audit log aktivitas, dan ekspor multi-tipe CSV. |

---

## 5. Fitur Unggulan yang Diterapkan

- **Pendaftaran Otomatis Saat Masuk:** Kendaraan dengan plat nomor baru dapat otomatis didaftarkan saat kendaraan pertama kali masuk tanpa harus keluar dari form transaksi.
- **Normalisasi Plat Nomor Konsisten:** Standardisasi huruf kapital dan pembersihan spasi ganda (`B 1234 XYZ`) untuk mencegah plat duplikat.
- **Kalkulasi Biaya & Snapshot Tarif:** Durasi dihitung di sisi server dengan pembulatan ke atas per jam minimum 1 jam (`max(1, ceil(durasi_menit / 60))`). Tarif menggunakan snapshot tarif saat kendaraan masuk sehingga aman dari perubahan tarif di kemudian hari.
- **Alur Pembayaran Lengkap (Tunai & Non-Tunai):**
  - **Tunai:** Hitung uang diterima, validasi uang tidak kurang, dan kalkulasi uang kembalian secara otomatis.
  - **Non-Tunai:** Tampilkan referensi QRIS / EDC simulasi dengan konfirmasi verifikasi sebelum transaksi diselesaikan lunas.
- **Cetak Struk Thermal Fleksibel (58 mm & 80 mm):** Halaman tiket masuk dan struk pembayaran dilengkapi toggle ukuran kertas serta aturan `@media print` yang menyembunyikan navigasi dan tombol saat dicetak.
- **Pencegahan Concurrency & Race Condition:** Transaksi masuk dan keluar dilindungi row-level locking `SELECT ... FOR UPDATE` dan PDO transaction (`beginTransaction`, `commit`, `rollBack`).
- **Rekonsiliasi & Sinkronisasi Kapasitas:** Fitur tombol sinkronisasi kapasitas di modul Area Parkir untuk memastikan nilai `terisi` selalu cocok 100% dengan transaksi aktif (`status='masuk'`).
- **Keamanan Folder Sensitif:** Folder `backup/`, `database/`, dan `config/` dilindungi file `.htaccess` (`Require all denied`) guna mencegah unduhan langsung dari publik.
- **Modul Backup Database Mandiri:** Fitur backup berbasis web khusus Admin di `/admin/backup/index.php` untuk membuat cadangan SQL instan dan mengunduhnya secara terautentikasi.
- **Proteksi CSV Formula Injection:** Seluruh nilai sel pada ekspor CSV yang diawali karakter formula (`=`, `+`, `-`, `@`) disanitasi otomatis dengan kutip tunggal aman.
- **Panel Pengawasan Khusus Owner (Read-Only):**
  1. `Dashboard` (`owner/dashboard.php`) &mdash; Metrik lalu lintas hari ini, omset lunas, status okupansi area, tren 7 hari, dan audit transaksi.
  2. `Data Kendaraan` (`owner/kendaraan.php`) &mdash; Direktori kendaraan terdaftar, status parkir saat ini, dan riwayat kunjungan per kendaraan.
  3. `Riwayat Transaksi` (`owner/transaksi.php`) &mdash; Audit riwayat transaksi lengkap dengan filter multi-parameter dan modal detail.
  4. `Laporan Pendapatan` (`owner/laporan.php`) &mdash; Perhitungan omset lunas, preset periode, komparasi antar-periode, dan breakdown pembayaran.
  5. `Statistik & Tren` (`owner/statistik.php`) &mdash; Visualisasi analitik Chart.js: tren 14 hari, jam sibuk (peak hour), proporsi kendaraan, dan okupansi.
  6. `Monitoring Area` (`owner/area.php`) &mdash; Pemantauan kapasitas, slot terisi, slot sisa, dan tingkat okupansi real-time.
  7. `Log Aktivitas` (`owner/log.php`) &mdash; Jejak audit keamanan sistem seluruh pengguna.

---

## 6. Menjalankan Pengujian Otomatis

Aplikasi ini dilengkapi pengujian otomatis untuk memvalidasi **17 skenario wajib sistem** dan **16 pengujian spesifik Role Owner**. Untuk menjalankan pengujian melalui terminal / Command Prompt:

### A. Pengujian Inti Sistem (17 Skenario Wajib)
```bash
cd C:\xampp\htdocs\parkir
php tests/test_all_17_scenarios.php
```

### B. Pengujian Hak Akses & Fitur Role Owner (16 Pengujian Wajib)
```bash
php tests/test_owner_role.php
```

Output Pengujian Role Owner:
```text
============================================================
       PENGUJIAN VALIDASI IMPLEMENTASI ROLE OWNER           
============================================================

[PASS] Test 1: Owner dapat login dengan akun yang benar
[PASS] Test 2: Owner dapat membuka dashboard
[PASS] Test 3: Owner dapat melihat data kendaraan dan riwayat transaksi
[PASS] Test 4: Owner dapat melihat laporan dan grafik
[PASS] Test 5: Owner dapat memfilter data berdasarkan tanggal
[PASS] Test 6: Owner dapat mengekspor CSV terlindungi
[PASS] Test 7: Owner dapat melihat log aktivitas
[PASS] Test 8: Owner tidak dapat mengakses halaman Admin
[PASS] Test 9: Owner tidak dapat menambahkan atau mengedit kendaraan
[PASS] Test 10: Owner tidak dapat mengubah tarif atau kapasitas area
[PASS] Test 11: Owner tidak dapat memproses transaksi masuk atau keluar
[PASS] Test 12: Owner tidak dapat memanipulasi status pembayaran
[PASS] Test 13: Owner tidak dapat menghapus transaksi atau log
[PASS] Test 14: Owner tidak dapat melakukan backup atau restore
[PASS] Test 15: Owner tidak dapat melewati pembatasan dengan direct URL
[PASS] Test 16: Konsistensi data antara Dashboard, Laporan, dan Basis Data

============================================================
HASIL AKHIR: 16 DARI 16 PENGUJIAN ROLE OWNER 100% PASS!
============================================================
```

---

## 7. Dokumentasi Lengkap
Dokumentasi mendalam tersedia di folder `docs/`:
- [`docs/dokumentasi.md`](docs/dokumentasi.md) &mdash; Panduan lengkap laporan proyek, DCL, TCL, ACID, kamus data, dan rincian modul.
- [`docs/ERD.md`](docs/ERD.md) &mdash; Diagram relasi entitas basis data (Mermaid) 6 tabel utama.
- [`docs/flowchart.md`](docs/flowchart.md) &mdash; Diagram alir proses bisnis sistem parkir (Mermaid).
