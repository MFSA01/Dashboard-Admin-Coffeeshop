<?php
/**
 * B L E N D — Koneksi Database
 * ---------------------------------------------------------
 * Driver wajib : Procedural mysqli
 * Database     : blend_3487 (Laragon / MariaDB)
 */

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'blend_3487');

/**
 * Membuat koneksi mysqli (singleton untuk satu request).
 */
function getKoneksi(): mysqli
{
    static $koneksi = null;

    if ($koneksi instanceof mysqli) {
        return $koneksi;
    }

    // Report dimatikan agar error ditangani secara eksplisit (procedural).
    mysqli_report(MYSQLI_REPORT_OFF);

    $koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($koneksi === false) {
        die('Koneksi database gagal: ' . mysqli_connect_error());
    }

    mysqli_set_charset($koneksi, 'utf8mb4');

    return $koneksi;
}

$koneksi = getKoneksi();
