<?php
/**
 * Modul Monitoring Denah Lantai Parkir & Status Real-Time
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 *
 * Menampilkan denah arsitektur visual lantai parkir indoor dengan jalur kendaraan (driveway),
 * smart sensor LED (merah = terisi, hijau = kosong), pembatas roda (wheel stopper),
 * rambu lalu lintas indoor, dan engine polling real-time tanpa refresh layar.
 */

$pageTitle = 'Denah Lantai Parkir Real-Time';
require_once __DIR__ . '/../includes/header.php';
requireRole(['petugas', 'admin', 'owner']);

$pdo = getDBConnection();
$currentUserRole = $_SESSION['user_role'] ?? '';
$canOperate = in_array($currentUserRole, ['petugas', 'admin'], true);

// Ambil seluruh area parkir
$stmtArea = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS terisi_aktual
    FROM tb_area_parkir a
    ORDER BY a.nama_area ASC
");
$areaList = $stmtArea->fetchAll();

// Ambil seluruh transaksi berstatus masuk (kendaraan yang sedang aktif parkir)
$stmtTrx = $pdo->query("
    SELECT t.id_parkir, t.kode_tiket, t.id_area, t.waktu_masuk, t.tarif_per_jam_saat_masuk,
           k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           u.nama_lengkap AS nama_petugas,
           a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    LEFT JOIN tb_user u ON t.id_user = u.id_user
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk ASC
");
$activeTransactions = $stmtTrx->fetchAll();

// Kelompokkan transaksi aktif per id_area
$parkedByArea = [];
foreach ($activeTransactions as $t) {
    $parkedByArea[$t['id_area']][] = $t;
}

// Hitung total agregat kapasitas, terisi, dan kosong
$totalKapasitas = 0;
$totalTerisi = 0;
foreach ($areaList as $a) {
    $totalKapasitas += (int)$a['kapasitas'];
    $totalTerisi += (int)$a['terisi_aktual'];
}
$totalKosong = max(0, $totalKapasitas - $totalTerisi);
$persenOkupansi = $totalKapasitas > 0 ? min(100, round(($totalTerisi / $totalKapasitas) * 100)) : 0;

// Filter URL
$filterArea = $_GET['area_id'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all'; // all, terisi, kosong
$viewMode = $_GET['view'] ?? 'denah';     // denah, table

function getAreaSlotPrefix($namaArea, $index) {
    if (preg_match('/Area\s+([A-Za-z0-9]+)/i', $namaArea, $m)) {
        return strtoupper($m[1]);
    }
    $clean = trim($namaArea);
    return strtoupper(substr($clean, 0, 1)) . ($index + 1);
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0">Denah Lantai Parkir Real-Time</h4>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                <span class="pulse-live"></span>
                <span class="fw-bold" style="font-size: 0.75rem;" id="liveConnectionBadge">LIVE CONNECTED</span>
            </span>
        </div>
        <p class="text-muted small mb-0">Peta denah arsitektur slot indoor dengan smart sensor LED: <strong class="text-danger">Merah (Terisi)</strong> & <strong class="text-success">Hijau (Kosong)</strong></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Live Polling Controls -->
        <div class="bg-white border rounded-pill px-3 py-1 shadow-sm d-flex align-items-center gap-2">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="livePollingSwitch" checked>
                <label class="form-check-label small fw-semibold text-secondary" for="livePollingSwitch">Live Auto-Update</label>
            </div>
            <span class="badge bg-primary rounded-pill" id="pollTimerBadge" style="font-size: 0.72rem;">3s</span>
        </div>

        <!-- Fullscreen Kiosk Mode -->
        <button type="button" class="btn btn-outline-dark rounded-pill px-3 shadow-sm btn-sm" id="btnFullscreen" title="Tampilkan Mode Layar Penuh Kios Gerbang">
            <i class="bi bi-arrows-fullscreen me-1"></i> Fullscreen
        </button>

        <?php if ($canOperate): ?>
            <a href="<?= BASE_URL ?>/petugas/transaksi_masuk.php" class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Check-In Masuk
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     Kartu Statistik Ringkasan Kapasitas Keseluruhan
     ======================================================== -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-primary fs-3" id="kpiTotalKapasitas"><?= number_format($totalKapasitas) ?></div>
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
                <div class="stat-value text-danger fs-3" id="kpiTotalTerisi"><?= number_format($totalTerisi) ?></div>
                <div class="stat-label">Slot Terisi (Occupied)</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-car-front-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-success fs-3" id="kpiTotalKosong"><?= number_format($totalKosong) ?></div>
                <div class="stat-label">Slot Kosong (Tersedia)</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-custom stat-card">
            <div>
                <div class="stat-value text-purple fs-3" id="kpiPersenOkupansi"><?= $persenOkupansi ?>%</div>
                <div class="stat-label">Tingkat Okupansi Fasilitas</div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-pie-chart-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     Bilah Kontrol: Filter Area, Status, dan Pencarian Plat
     ======================================================== -->
<div class="card-custom p-3 mb-4">
    <div class="row g-2 align-items-center">
        <!-- Filter Area -->
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label small fw-semibold text-muted mb-1">Zona Denah Area</label>
            <select class="form-select form-select-sm rounded-pill" id="filterAreaSelect">
                <option value="all" <?= $filterArea === 'all' ? 'selected' : '' ?>>Semua Zona (<?= count($areaList) ?> Area)</option>
                <?php foreach ($areaList as $a): ?>
                    <option value="<?= $a['id_area'] ?>" <?= $filterArea == $a['id_area'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nama_area']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Status Ketersediaan -->
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label small fw-semibold text-muted mb-1">Filter Ketersediaan Slot</label>
            <select class="form-select form-select-sm rounded-pill" id="filterStatusSelect">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Slot (Terisi & Kosong)</option>
                <option value="terisi" <?= $filterStatus === 'terisi' ? 'selected' : '' ?>>🔴 Hanya Slot Terisi</option>
                <option value="kosong" <?= $filterStatus === 'kosong' ? 'selected' : '' ?>>🟢 Hanya Slot Kosong</option>
            </select>
        </div>

        <!-- Cari Plat Nomor Real-Time -->
        <div class="col-12 col-md-4 col-lg-4">
            <label class="form-label small fw-semibold text-muted mb-1">Cari Plat Nomor di Denah</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 rounded-end-pill" id="slotSearchInput" placeholder="Ketik plat nomor (misal: B 1234 ABC)..." autocomplete="off">
            </div>
        </div>

        <!-- Mode Tampilan (Denah / Tabel) -->
        <div class="col-12 col-lg-2 text-lg-end mt-2 mt-lg-0">
            <label class="form-label small fw-semibold text-muted mb-1 d-none d-lg-block">Mode Tampilan</label>
            <div class="btn-group btn-group-sm w-100" role="group">
                <button type="button" class="btn btn-primary" id="btnModeDenah">
                    <i class="bi bi-map-fill me-1"></i> Denah
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnModeTabel">
                    <i class="bi bi-table me-1"></i> Tabel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     Legenda Sensor Denah Arsitektur
     ======================================================== -->
<div class="d-flex flex-wrap align-items-center gap-3 mb-3 px-1 small">
    <div class="d-flex align-items-center gap-2">
        <span class="ceiling-sensor sensor-empty d-inline-block position-static" style="width: 12px; height: 12px;"></span>
        <span class="text-secondary fw-semibold">Sensor Hijau: Slot Kosong (Tersedia)</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="ceiling-sensor sensor-occupied d-inline-block position-static" style="width: 12px; height: 12px;"></span>
        <span class="text-secondary fw-semibold">Sensor Merah: Slot Terisi Kendaraan</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="hazard-stripe" style="width: 28px; height: 8px;"></div>
        <span class="text-secondary fw-semibold">Pembatas Roda (Wheel Stopper)</span>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto text-muted">
        <span id="lastUpdatedLabel"><i class="bi bi-clock-history me-1"></i>Diperbarui: barusan</span>
    </div>
</div>

<!-- ========================================================
     CONTAINER 1: DENAH ARSITEKTUR BLUEPRINT PER AREA
     ======================================================== -->
<div id="denahViewWrapper">
    <?php 
    $adaAreaTampil = false;
    foreach ($areaList as $index => $area): 
        if ($filterArea !== 'all' && (string)$area['id_area'] !== (string)$filterArea) {
            continue;
        }
        $adaAreaTampil = true;
        $idArea = (int)$area['id_area'];
        $kapasitas = max(1, (int)$area['kapasitas']);
        $terisiAktual = (int)$area['terisi_aktual'];
        $sisaSlot = max(0, $kapasitas - $terisiAktual);
        $persenArea = min(100, round(($terisiAktual / $kapasitas) * 100));
        $prefix = getAreaSlotPrefix($area['nama_area'], $index);
        
        $parkedVehicles = $parkedByArea[$idArea] ?? [];
        $half = (int)ceil($kapasitas / 2.0);
    ?>
        <div class="parking-blueprint mb-5 area-blueprint-card" data-area-id="<?= $idArea ?>" id="areaBlueprint_<?= $idArea ?>">
            <!-- Rambu Header Arsitektur Denah -->
            <div class="denah-header-bar">
                <div class="d-flex align-items-center gap-3">
                    <div class="pillar-column">
                        <i class="bi bi-grid-fill text-warning"></i>
                        <span>ZONA <?= $prefix ?> &bull; <?= strtoupper(htmlspecialchars($area['nama_area'])) ?></span>
                    </div>
                    <span class="badge bg-secondary font-monospace d-none d-md-inline-block">KAPASITAS: <?= $kapasitas ?> SLOT</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-2 py-1 area-terisi-badge">
                        Terisi: <?= $terisiAktual ?>
                    </span>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2 py-1 area-tersedia-badge">
                        Kosong: <?= $sisaSlot ?>
                    </span>
                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 px-2 py-1 area-persen-badge">
                        <?= $persenArea ?>%
                    </span>
                </div>
            </div>

            <!-- Petunjuk Masuk Gerbang Atas -->
            <div class="d-flex justify-content-between align-items-center px-2 mb-2 text-muted small font-monospace">
                <div><i class="bi bi-box-arrow-in-right text-success me-1"></i> [ PINTU MASUK GERBANG ➡ ]</div>
                <div class="d-none d-md-block text-warning"><i class="bi bi-exclamation-triangle me-1"></i> RAMBU: BATAS TINGGI 2.1 METER</div>
                <div>[ TIANG STRUKTUR <?= $prefix ?>-01 ] <i class="bi bi-building text-info ms-1"></i></div>
            </div>

            <!-- BARIS ATAS DENAH (Facing Down towards Driveway) -->
            <div class="bay-row-container mb-1 row-top" id="rowTop_<?= $idArea ?>">
                <?php 
                for ($s = 1; $s <= $half; $s++): 
                    $slotCode = $prefix . '-' . str_pad($s, 2, '0', STR_PAD_LEFT);
                    $isOccupied = ($s <= count($parkedVehicles));
                    $t = $isOccupied ? $parkedVehicles[$s - 1] : null;
                    
                    $calc = $isOccupied ? hitungDurasiDanBiaya($t['waktu_masuk'], $t['tarif_per_jam_saat_masuk']) : null;
                    $menit = $calc ? $calc['durasi_menit'] : 0;
                    $durasiHuman = ($menit >= 60) ? floor($menit / 60) . 'j ' . ($menit % 60) . 'm' : $menit . ' mnt';
                    $jenis = $t ? strtolower($t['jenis_kendaraan']) : '';
                ?>
                    <div class="denah-stall facing-down <?= $isOccupied ? 'is-occupied' : 'is-empty' ?> stall-card"
                         id="stall_<?= $idArea ?>_<?= $s ?>"
                         data-slot="<?= $slotCode ?>"
                         data-occupied="<?= $isOccupied ? '1' : '0' ?>"
                         data-plat="<?= $t ? htmlspecialchars(strtoupper($t['plat_nomor'])) : '' ?>"
                         data-tiket="<?= $t ? htmlspecialchars($t['kode_tiket']) : '' ?>"
                         data-idparkir="<?= $t ? $t['id_parkir'] : '' ?>"
                         data-jenis="<?= $t ? htmlspecialchars(ucfirst($t['jenis_kendaraan'])) : '' ?>"
                         data-warna="<?= $t ? htmlspecialchars($t['warna'] ?? '-') : '' ?>"
                         data-pemilik="<?= $t ? htmlspecialchars($t['pemilik'] ?? '-') : '' ?>"
                         data-masuk="<?= $t ? formatTanggalIndo($t['waktu_masuk'], true) : '' ?>"
                         data-durasi="<?= $calc ? $durasiHuman . ' (' . $calc['durasi_jam'] . ' jam)' : '' ?>"
                         data-tarif="<?= $t ? formatRupiah($t['tarif_per_jam_saat_masuk']) : '' ?>"
                         data-estimasi="<?= $calc ? formatRupiah($calc['biaya_total']) : '' ?>"
                         data-petugas="<?= $t ? htmlspecialchars($t['nama_petugas'] ?? 'Sistem') : '' ?>"
                         data-area="<?= htmlspecialchars($area['nama_area']) ?>"
                         data-area-id="<?= $idArea ?>"
                         data-bs-toggle="modal"
                         data-bs-target="<?= $isOccupied ? '#slotDetailModal' : '#emptySlotModal' ?>"
                         title="Slot <?= $slotCode ?>: <?= $isOccupied ? 'Terisi ' . htmlspecialchars($t['plat_nomor']) : 'Kosong (Tersedia)' ?>">
                        
                        <!-- Smart Overhead Sensor LED -->
                        <span class="ceiling-sensor <?= $isOccupied ? 'sensor-occupied' : 'sensor-empty' ?>"></span>

                        <!-- Header Petak Slot -->
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="stall-code-badge"><?= $slotCode ?></span>
                        </div>

                        <!-- Konten Tengah Petak Slot -->
                        <div class="stall-content my-auto">
                            <?php if ($isOccupied): ?>
                                <div class="topdown-vehicle <?= $jenis === 'motor' ? 'motor' : ($jenis === 'lainnya' ? 'lainnya' : 'mobil') ?>">
                                    <div class="vehicle-windshield"></div>
                                    <div class="vehicle-plat-tag"><?= htmlspecialchars($t['plat_nomor']) ?></div>
                                    <div class="vehicle-lights">
                                        <span class="headlight"></span>
                                        <span class="headlight"></span>
                                    </div>
                                </div>
                                <div class="text-center text-truncate small text-secondary mt-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-clock me-1"></i><?= $durasiHuman ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-stencil">
                                    <div class="stencil-p">P</div>
                                    <div class="stencil-label">KOSONG</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pembatas Roda (Wheel Stopper di Dinding Belakang) -->
                        <div class="wheel-stopper"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- JALUR KENDARAAN TENGAH (DRIVEWAY AISLE) -->
            <div class="driveway-aisle">
                <div class="driveway-sign">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                    <span>JALUR SATU ARAH</span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-dark border border-warning text-warning px-3 py-1 font-monospace">
                        <i class="bi bi-speedometer2 me-1"></i> KECEPATAN MAKS 10 KM/JAM
                    </span>
                    <div class="pedestrian-walkway d-none d-lg-block" title="Zebra Cross Pejalan Kaki"></div>
                </div>

                <div class="driveway-sign">
                    <span>KE GERBANG KELUAR</span>
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </div>

            <!-- BARIS BAWAH DENAH (Facing Up towards Driveway) -->
            <div class="bay-row-container row-bottom" id="rowBottom_<?= $idArea ?>">
                <?php 
                for ($s = $half + 1; $s <= $kapasitas; $s++): 
                    $slotCode = $prefix . '-' . str_pad($s, 2, '0', STR_PAD_LEFT);
                    $isOccupied = ($s <= count($parkedVehicles));
                    $t = $isOccupied ? $parkedVehicles[$s - 1] : null;
                    
                    $calc = $isOccupied ? hitungDurasiDanBiaya($t['waktu_masuk'], $t['tarif_per_jam_saat_masuk']) : null;
                    $menit = $calc ? $calc['durasi_menit'] : 0;
                    $durasiHuman = ($menit >= 60) ? floor($menit / 60) . 'j ' . ($menit % 60) . 'm' : $menit . ' mnt';
                    $jenis = $t ? strtolower($t['jenis_kendaraan']) : '';
                ?>
                    <div class="denah-stall facing-up <?= $isOccupied ? 'is-occupied' : 'is-empty' ?> stall-card"
                         id="stall_<?= $idArea ?>_<?= $s ?>"
                         data-slot="<?= $slotCode ?>"
                         data-occupied="<?= $isOccupied ? '1' : '0' ?>"
                         data-plat="<?= $t ? htmlspecialchars(strtoupper($t['plat_nomor'])) : '' ?>"
                         data-tiket="<?= $t ? htmlspecialchars($t['kode_tiket']) : '' ?>"
                         data-idparkir="<?= $t ? $t['id_parkir'] : '' ?>"
                         data-jenis="<?= $t ? htmlspecialchars(ucfirst($t['jenis_kendaraan'])) : '' ?>"
                         data-warna="<?= $t ? htmlspecialchars($t['warna'] ?? '-') : '' ?>"
                         data-pemilik="<?= $t ? htmlspecialchars($t['pemilik'] ?? '-') : '' ?>"
                         data-masuk="<?= $t ? formatTanggalIndo($t['waktu_masuk'], true) : '' ?>"
                         data-durasi="<?= $calc ? $durasiHuman . ' (' . $calc['durasi_jam'] . ' jam)' : '' ?>"
                         data-tarif="<?= $t ? formatRupiah($t['tarif_per_jam_saat_masuk']) : '' ?>"
                         data-estimasi="<?= $calc ? formatRupiah($calc['biaya_total']) : '' ?>"
                         data-petugas="<?= $t ? htmlspecialchars($t['nama_petugas'] ?? 'Sistem') : '' ?>"
                         data-area="<?= htmlspecialchars($area['nama_area']) ?>"
                         data-area-id="<?= $idArea ?>"
                         data-bs-toggle="modal"
                         data-bs-target="<?= $isOccupied ? '#slotDetailModal' : '#emptySlotModal' ?>"
                         title="Slot <?= $slotCode ?>: <?= $isOccupied ? 'Terisi ' . htmlspecialchars($t['plat_nomor']) : 'Kosong (Tersedia)' ?>">
                        
                        <!-- Pembatas Roda Bawah -->
                        <div class="wheel-stopper"></div>

                        <!-- Konten Tengah Petak Slot -->
                        <div class="stall-content my-auto">
                            <?php if ($isOccupied): ?>
                                <div class="topdown-vehicle <?= $jenis === 'motor' ? 'motor' : ($jenis === 'lainnya' ? 'lainnya' : 'mobil') ?>">
                                    <div class="vehicle-lights">
                                        <span class="taillight"></span>
                                        <span class="taillight"></span>
                                    </div>
                                    <div class="vehicle-plat-tag"><?= htmlspecialchars($t['plat_nomor']) ?></div>
                                    <div class="vehicle-windshield" style="border-radius: 2px 2px 6px 6px;"></div>
                                </div>
                                <div class="text-center text-truncate small text-secondary mt-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-clock me-1"></i><?= $durasiHuman ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-stencil">
                                    <div class="stencil-p">P</div>
                                    <div class="stencil-label">KOSONG</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Header Petak Slot -->
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="stall-code-badge"><?= $slotCode ?></span>
                            <!-- Smart Overhead Sensor LED -->
                            <span class="ceiling-sensor <?= $isOccupied ? 'sensor-occupied' : 'sensor-empty' ?>"></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Petunjuk Keluar Gerbang Bawah -->
            <div class="d-flex justify-content-between align-items-center px-2 mt-3 pt-2 border-top border-secondary border-opacity-25 text-muted small font-monospace">
                <div><i class="bi bi-person-walking text-info me-1"></i> [ 🚶 JALUR PEDESTRIAN KE LOBBY & LIFT ]</div>
                <div>[ ➡ GERBANG KELUAR & POS KASIR ] <i class="bi bi-box-arrow-right text-danger ms-1"></i></div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$adaAreaTampil): ?>
        <div class="card-custom p-5 text-center text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
            Tidak ada area parkir yang sesuai dengan filter yang dipilih.
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================
     CONTAINER 2: MODE TABEL RINCIAN (ALTERNATIF VIEW)
     ======================================================== -->
<div id="tableViewWrapper" style="display: none;">
    <div class="card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Daftar Seluruh Kendaraan yang Sedang Mengisi Slot Parkir</h6>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" id="tableTotalAktifBadge">
                Total: <?= count($activeTransactions) ?> Kendaraan
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0" id="tableKendaraanAktif">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Kode Tiket</th>
                        <th>Plat Nomor</th>
                        <th>Jenis & Warna</th>
                        <th>Zona Area</th>
                        <th>Waktu Masuk</th>
                        <th>Durasi Berjalan</th>
                        <th>Estimasi Biaya</th>
                        <th style="width: 140px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBodyAktif">
                    <?php if (empty($activeTransactions)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Seluruh slot parkir saat ini dalam keadaan kosong.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($activeTransactions as $t): 
                            $calc = hitungDurasiDanBiaya($t['waktu_masuk'], $t['tarif_per_jam_saat_masuk']);
                            $menit = $calc['durasi_menit'];
                            $durasiHuman = ($menit >= 60) ? floor($menit / 60) . ' jam ' . ($menit % 60) . ' menit' : $menit . ' menit';
                        ?>
                            <tr data-plat="<?= htmlspecialchars(strtoupper($t['plat_nomor'])) ?>" data-area-id="<?= $t['id_area'] ?>">
                                <td><?= $no++ ?></td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($t['kode_tiket']) ?></span></td>
                                <td><span class="plat-badge"><?= htmlspecialchars($t['plat_nomor']) ?></span></td>
                                <td>
                                    <strong><?= ucfirst($t['jenis_kendaraan']) ?></strong>
                                    <?php if (!empty($t['warna'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($t['warna']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?= htmlspecialchars($t['nama_area']) ?></span></td>
                                <td><?= formatTanggalIndo($t['waktu_masuk'], true) ?></td>
                                <td><span class="fw-semibold text-primary"><?= $durasiHuman ?></span></td>
                                <td><strong class="text-danger"><?= formatRupiah($calc['biaya_total']) ?></strong></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="<?= BASE_URL ?>/petugas/tiket.php?id=<?= $t['id_parkir'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank" title="Cetak Tiket">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($canOperate): ?>
                                            <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php?kode=<?= urlencode($t['kode_tiket']) ?>" class="btn btn-sm btn-danger btn-action" title="Proses Kasir / Keluar">
                                                <i class="bi bi-box-arrow-right"></i>
                                            </a>
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
</div>

<!-- ========================================================
     MODAL 1: DETAIL KENDARAAN DI SLOT TERISI
     ======================================================== -->
<div class="modal fade" id="slotDetailModal" tabindex="-1" aria-labelledby="slotDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger rounded-pill px-3 py-1">SLOT TERISI</span>
                    <h5 class="modal-title fw-bold mb-0" id="mSlotCode">Slot A-01</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="mb-2">
                        <span class="plat-badge fs-4 px-4 py-2" id="mPlatNomor">B 1234 ABC</span>
                    </div>
                    <span class="badge bg-secondary font-monospace" id="mKodeTiket">TRX-XXXXXX</span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded border text-center">
                            <small class="text-muted d-block">Durasi Berjalan</small>
                            <span class="fw-bold text-primary" id="mDurasi">0 Jam</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded border text-center">
                            <small class="text-muted d-block">Estimasi Tagihan</small>
                            <span class="fw-bold text-danger" id="mEstimasi">Rp 0</span>
                        </div>
                    </div>
                </div>

                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Zona Area:</span>
                        <strong class="text-dark" id="mAreaNama">-</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Jenis Kendaraan:</span>
                        <strong class="text-dark" id="mJenisKendaraan">-</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Warna / Pemilik:</span>
                        <strong class="text-dark" id="mWarnaPemilik">-</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Waktu Masuk:</span>
                        <strong class="text-dark" id="mWaktuMasuk">-</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tarif Per Jam:</span>
                        <strong class="text-dark" id="mTarifPerJam">-</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Petugas Gerbang:</span>
                        <strong class="text-dark" id="mNamaPetugas">-</strong>
                    </li>
                </ul>

                <div class="d-flex gap-2">
                    <a href="#" id="mBtnCetakTiket" class="btn btn-outline-secondary w-50 rounded-pill" target="_blank">
                        <i class="bi bi-printer me-1"></i> Cetak Tiket
                    </a>
                    <?php if ($canOperate): ?>
                        <a href="#" id="mBtnCheckOut" class="btn btn-danger w-50 rounded-pill fw-semibold">
                            <i class="bi bi-box-arrow-right me-1"></i> Kasir / Keluar
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary w-50 rounded-pill" data-bs-dismiss="modal">Tutup</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 2: DETAIL SLOT KOSONG / TERSEDIA
     ======================================================== -->
<div class="modal fade" id="emptySlotModal" tabindex="-1" aria-labelledby="emptySlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success rounded-pill px-3 py-1">SLOT KOSONG</span>
                    <h5 class="modal-title fw-bold mb-0" id="eSlotCode">Slot A-05</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="bi bi-check-circle-fill fs-1"></i>
                </div>
                <h5 class="fw-bold mb-1">Slot Tersedia untuk Parkir</h5>
                <p class="text-muted small mb-4">Slot ini sedang kosong dan siap ditempati oleh kendaraan baru di zona <strong id="eAreaName">Area A</strong>.</p>

                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                    <?php if ($canOperate): ?>
                        <a href="#" id="eBtnCheckIn" class="btn btn-primary rounded-pill px-4 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Check-In ke Slot Ini
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     ENGINE POLLING REAL-TIME (AJAX FETCH TANPA RELOAD)
     ======================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const API_URL = '<?= BASE_URL ?>/petugas/api_slot.php';
    const livePollingSwitch = document.getElementById('livePollingSwitch');
    const pollTimerBadge = document.getElementById('pollTimerBadge');
    const liveConnectionBadge = document.getElementById('liveConnectionBadge');
    const lastUpdatedLabel = document.getElementById('lastUpdatedLabel');
    
    let isModalOpen = false;
    let pollIntervalSeconds = 3;
    let secondsRemaining = pollIntervalSeconds;

    // Track status modal
    const modals = document.querySelectorAll('.modal');
    modals.forEach(m => {
        m.addEventListener('show.bs.modal', () => { isModalOpen = true; });
        m.addEventListener('hidden.bs.modal', () => { isModalOpen = false; });
    });

    // 1. Logika Timer & Polling Asinkron
    setInterval(async () => {
        if (!livePollingSwitch.checked) {
            pollTimerBadge.textContent = 'Pause';
            pollTimerBadge.className = 'badge bg-secondary rounded-pill';
            return;
        }

        if (isModalOpen) {
            pollTimerBadge.textContent = 'Hold';
            pollTimerBadge.className = 'badge bg-warning text-dark rounded-pill';
            return;
        }

        secondsRemaining--;
        pollTimerBadge.textContent = secondsRemaining + 's';
        pollTimerBadge.className = 'badge bg-primary rounded-pill';

        if (secondsRemaining <= 0) {
            secondsRemaining = pollIntervalSeconds;
            await fetchRealtimeSlotData();
        }
    }, 1000);

    // 2. Fetch API Slot
    async function fetchRealtimeSlotData() {
        try {
            const res = await fetch(API_URL, { cache: 'no-store' });
            if (!res.ok) throw new Error('Network error: ' + res.status);
            const data = await res.json();
            if (data.status !== 'success') return;

            updateDashboardUI(data);

            liveConnectionBadge.textContent = 'LIVE CONNECTED';
            liveConnectionBadge.parentElement.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill d-inline-flex align-items-center gap-1';
            lastUpdatedLabel.innerHTML = '<i class="bi bi-clock-history me-1"></i>Diperbarui: ' + data.server_time + ' WIB';

        } catch (err) {
            console.warn('Real-time polling notice:', err);
            liveConnectionBadge.textContent = 'RECONNECTING...';
            liveConnectionBadge.parentElement.className = 'badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 rounded-pill d-inline-flex align-items-center gap-1';
        }
    }

    // 3. Update DOM UI Real-Time
    function updateDashboardUI(data) {
        // Update Ringkasan KPI
        if (data.summary) {
            document.getElementById('kpiTotalKapasitas').textContent = Number(data.summary.total_kapasitas).toLocaleString('id-ID');
            document.getElementById('kpiTotalTerisi').textContent = Number(data.summary.total_terisi).toLocaleString('id-ID');
            document.getElementById('kpiTotalKosong').textContent = Number(data.summary.total_kosong).toLocaleString('id-ID');
            document.getElementById('kpiPersenOkupansi').textContent = data.summary.persen_okupansi + '%';
        }

        // Update Slot per Area
        if (data.areas && Array.isArray(data.areas)) {
            data.areas.forEach(area => {
                const areaCard = document.getElementById('areaBlueprint_' + area.id_area);
                if (areaCard) {
                    const badgeTerisi = areaCard.querySelector('.area-terisi-badge');
                    const badgeTersedia = areaCard.querySelector('.area-tersedia-badge');
                    const badgePersen = areaCard.querySelector('.area-persen-badge');
                    if (badgeTerisi) badgeTerisi.textContent = 'Terisi: ' + area.terisi;
                    if (badgeTersedia) badgeTersedia.textContent = 'Kosong: ' + area.kosong;
                    if (badgePersen) badgePersen.textContent = area.persen + '%';
                }

                // Perbarui setiap petak stall
                if (area.slots && Array.isArray(area.slots)) {
                    area.slots.forEach(slot => {
                        const stallElem = document.getElementById('stall_' + area.id_area + '_' + slot.slot_num);
                        if (!stallElem) return;

                        const isOccupied = slot.is_occupied;
                        const prevOccupied = stallElem.getAttribute('data-occupied') === '1';

                        stallElem.setAttribute('data-occupied', isOccupied ? '1' : '0');

                        // Cek sensor LED
                        const sensor = stallElem.querySelector('.ceiling-sensor');
                        if (sensor) {
                            sensor.className = 'ceiling-sensor ' + (isOccupied ? 'sensor-occupied' : 'sensor-empty');
                        }

                        // Cek stall class
                        stallElem.classList.remove('is-occupied', 'is-empty');
                        stallElem.classList.add(isOccupied ? 'is-occupied' : 'is-empty');

                        // Target modal
                        stallElem.setAttribute('data-bs-target', isOccupied ? '#slotDetailModal' : '#emptySlotModal');

                        const contentElem = stallElem.querySelector('.stall-content');

                        if (isOccupied && slot.transaksi) {
                            const t = slot.transaksi;
                            stallElem.setAttribute('data-plat', t.plat_nomor);
                            stallElem.setAttribute('data-tiket', t.kode_tiket);
                            stallElem.setAttribute('data-idparkir', t.id_parkir);
                            stallElem.setAttribute('data-jenis', t.jenis_kendaraan);
                            stallElem.setAttribute('data-warna', t.warna);
                            stallElem.setAttribute('data-pemilik', t.pemilik);
                            stallElem.setAttribute('data-masuk', t.waktu_masuk_indo);
                            stallElem.setAttribute('data-durasi', t.durasi_human + ' (' + t.durasi_jam + ' jam)');
                            stallElem.setAttribute('data-tarif', t.tarif_per_jam_rp);
                            stallElem.setAttribute('data-estimasi', t.biaya_estimasi_rp);
                            stallElem.setAttribute('data-petugas', t.nama_petugas);

                            const j = t.jenis_kendaraan.toLowerCase();
                            const vClass = (j === 'motor') ? 'motor' : ((j === 'lainnya') ? 'lainnya' : 'mobil');
                            const isDown = stallElem.classList.contains('facing-down');

                            if (contentElem && (!prevOccupied || stallElem.getAttribute('data-plat') !== t.plat_nomor)) {
                                contentElem.innerHTML = `
                                    <div class="topdown-vehicle ${vClass}">
                                        ${isDown ? '<div class="vehicle-windshield"></div>' : '<div class="vehicle-lights"><span class="taillight"></span><span class="taillight"></span></div>'}
                                        <div class="vehicle-plat-tag">${escapeHtml(t.plat_nomor)}</div>
                                        ${isDown ? '<div class="vehicle-lights"><span class="headlight"></span><span class="headlight"></span></div>' : '<div class="vehicle-windshield" style="border-radius: 2px 2px 6px 6px;"></div>'}
                                    </div>
                                    <div class="text-center text-truncate small text-secondary mt-1" style="font-size: 0.65rem;">
                                        <i class="bi bi-clock me-1"></i>${escapeHtml(t.durasi_human)}
                                    </div>
                                `;
                            } else if (contentElem) {
                                // Update durasi berjalan saja
                                const durasiText = contentElem.querySelector('.text-secondary');
                                if (durasiText) {
                                    durasiText.innerHTML = '<i class="bi bi-clock me-1"></i>' + escapeHtml(t.durasi_human);
                                }
                            }
                        } else {
                            stallElem.setAttribute('data-plat', '');
                            stallElem.setAttribute('data-tiket', '');
                            stallElem.setAttribute('data-idparkir', '');
                            if (contentElem && prevOccupied) {
                                contentElem.innerHTML = `
                                    <div class="empty-stencil">
                                        <div class="stencil-p">P</div>
                                        <div class="stencil-label">KOSONG</div>
                                    </div>
                                `;
                            }
                        }
                    });
                });
            });
        }
    }

    // 4. Modal Handlers
    const slotDetailModal = document.getElementById('slotDetailModal');
    if (slotDetailModal) {
        slotDetailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            document.getElementById('mSlotCode').textContent = 'Slot ' + button.getAttribute('data-slot');
            document.getElementById('mPlatNomor').textContent = button.getAttribute('data-plat') || '-';
            document.getElementById('mKodeTiket').textContent = button.getAttribute('data-tiket') || '-';
            document.getElementById('mDurasi').textContent = button.getAttribute('data-durasi') || '-';
            document.getElementById('mEstimasi').textContent = button.getAttribute('data-estimasi') || '-';
            document.getElementById('mAreaNama').textContent = button.getAttribute('data-area') || '-';
            document.getElementById('mJenisKendaraan').textContent = button.getAttribute('data-jenis') || '-';
            document.getElementById('mWarnaPemilik').textContent = (button.getAttribute('data-warna') || '-') + ' / ' + (button.getAttribute('data-pemilik') || '-');
            document.getElementById('mWaktuMasuk').textContent = button.getAttribute('data-masuk') || '-';
            document.getElementById('mTarifPerJam').textContent = (button.getAttribute('data-tarif') || '-') + ' / jam';
            document.getElementById('mNamaPetugas').textContent = button.getAttribute('data-petugas') || '-';

            const idParkir = button.getAttribute('data-idparkir');
            document.getElementById('mBtnCetakTiket').href = '<?= BASE_URL ?>/petugas/tiket.php?id=' + encodeURIComponent(idParkir);
            
            const btnCheckOut = document.getElementById('mBtnCheckOut');
            if (btnCheckOut) {
                btnCheckOut.href = '<?= BASE_URL ?>/petugas/transaksi_keluar.php?kode=' + encodeURIComponent(button.getAttribute('data-tiket'));
            }
        });
    }

    const emptySlotModal = document.getElementById('emptySlotModal');
    if (emptySlotModal) {
        emptySlotModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            document.getElementById('eSlotCode').textContent = 'Slot ' + button.getAttribute('data-slot');
            document.getElementById('eAreaName').textContent = button.getAttribute('data-area');

            const btnCheckIn = document.getElementById('eBtnCheckIn');
            if (btnCheckIn) {
                btnCheckIn.href = '<?= BASE_URL ?>/petugas/transaksi_masuk.php?area_id=' + encodeURIComponent(button.getAttribute('data-area-id'));
            }
        });
    }

    // 5. Pencarian Plat Nomor / Tiket Real-Time di Sisi Klien
    const slotSearchInput = document.getElementById('slotSearchInput');
    if (slotSearchInput) {
        slotSearchInput.addEventListener('input', function () {
            const q = this.value.trim().toUpperCase();
            const stalls = document.querySelectorAll('.stall-card');

            stalls.forEach(card => {
                const plat = (card.getAttribute('data-plat') || '').toUpperCase();
                const tiket = (card.getAttribute('data-tiket') || '').toUpperCase();
                const slot = (card.getAttribute('data-slot') || '').toUpperCase();

                if (q === '') {
                    card.style.opacity = '1';
                    card.style.transform = '';
                    card.style.borderColor = '';
                } else if (plat.includes(q) || tiket.includes(q) || slot.includes(q)) {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1.08)';
                    card.style.borderColor = '#f59e0b';
                } else {
                    card.style.opacity = '0.18';
                    card.style.transform = 'scale(0.96)';
                    card.style.borderColor = '';
                }
            });
        });
    }

    // 6. Filter Area & Status
    const filterAreaSelect = document.getElementById('filterAreaSelect');
    const filterStatusSelect = document.getElementById('filterStatusSelect');

    function executeFilters() {
        const areaVal = filterAreaSelect.value;
        const statusVal = filterStatusSelect.value;

        // Filter Area
        document.querySelectorAll('.area-blueprint-card').forEach(card => {
            const areaId = card.getAttribute('data-area-id');
            if (areaVal === 'all' || areaVal === areaId) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        // Filter Status
        document.querySelectorAll('.stall-card').forEach(stall => {
            const isOcc = stall.getAttribute('data-occupied') === '1';
            if (statusVal === 'all') {
                stall.style.display = '';
            } else if (statusVal === 'terisi') {
                stall.style.display = isOcc ? '' : 'none';
            } else if (statusVal === 'kosong') {
                stall.style.display = !isOcc ? '' : 'none';
            }
        });
    }

    filterAreaSelect.addEventListener('change', executeFilters);
    filterStatusSelect.addEventListener('change', executeFilters);

    // 7. Pengalih Mode Denah vs Tabel
    const btnModeDenah = document.getElementById('btnModeDenah');
    const btnModeTabel = document.getElementById('btnModeTabel');
    const denahViewWrapper = document.getElementById('denahViewWrapper');
    const tableViewWrapper = document.getElementById('tableViewWrapper');

    btnModeDenah.addEventListener('click', () => {
        btnModeDenah.className = 'btn btn-primary';
        btnModeTabel.className = 'btn btn-outline-secondary';
        denahViewWrapper.style.display = '';
        tableViewWrapper.style.display = 'none';
    });

    btnModeTabel.addEventListener('click', () => {
        btnModeTabel.className = 'btn btn-primary';
        btnModeDenah.className = 'btn btn-outline-secondary';
        denahViewWrapper.style.display = 'none';
        tableViewWrapper.style.display = '';
    });

    // 8. Fullscreen Kiosk Mode
    const btnFullscreen = document.getElementById('btnFullscreen');
    if (btnFullscreen) {
        btnFullscreen.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    alert('Gagal mengaktifkan mode fullscreen: ' + err.message);
                });
                btnFullscreen.innerHTML = '<i class="bi bi-fullscreen-exit me-1"></i> Keluar Fullscreen';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    btnFullscreen.innerHTML = '<i class="bi bi-arrows-fullscreen me-1"></i> Fullscreen';
                }
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
