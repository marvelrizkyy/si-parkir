<?php
/**
 * Dashboard Eksekutif Pemilik Usaha (Owner)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Dashboard Owner';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

// 1. Statistik Finansial & Operasional Hari Ini
$stmtHariIni = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM tb_transaksi WHERE DATE(waktu_masuk) = CURDATE()) AS masuk_hari_ini,
        (SELECT COUNT(*) FROM tb_transaksi WHERE DATE(waktu_keluar) = CURDATE() AND status = 'keluar') AS keluar_hari_ini,
        (SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk') AS sedang_parkir,
        (SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE DATE(waktu_keluar) = CURDATE() AND status_pembayaran = 'lunas') AS omset_hari_ini,
        (SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status_pembayaran = 'lunas') AS omset_total,
        (SELECT COUNT(*) FROM tb_transaksi WHERE status_pembayaran = 'lunas') AS trx_lunas,
        (SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk' OR status_pembayaran = 'belum_bayar') AS trx_belum_lunas
");
$stat = $stmtHariIni->fetch();

$masukHariIni    = (int)$stat['masuk_hari_ini'];
$keluarHariIni   = (int)$stat['keluar_hari_ini'];
$sedangParkir    = (int)$stat['sedang_parkir'];
$omsetHariIni    = (float)$stat['omset_hari_ini'];
$omsetTotal      = (float)$stat['omset_total'];
$trxLunas        = (int)$stat['trx_lunas'];
$trxBelumLunas   = (int)$stat['trx_belum_lunas'];

// 2. Data Monitoring Ringkas Seluruh Area Parkir
$stmtArea = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS aktif_parkir
    FROM tb_area_parkir a 
    ORDER BY a.nama_area ASC
");
$areaList = $stmtArea->fetchAll();

$totalKapasitasSemua = 0;
$totalTerisiSemua = 0;
foreach ($areaList as $a) {
    $totalKapasitasSemua += (int)$a['kapasitas'];
    $totalTerisiSemua += (int)$a['terisi'];
}
$sisaSlotSemua = max(0, $totalKapasitasSemua - $totalTerisiSemua);
$persenOkupansiSemua = $totalKapasitasSemua > 0 ? min(100, round(($totalTerisiSemua / $totalKapasitasSemua) * 100)) : 0;

// 3. Ringkasan Pembayaran (Tunai vs Non-Tunai)
$stmtMetode = $pdo->query("
    SELECT metode_pembayaran, COUNT(*) AS jumlah, COALESCE(SUM(biaya_total), 0) AS total_nominal
    FROM tb_transaksi
    WHERE status_pembayaran = 'lunas' AND metode_pembayaran IS NOT NULL
    GROUP BY metode_pembayaran
");
$metodeList = $stmtMetode->fetchAll(PDO::FETCH_ASSOC);
$nominalTunai = 0; $jmlTunai = 0;
$nominalNonTunai = 0; $jmlNonTunai = 0;
foreach ($metodeList as $m) {
    if ($m['metode_pembayaran'] === 'tunai') {
        $nominalTunai = (float)$m['total_nominal'];
        $jmlTunai = (int)$m['jumlah'];
    } elseif ($m['metode_pembayaran'] === 'non-tunai') {
        $nominalNonTunai = (float)$m['total_nominal'];
        $jmlNonTunai = (int)$m['jumlah'];
    }
}

// 4. Data Tren Pendapatan 7 Hari Terakhir untuk Grafik (Chart.js)
$stmtGrafik = $pdo->query("
    SELECT DATE(waktu_keluar) AS tgl, COALESCE(SUM(biaya_total), 0) AS omset
    FROM tb_transaksi
    WHERE status_pembayaran = 'lunas' AND waktu_keluar >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu_keluar)
    ORDER BY DATE(waktu_keluar) ASC
");
$rawOmset = $stmtGrafik->fetchAll(PDO::FETCH_KEY_PAIR);

// 5. Data Tren Volume Kendaraan Masuk 7 Hari Terakhir
$stmtVol = $pdo->query("
    SELECT DATE(waktu_masuk) AS tgl, COUNT(*) AS vol
    FROM tb_transaksi
    WHERE waktu_masuk >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu_masuk)
    ORDER BY DATE(waktu_masuk) ASC
");
$rawVol = $stmtVol->fetchAll(PDO::FETCH_KEY_PAIR);

$labelsChart = [];
$dataChartOmset = [];
$dataChartVol = [];
for ($i = 6; $i >= 0; $i--) {
    $tglStr = date('Y-m-d', strtotime("-$i days"));
    $tglLabel = date('d M', strtotime("-$i days"));
    $labelsChart[] = $tglLabel;
    $dataChartOmset[] = (float)($rawOmset[$tglStr] ?? 0);
    $dataChartVol[] = (int)($rawVol[$tglStr] ?? 0);
}

// 6. Distribusi Jenis Kendaraan
$stmtJenis = $pdo->query("
    SELECT jenis_kendaraan, COUNT(*) AS total
    FROM tb_kendaraan
    GROUP BY jenis_kendaraan
");
$statJenis = $stmtJenis->fetchAll(PDO::FETCH_KEY_PAIR);
$motorCount = (int)($statJenis['motor'] ?? 0);
$mobilCount = (int)($statJenis['mobil'] ?? 0);
$lainnyaCount = (int)($statJenis['lainnya'] ?? 0);
$totKendaraan = max(1, $motorCount + $mobilCount + $lainnyaCount);

// 7. Aktivitas Terbaru (Log Audit Read-Only)
$stmtLog = $pdo->query("
    SELECT l.*, u.nama_lengkap, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    ORDER BY l.id_log DESC
    LIMIT 6
");
$recentLogs = $stmtLog->fetchAll();

// 8. Transaksi Terbaru (Audit Read-Only)
$stmtTrx = $pdo->query("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    ORDER BY t.id_parkir DESC
    LIMIT 6
");
$recentTrx = $stmtTrx->fetchAll();
?>

<!-- Header Halaman -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard Pengawasan Owner</h4>
        <p class="text-muted small mb-0">Pemantauan real-time operasional parkir, pendapatan terverifikasi, dan analisis tren</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/owner/laporan.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Laporan Keuangan
        </a>
        <a href="<?= BASE_URL ?>/owner/statistik.php" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-graph-up-arrow me-1"></i> Analisis Statistik
        </a>
        <a href="<?= BASE_URL ?>/owner/export_csv.php?type=laporan" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-download me-1"></i> Ekspor CSV
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Finansial & Operasional Hari Ini -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-4"><?= formatRupiah($omsetHariIni) ?></div>
                <div class="stat-label">Pendapatan Lunas Hari Ini</div>
                <small class="text-muted" style="font-size: 0.72rem;">Total Akumulasi: <?= formatRupiah($omsetTotal) ?></small>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-4"><?= number_format($sedangParkir) ?> Unit</div>
                <div class="stat-label">Sedang Parkir Saat Ini</div>
                <small class="text-muted" style="font-size: 0.72rem;">Okupansi: <?= $persenOkupansiSemua ?>% dari <?= $totalKapasitasSemua ?> slot</small>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-p-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-info fs-4"><?= number_format($masukHariIni) ?> Masuk</div>
                <div class="stat-label">Lalu Lintas Masuk Hari Ini</div>
                <small class="text-muted" style="font-size: 0.72rem;"><?= number_format($keluarHariIni) ?> kendaraan keluar hari ini</small>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple fs-4"><?= number_format($trxLunas) ?> Lunas</div>
                <div class="stat-label">Transaksi Berstatus Lunas</div>
                <small class="text-warning fw-semibold" style="font-size: 0.72rem;"><?= number_format($trxBelumLunas) ?> transaksi aktif/berjalan</small>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-check2-all"></i>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Pendapatan Harian & Distribusi Kategori -->
<div class="row g-4 mb-4">
    <!-- Grafik Omset 7 Hari Terakhir -->
    <div class="col-12 col-lg-8">
        <div class="card-custom p-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h6 class="fw-bold mb-0">Tren Pendapatan & Volume Kendaraan (7 Hari Terakhir)</h6>
                    <small class="text-muted">Hanya menghitung transaksi dengan pembayaran lunas terverifikasi</small>
                </div>
                <span class="badge bg-light text-secondary border">Sumber Data MariaDB</span>
            </div>
            <div style="position: relative; height: 280px;">
                <canvas id="chartDashboardOwner"></canvas>
            </div>
        </div>
    </div>

    <!-- Ringkasan Metode Bayar & Kategori Kendaraan -->
    <div class="col-12 col-lg-4">
        <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <h6 class="fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-credit-card-2-front text-primary me-2"></i> Ringkasan Metode Pembayaran
                </h6>
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold text-dark"><i class="bi bi-cash me-1 text-success"></i> Tunai (Cash)</span>
                        <strong class="text-success"><?= formatRupiah($nominalTunai) ?></strong>
                    </div>
                    <small class="text-muted"><?= number_format($jmlTunai) ?> transaksi telah diselesaikan secara tunai</small>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold text-dark"><i class="bi bi-qr-code-scan me-1 text-primary"></i> Non-Tunai (QRIS/EDC)</span>
                        <strong class="text-primary"><?= formatRupiah($nominalNonTunai) ?></strong>
                    </div>
                    <small class="text-muted"><?= number_format($jmlNonTunai) ?> transaksi melalui pembayaran digital</small>
                </div>
            </div>

            <div>
                <h6 class="fw-bold mb-2 pt-2 border-top">Proporsi Jenis Kendaraan</h6>
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Motor: <?= $motorCount ?></span>
                    <span>Mobil: <?= $mobilCount ?></span>
                    <span>Lainnya: <?= $lainnyaCount ?></span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-primary" style="width: <?= round(($motorCount / $totKendaraan) * 100) ?>%;" title="Motor: <?= $motorCount ?>"></div>
                    <div class="progress-bar bg-success" style="width: <?= round(($mobilCount / $totKendaraan) * 100) ?>%;" title="Mobil: <?= $mobilCount ?>"></div>
                    <div class="progress-bar bg-warning" style="width: <?= round(($lainnyaCount / $totKendaraan) * 100) ?>%;" title="Lainnya: <?= $lainnyaCount ?>"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monitoring Okupansi Area Parkir Real-Time -->
<div class="card-custom p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h6 class="fw-bold mb-0"><i class="bi bi-signpost-2-fill text-primary me-2"></i>Status Okupansi Area Parkir Saat Ini</h6>
            <small class="text-muted">Total Slot: <?= $totalKapasitasSemua ?> | Terisi: <?= $totalTerisiSemua ?> | Tersedia: <?= $sisaSlotSemua ?> Slot</small>
        </div>
        <a href="<?= BASE_URL ?>/owner/area.php" class="btn btn-light btn-sm border rounded-pill px-3">
            Buka Monitoring Detail <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="row g-3">
        <?php foreach ($areaList as $a): 
            $kap = max(1, (int)$a['kapasitas']);
            $ter = (int)$a['terisi'];
            $sisa = max(0, $kap - $ter);
            $persen = min(100, round(($ter / $kap) * 100));
            $barColor = ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success');
            $statusBadge = ($persen >= 100) ? '<span class="badge bg-danger">PENUH</span>' : (($persen >= 80) ? '<span class="badge bg-warning text-dark">HAMPIR PENUH</span>' : '<span class="badge bg-success">TERSEDIA</span>');
        ?>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-dark text-truncate"><?= htmlspecialchars($a['nama_area']) ?></strong>
                        <?= $statusBadge ?>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Terisi: <strong class="text-dark"><?= $ter ?></strong> / <?= $kap ?></span>
                        <span>Sisa: <strong class="text-success"><?= $sisa ?></strong></span>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar <?= $barColor ?>" style="width: <?= $persen ?>%;"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                        <span class="text-muted">Okupansi: <?= $persen ?>%</span>
                        <span class="text-secondary"><?= (int)$a['aktif_parkir'] ?> Aktif</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Dua Kolom: Transaksi Terbaru & Log Aktivitas Terbaru (Audit Read-Only) -->
<div class="row g-4 mb-2">
    <!-- Transaksi Terbaru -->
    <div class="col-12 col-xl-7">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Transaksi Terbaru (Read-Only)</h6>
                <a href="<?= BASE_URL ?>/owner/transaksi.php" class="btn btn-light btn-sm border rounded-pill px-3 small">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tiket</th>
                            <th>Plat Nomor</th>
                            <th>Waktu Masuk</th>
                            <th>Biaya</th>
                            <th>Metode</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTrx)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada transaksi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTrx as $rt): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($rt['kode_tiket']) ?></code></td>
                                    <td><span class="plat-badge"><?= htmlspecialchars($rt['plat_nomor']) ?></span></td>
                                    <td class="small"><?= date('d/m H:i', strtotime($rt['waktu_masuk'])) ?></td>
                                    <td class="fw-bold <?= ($rt['status'] === 'keluar') ? 'text-success' : 'text-muted' ?>">
                                        <?= ($rt['status'] === 'keluar') ? formatRupiah($rt['biaya_total']) : '-' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($rt['metode_pembayaran'])): ?>
                                            <span class="badge bg-light text-dark border"><?= strtoupper($rt['metode_pembayaran']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rt['status'] === 'keluar'): ?>
                                            <span class="badge-status-keluar"><i class="bi bi-check2"></i> Selesai</span>
                                        <?php else: ?>
                                            <span class="badge-status-masuk"><i class="bi bi-p-circle-fill"></i> Masuk</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Log Aktivitas Terkini -->
    <div class="col-12 col-xl-5">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-secondary me-2"></i>Log Aktivitas Pengawasan</h6>
                <a href="<?= BASE_URL ?>/owner/log.php" class="btn btn-light btn-sm border rounded-pill px-3 small">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aktivitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada aktivitas tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $rl): ?>
                                <tr>
                                    <td class="small text-muted" style="white-space: nowrap;">
                                        <?= date('d/m H:i', strtotime($rl['waktu_aktivitas'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small text-truncate" style="max-width: 100px;">
                                            <?= htmlspecialchars($rl['nama_lengkap']) ?>
                                        </div>
                                        <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">
                                            <?= strtoupper($rl['role']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-dark text-break">
                                        <?= htmlspecialchars($rl['aktivitas']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartDashboardOwner').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labelsChart) ?>,
            datasets: [
                {
                    label: 'Pendapatan Lunas (Rp)',
                    data: <?= json_encode($dataChartOmset) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Volume Kendaraan Masuk (Unit)',
                    data: <?= json_encode($dataChartVol) ?>,
                    borderColor: '#ebb146',
                    backgroundColor: 'rgba(235, 177, 70, 0.08)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointBackgroundColor: '#ebb146',
                    pointRadius: 4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            if (context.datasetIndex === 0) {
                                return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                            return 'Volume: ' + context.parsed.y + ' Kendaraan';
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: function (val) {
                            return 'Rp ' + val.toLocaleString('id-ID');
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback: function (val) {
                            return val + ' unit';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
