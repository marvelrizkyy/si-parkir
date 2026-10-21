<?php
/**
 * Transaksi Parkir Keluar & Pembayaran
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Mendukung Pembayaran Tunai (Kalkulasi Kembalian) & Non-Tunai
 * Menerapkan Database Concurrency Locking (SELECT ... FOR UPDATE)
 */

$pageTitle = 'Transaksi Kendaraan Keluar';
require_once __DIR__ . '/../includes/header.php';
requireRole('petugas');

$pdo = getDBConnection();
$error = '';

$queryInput = trim($_GET['kode'] ?? $_GET['id'] ?? $_POST['query_input'] ?? '');
$transaksi = null;
$perhitungan = null;

// Cari transaksi aktif berdasarkan id_parkir, kode_tiket, atau plat_nomor
if ($queryInput !== '') {
    $stmtCari = $pdo->prepare("
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               a.nama_area, u.nama_lengkap AS nama_petugas_masuk
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        JOIN tb_user u ON t.id_user = u.id_user
        WHERE (t.id_parkir = ? OR t.kode_tiket = ? OR LOWER(k.plat_nomor) = LOWER(?))
        ORDER BY t.id_parkir DESC
        LIMIT 1
    ");
    $stmtCari->execute([$queryInput, $queryInput, $queryInput]);
    $transaksi = $stmtCari->fetch();

    if ($transaksi && $transaksi['status'] === 'masuk') {
        $perhitungan = hitungDurasiDanBiaya($transaksi['waktu_masuk'], $transaksi['tarif_per_jam_saat_masuk']);
    }
}

// PROSES TRANSAKSI KELUAR & PEMBAYARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_keluar'])) {
    verifyCSRFOrDie();

    $idParkir = (int)($_POST['id_parkir'] ?? 0);
    $userId = (int)$_SESSION['user_id'];
    $metodeBayar = $_POST['metode_pembayaran'] ?? 'tunai';
    if (!in_array($metodeBayar, ['tunai', 'non_tunai'], true)) {
        $metodeBayar = 'tunai';
    }

    if ($idParkir <= 0) {
        $error = 'ID transaksi tidak valid.';
    } else {
        try {
            // ========================================================
            // PENERAPAN TRANSAKSI BASIS DATA & ROW-LEVEL LOCKING
            // ========================================================
            $pdo->beginTransaction();

            // 1. Kunci baris transaksi dengan SELECT ... FOR UPDATE
            $stmtLock = $pdo->prepare("
                SELECT t.id_parkir, t.kode_tiket, t.id_area, t.status, t.waktu_masuk, t.tarif_per_jam_saat_masuk,
                       k.plat_nomor
                FROM tb_transaksi t
                JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                WHERE t.id_parkir = ?
                FOR UPDATE
            ");
            $stmtLock->execute([$idParkir]);
            $trxLocked = $stmtLock->fetch();

            if (!$trxLocked) {
                $pdo->rollBack();
                $error = 'Transaksi parkir tidak ditemukan.';
            } elseif ($trxLocked['status'] !== 'masuk') {
                // Cegah pemrosesan ulang transaksi yang sudah keluar
                $pdo->rollBack();
                $error = 'Transaksi ini sudah selesai diproses sebelumnya (kendaraan sudah keluar).';
            } else {
                // 2. Hitung durasi dan biaya parkir menggunakan waktu server (NOW)
                $hitung = hitungDurasiDanBiaya($trxLocked['waktu_masuk'], $trxLocked['tarif_per_jam_saat_masuk']);
                $durasiJam = $hitung['durasi_jam'];
                $totalBiaya = (float)$hitung['biaya_total'];
                $idArea = (int)$trxLocked['id_area'];

                // 3. Validasi Pembayaran
                $jumlahBayar = 0;
                $kembalian = 0;

                if ($metodeBayar === 'tunai') {
                    $jumlahBayar = (float)str_replace(['.', ','], '', $_POST['jumlah_bayar'] ?? 0);
                    if ($jumlahBayar < $totalBiaya) {
                        $pdo->rollBack();
                        $error = 'Uang yang diterima (' . formatRupiah($jumlahBayar) . ') kurang dari total tagihan (' . formatRupiah($totalBiaya) . ').';
                    } else {
                        $kembalian = $jumlahBayar - $totalBiaya;
                    }
                } else {
                    // Non-tunai (QRIS / Debit / Transfer)
                    $jumlahBayar = $totalBiaya;
                    $kembalian = 0;
                }

                if (empty($error)) {
                    // 4. Update status transaksi menjadi 'keluar' dan 'lunas'
                    $stmtUpdateTrx = $pdo->prepare("
                        UPDATE tb_transaksi 
                        SET waktu_keluar = NOW(),
                            durasi_jam = ?,
                            biaya_total = ?,
                            metode_pembayaran = ?,
                            jumlah_bayar = ?,
                            kembalian = ?,
                            status_pembayaran = 'lunas',
                            waktu_pembayaran = NOW(),
                            status = 'keluar'
                        WHERE id_parkir = ?
                    ");
                    $stmtUpdateTrx->execute([$durasiJam, $totalBiaya, $metodeBayar, $jumlahBayar, $kembalian, $idParkir]);

                    // 5. Kunci dan kurangi slot terisi pada tb_area_parkir
                    $stmtUpdateArea = $pdo->prepare("
                        UPDATE tb_area_parkir 
                        SET terisi = GREATEST(0, terisi - 1) 
                        WHERE id_area = ?
                    ");
                    $stmtUpdateArea->execute([$idArea]);

                    // 6. Catat ke log aktivitas
                    $logKet = "Transaksi keluar & pembayaran {$trxLocked['plat_nomor']} [Tiket: {$trxLocked['kode_tiket']}] "
                            . "Total: " . formatRupiah($totalBiaya) . " via " . strtoupper($metodeBayar) 
                            . ($metodeBayar === 'tunai' ? " (Diterima: " . formatRupiah($jumlahBayar) . ", Kembali: " . formatRupiah($kembalian) . ")" : " (Lunas)");
                    recordLog($logKet, $userId);

                    // 7. Selesaikan transaksi ACID
                    $pdo->commit();

                    setFlashMessage('success', "Pembayaran untuk plat {$trxLocked['plat_nomor']} berhasil diselesaikan. Biaya: " . formatRupiah($totalBiaya));
                    header('Location: ' . BASE_URL . '/petugas/struk.php?id=' . $idParkir);
                    exit;
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error transaksi keluar: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat memproses transaksi keluar: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Transaksi Kendaraan Keluar & Pembayaran</h4>
        <p class="text-muted small mb-0">Scan QR tiket kendaraan keluar untuk kalkulasi durasi dan mengeluarkan total biaya yang perlu dibayar</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btn-toggle-camera" class="btn btn-warning rounded-pill px-3 shadow-sm text-dark fw-semibold" onclick="toggleCameraSection()">
            <i class="bi bi-camera-fill me-1"></i> <span id="label-toggle-camera">Buka Kamera Pemindai QR</span>
        </button>
        <?php if ($queryInput !== ''): ?>
            <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-light border rounded-pill px-3 shadow-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Scan Tiket Lain
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 small py-3 d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Kotak Kamera Scanner QR Code Tiket Keluar (Terintegrasi Langsung) -->
        <div id="scanner-container" class="card-custom p-4 mb-4 text-center <?= isset($_GET['scan']) ? '' : 'd-none' ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-qr-code-scan text-primary me-2"></i>Kamera Pemindai QR Code Tiket Keluar</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleCameraSection(false)">
                    <i class="bi bi-x-lg"></i> Tutup Kamera
                </button>
            </div>
            <div id="qr-reader" style="width: 100%; min-height: 280px; border-radius: 12px; overflow: hidden; background: #0f172a;" class="mb-3 d-flex align-items-center justify-content-center text-secondary small">
                <span>Klik tombol "Nyalakan Kamera" untuk mulai memindai QR tiket</span>
            </div>
            <div id="scanner-status" class="small mb-3 text-muted">
                <i class="bi bi-info-circle"></i> Arahkan QR Code pada tiket parkir ke dalam kotak kamera untuk mengeluarkan total biaya secara otomatis.
            </div>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" id="btn-start-scanner" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                    <i class="bi bi-camera me-1"></i> Nyalakan Kamera
                </button>
                <button type="button" id="btn-stop-scanner" class="btn btn-outline-danger rounded-pill px-4 d-none">
                    <i class="bi bi-camera-video-off me-1"></i> Matikan Kamera
                </button>
            </div>
        </div>

        <!-- Form Cari / Scan Tiket Parkir -->
        <div class="card-custom p-4 mb-4">
            <form method="GET" action="" id="form-cari-keluar">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label for="kode" class="form-label small fw-semibold text-secondary mb-0">
                        <i class="bi bi-upc-scan text-primary me-1"></i> Scan QR Tiket / Input Kode Tiket / Plat Nomor
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" onclick="toggleCameraSection(true)" style="font-size: 0.75rem;">
                        <i class="bi bi-camera me-1"></i> Mode Kamera
                    </button>
                </div>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light text-primary"><i class="bi bi-ticket-perforated-fill"></i></span>
                    <input type="text" class="form-control text-uppercase fw-bold" id="kode" name="kode" 
                           placeholder="Scan barcode/QR di sini atau ketik kode..." value="<?= htmlspecialchars($queryInput) ?>" required autofocus>
                    <button class="btn btn-primary px-4 fw-semibold" type="submit">
                        <i class="bi bi-calculator me-1"></i> Hitung Biaya
                    </button>
                </div>
                <div class="form-text small mt-2 d-flex flex-wrap justify-content-between text-muted">
                    <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Mendukung <strong>Scanner QR Kamera</strong>, <strong>Barcode Scanner USB</strong>, atau ketik manual Plat Nomor.</span>
                    <?php if ($queryInput !== ''): ?>
                        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="text-danger text-decoration-none">Reset Pencarian</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($queryInput !== ''): ?>
            <?php if (!$transaksi): ?>
                <div class="card-custom p-4 text-center border-danger">
                    <div class="text-danger mb-2 fs-2"><i class="bi bi-x-circle-fill"></i></div>
                    <h5 class="fw-bold text-dark">Data Tiket Tidak Ditemukan</h5>
                    <p class="text-muted small mb-3">Tidak ada data transaksi parkir aktif yang cocok dengan input <strong><?= htmlspecialchars($queryInput) ?></strong>.</p>
                    <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                        <i class="bi bi-arrow-clockwise me-1"></i> Scan Ulang Tiket
                    </a>
                </div>

            <?php elseif ($transaksi['status'] === 'keluar'): ?>
                <!-- Transaksi Sudah Selesai -->
                <div class="card-custom p-4 border-success">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="text-success fs-1"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <h5 class="fw-bold text-success mb-1">Transaksi Sudah Selesai (Lunas)</h5>
                            <p class="text-muted small mb-0">Kendaraan <strong><?= htmlspecialchars($transaksi['plat_nomor']) ?></strong> sudah menyelesaikan pembayaran dan tercatat keluar.</p>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 small border mb-3">
                        <div class="row g-2">
                            <div class="col-6">Nomor Tiket: <code><?= htmlspecialchars($transaksi['kode_tiket']) ?></code></div>
                            <div class="col-6">Total Biaya: <strong class="text-success"><?= formatRupiah($transaksi['biaya_total']) ?></strong></div>
                            <div class="col-6">Metode: <span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($transaksi['metode_pembayaran'] ?? 'tunai') ?></span></div>
                            <div class="col-6">Waktu Keluar: <?= formatTanggalIndo($transaksi['waktu_keluar']) ?></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/petugas/struk.php?id=<?= $transaksi['id_parkir'] ?>" class="btn btn-outline-success flex-grow-1 py-2 fw-semibold" target="_blank">
                            <i class="bi bi-printer me-1"></i> Cetak Ulang Struk
                        </a>
                        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-light border flex-grow-1 py-2 fw-semibold">
                            <i class="bi bi-upc-scan me-1"></i> Scan Kendaraan Berikutnya
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- ======================================================== -->
                <!-- DISPLAY HERO: TOTAL BIAYA PARKIR YANG PERLU DIBAYAR       -->
                <!-- ======================================================== -->
                <div id="bill-hero-card" class="card border-0 shadow-lg mb-4 overflow-hidden" style="border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                    <div class="p-4 border-bottom border-secondary border-opacity-25 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> HASIL SCAN QR TERVERIFIKASI
                            </span>
                            <span class="badge bg-light bg-opacity-10 text-light border border-light border-opacity-25 px-2 py-1 rounded-pill small font-monospace">
                                <?= htmlspecialchars($transaksi['kode_tiket']) ?>
                            </span>
                        </div>
                        <div>
                            <span class="plat-badge fs-4 shadow-sm"><?= htmlspecialchars($transaksi['plat_nomor']) ?></span>
                        </div>
                    </div>

                    <!-- HERO BANNER TOTAL BIAYA -->
                    <div class="p-4 text-center" style="background: radial-gradient(circle at center, rgba(16, 185, 129, 0.15) 0%, rgba(15, 23, 42, 0.4) 70%);">
                        <div class="text-secondary small fw-bold text-uppercase tracking-wider mb-1" style="letter-spacing: 2px;">
                            TOTAL BIAYA PARKIR YANG PERLU DIBAYAR
                        </div>
                        <div class="display-3 fw-bold text-warning my-2" style="font-family: 'Plus Jakarta Sans', monospace; letter-spacing: -1px; text-shadow: 0 0 25px rgba(245, 158, 11, 0.35);">
                            <?= formatRupiah($perhitungan['biaya_total']) ?>
                        </div>
                        <div class="d-flex justify-content-center flex-wrap gap-2 mt-2 text-light small">
                            <span class="bg-dark bg-opacity-50 px-3 py-1 rounded-pill border border-secondary border-opacity-25">
                                <i class="bi bi-clock-history text-info me-1"></i> Durasi: <strong><?= $perhitungan['durasi_jam'] ?> Jam</strong> (<?= $perhitungan['durasi_menit'] ?> Menit)
                            </span>
                            <span class="bg-dark bg-opacity-50 px-3 py-1 rounded-pill border border-secondary border-opacity-25">
                                <i class="bi bi-tag text-success me-1"></i> Tarif: <strong><?= formatRupiah($transaksi['tarif_per_jam_saat_masuk']) ?>/jam</strong>
                            </span>
                            <span class="bg-dark bg-opacity-50 px-3 py-1 rounded-pill border border-secondary border-opacity-25 text-uppercase">
                                <i class="bi <?= ($transaksi['jenis_kendaraan'] === 'motor') ? 'bi-bicycle' : 'bi-car-front' ?> text-warning me-1"></i> <?= htmlspecialchars($transaksi['jenis_kendaraan']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Formulir Rincian & Pembayaran Kasir -->
                <div class="card-custom p-4 border-primary">
                    <!-- Rincian Kalkulasi Durasi & Tarif -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-receipt-cutoff me-2"></i>Rincian Komputasi Parkir</h6>
                        <div class="row g-2 small">
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Area Parkir:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($transaksi['nama_area']) ?></span>
                            </div>
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Pemilik / Tamu:</span>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($transaksi['pemilik'] ?: 'Tamu Umum') ?></span>
                            </div>
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Waktu Masuk:</span>
                                <span class="fw-semibold text-dark"><?= formatTanggalIndo($transaksi['waktu_masuk']) ?></span>
                            </div>
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Waktu Keluar (Realtime):</span>
                                <span class="fw-semibold text-dark"><?= date('d F Y, H:i') ?> WIB</span>
                            </div>
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Durasi Dihitung:</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <?= $perhitungan['durasi_menit'] ?> Menit &rarr; <?= $perhitungan['durasi_jam'] ?> Jam (Pembulatan ke Atas)
                                </span>
                            </div>
                            <div class="col-12 col-sm-6 d-flex justify-content-between border-bottom pb-1">
                                <span class="text-muted">Tarif Saat Masuk:</span>
                                <span><?= formatRupiah($transaksi['tarif_per_jam_saat_masuk']) ?> / jam</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulir Pembayaran (Tunai vs Non-Tunai) -->
                    <form method="POST" action="" id="form-bayar">
                        <?= csrfField() ?>
                        <input type="hidden" name="proses_keluar" value="1">
                        <input type="hidden" name="id_parkir" value="<?= $transaksi['id_parkir'] ?>">
                        <input type="hidden" name="query_input" value="<?= htmlspecialchars($queryInput) ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Pilih Metode Pembayaran <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check form-check-inline border p-2 rounded-3 flex-grow-1 bg-white shadow-sm">
                                    <input class="form-check-input ms-1" type="radio" name="metode_pembayaran" id="metode_tunai" value="tunai" checked onchange="toggleMetodeBayar()">
                                    <label class="form-check-label fw-semibold ms-2" for="metode_tunai">
                                        <i class="bi bi-cash-stack text-success me-1"></i> Tunai (Cash)
                                    </label>
                                </div>
                                <div class="form-check form-check-inline border p-2 rounded-3 flex-grow-1 bg-white shadow-sm">
                                    <input class="form-check-input ms-1" type="radio" name="metode_pembayaran" id="metode_nontunai" value="non_tunai" onchange="toggleMetodeBayar()">
                                    <label class="form-check-label fw-semibold ms-2" for="metode_nontunai">
                                        <i class="bi bi-qr-code-scan text-primary me-1"></i> Non-Tunai (QRIS / EDC)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Pembayaran Tunai -->
                        <div id="panel-tunai" class="p-3 border rounded-3 bg-light mb-4">
                            <label for="jumlah_bayar" class="form-label small fw-semibold text-secondary">Uang Tunai Diterima (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg mb-2">
                                <span class="input-group-text bg-white fw-bold">Rp</span>
                                <input type="number" class="form-control fw-bold fs-4 text-dark" id="jumlah_bayar" name="jumlah_bayar" 
                                       value="<?= $perhitungan['biaya_total'] ?>" min="<?= $perhitungan['biaya_total'] ?>" step="500" required oninput="hitungKembalian()">
                            </div>

                            <!-- Tombol Cepat Pilihan Pecahan Uang -->
                            <div class="d-flex gap-1 flex-wrap mb-3">
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="setBayar(<?= $perhitungan['biaya_total'] ?>)">Uang Pas</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setBayar(5000)">Rp 5.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setBayar(10000)">Rp 10.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setBayar(20000)">Rp 20.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setBayar(50000)">Rp 50.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="setBayar(100000)">Rp 100.000</button>
                            </div>

                            <div class="p-3 rounded-3 bg-white border d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold text-secondary">Uang Kembalian:</span>
                                <span class="fs-4 fw-bold text-success" id="val-kembalian">Rp 0</span>
                            </div>
                            <div id="kembalian-alert" class="small text-danger mt-2 d-none">
                                <i class="bi bi-exclamation-circle-fill me-1"></i> Uang diterima kurang dari total biaya yang harus dibayar!
                            </div>
                        </div>

                        <!-- Panel Pembayaran Non-Tunai -->
                        <div id="panel-nontunai" class="p-3 border rounded-3 bg-light mb-4 d-none text-center">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 rounded-pill mb-2">
                                <i class="bi bi-check2-circle me-1"></i> Transaksi Non-Tunai Terintegrasi
                            </span>
                            <div class="fw-bold fs-6 text-dark mb-1">Pembayaran Terverifikasi via QRIS / EDC Gerbang Keluar</div>
                            <p class="text-muted small mb-2">Tagihan otomatis didebit pas sebesar <strong><?= formatRupiah($perhitungan['biaya_total']) ?></strong>.</p>
                            <div class="small text-secondary font-monospace bg-white border p-2 rounded-2 d-inline-block">REF: QRIS-<?= date('Ymd') ?>-<?= strtoupper(substr($transaksi['kode_tiket'], -4)) ?></div>
                        </div>

                        <button type="submit" id="btn-submit-bayar" class="btn btn-success w-100 py-3 rounded-3 fw-bold fs-5 shadow-sm">
                            <i class="bi bi-printer-fill me-2"></i> Selesaikan Transaksi & Cetak Struk
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const tagihan = <?= (float)($perhitungan['biaya_total'] ?? 0) ?>;

function toggleCameraSection(forceState = null) {
    const container = document.getElementById('scanner-container');
    const label = document.getElementById('label-toggle-camera');
    
    let shouldShow;
    if (forceState !== null) {
        shouldShow = forceState;
    } else {
        shouldShow = container.classList.contains('d-none');
    }

    if (shouldShow) {
        container.classList.remove('d-none');
        if (label) label.textContent = 'Tutup Kamera QR';
        // Auto-click start camera
        const startBtn = document.getElementById('btn-start-scanner');
        if (startBtn && !startBtn.classList.contains('d-none')) {
            startBtn.click();
        }
    } else {
        container.classList.add('d-none');
        if (label) label.textContent = 'Buka Kamera Pemindai QR';
        const stopBtn = document.getElementById('btn-stop-scanner');
        if (stopBtn && !stopBtn.classList.contains('d-none')) {
            stopBtn.click();
        }
    }
}

function toggleMetodeBayar() {
    const isTunai = document.getElementById('metode_tunai').checked;
    const panelTunai = document.getElementById('panel-tunai');
    const panelNonTunai = document.getElementById('panel-nontunai');
    const inputBayar = document.getElementById('jumlah_bayar');

    if (isTunai) {
        panelTunai.classList.remove('d-none');
        panelNonTunai.classList.add('d-none');
        inputBayar.setAttribute('required', 'required');
        hitungKembalian();
    } else {
        panelTunai.classList.add('d-none');
        panelNonTunai.classList.remove('d-none');
        inputBayar.removeAttribute('required');
        document.getElementById('btn-submit-bayar').removeAttribute('disabled');
    }
}

function setBayar(amount) {
    document.getElementById('jumlah_bayar').value = amount;
    hitungKembalian();
}

function hitungKembalian() {
    const inputVal = parseFloat(document.getElementById('jumlah_bayar').value) || 0;
    const kembalian = inputVal - tagihan;
    const valKembalian = document.getElementById('val-kembalian');
    const alertBox = document.getElementById('kembalian-alert');
    const btnSubmit = document.getElementById('btn-submit-bayar');

    if (kembalian >= 0) {
        valKembalian.textContent = 'Rp ' + kembalian.toLocaleString('id-ID');
        valKembalian.className = 'fs-4 fw-bold text-success';
        alertBox.classList.add('d-none');
        btnSubmit.removeAttribute('disabled');
    } else {
        valKembalian.textContent = '- Rp ' + Math.abs(kembalian).toLocaleString('id-ID');
        valKembalian.className = 'fs-4 fw-bold text-danger';
        alertBox.classList.remove('d-none');
        btnSubmit.setAttribute('disabled', 'disabled');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi QR Scanner langsung pada form keluar
    if (typeof initQrScanner === 'function') {
        initQrScanner('kode', 'form-cari-keluar');
    }

    // Cek auto start jika parameter ?scan=1
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('scan') === '1') {
        toggleCameraSection(true);
    }

    if (document.getElementById('jumlah_bayar')) {
        hitungKembalian();
        // Fokuskan ke input bayar jika tagihan sudah muncul
        document.getElementById('jumlah_bayar').focus();
        document.getElementById('jumlah_bayar').select();
    }

    // Auto scroll ke billing hero card jika ada
    const heroCard = document.getElementById('bill-hero-card');
    if (heroCard) {
        heroCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
