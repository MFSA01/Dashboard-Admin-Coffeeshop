<?php
/**
 * B L E N D — Hapus Menu (Delete + Proteksi Foreign Key)
 * ---------------------------------------------------------
 * Hanya menerima POST. Cek pemakaian di tabel transaksi lebih dulu,
 * lalu bungkus DELETE dengan try/catch (mysqli_sql_exception).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Wajib POST
   --------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Permintaan penghapusan menu tidak valid.');
    header('Location: menu.php');
    exit;
}

$idMenu = (int) ($_POST['id_menu'] ?? 0);
$asal   = 'menu.php';

if ($idMenu <= 0) {
    setFlashMessage('error', 'ID menu tidak valid.');
    header('Location: ' . $asal);
    exit;
}

/* ---------------------------------------------------------
   2. Ambil data menu
   --------------------------------------------------------- */

$sqlAmbil  = 'SELECT id_menu, nama_menu, gambar FROM menu WHERE id_menu = ? LIMIT 1';
$stmtAmbil = mysqli_prepare($koneksi, $sqlAmbil);

if ($stmtAmbil === false) {
    setFlashMessage('error', 'Terjadi kesalahan saat mengambil data menu.');
    header('Location: ' . $asal);
    exit;
}

mysqli_stmt_bind_param($stmtAmbil, 'i', $idMenu);
mysqli_stmt_execute($stmtAmbil);
$hasilAmbil = mysqli_stmt_get_result($stmtAmbil);
$menu       = $hasilAmbil !== false ? mysqli_fetch_assoc($hasilAmbil) : null;
mysqli_stmt_close($stmtAmbil);

if (!is_array($menu)) {
    setFlashMessage('error', 'Menu yang ingin dihapus tidak ditemukan.');
    header('Location: ' . $asal);
    exit;
}

$namaMenu    = (string) $menu['nama_menu'];
$gambarMenu  = (string) $menu['gambar'];

/* ---------------------------------------------------------
   3. Cek relasi ke tabel transaksi (FK ON DELETE RESTRICT)
   --------------------------------------------------------- */

$jumlahTransaksi = 0;
$sqlCek          = 'SELECT COUNT(*) AS total FROM transaksi WHERE id_menu = ?';
$stmtCek         = mysqli_prepare($koneksi, $sqlCek);

if ($stmtCek !== false) {
    mysqli_stmt_bind_param($stmtCek, 'i', $idMenu);
    mysqli_stmt_execute($stmtCek);
    $hasilCek = mysqli_stmt_get_result($stmtCek);
    $barisCek = $hasilCek !== false ? mysqli_fetch_assoc($hasilCek) : null;
    mysqli_stmt_close($stmtCek);

    $jumlahTransaksi = (int) ($barisCek['total'] ?? 0);
}

if ($jumlahTransaksi > 0) {
    setFlashMessage(
        'error',
        'Menu "' . $namaMenu . '" tidak dapat dihapus karena sudah tercatat pada '
        . $jumlahTransaksi . ' riwayat transaksi. '
        . 'Kurangi stok menjadi 0 jika menu ini ingin dinonaktifkan.'
    );
    header('Location: ' . $asal);
    exit;
}

/* ---------------------------------------------------------
   4. Eksekusi DELETE dengan pengamanan exception
   --------------------------------------------------------- */

$berhasilHapus = false;
$pesanGagal    = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stmtHapus = null;

try {
    $stmtHapus = mysqli_prepare($koneksi, 'DELETE FROM menu WHERE id_menu = ?');

    if ($stmtHapus === false) {
        throw new RuntimeException('Gagal menyiapkan perintah hapus.');
    }

    mysqli_stmt_bind_param($stmtHapus, 'i', $idMenu);
    mysqli_stmt_execute($stmtHapus);
    $berhasilHapus = mysqli_stmt_affected_rows($stmtHapus) > 0;
} catch (mysqli_sql_exception $e) {
    $pesanGagal = $e->getCode() === 1451
        ? 'Menu tidak dapat dihapus karena sudah tercatat pada riwayat transaksi.'
        : 'Menu gagal dihapus: ' . $e->getMessage();
} catch (Throwable $e) {
    $pesanGagal = 'Menu gagal dihapus karena kesalahan pada sistem.';
} finally {
    if ($stmtHapus instanceof mysqli_stmt) {
        mysqli_stmt_close($stmtHapus);
    }

    mysqli_report(MYSQLI_REPORT_OFF);
}

if (!$berhasilHapus) {
    setFlashMessage('error', $pesanGagal !== '' ? $pesanGagal : 'Menu gagal dihapus. Silakan coba lagi.');
    header('Location: ' . $asal);
    exit;
}

/* ---------------------------------------------------------
   5. Bersihkan file gambar (kecuali default)
   --------------------------------------------------------- */

hapusGambarMenu($gambarMenu);

setFlashMessage('success', 'Menu "' . $namaMenu . '" berhasil dihapus dari katalog.');
header('Location: ' . $asal);
exit;
