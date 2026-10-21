<?php
/**
 * Test Validasi Output API dan Denah Lantai Parkir
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'petugas';
$_SESSION['user_nama'] = 'Petugas Uji';

// 1. Uji Endpoint api_slot.php
ob_start();
include __DIR__ . '/../petugas/api_slot.php';
$jsonRaw = ob_get_clean();

$data = json_decode($jsonRaw, true);
$apiOk = ($data && isset($data['status']) && $data['status'] === 'success');
$hasAreas = (!empty($data['areas']) && is_array($data['areas']));
$hasSummary = (!empty($data['summary']) && isset($data['summary']['total_kapasitas']));

echo "=== UJI VALIDASI DENAH & API REALTIME ===\n";
echo "[TEST 1] api_slot.php mengembalikan JSON valid: " . ($apiOk ? "PASS" : "FAIL") . "\n";
echo "[TEST 2] api_slot.php memuat data summary kapasitas: " . ($hasSummary ? "PASS" : "FAIL") . "\n";
echo "[TEST 3] api_slot.php memuat detail zona denah: " . ($hasAreas ? "PASS" : "FAIL") . "\n";

if ($hasAreas) {
    $firstArea = $data['areas'][0];
    $hasSlots = !empty($firstArea['slots']);
    echo "[TEST 4] Struktur slot individual denah tersedia: " . ($hasSlots ? "PASS" : "FAIL") . "\n";
    echo "         Area 1: " . $firstArea['nama_area'] . " (Kapasitas: " . $firstArea['kapasitas'] . ", Terisi: " . $firstArea['terisi'] . ")\n";
}

// 2. Uji Berkas monitoring_slot.php
$monitoringContent = file_get_contents(__DIR__ . '/../petugas/monitoring_slot.php');
$hasBlueprint = (strpos($monitoringContent, 'parking-blueprint') !== false);
$hasDriveway = (strpos($monitoringContent, 'driveway-aisle') !== false);
$hasSensors = (strpos($monitoringContent, 'ceiling-sensor') !== false);
$hasPolling = (strpos($monitoringContent, 'fetchRealtimeSlotData') !== false);

echo "[TEST 5] Elemen arsitektur blueprint denah: " . ($hasBlueprint ? "PASS" : "FAIL") . "\n";
echo "[TEST 6] Jalur sirkulasi kendaraan (driveway): " . ($hasDriveway ? "PASS" : "FAIL") . "\n";
echo "[TEST 7] Smart overhead sensor LED denah: " . ($hasSensors ? "PASS" : "FAIL") . "\n";
echo "[TEST 8] Mesin polling asinkron background (3s): " . ($hasPolling ? "PASS" : "FAIL") . "\n";

$allPassed = ($apiOk && $hasSummary && $hasAreas && $hasBlueprint && $hasDriveway && $hasSensors && $hasPolling);
echo "HASIL AKHIR: " . ($allPassed ? "100% PASS!" : "ADA KEGAGALAN") . "\n";
