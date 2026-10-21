<?php
/**
 * Tambah Pengguna Baru
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Tambah Pengguna';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$error = '';
$nama = '';
$username = '';
$role = 'petugas';
$status = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'petugas';
    $status = isset($_POST['status_aktif']) ? 1 : 0;

    // Validasi
    if (empty($nama) || empty($username) || empty($password)) {
        $error = 'Seluruh kolom wajib diisi.';
    } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $error = 'Username hanya boleh terdiri dari 3-30 karakter huruf kecil, angka, atau underscore.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal harus 6 karakter.';
    } elseif (!in_array($role, ['admin', 'petugas', 'owner'], true)) {
        $error = 'Peran yang dipilih tidak valid.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Cek keunikan username
            $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tb_user WHERE username = ?");
            $stmtCek->execute([$username]);
            if ((int)$stmtCek->fetchColumn() > 0) {
                $error = "Username @{$username} sudah digunakan. Silakan gunakan username lain.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([$nama, $username, $hash, $role, $status]);

                recordLog("Menambahkan akun pengguna baru: @{$username} (Peran: {$role})");
                setFlashMessage('success', "Akun pengguna @{$username} berhasil dibuat.");
                header('Location: ' . BASE_URL . '/admin/users/index.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error tambah user: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat menyimpan data pengguna.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Pengguna Baru</h4>
                <p class="text-muted small mb-0">Lengkapi formulir untuk membuat akun baru</p>
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
                    <label for="nama_lengkap" class="form-label small fw-semibold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($nama) ?>" required placeholder="Contoh: Muhammad Fikri">
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required placeholder="Contoh: fikri_petugas">
                    <div class="form-text small">Huruf kecil, angka, atau underscore (3-30 karakter).</div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Minimal 6 karakter">
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
                    <label class="form-check-label small fw-medium" for="status_aktif">Akun Langsung Aktif</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Pengguna
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
