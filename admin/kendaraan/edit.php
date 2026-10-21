<?php
/**
 * Edit Kendaraan (Admin)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Edit Kendaraan';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM tb_kendaraan WHERE id_kendaraan = ?");
$stmt->execute([$id]);
$kendaraan = $stmt->fetch();

if (!$kendaraan) {
    setFlashMessage('danger', 'Kendaraan tidak ditemukan.');
    header('Location: ' . BASE_URL . '/admin/kendaraan/index.php');
    exit;
}

$error = '';
$jenis = $kendaraan['jenis_kendaraan'];
$warna = $kendaraan['warna'] ?? '';
$pemilik = $kendaraan['pemilik'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $jenis = $_POST['jenis_kendaraan'] ?? 'motor';
    $warna = trim($_POST['warna'] ?? '');
    $pemilik = trim($_POST['pemilik'] ?? '');

    if (!in_array($jenis, ['motor', 'mobil', 'lainnya'], true)) {
        $error = 'Jenis kendaraan tidak valid.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE tb_kendaraan SET jenis_kendaraan = ?, warna = ?, pemilik = ? WHERE id_kendaraan = ?");
            $stmtUpdate->execute([$jenis, $warna ?: null, $pemilik ?: null, $id]);

            recordLog("Mengubah data kendaraan {$kendaraan['plat_nomor']} (Jenis: {$jenis}, Warna: {$warna}, Pemilik: {$pemilik})");
            setFlashMessage('success', "Data kendaraan {$kendaraan['plat_nomor']} berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/kendaraan/index.php');
            exit;
        } catch (Exception $e) {
            error_log("Error update kendaraan: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat memperbarui data kendaraan.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Data Kendaraan</h4>
                <p class="text-muted small mb-0">Ubah informasi kendaraan <?= htmlspecialchars($kendaraan['plat_nomor']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/admin/kendaraan/index.php" class="btn btn-light btn-sm border rounded-pill px-3">
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
                    <label class="form-label small fw-semibold text-secondary">Plat Nomor</label>
                    <input type="text" class="form-control bg-light fw-bold text-uppercase" value="<?= htmlspecialchars($kendaraan['plat_nomor']) ?>" disabled>
                    <div class="form-text small">Plat nomor terkunci demi menjaga konsistensi riwayat transaksi.</div>
                </div>

                <div class="mb-3">
                    <label for="jenis_kendaraan" class="form-label small fw-semibold text-secondary">Jenis Kendaraan <span class="text-danger">*</span></label>
                    <select class="form-select" id="jenis_kendaraan" name="jenis_kendaraan" required>
                        <option value="motor" <?= ($jenis === 'motor') ? 'selected' : '' ?>>Motor (Roda Dua)</option>
                        <option value="mobil" <?= ($jenis === 'mobil') ? 'selected' : '' ?>>Mobil (Roda Empat)</option>
                        <option value="lainnya" <?= ($jenis === 'lainnya') ? 'selected' : '' ?>>Lainnya (Truk, Bus, dsb.)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="warna" class="form-label small fw-semibold text-secondary">Warna Kendaraan</label>
                    <input type="text" class="form-control" id="warna" name="warna" value="<?= htmlspecialchars($warna) ?>">
                </div>

                <div class="mb-4">
                    <label for="pemilik" class="form-label small fw-semibold text-secondary">Nama Pemilik / Pengendara</label>
                    <input type="text" class="form-control" id="pemilik" name="pemilik" value="<?= htmlspecialchars($pemilik) ?>">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan Kendaraan
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
