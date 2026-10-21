<?php
/**
 * Modul Log Aktivitas Pengawasan (Khusus Owner - Read-Only)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Log Aktivitas Pengawasan';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$filterTgl = trim($_GET['tgl'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(l.aktivitas LIKE ? OR u.nama_lengkap LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterTgl !== '') {
    $whereClauses[] = "DATE(l.waktu_aktivitas) = ?";
    $params[] = $filterTgl;
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung total baris
$countQuery = "
    SELECT COUNT(*) 
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    $whereSQL
";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query Data Log
$dataQuery = "
    SELECT l.*, u.nama_lengkap, u.username, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    $whereSQL
    ORDER BY l.id_log DESC
    LIMIT $limit OFFSET $offset
";
$stmtData = $pdo->prepare($dataQuery);
$stmtData->execute($params);
$logList = $stmtData->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Audit Trail & Log Aktivitas Sistem</h4>
        <p class="text-muted small mb-0">Pengawasan jejak aktivitas pengguna, transaksi masuk/keluar, dan perubahan sistem</p>
    </div>
    <div>
        <span class="badge bg-light text-secondary border px-3 py-2">
            <i class="bi bi-shield-lock-fill text-success me-1"></i> Mode Audit Read-Only
        </span>
    </div>
</div>

<!-- Form Filter & Pencarian Log -->
<div class="card-custom p-4 mb-4">
    <form method="GET" action="" class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold">Pencarian Aktivitas atau Nama Petugas</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Kata kunci aktivitas, nama pengguna..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">Tanggal Aktivitas</label>
            <input type="date" name="tgl" class="form-control" value="<?= htmlspecialchars($filterTgl) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100 rounded-pill">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if ($search !== '' || $filterTgl !== ''): ?>
                <a href="<?= BASE_URL ?>/owner/log.php" class="btn btn-light border rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabel Log Aktivitas (Read-Only) -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Catatan Jejak Audit Terfilter (<?= number_format($totalRows) ?> Aktivitas)</h6>
        <span class="badge bg-light text-secondary border">Sumber: tb_log_aktivitas</span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th style="width: 180px;">Waktu Kejadian</th>
                    <th style="width: 220px;">Pelaku Aktivitas</th>
                    <th>Peran</th>
                    <th>Deskripsi & Jejak Operasional</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logList)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Tidak ada log aktivitas yang cocok dengan kriteria filter.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = $offset + 1; foreach ($logList as $l): 
                        $roleColor = ($l['role'] === 'admin') ? 'danger' : (($l['role'] === 'petugas') ? 'primary' : 'success');
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="small text-muted" style="white-space: nowrap;">
                                <i class="bi bi-clock me-1 text-primary"></i>
                                <?= date('d M Y, H:i:s', strtotime($l['waktu_aktivitas'])) ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($l['nama_lengkap']) ?></div>
                                <small class="text-muted">@<?= htmlspecialchars($l['username']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $roleColor ?> bg-opacity-10 text-<?= $roleColor ?> border border-<?= $roleColor ?> border-opacity-25 px-2 py-1">
                                    <?= strtoupper($l['role']) ?>
                                </span>
                            </td>
                            <td class="text-dark small">
                                <?= htmlspecialchars($l['aktivitas']) ?>
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
            <span class="small text-muted">Menampilkan <?= count($logList) ?> dari <?= $totalRows ?> total log</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tgl=<?= urlencode($filterTgl) ?>">
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
