<?php
/**
 * Halaman Welcome / Landing Page Publik
 * Sistem Informasi Parkir Berbasis Web
 * Desain: Warm dashboard card-based layout
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

// Ambil data area parkir
$stmtAreas = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS terisi_aktual
    FROM tb_area_parkir a
    ORDER BY a.nama_area ASC
");
$areaList = $stmtAreas->fetchAll();

// Ambil transaksi aktif per area
$stmtTrx = $pdo->query("
    SELECT t.id_area, t.waktu_masuk, t.tarif_per_jam_saat_masuk, k.plat_nomor, k.jenis_kendaraan
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk ASC
");
$activeTrx = $stmtTrx->fetchAll();
$parkedByArea = [];
foreach ($activeTrx as $t) {
    $parkedByArea[$t['id_area']][] = $t;
}

$totalKapasitas = 0;
$totalTerisi = 0;
foreach ($areaList as $a) {
    $totalKapasitas += (int)$a['kapasitas'];
    $totalTerisi += (int)$a['terisi_aktual'];
}
$totalKosong = max(0, $totalKapasitas - $totalTerisi);
$persenOkupansi = $totalKapasitas > 0 ? min(100, round(($totalTerisi / $totalKapasitas) * 100)) : 0;

// Tarif
$stmtTarif = $pdo->query("SELECT * FROM tb_tarif ORDER BY tarif_per_jam ASC");
$tarifList = $stmtTarif->fetchAll();

// Cek Plat Nomor Mandiri
$checkPlat = normalizePlatNomor($_GET['plat_cek'] ?? '');
$hasilCek = null;
$cekDilakukan = false;

if (!empty($checkPlat)) {
    $cekDilakukan = true;
    $stmtCek = $pdo->prepare("
        SELECT t.id_parkir, t.kode_tiket, t.waktu_masuk, t.tarif_per_jam_saat_masuk,
               k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               a.nama_area
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        WHERE LOWER(k.plat_nomor) = LOWER(?) AND t.status = 'masuk'
        LIMIT 1
    ");
    $stmtCek->execute([$checkPlat]);
    $hasilCek = $stmtCek->fetch();
}

// Area tabs — yang pertama aktif
$activeAreaIndex = 0;
if (isset($_GET['area'])) {
    foreach ($areaList as $i => $a) {
        if ((int)$a['id_area'] === (int)$_GET['area']) {
            $activeAreaIndex = $i;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Informasi Parkir</title>
    
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <meta name="theme-color" content="#d5d0c8">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg: #c8c3ba;
            --card: #ffffff;
            --card-alt: #f7f5f2;
            --border: #e2ddd6;
            --text: #2c2a27;
            --text-mid: #6b6560;
            --text-light: #9e9890;
            --accent: #c9912b;
            --accent-light: #f5eacd;
            --accent-dark: #a47520;
            --green: #2e8b57;
            --green-light: #e8f5ee;
            --red: #c0392b;
            --red-light: #fdf0ee;
            --radius: 14px;
        }
        * { 
            font-family: 'DM Sans', system-ui, sans-serif; 
            box-sizing: border-box;
        }
        body {
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
        }

        /* ===== TOP NAVBAR ===== */
        .top-bar {
            background: rgba(180, 175, 167, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 0.55rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .top-bar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text);
        }
        .brand-icon {
            width: 34px; height: 34px;
            background: var(--text);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 1rem;
        }
        .brand-name {
            font-weight: 700;
            font-size: 1rem;
        }
        .nav-tabs-bar {
            display: flex;
            background: var(--card);
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
            border: 1px solid var(--border);
        }
        .nav-tab {
            padding: 0.4rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-mid);
            transition: all 0.15s;
            white-space: nowrap;
        }
        .nav-tab:hover {
            color: var(--text);
            background: var(--card-alt);
        }
        .nav-tab.active {
            background: var(--accent);
            color: #fff;
        }
        .top-right {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .top-right .icon-btn {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--card);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-mid);
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.15s;
        }
        .top-right .icon-btn:hover {
            background: var(--text);
            color: #fff;
            border-color: var(--text);
        }
        @media (max-width: 768px) {
            .nav-tabs-bar { display: none; }
        }

        /* ===== MAIN LAYOUT ===== */
        .main-wrap {
            max-width: 1200px;
            margin: 1rem auto;
            padding: 0 1.25rem 2rem;
        }
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1rem;
        }
        @media (max-width: 992px) {
            .grid-layout { grid-template-columns: 1fr; }
        }

        /* ===== CARDS ===== */
        .card-w {
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 1.25rem;
        }
        .card-w .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .card-w .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        .card-w .card-dots {
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.1rem;
        }

        /* ===== ZONE TABS ===== */
        .zone-tabs {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }
        .zone-tab {
            padding: 0.4rem 1.1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card);
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-mid);
            cursor: pointer;
            transition: all 0.15s;
        }
        .zone-tab:hover {
            border-color: var(--accent);
            color: var(--accent-dark);
        }
        .zone-tab.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        /* ===== SLOT GRID (DENAH) ===== */
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 6px;
        }
        .slot-cell {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            font-weight: 600;
            transition: all 0.15s;
            border: 1.5px solid transparent;
            position: relative;
        }
        .slot-cell.empty {
            background: var(--card-alt);
            border-color: var(--border);
            color: var(--text-light);
        }
        .slot-cell.occupied {
            background: #f0ece5;
            border-color: #d4cec4;
            color: var(--text);
        }
        .slot-cell .slot-icon {
            font-size: 1.3rem;
            margin-bottom: 1px;
            line-height: 1;
        }
        .slot-cell.occupied .slot-icon { color: var(--text); }
        .slot-cell.empty .slot-icon { color: #ccc; opacity: 0.5; }
        .slot-cell .slot-code {
            font-size: 0.62rem;
            letter-spacing: 0.02em;
        }
        .slot-cell.empty:hover {
            border-color: var(--green);
            background: var(--green-light);
        }

        /* ===== STAT ROW ===== */
        .stat-row {
            display: flex;
            gap: 1px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .stat-item {
            flex: 1;
            background: var(--card);
            padding: 0.9rem 0.5rem;
            text-align: center;
        }
        .stat-item .val {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.15rem;
            font-variant-numeric: tabular-nums;
        }
        .stat-item .lbl {
            font-size: 0.68rem;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* ===== SEARCH BOX ===== */
        .search-input {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 0.6rem 0.85rem;
            font-size: 0.88rem;
            width: 100%;
            background: var(--card-alt);
            color: var(--text);
            font-weight: 600;
            transition: border-color 0.15s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--card);
        }
        .search-input::placeholder {
            color: var(--text-light);
            font-weight: 400;
            text-transform: none;
        }
        .btn-accent {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 0.55rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-accent:hover {
            background: var(--accent-dark);
            color: #fff;
        }
        .btn-outline-w {
            background: var(--card);
            color: var(--text);
            border: 1.5px solid var(--border);
            padding: 0.55rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-outline-w:hover {
            border-color: var(--text);
            color: var(--text);
        }

        /* ===== TARIF CARDS ===== */
        .tarif-card {
            background: var(--card-alt);
            border-radius: 10px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border: 1px solid var(--border);
        }
        .tarif-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .tarif-card .tarif-price {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            font-variant-numeric: tabular-nums;
        }
        .tarif-card .tarif-label {
            font-size: 0.78rem;
            color: var(--text-light);
        }

        /* ===== RESULT BOXES ===== */
        .result-found {
            background: var(--green-light);
            border: 1px solid #b6dfc6;
            border-radius: 10px;
            padding: 1rem;
        }
        .result-notfound {
            background: #fef7e8;
            border: 1px solid #f0dba8;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .badge-w {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* ===== CURRENT PARKED CARD ===== */
        .current-parked {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.25rem 1rem;
            font-size: 0.82rem;
        }
        .current-parked .cp-label {
            color: var(--text-light);
            font-size: 0.72rem;
        }
        .current-parked .cp-val {
            font-weight: 700;
            color: var(--text);
        }

        /* ===== FEATURES ===== */
        .feat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
        }
        @media (max-width: 576px) {
            .feat-grid { grid-template-columns: 1fr; }
        }
        .feat-card {
            background: var(--card-alt);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.9rem;
            display: flex;
            gap: 0.7rem;
            align-items: flex-start;
        }
        .feat-icon-box {
            width: 36px; height: 36px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .feat-card h6 {
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 0.1rem;
            color: var(--text);
        }
        .feat-card p {
            font-size: 0.75rem;
            color: var(--text-mid);
            margin: 0;
            line-height: 1.45;
        }

        /* ===== FOOTER ===== */
        .site-footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.75rem;
            color: var(--text-light);
        }

        html { scroll-behavior: smooth; }

        /* ===== LEGEND ===== */
        .legend {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-mid);
            margin-top: 0.75rem;
        }
        .legend-dot {
            width: 10px; height: 10px;
            border-radius: 3px;
            display: inline-block;
            margin-right: 4px;
            vertical-align: middle;
        }

        /* Mobile nav */
        .mobile-nav {
            display: none;
            gap: 0.25rem;
            padding: 0.5rem 1.25rem;
        }
        .mobile-nav a {
            display: block;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            color: var(--text-mid);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }
        .mobile-nav a:hover { background: var(--card-alt); }
        .mobile-nav.show { display: flex; flex-direction: column; }
        @media (max-width: 768px) {
            .mob-toggle { display: flex !important; }
        }
    </style>
</head>
<body>

    <!-- ===== TOP BAR ===== -->
    <nav class="top-bar">
        <div class="top-bar-inner">
            <a class="brand" href="<?= BASE_URL ?>/welcome.php">
                <div class="brand-icon"><i class="bi bi-p-square-fill"></i></div>
                <span class="brand-name">SI Parkir</span>
            </a>

            <div class="nav-tabs-bar">
                <a href="#denah" class="nav-tab active">Denah Parkir</a>
                <a href="#cek" class="nav-tab">Cek Kendaraan</a>
                <a href="#tarif" class="nav-tab">Tarif</a>
                <a href="#info" class="nav-tab">Tentang</a>
            </div>

            <div class="top-right">
                <?php if (isLoggedIn()): ?>
                    <?php $role = $_SESSION['user_role'] ?? ''; ?>
                    <a href="<?= BASE_URL ?>/<?= $role ?>/dashboard.php" class="icon-btn" title="Dashboard">
                        <i class="bi bi-grid-fill"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/logout.php" class="icon-btn" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="icon-btn" title="Masuk" style="width: auto; border-radius: 10px; padding: 0 0.85rem; gap: 0.3rem; font-size: 0.82rem; font-weight: 600;">
                        <i class="bi bi-person"></i> Masuk
                    </a>
                <?php endif; ?>

                <button class="icon-btn mob-toggle" style="display: none;" onclick="document.getElementById('mobileNav').classList.toggle('show')">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>
        <div id="mobileNav" class="mobile-nav">
            <a href="#denah">Denah Parkir</a>
            <a href="#cek">Cek Kendaraan</a>
            <a href="#tarif">Tarif</a>
            <a href="#info">Tentang</a>
            <?php if (!isLoggedIn()): ?>
                <a href="<?= BASE_URL ?>/login.php" style="color: var(--accent); font-weight: 600;">Masuk ke Sistem</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="main-wrap">

        <!-- ===== STAT SUMMARY ===== -->
        <div class="stat-row mb-3">
            <div class="stat-item">
                <div class="val"><?= $totalKapasitas ?></div>
                <div class="lbl">Kapasitas</div>
            </div>
            <div class="stat-item">
                <div class="val" style="color: var(--red);"><?= $totalTerisi ?></div>
                <div class="lbl">Terisi</div>
            </div>
            <div class="stat-item">
                <div class="val" style="color: var(--green);"><?= $totalKosong ?></div>
                <div class="lbl">Tersedia</div>
            </div>
            <div class="stat-item">
                <div class="val"><?= $persenOkupansi ?>%</div>
                <div class="lbl">Okupansi</div>
            </div>
        </div>

        <!-- ===== MAIN GRID ===== -->
        <div class="grid-layout" id="denah">

            <!-- LEFT: DENAH PARKIR -->
            <div>
                <div class="card-w">
                    <div class="card-head">
                        <h2 class="card-title">Denah Slot Parkir</h2>
                        <span class="card-dots"><i class="bi bi-three-dots"></i></span>
                    </div>

                    <!-- Zone Tabs -->
                    <div class="zone-tabs">
                        <?php foreach ($areaList as $i => $area): ?>
                            <button class="zone-tab <?= ($i === $activeAreaIndex) ? 'active' : '' ?>" 
                                    onclick="switchZone(<?= $i ?>)">
                                <?= htmlspecialchars($area['nama_area']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slot Grids per Area -->
                    <?php foreach ($areaList as $i => $area): 
                        $idArea = (int)$area['id_area'];
                        $kap = max(1, (int)$area['kapasitas']);
                        $ter = (int)$area['terisi_aktual'];
                        $parked = $parkedByArea[$idArea] ?? [];
                        
                        if (preg_match('/Area\s+([A-Za-z0-9]+)/i', $area['nama_area'], $m)) {
                            $prefix = strtoupper($m[1]);
                        } else {
                            $prefix = strtoupper(substr(trim($area['nama_area']), 0, 1)) . ($i + 1);
                        }

                        $isMotor = (stripos($area['nama_area'], 'motor') !== false);
                    ?>
                        <div class="zone-panel" id="zone-<?= $i ?>" style="<?= ($i !== $activeAreaIndex) ? 'display:none;' : '' ?>">
                            <div class="slot-grid">
                                <?php for ($s = 1; $s <= $kap; $s++): 
                                    $occupied = ($s <= count($parked));
                                    $code = $prefix . str_pad($s, 2, '0', STR_PAD_LEFT);
                                ?>
                                    <div class="slot-cell <?= $occupied ? 'occupied' : 'empty' ?>" title="<?= $code ?> — <?= $occupied ? 'Terisi' : 'Kosong' ?>">
                                        <span class="slot-icon"><?= $isMotor ? '🏍️' : '🚗' ?></span>
                                        <span class="slot-code"><?= $code ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div class="legend">
                                <span><span class="legend-dot" style="background: #d4cec4;"></span> Terisi (<?= $ter ?>)</span>
                                <span><span class="legend-dot" style="background: var(--card-alt); border: 1px solid var(--border);"></span> Kosong (<?= max(0, $kap - $ter) ?>)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ===== CEK KENDARAAN ===== -->
                <div class="card-w mt-3" id="cek">
                    <div class="card-head">
                        <h3 class="card-title">Cek Status Kendaraan</h3>
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-mid); margin-top: -0.5rem; margin-bottom: 0.75rem;">
                        Masukkan plat nomor untuk mengecek apakah kendaraan sedang parkir.
                    </p>
                    <form method="GET" action="#cek" class="d-flex gap-2">
                        <input type="text" class="search-input text-uppercase" name="plat_cek" 
                               placeholder="Contoh: B 1234 ABC" 
                               value="<?= htmlspecialchars($checkPlat) ?>" required>
                        <button type="submit" class="btn-accent" style="white-space: nowrap;">Cek</button>
                    </form>

                    <?php if ($cekDilakukan): ?>
                        <?php if ($hasilCek): 
                            $calc = hitungDurasiDanBiaya($hasilCek['waktu_masuk'], $hasilCek['tarif_per_jam_saat_masuk']);
                            $menit = $calc['durasi_menit'];
                            $durasiHuman = ($menit >= 60) ? floor($menit / 60) . ' jam ' . ($menit % 60) . ' mnt' : $menit . ' menit';
                        ?>
                            <div class="result-found mt-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge-w" style="background: var(--green); color: #fff;">Sedang Parkir</span>
                                    <strong style="font-size: 1rem;"><?= htmlspecialchars($hasilCek['plat_nomor']) ?></strong>
                                </div>
                                <div class="current-parked">
                                    <div><div class="cp-label">Area</div><div class="cp-val"><?= htmlspecialchars($hasilCek['nama_area']) ?></div></div>
                                    <div><div class="cp-label">Masuk</div><div class="cp-val"><?= formatTanggalIndo($hasilCek['waktu_masuk'], true) ?></div></div>
                                    <div><div class="cp-label">Durasi</div><div class="cp-val"><?= $durasiHuman ?></div></div>
                                    <div><div class="cp-label">Tarif/jam</div><div class="cp-val"><?= formatRupiah($hasilCek['tarif_per_jam_saat_masuk']) ?></div></div>
                                    <div><div class="cp-label">Jenis</div><div class="cp-val"><?= ucfirst($hasilCek['jenis_kendaraan']) ?></div></div>
                                    <div>
                                        <div class="cp-label">Estimasi Biaya</div>
                                        <div class="cp-val" style="color: var(--accent-dark); font-size: 1.05rem;"><?= formatRupiah($calc['biaya_total']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="result-notfound mt-3">
                                <strong style="font-size: 0.88rem;">Tidak ditemukan</strong>
                                <p style="font-size: 0.8rem; color: var(--text-mid); margin: 0.2rem 0 0;">
                                    Plat <strong><?= htmlspecialchars($checkPlat) ?></strong> tidak terdaftar sedang parkir.
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div>
                <!-- Ringkasan Area -->
                <div class="card-w mb-3">
                    <div class="card-head">
                        <h3 class="card-title">Ringkasan Area</h3>
                    </div>
                    <?php foreach ($areaList as $area): 
                        $kap = max(1, (int)$area['kapasitas']);
                        $ter = (int)$area['terisi_aktual'];
                        $sisa = max(0, $kap - $ter);
                        $persen = min(100, round(($ter / $kap) * 100));
                        $isMotor = (stripos($area['nama_area'], 'motor') !== false);
                    ?>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-3" style="background: var(--card-alt); border: 1px solid var(--border);">
                            <span style="font-size: 1.3rem;"><?= $isMotor ? '🏍️' : '🚗' ?></span>
                            <div class="flex-grow-1">
                                <div style="font-size: 0.82rem; font-weight: 600;"><?= htmlspecialchars($area['nama_area']) ?></div>
                                <div style="display: flex; gap: 0.75rem; font-size: 0.72rem; color: var(--text-light);">
                                    <span>Kapasitas: <strong style="color: var(--text);"><?= $kap ?></strong></span>
                                    <span>Terisi: <strong style="color: var(--red);"><?= $ter ?></strong></span>
                                    <span>Sisa: <strong style="color: var(--green);"><?= $sisa ?></strong></span>
                                </div>
                                <div style="height: 4px; background: #ebe7e0; border-radius: 2px; margin-top: 4px; overflow: hidden;">
                                    <div style="height: 100%; width: <?= $persen ?>%; background: <?= $persen >= 90 ? 'var(--red)' : ($persen >= 70 ? 'var(--accent)' : 'var(--green)') ?>; border-radius: 2px;"></div>
                                </div>
                            </div>
                            <?php if ($sisa === 0): ?>
                                <span class="badge-w" style="background: var(--red-light); color: var(--red);">Penuh</span>
                            <?php else: ?>
                                <span class="badge-w" style="background: var(--green-light); color: var(--green);"><?= $sisa ?> slot</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tarif Parkir -->
                <div class="card-w mb-3" id="tarif">
                    <div class="card-head">
                        <h3 class="card-title">Tarif Parkir</h3>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($tarifList as $t): 
                            $jenis = strtolower($t['jenis_kendaraan']);
                        ?>
                            <div class="tarif-card">
                                <div class="tarif-icon" style="background: <?= $jenis === 'motor' ? '#e0f2fe' : ($jenis === 'mobil' ? '#fef3c7' : '#f3e8ff') ?>; color: <?= $jenis === 'motor' ? '#0284c7' : ($jenis === 'mobil' ? '#d97706' : '#7c3aed') ?>;">
                                    <i class="bi <?= $jenis === 'motor' ? 'bi-scooter' : ($jenis === 'mobil' ? 'bi-car-front-fill' : 'bi-truck') ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="tarif-price"><?= formatRupiah($t['tarif_per_jam']) ?><span style="font-size: 0.72rem; color: var(--text-light); font-weight: 400;"> / jam</span></div>
                                    <div class="tarif-label"><?= ucfirst($t['jenis_kendaraan']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size: 0.72rem; color: var(--text-light); margin-top: 0.6rem;">
                        Pembulatan ke atas per jam. Pembayaran tunai & non-tunai.
                    </div>
                </div>

                <!-- Tentang Sistem -->
                <div class="card-w" id="info">
                    <div class="card-head">
                        <h3 class="card-title">Tentang Sistem</h3>
                    </div>
                    <div class="feat-grid">
                        <div class="feat-card">
                            <div class="feat-icon-box" style="background: #fef3c7; color: #d97706;"><i class="bi bi-qr-code"></i></div>
                            <div>
                                <h6>Tiket QR</h6>
                                <p>Tiket masuk dengan QR code unik</p>
                            </div>
                        </div>
                        <div class="feat-card">
                            <div class="feat-icon-box" style="background: #e0f2fe; color: #0284c7;"><i class="bi bi-grid-3x3"></i></div>
                            <div>
                                <h6>Denah Live</h6>
                                <p>Monitoring slot parkir real-time</p>
                            </div>
                        </div>
                        <div class="feat-card">
                            <div class="feat-icon-box" style="background: #fce7f3; color: #db2777;"><i class="bi bi-receipt"></i></div>
                            <div>
                                <h6>Struk & Laporan</h6>
                                <p>Cetak struk dan unduh CSV</p>
                            </div>
                        </div>
                        <div class="feat-card">
                            <div class="feat-icon-box" style="background: #e8f5ee; color: #16a34a;"><i class="bi bi-shield-check"></i></div>
                            <div>
                                <h6>Keamanan</h6>
                                <p>Proteksi CSRF dan role-based access</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /grid-layout -->
    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <strong>SI Parkir</strong> &mdash; Sistem Informasi Parkir &bull; PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?> &bull; Bootstrap 5.3
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Zone tab switching
        function switchZone(index) {
            document.querySelectorAll('.zone-tab').forEach((t, i) => {
                t.classList.toggle('active', i === index);
            });
            document.querySelectorAll('.zone-panel').forEach((p, i) => {
                p.style.display = (i === index) ? '' : 'none';
            });
        }

        // Auto-refresh slot data setiap 10 detik
        setInterval(() => {
            fetch('<?= BASE_URL ?>/api/slot_publik.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;
                    // Update stat row
                    const vals = document.querySelectorAll('.stat-row .val');
                    if (vals.length >= 4) {
                        vals[0].textContent = data.total_kapasitas;
                        vals[1].textContent = data.total_terisi;
                        vals[2].textContent = data.total_kosong;
                        vals[3].textContent = (data.total_kapasitas > 0 
                            ? Math.round(data.total_terisi / data.total_kapasitas * 100) 
                            : 0) + '%';
                    }
                })
                .catch(() => {});
        }, 10000);

        // Smooth nav tab highlighting
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
