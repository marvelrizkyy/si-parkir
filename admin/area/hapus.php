<?php
/**
 * Hapus Area Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_area, nama_area, terisi FROM tb_area_parkir WHERE id_area = ?");
$stmt->execute([$id]);
$area = $stmt->fetch();

if (!$area) {
    setFlashMessage('danger', 'Area tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/area/index.php');
    exit;
}

if ((int)$area['terisi'] > 0) {
    setFlashMessage('danger', "Area '{$area['nama_area']}' tidak dapat dihapus karena masih ada {$area['terisi']} kendaraan yang sedang parkir.");
    header('Location: ' . BASE_URL . '/admin/area/index.php');
    exit;
}

// Cek apakah ada relasi transaksi
$stmtTrx = $pdo->prepare("SELECT COUNT(*) FROM tb_transaksi WHERE id_area = ?");
$stmtTrx->execute([$id]);
$trxCount = (int)$stmtTrx->fetchColumn();

if ($trxCount > 0) {
    setFlashMessage('danger', "Area '{$area['nama_area']}' tidak dapat dihapus karena tercatat dalam {$trxCount} riwayat transaksi.");
    header('Location: ' . BASE_URL . '/admin/area/index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM tb_area_parkir WHERE id_area = ?");
    $stmtDel->execute([$id]);

    recordLog("Menghapus area parkir: {$area['nama_area']}");
    setFlashMessage('success', "Area '{$area['nama_area']}' berhasil dihapus.");
} catch (Exception $e) {
    error_log("Error hapus area: " . $e->getMessage());
    setFlashMessage('danger', 'Gagal menghapus area parkir.');
}

header('Location: ' . BASE_URL . '/admin/area/index.php');
exit;
