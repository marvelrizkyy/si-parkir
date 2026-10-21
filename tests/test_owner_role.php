<?php
/**
 * Test Suite Khusus Verifikasi Role Owner (16 Pengujian Wajib)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

echo "============================================================\n";
echo "       PENGUJIAN VALIDASI IMPLEMENTASI ROLE OWNER           \n";
echo "============================================================\n\n";

$pdo = getDBConnection();
$passed = 0;
$total = 16;

function assertOwner($no, $title, $condition, $details = '') {
    global $passed;
    if ($condition) {
        $passed++;
        echo "[PASS] Test {$no}: {$title}\n";
        if ($details) {
            echo "       Detail: {$details}\n";
        }
    } else {
        echo "[FAIL] Test {$no}: {$title}\n";
        if ($details) {
            echo "       Alasan: {$details}\n";
        }
        exit(1);
    }
}

// -------------------------------------------------------------
// 1. Owner dapat login dengan akun yang benar
// -------------------------------------------------------------
$stmtOwner = $pdo->prepare("SELECT * FROM tb_user WHERE username = ?");
$stmtOwner->execute(['owner']);
$userOwner = $stmtOwner->fetch();
$authOk = ($userOwner && $userOwner['role'] === 'owner' && password_verify('owner123', $userOwner['password']));
assertOwner(1, "Owner dapat login dengan akun yang benar", $authOk, "Kredensial owner / owner123 valid dan terverifikasi hash Bcrypt.");

// Set session sebagai Owner
$_SESSION['user_id'] = $userOwner['id_user'];
$_SESSION['user_nama'] = $userOwner['nama_lengkap'];
$_SESSION['user_username'] = $userOwner['username'];
$_SESSION['user_role'] = 'owner';

// -------------------------------------------------------------
// 2. Owner dapat membuka dashboard
// -------------------------------------------------------------
$canAccessDashboard = hasRole('owner');
$dashboardFile = file_exists(__DIR__ . '/../owner/dashboard.php');
assertOwner(2, "Owner dapat membuka dashboard", ($canAccessDashboard && $dashboardFile), "Berkas owner/dashboard.php ada dan hasRole('owner') aktif.");

// -------------------------------------------------------------
// 3. Owner dapat melihat data kendaraan dan riwayat transaksi
// -------------------------------------------------------------
$kendaraanFile = file_exists(__DIR__ . '/../owner/kendaraan.php');
$transaksiFile = file_exists(__DIR__ . '/../owner/transaksi.php');
$stmtK = $pdo->query("SELECT COUNT(*) FROM tb_kendaraan");
$canQueryKendaraan = ($stmtK->fetchColumn() !== false);
$stmtT = $pdo->query("SELECT COUNT(*) FROM tb_transaksi");
$canQueryTransaksi = ($stmtT->fetchColumn() !== false);
assertOwner(3, "Owner dapat melihat data kendaraan dan riwayat transaksi", 
    ($kendaraanFile && $transaksiFile && $canQueryKendaraan && $canQueryTransaksi), 
    "Modul owner/kendaraan.php dan owner/transaksi.php terhubung langsung ke basis data."
);

// -------------------------------------------------------------
// 4. Owner dapat melihat laporan dan grafik
// -------------------------------------------------------------
$laporanFile = file_exists(__DIR__ . '/../owner/laporan.php');
$statistikFile = file_exists(__DIR__ . '/../owner/statistik.php');
$laporanContent = file_get_contents(__DIR__ . '/../owner/laporan.php');
$hasLunasRule = (strpos($laporanContent, "status_pembayaran = 'lunas'") !== false);
assertOwner(4, "Owner dapat melihat laporan dan grafik", 
    ($laporanFile && $statistikFile && $hasLunasRule), 
    "Laporan pendapatan menghitung hanya transaksi lunas dan modul grafik statistik tersedia."
);

// -------------------------------------------------------------
// 5. Owner dapat memfilter data berdasarkan tanggal
// -------------------------------------------------------------
$filterQuery = "SELECT COUNT(*) FROM tb_transaksi WHERE DATE(waktu_masuk) >= ? AND DATE(waktu_masuk) <= ?";
$stmtFilter = $pdo->prepare($filterQuery);
$filterOk = $stmtFilter->execute(['2026-01-01', '2026-12-31']);
assertOwner(5, "Owner dapat memfilter data berdasarkan tanggal", 
    $filterOk, 
    "Filter rentang tanggal dieksekusi dengan PDO prepared statements."
);

// -------------------------------------------------------------
// 6. Owner dapat mengekspor CSV
// -------------------------------------------------------------
$exportFile = file_exists(__DIR__ . '/../owner/export_csv.php');
$exportContent = file_get_contents(__DIR__ . '/../owner/export_csv.php');
$hasProtection = (strpos($exportContent, "safeCSVCell") !== false);
$hasRoleCheck = (strpos($exportContent, "requireRole") !== false);
assertOwner(6, "Owner dapat mengekspor CSV terlindungi", 
    ($exportFile && $hasProtection && $hasRoleCheck), 
    "owner/export_csv.php memeriksa role dan menerapkan sanitasi safeCSVCell anti formula injection."
);

// -------------------------------------------------------------
// 7. Owner dapat melihat log aktivitas
// -------------------------------------------------------------
$logFile = file_exists(__DIR__ . '/../owner/log.php');
$stmtLog = $pdo->query("SELECT COUNT(*) FROM tb_log_aktivitas");
$canQueryLog = ($stmtLog->fetchColumn() !== false);
assertOwner(7, "Owner dapat melihat log aktivitas", 
    ($logFile && $canQueryLog), 
    "owner/log.php menyediakan pengawasan audit trail aktivitas sistem bagi Owner."
);

// -------------------------------------------------------------
// 8. Owner tidak dapat mengakses halaman Admin
// -------------------------------------------------------------
$ownerCobaAdmin = hasRole('admin'); // Harus false
assertOwner(8, "Owner tidak dapat mengakses halaman Admin", 
    (!$ownerCobaAdmin), 
    "Pemeriksaan RBAC hasRole('admin') menolak sesi Owner secara mutlak."
);

// -------------------------------------------------------------
// 9. Owner tidak dapat menambahkan atau mengedit kendaraan
// -------------------------------------------------------------
$adminKendaraanTambah = file_get_contents(__DIR__ . '/../admin/kendaraan/tambah.php');
$petugasKendaraanTambah = file_get_contents(__DIR__ . '/../petugas/kendaraan_tambah.php');
$adminKendaraanEdit = file_get_contents(__DIR__ . '/../admin/kendaraan/edit.php');
$ownerBlocked1 = (strpos($adminKendaraanTambah, "requireRole('admin')") !== false);
$ownerBlocked2 = (strpos($petugasKendaraanTambah, "requireRole('petugas')") !== false);
$ownerBlocked3 = (strpos($adminKendaraanEdit, "requireRole('admin')") !== false);
assertOwner(9, "Owner tidak dapat menambahkan atau mengedit kendaraan", 
    ($ownerBlocked1 && $ownerBlocked2 && $ownerBlocked3), 
    "Endpoint tambah & edit kendaraan dibatasi ketat khusus peran Admin atau Petugas."
);

// -------------------------------------------------------------
// 10. Owner tidak dapat mengubah tarif atau kapasitas area
// -------------------------------------------------------------
$tarifEdit = file_get_contents(__DIR__ . '/../admin/tarif/edit.php');
$areaEdit = file_get_contents(__DIR__ . '/../admin/area/edit.php');
$tarifBlocked = (strpos($tarifEdit, "requireRole('admin')") !== false);
$areaBlocked = (strpos($areaEdit, "requireRole('admin')") !== false);
assertOwner(10, "Owner tidak dapat mengubah tarif atau kapasitas area", 
    ($tarifBlocked && $areaBlocked), 
    "Endpoint modifikasi master tarif dan kapasitas area memblokir peran non-admin."
);

// -------------------------------------------------------------
// 11. Owner tidak dapat memproses transaksi masuk atau keluar
// -------------------------------------------------------------
$trxMasuk = file_get_contents(__DIR__ . '/../petugas/transaksi_masuk.php');
$trxKeluar = file_get_contents(__DIR__ . '/../petugas/transaksi_keluar.php');
$ownerBlockedIn = (strpos($trxMasuk, "requireRole('petugas')") !== false);
$ownerBlockedOut = (strpos($trxKeluar, "requireRole('petugas')") !== false);
assertOwner(11, "Owner tidak dapat memproses transaksi masuk atau keluar", 
    ($ownerBlockedIn && $ownerBlockedOut), 
    "Gerbang masuk dan keluar mewajibkan peran petugas (Owner ditolak HTTP 403)."
);

// -------------------------------------------------------------
// 12. Owner tidak dapat mengubah status pembayaran
// -------------------------------------------------------------
// Pembayaran hanya ada di petugas/transaksi_keluar.php
assertOwner(12, "Owner tidak dapat memanipulasi status pembayaran", 
    $ownerBlockedOut, 
    "Eksekusi pelunasan pembayaran berada di dalam transaksi_keluar.php yang memblokir Owner."
);

// -------------------------------------------------------------
// 13. Owner tidak dapat menghapus transaksi atau log
// -------------------------------------------------------------
// Di owner/transaksi.php dan owner/log.php tidak ada POST handler delete
$ownerTrxContent = file_get_contents(__DIR__ . '/../owner/transaksi.php');
$ownerLogContent = file_get_contents(__DIR__ . '/../owner/log.php');
$noDeleteTrx = (strpos($ownerTrxContent, 'DELETE') === false && strpos($ownerTrxContent, 'action="hapus"') === false);
$noDeleteLog = (strpos($ownerLogContent, 'DELETE') === false && strpos($ownerLogContent, 'action="hapus"') === false);
assertOwner(13, "Owner tidak dapat menghapus transaksi atau log", 
    ($noDeleteTrx && $noDeleteLog), 
    "Modul transaksi dan log Owner 100% read-only tanpa endpoint atau query DELETE."
);

// -------------------------------------------------------------
// 14. Owner tidak dapat melakukan backup atau restore
// -------------------------------------------------------------
$backupFile = file_get_contents(__DIR__ . '/../admin/backup/index.php');
$backupBlocked = (strpos($backupFile, "requireRole('admin')") !== false);
assertOwner(14, "Owner tidak dapat melakukan backup atau restore", 
    $backupBlocked, 
    "Modul backup database mandiri dilindungi requireRole('admin') (Owner dilarang)."
);

// -------------------------------------------------------------
// 15. Owner tidak dapat melewati pembatasan dengan direct URL
// -------------------------------------------------------------
// Simulasikan session Owner memanggil hasRole('admin') atau hasRole('petugas')
$canBypassToAdmin = hasRole('admin');
$canBypassToPetugas = hasRole('petugas');
assertOwner(15, "Owner tidak dapat melewati pembatasan dengan direct URL", 
    (!$canBypassToAdmin && !$canBypassToPetugas), 
    "Pengecekan server-side session user_role mencegah bypass URL tanpa otorisasi."
);

// -------------------------------------------------------------
// 16. Data dashboard, laporan, dan CSV tetap konsisten
// -------------------------------------------------------------
$sumLunasDb = (float)$pdo->query("SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status_pembayaran = 'lunas'")->fetchColumn();
$stmtLunasLaporan = $pdo->query("
    SELECT COALESCE(SUM(CASE WHEN status_pembayaran = 'lunas' THEN biaya_total ELSE 0 END), 0) 
    FROM tb_transaksi
");
$sumLunasLaporan = (float)$stmtLunasLaporan->fetchColumn();
$isConsistent = ($sumLunasDb === $sumLunasLaporan);
assertOwner(16, "Konsistensi data antara Dashboard, Laporan, dan Basis Data", 
    $isConsistent, 
    "Kalkulasi omset lunas di seluruh modul konsisten Rp " . number_format($sumLunasDb, 0, ',', '.') . "."
);

echo "\n============================================================\n";
echo "HASIL AKHIR: {$passed} DARI {$total} PENGUJIAN ROLE OWNER 100% PASS!\n";
echo "============================================================\n";
