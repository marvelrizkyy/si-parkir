-- ========================================================
-- SISTEM INFORMASI PARKIR BERBASIS WEB
-- Proyek Uji Kompetensi Keahlian / Basis Data SMK Kelas XII RPL
-- Database: parkir
-- Engine: InnoDB, Charset: utf8mb4
-- ========================================================

CREATE DATABASE IF NOT EXISTS `parkir` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `parkir`;

-- Matikan foreign key checks sementara untuk reset tabel bersih
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tb_log_aktivitas`;
DROP TABLE IF EXISTS `tb_transaksi`;
DROP TABLE IF EXISTS `tb_area_parkir`;
DROP TABLE IF EXISTS `tb_tarif`;
DROP TABLE IF EXISTS `tb_kendaraan`;
DROP TABLE IF EXISTS `tb_user`;
DROP PROCEDURE IF EXISTS `sp_parkir_keluar`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. Tabel: tb_user
-- --------------------------------------------------------
CREATE TABLE `tb_user` (
  `id_user` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','petugas','owner') NOT NULL,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  INDEX `idx_username` (`username`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabel: tb_kendaraan
-- --------------------------------------------------------
CREATE TABLE `tb_kendaraan` (
  `id_kendaraan` INT AUTO_INCREMENT PRIMARY KEY,
  `plat_nomor` VARCHAR(15) NOT NULL UNIQUE,
  `jenis_kendaraan` VARCHAR(20) NOT NULL,
  `warna` VARCHAR(20) NULL,
  `pemilik` VARCHAR(100) NULL,
  `id_user` INT NOT NULL,
  INDEX `idx_plat` (`plat_nomor`),
  INDEX `idx_jenis` (`jenis_kendaraan`),
  CONSTRAINT `fk_kendaraan_user` FOREIGN KEY (`id_user`) 
    REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabel: tb_tarif
-- --------------------------------------------------------
CREATE TABLE `tb_tarif` (
  `id_tarif` INT AUTO_INCREMENT PRIMARY KEY,
  `jenis_kendaraan` ENUM('motor','mobil','lainnya') NOT NULL UNIQUE,
  `tarif_per_jam` DECIMAL(10,0) NOT NULL,
  INDEX `idx_tarif_jenis` (`jenis_kendaraan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabel: tb_area_parkir
-- --------------------------------------------------------
CREATE TABLE `tb_area_parkir` (
  `id_area` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_area` VARCHAR(50) NOT NULL,
  `kapasitas` INT NOT NULL,
  `terisi` INT NOT NULL DEFAULT 0,
  INDEX `idx_area_nama` (`nama_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabel: tb_transaksi
-- --------------------------------------------------------
CREATE TABLE `tb_transaksi` (
  `id_parkir` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_tiket` VARCHAR(64) NOT NULL UNIQUE,
  `id_kendaraan` INT NOT NULL,
  `waktu_masuk` DATETIME NOT NULL,
  `waktu_keluar` DATETIME NULL,
  `id_tarif` INT NOT NULL,
  `tarif_per_jam_saat_masuk` DECIMAL(10,0) NOT NULL,
  `durasi_jam` INT NULL,
  `biaya_total` DECIMAL(10,0) NULL,
  `status` ENUM('masuk','keluar') NOT NULL DEFAULT 'masuk',
  `metode_pembayaran` ENUM('tunai','non_tunai') NOT NULL DEFAULT 'tunai',
  `jumlah_bayar` DECIMAL(10,0) NOT NULL DEFAULT 0,
  `kembalian` DECIMAL(10,0) NOT NULL DEFAULT 0,
  `status_pembayaran` ENUM('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
  `waktu_pembayaran` DATETIME NULL,
  `id_user` INT NOT NULL,
  `id_area` INT NOT NULL,
  INDEX `idx_trx_status` (`status`),
  INDEX `idx_status_bayar` (`status_pembayaran`),
  INDEX `idx_metode_bayar` (`metode_pembayaran`),
  INDEX `idx_trx_masuk` (`waktu_masuk`),
  INDEX `idx_trx_keluar` (`waktu_keluar`),
  INDEX `idx_trx_kode` (`kode_tiket`),
  CONSTRAINT `fk_trx_kendaraan` FOREIGN KEY (`id_kendaraan`) 
    REFERENCES `tb_kendaraan` (`id_kendaraan`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_trx_tarif` FOREIGN KEY (`id_tarif`) 
    REFERENCES `tb_tarif` (`id_tarif`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_trx_user` FOREIGN KEY (`id_user`) 
    REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_trx_area` FOREIGN KEY (`id_area`) 
    REFERENCES `tb_area_parkir` (`id_area`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabel: tb_log_aktivitas
-- --------------------------------------------------------
CREATE TABLE `tb_log_aktivitas` (
  `id_log` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT NOT NULL,
  `aktivitas` VARCHAR(255) NOT NULL,
  `waktu_aktivitas` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_user` (`id_user`),
  INDEX `idx_log_waktu` (`waktu_aktivitas`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) 
    REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal: tb_user (Password default: [role]123)
-- admin: admin123 | petugas: petugas123 | owner: owner123
-- --------------------------------------------------------
INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `status_aktif`) VALUES
(1, 'Administrator Utama', 'admin', '$2y$10$Ubxzpdk1P5SRYDGT.vlBfebHrGFwOS92paIbVuPJRpi2TSa943vxO', 'admin', 1),
(2, 'Ahmad Petugas', 'petugas', '$2y$10$NgyE6LVWAROGjOKA0RvOjuhZpnfoPEhKiBrGhmoz2sbsPUisgNTyC', 'petugas', 1),
(3, 'Bapak Hendra (Owner)', 'owner', '$2y$10$pUTc.51uDeJZFdXUogs99ejaSQ0xjOk.Mq/hfcKF.9KWT7lJSfvIu', 'owner', 1);

-- --------------------------------------------------------
-- Data Awal: tb_tarif
-- --------------------------------------------------------
INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`) VALUES
(1, 'motor', 2000),
(2, 'mobil', 5000),
(3, 'lainnya', 8000);

-- --------------------------------------------------------
-- Data Awal: tb_area_parkir (Indoor Area A & Area B)
-- --------------------------------------------------------
INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`) VALUES
(1, 'Area A - Motor', 50, 0),
(2, 'Area B - Mobil', 30, 0);

-- --------------------------------------------------------
-- Data Awal: tb_kendaraan (Sampel data kendaraan uji coba)
-- --------------------------------------------------------
INSERT INTO `tb_kendaraan` (`id_kendaraan`, `plat_nomor`, `jenis_kendaraan`, `warna`, `pemilik`, `id_user`) VALUES
(1, 'B 1234 ABC', 'mobil', 'Hitam Metalik', 'Budi Santoso', 1),
(2, 'B 5678 XYZ', 'motor', 'Merah', 'Siti Rahma', 1),
(3, 'D 9988 EF', 'motor', 'Biru Putih', 'Andi Pratama', 2);

-- --------------------------------------------------------
-- Data Awal: tb_log_aktivitas
-- --------------------------------------------------------
INSERT INTO `tb_log_aktivitas` (`id_user`, `aktivitas`, `waktu_aktivitas`) VALUES
(1, 'Sistem diinisialisasi dan database berhasil dikonfigurasi', NOW());

-- --------------------------------------------------------
-- Stored Procedure: sp_parkir_keluar
-- Mendukung demonstrasi TCL (Transaction Control Language) & Locking
-- --------------------------------------------------------
DELIMITER //
CREATE PROCEDURE `sp_parkir_keluar`(
    IN `p_id_parkir` INT,
    IN `p_id_user` INT,
    OUT `p_sukses` TINYINT,
    OUT `p_pesan` VARCHAR(100),
    OUT `p_biaya` DECIMAL(10,0),
    OUT `p_durasi` INT
)
proc_block: BEGIN
    DECLARE v_status VARCHAR(10);
    DECLARE v_waktu_masuk DATETIME;
    DECLARE v_id_area INT;
    DECLARE v_tarif_saat_masuk DECIMAL(10,0);
    DECLARE v_durasi_menit INT;
    DECLARE v_durasi_jam INT;
    DECLARE v_total DECIMAL(10,0);
    DECLARE v_now DATETIME;

    -- Tangani error SQL otomatis rollback (harus sebelum statement executable)
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_sukses = 0;
        SET p_pesan = 'Terjadi kesalahan basis data (SQLEXCEPTION). Transaksi di-rollback.';
    END;

    -- Default output
    SET p_sukses = 0;
    SET p_pesan = '';
    SET p_biaya = 0;
    SET p_durasi = 0;
    SET v_now = NOW();

    START TRANSACTION;

    -- 1. Kunci baris transaksi dengan FOR UPDATE
    SELECT `status`, `waktu_masuk`, `id_area`, `tarif_per_jam_saat_masuk`
    INTO v_status, v_waktu_masuk, v_id_area, v_tarif_saat_masuk
    FROM `tb_transaksi`
    WHERE `id_parkir` = p_id_parkir
    FOR UPDATE;

    IF v_status IS NULL THEN
        ROLLBACK;
        SET p_sukses = 0;
        SET p_pesan = 'Transaksi parkir tidak ditemukan.';
        LEAVE proc_block;
    END IF;

    -- 2. Pastikan transaksi masih berstatus masuk
    IF v_status != 'masuk' THEN
        ROLLBACK;
        SET p_sukses = 0;
        SET p_pesan = 'Transaksi ini sudah selesai (kendaraan sudah keluar).';
        LEAVE proc_block;
    END IF;

    -- 3. Hitung durasi dalam menit, lalu konversi ke jam (pembulatan ke atas, min 1 jam)
    SET v_durasi_menit = TIMESTAMPDIFF(MINUTE, v_waktu_masuk, v_now);
    IF v_durasi_menit < 0 THEN
        SET v_durasi_menit = 0;
    END IF;

    -- Rumus: durasi_jam = max(1, ceil(durasi_menit / 60))
    SET v_durasi_jam = CEIL(v_durasi_menit / 60.0);
    IF v_durasi_jam < 1 THEN
        SET v_durasi_jam = 1;
    END IF;

    SET v_total = v_durasi_jam * v_tarif_saat_masuk;

    -- 4. Kunci area dan kurangi kapasitas terisi
    UPDATE `tb_area_parkir`
    SET `terisi` = GREATEST(0, `terisi` - 1)
    WHERE `id_area` = v_id_area;

    -- 5. Update transaksi menjadi keluar dan lunas
    UPDATE `tb_transaksi`
    SET `waktu_keluar` = v_now,
        `durasi_jam` = v_durasi_jam,
        `biaya_total` = v_total,
        `metode_pembayaran` = 'tunai',
        `jumlah_bayar` = v_total,
        `kembalian` = 0,
        `status_pembayaran` = 'lunas',
        `waktu_pembayaran` = v_now,
        `status` = 'keluar'
    WHERE `id_parkir` = p_id_parkir;

    -- 6. Catat log aktivitas
    INSERT INTO `tb_log_aktivitas` (`id_user`, `aktivitas`, `waktu_aktivitas`)
    VALUES (p_id_user, CONCAT('Kendaraan keluar id_parkir: ', p_id_parkir, ' (Biaya: Rp ', FORMAT(v_total, 0), ')'), v_now);

    COMMIT;

    SET p_sukses = 1;
    SET p_pesan = 'Kendaraan berhasil keluar.';
    SET p_biaya = v_total;
    SET p_durasi = v_durasi_jam;
END proc_block //
DELIMITER ;
