<?php
/**
 * Tambah Kendaraan Baru (Admin)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Tambah Kendaraan';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$error = '';
$plat = '';
$jenis = 'motor';
$warna = '';
$pemilik = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $plat = normalizePlatNomor($_POST['plat_nomor'] ?? '');
    $jenis = $_POST['jenis_kendaraan'] ?? 'motor';
    $warna = trim($_POST['warna'] ?? '');
    $pemilik = trim($_POST['pemilik'] ?? '');

    if (empty($plat)) {
        $error = 'Plat nomor kendaraan tidak boleh kosong.';
    } elseif (!in_array($jenis, ['motor', 'mobil', 'lainnya'], true)) {
        $error = 'Jenis kendaraan tidak valid.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Cek duplikasi plat nomor (case-insensitive)
            $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan WHERE LOWER(plat_nomor) = LOWER(?)");
            $stmtCek->execute([$plat]);
            if ((int)$stmtCek->fetchColumn() > 0) {
                $error = "Kendaraan dengan plat nomor '{$plat}' sudah terdaftar dalam sistem.";
            } else {
                $userId = (int)$_SESSION['user_id'];
                $stmtInsert = $pdo->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([$plat, $jenis, $warna ?: null, $pemilik ?: null, $userId]);

                recordLog("Mendaftarkan kendaraan baru: {$plat} ({$jenis})");
                setFlashMessage('success', "Kendaraan dengan plat nomor {$plat} berhasil didaftarkan.");
                header('Location: ' . BASE_URL . '/admin/kendaraan/index.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Error tambah kendaraan: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat menyimpan data kendaraan.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Kendaraan Baru</h4>
                <p class="text-muted small mb-0">Daftarkan data kendaraan ke dalam basis data</p>
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
                    <label for="plat_nomor" class="form-label small fw-semibold text-secondary">Plat Nomor Kendaraan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control input-plat fw-bold text-uppercase" id="plat_nomor" name="plat_nomor" value="<?= htmlspecialchars($plat) ?>" required placeholder="Contoh: B 1234 ABC" autofocus>
                    <div class="form-text small">Huruf kapital otomatis dinormalisasi.</div>
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
                    <input type="text" class="form-control" id="warna" name="warna" value="<?= htmlspecialchars($warna) ?>" placeholder="Contoh: Hitam Metalik, Putih, Merah">
                </div>

                <div class="mb-4">
                    <label for="pemilik" class="form-label small fw-semibold text-secondary">Nama Pemilik / Pengendara</label>
                    <input type="text" class="form-control" id="pemilik" name="pemilik" value="<?= htmlspecialchars($pemilik) ?>" placeholder="Contoh: Budi Santoso (opsional)">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Kendaraan
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
