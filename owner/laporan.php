<?php
/**
 * Laporan Rekapitulasi & Audit Pendapatan (Owner & Admin)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Laporan Pendapatan';
require_once __DIR__ . '/../includes/header.php';
requireRole(['owner', 'admin']);

$pdo = getDBConnection();

// Parameter Filter
$periode = trim($_GET['periode'] ?? '');
$searchPlat = trim($_GET['plat'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$filterStatusBayar = trim($_GET['status_bayar'] ?? '');
$filterTglMulai = trim($_GET['tgl_mulai'] ?? '');
$filterTglSelesai = trim($_GET['tgl_selesai'] ?? '');

// Penanganan Preset Periode
if ($periode === 'hari_ini') {
    $filterTglMulai = date('Y-m-d');
    $filterTglSelesai = date('Y-m-d');
} elseif ($periode === '7_hari') {
    $filterTglMulai = date('Y-m-d', strtotime('-6 days'));
    $filterTglSelesai = date('Y-m-d');
} elseif ($periode === 'bulan_ini') {
    $filterTglMulai = date('Y-m-01');
    $filterTglSelesai = date('Y-m-t');
} elseif ($periode === 'bulan_lalu') {
    $filterTglMulai = date('Y-m-01', strtotime('-1 month'));
    $filterTglSelesai = date('Y-m-t', strtotime('-1 month'));
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($searchPlat !== '') {
    $whereClauses[] = "(k.plat_nomor LIKE ? OR t.kode_tiket LIKE ?)";
    $params[] = "%$searchPlat%";
    $params[] = "%$searchPlat%";
}

if ($filterJenis !== '' && in_array($filterJenis, ['motor', 'mobil', 'lainnya'], true)) {
    $whereClauses[] = "k.jenis_kendaraan = ?";
    $params[] = $filterJenis;
}

if ($filterStatus !== '' && in_array($filterStatus, ['masuk', 'keluar'], true)) {
    $whereClauses[] = "t.status = ?";
    $params[] = $filterStatus;
}

if ($filterMetode !== '' && in_array($filterMetode, ['tunai', 'non-tunai'], true)) {
    $whereClauses[] = "t.metode_pembayaran = ?";
    $params[] = $filterMetode;
}

if ($filterStatusBayar !== '' && in_array($filterStatusBayar, ['lunas', 'belum_bayar'], true)) {
    $whereClauses[] = "t.status_pembayaran = ?";
    $params[] = $filterStatusBayar;
}

if ($filterTglMulai !== '') {
    $whereClauses[] = "DATE(t.waktu_masuk) >= ?";
    $params[] = $filterTglMulai;
}

if ($filterTglSelesai !== '') {
    $whereClauses[] = "DATE(t.waktu_masuk) <= ?";
    $params[] = $filterTglSelesai;
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung Ringkasan Data Sesuai Filter
// ATURAN MUTLAK: Pendapatan aktual HANYA menghitung pembayaran berstatus 'lunas'
$stmtSummary = $pdo->prepare("
    SELECT COUNT(*) AS total_transaksi,
           SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN 1 ELSE 0 END) AS total_lunas,
           SUM(CASE WHEN t.status_pembayaran = 'belum_bayar' OR t.status = 'masuk' THEN 1 ELSE 0 END) AS total_belum_lunas,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_pendapatan,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' AND t.metode_pembayaran = 'tunai' THEN t.biaya_total ELSE 0 END), 0) AS pendapatan_tunai,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' AND t.metode_pembayaran = 'non-tunai' THEN t.biaya_total ELSE 0 END), 0) AS pendapatan_nontunai
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    $whereSQL
");
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch();

$totalRows = (int)$summary['total_transaksi'];
$totalLunas = (int)$summary['total_lunas'];
$totalBelumLunas = (int)$summary['total_belum_lunas'];
$totalPendapatan = (float)$summary['total_pendapatan'];
$pendapatanTunai = (float)$summary['pendapatan_tunai'];
$pendapatanNonTunai = (float)$summary['pendapatan_nontunai'];

$totalPages = ceil($totalRows / $limit);

// Breakdown Pendapatan per Jenis Kendaraan Terfilter
$stmtJenis = $pdo->prepare("
    SELECT k.jenis_kendaraan, 
           COUNT(*) AS total_vol,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_uang
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    $whereSQL
    GROUP BY k.jenis_kendaraan
");
$stmtJenis->execute($params);
$jenisBreakdown = $stmtJenis->fetchAll();

// Perbandingan Antar-Periode (Bulan Ini vs Bulan Lalu)
$stmtKomparasi = $pdo->query("
    SELECT 
        (SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status_pembayaran = 'lunas' AND MONTH(waktu_masuk) = MONTH(CURDATE()) AND YEAR(waktu_masuk) = YEAR(CURDATE())) AS omset_bulan_ini,
        (SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status_pembayaran = 'lunas' AND MONTH(waktu_masuk) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(waktu_masuk) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) AS omset_bulan_lalu
");
$komparasi = $stmtKomparasi->fetch();
$omsetBulanIni = (float)$komparasi['omset_bulan_ini'];
$omsetBulanLalu = (float)$komparasi['omset_bulan_lalu'];
$selisihOmset = $omsetBulanIni - $omsetBulanLalu;
$persenPertumbuhan = $omsetBulanLalu > 0 ? round(($selisihOmset / $omsetBulanLalu) * 100, 1) : 0;

// Query Data Transaksi untuk Tabel
$queryData = "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    $whereSQL
    ORDER BY t.id_parkir DESC
    LIMIT $limit OFFSET $offset
";
$stmtData = $pdo->prepare($queryData);
$stmtData->execute($params);
$laporanList = $stmtData->fetchAll();

$queryStringExport = http_build_query([
    'type' => 'laporan',
    'plat' => $searchPlat,
    'jenis' => $filterJenis,
    'status' => $filterStatus,
    'metode' => $filterMetode,
    'status_bayar' => $filterStatusBayar,
    'tgl_mulai' => $filterTglMulai,
    'tgl_selesai' => $filterTglSelesai
]);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Laporan & Rekapitulasi Pendapatan</h4>
        <p class="text-muted small mb-0">Audit pendapatan aktual terverifikasi lunas, analisis metode pembayaran, dan ekspor CSV</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/owner/export_csv.php?<?= $queryStringExport ?>" class="btn btn-success rounded-pill px-4 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Unduh Laporan (CSV)
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Hasil Filter -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-4"><?= formatRupiah($totalPendapatan) ?></div>
                <div class="stat-label">Pendapatan Terkonfirmasi Lunas</div>
                <small class="text-muted" style="font-size: 0.72rem;"><?= number_format($totalLunas) ?> transaksi telah lunas</small>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-4"><?= formatRupiah($pendapatanTunai) ?></div>
                <div class="stat-label">Penerimaan Tunai (Cash)</div>
                <small class="text-muted" style="font-size: 0.72rem;">Uang fisik diterima di kasir</small>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-info fs-4"><?= formatRupiah($pendapatanNonTunai) ?></div>
                <div class="stat-label">Penerimaan Non-Tunai</div>
                <small class="text-muted" style="font-size: 0.72rem;">QRIS & transfer perbankan</small>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-qr-code-scan"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-warning fs-4"><?= number_format($totalBelumLunas) ?> Unit</div>
                <div class="stat-label">Belum Lunas / Sedang Parkir</div>
                <small class="text-muted" style="font-size: 0.72rem;">Tidak dihitung sebagai pendapatan</small>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Komparasi Antar-Periode & Distribusi Kategori -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-arrow-left-right text-primary me-2"></i> Perbandingan Pendapatan Antar-Periode
            </h6>
            <div class="row g-3">
                <div class="col-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted d-block">Bulan Lalu (<?= date('F Y', strtotime('-1 month')) ?>)</small>
                        <strong class="fs-5 text-secondary"><?= formatRupiah($omsetBulanLalu) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted d-block">Bulan Ini (<?= date('F Y') ?>)</small>
                        <strong class="fs-5 text-success"><?= formatRupiah($omsetBulanIni) ?></strong>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <?php if ($selisihOmset >= 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 p-2">
                        <i class="bi bi-arrow-up-right me-1"></i> Pertumbuhan +<?= $persenPertumbuhan ?>% (+<?= formatRupiah($selisihOmset) ?>) dibanding bulan lalu
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 p-2">
                        <i class="bi bi-arrow-down-right me-1"></i> Penurunan <?= $persenPertumbuhan ?>% (<?= formatRupiah($selisihOmset) ?>) dibanding bulan lalu
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card-custom p-4 h-100">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-pie-chart text-success me-2"></i> Rincian Pendapatan per Jenis Kendaraan (Terfilter)
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Volume</th>
                            <th>Total Pendapatan Lunas</th>
                            <th>Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jenisBreakdown)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-2">Tidak ada transaksi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($jenisBreakdown as $jb): 
                                $kontribusi = $totalPendapatan > 0 ? round(((float)$jb['total_uang'] / $totalPendapatan) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td class="text-uppercase fw-semibold"><?= htmlspecialchars($jb['jenis_kendaraan']) ?></td>
                                    <td><?= number_format($jb['total_vol']) ?> Unit</td>
                                    <td class="fw-bold text-success"><?= formatRupiah($jb['total_uang']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $kontribusi ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Preset Periode Cepat & Form Filter -->
<div class="card-custom p-4 mb-4">
    <!-- Preset Tab Button -->
    <div class="d-flex flex-wrap gap-2 mb-3 pb-3 border-bottom">
        <span class="small fw-bold text-muted align-self-center me-2">Preset Periode:</span>
        <a href="?periode=hari_ini" class="btn btn-sm <?= ($periode === 'hari_ini') ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">Hari Ini</a>
        <a href="?periode=7_hari" class="btn btn-sm <?= ($periode === '7_hari') ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">7 Hari Terakhir</a>
        <a href="?periode=bulan_ini" class="btn btn-sm <?= ($periode === 'bulan_ini') ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">Bulan Ini</a>
        <a href="?periode=bulan_lalu" class="btn btn-sm <?= ($periode === 'bulan_lalu') ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">Bulan Lalu</a>
        <a href="<?= BASE_URL ?>/owner/laporan.php" class="btn btn-sm <?= empty($periode) && empty($filterTglMulai) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">Semua Waktu</a>
    </div>

    <!-- Form Filter Terinci -->
    <form method="GET" action="" class="row g-3">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Pencarian Plat / Tiket</label>
            <input type="text" name="plat" class="form-control" placeholder="No. Plat / Tiket..." value="<?= htmlspecialchars($searchPlat) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Jenis Kendaraan</label>
            <select name="jenis" class="form-select">
                <option value="">Semua Jenis</option>
                <option value="motor" <?= ($filterJenis === 'motor') ? 'selected' : '' ?>>Sepeda Motor</option>
                <option value="mobil" <?= ($filterJenis === 'mobil') ? 'selected' : '' ?>>Mobil</option>
                <option value="lainnya" <?= ($filterJenis === 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Status Parkir</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="masuk" <?= ($filterStatus === 'masuk') ? 'selected' : '' ?>>Sedang Parkir</option>
                <option value="keluar" <?= ($filterStatus === 'keluar') ? 'selected' : '' ?>>Sudah Keluar</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Metode Pembayaran</label>
            <select name="metode" class="form-select">
                <option value="">Semua Metode</option>
                <option value="tunai" <?= ($filterMetode === 'tunai') ? 'selected' : '' ?>>Tunai</option>
                <option value="non-tunai" <?= ($filterMetode === 'non-tunai') ? 'selected' : '' ?>>Non-Tunai</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Rentang Tanggal Masuk</label>
            <div class="input-group">
                <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($filterTglMulai) ?>">
                <span class="input-group-text">-</span>
                <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($filterTglSelesai) ?>">
            </div>
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-funnel me-1"></i> Terapkan Filter
            </button>
            <a href="<?= BASE_URL ?>/owner/laporan.php" class="btn btn-light border rounded-pill px-3">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Tabel Laporan Rinci -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Rincian Transaksi Sesuai Filter (<?= number_format($totalRows) ?> Data)</h6>
        <span class="badge bg-light text-secondary border">Mata Uang: IDR</span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>No. Tiket</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Total Biaya</th>
                    <th>Metode</th>
                    <th>Status Bayar</th>
                    <th>Status Parkir</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporanList)): ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">Tidak ada data transaksi yang cocok dengan kriteria filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($laporanList as $l): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($l['kode_tiket']) ?></code></td>
                            <td><span class="plat-badge"><?= htmlspecialchars($l['plat_nomor']) ?></span></td>
                            <td class="text-uppercase small"><?= htmlspecialchars($l['jenis_kendaraan']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['nama_area']) ?></span></td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($l['waktu_masuk'])) ?></td>
                            <td class="small"><?= ($l['status'] === 'keluar') ? date('d/m/Y H:i', strtotime($l['waktu_keluar'])) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= ($l['status'] === 'keluar') ? $l['durasi_jam'] . ' Jam' : '-' ?></td>
                            <td class="fw-bold <?= ($l['status'] === 'keluar') ? 'text-success' : 'text-muted' ?>">
                                <?= ($l['status'] === 'keluar') ? formatRupiah($l['biaya_total']) : '-' ?>
                            </td>
                            <td>
                                <?php if (!empty($l['metode_pembayaran'])): ?>
                                    <span class="badge bg-<?= $l['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?>-subtle text-<?= $l['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?> border border-<?= $l['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?>-subtle">
                                        <?= strtoupper(htmlspecialchars($l['metode_pembayaran'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['status_pembayaran'] === 'lunas'): ?>
                                    <span class="badge bg-success">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['status'] === 'keluar'): ?>
                                    <span class="badge-status-keluar"><i class="bi bi-check2"></i> Selesai</span>
                                <?php else: ?>
                                    <span class="badge-status-masuk"><i class="bi bi-p-circle-fill"></i> Masuk</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($l['nama_petugas']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <span class="small text-muted">Menampilkan <?= count($laporanList) ?> dari <?= $totalRows ?> total transaksi</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $queryStringExport ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
