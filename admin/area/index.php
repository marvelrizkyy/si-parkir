<?php
/**
 * Master Data: Area Parkir & Kapasitas
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Area & Kapasitas Parkir';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$pdo = getDBConnection();

// Handler sinkronisasi kapasitas dengan transaksi aktif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sinkron') {
    validateCSRF();
    try {
        $pdo->beginTransaction();
        $pdo->exec("
            UPDATE tb_area_parkir a 
            SET terisi = (
                SELECT COUNT(*) 
                FROM tb_transaksi t 
                WHERE t.id_area = a.id_area AND t.status = 'masuk'
            )
        ");
        $pdo->commit();
        recordLog("Melakukan sinkronisasi ulang kapasitas seluruh area parkir dengan transaksi aktif.");
        setFlash('success', 'Kapasitas seluruh area parkir berhasil disinkronkan dengan transaksi aktif.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('danger', 'Gagal menyinkronkan kapasitas: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/area/index.php');
}

$stmt = $pdo->query("
    SELECT a.*, 
           COUNT(t.id_parkir) AS total_transaksi,
           (SELECT COUNT(*) FROM tb_transaksi tk WHERE tk.id_area = a.id_area AND tk.status = 'masuk') AS terisi_aktual
    FROM tb_area_parkir a
    LEFT JOIN tb_transaksi t ON a.id_area = t.id_area
    GROUP BY a.id_area
    ORDER BY a.nama_area ASC
");
$areaList = $stmt->fetchAll();

// Cek apakah ada diskrepansi
$adaDiskrepansi = false;
foreach ($areaList as $item) {
    if ((int)$item['terisi'] !== (int)$item['terisi_aktual']) {
        $adaDiskrepansi = true;
        break;
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Area Parkir</h4>
        <p class="text-muted small mb-0">Kelola zona parkir indoor, kapasitas slot, dan validasi transaksi aktif</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Denah Slot Real-Time
        </a>
        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menyinkronkan kapasitas terisi dengan transaksi aktif?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="sinkron">
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm <?= $adaDiskrepansi ? 'btn-warning text-dark border-warning' : '' ?>" title="Sinkronkan kapasitas dengan transaksi aktif">
                <i class="bi bi-arrow-repeat me-1"></i> Sinkronkan Kapasitas
                <?php if ($adaDiskrepansi): ?>
                    <span class="badge bg-danger ms-1">Perlu Sinkron</span>
                <?php endif; ?>
            </button>
        </form>
        <a href="<?= BASE_URL ?>/admin/area/tambah.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Tambah Area Baru
        </a>
    </div>
</div>

<?php if ($adaDiskrepansi): ?>
    <div class="alert alert-warning border-warning d-flex align-items-center mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
        <div>
            <strong>Peringatan Diskrepansi Kapasitas Terdeteksi!</strong><br>
            Terdapat perbedaan antara kolom <em>terisi</em> dengan jumlah transaksi aktif (status = 'masuk'). Silakan klik tombol <strong>Sinkronkan Kapasitas</strong> di atas untuk memulihkan konsistensi data.
        </div>
    </div>
<?php endif; ?>

<div class="card-custom p-4">
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Nama Area</th>
                    <th>Kapasitas Total</th>
                    <th>Terisi Saat Ini</th>
                    <th>Transaksi Aktif</th>
                    <th>Slot Tersedia</th>
                    <th style="width: 180px;">Okupansi</th>
                    <th style="width: 140px;" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($areaList)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada data area parkir.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($areaList as $a): 
                        $kap = max(1, (int)$a['kapasitas']);
                        $ter = (int)$a['terisi'];
                        $terAktual = (int)$a['terisi_aktual'];
                        $sisa = max(0, $kap - $ter);
                        $persen = min(100, round(($ter / $kap) * 100));
                        $barClass = ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success');
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-bold text-dark">
                                <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                                <?= htmlspecialchars($a['nama_area']) ?>
                            </td>
                            <td><span class="badge bg-light text-dark border px-2 py-1 fs-6"><?= $kap ?> Slot</span></td>
                            <td>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 fs-6"><?= $ter ?> Slot</span>
                                <?php if ($ter !== $terAktual): ?>
                                    <span class="badge bg-warning text-dark ms-1" title="Perbedaan terdeteksi: riwayat aktif <?= $terAktual ?>"><i class="bi bi-exclamation-circle-fill"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fs-6"><?= $terAktual ?> Aktif</span>
                            </td>
                            <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fs-6"><?= $sisa ?> Slot</span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar <?= $barClass ?>" style="width: <?= $persen ?>%;"></div>
                                    </div>
                                    <small class="fw-bold text-secondary"><?= $persen ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= BASE_URL ?>/admin/area/edit.php?id=<?= $a['id_area'] ?>" class="btn btn-light btn-sm border btn-action" title="Edit Area">
                                        <i class="bi bi-pencil-fill text-primary"></i>
                                    </a>
                                    <?php if ($ter === 0 && (int)$a['total_transaksi'] === 0): ?>
                                        <a href="<?= BASE_URL ?>/admin/area/hapus.php?id=<?= $a['id_area'] ?>" class="btn btn-light btn-sm border btn-action" 
                                           title="Hapus Area" data-confirm="Hapus area '<?= htmlspecialchars($a['nama_area']) ?>'?">
                                            <i class="bi bi-trash-fill text-danger"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm border btn-action text-muted" title="Tidak dapat dihapus karena memiliki riwayat atau kendaraan aktif" disabled>
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
