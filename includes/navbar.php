<?php
/**
 * Top Navbar Template
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$user = getAuthUser();
?>
<header class="app-navbar">
    <div class="d-flex align-items-center gap-3">
        <button class="sidebar-toggler" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="d-none d-md-block">
            <span class="badge border py-2 px-3 rounded-pill fw-semibold" style="background: #fdf8ed; color: #b88220; border-color: #fae8c3 !important;">
                <i class="bi bi-geo-alt-fill me-1" style="color: #ebb146;"></i> <?= APP_LOCATION ?>
            </span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Live Clock WIB -->
        <div class="d-flex align-items-center gap-2 text-secondary small bg-white border px-3 py-1 rounded-pill shadow-sm" style="font-size: 0.82rem; border-color: var(--card-border) !important;">
            <span class="pulse-live"></span>
            <span id="live-clock" class="fw-bold text-dark font-monospace" style="font-family: 'JetBrains Mono', monospace !important;">00:00:00 WIB</span>
        </div>

        <!-- User Dropdown Profile -->
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1 border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.88rem; background: #ffffff; border-color: var(--card-border) !important;">
                <i class="bi bi-person-circle fs-5" style="color: #ebb146;"></i>
                <span class="d-none d-sm-inline fw-semibold"><?= htmlspecialchars($user['nama_lengkap']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border rounded-4 mt-2" style="border-color: var(--card-border); min-width: 220px; padding: 6px;">
                <li class="px-3 py-2 border-bottom" style="border-color: var(--card-border) !important;">
                    <div class="fw-bold small text-dark"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    <div class="text-muted small mt-1">@<?= htmlspecialchars($user['username']) ?> &bull; <span class="badge-role-<?= $user['role'] ?> px-2 py-0 rounded text-uppercase" style="font-size:0.65rem;"><?= $user['role'] ?></span></div>
                </li>
                <li>
                    <a class="dropdown-item py-2 text-secondary small rounded-3 mt-1" href="<?= BASE_URL ?>/welcome.php" target="_blank">
                        <i class="bi bi-house-door me-2" style="color: #ebb146;"></i> Halaman Depan
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1" style="border-color: var(--card-border);"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger small rounded-3" href="<?= BASE_URL ?>/logout.php" data-confirm="Yakin ingin logout?">
                        <i class="bi bi-box-arrow-left me-2"></i> Logout Sesi
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
