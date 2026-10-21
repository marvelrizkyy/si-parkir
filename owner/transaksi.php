<?php
/**
 * Modul Pengawasan Riwayat Transaksi (Khusus Owner - Read-Only)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Riwayat Transaksi';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

// Parameter Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$filterArea = (int)($_GET['area'] ?? 0);
$filterJenis = trim($_GET['jenis'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$filterStatusBayar = trim($_GET['status_bayar'] ?? '');
$filterTglMulai = trim($_GET['tgl_mulai'] ?? '');
$filterTglSelesai = trim($_GET['tgl_selesai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(t.kode_tiket LIKE ? OR k.plat_nomor LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterArea > 0) {
    $whereClauses[] = "t.id_area = ?";
    $params[] = $filterArea;
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

// Hitung total baris & agregat terfilter
$aggQuery = "
    SELECT COUNT(*) AS total_rows,
           COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_omset_lunas,
           COUNT(CASE WHEN t.status = 'masuk' THEN 1 END) AS total_aktif
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    $whereSQL
";
$aggStmt = $pdo->prepare($aggQuery);
$aggStmt->execute($params);
$agg = $aggStmt->fetch();

$totalRows = (int)$agg['total_rows'];
$totalOmsetLunas = (float)$agg['total_omset_lunas'];
$totalAktif = (int)$agg['total_aktif'];
$totalPages = ceil($totalRows / $limit);

// Query Daftar Transaksi
$dataQuery = "
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
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$trxList = $stmt->fetchAll();

// Ambil data detail jika dipilih
$detailTrx = null;
if (isset($_GET['detail_id']) && (int)$_GET['detail_id'] > 0) {
    $detId = (int)$_GET['detail_id'];
    $detStmt = $pdo->prepare("
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               a.nama_area, u.nama_lengkap AS nama_petugas
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        JOIN tb_user u ON t.id_user = u.id_user
        WHERE t.id_parkir = ?
    ");
    $detStmt->execute([$detId]);
    $detailTrx = $detStmt->fetch();
}

// Master Area untuk Filter Dropdown
$areaOptions = $pdo->query("SELECT id_area, nama_area FROM tb_area_parkir ORDER BY nama_area ASC")->fetchAll();

$queryExport = http_build_query([
    'type' => 'transaksi',
    'search' => $search,
    'area' => $filterArea,
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
        <h4 class="fw-bold mb-1">Riwayat Transaksi Parkir</h4>
        <p class="text-muted small mb-0">Audit pengawasan log transaksi, durasi, perhitungan biaya, dan pelunasan kasir</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/owner/export_csv.php?<?= $queryExport ?>" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor Riwayat (CSV)
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Filter Terkini -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card-custom p-3 bg-white">
            <div class="text-muted small">Total Transaksi Cocok</div>
            <div class="fs-4 fw-bold text-dark"><?= number_format($totalRows) ?> Data</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card-custom p-3 bg-white">
            <div class="text-muted small">Pendapatan Lunas (Terfilter)</div>
            <div class="fs-4 fw-bold text-success"><?= formatRupiah($totalOmsetLunas) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card-custom p-3 bg-white">
            <div class="text-muted small">Kendaraan Masih Parkir</div>
            <div class="fs-4 fw-bold text-primary"><?= number_format($totalAktif) ?> Unit</div>
        </div>
    </div>
</div>

<!-- Form Filter & Pencarian Lengkap -->
<div class="card-custom p-4 mb-4">
    <form method="GET" action="" class="row g-3">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Pencarian Tiket / Plat</label>
            <input type="text" name="search" class="form-control" placeholder="Tiket / Plat Nomor..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Area Parkir</label>
            <select name="area" class="form-select">
                <option value="0">Semua Area</option>
                <?php foreach ($areaOptions as $ao): ?>
                    <option value="<?= $ao['id_area'] ?>" <?= ($filterArea === (int)$ao['id_area']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ao['nama_area']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Jenis Kendaraan</label>
            <select name="jenis" class="form-select">
                <option value="">Semua Jenis</option>
                <option value="motor" <?= ($filterJenis === 'motor') ? 'selected' : '' ?>>Motor</option>
                <option value="mobil" <?= ($filterJenis === 'mobil') ? 'selected' : '' ?>>Mobil</option>
                <option value="lainnya" <?= ($filterJenis === 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Status Parkir</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="masuk" <?= ($filterStatus === 'masuk') ? 'selected' : '' ?>>Masuk (Aktif)</option>
                <option value="keluar" <?= ($filterStatus === 'keluar') ? 'selected' : '' ?>>Keluar (Selesai)</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Metode & Status Bayar</label>
            <div class="input-group">
                <select name="metode" class="form-select">
                    <option value="">Metode</option>
                    <option value="tunai" <?= ($filterMetode === 'tunai') ? 'selected' : '' ?>>Tunai</option>
                    <option value="non-tunai" <?= ($filterMetode === 'non-tunai') ? 'selected' : '' ?>>Non-Tunai</option>
                </select>
                <select name="status_bayar" class="form-select">
                    <option value="">Bayar</option>
                    <option value="lunas" <?= ($filterStatusBayar === 'lunas') ? 'selected' : '' ?>>Lunas</option>
                    <option value="belum_bayar" <?= ($filterStatusBayar === 'belum_bayar') ? 'selected' : '' ?>>Belum</option>
                </select>
            </div>
        </div>

        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Dari Tanggal Masuk</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($filterTglMulai) ?>">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($filterTglSelesai) ?>">
        </div>
        <div class="col-12 col-md-6 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-funnel me-1"></i> Terapkan Filter
            </button>
            <a href="<?= BASE_URL ?>/owner/transaksi.php" class="btn btn-light border rounded-pill px-3">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Modal Rincian Detail Transaksi Read-Only -->
<?php if ($detailTrx): ?>
    <div class="card-custom p-4 mb-4 border-primary">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="bi bi-receipt-cutoff me-2"></i> Rincian Transaksi: <?= htmlspecialchars($detailTrx['kode_tiket']) ?> (Audit Read-Only)
            </h5>
            <a href="<?= BASE_URL ?>/owner/transaksi.php?<?= http_build_query(array_diff_key($_GET, ['detail_id' => ''])) ?>" class="btn-close"></a>
        </div>
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-3">
                <small class="text-muted d-block">Nomor Polisi</small>
                <span class="plat-badge fs-6"><?= htmlspecialchars($detailTrx['plat_nomor']) ?></span>
                <div class="text-uppercase small text-muted mt-1"><?= htmlspecialchars($detailTrx['jenis_kendaraan']) ?> | <?= htmlspecialchars($detailTrx['warna'] ?: '-') ?></div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <small class="text-muted d-block">Area Parkir</small>
                <div class="fw-bold text-dark"><?= htmlspecialchars($detailTrx['nama_area']) ?></div>
                <small class="text-muted">Petugas: <?= htmlspecialchars($detailTrx['nama_petugas']) ?></small>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <small class="text-muted d-block">Waktu Parkir</small>
                <div class="small">Masuk: <strong><?= formatTanggalIndo($detailTrx['waktu_masuk']) ?></strong></div>
                <div class="small">Keluar: <strong><?= $detailTrx['waktu_keluar'] ? formatTanggalIndo($detailTrx['waktu_keluar']) : '<span class="text-muted">-</span>' ?></strong></div>
                <div class="small">Durasi: <strong><?= $detailTrx['durasi_jam'] ? $detailTrx['durasi_jam'] . ' Jam' : '-' ?></strong></div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <small class="text-muted d-block">Kalkulasi Tarif & Pembayaran</small>
                <div class="small">Tarif/Jam: <?= formatRupiah($detailTrx['tarif_per_jam_saat_masuk']) ?></div>
                <div class="fw-bold text-success fs-5"><?= $detailTrx['biaya_total'] ? formatRupiah($detailTrx['biaya_total']) : '-' ?></div>
                <div class="small">
                    Metode: <strong><?= strtoupper($detailTrx['metode_pembayaran'] ?: '-') ?></strong> |
                    Status: <span class="badge bg-<?= $detailTrx['status_pembayaran'] === 'lunas' ? 'success' : 'warning text-dark' ?>"><?= strtoupper($detailTrx['status_pembayaran'] ?: 'BELUM') ?></span>
                </div>
                <?php if ($detailTrx['metode_pembayaran'] === 'tunai' && $detailTrx['jumlah_bayar']): ?>
                    <div class="small text-muted mt-1">
                        Diterima: <?= formatRupiah($detailTrx['jumlah_bayar']) ?> | Kembali: <?= formatRupiah($detailTrx['kembalian']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tabel Data Transaksi (Read-Only) -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Daftar Transaksi Terfilter (<?= number_format($totalRows) ?> Transaksi)</h6>
        <span class="badge bg-light text-secondary border">Akses Pengawasan Read-Only</span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>No. Tiket</th>
                    <th>Plat Nomor</th>
                    <th>Area & Jenis</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Total Biaya</th>
                    <th>Metode</th>
                    <th>Status Bayar</th>
                    <th>Status Parkir</th>
                    <th style="width: 80px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trxList)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Tidak ada data transaksi yang cocok dengan kriteria filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trxList as $t): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($t['kode_tiket']) ?></code></td>
                            <td><span class="plat-badge"><?= htmlspecialchars($t['plat_nomor']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['nama_area']) ?></div>
                                <small class="text-muted text-uppercase"><?= htmlspecialchars($t['jenis_kendaraan']) ?></small>
                            </td>
                            <td class="small"><?= date('d/m/Y H:i', strtotime($t['waktu_masuk'])) ?></td>
                            <td class="small"><?= ($t['status'] === 'keluar') ? date('d/m/Y H:i', strtotime($t['waktu_keluar'])) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= ($t['status'] === 'keluar') ? $t['durasi_jam'] . ' Jam' : '-' ?></td>
                            <td class="fw-bold <?= ($t['status'] === 'keluar') ? 'text-success' : 'text-muted' ?>">
                                <?= ($t['status'] === 'keluar') ? formatRupiah($t['biaya_total']) : '-' ?>
                            </td>
                            <td>
                                <?php if (!empty($t['metode_pembayaran'])): ?>
                                    <span class="badge bg-<?= $t['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?>-subtle text-<?= $t['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?> border border-<?= $t['metode_pembayaran'] === 'tunai' ? 'success' : 'primary' ?>-subtle">
                                        <?= strtoupper(htmlspecialchars($t['metode_pembayaran'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['status_pembayaran'] === 'lunas'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Belum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['status'] === 'keluar'): ?>
                                    <span class="badge-status-keluar"><i class="bi bi-check2"></i> Selesai</span>
                                <?php else: ?>
                                    <span class="badge-status-masuk"><i class="bi bi-p-circle-fill"></i> Masuk</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="?detail_id=<?= $t['id_parkir'] ?>&<?= $queryExport ?>&page=<?= $page ?>" 
                                   class="btn btn-light btn-sm border btn-action" title="Lihat Detail Transaksi">
                                    <i class="bi bi-eye-fill text-primary"></i>
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
            <span class="small text-muted">Menampilkan <?= count($trxList) ?> dari <?= $totalRows ?> total transaksi</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $queryExport ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
