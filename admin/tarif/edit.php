<?php
/**
 * Edit Tarif Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Edit Tarif Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id_tarif, jenis_kendaraan, tarif_per_jam FROM tb_tarif WHERE id_tarif = ?");
$stmt->execute([$id]);
$tarif = $stmt->fetch();

if (!$tarif) {
    setFlashMessage('danger', 'Tarif tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/tarif/index.php');
    exit;
}

$error = '';
$tarifPerJam = $tarif['tarif_per_jam'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $newTarif = (float)($_POST['tarif_per_jam'] ?? 0);

    if ($newTarif <= 0) {
        $error = 'Tarif per jam harus lebih dari 0.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE tb_tarif SET tarif_per_jam = ? WHERE id_tarif = ?");
            $stmtUpdate->execute([$newTarif, $id]);

            $oldFormatted = formatRupiah($tarif['tarif_per_jam']);
            $newFormatted = formatRupiah($newTarif);
            recordLog("Mengubah tarif parkir {$tarif['jenis_kendaraan']} dari {$oldFormatted} menjadi {$newFormatted} per jam.");

            setFlashMessage('success', "Tarif untuk '{$tarif['jenis_kendaraan']}' berhasil diperbarui menjadi {$newFormatted}/jam.");
            header('Location: ' . BASE_URL . '/admin/tarif/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Error update tarif: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat memperbarui tarif.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Tarif Parkir</h4>
                <p class="text-muted small mb-0">Ubah tarif per jam untuk kategori <?= htmlspecialchars($tarif['jenis_kendaraan']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/admin/tarif/index.php" class="btn btn-light btn-sm border rounded-pill px-3">
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

            <div class="alert alert-warning border-0 rounded-3 small py-2 d-flex align-items-center mb-3">
                <i class="bi bi-shield-check me-2 fs-5"></i>
                <div>
                    <strong>Pemberitahuan Sistem:</strong> Perubahan tarif ini tidak akan mengubah biaya transaksi yang sedang atau sudah berjalan.
                </div>
            </div>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Jenis Kendaraan</label>
                    <input type="text" class="form-control bg-light text-uppercase fw-bold" value="<?= htmlspecialchars($tarif['jenis_kendaraan']) ?>" disabled>
                </div>

                <div class="mb-4">
                    <label for="tarif_per_jam" class="form-label small fw-semibold text-secondary">Tarif Per Jam (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted">Rp</span>
                        <input type="number" class="form-control" id="tarif_per_jam" name="tarif_per_jam" value="<?= htmlspecialchars((string)$tarifPerJam) ?>" min="500" step="500" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan Tarif
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
