<?php
/**
 * Edit Area Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Edit Area Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = ?");
$stmt->execute([$id]);
$area = $stmt->fetch();

if (!$area) {
    setFlashMessage('danger', 'Area parkir tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/area/index.php');
    exit;
}

$error = '';
$nama = $area['nama_area'];
$kapasitas = (int)$area['kapasitas'];
$terisiSaatIni = (int)$area['terisi'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $nama = trim($_POST['nama_area'] ?? '');
    $newKapasitas = (int)($_POST['kapasitas'] ?? 0);

    if (empty($nama)) {
        $error = 'Nama area tidak boleh kosong.';
    } elseif ($newKapasitas <= 0) {
        $error = 'Kapasitas harus lebih besar dari 0.';
    } elseif ($newKapasitas < $terisiSaatIni) {
        // Validasi aturan jobsheet: kapasitas tidak boleh kurang dari kendaraan aktif
        $error = "Gagal mengubah kapasitas: Kapasitas baru ({$newKapasitas} slot) tidak boleh lebih kecil dari jumlah kendaraan yang sedang parkir saat ini ({$terisiSaatIni} kendaraan).";
    } else {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE tb_area_parkir SET nama_area = ?, kapasitas = ? WHERE id_area = ?");
            $stmtUpdate->execute([$nama, $newKapasitas, $id]);

            recordLog("Mengubah data area '{$area['nama_area']}': Nama baru '{$nama}', Kapasitas {$newKapasitas} slot.");
            setFlashMessage('success', "Area parkir '{$nama}' berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/area/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Error update area: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat memperbarui area parkir.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Area Parkir</h4>
                <p class="text-muted small mb-0">Ubah konfigurasi zona <?= htmlspecialchars($area['nama_area']) ?></p>
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

            <div class="p-3 bg-light rounded-3 mb-3 small d-flex justify-content-between align-items-center border">
                <div>
                    <span class="text-muted d-block">Kendaraan Parkir Aktif:</span>
                    <strong class="text-danger fs-6"><?= $terisiSaatIni ?> Kendaraan</strong>
                </div>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                    Batas Minimal Kapasitas: <?= $terisiSaatIni ?>
                </span>
            </div>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="nama_area" class="form-label small fw-semibold text-secondary">Nama Area <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_area" name="nama_area" value="<?= htmlspecialchars($nama) ?>" required>
                </div>

                <div class="mb-4">
                    <label for="kapasitas" class="form-label small fw-semibold text-secondary">Kapasitas Maksimal (Slot) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="kapasitas" name="kapasitas" value="<?= $kapasitas ?>" min="<?= max(1, $terisiSaatIni) ?>" required>
                    <div class="form-text small">Tidak boleh kurang dari <?= $terisiSaatIni ?> slot (kendaraan yang saat ini aktif).</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan Area
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
