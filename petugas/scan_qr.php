<?php
/**
 * Pemindaian QR Code Tiket Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Fitur QR scanner telah diintegrasikan langsung pada Gerbang Kendaraan Keluar (Kasir Pembayaran).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('petugas');

// Pengalihan cerdas: Scan QR tiket kini terintegrasi langsung pada Gerbang Kendaraan Keluar
header('Location: ' . BASE_URL . '/petugas/transaksi_keluar.php?scan=1');
exit;
