<?php
/**
 * Tambah Area Parkir Baru
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Tambah Area Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$error = '';
$nama = '';
$kapasitas = 20;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $nama = trim($_POST['nama_area'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);

    if (empty($nama)) {
        $error = 'Nama area parkir tidak boleh kosong.';
    } elseif ($kapasitas <= 0) {
        $error = 'Kapasitas slot parkir harus lebih besar dari 0.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmtInsert = $pdo->prepare("INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (?, ?, 0)");
            $stmtInsert->execute([$nama, $kapasitas]);

            recordLog("Menambahkan area parkir baru: '{$nama}' dengan kapasitas {$kapasitas} slot.");
            setFlashMessage('success', "Area parkir '{$nama}' berhasil ditambahkan.");
            header('Location: ' . BASE_URL . '/admin/area/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Error tambah area: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat menyimpan area.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Area Parkir</h4>
                <p class="text-muted small mb-0">Daftarkan zona atau lantai parkir baru</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/area/index.php" class="btn btn-light btn-sm border rounded-pill px-3">
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
                    <label for="nama_area" class="form-label small fw-semibold text-secondary">Nama Area Parkir <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_area" name="nama_area" value="<?= htmlspecialchars($nama) ?>" required placeholder="Contoh: Area C - Mobil VIP">
                </div>

                <div class="mb-4">
                    <label for="kapasitas" class="form-label small fw-semibold text-secondary">Kapasitas Maksimal (Slot) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="kapasitas" name="kapasitas" value="<?= $kapasitas ?>" min="1" required>
                    <div class="form-text small">Jumlah slot kendaraan fisik yang dapat ditampung area ini.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Area Parkir
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
