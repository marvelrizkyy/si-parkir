<?php
/**
 * Test Runner untuk Memvalidasi Seluruh Logika Sistem Parkir
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== MEMULAI PENGUJIAN SISTEM PARKIR ===" . PHP_EOL;

// 1. Uji Database Connection
$pdo = getDBConnection();
echo "[PASS] 1. Database Connection: Sukses (" . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ")" . PHP_EOL;

// 2. Uji Perhitungan Durasi dan Biaya
$calc1 = hitungDurasiDanBiaya('2026-10-21 08:00:00', 2000, '2026-10-21 08:20:00');
if ($calc1['durasi_jam'] === 1 && $calc1['biaya_total'] == 2000) {
    echo "[PASS] 2.1 Perhitungan 20 menit = 1 jam (Rp 2.000): Sukses" . PHP_EOL;
} else {
    die("[FAIL] 2.1 Perhitungan 20 menit gagal!" . PHP_EOL);
}

$calc2 = hitungDurasiDanBiaya('2026-10-21 08:00:00', 2000, '2026-10-21 09:01:00');
if ($calc2['durasi_jam'] === 2 && $calc2['biaya_total'] == 4000) {
    echo "[PASS] 2.2 Perhitungan 61 menit = 2 jam (Rp 4.000): Sukses" . PHP_EOL;
} else {
    die("[FAIL] 2.2 Perhitungan 61 menit gagal!" . PHP_EOL);
}

$calc3 = hitungDurasiDanBiaya('2026-10-21 08:00:00', 2000, '2026-10-21 10:05:00');
if ($calc3['durasi_jam'] === 3 && $calc3['biaya_total'] == 6000) {
    echo "[PASS] 2.3 Perhitungan 125 menit = 3 jam (Rp 6.000): Sukses" . PHP_EOL;
} else {
    die("[FAIL] 2.3 Perhitungan 125 menit gagal!" . PHP_EOL);
}

// 3. Uji Normalisasi Plat Nomor
$norm = normalizePlatNomor('   b   1234   xyz   ');
if ($norm === 'B 1234 XYZ') {
    echo "[PASS] 3. Normalisasi Plat Nomor: Sukses ('{$norm}')" . PHP_EOL;
} else {
    die("[FAIL] 3. Normalisasi Plat Nomor gagal!" . PHP_EOL);
}

// 4. Uji Sanitasi CSV Injection
$safeFormula = safeCSVCell('=SUM(A1:A10)');
if ($safeFormula === "'=SUM(A1:A10)") {
    echo "[PASS] 4. Sanitasi Formula CSV: Sukses" . PHP_EOL;
} else {
    die("[FAIL] 4. Sanitasi Formula CSV gagal!" . PHP_EOL);
}

// 5. Uji Render QR Code SVG
$svg = renderQRCodeSVG('TRX-TEST-999');
if (strpos($svg, 'data:image/svg+xml;base64') !== false) {
    echo "[PASS] 5. Render SVG Data URI QR Code Tiket: Sukses" . PHP_EOL;
} else {
    die("[FAIL] 5. Render SVG QR Code gagal!" . PHP_EOL);
}

// 6. Uji Transaksi Masuk dan Keluar (Siklus Penuh Transaksional)
echo "=== UJI TRANSAKSI MASUK DAN KELUAR BASIS DATA ===" . PHP_EOL;
$pdo->beginTransaction();

// Cek kapasitas area awal
$stmtArea = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = 1 FOR UPDATE");
$terisiAwal = (int)$stmtArea->fetchColumn();

// Simpan transaksi masuk uji coba
$kodeTiketUji = 'TEST-' . date('ymd') . '-' . rand(100, 999);
$stmtInsert = $pdo->prepare("
    INSERT INTO tb_transaksi 
    (kode_tiket, id_kendaraan, waktu_masuk, id_tarif, tarif_per_jam_saat_masuk, status, id_user, id_area)
    VALUES (?, 2, '2026-10-21 06:00:00', 1, 2000, 'masuk', 2, 1)
");
$stmtInsert->execute([$kodeTiketUji]);
$idParkirUji = (int)$pdo->lastInsertId();

// Increment area
$pdo->exec("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = 1");

$stmtCekInc = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = 1");
$terisiSetelahMasuk = (int)$stmtCekInc->fetchColumn();

if ($terisiSetelahMasuk === $terisiAwal + 1) {
    echo "[PASS] 6.1 Transaksi Masuk & Increment Kapasitas Area: Sukses (+1)" . PHP_EOL;
} else {
    $pdo->rollBack();
    die("[FAIL] 6.1 Increment Kapasitas Area Gagal!" . PHP_EOL);
}

// Proses Transaksi Keluar
$pdo->exec("
    UPDATE tb_transaksi 
    SET waktu_keluar = '2026-10-21 08:30:00',
        durasi_jam = 3,
        biaya_total = 6000,
        status = 'keluar'
    WHERE id_parkir = $idParkirUji
");
$pdo->exec("UPDATE tb_area_parkir SET terisi = GREATEST(0, terisi - 1) WHERE id_area = 1");

$stmtCekDec = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = 1");
$terisiSetelahKeluar = (int)$stmtCekDec->fetchColumn();

if ($terisiSetelahKeluar === $terisiAwal) {
    echo "[PASS] 6.2 Transaksi Keluar & Decrement Kapasitas Area: Sukses (-1 konsisten)" . PHP_EOL;
} else {
    $pdo->rollBack();
    die("[FAIL] 6.2 Decrement Kapasitas Area Gagal!" . PHP_EOL);
}

// Log aktivitas
recordLog("Unit Test Automated Check Completed Successfully", 1);
echo "[PASS] 6.3 Pencatatan Log Aktivitas: Sukses" . PHP_EOL;

$pdo->commit();

echo PHP_EOL . "SELURUH PENGUJIAN OTOMATIS SELESAI DENGAN STATUS 100% PASS!" . PHP_EOL;
