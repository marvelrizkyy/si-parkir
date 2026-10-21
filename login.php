<?php
/**
 * Halaman Login Sistem
 * Sistem Informasi Parkir Berbasis Web
 * Desain: Warm card-based (matching welcome.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, langsung alihkan ke dashboard role
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role'] ?? '');
}

$error = '';
$usernameInput = trim($_POST['username'] ?? $_GET['role'] ?? $_GET['user'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFOrDie();

    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if (empty($usernameInput) || empty($passwordInput)) {
        $error = 'Harap masukkan username dan password.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, password, role, status_aktif FROM tb_user WHERE username = ? LIMIT 1");
            $stmt->execute([$usernameInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($passwordInput, $user['password'])) {
                if ((int)$user['status_aktif'] !== 1) {
                    $error = 'Akun Anda telah dinonaktifkan. Hubungi admin sistem.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id_user'];
                    $_SESSION['user_nama'] = $user['nama_lengkap'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];

                    recordLog("Pengguna '{$user['username']}' ({$user['role']}) berhasil login ke sistem.", (int)$user['id_user']);
                    setFlashMessage('success', "Selamat datang kembali, {$user['nama_lengkap']}!");
                    redirectByRole($user['role']);
                }
            } else {
                $error = 'Username atau password salah.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — <?= APP_NAME ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <meta name="theme-color" content="#c8c3ba">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bg: #c8c3ba;
            --card: #ffffff;
            --card-alt: #f7f5f2;
            --border: #e2ddd6;
            --text: #2c2a27;
            --text-mid: #6b6560;
            --text-light: #9e9890;
            --accent: #c9912b;
            --accent-dark: #a47520;
        }
        * { font-family: 'DM Sans', system-ui, sans-serif; box-sizing: border-box; }

        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-wrap {
            max-width: 400px;
            width: 100%;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .login-brand .icon {
            width: 48px; height: 48px;
            background: var(--text);
            border-radius: 12px;
            display: inline-flex;
            align-items: center; justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
        }
        .login-brand h1 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.2rem;
        }
        .login-brand p {
            font-size: 0.85rem;
            color: var(--text-mid);
            margin: 0;
        }

        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.75rem;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-mid);
            margin-bottom: 0.3rem;
        }
        .form-control {
            border-color: var(--border);
            padding: 0.6rem 0.75rem;
            font-size: 0.9rem;
            border-radius: 10px;
            background: var(--card-alt);
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(201, 145, 43, 0.12);
            background: var(--card);
        }
        .input-group-text {
            background: var(--card-alt);
            border-color: var(--border);
            color: var(--text-light);
            border-radius: 10px 0 0 10px;
        }

        .btn-login {
            background: var(--accent);
            color: #fff;
            border: none;
            width: 100%;
            padding: 0.65rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.15s;
        }
        .btn-login:hover {
            background: var(--accent-dark);
            color: #fff;
        }

        .demo-section {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
        }
        .demo-label {
            font-size: 0.75rem;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .demo-btns {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .demo-btn {
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-mid);
            background: var(--card-alt);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .demo-btn:hover {
            border-color: var(--accent);
            color: var(--accent-dark);
        }
        .demo-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: var(--text-mid);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .back-link a:hover {
            color: var(--text);
        }

        .alert {
            font-size: 0.85rem;
            border-radius: 10px;
            border: 1px solid #f0d0cc;
            background: #fdf0ee;
            color: #c0392b;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="login-brand">
        <div class="icon"><i class="bi bi-p-square-fill"></i></div>
        <h1>SI Parkir</h1>
        <p>Masuk ke sistem pengelolaan parkir</p>
    </div>

    <div class="login-card">
        <?= displayFlashMessages() ?>

        <?php if (!empty($error)): ?>
            <div class="alert d-flex align-items-center gap-2 py-2 mb-3" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="form-login">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Masukkan username" value="<?= htmlspecialchars($usernameInput) ?>" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Masukkan password" required>
                    <button class="btn" style="border: 1px solid var(--border); border-left: 0; background: var(--card-alt); color: var(--text-light); border-radius: 0 10px 10px 0;" 
                            type="button" onclick="togglePassword()">
                        <i class="bi bi-eye" id="icon-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Masuk</button>
        </form>

        <div class="demo-section">
            <div class="demo-label">Akun demo (klik untuk isi otomatis)</div>
            <div class="demo-btns">
                <button type="button" class="demo-btn" onclick="fillCred('admin','admin123',this)">Admin</button>
                <button type="button" class="demo-btn" onclick="fillCred('petugas','petugas123',this)">Petugas</button>
                <button type="button" class="demo-btn" onclick="fillCred('owner','owner123',this)">Owner</button>
            </div>
        </div>
    </div>

    <div class="back-link">
        <a href="<?= BASE_URL ?>/welcome.php"><i class="bi bi-arrow-left me-1"></i> Kembali ke halaman utama</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function fillCred(user, pass, el) {
        document.getElementById('username').value = user;
        document.getElementById('password').value = pass;
        document.querySelectorAll('.demo-btn').forEach(b => b.classList.remove('active'));
        if (el) el.classList.add('active');
    }
    function togglePassword() {
        const p = document.getElementById('password');
        const i = document.getElementById('icon-eye');
        p.type = (p.type === 'password') ? 'text' : 'password';
        i.className = (p.type === 'password') ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
    document.addEventListener('DOMContentLoaded', () => {
        const r = new URLSearchParams(location.search).get('role');
        if (r === 'admin') fillCred('admin','admin123');
        else if (r === 'petugas') fillCred('petugas','petugas123');
        else if (r === 'owner') fillCred('owner','owner123');
    });
</script>
</body>
</html>
