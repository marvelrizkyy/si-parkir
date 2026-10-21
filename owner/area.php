<?php
/**
 * Modul Monitoring Area Parkir (Khusus Owner - Read-Only)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

$pageTitle = 'Monitoring Area Parkir';
require_once __DIR__ . '/../includes/header.php';
requireRole('owner');

$pdo = getDBConnection();

$stmt = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS aktif_parkir,
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area) AS total_riwayat
    FROM tb_area_parkir a
    ORDER BY a.nama_area ASC
");
$areaList = $stmt->fetchAll();

$totalKapasitas = 0;
$totalTerisi = 0;
$totalAktif = 0;
foreach ($areaList as $a) {
    $totalKapasitas += (int)$a['kapasitas'];
    $totalTerisi += (int)$a['terisi'];
    $totalAktif += (int)$a['aktif_parkir'];
}
$totalSisa = max(0, $totalKapasitas - $totalTerisi);
$persenRataRata = $totalKapasitas > 0 ? min(100, round(($totalTerisi / $totalKapasitas) * 100)) : 0;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Monitoring Kapasitas & Area Parkir</h4>
        <p class="text-muted small mb-0">Pengawasan kapasitas slot indoor, ketersediaan sisa slot, dan tingkat okupansi real-time</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/petugas/monitoring_slot.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Buka Denah Slot Real-Time
        </a>
        <a href="<?= BASE_URL ?>/owner/export_csv.php?type=area" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor Rekap Area (CSV)
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Kapasitas Keseluruhan -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-3"><?= number_format($totalKapasitas) ?> Slot</div>
                <div class="stat-label">Total Kapasitas Bangunan</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-building"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-danger fs-3"><?= number_format($totalTerisi) ?> Slot</div>
                <div class="stat-label">Slot Terisi Saat Ini</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-car-front-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-3"><?= number_format($totalSisa) ?> Slot</div>
                <div class="stat-label">Slot Kosong / Tersedia</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple fs-3"><?= $persenRataRata ?>%</div>
                <div class="stat-label">Rata-rata Okupansi Fasilitas</div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-pie-chart-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Visual Grid per Area Parkir -->
<div class="row g-4 mb-4">
    <?php foreach ($areaList as $a): 
        $kap = max(1, (int)$a['kapasitas']);
        $ter = (int)$a['terisi'];
        $sisa = max(0, $kap - $ter);
        $persen = min(100, round(($ter / $kap) * 100));
        $barColor = ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success');
        $statusBadge = ($persen >= 100) ? '<span class="badge bg-danger px-3 py-1">PENUH</span>' : (($persen >= 80) ? '<span class="badge bg-warning text-dark px-3 py-1">HAMPIR PENUH</span>' : '<span class="badge bg-success px-3 py-1">TERSEDIA</span>');
    ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card-custom p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-geo-alt-fill text-primary me-2"></i><?= htmlspecialchars($a['nama_area']) ?>
                    </h5>
                    <?= $statusBadge ?>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center border">
                            <small class="text-muted d-block">Slot Terisi</small>
                            <span class="fs-4 fw-bold text-danger"><?= $ter ?></span>
                            <small class="text-muted">/ <?= $kap ?></small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded text-center border">
                            <small class="text-muted d-block">Slot Tersedia</small>
                            <span class="fs-4 fw-bold text-success"><?= $sisa ?></span>
                            <small class="text-muted">Slot</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold text-muted mb-1">
                        <span>Tingkat Okupansi</span>
                        <span class="text-dark"><?= $persen ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?= $barColor ?>" style="width: <?= $persen ?>%;"></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between pt-2 border-top small text-muted">
                    <span>Transaksi Masuk Aktif:</span>
                    <strong class="text-dark"><?= (int)$a['aktif_parkir'] ?> Kendaraan</strong>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabel Ringkasan Area Parkir (Read-Only) -->
<div class="card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Tabel Rincian Kapasitas Area</h6>
        <span class="badge bg-light text-secondary border">Audit Pengawasan</span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Area</th>
                    <th>Kapasitas Total</th>
                    <th>Terisi Saat Ini</th>
                    <th>Slot Tersedia</th>
                    <th>Transaksi Aktif</th>
                    <th>Total Riwayat Pelayanan</th>
                    <th style="width: 180px;">Tingkat Okupansi</th>
                    <th style="width: 120px;" class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($areaList)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data area parkir.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($areaList as $a): 
                        $kap = max(1, (int)$a['kapasitas']);
                        $ter = (int)$a['terisi'];
                        $sisa = max(0, $kap - $ter);
                        $persen = min(100, round(($ter / $kap) * 100));
                        $barColor = ($persen >= 90) ? 'bg-danger' : (($persen >= 70) ? 'bg-warning' : 'bg-success');
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-bold text-dark">
                                <i class="bi bi-signpost-fill text-primary me-2"></i>
                                <?= htmlspecialchars($a['nama_area']) ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $kap ?> Slot</span></td>
                            <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= $ter ?> Slot</span></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?= $sisa ?> Slot</span></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= (int)$a['aktif_parkir'] ?> Aktif</span></td>
                            <td><span class="text-muted"><?= number_format((int)$a['total_riwayat']) ?> Transaksi</span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar <?= $barColor ?>" style="width: <?= $persen ?>%;"></div>
                                    </div>
                                    <small class="fw-bold text-secondary"><?= $persen ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($persen >= 100): ?>
                                    <span class="badge bg-danger">PENUH</span>
                                <?php elseif ($persen >= 80): ?>
                                    <span class="badge bg-warning text-dark">HAMPIR PENUH</span>
                                <?php else: ?>
                                    <span class="badge bg-success">TERSEDIA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
