<?php
/**
 * Sidebar Navigation Template
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$userRole = $_SESSION['user_role'] ?? '';
$userNama = $_SESSION['user_nama'] ?? 'Pengguna';
$currentUri = $_SERVER['SCRIPT_NAME'];

function isActiveMenu($keyword) {
    global $currentUri;
    return (strpos($currentUri, $keyword) !== false) ? 'active' : '';
}
?>
<aside class="app-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>" class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-p-square-fill"></i>
            </div>
            <div>
                <div class="lh-1">SI PARKIR</div>
                <small class="text-secondary" style="font-size: 0.68rem; font-weight: 500;">Indoor Parking System</small>
            </div>
        </a>
    </div>

    <!-- Menu Navigasi -->
    <div class="sidebar-menu">
        <?php if ($userRole === 'admin'): ?>
            <!-- Menu Khusus Admin -->
            <div class="menu-label">Menu Utama</div>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link-custom <?= isActiveMenu('admin/dashboard.php') ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard Admin</span>
            </a>

            <div class="menu-label">Master Data</div>
            <a href="<?= BASE_URL ?>/admin/users/index.php" class="nav-link-custom <?= isActiveMenu('admin/users') ?>">
                <i class="bi bi-people-fill"></i>
                <span>Kelola Pengguna</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/tarif/index.php" class="nav-link-custom <?= isActiveMenu('admin/tarif') ?>">
                <i class="bi bi-tags-fill"></i>
                <span>Tarif Parkir</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/area/index.php" class="nav-link-custom <?= isActiveMenu('admin/area') ?>">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Area & Kapasitas</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="nav-link-custom <?= isActiveMenu('monitoring_slot.php') ?>">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <span>Monitoring Slot Parkir</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/kendaraan/index.php" class="nav-link-custom <?= isActiveMenu('admin/kendaraan') ?>">
                <i class="bi bi-car-front-fill"></i>
                <span>Data Kendaraan</span>
            </a>

            <div class="menu-label">Pengawasan & Log</div>
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="nav-link-custom <?= isActiveMenu('admin/transaksi') ?>">
                <i class="bi bi-receipt-cutoff"></i>
                <span>Data Transaksi</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/log/index.php" class="nav-link-custom <?= isActiveMenu('admin/log') ?>">
                <i class="bi bi-clock-history"></i>
                <span>Log Aktivitas</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/laporan.php" class="nav-link-custom <?= isActiveMenu('owner/laporan') ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Laporan & Rekap</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/backup/index.php" class="nav-link-custom <?= isActiveMenu('admin/backup') ?>">
                <i class="bi bi-database-down"></i>
                <span>Backup Database</span>
            </a>

        <?php elseif ($userRole === 'petugas'): ?>
            <!-- Menu Khusus Petugas Sesuai Kebijakan Operasional -->
            <div class="menu-label">Operasional Gerbang</div>
            <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="nav-link-custom <?= isActiveMenu('petugas/dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/transaksi_masuk.php" class="nav-link-custom <?= isActiveMenu('transaksi_masuk.php') ?>">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Kendaraan Masuk</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="nav-link-custom <?= isActiveMenu('transaksi_keluar.php') ?>">
                <i class="bi bi-box-arrow-right"></i>
                <span>Kendaraan Keluar</span>
            </a>

            <div class="menu-label">Laporan & Pemantauan</div>
            <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="nav-link-custom <?= isActiveMenu('monitoring_slot.php') ?>">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <span>Monitoring Slot Parkir</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/laporan.php" class="nav-link-custom <?= isActiveMenu('petugas/laporan.php') ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Laporan</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/riwayat.php" class="nav-link-custom <?= isActiveMenu('petugas/riwayat.php') ?>">
                <i class="bi bi-clock-history"></i>
                <span>Riwayat</span>
            </a>

        <?php elseif ($userRole === 'owner'): ?>
            <!-- Menu Khusus Owner (Eksekutif Read-Only) -->
            <div class="menu-label">Pengawasan Eksekutif</div>
            <a href="<?= BASE_URL ?>/owner/dashboard.php" class="nav-link-custom <?= isActiveMenu('owner/dashboard.php') ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/kendaraan.php" class="nav-link-custom <?= isActiveMenu('owner/kendaraan.php') ?>">
                <i class="bi bi-car-front-fill"></i>
                <span>Data Kendaraan</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/transaksi.php" class="nav-link-custom <?= isActiveMenu('owner/transaksi.php') ?>">
                <i class="bi bi-receipt"></i>
                <span>Riwayat Transaksi</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/laporan.php" class="nav-link-custom <?= isActiveMenu('owner/laporan.php') ?>">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Laporan Pendapatan</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/statistik.php" class="nav-link-custom <?= isActiveMenu('owner/statistik.php') ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Statistik & Tren</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/area.php" class="nav-link-custom <?= isActiveMenu('owner/area.php') ?>">
                <i class="bi bi-signpost-2-fill"></i>
                <span>Monitoring Area</span>
            </a>
            <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="nav-link-custom <?= isActiveMenu('monitoring_slot.php') ?>">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <span>Denah Slot Parkir</span>
            </a>
            <a href="<?= BASE_URL ?>/owner/log.php" class="nav-link-custom <?= isActiveMenu('owner/log.php') ?>">
                <i class="bi bi-clock-history"></i>
                <span>Log Aktivitas</span>
            </a>
        <?php endif; ?>

        <div class="menu-label">Sesi Pengguna</div>
        <a href="<?= BASE_URL ?>/welcome.php" target="_blank" class="nav-link-custom text-info">
            <i class="bi bi-house-door-fill text-info"></i>
            <span>Halaman Utama (Welcome)</span>
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="nav-link-custom text-danger" data-confirm="Apakah Anda yakin ingin keluar dari aplikasi?">
            <i class="bi bi-box-arrow-left text-danger"></i>
            <span>Keluar (Logout)</span>
        </a>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-25 p-2 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-white text-truncate small"><?= htmlspecialchars($userNama) ?></div>
                <span class="user-badge badge-role-<?= $userRole ?>"><?= strtoupper($userRole) ?></span>
            </div>
        </div>
    </div>
</aside>
