<?php
/**
 * Modul Autentikasi dan Otorisasi Berbasis Peran
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../config/database.php';

// Inisialisasi session yang aman
if (session_status() === PHP_SESSION_NONE) {
    // Pengaturan keamanan cookie session
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    // Jika HTTPS aktif
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

/**
 * Memeriksa apakah pengguna saat ini sudah login
 * @return bool
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Mendapatkan data pengguna yang sedang login dari session
 * @return array
 */
function getAuthUser(): array {
    return [
        'id_user'      => $_SESSION['user_id'] ?? null,
        'nama_lengkap' => $_SESSION['user_nama'] ?? '',
        'username'     => $_SESSION['user_username'] ?? '',
        'role'         => $_SESSION['user_role'] ?? ''
    ];
}

/**
 * Memastikan pengguna sudah login. Jika belum, redirect ke halaman login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses sistem.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Memeriksa apakah pengguna memiliki peran tertentu tanpa melakukan redirect
 * @param array|string $allowedRoles contoh: 'admin' atau ['admin', 'petugas']
 * @return bool
 */
function hasRole($allowedRoles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $userRole = $_SESSION['user_role'] ?? '';
    return in_array($userRole, $allowedRoles, true);
}

/**
 * Memastikan pengguna memiliki peran (role) yang diizinkan
 * @param array|string $allowedRoles contoh: 'admin' atau ['admin', 'petugas']
 */
function requireRole($allowedRoles): void {
    requireLogin();
    
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        include_once __DIR__ . '/header.php';
        echo "<div class='container py-5'>";
        echo "<div class='alert alert-danger shadow-sm border-0 rounded-4 p-4 text-center'>";
        echo "<h3 class='fw-bold text-danger mb-2'><i class='bi bi-shield-lock-fill me-2'></i>403 - Akses Terlarang</h3>";
        echo "<p class='text-muted mb-4'>Akun Anda dengan peran <strong>" . htmlspecialchars(strtoupper($userRole)) . "</strong> tidak memiliki wewenang untuk membuka modul ini.</p>";
        echo "<a href='" . BASE_URL . "/" . $userRole . "/dashboard.php' class='btn btn-primary px-4 rounded-pill'>Kembali ke Dashboard</a>";
        echo "</div>";
        echo "</div>";
        include_once __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * Mengarahkan pengguna ke dashboard sesuai peran masing-masing
 * @param string $role
 */
function redirectByRole(string $role): void {
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        case 'petugas':
            header('Location: ' . BASE_URL . '/petugas/dashboard.php');
            exit;
        case 'owner':
            header('Location: ' . BASE_URL . '/owner/dashboard.php');
            exit;
        default:
            header('Location: ' . BASE_URL . '/login.php');
            exit;
    }
}

/**
 * Mencatat aktivitas penting ke tabel tb_log_aktivitas
 * @param string $aktivitas
 * @param int|null $id_user Jika null, ambil dari user login saat ini
 * @return bool
 */
function recordLog(string $aktivitas, ?int $id_user = null): bool {
    try {
        $pdo = getDBConnection();
        if ($id_user === null) {
            $id_user = $_SESSION['user_id'] ?? null;
        }
        if (!$id_user) {
            return false;
        }
        $stmt = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, NOW())");
        return $stmt->execute([$id_user, $aktivitas]);
    } catch (Exception $e) {
        error_log("Gagal mencatat log aktivitas: " . $e->getMessage());
        return false;
    }
}
