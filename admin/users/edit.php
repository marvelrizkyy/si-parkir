<?php
/**
 * Edit Pengguna
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Edit Pengguna';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, role, status_aktif FROM tb_user WHERE id_user = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('danger', 'Data pengguna tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/users/index.php');
    exit;
}

$error = '';
$nama = $user['nama_lengkap'];
$role = $user['role'];
$status = (int)$user['status_aktif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $newRole = $_POST['role'] ?? 'petugas';
    $newStatus = isset($_POST['status_aktif']) ? 1 : 0;

    if (empty($nama)) {
        $error = 'Nama lengkap tidak boleh kosong.';
    } elseif (!in_array($newRole, ['admin', 'petugas', 'owner'], true)) {
        $error = 'Peran tidak valid.';
    } else {
        // Cek jika akun ini adalah admin aktif terakhir
        if ($user['role'] === 'admin' && ($newRole !== 'admin' || $newStatus === 0)) {
            $stmtCekAdmin = $pdo->prepare("SELECT COUNT(*) FROM tb_user WHERE role = 'admin' AND status_aktif = 1 AND id_user != ?");
            $stmtCekAdmin->execute([$id]);
            $otherAdmins = (int)$stmtCekAdmin->fetchColumn();

            if ($otherAdmins === 0) {
                $error = 'Gagal menyimpan: Sistem harus memiliki setidaknya satu Administrator yang aktif!';
            }
        }

        if (empty($error)) {
            try {
                $stmtUpdate = $pdo->prepare("UPDATE tb_user SET nama_lengkap = ?, role = ?, status_aktif = ? WHERE id_user = ?");
                $stmtUpdate->execute([$nama, $newRole, $newStatus, $id]);

                // Jika user mengedit akunnya sendiri, perbarui data session
                if ($id === (int)$_SESSION['user_id']) {
                    $_SESSION['user_nama'] = $nama;
                    $_SESSION['user_role'] = $newRole;
                }

                recordLog("Memperbarui data pengguna @{$user['username']} (Nama: {$nama}, Role: {$newRole}, Status: {$newStatus})");
                setFlashMessage('success', "Data pengguna @{$user['username']} berhasil diperbarui.");
                header('Location: ' . BASE_URL . '/admin/users/index.php');
                exit;
            } catch (Exception $e) {
                error_log("Error update user: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem saat memperbarui data.';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Pengguna</h4>
                <p class="text-muted small mb-0">Ubah informasi akun @<?= htmlspecialchars($user['username']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-light btn-sm border rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="card-custom p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 small py-2 d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-6"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    <div class="form-text small">Username bersifat unik dan tidak dapat diubah.</div>
                </div>

                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label small fw-semibold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($nama) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label small fw-semibold text-secondary">Peran (Role) <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="petugas" <?= ($role === 'petugas') ? 'selected' : '' ?>>Petugas (Gerbang & Transaksi)</option>
                        <option value="admin" <?= ($role === 'admin') ? 'selected' : '' ?>>Admin (Kelola Master Data & Sistem)</option>
                        <option value="owner" <?= ($role === 'owner') ? 'selected' : '' ?>>Owner (Laporan & Rekapitulasi)</option>
                    </select>
                </div>

                <div class="mb-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="status_aktif" name="status_aktif" value="1" <?= ($status === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-medium" for="status_aktif">Status Akun Aktif</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
