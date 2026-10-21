-- ========================================================
-- SISTEM INFORMASI PARKIR - CADANGAN DATABASE MANUAL
-- Waktu Backup : 2026-10-21 13:25:38
-- Dibuat Oleh   : Administrator Utama (ID: 1)
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- Struktur tabel `tb_user`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_user`;
CREATE TABLE `tb_user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','petugas','owner') NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_user`
INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `status_aktif`) VALUES
('1', 'Administrator Utama', 'admin', '$2y$10$Ubxzpdk1P5SRYDGT.vlBfebHrGFwOS92paIbVuPJRpi2TSa943vxO', 'admin', '1'),
('2', 'Ahmad Petugas', 'petugas', '$2y$10$NgyE6LVWAROGjOKA0RvOjuhZpnfoPEhKiBrGhmoz2sbsPUisgNTyC', 'petugas', '1'),
('3', 'Bapak Hendra (Owner)', 'owner', '$2y$10$pUTc.51uDeJZFdXUogs99ejaSQ0xjOk.Mq/hfcKF.9KWT7lJSfvIu', 'owner', '1');

-- --------------------------------------------------------
-- Struktur tabel `tb_kendaraan`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_kendaraan`;
CREATE TABLE `tb_kendaraan` (
  `id_kendaraan` int(11) NOT NULL AUTO_INCREMENT,
  `plat_nomor` varchar(15) NOT NULL,
  `jenis_kendaraan` varchar(20) NOT NULL,
  `warna` varchar(20) DEFAULT NULL,
  `pemilik` varchar(100) DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  PRIMARY KEY (`id_kendaraan`),
  UNIQUE KEY `plat_nomor` (`plat_nomor`),
  KEY `idx_plat` (`plat_nomor`),
  KEY `idx_jenis` (`jenis_kendaraan`),
  KEY `fk_kendaraan_user` (`id_user`),
  CONSTRAINT `fk_kendaraan_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_kendaraan`
INSERT INTO `tb_kendaraan` (`id_kendaraan`, `plat_nomor`, `jenis_kendaraan`, `warna`, `pemilik`, `id_user`) VALUES
('1', 'B 1234 ABC', 'mobil', 'Hitam Metalik', 'Budi Santoso', '1'),
('2', 'B 5678 XYZ', 'motor', 'Merah', 'Siti Rahma', '1'),
('3', 'D 9988 EF', 'motor', 'Biru Putih', 'Andi Pratama', '2'),
('4', 'B 4200 AUT', 'motor', NULL, 'Tamu Umum', '1');

-- --------------------------------------------------------
-- Struktur tabel `tb_tarif`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_tarif`;
CREATE TABLE `tb_tarif` (
  `id_tarif` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_kendaraan` enum('motor','mobil','lainnya') NOT NULL,
  `tarif_per_jam` decimal(10,0) NOT NULL,
  PRIMARY KEY (`id_tarif`),
  UNIQUE KEY `jenis_kendaraan` (`jenis_kendaraan`),
  KEY `idx_tarif_jenis` (`jenis_kendaraan`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_tarif`
INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`) VALUES
('1', 'motor', '2000'),
('2', 'mobil', '5000'),
('3', 'lainnya', '8000');

-- --------------------------------------------------------
-- Struktur tabel `tb_area_parkir`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_area_parkir`;
CREATE TABLE `tb_area_parkir` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `nama_area` varchar(50) NOT NULL,
  `kapasitas` int(11) NOT NULL,
  `terisi` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`),
  KEY `idx_area_nama` (`nama_area`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_area_parkir`
INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`) VALUES
('1', 'Area A - Motor', '50', '0'),
('2', 'Area B - Mobil', '30', '0');

-- --------------------------------------------------------
-- Struktur tabel `tb_transaksi`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_transaksi`;
CREATE TABLE `tb_transaksi` (
  `id_parkir` int(11) NOT NULL AUTO_INCREMENT,
  `kode_tiket` varchar(64) NOT NULL,
  `id_kendaraan` int(11) NOT NULL,
  `waktu_masuk` datetime NOT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `id_tarif` int(11) NOT NULL,
  `tarif_per_jam_saat_masuk` decimal(10,0) NOT NULL,
  `durasi_jam` int(11) DEFAULT NULL,
  `biaya_total` decimal(10,0) DEFAULT NULL,
  `status` enum('masuk','keluar') NOT NULL DEFAULT 'masuk',
  `metode_pembayaran` enum('tunai','non_tunai') NOT NULL DEFAULT 'tunai',
  `jumlah_bayar` decimal(10,0) NOT NULL DEFAULT 0,
  `kembalian` decimal(10,0) NOT NULL DEFAULT 0,
  `status_pembayaran` enum('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
  `waktu_pembayaran` datetime DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  PRIMARY KEY (`id_parkir`),
  UNIQUE KEY `kode_tiket` (`kode_tiket`),
  KEY `idx_trx_status` (`status`),
  KEY `idx_status_bayar` (`status_pembayaran`),
  KEY `idx_metode_bayar` (`metode_pembayaran`),
  KEY `idx_trx_masuk` (`waktu_masuk`),
  KEY `idx_trx_keluar` (`waktu_keluar`),
  KEY `idx_trx_kode` (`kode_tiket`),
  KEY `fk_trx_kendaraan` (`id_kendaraan`),
  KEY `fk_trx_tarif` (`id_tarif`),
  KEY `fk_trx_user` (`id_user`),
  KEY `fk_trx_area` (`id_area`),
  CONSTRAINT `fk_trx_area` FOREIGN KEY (`id_area`) REFERENCES `tb_area_parkir` (`id_area`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `tb_kendaraan` (`id_kendaraan`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_tarif` FOREIGN KEY (`id_tarif`) REFERENCES `tb_tarif` (`id_tarif`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_transaksi`
INSERT INTO `tb_transaksi` (`id_parkir`, `kode_tiket`, `id_kendaraan`, `waktu_masuk`, `waktu_keluar`, `id_tarif`, `tarif_per_jam_saat_masuk`, `durasi_jam`, `biaya_total`, `status`, `metode_pembayaran`, `jumlah_bayar`, `kembalian`, `status_pembayaran`, `waktu_pembayaran`, `id_user`, `id_area`) VALUES
('1', 'TRX-261021-AKTIF-590', '4', '2026-10-21 13:24:46', '2026-10-21 13:24:46', '1', '2000', '1', '2000', 'keluar', 'tunai', '2000', '0', 'lunas', NULL, '1', '1'),
('2', 'TRX-261021-OUT-88577-4307', '4', '2026-10-21 13:24:46', '2026-10-21 13:24:46', '1', '2000', '1', '2000', 'keluar', 'tunai', '0', '0', 'belum_bayar', NULL, '1', '1');

-- --------------------------------------------------------
-- Struktur tabel `tb_log_aktivitas`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_log_aktivitas`;
CREATE TABLE `tb_log_aktivitas` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `waktu_aktivitas` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_log_user` (`id_user`),
  KEY `idx_log_waktu` (`waktu_aktivitas`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data untuk tabel `tb_log_aktivitas`
INSERT INTO `tb_log_aktivitas` (`id_log`, `id_user`, `aktivitas`, `waktu_aktivitas`) VALUES
('1', '1', 'Sistem diinisialisasi dan database berhasil dikonfigurasi', '2026-10-21 13:24:30'),
('2', '1', 'Pengguna \'admin\' (admin) berhasil login ke sistem.', '2026-10-21 13:25:38');

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
