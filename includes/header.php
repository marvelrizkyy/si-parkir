<?php
/**
 * Header Layout Template
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Sistem Informasi Parkir';
$user = getAuthUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <meta name="theme-color" content="#7f8c8d">
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Chart.js (Untuk Grafik Dashboard) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Html5-QRCode (Untuk Pemindaian Kamera QR Code di Petugas) -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head>
<body>

<?php if (isLoggedIn()): ?>
<div class="app-wrapper">
    <!-- Backdrop untuk Mobile -->
    <div class="sidebar-backdrop"></div>
    
    <!-- Sidebar Menu Navigasi -->
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <!-- Area Utama -->
    <div class="app-main">
        <!-- Top Navbar -->
        <?php include __DIR__ . '/navbar.php'; ?>
        
        <!-- Content Container -->
        <main class="content-body">
            <?= displayFlashMessages() ?>
<?php endif; ?>
