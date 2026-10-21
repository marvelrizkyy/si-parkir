<?php
/**
 * API Publik: Status Slot Parkir (Read-Only, tanpa autentikasi)
 * Untuk dipakai di halaman welcome.php
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDBConnection();

    $stmtArea = $pdo->query("
        SELECT a.*, 
               (SELECT COUNT(*) FROM tb_transaksi t WHERE t.id_area = a.id_area AND t.status = 'masuk') AS terisi_aktual
        FROM tb_area_parkir a
        ORDER BY a.nama_area ASC
    ");
    $areaList = $stmtArea->fetchAll(PDO::FETCH_ASSOC);

    $stmtTrx = $pdo->query("
        SELECT t.id_area, t.waktu_masuk, k.plat_nomor, k.jenis_kendaraan
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        WHERE t.status = 'masuk'
        ORDER BY t.waktu_masuk ASC
    ");
    $activeTransactions = $stmtTrx->fetchAll(PDO::FETCH_ASSOC);

    $parkedByArea = [];
    foreach ($activeTransactions as $t) {
        $parkedByArea[$t['id_area']][] = $t;
    }

    $totalKapasitas = 0;
    $totalTerisi = 0;

    $areas = [];
    foreach ($areaList as $index => $area) {
        $idArea = (int)$area['id_area'];
        $kapasitas = max(1, (int)$area['kapasitas']);
        $terisi = (int)$area['terisi_aktual'];
        $kosong = max(0, $kapasitas - $terisi);
        $totalKapasitas += $kapasitas;
        $totalTerisi += $terisi;

        // Prefix dari nama area
        if (preg_match('/Area\s+([A-Za-z0-9]+)/i', $area['nama_area'], $m)) {
            $prefix = strtoupper($m[1]);
        } else {
            $prefix = strtoupper(substr(trim($area['nama_area']), 0, 1)) . ($index + 1);
        }

        $parked = $parkedByArea[$idArea] ?? [];

        // Generate slot grid
        $slots = [];
        for ($s = 1; $s <= $kapasitas; $s++) {
            $code = $prefix . str_pad($s, 2, '0', STR_PAD_LEFT);
            $occupied = ($s <= count($parked));
            $slots[] = [
                'code' => $code,
                'occupied' => $occupied,
                'plat' => $occupied ? $parked[$s - 1]['plat_nomor'] : null,
                'jenis' => $occupied ? $parked[$s - 1]['jenis_kendaraan'] : null,
            ];
        }

        $areas[] = [
            'id' => $idArea,
            'nama' => $area['nama_area'],
            'prefix' => $prefix,
            'kapasitas' => $kapasitas,
            'terisi' => $terisi,
            'kosong' => $kosong,
            'slots' => $slots,
        ];
    }

    echo json_encode([
        'ok' => true,
        'waktu' => date('H:i:s'),
        'total_kapasitas' => $totalKapasitas,
        'total_terisi' => $totalTerisi,
        'total_kosong' => max(0, $totalKapasitas - $totalTerisi),
        'areas' => $areas,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
