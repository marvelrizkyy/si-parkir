<?php
/**
 * Test HTTP Flow: Login, Session, Dashboard, and Redirects
 */

$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. GET login page and grab CSRF token & cookie
$ch = curl_init('http://localhost/parkir/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
curl_close($ch);

if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m)) {
    die("[FAIL] CSRF token not found in login page." . PHP_EOL);
}
$csrf = $m[1];
echo "[PASS] 1. Berhasil mengambil CSRF token: " . substr($csrf, 0, 10) . "..." . PHP_EOL;

// 2. POST login credentials (admin / admin123)
$ch = curl_init('http://localhost/parkir/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'username'   => 'admin',
    'password'   => 'admin123'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 302 && strpos($response, 'Location: http://localhost/parkir/admin/dashboard.php') !== false) {
    echo "[PASS] 2. Login Admin Berhasil! HTTP 302 Redirect ke /admin/dashboard.php" . PHP_EOL;
} else {
    die("[FAIL] Login Admin gagal. Response: " . substr($response, 0, 300) . PHP_EOL);
}

// 3. Akses Dashboard Admin dengan cookie login
$ch = curl_init('http://localhost/parkir/admin/dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$dashHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && strpos($dashHtml, 'Dashboard Administrator') !== false) {
    echo "[PASS] 3. Dashboard Admin berhasil diakses dengan sesi terotentikasi." . PHP_EOL;
} else {
    die("[FAIL] Gagal memuat dashboard admin." . PHP_EOL);
}

// Bersihkan file cookie
if (file_exists($cookieFile)) unlink($cookieFile);

function testRoleLogin($user, $pass, $expectedDash) {
    $cookie = __DIR__ . "/ck_{$user}.txt";
    if (file_exists($cookie)) unlink($cookie);

    $ch = curl_init('http://localhost/parkir/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    $html = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
    $csrf = $m[1] ?? '';

    $ch = curl_init('http://localhost/parkir/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'username'   => $user,
        'password'   => $pass
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $res = curl_exec($ch);
    curl_close($ch);

    if (strpos($res, $expectedDash) !== false) {
        echo "[PASS] Login {$user} berhasil dialihkan ke {$expectedDash}" . PHP_EOL;
    } else {
        echo "[FAIL] Login {$user} gagal!" . PHP_EOL;
    }
    if (file_exists($cookie)) unlink($cookie);
}

testRoleLogin('petugas', 'petugas123', 'petugas/dashboard.php');
testRoleLogin('owner', 'owner123', 'owner/dashboard.php');

echo PHP_EOL . "=== SELURUH PENGUJIAN HTTP BROWSER SUKSES! ===" . PHP_EOL;
