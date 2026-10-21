<?php
/**
 * Laporan Rekapitulasi Shift & Pelayanan Kasir Petugas
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 *
 * Menampilkan ringkasan transaksi pelayanan, total penerimaan uang tunai & non-tunai,
 * serta rekapitulasi shift yang siap dicetak untuk pertanggungjawaban kasir parkir.
 */

$pageTitle = 'Laporan Pelayanan Petugas';
require_once __DIR__ . '/../includes/header.php';
requireRole('petugas');

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$userNama = $_SESSION['user_nama'] ?? 'Petugas';

// Filter Parameter
$periode = trim($_GET['periode'] ?? 'hari_ini');
$filterJenis = trim($_GET['jenis'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$tglMulai = trim($_GET['tgl_mulai'] ?? date('Y-m-d'));
$tglSelesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));

if ($periode === 'hari_ini') {
    $tglMulai = date('Y-m-d');
    $tglSelesai = date('Y-m-d');
} elseif ($periode === 'kemarin') {
    $tglMulai = date('Y-m-d', strtotime('-1 day'));
    $tglSelesai = date('Y-m-d', strtotime('-1 day'));
} elseif ($periode === '7_hari') {
    $tglMulai = date('Y-m-d', strtotime('-6 days'));
    $tglSelesai = date('Y-m-d');
} elseif ($periode === 'bulan_ini') {
    $tglMulai = date('Y-m-01');
    $tglSelesai = date('Y-m-t');
}

// Query Rekapitulasi Data Transaksi Keluar / Lunas
$params = [];
$where = ["t.status = 'keluar' AND t.status_pembayaran = 'lunas'"];

// Filter Rentang Tanggal Keluar
if (!empty($tglMulai) && !empty($tglSelesai)) {
    $where[] = "DATE(t.waktu_keluar) BETWEEN ? AND ?";
    $params[] = $tglMulai;
    $params[] = $tglSelesai;
}

if (!empty($filterJenis) && in_array($filterJenis, ['motor', 'mobil', 'lainnya'], true)) {
    $where[] = "k.jenis_kendaraan = ?";
    $params[] = $filterJenis;
}

if (!empty($filterMetode) && in_array($filterMetode, ['tunai', 'non_tunai', 'non-tunai'], true)) {
    $mVal = ($filterMetode === 'non-tunai') ? 'non_tunai' : $filterMetode;
    $where[] = "t.metode_pembayaran = ?";
    $params[] = $mVal;
}

$whereSql = implode(' AND ', $where);

// 1. Agregasi Statistik Ringkasan
$stmtStat = $pdo->prepare("
    SELECT 
        COUNT(t.id_parkir) AS total_transaksi,
        COALESCE(SUM(t.biaya_total), 0) AS total_pendapatan,
        COALESCE(SUM(CASE WHEN t.metode_pembayaran = 'tunai' THEN t.biaya_total ELSE 0 END), 0) AS total_tunai,
        COALESCE(SUM(CASE WHEN t.metode_pembayaran IN ('non_tunai', 'non-tunai') THEN t.biaya_total ELSE 0 END), 0) AS total_nontunai,
        COALESCE(AVG(t.durasi_jam), 0) AS rata_durasi_jam
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    WHERE $whereSql
");
$stmtStat->execute($params);
$stat = $stmtStat->fetch();

$totalTrx = (int)($stat['total_transaksi'] ?? 0);
$totalPendapatan = (float)($stat['total_pendapatan'] ?? 0);
$totalTunai = (float)($stat['total_tunai'] ?? 0);
$totalNontunai = (float)($stat['total_nontunai'] ?? 0);
$rataDurasi = round((float)($stat['rata_durasi_jam'] ?? 0), 1);

// 2. Daftar Baris Transaksi
$stmtList = $pdo->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE $whereSql
    ORDER BY t.waktu_keluar DESC
    LIMIT 200
");
$stmtList->execute($params);
$transaksiList = $stmtList->fetchAll();
?>

<!-- Tombol Aksi Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 d-print-none">
    <div>
        <h4 class="fw-bold mb-1">Laporan Rekapitulasi Pelayanan Kasir</h4>
        <p class="text-muted small mb-0">Rekapitulasi penerimaan biaya parkir, setoran kasir, dan volume transaksi shift</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Laporan Shift
        </button>
        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-box-arrow-right me-1"></i> Ke Gerbang Keluar
        </a>
    </div>
</div>

<!-- Header Khusus Mode Cetak (Print View) -->
<div class="d-none d-print-block mb-4 text-center border-bottom pb-3">
    <h3 class="fw-bold mb-1">SI PARKIR &mdash; LAPORAN SETORAN KASIR</h3>
    <p class="mb-1 text-muted small">Periode: <?= date('d/m/Y', strtotime($tglMulai)) ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)) ?> | Petugas: <?= htmlspecialchars($userNama) ?></p>
    <small class="text-secondary">Dicetak pada: <?= date('d F Y, H:i') ?> WIB</small>
</div>

<!-- ========================================================
     KARTU RINGKASAN PENDAPATAN & SETORAN SHIFT
     ======================================================== -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-3"><?= formatRupiah($totalPendapatan) ?></div>
                <div class="stat-label">Total Penerimaan Shift</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-3"><?= formatRupiah($totalTunai) ?></div>
                <div class="stat-label">Uang Tunai (Fisik Laci)</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple fs-3"><?= formatRupiah($totalNontunai) ?></div>
                <div class="stat-label">Non-Tunai (QRIS / EDC)</div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-qr-code"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-info fs-3"><?= number_format($totalTrx) ?></div>
                <div class="stat-label">Kendaraan Keluar (Lunas)</div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-car-front-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     FILTER PERIODE & PARAMETER LAPORAN
     ======================================================== -->
<div class="card-custom p-4 mb-4 d-print-none">
    <form method="GET" action="" class="row g-3 align-items-end">
        <!-- Preset Periode Cepat -->
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary">Pilihan Periode</label>
            <select name="periode" class="form-select" onchange="this.form.submit()">
                <option value="hari_ini" <?= $periode === 'hari_ini' ? 'selected' : '' ?>>Hari Ini (<?= date('d M Y') ?>)</option>
                <option value="kemarin" <?= $periode === 'kemarin' ? 'selected' : '' ?>>Kemarin</option>
                <option value="7_hari" <?= $periode === '7_hari' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                <option value="bulan_ini" <?= $periode === 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini (<?= date('F Y') ?>)</option>
                <option value="kustom" <?= $periode === 'kustom' ? 'selected' : '' ?>>Rentang Tanggal Khusus</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tglMulai) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tglSelesai) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Metode Bayar</label>
            <select name="metode" class="form-select">
                <option value="">Semua Metode</option>
                <option value="tunai" <?= $filterMetode === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                <option value="non_tunai" <?= in_array($filterMetode, ['non_tunai', 'non-tunai']) ? 'selected' : '' ?>>Non-Tunai</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Jenis Kendaraan</label>
            <select name="jenis" class="form-select">
                <option value="">Semua Jenis</option>
                <option value="motor" <?= $filterJenis === 'motor' ? 'selected' : '' ?>>Motor</option>
                <option value="mobil" <?= $filterJenis === 'mobil' ? 'selected' : '' ?>>Mobil</option>
                <option value="lainnya" <?= $filterJenis === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>

        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                <i class="bi bi-filter"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- ========================================================
     TABEL RINCIAN TRANSAKSI LAPORAN SHIFT
     ======================================================== -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Rincian Transaksi Keluar & Pembayaran</h6>
        <span class="badge bg-light text-dark border">
            Total Baris: <?= count($transaksiList) ?> Transaksi
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Kode Tiket</th>
                    <th>Plat Nomor</th>
                    <th>Kategori</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Metode</th>
                    <th class="text-end">Total Biaya</th>
                    <th class="text-center d-print-none" style="width: 80px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksiList)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            Tidak ada transaksi keluar/lunas yang tercatat pada periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($transaksiList as $row): 
                        $metodeLabel = ($row['metode_pembayaran'] === 'tunai') 
                            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Tunai</span>' 
                            : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Non-Tunai</span>';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['kode_tiket']) ?></span></td>
                            <td><span class="plat-badge"><?= htmlspecialchars($row['plat_nomor']) ?></span></td>
                            <td><strong class="text-capitalize"><?= htmlspecialchars($row['jenis_kendaraan']) ?></strong></td>
                            <td><?= htmlspecialchars($row['nama_area']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['waktu_masuk'])) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($row['waktu_keluar'])) ?></td>
                            <td><?= (int)$row['durasi_jam'] ?> Jam</td>
                            <td><?= $metodeLabel ?></td>
                            <td class="text-end fw-bold text-success"><?= formatRupiah($row['biaya_total']) ?></td>
                            <td class="text-center d-print-none">
                                <a href="<?= BASE_URL ?>/petugas/struk.php?id=<?= $row['id_parkir'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank" title="Cetak Ulang Struk">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold bg-light">
                    <td colspan="9" class="text-end py-3">TOTAL PENERIMAAN KESELURUHAN:</td>
                    <td class="text-end py-3 text-success fs-6"><?= formatRupiah($totalPendapatan) ?></td>
                    <td class="d-print-none"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Lembar Tanda Tangan Penyerahan Kasir (Mode Print) -->
    <div class="d-none d-print-block mt-5 pt-4">
        <div class="row text-center">
            <div class="col-6">
                <p class="mb-5 text-muted">Kasir / Petugas Parkir:</p>
                <p class="fw-bold text-decoration-underline mb-0"><?= htmlspecialchars($userNama) ?></p>
                <small class="text-muted">NIP / ID: <?= $userId ?></small>
            </div>
            <div class="col-6">
                <p class="mb-5 text-muted">Diverifikasi oleh (Admin / Supervisor):</p>
                <p class="fw-bold text-decoration-underline mb-0">___________________________</p>
                <small class="text-muted">Tanda Tangan & Tanggal</small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
