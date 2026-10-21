<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection Helper
 * Sistem Informasi Parkir Berbasis Web
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Menghasilkan token CSRF baru atau mengambil token yang sudah ada di session
 * @return string
 */
function getCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan elemen HTML input hidden untuk CSRF
 * @return string
 */
function csrfField(): string {
    $token = htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Memvalidasi token CSRF dari request POST
 * @param string|null $token
 * @return bool
 */
function validateCSRFToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Memverifikasi token CSRF dari $_POST, jika gagal langsung hentikan eksekusi (403)
 */
function verifyCSRFOrDie(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die("<div style='font-family:sans-serif;padding:20px;margin:20px;border-left:5px solid #dc3545;background:#f8d7da;color:#842029;border-radius:4px;'>"
                . "<h4>403 - Akses Ditolak (CSRF Validation Failed)</h4>"
                . "<p>Token keamanan tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman formulir dan coba kembali.</p>"
                . "<a href='javascript:history.back()' style='color:#0d6efd;'>Kembali</a>"
                . "</div>");
        }
    }
}
