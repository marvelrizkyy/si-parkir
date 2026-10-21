-- ========================================================
-- SISTEM INFORMASI PARKIR - MIGRASI METODE & STATUS PEMBAYARAN
-- File: database/migration_pembayaran.sql
-- Menambahkan kolom pembayaran pada tb_transaksi (tetap 6 tabel utama)
-- ========================================================

USE `parkir`;

-- Tambahkan kolom pembayaran pada tb_transaksi
ALTER TABLE `tb_transaksi`
ADD COLUMN `metode_pembayaran` ENUM('tunai', 'non_tunai') NOT NULL DEFAULT 'tunai' AFTER `status`,
ADD COLUMN `jumlah_bayar` DECIMAL(10,0) NOT NULL DEFAULT 0 AFTER `metode_pembayaran`,
ADD COLUMN `kembalian` DECIMAL(10,0) NOT NULL DEFAULT 0 AFTER `jumlah_bayar`,
ADD COLUMN `status_pembayaran` ENUM('belum_bayar', 'lunas') NOT NULL DEFAULT 'belum_bayar' AFTER `kembalian`,
ADD COLUMN `waktu_pembayaran` DATETIME NULL AFTER `status_pembayaran`,
ADD INDEX `idx_status_bayar` (`status_pembayaran`),
ADD INDEX `idx_metode_bayar` (`metode_pembayaran`);

-- Perbarui transaksi lama yang statusnya sudah 'keluar' agar status_pembayaran menjadi 'lunas'
UPDATE `tb_transaksi`
SET `status_pembayaran` = 'lunas',
    `jumlah_bayar` = COALESCE(`biaya_total`, 0),
    `kembalian` = 0,
    `waktu_pembayaran` = `waktu_keluar`
WHERE `status` = 'keluar' AND `biaya_total` IS NOT NULL;
