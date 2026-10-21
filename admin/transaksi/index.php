<?php
/**
 * Pengawasan Transaksi Parkir (Admin)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Data Transaksi Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

// Parameter Filter
$searchPlat = trim($_GET['plat'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$filterTglMulai = trim($_GET['tgl_mulai'] ?? '');
$filterTglSelesai = trim($_GET['tgl_selesai'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($searchPlat !== '') {
    $whereClauses[] = "(k.plat_nomor LIKE ? OR t.kode_tiket LIKE ?)";
    $params[] = "%$searchPlat%";
    $params[] = "%$searchPlat%";
}

if ($filterStatus !== '' && in_array($filterStatus, ['masuk', 'keluar'], true)) {
    $whereClauses[] = "t.status = ?";
    $params[] = $filterStatus;
}

if ($filterMetode !== '' && in_array($filterMetode, ['tunai', 'non_tunai'], true)) {
    $whereClauses[] = "t.metode_pembayaran = ?";
    $params[] = $filterMetode;
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

// Hitung total data
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tb_transaksi t 
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan 
    $whereSQL
");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query Data Transaksi Lengkap
$query = "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    $whereSQL
    ORDER BY t.id_parkir DESC
    LIMIT $limit OFFSET $offset
";
$stmtTrx = $pdo->prepare($query);
$stmtTrx->execute($params);
$transaksiList = $stmtTrx->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pengawasan Transaksi Parkir</h4>
        <p class="text-muted small mb-0">Audit dan pemantauan data transaksi keluar masuk kendaraan</p>
    </div>
</div>

<div class="card-custom p-4 mb-4">
    <!-- Filter Pencarian -->
    <form method="GET" action="" class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-secondary">Cari Plat / Kode Tiket</label>
            <input type="text" name="plat" class="form-control" placeholder="Contoh: B 1234 ABC" value="<?= htmlspecialchars($searchPlat) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Status Parkir</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="masuk" <?= ($filterStatus === 'masuk') ? 'selected' : '' ?>>Sedang Parkir</option>
                <option value="keluar" <?= ($filterStatus === 'keluar') ? 'selected' : '' ?>>Sudah Keluar</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Metode Bayar</label>
            <select name="metode" class="form-select">
                <option value="">Semua Metode</option>
                <option value="tunai" <?= ($filterMetode === 'tunai') ? 'selected' : '' ?>>Tunai (Cash)</option>
                <option value="non_tunai" <?= ($filterMetode === 'non_tunai') ? 'selected' : '' ?>>Non-Tunai</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($filterTglMulai) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-secondary">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($filterTglSelesai) ?>">
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end gap-1">
            <button type="submit" class="btn btn-primary w-100" title="Filter"><i class="bi bi-filter"></i></button>
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="btn btn-light border" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
        </div>
    </form>

    <!-- Tabel Data Transaksi -->
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>No. Tiket</th>
                    <th>Plat Nomor</th>
                    <th>Area & Jenis</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi & Pembayaran</th>
                    <th>Petugas</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksiList)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Data transaksi tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transaksiList as $t): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($t['kode_tiket']) ?></code></td>
                            <td><span class="plat-badge"><?= htmlspecialchars($t['plat_nomor']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['nama_area']) ?></div>
                                <small class="text-muted text-uppercase"><?= htmlspecialchars($t['jenis_kendaraan']) ?></small>
                            </td>
                            <td class="small"><?= formatTanggalIndo($t['waktu_masuk']) ?></td>
                            <td class="small"><?= ($t['status'] === 'keluar') ? formatTanggalIndo($t['waktu_keluar']) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <?php if ($t['status'] === 'keluar'): ?>
                                    <div class="fw-bold text-success"><?= formatRupiah($t['biaya_total']) ?></div>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        <span class="badge <?= ($t['metode_pembayaran'] === 'tunai') ? 'bg-success' : 'bg-primary' ?> bg-opacity-10 text-<?= ($t['metode_pembayaran'] === 'tunai') ? 'success' : 'primary' ?> border px-2 py-0" style="font-size:0.68rem;">
                                            <?= strtoupper($t['metode_pembayaran'] ?? 'tunai') ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.72rem;"><?= $t['durasi_jam'] ?> Jam</small>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Sedang dihitung</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="fw-medium text-dark"><?= htmlspecialchars($t['nama_petugas']) ?></small></td>
                            <td>
                                <?php if ($t['status'] === 'masuk'): ?>
                                    <span class="badge-status-masuk"><i class="bi bi-p-circle-fill"></i> Masuk</span>
                                <?php else: ?>
                                    <span class="badge-status-keluar"><i class="bi bi-check2-circle"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($t['status'] === 'masuk'): ?>
                                    <a href="<?= BASE_URL ?>/petugas/tiket.php?id=<?= $t['id_parkir'] ?>" class="btn btn-light btn-sm border btn-action" title="Lihat Tiket QR" target="_blank">
                                        <i class="bi bi-qr-code text-primary"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/petugas/struk.php?id=<?= $t['id_parkir'] ?>" class="btn btn-light btn-sm border btn-action" title="Lihat Struk Thermal" target="_blank">
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
            <span class="small text-muted">Menampilkan <?= count($transaksiList) ?> dari <?= $totalRows ?> total transaksi</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&plat=<?= urlencode($searchPlat) ?>&status=<?= urlencode($filterStatus) ?>&tgl_mulai=<?= urlencode($filterTglMulai) ?>&tgl_selesai=<?= urlencode($filterTglSelesai) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
