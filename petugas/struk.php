<?php
/**
 * Struk Thermal Pembayaran Parkir (Ukuran 58mm / 80mm)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    WHERE t.id_parkir = ?
");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t || $t['status'] !== 'keluar') {
    setFlashMessage('danger', 'Struk hanya dapat dicetak untuk transaksi yang telah selesai (status keluar).');
    header('Location: ' . BASE_URL . '/petugas/dashboard.php');
    exit;
}

$metode = strtoupper($t['metode_pembayaran'] ?? 'TUNAI');
$jumlahBayar = (float)($t['jumlah_bayar'] ?? $t['biaya_total']);
$kembalian = (float)($t['kembalian'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - <?= htmlspecialchars($t['kode_tiket']) ?></title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body {
            background-color: #f1f5f9;
        }
        .width-80mm {
            max-width: 80mm !important;
            width: 380px !important;
        }
        .width-58mm {
            max-width: 58mm !important;
            width: 300px !important;
        }
    </style>
</head>
<body>

<!-- Panel Kontrol Aksi (Disembunyikan saat cetak) -->
<div class="container py-4 no-print text-center">
    <div class="d-inline-flex flex-wrap gap-2 p-2 bg-white rounded-pill shadow-sm border align-items-center">
        <!-- Opsi Ukuran Kertas Thermal -->
        <div class="btn-group btn-group-sm me-2" role="group">
            <button type="button" class="btn btn-outline-dark active" id="btn-58" onclick="setPaperWidth('58')">58 mm</button>
            <button type="button" class="btn btn-outline-dark" id="btn-80" onclick="setPaperWidth('80')">80 mm</button>
        </div>

        <button onclick="window.print()" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold">
            <i class="bi bi-printer-fill me-1"></i> Cetak Struk Thermal
        </button>
        <a href="<?= BASE_URL ?>/petugas/transaksi_keluar.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Transaksi Keluar Baru
        </a>
        <a href="<?= BASE_URL ?>/petugas/dashboard.php" class="btn btn-light btn-sm rounded-pill px-3 border">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Layout Struk Thermal (58mm / 80mm) -->
<div class="thermal-receipt-container width-58mm" id="receipt-box">
    <div class="receipt-header">
        <div class="receipt-title"><?= APP_NAME ?></div>
        <div class="receipt-subtitle"><?= APP_LOCATION ?></div>
        <div class="receipt-divider"></div>
        <div style="font-weight: 800; font-size: 13px; text-transform: uppercase;">BUKTI PEMBAYARAN PARKIR</div>
    </div>

    <div class="receipt-row">
        <span class="label">NO. TRANSAKSI:</span>
        <span class="value"><?= htmlspecialchars($t['kode_tiket']) ?></span>
    </div>
    <div class="receipt-row">
        <span class="label">PLAT NOMOR   :</span>
        <span class="value" style="font-size: 13px;"><?= htmlspecialchars($t['plat_nomor']) ?></span>
    </div>
    <div class="receipt-row">
        <span class="label">JENIS        :</span>
        <span class="value text-uppercase"><?= htmlspecialchars($t['jenis_kendaraan']) ?></span>
    </div>
    <div class="receipt-row">
        <span class="label">AREA PARKIR  :</span>
        <span class="value"><?= htmlspecialchars($t['nama_area']) ?></span>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row">
        <span class="label">WAKTU MASUK  :</span>
        <span class="value"><?= date('d/m/Y H:i', strtotime($t['waktu_masuk'])) ?></span>
    </div>
    <div class="receipt-row">
        <span class="label">WAKTU KELUAR :</span>
        <span class="value"><?= date('d/m/Y H:i', strtotime($t['waktu_keluar'])) ?></span>
    </div>
    <div class="receipt-row">
        <span class="label">DURASI PARKIR:</span>
        <span class="value"><?= $t['durasi_jam'] ?> JAM</span>
    </div>
    <div class="receipt-row">
        <span class="label">TARIF/JAM    :</span>
        <span class="value"><?= formatRupiah($t['tarif_per_jam_saat_masuk']) ?></span>
    </div>

    <div class="receipt-double-divider"></div>

    <div class="receipt-row total">
        <span class="label">TOTAL BIAYA  :</span>
        <span class="value"><?= formatRupiah($t['biaya_total']) ?></span>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row">
        <span class="label">METODE BAYAR :</span>
        <span class="value fw-bold"><?= $metode ?></span>
    </div>
    <?php if ($t['metode_pembayaran'] === 'tunai'): ?>
        <div class="receipt-row">
            <span class="label">DITERIMA     :</span>
            <span class="value"><?= formatRupiah($jumlahBayar) ?></span>
        </div>
        <div class="receipt-row">
            <span class="label">KEMBALIAN    :</span>
            <span class="value"><?= formatRupiah($kembalian) ?></span>
        </div>
    <?php else: ?>
        <div class="receipt-row">
            <span class="label">STATUS BAYAR :</span>
            <span class="value text-uppercase fw-bold">LUNAS (NON-TUNAI)</span>
        </div>
    <?php endif; ?>

    <div class="receipt-double-divider"></div>

    <div class="receipt-footer">
        <div>Petugas : <?= htmlspecialchars($t['nama_petugas']) ?></div>
        <div>Dicetak : <?= date('d/m/Y H:i:s') ?> WIB</div>
        <div style="margin-top: 8px; font-weight: 700;">TERIMA KASIH ATAS KUNJUNGAN ANDA</div>
        <div style="font-size: 10px;">Semoga perjalanan Anda aman dan menyenangkan</div>
    </div>
</div>

<script>
function setPaperWidth(size) {
    const box = document.getElementById('receipt-box');
    const btn58 = document.getElementById('btn-58');
    const btn80 = document.getElementById('btn-80');

    if (size === '80') {
        box.classList.remove('width-58mm');
        box.classList.add('width-80mm');
        btn80.classList.add('active');
        btn58.classList.remove('active');
    } else {
        box.classList.remove('width-80mm');
        box.classList.add('width-58mm');
        btn58.classList.add('active');
        btn80.classList.remove('active');
    }
}
</script>

</body>
</html>
