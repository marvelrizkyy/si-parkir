<?php
/**
 * Toggle Status Pengguna (Aktif / Nonaktif)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_user, username, role, status_aktif FROM tb_user WHERE id_user = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('danger', 'Pengguna tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/users/index.php');
    exit;
}

$newStatus = ((int)$user['status_aktif'] === 1) ? 0 : 1;

// Cegah menonaktifkan admin terakhir
if ($user['role'] === 'admin' && $newStatus === 0) {
    $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tb_user WHERE role = 'admin' AND status_aktif = 1 AND id_user != ?");
    $stmtCek->execute([$id]);
    $otherAdmins = (int)$stmtCek->fetchColumn();

    if ($otherAdmins === 0) {
        setFlashMessage('danger', 'Gagal menonaktifkan akun: Sistem harus memiliki minimal satu akun Administrator yang aktif!');
        header('Location: ' . BASE_URL . '/admin/users/index.php');
        exit;
    }
}

try {
    $stmtUpdate = $pdo->prepare("UPDATE tb_user SET status_aktif = ? WHERE id_user = ?");
    $stmtUpdate->execute([$newStatus, $id]);

    $statusText = ($newStatus === 1) ? 'diaktifkan' : 'dinonaktifkan';
    recordLog("Mengubah status akun pengguna @{$user['username']} menjadi {$statusText}.");
    setFlashMessage('success', "Akun @{$user['username']} berhasil {$statusText}.");
} catch (Exception $e) {
    error_log("Error toggle status user: " . $e->getMessage());
    setFlashMessage('danger', 'Gagal mengubah status akun pengguna.');
}

header('Location: ' . BASE_URL . '/admin/users/index.php');
exit;
