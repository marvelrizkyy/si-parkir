<?php
/**
 * Log Aktivitas Pengguna (Admin)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Log Aktivitas Sistem';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

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

// Hitung total data log
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    $whereSQL
");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query Log Aktivitas
$query = "
    SELECT l.*, u.nama_lengkap, u.username, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    $whereSQL
    ORDER BY l.id_log DESC
    LIMIT $limit OFFSET $offset
";
$stmtLog = $pdo->prepare($query);
$stmtLog->execute($params);
$logList = $stmtLog->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Log Aktivitas Sistem</h4>
        <p class="text-muted small mb-0">Audit trail seluruh tindakan penting pengguna dan sistem parkir</p>
    </div>
</div>

<div class="card-custom p-4">
    <!-- Filter Log -->
    <form method="GET" action="" class="row g-3 mb-4">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Cari aktivitas, nama pengguna..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <input type="date" name="tgl" class="form-control" value="<?= htmlspecialchars($filterTgl) ?>" onchange="this.form.submit()">
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <?php if ($search !== '' || $filterTgl !== ''): ?>
                <a href="<?= BASE_URL ?>/admin/log/index.php" class="btn btn-light border rounded-pill px-3">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabel Log -->
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;">ID Log</th>
                    <th style="width: 200px;">Waktu Kejadian</th>
                    <th style="width: 220px;">Pelaku Aktivitas</th>
                    <th>Deskripsi Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logList)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Tidak ada log aktivitas yang cocok dengan filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logList as $l): ?>
                        <tr>
                            <td><code>#<?= $l['id_log'] ?></code></td>
                            <td class="small text-muted"><?= formatTanggalIndo($l['waktu_aktivitas']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($l['nama_lengkap']) ?></div>
                                <small class="text-muted">@<?= htmlspecialchars($l['username']) ?> &bull; <span class="badge-role-<?= $l['role'] ?> px-2 py-0 rounded text-uppercase" style="font-size:0.65rem;"><?= $l['role'] ?></span></small>
                            </td>
                            <td>
                                <span class="text-dark"><?= htmlspecialchars($l['aktivitas']) ?></span>
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
            <span class="small text-muted">Menampilkan <?= count($logList) ?> dari <?= $totalRows ?> total catatan log</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tgl=<?= urlencode($filterTgl) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
