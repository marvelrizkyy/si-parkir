<?php
/**
 * Master Data: Manajemen Pengguna
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Kelola Pengguna';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

// Parameter Pencarian & Pagination
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$whereClause = "";

if ($search !== '') {
    $whereClause = "WHERE (nama_lengkap LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Hitung Total Data
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tb_user $whereClause");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Ambil Data
$stmtUsers = $pdo->prepare("SELECT id_user, nama_lengkap, username, role, status_aktif FROM tb_user $whereClause ORDER BY id_user DESC LIMIT $limit OFFSET $offset");
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Pengguna</h4>
        <p class="text-muted small mb-0">Kelola akun administrator, petugas gerbang, dan owner</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users/tambah.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
    </a>
</div>

<div class="card-custom p-4">
    <!-- Filter Pencarian -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-4">
            <form method="GET" action="" class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Cari nama atau username..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabel Data Pengguna -->
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Peran (Role)</th>
                    <th>Status</th>
                    <th style="width: 170px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Data pengguna tidak ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = $offset + 1; foreach ($users as $u): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                            <td><code>@<?= htmlspecialchars($u['username']) ?></code></td>
                            <td>
                                <span class="badge-role-<?= $u['role'] ?> px-2 py-1 rounded text-uppercase small">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$u['status_aktif'] === 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                        <i class="bi bi-x-circle-fill me-1"></i> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $u['id_user'] ?>" class="btn btn-light btn-sm border btn-action" title="Edit Data">
                                        <i class="bi bi-pencil-fill text-primary"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/users/reset_password.php?id=<?= $u['id_user'] ?>" class="btn btn-light btn-sm border btn-action" title="Atur Ulang Password">
                                        <i class="bi bi-key-fill text-warning"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/users/toggle.php?id=<?= $u['id_user'] ?>" class="btn btn-light btn-sm border btn-action" 
                                       title="<?= ((int)$u['status_aktif'] === 1) ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>"
                                       data-confirm="Ubah status aktif akun @<?= htmlspecialchars($u['username']) ?>?">
                                        <i class="bi bi-power <?= ((int)$u['status_aktif'] === 1) ? 'text-danger' : 'text-success' ?>"></i>
                                    </a>
                                </div>
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
            <span class="small text-muted">Menampilkan <?= count($users) ?> dari <?= $totalRows ?> total pengguna</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
