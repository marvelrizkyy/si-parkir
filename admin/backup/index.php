<?php
/**
 * Modul Backup & Restore Database Manual & Manajemen Berkas Cadangan
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

// Pastikan hanya Administrator yang memiliki hak akses
requireRole('admin');

$pdo = getDBConnection();
$backupDir = realpath(__DIR__ . '/../../backup');
if (!$backupDir || !is_dir($backupDir)) {
    $backupDir = __DIR__ . '/../../backup';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
}

/**
 * Eksekusi berkas SQL dump ke database secara aman
 * Menangani UTF-8 BOM, UTF-16LE, UTF-16BE, dan MariaDB syntax dump
 */
function executeSqlDump(PDO $pdo, string $sql): array {
    // 1. Normalisasi Encoding (Tangani UTF-16LE, UTF-16BE, dan UTF-8 BOM)
    if (substr($sql, 0, 2) === "\xFF\xFE") {
        $sql = mb_convert_encoding($sql, 'UTF-8', 'UTF-16LE');
    } elseif (substr($sql, 0, 2) === "\xFE\xFF") {
        $sql = mb_convert_encoding($sql, 'UTF-8', 'UTF-16BE');
    }
    // Hapus UTF-8 BOM jika ada
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

    // 2. Nonaktifkan foreign key checks dan set mode SQL aman
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

    $lines = explode("\n", $sql);
    $query = '';
    $queryCount = 0;
    $delimiter = ';';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Lewati komentar baris dan baris kosong
        if ($trimmed === '' || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') {
            continue;
        }

        // Cek perubahan DELIMITER (misal: DELIMITER // atau DELIMITER ;)
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $m)) {
            $delimiter = trim($m[1]);
            continue;
        }

        // Lewati komentar multi-baris standar kecuali version comment MySQL/MariaDB (/*!... */)
        if (substr($trimmed, 0, 2) === '/*' && substr($trimmed, 0, 3) !== '/*!') {
            continue;
        }

        $query .= $line . "\n";
        $delimLen = strlen($delimiter);
        if (substr(rtrim($trimmed), -$delimLen) === $delimiter) {
            $cleanQuery = substr($query, 0, strrpos($query, $delimiter));
            try {
                $stmt = $pdo->prepare($cleanQuery);
                if ($stmt) {
                    $stmt->execute();
                    $stmt->closeCursor();
                }
                $queryCount++;
            } catch (Exception $e) {
                // Abaikan kesalahan sepele pada statement DROP TABLE / DROP PROCEDURE IF EXISTS
                if (stripos($cleanQuery, 'DROP TABLE IF EXISTS') === false && stripos($cleanQuery, 'DROP PROCEDURE IF EXISTS') === false) {
                    throw $e;
                }
            }
            $query = '';
        }
    }

    if (trim($query) !== '') {
        try {
            $stmt = $pdo->prepare($query);
            if ($stmt) {
                $stmt->execute();
                $stmt->closeCursor();
            }
            $queryCount++;
        } catch (Exception $e) {
            if (stripos($query, 'DROP TABLE IF EXISTS') === false && stripos($query, 'DROP PROCEDURE IF EXISTS') === false) {
                throw $e;
            }
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    return ['ok' => true, 'count' => $queryCount];
}

// -------------------------------------------------------------
// 1. HANDLER AKSI DOWNLOAD BERKAS BACKUP (SEBELUM OUTPUT HTML)
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download' && !empty($_GET['file'])) {
    $requestedFile = basename($_GET['file']);
    $filePath = $backupDir . DIRECTORY_SEPARATOR . $requestedFile;

    if (file_exists($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'sql') {
        recordLog("Mengunduh berkas backup database: " . $requestedFile);
        
        // Bersihkan output buffer agar file SQL murni tanpa karakter tambahan
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        setFlash('danger', 'Berkas backup tidak ditemukan atau tidak valid.');
        redirect(BASE_URL . '/admin/backup/index.php');
    }
}

// -------------------------------------------------------------
// 2. HANDLER POST: BUAT BACKUP, RESTORE, UPLOAD RESTORE, DELETE
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $action = $_POST['action'] ?? '';

    // A. BUAT CADANGAN BARU (BACKUP)
    if ($action === 'create_backup') {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = "backup_parkir_{$timestamp}.sql";
            $backupFilePath = $backupDir . DIRECTORY_SEPARATOR . $backupFilename;

            // Daftar tabel utama sesuai skema sistem informasi parkir
            $tables = ['tb_user', 'tb_kendaraan', 'tb_tarif', 'tb_area_parkir', 'tb_transaksi', 'tb_log_aktivitas'];

            $sqlContent  = "-- ========================================================\n";
            $sqlContent .= "-- SISTEM INFORMASI PARKIR - CADANGAN DATABASE MANUAL\n";
            $sqlContent .= "-- Waktu Backup : " . date('Y-m-d H:i:s') . "\n";
            $sqlContent .= "-- Dibuat Oleh   : " . ($_SESSION['user_nama'] ?? 'Admin') . " (ID: " . ($_SESSION['user_id'] ?? 0) . ")\n";
            $sqlContent .= "-- ========================================================\n\n";
            $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $sqlContent .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $sqlContent .= "START TRANSACTION;\n";
            $sqlContent .= "SET time_zone = \"+07:00\";\n\n";

            foreach ($tables as $table) {
                // Skema Create Table
                $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $createRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $sqlContent .= "-- --------------------------------------------------------\n";
                $sqlContent .= "-- Struktur tabel `{$table}`\n";
                $sqlContent .= "-- --------------------------------------------------------\n";
                $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sqlContent .= $createRow['Create Table'] . ";\n\n";

                // Isi Data Tabel
                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $sqlContent .= "-- Data untuk tabel `{$table}`\n";
                    $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($rows[0]));
                    $columnsStr = implode(', ', $columns);

                    $sqlContent .= "INSERT INTO `{$table}` ({$columnsStr}) VALUES\n";
                    $rowValues = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $pdo->quote($val);
                            }
                        }
                        $rowValues[] = "(" . implode(', ', $values) . ")";
                    }
                    $sqlContent .= implode(",\n", $rowValues) . ";\n\n";
                }
            }

            $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
            $sqlContent .= "COMMIT;\n";

            file_put_contents($backupFilePath, $sqlContent);

            recordLog("Membuat berkas cadangan database baru: {$backupFilename}");
            $fileKb = round(strlen($sqlContent) / 1024, 2);
            setFlash('success', "Cadangan database berhasil dibuat: <strong>{$backupFilename}</strong> ({$fileKb} KB)");
        } catch (Exception $e) {
            setFlash('danger', "Gagal membuat cadangan database: " . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/backup/index.php');
    }

    // B. PEMULIHAN DATABASE DARI BERKAS TERSIMPAN (RESTORE)
    if ($action === 'restore_backup') {
        $fileToRestore = basename($_POST['file'] ?? '');
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileToRestore;

        if (file_exists($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'sql') {
            try {
                $sqlContent = file_get_contents($filePath);
                if (empty($sqlContent)) {
                    throw new Exception("Berkas cadangan kosong.");
                }

                $res = executeSqlDump($pdo, $sqlContent);

                recordLog("Memulihkan database dari berkas cadangan: {$fileToRestore}");
                setFlash('success', "Database berhasil dipulihkan (restore) dari <strong>{$fileToRestore}</strong>! Total {$res['count']} kueri berhasil dieksekusi.");
            } catch (Exception $e) {
                setFlash('danger', "Gagal memulihkan database: " . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Berkas cadangan tidak valid atau tidak ditemukan.');
        }
        redirect(BASE_URL . '/admin/backup/index.php');
    }

    // C. PEMULIHAN DATABASE DARI UNGGAHAN BERKAS SQL
    if ($action === 'upload_restore') {
        if (!empty($_FILES['sql_file']['tmp_name']) && is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
            $origName = basename($_FILES['sql_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($ext !== 'sql') {
                setFlash('danger', 'Berkas yang diunggah harus berekstensi .sql');
            } else {
                try {
                    $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
                    if (empty($sqlContent)) {
                        throw new Exception("Berkas SQL yang diunggah kosong.");
                    }

                    // Simpan salinan ke folder backup untuk riwayat
                    $savedName = "restore_" . date('Y-m-d_H-i-s') . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/', '', $origName);
                    file_put_contents($backupDir . DIRECTORY_SEPARATOR . $savedName, $sqlContent);

                    $res = executeSqlDump($pdo, $sqlContent);

                    recordLog("Memulihkan database dari unggahan berkas SQL: {$origName}");
                    setFlash('success', "Database berhasil dipulihkan dari unggahan <strong>{$origName}</strong>! Total {$res['count']} kueri berhasil diterapkan.");
                } catch (Exception $e) {
                    setFlash('danger', "Gagal memulihkan database dari unggahan: " . $e->getMessage());
                }
            }
        } else {
            setFlash('danger', 'Harap pilih berkas .sql yang ingin dipulihkan.');
        }
        redirect(BASE_URL . '/admin/backup/index.php');
    }

    // D. HAPUS BERKAS CADANGAN
    if ($action === 'delete_backup') {
        $fileToDelete = basename($_POST['file'] ?? '');
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileToDelete;

        if (file_exists($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'sql') {
            unlink($filePath);
            recordLog("Menghapus berkas cadangan database: {$fileToDelete}");
            setFlash('success', "Berkas cadangan <strong>{$fileToDelete}</strong> berhasil dihapus.");
        } else {
            setFlash('danger', 'Berkas cadangan tidak valid atau sudah tidak ada.');
        }
        redirect(BASE_URL . '/admin/backup/index.php');
    }
}

// -------------------------------------------------------------
// 3. PERSIAPAN DATA TAMPILAN VIEW
// -------------------------------------------------------------
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'sql') {
            $fullPath = $backupDir . DIRECTORY_SEPARATOR . $f;
            $backupFiles[] = [
                'name' => $f,
                'size' => filesize($fullPath),
                'mtime' => filemtime($fullPath)
            ];
        }
    }
    // Urutkan dari berkas yang paling baru
    usort($backupFiles, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
}

// -------------------------------------------------------------
// 4. RENDERING TAMPILAN (HEADER & KONTEN HTML)
// -------------------------------------------------------------
$pageTitle = 'Backup & Restore Database';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Cadangan & Pemulihan Database (Backup & Restore)</h4>
        <p class="text-muted small mb-0">Kelola pembuatan cadangan SQL dan pemulihan data sistem manajemen parkir secara mandiri, aman, dan instan</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Tombol Modal Upload Restore -->
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalUploadRestore">
            <i class="bi bi-cloud-arrow-up-fill me-1" style="color: var(--primary-color);"></i> Unggah & Restore SQL
        </button>

        <!-- Tombol Buat Backup Baru -->
        <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin membuat cadangan database baru sekarang?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_backup">
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                <i class="bi bi-database-fill-add me-1"></i> Buat Cadangan Baru
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Tabel Berkas Backup -->
    <div class="col-lg-8">
        <div class="card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 d-flex align-items-center">
                    <i class="bi bi-folder-check me-2" style="color: var(--primary-color);"></i> Daftar Berkas Cadangan Tersedia
                </h5>
                <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                    Total: <?= count($backupFiles) ?> berkas
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th>Nama Berkas</th>
                            <th>Ukuran</th>
                            <th>Waktu Pembuatan</th>
                            <th style="width: 200px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backupFiles)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada berkas cadangan database. Klik <strong>"Buat Cadangan Baru"</strong> di atas.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($backupFiles as $bf): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-code fs-5" style="color: var(--primary-color);"></i>
                                            <span class="fw-semibold text-dark font-monospace small"><?= htmlspecialchars($bf['name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= round($bf['size'] / 1024, 1) ?> KB
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('d M Y, H:i', $bf['mtime']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1 align-items-center">
                                            <!-- Tombol Unduh -->
                                            <a href="?action=download&file=<?= urlencode($bf['name']) ?>" class="btn btn-sm btn-outline-secondary" title="Unduh Berkas SQL" style="border-radius: 8px;">
                                                <i class="bi bi-download"></i>
                                            </a>

                                            <!-- Tombol Restore Eksplisit -->
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('PERINGATAN PEMULIHAN (RESTORE):\n\nMemulihkan database dari berkas \'<?= htmlspecialchars($bf['name']) ?>\' akan menimpa data parkir saat ini dengan isi cadangan ini.\n\nApakah Anda yakin ingin memulihkan sekarang?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="file" value="<?= htmlspecialchars($bf['name']) ?>">
                                                <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold px-2" title="Restore Data dari Berkas Ini" style="border-radius: 8px;">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                </button>
                                            </form>

                                            <!-- Tombol Hapus -->
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus berkas cadangan \'<?= htmlspecialchars($bf['name']) ?>\' secara permanen?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="file" value="<?= htmlspecialchars($bf['name']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Berkas Cadangan" style="border-radius: 8px;">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel Petunjuk & Keamanan -->
    <div class="col-lg-4">
        <div class="card-custom p-4 mb-4">
            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                <i class="bi bi-arrow-counterclockwise me-2" style="color: #d97706;"></i> Fitur Restore Instan
            </h6>
            <p class="small text-muted mb-2">
                Anda dapat memulihkan seluruh struktur tabel dan data transaksi secara langsung:
            </p>
            <ul class="small text-muted ps-3 mb-0">
                <li class="mb-2"><strong>Tombol Restore pada Tabel:</strong> Memulihkan database seketika dari salah satu berkas cadangan yang tersimpan di server.</li>
                <li><strong>Tombol Unggah SQL:</strong> Mengimpor dan memulihkan database dari berkas cadangan yang tersimpan di perangkat lokal Anda.</li>
            </ul>
        </div>

        <div class="card-custom p-4">
            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                <i class="bi bi-shield-lock-fill text-success me-2"></i> Keamanan Direktori Cadangan
            </h6>
            <p class="small text-muted mb-2">
                Direktori <code>backup/</code> dilindungi dengan konfigurasi web server (<code>Require all denied</code>), mencegah akses atau unduhan publik dari luar.
            </p>
            <p class="small text-muted mb-0">
                Hanya akun dengan hak akses <strong>Administrator</strong> yang memiliki izin membuat, mengunduh, memulihkan (restore), atau menghapus berkas cadangan.
            </p>
        </div>
    </div>
</div>

<!-- Modal Unggah & Restore Berkas SQL -->
<div class="modal fade" id="modalUploadRestore" tabindex="-1" aria-labelledby="modalUploadRestoreLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('PERINGATAN PEMULIHAN:\n\nProses ini akan mengeksekusi kueri dari berkas SQL yang Anda unggah dan menimpa data parkir saat ini.\n\nPastikan berkas berasal dari sumber terpercaya. Lanjutkan pemulihan?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload_restore">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalUploadRestoreLabel">
                        <i class="bi bi-cloud-arrow-up-fill me-2" style="color: var(--primary-color);"></i> Unggah & Restore Berkas SQL
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small d-flex align-items-start gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-warning"></i>
                        <div>
                            Proses pemulihan akan menimpa seluruh tabel yang ada dalam berkas cadangan. Disarankan membuat cadangan baru terlebih dahulu untuk berjaga-jaga.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="sql_file" class="form-label small fw-bold text-secondary">Pilih Berkas Cadangan (.sql) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="sql_file" name="sql_file" accept=".sql" required>
                        <div class="form-text small">Hanya menerima format berkas SQL dump (.sql) UTF-8 atau mysqldump standar.</div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Mulai Pemulihan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
