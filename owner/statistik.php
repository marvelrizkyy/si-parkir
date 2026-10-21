<?php
/**
 * Modul Analisis Statistik & Tren Operasional (Khusus Owner)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Statistik & Tren Bisnis';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

// 1. Tren Omset 14 Hari Terakhir
$stmt14Hari = $pdo->query("
    SELECT DATE(waktu_keluar) AS tgl, COALESCE(SUM(biaya_total), 0) AS omset
    FROM tb_transaksi
    WHERE status_pembayaran = 'lunas' AND waktu_keluar >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(waktu_keluar)
    ORDER BY DATE(waktu_keluar) ASC
");
$rawOmset14 = $stmt14Hari->fetchAll(PDO::FETCH_KEY_PAIR);

$labels14 = [];
$dataOmset14 = [];
$total14Hari = 0;
for ($i = 13; $i >= 0; $i--) {
    $tglStr = date('Y-m-d', strtotime("-$i days"));
    $labels14[] = date('d M', strtotime("-$i days"));
    $val = (float)($rawOmset14[$tglStr] ?? 0);
    $dataOmset14[] = $val;
    $total14Hari += $val;
}
$rataRataHarian = round($total14Hari / 14);

// 2. Analisis Jam Sibuk Kedatangan Kendaraan (06:00 - 22:00)
$stmtJam = $pdo->query("
    SELECT HOUR(waktu_masuk) AS jam, COUNT(*) AS volume
    FROM tb_transaksi
    GROUP BY HOUR(waktu_masuk)
    ORDER BY HOUR(waktu_masuk) ASC
");
$rawJam = $stmtJam->fetchAll(PDO::FETCH_KEY_PAIR);

$labelsJam = [];
$dataJam = [];
$maxJamVol = 0;
$jamPalingSibuk = '08:00';
for ($h = 6; $h <= 22; $h++) {
    $labelsJam[] = sprintf('%02d:00', $h);
    $vol = (int)($rawJam[$h] ?? 0);
    $dataJam[] = $vol;
    if ($vol > $maxJamVol) {
        $maxJamVol = $vol;
        $jamPalingSibuk = sprintf('%02d:00 - %02d:00', $h, $h + 1);
    }
}

// 3. Proporsi Pendapatan Berdasarkan Kategori Kendaraan
$stmtKat = $pdo->query("
    SELECT k.jenis_kendaraan, COALESCE(SUM(t.biaya_total), 0) AS pendapatan
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    WHERE t.status_pembayaran = 'lunas'
    GROUP BY k.jenis_kendaraan
");
$rawKat = $stmtKat->fetchAll(PDO::FETCH_KEY_PAIR);
$labelsKat = ['Sepeda Motor', 'Mobil', 'Lainnya'];
$dataKat = [
    (float)($rawKat['motor'] ?? 0),
    (float)($rawKat['mobil'] ?? 0),
    (float)($rawKat['lainnya'] ?? 0)
];

// 4. Perbandingan Metode Pembayaran (Tunai vs Non-Tunai)
$stmtMetode = $pdo->query("
    SELECT metode_pembayaran, COUNT(*) AS jml, COALESCE(SUM(biaya_total), 0) AS nominal
    FROM tb_transaksi
    WHERE status_pembayaran = 'lunas' AND metode_pembayaran IS NOT NULL
    GROUP BY metode_pembayaran
");
$rawMetode = $stmtMetode->fetchAll(PDO::FETCH_ASSOC);
$metodeLabels = [];
$metodeData = [];
$metodeFavorit = 'Tunai';
$maxJmlMetode = 0;
foreach ($rawMetode as $rm) {
    $metodeLabels[] = strtoupper($rm['metode_pembayaran']);
    $metodeData[] = (float)$rm['nominal'];
    if ((int)$rm['jml'] > $maxJmlMetode) {
        $maxJmlMetode = (int)$rm['jml'];
        $metodeFavorit = strtoupper($rm['metode_pembayaran']);
    }
}

// 5. Okupansi Rata-rata per Area Parkir
$stmtArea = $pdo->query("
    SELECT nama_area, kapasitas, terisi
    FROM tb_area_parkir
    ORDER BY nama_area ASC
");
$rawArea = $stmtArea->fetchAll();
$labelsArea = [];
$dataAreaKapasitas = [];
$dataAreaTerisi = [];
foreach ($rawArea as $ra) {
    $labelsArea[] = $ra['nama_area'];
    $dataAreaKapasitas[] = (int)$ra['kapasitas'];
    $dataAreaTerisi[] = (int)$ra['terisi'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Analisis Statistik & Tren Bisnis</h4>
        <p class="text-muted small mb-0">Visualisasi data omset, pola kedatangan kendaraan, dan kinerja fasilitas parkir</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/owner/laporan.php" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Buka Tabel Laporan
        </a>
    </div>
</div>

<!-- Kartu Sorotan Indikator Kunci -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-4"><?= formatRupiah($rataRataHarian) ?></div>
                <div class="stat-label">Rata-rata Omset Harian (14 Hari)</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-calculator"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-4"><?= $jamPalingSibuk ?></div>
                <div class="stat-label">Jam Puncak Kedatangan (Peak)</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-info fs-4"><?= $metodeFavorit ?></div>
                <div class="stat-label">Metode Pembayaran Terfavorit</div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-credit-card-2-front-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple fs-4"><?= formatRupiah($total14Hari) ?></div>
                <div class="stat-label">Total Omset 14 Hari Terakhir</div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-graph-up"></i>
            </div>
        </div>
    </div>
</div>

<!-- Baris Grafik 1: Omset 14 Hari & Jam Sibuk -->
<div class="row g-4 mb-4">
    <!-- Grafik 14 Hari -->
    <div class="col-12 col-lg-7">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Tren Omset Pendapatan 14 Hari Terakhir</h6>
                <span class="badge bg-light text-success border">Lunas</span>
            </div>
            <div style="position: relative; height: 280px;">
                <canvas id="chartOmset14"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Jam Sibuk -->
    <div class="col-12 col-lg-5">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Volume Kedatangan Kendaraan per Jam</h6>
                <span class="badge bg-light text-primary border">WIB</span>
            </div>
            <div style="position: relative; height: 280px;">
                <canvas id="chartJamSibuk"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Baris Grafik 2: Kategori Kendaraan, Metode Bayar, dan Okupansi Area -->
<div class="row g-4">
    <!-- Proporsi Omset Kategori Kendaraan -->
    <div class="col-12 col-lg-4">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold mb-3">Kontribusi Pendapatan per Kategori</h6>
            <div style="position: relative; height: 240px;">
                <canvas id="chartKategori"></canvas>
            </div>
        </div>
    </div>

    <!-- Metode Pembayaran -->
    <div class="col-12 col-lg-4">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold mb-3">Nominal Omset per Metode Bayar</h6>
            <div style="position: relative; height: 240px;">
                <canvas id="chartMetodeBayar"></canvas>
            </div>
        </div>
    </div>

    <!-- Okupansi per Area Parkir -->
    <div class="col-12 col-lg-4">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold mb-3">Kapasitas vs Terisi per Area Parkir</h6>
            <div style="position: relative; height: 240px;">
                <canvas id="chartAreaOkupansi"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Grafik 14 Hari
    new Chart(document.getElementById('chartOmset14').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($labels14) ?>,
            datasets: [{
                label: 'Omset (Rp)',
                data: <?= json_encode($dataOmset14) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.15)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#10b981',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (ctx) { return 'Rp ' + ctx.parsed.y.toLocaleString('id-ID'); }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function (v) { return 'Rp ' + v.toLocaleString('id-ID'); } }
                }
            }
        }
    });

    // 2. Grafik Jam Sibuk
    new Chart(document.getElementById('chartJamSibuk').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsJam) ?>,
            datasets: [{
                label: 'Kedatangan',
                data: <?= json_encode($dataJam) ?>,
                backgroundColor: 'rgba(235, 177, 70, 0.85)',
                borderColor: '#d39b33',
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // 3. Proporsi Kategori Kendaraan
    new Chart(document.getElementById('chartKategori').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labelsKat) ?>,
            datasets: [{
                data: <?= json_encode($dataKat) ?>,
                backgroundColor: ['#ebb146', '#2b3036', '#10b981']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 4. Metode Bayar
    new Chart(document.getElementById('chartMetodeBayar').getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($metodeLabels) ?>,
            datasets: [{
                data: <?= json_encode($metodeData) ?>,
                backgroundColor: ['#10b981', '#ebb146']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 5. Okupansi per Area
    new Chart(document.getElementById('chartAreaOkupansi').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsArea) ?>,
            datasets: [
                {
                    label: 'Terisi Saat Ini',
                    data: <?= json_encode($dataAreaTerisi) ?>,
                    backgroundColor: '#ef4444'
                },
                {
                    label: 'Kapasitas Total',
                    data: <?= json_encode($dataAreaKapasitas) ?>,
                    backgroundColor: '#cbd5e1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
