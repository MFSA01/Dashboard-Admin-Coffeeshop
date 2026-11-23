<?php
/**
 * B L E N D — Login Admin
 * ---------------------------------------------------------
 * Verifikasi memakai prepared statement + password_verify().
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';

// Sudah login? Langsung ke dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error       = '';
$username    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $sql  = 'SELECT id_user, username, password, nama_lengkap FROM users WHERE username = ? LIMIT 1';
        $stmt = mysqli_prepare($koneksi, $sql);

        if ($stmt === false) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $hasil = mysqli_stmt_get_result($stmt);
            $user  = $hasil !== false ? mysqli_fetch_assoc($hasil) : null;
            mysqli_stmt_close($stmt);

            if (!is_array($user) || !password_verify($password, (string) $user['password'])) {
                $error = 'Username atau password salah.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id']      = (int) $user['id_user'];
                $_SESSION['username']     = (string) $user['username'];
                $_SESSION['nama_lengkap'] = (string) $user['nama_lengkap'];

                setFlashMessage('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login admin B L E N D Coffee Shop.">
    <title>Login · B L E N D</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/img/default-coffee.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

<main class="login-shell">

    <section class="login-aside" aria-label="Identitas B L E N D">
        <span class="login-aside__badge">
            <i data-lucide="coffee" aria-hidden="true"></i>
            Internal System
        </span>

        <div class="login-aside__stack">
            <span class="login-aside__word">Brew</span>
            <span class="login-aside__word login-aside__word--outline">Blend</span>
            <span class="login-aside__word">Serve</span>
            <span class="login-aside__word login-aside__word--outline">Repeat</span>
        </div>

        <p class="login-aside__copy">
            Dashboard operasional Coffee Shop B L E N D. Kelola katalog menu,
            pantau stok, dan catat setiap transaksi dalam satu tempat.
        </p>
    </section>

    <section class="login-panel" aria-label="Form login">
        <div class="login-panel__head">
            <p class="eyebrow">Admin Access</p>
            <h1 class="login-panel__title">Masuk</h1>
            <p class="login-panel__desc">
                Gunakan akun admin untuk membuka dashboard B L E N D.
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert--<?= e($flash['tipe']) ?>" role="status" data-flash-alert>
                <i class="alert__icon" data-lucide="info" aria-hidden="true"></i>
                <p class="alert__message"><?= e($flash['pesan']) ?></p>
                <button type="button" class="alert__close" data-alert-close aria-label="Tutup notifikasi">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" data-flash-alert>
                <i class="alert__icon" data-lucide="alert-circle" aria-hidden="true"></i>
                <p class="alert__message"><?= e($error) ?></p>
                <button type="button" class="alert__close" data-alert-close aria-label="Tutup notifikasi">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>

        <form class="login-form" method="post" action="login.php" autocomplete="on" novalidate>
            <div class="form-field">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <input
                    class="form-control"
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($username) ?>"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-field">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="btn btn--block" type="submit">
                <i data-lucide="log-in" aria-hidden="true"></i>
                Masuk ke Dashboard
            </button>
        </form>

        <p class="login-footer-note">
            <i data-lucide="shield-check" aria-hidden="true"></i>
            Akses terbatas untuk administrator B L E N D.
        </p>
    </section>

</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
