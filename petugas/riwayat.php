<?php
/**
 * Riwayat Transaksi Pelayanan Parkir (Petugas)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Riwayat Transaksi';
require_once __DIR__ . '/../includes/header.php';
requireRole('petugas');

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$filterTgl = trim($_GET['tgl'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(k.plat_nomor LIKE ? OR t.kode_tiket LIKE ? OR k.pemilik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterTgl !== '') {
    $whereClauses[] = "DATE(t.waktu_masuk) = ?";
    $params[] = $filterTgl;
}

if ($filterStatus !== '' && in_array($filterStatus, ['masuk', 'keluar'], true)) {
    $whereClauses[] = "t.status = ?";
    $params[] = $filterStatus;
}

if ($filterMetode !== '') {
    $normMetode = ($filterMetode === 'non_tunai') ? 'non-tunai' : $filterMetode;
    if (in_array($normMetode, ['tunai', 'non-tunai'], true)) {
        $whereClauses[] = "t.metode_pembayaran = ?";
        $params[] = $normMetode;
    }
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung Total Data
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tb_transaksi t 
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan 
    $whereSQL
");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query Riwayat
$query = "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik,
           a.nama_area, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    $whereSQL
    ORDER BY t.id_parkir DESC
    LIMIT $limit OFFSET $offset
";
$stmtRiwayat = $pdo->prepare($query);
$stmtRiwayat->execute($params);
$riwayatList = $stmtRiwayat->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Riwayat Pelayanan Parkir</h4>
        <p class="text-muted small mb-0">Daftar transaksi parkir yang ditangani oleh petugas operasional</p>
    </div>
</div>

<div class="card-custom p-4">
    <!-- Filter Pencarian -->
    <form method="GET" action="" class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Cari plat, tiket..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <input type="date" name="tgl" class="form-control" value="<?= htmlspecialchars($filterTgl) ?>" onchange="this.form.submit()">
        </div>
        <div class="col-12 col-md-2">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="masuk" <?= ($filterStatus === 'masuk') ? 'selected' : '' ?>>Sedang Parkir</option>
                <option value="keluar" <?= ($filterStatus === 'keluar') ? 'selected' : '' ?>>Sudah Keluar</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <select name="metode" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Metode</option>
                <option value="tunai" <?= ($filterMetode === 'tunai') ? 'selected' : '' ?>>Tunai</option>
                <option value="non-tunai" <?= ($filterMetode === 'non-tunai' || $filterMetode === 'non_tunai') ? 'selected' : '' ?>>Non-Tunai</option>
            </select>
        </div>
        <div class="col-12 col-md-2 text-md-end">
            <?php if ($search !== '' || $filterTgl !== '' || $filterStatus !== '' || $filterMetode !== ''): ?>
                <a href="<?= BASE_URL ?>/petugas/riwayat.php" class="btn btn-light border rounded-pill px-3">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabel Riwayat -->
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Plat Nomor</th>
                    <th>Tiket & Jenis</th>
                    <th>Area Parkir</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi & Pembayaran</th>
                    <th>Status</th>
                    <th class="text-center" style="width: 120px;">Cetak Ulang</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayatList)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Data riwayat parkir tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayatList as $r): ?>
                        <tr>
                            <td><span class="plat-badge"><?= htmlspecialchars($r['plat_nomor']) ?></span></td>
                            <td>
                                <code><?= htmlspecialchars($r['kode_tiket']) ?></code>
                                <small class="text-muted d-block text-uppercase"><?= htmlspecialchars($r['jenis_kendaraan']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['nama_area']) ?></span></td>
                            <td class="small"><?= formatTanggalIndo($r['waktu_masuk']) ?></td>
                            <td class="small"><?= ($r['status'] === 'keluar') ? formatTanggalIndo($r['waktu_keluar']) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <?php if ($r['status'] === 'keluar'): ?>
                                    <div class="fw-bold text-success"><?= formatRupiah($r['biaya_total']) ?></div>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        <span class="badge <?= ($r['metode_pembayaran'] === 'tunai') ? 'bg-success' : 'bg-primary' ?> bg-opacity-10 text-<?= ($r['metode_pembayaran'] === 'tunai') ? 'success' : 'primary' ?> border px-2 py-0" style="font-size:0.68rem;">
                                            <?= strtoupper($r['metode_pembayaran'] ?? 'tunai') ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.72rem;"><?= $r['durasi_jam'] ?> Jam</small>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Sedang berlangsung</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'masuk'): ?>
                                    <span class="badge-status-masuk"><i class="bi bi-p-circle-fill"></i> Masuk</span>
                                <?php else: ?>
                                    <span class="badge-status-keluar"><i class="bi bi-check2-circle"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'masuk'): ?>
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="<?= BASE_URL ?>/petugas/tiket.php?id=<?= $r['id_parkir'] ?>" class="btn btn-light btn-sm border btn-action" title="Cetak Ulang Tiket" target="_blank">
                                            <i class="bi bi-qr-code text-primary"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php?id=<?= $r['id_parkir'] ?>" class="btn btn-outline-success btn-sm border btn-action" title="Proses Keluar & Hitung Tagihan">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/petugas/struk.php?id=<?= $r['id_parkir'] ?>" class="btn btn-light btn-sm border btn-action" title="Cetak Ulang Struk" target="_blank">
                                        <i class="bi bi-printer-fill text-success"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <span class="small text-muted">Menampilkan <?= count($riwayatList) ?> dari <?= $totalRows ?> total riwayat</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tgl=<?= urlencode($filterTgl) ?>&status=<?= urlencode($filterStatus) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
