<?php
/**
 * Modul Data Kendaraan Terdaftar (Khusus Owner - Read-Only)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Data Kendaraan';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

// Parameter Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
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

if ($filterStatus === 'parkir') {
    $whereClauses[] = "EXISTS (SELECT 1 FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk')";
} elseif ($filterStatus === 'luar') {
    $whereClauses[] = "NOT EXISTS (SELECT 1 FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk')";
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung total baris
$countQuery = "SELECT COUNT(*) FROM tb_kendaraan k $whereSQL";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query Data Kendaraan
$dataQuery = "
    SELECT k.*, 
           u.nama_lengkap AS nama_pendaftar,
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan) AS total_kunjungan,
           (SELECT a.nama_area FROM tb_transaksi t JOIN tb_area_parkir a ON t.id_area = a.id_area WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk' LIMIT 1) AS area_aktif,
           (SELECT MAX(t.waktu_masuk) FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan) AS terakhir_masuk
    FROM tb_kendaraan k
    LEFT JOIN tb_user u ON k.id_user = u.id_user
    $whereSQL
    ORDER BY k.id_kendaraan DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($dataQuery);
$stmt->execute($params);
$kendaraanList = $stmt->fetchAll();

// Detail Kendaraan jika diminta
$detailData = null;
$detailRiwayat = [];
if (isset($_GET['detail']) && (int)$_GET['detail'] > 0) {
    $detId = (int)$_GET['detail'];
    $detStmt = $pdo->prepare("
        SELECT k.*, u.nama_lengkap AS pendaftar
        FROM tb_kendaraan k
        LEFT JOIN tb_user u ON k.id_user = u.id_user
        WHERE k.id_kendaraan = ?
    ");
    $detStmt->execute([$detId]);
    $detailData = $detStmt->fetch();

    if ($detailData) {
        $trxStmt = $pdo->prepare("
            SELECT t.*, a.nama_area, u.nama_lengkap AS nama_petugas
            FROM tb_transaksi t
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            JOIN tb_user u ON t.id_user = u.id_user
            WHERE t.id_kendaraan = ?
            ORDER BY t.id_parkir DESC
            LIMIT 10
        ");
        $trxStmt->execute([$detId]);
        $detailRiwayat = $trxStmt->fetchAll();
    }
}

$queryStringExport = http_build_query([
    'type' => 'kendaraan',
    'search' => $search,
    'jenis' => $filterJenis,
    'status' => $filterStatus
]);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Direktori Data Kendaraan</h4>
        <p class="text-muted small mb-0">Pengawasan master kendaraan terdaftar, status parkir real-time, dan riwayat kunjungan</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/owner/export_csv.php?<?= $queryStringExport ?>" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor Data Kendaraan (CSV)
        </a>
    </div>
</div>

<!-- Form Filter & Pencarian -->
<div class="card-custom p-4 mb-4">
    <form method="GET" action="" class="row g-3">
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Pencarian Plat Nomor / Pemilik</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Contoh: B 1234 XYZ..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Jenis Kendaraan</label>
            <select name="jenis" class="form-select">
                <option value="">Semua Jenis</option>
                <option value="motor" <?= ($filterJenis === 'motor') ? 'selected' : '' ?>>Sepeda Motor</option>
                <option value="mobil" <?= ($filterJenis === 'mobil') ? 'selected' : '' ?>>Mobil</option>
                <option value="lainnya" <?= ($filterJenis === 'lainnya') ? 'selected' : '' ?>>Lainnya / Truk</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Status Parkir Saat Ini</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="parkir" <?= ($filterStatus === 'parkir') ? 'selected' : '' ?>>Sedang Parkir</option>
                <option value="luar" <?= ($filterStatus === 'luar') ? 'selected' : '' ?>>Di Luar Area (Tidak Parkir)</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100 rounded-pill">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if ($search !== '' || $filterJenis !== '' || $filterStatus !== ''): ?>
                <a href="<?= BASE_URL ?>/owner/kendaraan.php" class="btn btn-light border rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Modal / Panel Detail Kendaraan Read-Only -->
<?php if ($detailData): ?>
    <div class="card-custom p-4 mb-4 border-primary">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="bi bi-info-circle-fill me-2"></i> Detail Informasi Kendaraan (Audit Read-Only)
            </h5>
            <a href="<?= BASE_URL ?>/owner/kendaraan.php?<?= http_build_query($_GET) ? str_replace('detail=' . $detailData['id_kendaraan'], '', http_build_query($_GET)) : '' ?>" class="btn-close"></a>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <small class="text-muted d-block">Nomor Polisi (Plat):</small>
                <span class="plat-badge fs-6"><?= htmlspecialchars($detailData['plat_nomor']) ?></span>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block">Jenis Kendaraan:</small>
                <strong class="text-uppercase text-dark"><?= htmlspecialchars($detailData['jenis_kendaraan']) ?></strong>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block">Warna & Pemilik:</small>
                <div><?= htmlspecialchars($detailData['warna'] ?: 'Tidak Dicatat') ?> | <?= htmlspecialchars($detailData['pemilik'] ?: 'Tamu Umum') ?></div>
            </div>
            <div class="col-12 col-md-3">
                <small class="text-muted d-block">Didaftarkan Oleh:</small>
                <div><?= htmlspecialchars($detailData['pendaftar'] ?: 'Sistem Auto-Register') ?></div>
            </div>
        </div>

        <h6 class="fw-bold mb-2">Riwayat Transaksi Terakhir Kendaraan Ini (Maks. 10 Transaksi)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tiket</th>
                        <th>Area</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Durasi</th>
                        <th>Biaya</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detailRiwayat)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-2">Belum ada riwayat transaksi parkir.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detailRiwayat as $dr): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($dr['kode_tiket']) ?></code></td>
                                <td><?= htmlspecialchars($dr['nama_area']) ?></td>
                                <td class="small"><?= date('d/m/Y H:i', strtotime($dr['waktu_masuk'])) ?></td>
                                <td class="small"><?= $dr['waktu_keluar'] ? date('d/m/Y H:i', strtotime($dr['waktu_keluar'])) : '-' ?></td>
                                <td><?= $dr['durasi_jam'] ? $dr['durasi_jam'] . ' Jam' : '-' ?></td>
                                <td class="fw-bold <?= $dr['status'] === 'keluar' ? 'text-success' : 'text-muted' ?>">
                                    <?= $dr['status'] === 'keluar' ? formatRupiah($dr['biaya_total']) : '-' ?>
                                </td>
                                <td><?= strtoupper($dr['metode_pembayaran'] ?: '-') ?></td>
                                <td>
                                    <?= ($dr['status'] === 'keluar') ? '<span class="badge-status-keluar">Selesai</span>' : '<span class="badge-status-masuk">Sedang Parkir</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Tabel Master Data Kendaraan (Read-Only) -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Daftar Kendaraan Terdata (<?= number_format($totalRows) ?> Unit)</h6>
        <span class="badge bg-light text-secondary border">Akses Read-Only</span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Warna & Pemilik</th>
                    <th>Status Parkir Saat Ini</th>
                    <th>Total Kunjungan</th>
                    <th>Terakhir Berkunjung</th>
                    <th style="width: 100px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kendaraanList)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Tidak ada data kendaraan yang cocok dengan filter pencarian.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = $offset + 1; foreach ($kendaraanList as $k): 
                        $isSedangParkir = !empty($k['area_aktif']);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="plat-badge"><?= htmlspecialchars($k['plat_nomor']) ?></span></td>
                            <td>
                                <span class="badge bg-light text-dark border text-uppercase">
                                    <?php if ($k['jenis_kendaraan'] === 'motor'): ?>
                                        <i class="bi bi-bicycle me-1 text-primary"></i>
                                    <?php elseif ($k['jenis_kendaraan'] === 'mobil'): ?>
                                        <i class="bi bi-car-front me-1 text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-truck me-1 text-warning"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($k['jenis_kendaraan']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($k['pemilik'] ?: 'Tamu Umum') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($k['warna'] ?: 'Warna tidak dicatat') ?></small>
                            </td>
                            <td>
                                <?php if ($isSedangParkir): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="bi bi-p-circle-fill me-1"></i> Parkir di <?= htmlspecialchars($k['area_aktif']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i> Di Luar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                    <?= (int)$k['total_kunjungan'] ?> Kali
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= $k['terakhir_masuk'] ? date('d M Y, H:i', strtotime($k['terakhir_masuk'])) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-center">
                                <a href="?detail=<?= $k['id_kendaraan'] ?>&search=<?= urlencode($search) ?>&jenis=<?= urlencode($filterJenis) ?>&status=<?= urlencode($filterStatus) ?>&page=<?= $page ?>" 
                                   class="btn btn-light btn-sm border btn-action" title="Lihat Detail & Riwayat Transaksi">
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
            <span class="small text-muted">Menampilkan <?= count($kendaraanList) ?> dari <?= $totalRows ?> kendaraan</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&jenis=<?= urlencode($filterJenis) ?>&status=<?= urlencode($filterStatus) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
