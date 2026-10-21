<?php
/**
 * API JSON: Status Real-Time Slot & Denah Parkir
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 *
 * Mengembalikan data status terisi dan kosong seluruh slot per area parkir
 * secara real-time untuk polling frontend tanpa reload halaman.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verifikasi sesi dan hak akses (Petugas, Admin, Owner diizinkan)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUserRole = $_SESSION['user_role'] ?? '';
if (!in_array($currentUserRole, ['petugas', 'admin', 'owner'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

try {
    $pdo = getDBConnection();

    // 1. Ambil data area
    $stmtArea = $pdo->query("
        SELECT a.*, 
               (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS terisi_aktual
        FROM tb_area_parkir a
        ORDER BY a.nama_area ASC
    ");
    $areaList = $stmtArea->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil transaksi aktif yang sedang mengisi slot
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
    $activeTransactions = $stmtTrx->fetchAll(PDO::FETCH_ASSOC);

    // Kelompokkan transaksi aktif per id_area
    $parkedByArea = [];
    foreach ($activeTransactions as $t) {
        $parkedByArea[$t['id_area']][] = $t;
    }

    $totalKapasitas = 0;
    $totalTerisi = 0;
    foreach ($areaList as $a) {
        $totalKapasitas += (int)$a['kapasitas'];
        $totalTerisi += (int)$a['terisi_aktual'];
    }
    $totalKosong = max(0, $totalKapasitas - $totalTerisi);
    $persenOkupansi = $totalKapasitas > 0 ? min(100, round(($totalTerisi / $totalKapasitas) * 100)) : 0;

    // Helper prefix
    function getPrefixArea($namaArea, $index) {
        if (preg_match('/Area\s+([A-Za-z0-9]+)/i', $namaArea, $m)) {
            return strtoupper($m[1]);
        }
        $clean = trim($namaArea);
        return strtoupper(substr($clean, 0, 1)) . ($index + 1);
    }

    // Bangun struktur detail area dan slot individual untuk denah
    $areasResponse = [];
    foreach ($areaList as $index => $area) {
        $idArea = (int)$area['id_area'];
        $kapasitas = max(1, (int)$area['kapasitas']);
        $terisi = (int)$area['terisi_aktual'];
        $kosong = max(0, $kapasitas - $terisi);
        $persenArea = min(100, round(($terisi / $kapasitas) * 100));
        $prefix = getPrefixArea($area['nama_area'], $index);

        $parked = $parkedByArea[$idArea] ?? [];

        // Bagi slot menjadi 2 baris denah (Baris Atas & Baris Bawah)
        $half = (int)ceil($kapasitas / 2.0);

        $slots = [];
        for ($s = 1; $s <= $kapasitas; $s++) {
            $slotCode = $prefix . '-' . str_pad($s, 2, '0', STR_PAD_LEFT);
            $isOccupied = ($s <= count($parked));
            $rowNumber = ($s <= $half) ? 1 : 2;

            $slotData = [
                'slot_num'    => $s,
                'slot_code'   => $slotCode,
                'row'         => $rowNumber,
                'is_occupied' => $isOccupied,
                'transaksi'   => null
            ];

            if ($isOccupied) {
                $t = $parked[$s - 1];
                $calc = hitungDurasiDanBiaya($t['waktu_masuk'], $t['tarif_per_jam_saat_masuk']);
                $menit = $calc['durasi_menit'];
                $durasiHuman = ($menit >= 60) ? floor($menit / 60) . 'j ' . ($menit % 60) . 'm' : $menit . ' mnt';

                $slotData['transaksi'] = [
                    'id_parkir'         => (int)$t['id_parkir'],
                    'kode_tiket'        => $t['kode_tiket'],
                    'plat_nomor'        => $t['plat_nomor'],
                    'jenis_kendaraan'   => $t['jenis_kendaraan'],
                    'warna'             => $t['warna'] ?? '-',
                    'pemilik'           => $t['pemilik'] ?? '-',
                    'waktu_masuk'       => $t['waktu_masuk'],
                    'waktu_masuk_indo'  => formatTanggalIndo($t['waktu_masuk'], true),
                    'durasi_menit'      => $menit,
                    'durasi_jam'        => $calc['durasi_jam'],
                    'durasi_human'      => $durasiHuman,
                    'tarif_per_jam'     => (float)$t['tarif_per_jam_saat_masuk'],
                    'tarif_per_jam_rp'  => formatRupiah($t['tarif_per_jam_saat_masuk']),
                    'biaya_estimasi'    => (float)$calc['biaya_total'],
                    'biaya_estimasi_rp' => formatRupiah($calc['biaya_total']),
                    'nama_petugas'      => $t['nama_petugas'] ?? 'Sistem'
                ];
            }

            $slots[] = $slotData;
        }

        $areasResponse[] = [
            'id_area'       => $idArea,
            'nama_area'     => $area['nama_area'],
            'prefix'        => $prefix,
            'kapasitas'     => $kapasitas,
            'terisi'        => $terisi,
            'kosong'        => $kosong,
            'persen'        => $persenArea,
            'status_badge'  => ($persenArea >= 100) ? 'PENUH' : (($persenArea >= 80) ? 'HAMPIR PENUH' : 'TERSEDIA'),
            'slots'         => $slots
        ];
    }

    echo json_encode([
        'status'     => 'success',
        'server_time'=> date('H:i:s'),
        'timestamp'  => date('Y-m-d H:i:s'),
        'summary'    => [
            'total_kapasitas' => $totalKapasitas,
            'total_terisi'    => $totalTerisi,
            'total_kosong'    => $totalKosong,
            'persen_okupansi' => $persenOkupansi
        ],
        'areas'      => $areasResponse
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal mengambil data real-time: ' . $e->getMessage()
    ]);
}
