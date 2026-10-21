<?php
/**
 * Hapus Tarif Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_tarif, jenis_kendaraan FROM tb_tarif WHERE id_tarif = ?");
$stmt->execute([$id]);
$tarif = $stmt->fetch();

if (!$tarif) {
    setFlashMessage('danger', 'Tarif tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/tarif/index.php');
    exit;
}

// Cek apakah ada transaksi yang menggunakan tarif ini
$stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tb_transaksi WHERE id_tarif = ?");
$stmtCek->execute([$id]);
$trxCount = (int)$stmtCek->fetchColumn();

if ($trxCount > 0) {
    setFlashMessage('danger', "Tarif untuk '{$tarif['jenis_kendaraan']}' tidak dapat dihapus karena tercatat dalam {$trxCount} riwayat transaksi parkir.");
    header('Location: ' . BASE_URL . '/admin/tarif/index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM tb_tarif WHERE id_tarif = ?");
    $stmtDel->execute([$id]);

    recordLog("Menghapus data tarif parkir: {$tarif['jenis_kendaraan']}");
    setFlashMessage('success', "Tarif untuk '{$tarif['jenis_kendaraan']}' berhasil dihapus.");
} catch (Exception $e) {
    error_log("Error hapus tarif: " . $e->getMessage());
    setFlashMessage('danger', 'Gagal menghapus tarif parkir.');
}

header('Location: ' . BASE_URL . '/admin/tarif/index.php');
exit;
