<?php
/**
 * Transaksi Parkir Masuk
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Mendukung Pendaftaran Manual & Otomatis Saat Masuk Pertama Kali
 * Menerapkan Database Concurrency Locking (SELECT ... FOR UPDATE)
 */

$pageTitle = 'Transaksi Parkir Masuk';
require_once __DIR__ . '/../includes/header.php';
requireRole('petugas');

$pdo = getDBConnection();
$error = '';

$platQuery = normalizePlatNomor($_GET['plat'] ?? $_POST['plat_nomor'] ?? '');
$selectedAreaId = (int)($_GET['area_id'] ?? $_GET['id_area'] ?? 0);
$kendaraan = null;
$transaksiAktif = null;

// Cek data kendaraan jika ada plat yang diinput
if ($platQuery !== '') {
    $stmtCekKendaraan = $pdo->prepare("SELECT * FROM tb_kendaraan WHERE LOWER(plat_nomor) = LOWER(?)");
    $stmtCekKendaraan->execute([$platQuery]);
    $kendaraan = $stmtCekKendaraan->fetch();

    if ($kendaraan) {
        // Cek apakah sudah ada transaksi aktif
        $stmtCekAktif = $pdo->prepare("SELECT id_parkir, kode_tiket, waktu_masuk FROM tb_transaksi WHERE id_kendaraan = ? AND status = 'masuk'");
        $stmtCekAktif->execute([$kendaraan['id_kendaraan']]);
        $transaksiAktif = $stmtCekAktif->fetch();
    }
}

// Ambil daftar area yang tersedia
$stmtAreas = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY nama_area ASC");
$areaList = $stmtAreas->fetchAll();

// PROSES TRANSAKSI MASUK (Untuk Kendaraan Terdaftar maupun Kendaraan Baru Otomatis)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['proses_masuk']) || isset($_POST['proses_masuk_otomatis']))) {
    verifyCSRFOrDie();

    $idArea = (int)($_POST['id_area'] ?? 0);
    $userId = (int)$_SESSION['user_id'];
    $platPost = normalizePlatNomor($_POST['plat_nomor'] ?? '');
    $isOtomatis = isset($_POST['proses_masuk_otomatis']);

    if (empty($platPost) || $idArea <= 0) {
        $error = 'Harap lengkapi plat nomor dan area parkir yang valid.';
    } else {
        try {
            // ========================================================
            // MULAI TRANSAKSI BASIS DATA DENGAN ROW-LEVEL LOCKING
            // ========================================================
            $pdo->beginTransaction();

            // 1. Kunci dan periksa kapasitas area parkir (FOR UPDATE)
            $stmtLockArea = $pdo->prepare("SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = ? FOR UPDATE");
            $stmtLockArea->execute([$idArea]);
            $areaData = $stmtLockArea->fetch();

            if (!$areaData) {
                $pdo->rollBack();
                $error = 'Area parkir tidak ditemukan.';
            } elseif ($areaData['terisi'] >= $areaData['kapasitas']) {
                $pdo->rollBack();
                $error = "Area parkir '{$areaData['nama_area']}' sudah PENUH! Kapasitas: {$areaData['kapasitas']} slot.";
            } else {
                // 2. Tentukan atau Daftarkan Kendaraan
                $idKendaraan = 0;
                $jenisKendaraan = '';

                // Cek ulang kendaraan di DB
                $stmtCekK = $pdo->prepare("SELECT id_kendaraan, plat_nomor, jenis_kendaraan FROM tb_kendaraan WHERE LOWER(plat_nomor) = LOWER(?)");
                $stmtCekK->execute([$platPost]);
                $kRow = $stmtCekK->fetch();

                if ($kRow) {
                    $idKendaraan = (int)$kRow['id_kendaraan'];
                    $jenisKendaraan = $kRow['jenis_kendaraan'];
                } elseif ($isOtomatis) {
                    // Pendaftaran otomatis saat pertama kali masuk (Sesuai Aturan Sistem)
                    $jenisKendaraan = $_POST['jenis_kendaraan'] ?? 'motor';
                    if (!in_array($jenisKendaraan, ['motor', 'mobil', 'lainnya'], true)) {
                        $jenisKendaraan = 'motor';
                    }
                    $warna = trim($_POST['warna'] ?? '');
                    $pemilik = trim($_POST['pemilik'] ?? '');

                    $stmtInsertK = $pdo->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsertK->execute([$platPost, $jenisKendaraan, $warna ?: null, $pemilik ?: null, $userId]);
                    $idKendaraan = (int)$pdo->lastInsertId();

                    recordLog("Pendaftaran otomatis kendaraan baru saat masuk: {$platPost} ({$jenisKendaraan})", $userId);
                } else {
                    $pdo->rollBack();
                    $error = 'Data kendaraan tidak valid. Silakan lengkapi jenis kendaraan.';
                }

                if ($idKendaraan > 0) {
                    // 3. Kunci dan pastikan kendaraan belum memiliki transaksi aktif
                    $stmtLockTrx = $pdo->prepare("SELECT id_parkir FROM tb_transaksi WHERE id_kendaraan = ? AND status = 'masuk' FOR UPDATE");
                    $stmtLockTrx->execute([$idKendaraan]);
                    if ($stmtLockTrx->fetch()) {
                        $pdo->rollBack();
                        $error = "Kendaraan {$platPost} masih tercatat sedang parkir di dalam gedung (memiliki transaksi aktif)!";
                    } else {
                        // 4. Ambil snapshot tarif yang berlaku saat ini
                        $stmtTarif = $pdo->prepare("SELECT id_tarif, tarif_per_jam FROM tb_tarif WHERE jenis_kendaraan = ? LIMIT 1");
                        $stmtTarif->execute([$jenisKendaraan]);
                        $tarifData = $stmtTarif->fetch();

                        if (!$tarifData) {
                            $stmtTarifDefault = $pdo->query("SELECT id_tarif, tarif_per_jam FROM tb_tarif WHERE jenis_kendaraan = 'lainnya' LIMIT 1");
                            $tarifData = $stmtTarifDefault->fetch();
                        }

                        $idTarif = (int)$tarifData['id_tarif'];
                        $tarifSaatMasuk = (float)$tarifData['tarif_per_jam'];
                        $kodeTiket = generateKodeTiket();

                        // 5. Simpan transaksi masuk ke tb_transaksi
                        $stmtInsertTrx = $pdo->prepare("
                            INSERT INTO tb_transaksi 
                            (kode_tiket, id_kendaraan, waktu_masuk, id_tarif, tarif_per_jam_saat_masuk, status, status_pembayaran, id_user, id_area) 
                            VALUES (?, ?, NOW(), ?, ?, 'masuk', 'belum_bayar', ?, ?)
                        ");
                        $stmtInsertTrx->execute([$kodeTiket, $idKendaraan, $idTarif, $tarifSaatMasuk, $userId, $idArea]);
                        $newIdParkir = (int)$pdo->lastInsertId();

                        // 6. Tambahkan slot terisi pada area parkir
                        $stmtUpdateArea = $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = ?");
                        $stmtUpdateArea->execute([$idArea]);

                        // 7. Catat log aktivitas sistem
                        recordLog("Kendaraan masuk: {$platPost} ({$jenisKendaraan}) ke {$areaData['nama_area']} [Tiket: {$kodeTiket}]", $userId);

                        // 8. Commit transaksi seluruh operasi
                        $pdo->commit();

                        setFlashMessage('success', "Kendaraan {$platPost} berhasil masuk. Tiket siap dicetak.");
                        header('Location: ' . BASE_URL . '/petugas/tiket.php?id=' . $newIdParkir);
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error transaksi masuk: " . $e->getMessage());
            $error = 'Terjadi kegagalan transaksi basis data: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Transaksi Kendaraan Masuk</h4>
        <p class="text-muted small mb-0">Input plat nomor kendaraan yang masuk ke gerbang parkir</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/petugas/kendaraan_tambah.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="bi bi-plus-circle me-1"></i> Pendaftaran Manual
        </a>
        <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="btn btn-light btn-sm border rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 small py-3 d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Langkah 1: Input / Pencarian Plat Nomor -->
        <div class="card-custom p-4 mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-1-circle-fill text-primary me-2"></i>Identifikasi Plat Nomor Kendaraan</h6>
            <form method="GET" action="">
                <div class="input-group input-group-lg mb-2">
                    <span class="input-group-text bg-light"><i class="bi bi-car-front"></i></span>
                    <input type="text" class="form-control input-plat text-uppercase fw-bold" name="plat" 
                           placeholder="Contoh: B 1234 ABC" value="<?= htmlspecialchars($platQuery) ?>" required autofocus>
                    <button class="btn btn-primary px-4 fw-semibold" type="submit">Periksa Plat</button>
                </div>
                <div class="form-text small">Ketik plat nomor kendaraan di gerbang masuk. Kendaraan baru otomatis didaftarkan saat masuk.</div>
            </form>
        </div>

        <?php if ($platQuery !== ''): ?>
            <?php if ($transaksiAktif): ?>
                <!-- Kendaraan Sedang Parkir -->
                <div class="card-custom p-4 mb-4 border-danger">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="text-danger fs-1"><i class="bi bi-exclamation-octagon-fill"></i></div>
                        <div>
                            <h5 class="fw-bold text-danger mb-1">Peringatan: Kendaraan Masih Sedang Parkir!</h5>
                            <p class="text-muted small mb-0">Kendaraan <strong><?= htmlspecialchars($kendaraan['plat_nomor']) ?></strong> tercatat belum melakukan transaksi keluar.</p>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 small border mb-3">
                        <div>Nomor Tiket Aktif: <code><?= htmlspecialchars($transaksiAktif['kode_tiket']) ?></code></div>
                        <div>Waktu Masuk: <?= formatTanggalIndo($transaksiAktif['waktu_masuk']) ?></div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php?id=<?= $transaksiAktif['id_parkir'] ?>" class="btn btn-success flex-grow-1">
                            <i class="bi bi-box-arrow-right me-1"></i> Proses Transaksi Keluar Kendaraan Ini
                        </a>
                        <a href="<?= BASE_URL ?>/petugas/tiket.php?id=<?= $transaksiAktif['id_parkir'] ?>" class="btn btn-outline-primary" target="_blank">
                            <i class="bi bi-printer me-1"></i> Cetak Ulang Tiket
                        </a>
                    </div>
                </div>

            <?php elseif (!$kendaraan): ?>
                <!-- OPSI PENDAFTARAN OTOMATIS KENDARAAN BARU SAAT PERTAMA KALI MASUK -->
                <div class="card-custom p-4 border-warning">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-magic me-1"></i> Kendaraan Baru (Otomatis Didaftarkan)
                            </span>
                            <div class="mt-2">
                                <span class="plat-badge fs-5"><?= htmlspecialchars($platQuery) ?></span>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/petugas/kendaraan_tambah.php?plat=<?= urlencode($platQuery) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Atau gunakan formulir manual lengkap">
                            Formulir Manual
                        </a>
                    </div>

                    <p class="text-muted small mb-3">
                        Plat nomor <strong><?= htmlspecialchars($platQuery) ?></strong> belum terdaftar sebelumnya. Sistem akan otomatis menyimpannya ke master kendaraan saat tiket masuk diterbitkan.
                    </p>

                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="proses_masuk_otomatis" value="1">
                        <input type="hidden" name="plat_nomor" value="<?= htmlspecialchars($platQuery) ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label for="jenis_kendaraan" class="form-label small fw-semibold text-secondary">Jenis Kendaraan <span class="text-danger">*</span></label>
                                <select class="form-select" id="jenis_kendaraan" name="jenis_kendaraan" required>
                                    <option value="motor">Motor (Roda Dua)</option>
                                    <option value="mobil">Mobil (Roda Empat)</option>
                                    <option value="lainnya">Lainnya (Truk, Bus, dll)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="id_area" class="form-label small fw-semibold text-secondary">Pilih Area Parkir <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_area" name="id_area" required>
                                    <?php foreach ($areaList as $area): 
                                        $kap = (int)$area['kapasitas'];
                                        $ter = (int)$area['terisi'];
                                        $sisa = max(0, $kap - $ter);
                                        $isFull = ($sisa === 0);
                                        $isSelected = ($selectedAreaId > 0 && (int)$area['id_area'] === $selectedAreaId);
                                    ?>
                                        <option value="<?= $area['id_area'] ?>" <?= $isFull ? 'disabled class="text-danger"' : '' ?> <?= ($isSelected && !$isFull) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($area['nama_area']) ?> &mdash; Sisa <?= $sisa ?> Slot <?= $isFull ? '(PENUH)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="warna" class="form-label small fw-semibold text-secondary">Warna (Opsional)</label>
                                <input type="text" class="form-control" id="warna" name="warna" placeholder="Contoh: Hitam, Putih">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="pemilik" class="form-label small fw-semibold text-secondary">Nama Pemilik / Pengendara (Opsional)</label>
                                <input type="text" class="form-control" id="pemilik" name="pemilik" placeholder="Contoh: Tamu / Pelanggan">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold fs-6 shadow-sm">
                            <i class="bi bi-qr-code me-2"></i> Daftarkan Kendaraan & Cetak Tiket Masuk
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- KENDARAAN SUDAH TERDAFTAR SEBELUMNYA -->
                <div class="card-custom p-4 border-primary">
                    <h6 class="fw-bold mb-3"><i class="bi bi-2-circle-fill text-primary me-2"></i>Konfirmasi Kendaraan & Area Parkir</h6>

                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6 mb-2 mb-md-0">
                                <span class="plat-badge fs-5"><?= htmlspecialchars($kendaraan['plat_nomor']) ?></span>
                                <div class="mt-2 text-uppercase fw-bold text-dark">
                                    <i class="bi <?= ($kendaraan['jenis_kendaraan'] === 'motor') ? 'bi-bicycle' : 'bi-car-front' ?> text-primary me-1"></i>
                                    <?= htmlspecialchars($kendaraan['jenis_kendaraan']) ?> &bull; Warna: <?= htmlspecialchars($kendaraan['warna'] ?: '-') ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 text-md-end">
                                <small class="text-muted d-block">Pemilik / Pengendara:</small>
                                <strong class="text-dark"><?= htmlspecialchars($kendaraan['pemilik'] ?: 'Tamu Umum') ?></strong>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <input type="hidden" name="proses_masuk" value="1">
                        <input type="hidden" name="id_kendaraan" value="<?= $kendaraan['id_kendaraan'] ?>">
                        <input type="hidden" name="plat_nomor" value="<?= htmlspecialchars($kendaraan['plat_nomor']) ?>">

                        <div class="mb-4">
                            <label for="id_area" class="form-label small fw-semibold text-secondary">Pilih Area Parkir <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="id_area" name="id_area" required>
                                <?php foreach ($areaList as $area): 
                                    $kap = (int)$area['kapasitas'];
                                    $ter = (int)$area['terisi'];
                                    $sisa = max(0, $kap - $ter);
                                    $isFull = ($sisa === 0);
                                    $autoSelect = false;
                                    if ($selectedAreaId > 0 && (int)$area['id_area'] === $selectedAreaId) {
                                        $autoSelect = true;
                                    } elseif ($selectedAreaId === 0 && $kendaraan['jenis_kendaraan'] === 'motor' && stripos($area['nama_area'], 'motor') !== false) {
                                        $autoSelect = true;
                                    } elseif ($selectedAreaId === 0 && $kendaraan['jenis_kendaraan'] === 'mobil' && stripos($area['nama_area'], 'mobil') !== false) {
                                        $autoSelect = true;
                                    }
                                ?>
                                    <option value="<?= $area['id_area'] ?>" <?= $isFull ? 'disabled class="text-danger"' : '' ?> <?= ($autoSelect && !$isFull) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($area['nama_area']) ?> &mdash; Sisa <?= $sisa ?> Slot <?= $isFull ? '(PENUH)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Sistem menggunakan database locking untuk menjamin slot tidak terpakai ganda saat kapasitas sisa 1.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold fs-6 shadow-sm">
                            <i class="bi bi-qr-code me-2"></i> Cetak Tiket & Buka Palang Masuk
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
