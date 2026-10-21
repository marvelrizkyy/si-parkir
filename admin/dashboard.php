<?php
/**
 * Dashboard Administrator
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

// 1. Ambil Statistik Kartu Utama
// Pengguna Aktif
$stmtUser = $pdo->query("SELECT COUNT(*) AS total FROM tb_user WHERE status_aktif = 1");
$totalUserAktif = (int)$stmtUser->fetchColumn();

// Kendaraan Terdaftar
$stmtKendaraan = $pdo->query("SELECT COUNT(*) AS total FROM tb_kendaraan");
$totalKendaraan = (int)$stmtKendaraan->fetchColumn();

// Kendaraan Sedang Parkir
$stmtAktif = $pdo->query("SELECT COUNT(*) AS total FROM tb_transaksi WHERE status = 'masuk'");
$totalSedangParkir = (int)$stmtAktif->fetchColumn();

// Kapasitas & Slot Parkir
$stmtArea = $pdo->query("SELECT COUNT(*) AS total_area, COALESCE(SUM(kapasitas), 0) AS total_kapasitas, COALESCE(SUM(terisi), 0) AS total_terisi FROM tb_area_parkir");
$dataArea = $stmtArea->fetch();
$totalArea = (int)$dataArea['total_area'];
$totalKapasitas = (int)$dataArea['total_kapasitas'];
$totalTerisi = (int)$dataArea['total_terisi'];
$slotTersedia = max(0, $totalKapasitas - $totalTerisi);

// Transaksi Hari Ini
$stmtHariIni = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(biaya_total), 0) AS pendapatan FROM tb_transaksi WHERE DATE(waktu_masuk) = CURDATE()");
$dataHariIni = $stmtHariIni->fetch();
$trxHariIni = (int)$dataHariIni['total'];

// Data Area Rinci untuk Progress Bar
$stmtListArea = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");
$listArea = $stmtListArea->fetchAll();

// Aktivitas Terbaru (8 terakhir)
$stmtLog = $pdo->query("
    SELECT l.*, u.nama_lengkap, u.role 
    FROM tb_log_aktivitas l 
    JOIN tb_user u ON l.id_user = u.id_user 
    ORDER BY l.waktu_aktivitas DESC 
    LIMIT 8
");
$recentLogs = $stmtLog->fetchAll();

// Data Grafik 7 Hari Terakhir (Aggregasi Asli Database)
$stmtGrafik = $pdo->query("
    SELECT DATE(waktu_masuk) AS tgl, COUNT(*) AS jml_trx
    FROM tb_transaksi
    WHERE waktu_masuk >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu_masuk)
    ORDER BY DATE(waktu_masuk) ASC
");
$rawGrafik = $stmtGrafik->fetchAll(PDO::FETCH_KEY_PAIR);

// Siapkan array 7 hari terakhir agar tanggal tanpa transaksi tetap muncul dengan nilai 0
$labelsChart = [];
$dataChart = [];
for ($i = 6; $i >= 0; $i--) {
    $tglStr = date('Y-m-d', strtotime("-$i days"));
    $tglLabel = date('d M', strtotime("-$i days"));
    $labelsChart[] = $tglLabel;
    $dataChart[] = (int)($rawGrafik[$tglStr] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard Administrator</h4>
        <p class="text-muted small mb-0">Ringkasan operasional dan pengawasan sistem informasi parkir</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="btn btn-primary btn-sm rounded-pill px-3">
            <i class="bi bi-eye-fill me-1"></i> Pantau Transaksi
        </a>
    </div>
</div>

<!-- Kartu Statistik Utama -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary"><?= number_format($totalSedangParkir) ?></div>
                <div class="stat-label">Sedang Parkir</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-p-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success"><?= number_format($slotTersedia) ?></div>
                <div class="stat-label">Slot Parkir Tersedia</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-warning"><?= number_format($trxHariIni) ?></div>
                <div class="stat-label">Transaksi Hari Ini</div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-receipt"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple"><?= number_format($totalKendaraan) ?></div>
                <div class="stat-label">Kendaraan Terdaftar</div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-car-front-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Grafik dan Kapasitas Area -->
<div class="row g-4 mb-4">
    <!-- Kolom Grafik Transaksi Harian -->
    <div class="col-12 col-lg-8">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Tren Transaksi Parkir (7 Hari Terakhir)</h6>
                <span class="badge bg-light text-secondary border">Real-time DB</span>
            </div>
            <div style="position: relative; height: 260px;">
                <canvas id="chartTransaksiAdmin"></canvas>
            </div>
        </div>
    </div>

    <!-- Kolom Status Kapasitas Area -->
    <div class="col-12 col-lg-4">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Kapasitas Area Parkir</h6>
                <a href="<?= BASE_URL ?>/admin/area/index.php" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 small" style="font-size:0.75rem;">Kelola Area</a>
            </div>

            <div class="mb-3 p-3 bg-light rounded-3 text-center border">
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block">Kapasitas Total</small>
                        <span class="fs-5 fw-bold text-dark"><?= $totalKapasitas ?> Slot</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Terisi Saat Ini</small>
                        <span class="fs-5 fw-bold text-danger"><?= $totalTerisi ?> Slot</span>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php foreach ($listArea as $area): 
                    $kap = max(1, (int)$area['kapasitas']);
                    $ter = (int)$area['terisi'];
                    $persen = min(100, round(($ter / $kap) * 100));
                    $bgClass = ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success');
                ?>
                    <div>
                        <div class="d-flex justify-content-between small fw-medium mb-1">
                            <span><?= htmlspecialchars($area['nama_area']) ?></span>
                            <span><strong><?= $ter ?></strong> / <?= $kap ?> (<?= $persen ?>%)</span>
                        </div>
                        <div class="progress" style="height: 9px;">
                            <div class="progress-bar <?= $bgClass ?>" role="progressbar" style="width: <?= $persen ?>%;" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Log Aktivitas Terbaru -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Log Aktivitas Pengguna Terbaru</h6>
        <a href="<?= BASE_URL ?>/admin/log/index.php" class="btn btn-light btn-sm border rounded-pill px-3 small">Lihat Semua Log</a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 180px;">Waktu</th>
                    <th style="width: 200px;">Pengguna</th>
                    <th>Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">Belum ada catatan aktivitas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td class="small text-muted"><?= formatTanggalIndo($log['waktu_aktivitas']) ?></td>
                            <td>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($log['nama_lengkap']) ?></span>
                                <span class="badge-role-<?= $log['role'] ?> px-2 py-0 rounded text-uppercase ms-1" style="font-size: 0.65rem;"><?= $log['role'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($log['aktivitas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartTransaksiAdmin').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsChart) ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= json_encode($dataChart) ?>,
                backgroundColor: 'rgba(235, 177, 70, 0.85)',
                borderColor: '#d39b33',
                borderWidth: 1.5,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
