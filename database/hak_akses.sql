-- ========================================================
-- SISTEM INFORMASI PARKIR - MANAJEMEN HAK AKSES DATABASE (DCL)
-- File: database/hak_akses.sql
-- Penjelasan dan Perintah GRANT, REVOKE, & Pengamanan Akun DB
-- ========================================================

-- PENTING UNTUK PEMBELAJARAN KELAS XII RPL:
-- Terdapat 2 lapisan keamanan hak akses yang BERBEDA:
-- 1. Hak Akses Aplikasi (Application Role):
--    Tersimpan di tabel `tb_user` (admin, petugas, owner).
--    Mengontrol halaman dan fitur yang dapat diakses pengguna di browser.
-- 2. Hak Akses Basis Data (Database Privileges / DCL):
--    Dikelola langsung oleh MySQL/MariaDB menggunakan perintah GRANT & REVOKE.
--    Memastikan script PHP hanya memiliki hak minimum (Principle of Least Privilege).

USE `parkir`;

-- --------------------------------------------------------
-- 1. Pembuatan Pengguna Database Khusus Aplikasi
-- Jangan menggunakan akun 'root' tanpa password pada lingkungan produksi!
-- --------------------------------------------------------

-- Buat user untuk aplikasi web (hanya operasi DML dan Eksekusi Stored Procedure)
CREATE USER IF NOT EXISTS 'parkir_app'@'localhost' IDENTIFIED BY 'Parkir@App2026!';

-- Buat user untuk auditor/owner read-only (hanya operasi SELECT)
CREATE USER IF NOT EXISTS 'parkir_read'@'localhost' IDENTIFIED BY 'Parkir@Read2026!';

-- Buat user untuk administrator database (DBA)
CREATE USER IF NOT EXISTS 'parkir_dba'@'localhost' IDENTIFIED BY 'Parkir@DBA2026!';

-- --------------------------------------------------------
-- 2. Pemberian Hak Akses (GRANT)
-- --------------------------------------------------------

-- A. Hak akses untuk aplikasi operasional (parkir_app):
-- Memberikan izin CRUD data dan menjalankan Stored Procedure,
-- tanpa izin DROP TABLE atau ALTER TABLE demi keamanan dari SQL Injection perusak skema.
GRANT SELECT, INSERT, UPDATE, DELETE ON `parkir`.* TO 'parkir_app'@'localhost';
GRANT EXECUTE ON PROCEDURE `parkir`.`sp_parkir_keluar` TO 'parkir_app'@'localhost';

-- B. Hak akses untuk pelaporan read-only (parkir_read):
-- Hanya diizinkan membaca data (SELECT) untuk laporan audit.
GRANT SELECT ON `parkir`.* TO 'parkir_read'@'localhost';

-- C. Hak akses untuk DBA (parkir_dba):
-- Memiliki kendali penuh atas skema database parkir.
GRANT ALL PRIVILEGES ON `parkir`.* TO 'parkir_dba'@'localhost';

-- --------------------------------------------------------
-- 3. Pencabutan Hak Akses (REVOKE) - Contoh Penggunaan
-- --------------------------------------------------------

-- Contoh: Jika user parkir_app tidak diizinkan lagi menghapus data (DELETE)
-- REVOKE DELETE ON `parkir`.* FROM 'parkir_app'@'localhost';

-- Contoh: Mencabut hak eksekusi procedure dari pengguna tertentu
-- REVOKE EXECUTE ON PROCEDURE `parkir`.`sp_parkir_keluar` FROM 'parkir_app'@'localhost';

-- --------------------------------------------------------
-- 4. Terapkan Perubahan Hak Akses
-- --------------------------------------------------------
FLUSH PRIVILEGES;

-- --------------------------------------------------------
-- 5. Pengecekan Hak Akses yang Telah Diberikan
-- --------------------------------------------------------
SHOW GRANTS FOR 'parkir_app'@'localhost';
SHOW GRANTS FOR 'parkir_read'@'localhost';
