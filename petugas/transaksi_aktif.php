<?php
/**
 * Kendaraan Sedang Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Terintegrasi ke modul Riwayat dengan filter status masuk.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('petugas');

header('Location: ' . BASE_URL . '/petugas/riwayat.php?status=masuk');
exit;
