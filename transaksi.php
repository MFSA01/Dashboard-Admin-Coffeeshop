<?php
/**
 * B L E N D — Riwayat Transaksi (Read, Filter Metode & Pencarian)
 * ---------------------------------------------------------
 * INNER JOIN transaksi + menu · Prepared Statements.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Parameter filter & pencarian
   --------------------------------------------------------- */

$metodeDipilih = trim((string) ($_GET['metode'] ?? ''));
$kataKunci     = trim((string) ($_GET['q'] ?? ''));

if ($metodeDipilih !== '' && !in_array($metodeDipilih, METODE_PEMBAYARAN, true)) {
    $metodeDipilih = '';
}

/* ---------------------------------------------------------
   2. Query daftar transaksi (prepared statement dinamis)
   --------------------------------------------------------- */

$where  = [];
$types  = '';
$params = [];

if ($metodeDipilih !== '') {
    $where[]  = 't.metode_pembayaran = ?';
    $types   .= 's';
    $params[] = $metodeDipilih;
}

if ($kataKunci !== '') {
    $where[]  = '(t.nomor_nota LIKE ? OR t.nama_kasir LIKE ? OR m.nama_menu LIKE ?)';
    $types   .= 'sss';
    $like     = '%' . $kataKunci . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT t.id_transaksi, t.nomor_nota, t.tanggal_pembelian, t.nama_kasir,
               t.id_menu, t.jumlah, t.harga_satuan, t.total_harga, t.metode_pembayaran,
               m.nama_menu, m.kategori, m.gambar
        FROM transaksi t
        INNER JOIN menu m ON m.id_menu = t.id_menu';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY t.tanggal_pembelian DESC, t.id_transaksi DESC';

$daftarTransaksi = [];
$stmt            = mysqli_prepare($koneksi, $sql);

if ($stmt !== false) {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);

    if ($hasil !== false) {
        while ($baris = mysqli_fetch_assoc($hasil)) {
            $daftarTransaksi[] = $baris;
        }
    }

    mysqli_stmt_close($stmt);
}

/* ---------------------------------------------------------
   3. Ringkasan hasil filter (dihitung dari data yang tampil)
   --------------------------------------------------------- */

$jumlahTampil   = count($daftarTransaksi);
$totalPendapatan = 0;
$totalItem       = 0;

foreach ($daftarTransaksi as $barisRingkas) {
    $totalPendapatan += (int) $barisRingkas['total_harga'];
    $totalItem       += (int) $barisRingkas['jumlah'];
}

/* ---------------------------------------------------------
   4. Jumlah transaksi per metode (label tab filter)
   --------------------------------------------------------- */

$jumlahMetode = array_fill_keys(METODE_PEMBAYARAN, 0);
$jumlahSemua  = 0;
$hasilHitung  = mysqli_query($koneksi, 'SELECT metode_pembayaran, COUNT(*) AS total FROM transaksi GROUP BY metode_pembayaran');

if ($hasilHitung !== false) {
    while ($barisHitung = mysqli_fetch_assoc($hasilHitung)) {
        $kunci = (string) $barisHitung['metode_pembayaran'];

        if (array_key_exists($kunci, $jumlahMetode)) {
            $jumlahMetode[$kunci] = (int) $barisHitung['total'];
        }

        $jumlahSemua += (int) $barisHitung['total'];
    }
}

/* ---------------------------------------------------------
   5. URL bantu tab filter
   --------------------------------------------------------- */

$buatUrlFilter = static function (string $metode) use ($kataKunci): string {
    $query = [];

    if ($metode !== '') {
        $query['metode'] = $metode;
    }

    if ($kataKunci !== '') {
        $query['q'] = $kataKunci;
    }

    return 'transaksi.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$tabFilter = ['' => 'Semua'] + array_combine(METODE_PEMBAYARAN, METODE_PEMBAYARAN);

/* ---------------------------------------------------------
   6. Render
   --------------------------------------------------------- */

$halamanAktif     = 'transaksi';
$judulHalaman     = 'Riwayat Transaksi';
$eyebrowHalaman   = 'B L E N D · Arus Kasir';
$deskripsiHalaman = 'Pantau seluruh pencatatan pembelian, metode pembayaran, dan nilai penjualan Coffee Shop B L E N D.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
    <nav class="filter-tabs" aria-label="Filter metode pembayaran">
        <?php foreach ($tabFilter as $nilaiMetode => $labelMetode): ?>
            <?php
            $jumlahTab = $nilaiMetode === '' ? $jumlahSemua : ($jumlahMetode[$nilaiMetode] ?? 0);
            $isAktif   = $metodeDipilih === $nilaiMetode;
            ?>
            <a class="filter-tabs__link <?= $isAktif ? 'is-active' : '' ?>"
               href="<?= e($buatUrlFilter($nilaiMetode)) ?>"
               <?= $isAktif ? 'aria-current="page"' : '' ?>>
                <?= e($labelMetode) ?>
                <span class="filter-tabs__count"><?= e($jumlahTab) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="page-actions__right">
        <form class="search-form" method="get" action="transaksi.php" role="search">
            <?php if ($metodeDipilih !== ''): ?>
                <input type="hidden" name="metode" value="<?= e($metodeDipilih) ?>">
            <?php endif; ?>

            <label class="sr-only" for="pencarian-transaksi">Cari nomor nota, kasir, atau menu</label>
            <input
                class="search-form__input"
                type="search"
                id="pencarian-transaksi"
                name="q"
                value="<?= e($kataKunci) ?>"
                placeholder="Cari nota / kasir / menu..."
                autocomplete="off"
            >
            <button class="btn btn--sm" type="submit">
                <i data-lucide="search" aria-hidden="true"></i>
                Cari
            </button>
        </form>

        <a class="btn" href="transaksi_tambah.php">
            <i data-lucide="plus" aria-hidden="true"></i>
            Catat Transaksi
        </a>
    </div>
</div>

<section class="stat-strip" aria-label="Ringkasan transaksi">
    <div class="stat-strip__item">
        <span class="stat-strip__icon"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
        <div>
            <p class="stat-strip__label">Transaksi Tampil</p>
            <p class="stat-strip__value"><?= e($jumlahTampil) ?></p>
        </div>
    </div>

    <div class="stat-strip__item">
        <span class="stat-strip__icon"><i data-lucide="package" aria-hidden="true"></i></span>
        <div>
            <p class="stat-strip__label">Total Item Terjual</p>
            <p class="stat-strip__value"><?= e($totalItem) ?></p>
        </div>
    </div>

    <div class="stat-strip__item stat-strip__item--accent">
        <span class="stat-strip__icon"><i data-lucide="banknote" aria-hidden="true"></i></span>
        <div>
            <p class="stat-strip__label">Total Pendapatan</p>
            <p class="stat-strip__value"><?= e(formatRupiah($totalPendapatan)) ?></p>
        </div>
    </div>
</section>

<?php if ($kataKunci !== '' || $metodeDipilih !== ''): ?>
    <p class="result-note">
        <i data-lucide="filter" aria-hidden="true"></i>
        Menampilkan <strong><?= e($jumlahTampil) ?></strong> transaksi
        <?php if ($metodeDipilih !== ''): ?>
            dengan metode <strong><?= e($metodeDipilih) ?></strong>
        <?php endif; ?>
        <?php if ($kataKunci !== ''): ?>
            untuk kata kunci &ldquo;<?= e($kataKunci) ?>&rdquo;
        <?php endif; ?>
        · <a href="transaksi.php">Reset filter</a>
    </p>
<?php endif; ?>

<?php if ($daftarTransaksi === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
        <?php if ($kataKunci !== '' || $metodeDipilih !== ''): ?>
            <p class="empty-state__title">Transaksi Tidak Ditemukan</p>
            <p class="empty-state__desc">
                Tidak ada transaksi yang cocok dengan filter atau kata kunci pencarian Anda.
                Coba kata kunci lain atau tampilkan seluruh riwayat.
            </p>
            <div class="btn-group">
                <a class="btn btn--secondary btn--sm" href="transaksi.php">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Reset Filter
                </a>
                <a class="btn btn--sm" href="transaksi_tambah.php">
                    <i data-lucide="plus" aria-hidden="true"></i>
                    Catat Transaksi
                </a>
            </div>
        <?php else: ?>
            <p class="empty-state__title">Belum Ada Transaksi</p>
            <p class="empty-state__desc">
                Riwayat pembelian akan muncul di sini setelah kasir mencatat transaksi pertama.
            </p>
            <a class="btn btn--sm" href="transaksi_tambah.php">
                <i data-lucide="plus" aria-hidden="true"></i>
                Catat Transaksi
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrapper">
        <table class="data-table data-table--transaksi">
            <thead>
                <tr>
                    <th scope="col">Nomor Nota</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Kasir</th>
                    <th scope="col">Produk</th>
                    <th scope="col" class="cell-num">Qty</th>
                    <th scope="col" class="cell-num">Harga Satuan</th>
                    <th scope="col" class="cell-num">Total Harga</th>
                    <th scope="col">Metode</th>
                    <th scope="col" class="cell-num">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftarTransaksi as $trx): ?>
                    <?php
                    $metode        = (string) $trx['metode_pembayaran'];
                    $classMetode   = match ($metode) {
                        'Tunai'         => 'badge--tunai',
                        'QRIS'          => 'badge--qris',
                        'Transfer Bank' => 'badge--transfer',
                        default         => 'badge--metode',
                    };
                    $konfirmasiHapus = 'Yakin ingin menghapus transaksi "' . $trx['nomor_nota'] . '"? '
                        . 'Stok ' . $trx['jumlah'] . ' porsi ' . $trx['nama_menu'] . ' akan dikembalikan.';
                    ?>
                    <tr>
                        <td class="cell-code"><?= e($trx['nomor_nota']) ?></td>
                        <td class="cell-date"><?= e(formatTanggalIndonesia((string) $trx['tanggal_pembelian'])) ?></td>
                        <td class="cell-strong"><?= e($trx['nama_kasir']) ?></td>
                        <td>
                            <div class="table-product">
                                <img
                                    class="table-thumb"
                                    src="<?= e(getMenuImageUrl((string) $trx['gambar'], (string) $trx['kategori'])) ?>"
                                    alt=""
                                    loading="lazy"
                                >
                                <div>
                                    <p class="table-product__name"><?= e($trx['nama_menu']) ?></p>
                                    <p class="table-product__meta"><?= e($trx['kategori']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="cell-num"><?= e($trx['jumlah']) ?></td>
                        <td class="cell-num"><?= e(formatRupiah((int) $trx['harga_satuan'])) ?></td>
                        <td class="cell-num cell-strong"><?= e(formatRupiah((int) $trx['total_harga'])) ?></td>
                        <td><span class="badge <?= e($classMetode) ?>"><?= e($metode) ?></span></td>
                        <td>
                            <div class="cell-actions">
                                <a
                                    class="btn btn--icon"
                                    href="transaksi_edit.php?id=<?= e($trx['id_transaksi']) ?>"
                                    title="Edit transaksi <?= e($trx['nomor_nota']) ?>"
                                    aria-label="Edit transaksi <?= e($trx['nomor_nota']) ?>"
                                >
                                    <i data-lucide="pencil" aria-hidden="true"></i>
                                </a>
                                <form method="post" action="transaksi_hapus.php">
                                    <input type="hidden" name="id_transaksi" value="<?= e($trx['id_transaksi']) ?>">
                                    <button
                                        class="btn btn--icon btn--danger"
                                        type="submit"
                                        title="Hapus transaksi <?= e($trx['nomor_nota']) ?>"
                                        aria-label="Hapus transaksi <?= e($trx['nomor_nota']) ?>"
                                        data-confirm-delete="<?= e($konfirmasiHapus) ?>"
                                    >
                                        <i data-lucide="trash-2" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="cell-num cell-strong">Total Pendapatan (<?= e($jumlahTampil) ?> transaksi)</td>
                    <td class="cell-num cell-strong"><?= e(formatRupiah($totalPendapatan)) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
