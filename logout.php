<?php
/**
 * Logout Handler
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $username = $_SESSION['user_username'] ?? 'User';
    $userId = $_SESSION['user_id'] ?? null;
    
    // Catat log aktivitas logout
    recordLog("Pengguna '{$username}' telah logout dari sistem.", $userId);
    
    // Hapus seluruh variabel sesi
    $_SESSION = [];
    
    // Hapus cookie sesi jika ada
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan sesi
    session_destroy();
}

// Mulai sesi baru khusus untuk pesan flash
session_start();
$_SESSION['flash_message'] = [
    'type'    => 'info',
    'message' => 'Anda telah berhasil keluar dari sistem.'
];

header('Location: ' . BASE_URL . '/login.php');
exit;
