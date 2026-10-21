<?php
/**
 * Konfigurasi Database dan Aplikasi
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

// Atur Zona Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'parkir');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// Base URL dinamis sesuai lokasi project di htdocs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Mendeteksi subfolder aplikasi
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$dirName = dirname($scriptName);
// Hilangkan subfolder bertingkat jika script dijalankan dari dalam admin/users dsb.
$pathParts = explode('/', trim($scriptName, '/'));
$baseFolder = (isset($pathParts[0]) && $pathParts[0] !== '') ? '/' . $pathParts[0] : '';
define('BASE_URL', $protocol . $host . $baseFolder);

// Nama Aplikasi
define('APP_NAME', 'Sistem Informasi Parkir');
define('APP_DESC', 'Aplikasi Pengelolaan Parkir Indoor (Motor & Mobil)');
define('APP_LOCATION', 'Gedung Parkir Utama Graha RPL');

/**
 * Mendapatkan koneksi PDO ke MySQL Database
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Menggunakan native prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Hindari membocorkan detail kredensial saat error
            error_log("Database Connection Error: " . $e->getMessage());
            die("<div style='font-family:sans-serif;padding:20px;margin:20px;border-left:5px solid #dc3545;background:#f8d7da;color:#842029;border-radius:4px;'>"
                . "<h4>Koneksi Basis Data Gagal</h4>"
                . "<p>Pastikan layanan MySQL di XAMPP sudah berjalan dan database <strong>" . DB_NAME . "</strong> sudah diimport.</p>"
                . "</div>");
        }
    }
    return $pdo;
}
