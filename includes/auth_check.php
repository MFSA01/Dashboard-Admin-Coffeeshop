<?php
/**
 * B L E N D — Proteksi Halaman Dashboard
 * ---------------------------------------------------------
 * Semua halaman dashboard & CRUD wajib meng-include file ini
 * setelah config/koneksi.php dan includes/functions.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    setFlashMessage('error', 'Silakan login terlebih dahulu untuk mengakses dashboard.');
    header('Location: login.php');
    exit;
}
