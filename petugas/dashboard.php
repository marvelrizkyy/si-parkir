<?php
/**
 * Dashboard Petugas Gerbang Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Dashboard Petugas';
require_once __DIR__ . '/../includes/header.php';
requireRole('petugas');

$pdo = getDBConnection();

// 1. Ambil Statistik Operasional Petugas
// Total Sedang Parkir
$stmtAktif = $pdo->query("SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'");
$totalSedangParkir = (int)$stmtAktif->fetchColumn();

// Transaksi Masuk Hari Ini
$stmtMasukHariIni = $pdo->query("SELECT COUNT(*) FROM tb_transaksi WHERE DATE(waktu_masuk) = CURDATE()");
$trxMasukHariIni = (int)$stmtMasukHariIni->fetchColumn();

// Transaksi Keluar Hari Ini
$stmtKeluarHariIni = $pdo->query("SELECT COUNT(*) FROM tb_transaksi WHERE status = 'keluar' AND DATE(waktu_keluar) = CURDATE()");
$trxKeluarHariIni = (int)$stmtKeluarHariIni->fetchColumn();

// Kapasitas Tiap Area
$stmtArea = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");
$areaList = $stmtArea->fetchAll();

// Daftar Kendaraan yang Masih Parkir (Maksimal 10 terbaru)
$stmtListAktif = $pdo->query("
    SELECT t.id_parkir, t.kode_tiket, t.waktu_masuk, t.tarif_per_jam_saat_masuk,
           k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk DESC
    LIMIT 10
");
$kendaraanAktif = $stmtListAktif->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard Operasional Petugas</h4>
        <p class="text-muted small mb-0">Layanan gerbang masuk, keluar (kasir & scan QR), laporan shift, dan monitoring slot</p>
    </div>
    <!-- Tombol Aksi Cepat Operasional -->
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/petugas/transaksi_masuk.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Kendaraan Masuk
        </a>
        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-success rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-box-arrow-right me-1"></i> Kendaraan Keluar (Scan & Kasir)
        </a>
        <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Monitoring Slot Parkir
        </a>
        <a href="<?= BASE_URL ?>/petugas/laporan.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-bar-graph-fill me-1"></i> Laporan
        </a>
        <a href="<?= BASE_URL ?>/petugas/riwayat.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-clock-history me-1"></i> Riwayat
        </a>
    </div>
</div>

<!-- Kartu Statistik Petugas -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary"><?= number_format($totalSedangParkir) ?></div>
                <div class="stat-label">Sedang Parkir Saat Ini</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-p-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-info"><?= number_format($trxMasukHariIni) ?></div>
                <div class="stat-label">Transaksi Masuk Hari Ini</div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success"><?= number_format($trxKeluarHariIni) ?></div>
                <div class="stat-label">Transaksi Keluar Hari Ini</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-box-arrow-right"></i>
            </div>
        </div>
    </div>
</div>

<!-- Baris Informasi Slot Area & Pencarian Cepat Plat -->
<div class="row g-4 mb-4">
    <!-- Slot Area Parkir -->
    <div class="col-12 col-lg-7">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-signpost-2-fill text-primary me-2"></i>Ketersediaan Slot Parkir</h6>
                <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Buka Denah Slot
                </a>
            </div>
            <div class="row g-3">
                <?php foreach ($areaList as $area): 
                    $kap = max(1, (int)$area['kapasitas']);
                    $ter = (int)$area['terisi'];
                    $sisa = max(0, $kap - $ter);
                    $persen = min(100, round(($ter / $kap) * 100));
                    $isFull = ($sisa === 0);
                ?>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 border rounded-3 bg-light position-relative">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($area['nama_area']) ?></h6>
                                <?php if ($isFull): ?>
                                    <span class="badge bg-danger rounded-pill">PENUH</span>
                                <?php else: ?>
                                    <span class="badge bg-success rounded-pill"><?= $sisa ?> Slot Kosong</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Terisi: <strong><?= $ter ?></strong> / <?= $kap ?></span>
                                <span><?= $persen ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success') ?>" 
                                     style="width: <?= $persen ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Pencarian Cepat Plat Nomor & Kasir Keluar -->
    <div class="col-12 col-lg-5">
        <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <h6 class="fw-bold mb-2"><i class="bi bi-search text-primary me-2"></i>Pencarian Cepat & Kasir Keluar</h6>
                <p class="text-muted small mb-3">Cek status parkir atau langsung hitung tagihan keluar dengan plat nomor / tiket</p>
                <form action="<?= BASE_URL ?>/petugas/transaksi_keluar.php" method="GET">
                    <div class="input-group mb-3">
                        <input type="text" name="kode" class="form-control input-plat text-uppercase fw-bold" placeholder="Masukkan plat atau kode tiket..." required>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-calculator me-1"></i> Hitung Biaya</button>
                    </div>
                </form>
            </div>
            <div class="p-3 bg-light rounded-3 small text-muted border">
                <i class="bi bi-info-circle text-primary me-1"></i>
                Bila kendaraan mau keluar, arahkan scan QR tiket di pintu kasir untuk langsung memunculkan total biaya yang harus dibayar.
            </div>
        </div>
    </div>
</div>

<!-- Tabel Kendaraan yang Masih Parkir -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-car-front-fill text-primary me-2"></i>Kendaraan yang Sedang Parkir</h6>
        <a href="<?= BASE_URL ?>/petugas/riwayat.php?status=masuk" class="btn btn-light btn-sm border rounded-pill px-3 small">Lihat Semua (<?= $totalSedangParkir ?>)</a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Plat Nomor</th>
                    <th>Jenis & Pemilik</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Durasi Berjalan</th>
                    <th>No. Tiket</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kendaraanAktif)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Saat ini tidak ada kendaraan yang sedang parkir.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kendaraanAktif as $k): 
                        $hitung = hitungDurasiDanBiaya($k['waktu_masuk'], $k['tarif_per_jam_saat_masuk']);
                    ?>
                        <tr>
                            <td><span class="plat-badge"><?= htmlspecialchars($k['plat_nomor']) ?></span></td>
                            <td>
                                <span class="fw-semibold text-dark text-uppercase"><?= htmlspecialchars($k['jenis_kendaraan']) ?></span>
                                <small class="text-muted d-block"><?= htmlspecialchars($k['pemilik'] ?: 'Tamu Umum') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($k['nama_area']) ?></span></td>
                            <td class="small"><?= formatTanggalIndo($k['waktu_masuk']) ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <i class="bi bi-stopwatch me-1"></i> <?= $hitung['durasi_menit'] ?> Menit (<?= $hitung['durasi_jam'] ?> Jam)
                                </span>
                            </td>
                            <td><code><?= htmlspecialchars($k['kode_tiket']) ?></code></td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php?id=<?= $k['id_parkir'] ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                                    <i class="bi bi-box-arrow-right me-1"></i> Keluar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
