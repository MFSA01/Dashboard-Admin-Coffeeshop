<?php
/**
 * B L E N D — Hapus Transaksi + Pengembalian Stok
 * ---------------------------------------------------------
 * Hanya menerima POST. Hapus record & kembalikan stok
 * dalam satu database transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Wajib POST
   --------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Permintaan penghapusan transaksi tidak valid.');
    header('Location: transaksi.php');
    exit;
}

$idTransaksi = (int) ($_POST['id_transaksi'] ?? 0);

if ($idTransaksi <= 0) {
    setFlashMessage('error', 'ID transaksi tidak valid.');
    header('Location: transaksi.php');
    exit;
}

/* ---------------------------------------------------------
   2. Hapus + kembalikan stok (atomik)
   --------------------------------------------------------- */

$pesanSukses = '';
$pesanError  = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    mysqli_begin_transaction($koneksi);

    $stmtAmbil = mysqli_prepare(
        $koneksi,
        'SELECT nomor_nota, id_menu, jumlah FROM transaksi WHERE id_transaksi = ? FOR UPDATE'
    );

    if ($stmtAmbil === false) {
        throw new RuntimeException('Gagal membaca data transaksi.');
    }

    mysqli_stmt_bind_param($stmtAmbil, 'i', $idTransaksi);
    mysqli_stmt_execute($stmtAmbil);
    $hasilAmbil = mysqli_stmt_get_result($stmtAmbil);
    $transaksi  = $hasilAmbil !== false ? mysqli_fetch_assoc($hasilAmbil) : null;
    mysqli_stmt_close($stmtAmbil);

    if (!is_array($transaksi)) {
        throw new RuntimeException('Transaksi tidak ditemukan atau sudah dihapus.');
    }

    $idMenu    = (int) $transaksi['id_menu'];
    $jumlah    = (int) $transaksi['jumlah'];
    $nomorNota = (string) $transaksi['nomor_nota'];

    $stmtHapus = mysqli_prepare($koneksi, 'DELETE FROM transaksi WHERE id_transaksi = ?');

    if ($stmtHapus === false) {
        throw new RuntimeException('Gagal menyiapkan penghapusan transaksi.');
    }

    mysqli_stmt_bind_param($stmtHapus, 'i', $idTransaksi);
    mysqli_stmt_execute($stmtHapus);
    mysqli_stmt_close($stmtHapus);

    $stmtStok = mysqli_prepare($koneksi, 'UPDATE menu SET stok = stok + ? WHERE id_menu = ?');

    if ($stmtStok === false) {
        throw new RuntimeException('Gagal menyiapkan pengembalian stok.');
    }

    mysqli_stmt_bind_param($stmtStok, 'ii', $jumlah, $idMenu);
    mysqli_stmt_execute($stmtStok);
    mysqli_stmt_close($stmtStok);

    mysqli_commit($koneksi);

    $pesanSukses = 'Transaksi "' . $nomorNota . '" berhasil dihapus. '
        . 'Stok ' . $jumlah . ' porsi telah dikembalikan ke katalog menu.';
} catch (Throwable $e) {
    mysqli_rollback($koneksi);
    $pesanError = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Transaksi gagal dihapus karena kesalahan pada sistem.';
} finally {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if ($pesanSukses !== '') {
    setFlashMessage('success', $pesanSukses);
} else {
    setFlashMessage('error', $pesanError !== '' ? $pesanError : 'Transaksi gagal dihapus.');
}

header('Location: transaksi.php');
exit;
