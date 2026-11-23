<?php
/**
 * B L E N D — Header Global
 * ---------------------------------------------------------
 * Variabel opsional sebelum include:
 *   $halamanAktif (string) : index | menu | transaksi
 *   $judulHalaman (string) : judul tab browser
 *   $eyebrowHalaman (string) : label kecil di atas judul
 *   $deskripsiHalaman (string) : subjudul halaman
 */

declare(strict_types=1);

if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

$halamanAktif     = $halamanAktif ?? 'index';
$judulHalaman     = $judulHalaman ?? 'Dashboard';
$eyebrowHalaman   = $eyebrowHalaman ?? 'Admin Workspace';
$deskripsiHalaman = $deskripsiHalaman ?? '';
$flash            = getFlashMessage();

$navItems = [
    'index'     => ['label' => 'Dashboard', 'url' => 'index.php',     'icon' => 'layout-dashboard'],
    'menu'      => ['label' => 'Menu',      'url' => 'menu.php',      'icon' => 'coffee'],
    'transaksi' => ['label' => 'Transaksi', 'url' => 'transaksi.php', 'icon' => 'receipt-text'],
];

$namaPengguna = (string) ($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="B L E N D — Admin Dashboard &amp; Sistem Transaksi Coffee Shop.">
    <title><?= e($judulHalaman) ?> · B L E N D</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/img/default-coffee.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">

<a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

<header class="site-header">
    <div class="site-header__inner container">
        <a class="brand" href="index.php" aria-label="B L E N D Dashboard">
            <span class="brand__mark" aria-hidden="true">
                <i data-lucide="coffee"></i>
            </span>
            <span class="brand__text">
                <span class="brand__name">B L E N D</span>
                <span class="brand__sub">Coffee Shop Admin</span>
            </span>
        </a>

        <nav class="main-nav" aria-label="Navigasi utama">
            <ul class="main-nav__list">
                <?php foreach ($navItems as $kunci => $item): ?>
                    <li class="main-nav__item">
                        <a class="main-nav__link <?= $halamanAktif === $kunci ? 'is-active' : '' ?>"
                           href="<?= e($item['url']) ?>"
                           <?= $halamanAktif === $kunci ? 'aria-current="page"' : '' ?>>
                            <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="user-menu">
            <span class="user-menu__avatar" aria-hidden="true">
                <?= e(mb_strtoupper(mb_substr($namaPengguna, 0, 1))) ?>
            </span>
            <span class="user-menu__info">
                <span class="user-menu__label">Masuk sebagai</span>
                <span class="user-menu__name"><?= e($namaPengguna) ?></span>
            </span>
            <a class="icon-button" href="logout.php" title="Keluar" aria-label="Keluar dari akun">
                <i data-lucide="log-out" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</header>

<main class="site-main container" id="konten-utama">

    <section class="page-heading">
        <div class="page-heading__text">
            <p class="eyebrow"><?= e($eyebrowHalaman) ?></p>
            <h1 class="page-heading__title"><?= e($judulHalaman) ?></h1>
            <?php if ($deskripsiHalaman !== ''): ?>
                <p class="page-heading__desc"><?= e($deskripsiHalaman) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($flash !== null): ?>
        <div class="alert alert--<?= e($flash['tipe']) ?>" role="status" data-flash-alert>
            <i class="alert__icon" data-lucide="<?= $flash['tipe'] === 'success' ? 'check-circle-2' : ($flash['tipe'] === 'error' ? 'alert-circle' : 'info') ?>" aria-hidden="true"></i>
            <p class="alert__message"><?= e($flash['pesan']) ?></p>
            <button type="button" class="alert__close" data-alert-close aria-label="Tutup notifikasi">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
    <?php endif; ?>
