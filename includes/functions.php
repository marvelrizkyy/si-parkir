<?php
/**
 * Kumpulan Fungsi Pembantu (Helper Functions)
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Format angka ke format mata uang Rupiah
 * @param float|int|null $nominal
 * @return string
 */
function formatRupiah($nominal): string {
    if ($nominal === null || $nominal === '') {
        return 'Rp 0';
    }
    return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
}

/**
 * Format tanggal ke format bahasa Indonesia
 * @param string|null $datetime
 * @param bool $withTime
 * @return string
 */
function formatTanggalIndo(?string $datetime, bool $withTime = true): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '-';
    }

    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $hari = date('d', $timestamp);
    $bulan = $bulanIndo[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    $hasil = "$hari $bulan $tahun";
    if ($withTime) {
        $hasil .= ', ' . date('H:i', $timestamp) . ' WIB';
    }
    return $hasil;
}

/**
 * Normalisasi Plat Nomor Kendaraan
 * Contoh: "b  1234   abc" -> "B 1234 ABC"
 * @param string $plat
 * @return string
 */
function normalizePlatNomor(string $plat): string {
    $plat = strtoupper(trim($plat));
    // Rapikan spasi ganda menjadi spasi tunggal
    $plat = preg_replace('/\s+/', ' ', $plat);
    return $plat;
}

/**
 * Menghasilkan kode tiket transaksi parkir yang unik
 * Format: TRX-YYMMDD-XXXX (contoh: TRX-261021-A9F2)
 * @return string
 */
function generateKodeTiket(): string {
    return 'TRX-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/**
 * Menghitung durasi parkir dalam jam dan total biaya
 * Aturan: Pembulatan ke atas per jam, durasi minimum 1 jam
 * Rumus: durasi_jam = max(1, ceil(durasi_menit / 60))
 *        biaya_total = durasi_jam * tarif_per_jam
 * 
 * @param string $waktuMasuk
 * @param float|int $tarifPerJam
 * @param string|null $waktuKeluar Jika null, gunakan waktu sekarang
 * @return array ['menit' => int, 'durasi_jam' => int, 'biaya_total' => float]
 */
function hitungDurasiDanBiaya(string $waktuMasuk, $tarifPerJam, ?string $waktuKeluar = null): array {
    $tMasuk = strtotime($waktuMasuk);
    $tKeluar = $waktuKeluar ? strtotime($waktuKeluar) : time();
    
    $selisihDetik = max(0, $tKeluar - $tMasuk);
    $durasiMenit = (int)ceil($selisihDetik / 60.0);
    
    // Pembulatan ke atas per jam dengan minimum 1 jam
    $durasiJam = (int)ceil($durasiMenit / 60.0);
    if ($durasiJam < 1) {
        $durasiJam = 1;
    }
    
    $biayaTotal = $durasiJam * (float)$tarifPerJam;
    
    return [
        'durasi_menit' => $durasiMenit,
        'durasi_jam'   => $durasiJam,
        'biaya_total'  => $biayaTotal
    ];
}

/**
 * Menghasilkan elemen QR Code dalam format SVG Data URI untuk ditampilkan di web atau struk thermal
 * @param string $data
 * @param int $scale
 * @return string
 */
function renderQRCodeSVG(string $data, int $scale = 4): string {
    try {
        if (!class_exists('chillerlan\QRCode\QRCode')) {
            return '<div class="alert alert-warning p-2 small">Library QR Code belum terpasang.</div>';
        }
        $qrcode = new QRCode();
        $dataUri = $qrcode->render($data);
        return '<img src="' . $dataUri . '" alt="QR Code Tiket" style="max-width: 150px; max-height: 150px; display: block; margin: 0 auto;">';
    } catch (Exception $e) {
        return '<div class="text-danger small">Gagal memuat QR Code: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Mengatur pesan kilat (flash message)
 * @param string $type success | danger | warning | info
 * @param string $message
 */
function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Menampilkan dan menghapus flash message dari session
 * @return string
 */
function displayFlashMessages(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $output = '';
    
    // Cek flash_message
    if (!empty($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $type = htmlspecialchars($msg['type']);
        $text = htmlspecialchars($msg['message']);
        $icon = ($type === 'success') ? 'check-circle-fill' : (($type === 'danger') ? 'exclamation-triangle-fill' : 'info-circle-fill');
        
        $output .= "
        <div class='alert alert-{$type} alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4' role='alert'>
            <i class='bi bi-{$icon} me-2 fs-5'></i>
            <div>{$text}</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
        unset($_SESSION['flash_message']);
    }
    
    // Cek flash_error sederhana
    if (!empty($_SESSION['flash_error'])) {
        $text = htmlspecialchars($_SESSION['flash_error']);
        $output .= "
        <div class='alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4' role='alert'>
            <i class='bi bi-exclamation-triangle-fill me-2 fs-5'></i>
            <div>{$text}</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
        unset($_SESSION['flash_error']);
    }
    
    return $output;
}

/**
 * Membersihkan input form
 * @param mixed $input
 * @return string
 */
function cleanInput($input): string {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Mengamankan cell CSV dari Spreadsheet Formula Injection (CSV Injection)
 * Formula dimulai dengan =, +, -, @
 * @param mixed $value
 * @return string
 */
function safeCSVCell($value): string {
    $str = (string)$value;
    if ($str !== '' && in_array($str[0], ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * Helper alias untuk setFlashMessage
 */
function setFlash(string $type, string $message): void {
    setFlashMessage($type, $message);
}

/**
 * Helper redirect URL dengan exit
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Helper alias untuk verifyCSRFOrDie
 */
function validateCSRF(): void {
    verifyCSRFOrDie();
}

