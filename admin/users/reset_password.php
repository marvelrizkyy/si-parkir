<?php
/**
 * Atur Ulang Password Pengguna
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Atur Ulang Password';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username FROM tb_user WHERE id_user = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('danger', 'Pengguna tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/users/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $error = 'Password baru minimal harus 6 karakter.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE tb_user SET password = ? WHERE id_user = ?");
            $stmtUpdate->execute([$hash, $id]);

            recordLog("Mengatur ulang password akun pengguna: @{$user['username']}");
            setFlashMessage('success', "Password untuk akun @{$user['username']} berhasil diatur ulang.");
            header('Location: ' . BASE_URL . '/admin/users/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Error reset password: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat memperbarui password.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Atur Ulang Password</h4>
                <p class="text-muted small mb-0">Perbarui kata sandi akun @<?= htmlspecialchars($user['username']) ?></p>
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

            <div class="p-3 bg-light rounded-3 mb-3 small">
                <div class="fw-semibold text-dark"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                <div class="text-muted">Username: <code>@<?= htmlspecialchars($user['username']) ?></code></div>
            </div>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="new_password" class="form-label small fw-semibold text-secondary">Password Baru <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Minimal 6 karakter" autofocus>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label small fw-semibold text-secondary">Ulangi Password Baru <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ketik ulang password">
                </div>

                <button type="submit" class="btn btn-warning w-100 py-2 rounded-3 fw-semibold text-dark">
                    <i class="bi bi-key-fill me-1"></i> Simpan Password Baru
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
