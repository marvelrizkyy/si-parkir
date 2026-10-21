<?php
/**
 * Pencarian Kendaraan Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Fitur pencarian plat nomor untuk checkout parkir terintegrasi pada Kendaraan Keluar.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('petugas');

$plat = trim($_GET['plat'] ?? '');
if ($plat !== '') {
    header('Location: ' . BASE_URL . '/petugas/transaksi_keluar.php?kode=' . urlencode($plat));
    exit;
}

header('Location: ' . BASE_URL . '/petugas/transaksi_keluar.php');
exit;
