<?php
/**
 * Root Entry Point
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    redirectByRole($role);
} else {
    header('Location: ' . BASE_URL . '/welcome.php');
    exit;
}
