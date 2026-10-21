<?php
/**
 * Test Validasi Khusus Halaman Petugas & Fitur Scan QR Tiket Keluar
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

echo "============================================================\n";
echo "   UJI VALIDASI HALAMAN PETUGAS & SCAN QR KENDARAAN KELUAR  \n";
echo "============================================================\n\n";

$errors = 0;

function assertCheck($condition, $title, $detail = '') {
    global $errors;
    if ($condition) {
        echo "[PASS] $title\n";
        if ($detail) echo "       Detail: $detail\n";
    } else {
        $errors++;
        echo "[FAIL] $title\n";
        if ($detail) echo "       Detail: $detail\n";
    }
}

// 1. Uji Sidebar Navigasi Petugas
$sidebarCode = file_get_contents(__DIR__ . '/../includes/sidebar.php');
$petugasSection = '';
if (preg_match('/<\?php\s+elseif\s*\(\$userRole\s*===\s*\'petugas\'\):(?:\?>)?(.*?)(?:<\?php\s+elseif|<\?php\s+endif)/s', $sidebarCode, $m)) {
    $petugasSection = $m[1];
}

assertCheck(!empty($petugasSection), 'Menu Petugas di sidebar ditemukan', 'Blok elseif ($userRole === \'petugas\') berhasil diparsing.');

// Cek 6 menu yang wajib ada di Petugas
$requiredMenus = [
    'petugas/dashboard.php' => 'Dashboard',
    'transaksi_masuk.php' => 'Kendaraan Masuk',
    'transaksi_keluar.php' => 'Kendaraan Keluar',
    'monitoring_slot.php' => 'Monitoring Slot Parkir',
    'petugas/laporan.php' => 'Laporan',
    'petugas/riwayat.php' => 'Riwayat'
];

foreach ($requiredMenus as $href => $label) {
    $found = (strpos($petugasSection, $href) !== false);
    assertCheck($found, "Menu Petugas memuat '$label' ($href)", "Tautan navigasi terverifikasi dalam menu Petugas.");
}

// Cek menu yang TIDAK boleh ada di Petugas
$forbiddenMenus = [
    'petugas/scan_qr.php' => 'Scan QR Tiket Standalone',
    'petugas/transaksi_aktif.php' => 'Sedang Parkir Standalone',
    'petugas/cari_kendaraan.php' => 'Cari Plat Standalone'
];

foreach ($forbiddenMenus as $href => $label) {
    $notPresent = (strpos($petugasSection, $href) === false);
    assertCheck($notPresent, "Menu Petugas TIDAK memuat menu terpisah '$label'", "Navigasi Petugas tetap bersih & terfokus.");
}

// 2. Uji Integrasi Scanner QR Langsung pada transaksi_keluar.php
$keluarCode = file_get_contents(__DIR__ . '/../petugas/transaksi_keluar.php');

$hasQrReader = (strpos($keluarCode, 'id="qr-reader"') !== false);
assertCheck($hasQrReader, "transaksi_keluar.php memuat elemen scanner kamera '#qr-reader'", "Scanner kamera terpasang langsung pada gerbang keluar.");

$hasScannerButtons = (strpos($keluarCode, 'id="btn-start-scanner"') !== false && strpos($keluarCode, 'id="btn-stop-scanner"') !== false);
assertCheck($hasScannerButtons, "transaksi_keluar.php memuat tombol start/stop scanner kamera", "Kontrol kamera terverifikasi.");

$hasScannerInit = (strpos($keluarCode, "initQrScanner('kode', 'form-cari-keluar')") !== false);
assertCheck($hasScannerInit, "transaksi_keluar.php menginisialisasi initQrScanner dengan auto-submit", "Hasil scan QR langsung memicu pencarian dan perhitungan biaya.");

$hasHeroBanner = (strpos($keluarCode, "TOTAL BIAYA PARKIR YANG PERLU DIBAYAR") !== false);
assertCheck($hasHeroBanner, "transaksi_keluar.php memuat Display Hero 'TOTAL BIAYA PARKIR YANG PERLU DIBAYAR'", "Total biaya ditampilkan secara sangat jelas dan menonjol bagi kasir.");

// 3. Uji Pengalihan scan_qr.php ke transaksi_keluar.php?scan=1
$scanQrCode = file_get_contents(__DIR__ . '/../petugas/scan_qr.php');
$hasScanRedirect = (strpos($scanQrCode, "transaksi_keluar.php?scan=1") !== false);
assertCheck($hasScanRedirect, "scan_qr.php dialihkan secara otomatis ke transaksi_keluar.php?scan=1", "Akses legacy langsung menuju ke fitur scanner di gerbang keluar.");

// 4. Uji Dashboard Petugas
$dashCode = file_get_contents(__DIR__ . '/../petugas/dashboard.php');
$dashHasKeluarSearch = (strpos($dashCode, 'action="<?= BASE_URL ?>/petugas/transaksi_keluar.php"') !== false);
assertCheck($dashHasKeluarSearch, "Pencarian cepat di Dashboard Petugas mengarah ke transaksi_keluar.php", "Input plat nomor langsung menghitung biaya keluar.");

$dashHasRiwayatAll = (strpos($dashCode, 'href="<?= BASE_URL ?>/petugas/riwayat.php?status=masuk"') !== false);
assertCheck($dashHasRiwayatAll, "Tautan 'Lihat Semua' kendaraan sedang parkir mengarah ke riwayat.php?status=masuk", "Menggunakan halaman riwayat resmi petugas.");

echo "\n============================================================\n";
if ($errors === 0) {
    echo "HASIL AKHIR: SELURUH PENGUJIAN HALAMAN PETUGAS & SCAN QR 100% PASS!\n";
} else {
    echo "HASIL AKHIR: ADA $errors PENGUJIAN YANG GAGAL!\n";
}
echo "============================================================\n";
exit($errors > 0 ? 1 : 0);
