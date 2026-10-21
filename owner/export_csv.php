<?php
/**
 * Ekspor Data & Laporan ke Format CSV Terproteksi
 * Mendukung Ekspor: Laporan Pendapatan, Riwayat Transaksi, Data Kendaraan, Rekap Area
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Hanya peran owner dan admin yang diizinkan mengunduh data CSV
requireRole(['owner', 'admin']);

$pdo = getDBConnection();
$type = trim($_GET['type'] ?? 'laporan');

// -------------------------------------------------------------
// EKSPOR 1: DATA KENDARAAN (type=kendaraan)
// -------------------------------------------------------------
if ($type === 'kendaraan') {
    $search = trim($_GET['search'] ?? '');
    $filterJenis = trim($_GET['jenis'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');

    $params = [];
    $where = [];
    if ($search !== '') {
        $where[] = "(k.plat_nomor LIKE ? OR k.pemilik LIKE ? OR k.warna LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($filterJenis !== '' && in_array($filterJenis, ['motor', 'mobil', 'lainnya'], true)) {
        $where[] = "k.jenis_kendaraan = ?";
        $params[] = $filterJenis;
    }
    if ($filterStatus === 'parkir') {
        $where[] = "EXISTS (SELECT 1 FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk')";
    } elseif ($filterStatus === 'luar') {
        $where[] = "NOT EXISTS (SELECT 1 FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk')";
    }

    $whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $query = "
        SELECT k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               (SELECT a.nama_area FROM tb_transaksi t JOIN tb_area_parkir a ON t.id_area = a.id_area WHERE t.id_kendaraan = k.id_kendaraan AND t.status = 'masuk' LIMIT 1) AS area_aktif,
               (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan) AS total_kunjungan,
               (SELECT MAX(t.waktu_masuk) FROM tb_transaksi t WHERE t.id_kendaraan = k.id_kendaraan) AS terakhir_masuk,
               u.nama_lengkap AS nama_pendaftar
        FROM tb_kendaraan k
        LEFT JOIN tb_user u ON k.id_user = u.id_user
        $whereSQL
        ORDER BY k.id_kendaraan DESC
        LIMIT 5000
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    recordLog("Mengekspor direktori data kendaraan ke CSV.");

    $filename = "rekap_kendaraan_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    $headers = [
        'No',
        'Nomor Polisi (Plat)',
        'Jenis Kendaraan',
        'Warna',
        'Pemilik',
        'Status Parkir Saat Ini',
        'Total Riwayat Kunjungan',
        'Kunjungan Terakhir',
        'Didaftarkan Oleh'
    ];
    fputcsv($output, $headers, ';');

    $no = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusParkir = !empty($row['area_aktif']) ? 'SEDANG PARKIR (' . $row['area_aktif'] . ')' : 'DI LUAR AREA';
        $line = [
            $no++,
            safeCSVCell($row['plat_nomor']),
            strtoupper($row['jenis_kendaraan']),
            safeCSVCell($row['warna'] ?: '-'),
            safeCSVCell($row['pemilik'] ?: 'Tamu Umum'),
            $statusParkir,
            (int)$row['total_kunjungan'],
            $row['terakhir_masuk'] ?: '-',
            safeCSVCell($row['nama_pendaftar'] ?: 'Sistem')
        ];
        fputcsv($output, $line, ';');
    }
    fclose($output);
    exit;
}

// -------------------------------------------------------------
// EKSPOR 2: REKAP AREA PARKIR (type=area)
// -------------------------------------------------------------
if ($type === 'area') {
    $query = "
        SELECT a.nama_area, a.kapasitas, a.terisi,
               (a.kapasitas - a.terisi) AS sisa_slot,
               ROUND((a.terisi / a.kapasitas) * 100, 1) AS persen_okupansi,
               (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS aktif_parkir,
               (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area) AS total_transaksi
        FROM tb_area_parkir a
        ORDER BY a.nama_area ASC
    ";
    $stmt = $pdo->query($query);

    recordLog("Mengekspor rekapitulasi area parkir ke CSV.");

    $filename = "rekap_area_parkir_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    $headers = [
        'No',
        'Nama Area Parkir',
        'Kapasitas Total (Slot)',
        'Terisi Saat Ini (Slot)',
        'Slot Tersedia',
        'Okupansi (%)',
        'Status Area',
        'Transaksi Aktif Saat Ini',
        'Total Riwayat Pelayanan'
    ];
    fputcsv($output, $headers, ';');

    $no = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $persen = (float)$row['persen_okupansi'];
        $statusArea = ($persen >= 100) ? 'PENUH' : (($persen >= 80) ? 'HAMPIR PENUH' : 'TERSEDIA');
        $line = [
            $no++,
            safeCSVCell($row['nama_area']),
            (int)$row['kapasitas'],
            (int)$row['terisi'],
            max(0, (int)$row['sisa_slot']),
            $persen . '%',
            $statusArea,
            (int)$row['aktif_parkir'],
            (int)$row['total_transaksi']
        ];
        fputcsv($output, $line, ';');
    }
    fclose($output);
    exit;
}

// -------------------------------------------------------------
// EKSPOR 3 & 4: LAPORAN PENDAPATAN & RIWAYAT TRANSAKSI (type=laporan / type=transaksi)
// -------------------------------------------------------------
$searchPlat = trim($_GET['plat'] ?? $_GET['search'] ?? '');
$filterArea = (int)($_GET['area'] ?? 0);
$filterJenis = trim($_GET['jenis'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterMetode = trim($_GET['metode'] ?? '');
$filterStatusBayar = trim($_GET['status_bayar'] ?? '');
$filterTglMulai = trim($_GET['tgl_mulai'] ?? '');
$filterTglSelesai = trim($_GET['tgl_selesai'] ?? '');

$params = [];
$whereClauses = [];

if ($searchPlat !== '') {
    $whereClauses[] = "(k.plat_nomor LIKE ? OR t.kode_tiket LIKE ?)";
    $params[] = "%$searchPlat%";
    $params[] = "%$searchPlat%";
}

if ($filterArea > 0) {
    $whereClauses[] = "t.id_area = ?";
    $params[] = $filterArea;
}

if ($filterJenis !== '' && in_array($filterJenis, ['motor', 'mobil', 'lainnya'], true)) {
    $whereClauses[] = "k.jenis_kendaraan = ?";
    $params[] = $filterJenis;
}

if ($filterStatus !== '' && in_array($filterStatus, ['masuk', 'keluar'], true)) {
    $whereClauses[] = "t.status = ?";
    $params[] = $filterStatus;
}

if ($filterMetode !== '' && in_array($filterMetode, ['tunai', 'non-tunai'], true)) {
    $whereClauses[] = "t.metode_pembayaran = ?";
    $params[] = $filterMetode;
}

if ($filterStatusBayar !== '' && in_array($filterStatusBayar, ['lunas', 'belum_bayar'], true)) {
    $whereClauses[] = "t.status_pembayaran = ?";
    $params[] = $filterStatusBayar;
}

if ($filterTglMulai !== '') {
    $whereClauses[] = "DATE(t.waktu_masuk) >= ?";
    $params[] = $filterTglMulai;
}

if ($filterTglSelesai !== '') {
    $whereClauses[] = "DATE(t.waktu_masuk) <= ?";
    $params[] = $filterTglSelesai;
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$query = "
    SELECT t.kode_tiket, k.plat_nomor, k.jenis_kendaraan, k.pemilik,
           a.nama_area, t.waktu_masuk, t.waktu_keluar, t.durasi_jam,
           t.tarif_per_jam_saat_masuk, t.biaya_total, t.status,
           t.metode_pembayaran, t.jumlah_bayar, t.kembalian, t.status_pembayaran,
           u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_user u ON t.id_user = u.id_user
    $whereSQL
    ORDER BY t.id_parkir ASC
    LIMIT 5000
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

recordLog("Mengekspor laporan/riwayat transaksi parkir ke CSV.");

$prefix = ($type === 'transaksi') ? 'riwayat_transaksi_' : 'laporan_pendapatan_';
$filename = $prefix . date('Y-m-d_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF");

$headers = [
    'No',
    'Nomor Tiket',
    'Plat Nomor',
    'Jenis Kendaraan',
    'Pemilik',
    'Area Parkir',
    'Waktu Masuk',
    'Waktu Keluar',
    'Durasi (Jam)',
    'Tarif Per Jam (Rp)',
    'Total Biaya (Rp)',
    'Metode Pembayaran',
    'Jumlah Bayar (Rp)',
    'Kembalian (Rp)',
    'Status Pembayaran',
    'Status Parkir',
    'Petugas Kasir'
];
fputcsv($output, $headers, ';');

$no = 1;
$totalPendapatanLunas = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $biaya = (float)($row['biaya_total'] ?? 0);
    $statusBayar = strtoupper($row['status_pembayaran'] ?: 'BELUM_BAYAR');
    if ($statusBayar === 'LUNAS') {
        $totalPendapatanLunas += $biaya;
    }

    $line = [
        $no++,
        safeCSVCell($row['kode_tiket']),
        safeCSVCell($row['plat_nomor']),
        strtoupper($row['jenis_kendaraan']),
        safeCSVCell($row['pemilik'] ?: 'Tamu Umum'),
        safeCSVCell($row['nama_area']),
        $row['waktu_masuk'],
        $row['waktu_keluar'] ?: '-',
        $row['durasi_jam'] ?? 0,
        (float)$row['tarif_per_jam_saat_masuk'],
        $biaya,
        strtoupper($row['metode_pembayaran'] ?: '-'),
        (float)($row['jumlah_bayar'] ?? 0),
        (float)($row['kembalian'] ?? 0),
        $statusBayar,
        strtoupper($row['status']),
        safeCSVCell($row['nama_petugas'])
    ];
    fputcsv($output, $line, ';');
}

// Tambahkan baris total rekapitulasi di akhir file CSV
$summaryRow = [
    '',
    'TOTAL PENDAPATAN LUNAS TERKONFIRMASI',
    '', '', '', '', '', '', '', '',
    $totalPendapatanLunas,
    '', '', '', '', '', ''
];
fputcsv($output, $summaryRow, ';');

fclose($output);
exit;
