-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: parkir
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tb_area_parkir`
--

DROP TABLE IF EXISTS `tb_area_parkir`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_area_parkir` (
  `id_area` int(11) NOT NULL AUTO_INCREMENT,
  `nama_area` varchar(50) NOT NULL,
  `kapasitas` int(11) NOT NULL,
  `terisi` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`),
  KEY `idx_area_nama` (`nama_area`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_area_parkir`
--

LOCK TABLES `tb_area_parkir` WRITE;
/*!40000 ALTER TABLE `tb_area_parkir` DISABLE KEYS */;
INSERT INTO `tb_area_parkir` VALUES (1,'Area A - Motor',50,0),(2,'Area B - Mobil',30,0);
/*!40000 ALTER TABLE `tb_area_parkir` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_kendaraan`
--

DROP TABLE IF EXISTS `tb_kendaraan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_kendaraan`
--

LOCK TABLES `tb_kendaraan` WRITE;
/*!40000 ALTER TABLE `tb_kendaraan` DISABLE KEYS */;
INSERT INTO `tb_kendaraan` VALUES (1,'B 1234 ABC','mobil','Hitam Metalik','Budi Santoso',1),(2,'B 5678 XYZ','motor','Merah','Siti Rahma',1),(3,'D 9988 EF','motor','Biru Putih','Andi Pratama',2);
/*!40000 ALTER TABLE `tb_kendaraan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_log_aktivitas`
--

DROP TABLE IF EXISTS `tb_log_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_log_aktivitas` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `waktu_aktivitas` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_log_user` (`id_user`),
  KEY `idx_log_waktu` (`waktu_aktivitas`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_log_aktivitas`
--

LOCK TABLES `tb_log_aktivitas` WRITE;
/*!40000 ALTER TABLE `tb_log_aktivitas` DISABLE KEYS */;
INSERT INTO `tb_log_aktivitas` VALUES (1,1,'Sistem diinisialisasi dan database berhasil dikonfigurasi','2026-10-21 08:28:55'),(2,1,'Pengguna \'admin\' (admin) berhasil login ke sistem.','2026-10-21 08:34:48'),(3,1,'Pengguna \'admin\' telah logout dari sistem.','2026-10-21 08:35:12'),(4,2,'Pengguna \'petugas\' (petugas) berhasil login ke sistem.','2026-10-21 08:35:14'),(5,2,'Pengguna \'petugas\' telah logout dari sistem.','2026-10-21 08:38:44'),(6,1,'Pengguna \'admin\' (admin) berhasil login ke sistem.','2026-10-21 08:38:46'),(7,1,'Pengguna \'admin\' telah logout dari sistem.','2026-10-21 08:39:21'),(8,3,'Pengguna \'owner\' (owner) berhasil login ke sistem.','2026-10-21 08:39:23'),(9,3,'Pengguna \'owner\' telah logout dari sistem.','2026-10-21 08:42:17'),(10,2,'Pengguna \'petugas\' (petugas) berhasil login ke sistem.','2026-10-21 08:42:19'),(11,1,'Unit Test Automated Check Completed Successfully','2026-10-21 08:47:36'),(12,1,'Pengguna \'admin\' (admin) berhasil login ke sistem.','2026-10-21 08:49:35'),(13,1,'Pengguna \'admin\' (admin) berhasil login ke sistem.','2026-10-21 08:50:16'),(14,2,'Pengguna \'petugas\' (petugas) berhasil login ke sistem.','2026-10-21 08:50:16'),(15,3,'Pengguna \'owner\' (owner) berhasil login ke sistem.','2026-10-21 08:50:16');
/*!40000 ALTER TABLE `tb_log_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_tarif`
--

DROP TABLE IF EXISTS `tb_tarif`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_tarif` (
  `id_tarif` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_kendaraan` enum('motor','mobil','lainnya') NOT NULL,
  `tarif_per_jam` decimal(10,0) NOT NULL,
  PRIMARY KEY (`id_tarif`),
  UNIQUE KEY `jenis_kendaraan` (`jenis_kendaraan`),
  KEY `idx_tarif_jenis` (`jenis_kendaraan`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_tarif`
--

LOCK TABLES `tb_tarif` WRITE;
/*!40000 ALTER TABLE `tb_tarif` DISABLE KEYS */;
INSERT INTO `tb_tarif` VALUES (1,'motor',2000),(2,'mobil',5000),(3,'lainnya',8000);
/*!40000 ALTER TABLE `tb_tarif` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_transaksi`
--

DROP TABLE IF EXISTS `tb_transaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `id_user` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  PRIMARY KEY (`id_parkir`),
  UNIQUE KEY `kode_tiket` (`kode_tiket`),
  KEY `idx_trx_status` (`status`),
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_transaksi`
--

LOCK TABLES `tb_transaksi` WRITE;
/*!40000 ALTER TABLE `tb_transaksi` DISABLE KEYS */;
INSERT INTO `tb_transaksi` VALUES (1,'TEST-261021-895',2,'2026-10-21 06:00:00','2026-10-21 08:30:00',1,2000,3,6000,'keluar',2,1);
/*!40000 ALTER TABLE `tb_transaksi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_user`
--

DROP TABLE IF EXISTS `tb_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_user`
--

LOCK TABLES `tb_user` WRITE;
/*!40000 ALTER TABLE `tb_user` DISABLE KEYS */;
INSERT INTO `tb_user` VALUES (1,'Administrator Utama','admin','$2y$10$Ubxzpdk1P5SRYDGT.vlBfebHrGFwOS92paIbVuPJRpi2TSa943vxO','admin',1),(2,'Ahmad Petugas','petugas','$2y$10$NgyE6LVWAROGjOKA0RvOjuhZpnfoPEhKiBrGhmoz2sbsPUisgNTyC','petugas',1),(3,'Bapak Hendra (Owner)','owner','$2y$10$pUTc.51uDeJZFdXUogs99ejaSQ0xjOk.Mq/hfcKF.9KWT7lJSfvIu','owner',1);
/*!40000 ALTER TABLE `tb_user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-21  8:50:25
