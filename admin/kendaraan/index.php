<?php
/**
 * Master Data: Manajemen Kendaraan
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Data Kendaraan';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(k.plat_nomor LIKE ? OR k.pemilik LIKE ? OR k.warna LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterJenis !== '' && in_array($filterJenis, ['motor', 'mobil', 'lainnya'], true)) {
    $whereClauses[] = "k.jenis_kendaraan = ?";
    $params[] = $filterJenis;
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan k $whereSQL");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query data kendaraan dengan informasi transaksi aktif
$query = "
    SELECT k.*, u.nama_lengkap AS didaftarkan_oleh,
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan) AS total_parkir,
           (SELECT t.id_parkir FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk' LIMIT 1) AS id_parkir_aktif
    FROM tb_kendaraan k
    JOIN tb_user u ON k.id_user = u.id_user
    $whereSQL
    ORDER BY k.id_kendaraan DESC
    LIMIT $limit OFFSET $offset
";
$stmtKendaraan = $pdo->prepare($query);
$stmtKendaraan->execute($params);
$kendaraanList = $stmtKendaraan->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Data Kendaraan</h4>
        <p class="text-muted small mb-0">Kelola master data kendaraan bermotor yang terdaftar dalam sistem</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/kendaraan/tambah.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
        <i class="bi bi-plus-circle-fill me-1"></i> Tambah Kendaraan
    </a>
</div>

<div class="card-custom p-4">
    <!-- Filter Pencarian -->
    <form method="GET" action="" class="row g-3 mb-4">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Cari plat nomor atau pemilik..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <select name="jenis" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Jenis Kendaraan</option>
                <option value="motor" <?= ($filterJenis === 'motor') ? 'selected' : '' ?>>Motor</option>
                <option value="mobil" <?= ($filterJenis === 'mobil') ? 'selected' : '' ?>>Mobil</option>
                <option value="lainnya" <?= ($filterJenis === 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>
        <div class="col-12 col-md-3 text-md-end">
            <?php if ($search !== '' || $filterJenis !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/kendaraan/index.php" class="btn btn-light border rounded-pill px-3">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabel Kendaraan -->
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Plat Nomor</th>
                    <th>Jenis Kendaraan</th>
                    <th>Warna</th>
                    <th>Nama Pemilik</th>
                    <th>Status Parkir</th>
                    <th>Total Riwayat</th>
                    <th style="width: 100px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kendaraanList)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Data kendaraan tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = $offset + 1; foreach ($kendaraanList as $k): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="plat-badge"><?= htmlspecialchars($k['plat_nomor']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 text-uppercase">
                                    <i class="bi <?= ($k['jenis_kendaraan'] === 'motor') ? 'bi-bicycle' : (($k['jenis_kendaraan'] === 'mobil') ? 'bi-car-front' : 'bi-truck') ?> me-1 text-primary"></i>
                                    <?= htmlspecialchars($k['jenis_kendaraan']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($k['warna'] ?: '-') ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($k['pemilik'] ?: 'Tamu Umum') ?></td>
                            <td>
                                <?php if ($k['id_parkir_aktif']): ?>
                                    <span class="badge-status-masuk">
                                        <i class="bi bi-p-circle-fill"></i> Sedang Parkir
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border px-2 py-1 rounded-pill">
                                        Tidak Parkir
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">
                                    <?= number_format($k['total_parkir']) ?> kali
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/admin/kendaraan/edit.php?id=<?= $k['id_kendaraan'] ?>" class="btn btn-light btn-sm border btn-action" title="Edit Data Kendaraan">
                                    <i class="bi bi-pencil-fill text-primary"></i>
                                </a>
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
            <span class="small text-muted">Menampilkan <?= count($kendaraanList) ?> dari <?= $totalRows ?> total kendaraan</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&jenis=<?= urlencode($filterJenis) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
