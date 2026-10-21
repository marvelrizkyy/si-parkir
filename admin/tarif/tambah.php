<?php
/**
 * Tambah Tarif Parkir Baru
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Tambah Tarif Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$error = '';
$jenis = 'motor';
$tarif = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $tarif = (float)($_POST['tarif_per_jam'] ?? 0);

    if (!in_array($jenis, ['motor', 'mobil', 'lainnya'], true)) {
        $error = 'Jenis kendaraan tidak valid.';
    } elseif ($tarif <= 0) {
        $error = 'Tarif per jam harus bernilai lebih dari 0.';
    } else {
        try {
            $pdo = getDBConnection();
            // Cek apakah jenis kendaraan sudah ada
            $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tb_tarif WHERE jenis_kendaraan = ?");
            $stmtCek->execute([$jenis]);
            if ((int)$stmtCek->fetchColumn() > 0) {
                $error = "Tarif untuk jenis kendaraan '{$jenis}' sudah terdaftar. Silakan edit tarif yang sudah ada.";
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam) VALUES (?, ?)");
                $stmtInsert->execute([$jenis, $tarif]);

                recordLog("Menambahkan tarif parkir baru untuk {$jenis}: Rp " . number_format($tarif, 0, ',', '.'));
                setFlashMessage('success', "Tarif untuk '{$jenis}' berhasil ditambahkan.");
                header('Location: ' . BASE_URL . '/admin/tarif/index.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error tambah tarif: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat menyimpan tarif.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Tarif Parkir</h4>
                <p class="text-muted small mb-0">Tentukan tarif per jam untuk kategori kendaraan</p>
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

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="jenis_kendaraan" class="form-label small fw-semibold text-secondary">Jenis Kendaraan <span class="text-danger">*</span></label>
                    <select class="form-select" id="jenis_kendaraan" name="jenis_kendaraan" required>
                        <option value="motor" <?= ($jenis === 'motor') ? 'selected' : '' ?>>Motor (Sepeda Motor / Roda Dua)</option>
                        <option value="mobil" <?= ($jenis === 'mobil') ? 'selected' : '' ?>>Mobil (Roda Empat)</option>
                        <option value="lainnya" <?= ($jenis === 'lainnya') ? 'selected' : '' ?>>Lainnya (Truk, Bus, dsb.)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="tarif_per_jam" class="form-label small fw-semibold text-secondary">Tarif Per Jam (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted">Rp</span>
                        <input type="number" class="form-control" id="tarif_per_jam" name="tarif_per_jam" value="<?= htmlspecialchars((string)$tarif) ?>" min="500" step="500" required placeholder="Contoh: 2000">
                    </div>
                    <div class="form-text small">Tarif harus berupa angka positif dan kelipatan yang wajar.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Tarif
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
