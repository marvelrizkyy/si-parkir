<?php
/**
 * Master Data: Tarif Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Tarif Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

// Ambil data tarif beserta jumlah transaksi yang terkait
$stmt = $pdo->query("
    SELECT t.*, COUNT(tr.id_parkir) AS total_transaksi
    FROM tb_tarif t
    LEFT JOIN tb_transaksi tr ON t.id_tarif = tr.id_tarif
    GROUP BY t.id_tarif
    ORDER BY t.tarif_per_jam ASC
");
$tarifList = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pengaturan Tarif Parkir</h4>
        <p class="text-muted small mb-0">Kelola besaran tarif per jam berdasarkan jenis kendaraan</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/tarif/tambah.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
        <i class="bi bi-plus-circle-fill me-1"></i> Tambah Tarif Baru
    </a>
</div>

<div class="card-custom p-4 mb-4">
    <div class="alert alert-info border-0 rounded-3 small py-2 d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>
            <strong>Prinsip Integritas Data Transaksi:</strong> Perubahan tarif hanya berlaku untuk kendaraan yang masuk setelah tarif diperbarui. Transaksi lama tetap menggunakan tarif saat kendaraan masuk (snapshot tarif).
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Jenis Kendaraan</th>
                    <th>Tarif Per Jam</th>
                    <th>Total Transaksi Terkait</th>
                    <th style="width: 140px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tarifList)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada data tarif parkir.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($tarifList as $t): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="fw-bold text-dark text-uppercase">
                                    <i class="bi <?= ($t['jenis_kendaraan'] === 'motor') ? 'bi-bicycle' : (($t['jenis_kendaraan'] === 'mobil') ? 'bi-car-front' : 'bi-truck') ?> me-2 text-primary"></i>
                                    <?= htmlspecialchars($t['jenis_kendaraan']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 px-3 py-1 fw-bold rounded-pill">
                                    <?= formatRupiah($t['tarif_per_jam']) ?> / jam
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">
                                    <?= number_format($t['total_transaksi']) ?> transaksi
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= BASE_URL ?>/admin/tarif/edit.php?id=<?= $t['id_tarif'] ?>" class="btn btn-light btn-sm border btn-action" title="Ubah Tarif">
                                        <i class="bi bi-pencil-fill text-primary"></i>
                                    </a>
                                    <?php if ((int)$t['total_transaksi'] === 0): ?>
                                        <a href="<?= BASE_URL ?>/admin/tarif/hapus.php?id=<?= $t['id_tarif'] ?>" class="btn btn-light btn-sm border btn-action" 
                                           title="Hapus Tarif" data-confirm="Hapus data tarif untuk jenis kendaraan <?= $t['jenis_kendaraan'] ?>?">
                                            <i class="bi bi-trash-fill text-danger"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm border btn-action text-muted" title="Tidak dapat dihapus karena digunakan transaksi" disabled>
                                            <i class="bi bi-lock-fill"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
