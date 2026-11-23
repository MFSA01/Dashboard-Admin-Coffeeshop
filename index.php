<?php
/**
 * B L E N D — Dashboard Admin
 * ---------------------------------------------------------
 * KPI (total menu, transaksi, pendapatan), preview katalog
 * menu populer, dan 5 transaksi terakhir (INNER JOIN).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. KPI
   --------------------------------------------------------- */

$totalMenu = 0;
$hasilMenu = mysqli_query($koneksi, 'SELECT COUNT(*) AS total FROM menu');

if ($hasilMenu !== false) {
    $barisMenu = mysqli_fetch_assoc($hasilMenu);
    $totalMenu = (int) ($barisMenu['total'] ?? 0);
}

$totalTransaksi = 0;
$totalPendapatan = 0;
$hasilTrx = mysqli_query($koneksi, 'SELECT COUNT(*) AS total, COALESCE(SUM(total_harga), 0) AS pendapatan FROM transaksi');

if ($hasilTrx !== false) {
    $barisTrx = mysqli_fetch_assoc($hasilTrx);
    $totalTransaksi  = (int) ($barisTrx['total'] ?? 0);
    $totalPendapatan = (int) ($barisTrx['pendapatan'] ?? 0);
}

/* Ringkasan stok untuk meta KPI */
$totalStok = 0;
$menuHabis = 0;
$hasilStok = mysqli_query($koneksi, 'SELECT COALESCE(SUM(stok), 0) AS total_stok, SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END) AS habis FROM menu');

if ($hasilStok !== false) {
    $barisStok  = mysqli_fetch_assoc($hasilStok);
    $totalStok  = (int) ($barisStok['total_stok'] ?? 0);
    $menuHabis  = (int) ($barisStok['habis'] ?? 0);
}

/* ---------------------------------------------------------
   2. Katalog menu perwakilan (1 menu terbaru per kategori)
   --------------------------------------------------------- */

$menuPopuler = [];

$sqlPopuler = 'SELECT m.id_menu, m.nama_menu, m.kategori, m.deskripsi, m.stok, m.harga, m.gambar,
                      COALESCE(SUM(t.jumlah), 0) AS total_terjual
               FROM menu m
               LEFT JOIN transaksi t ON t.id_menu = m.id_menu
               WHERE m.id_menu IN (SELECT MAX(id_menu) FROM menu GROUP BY kategori)
               GROUP BY m.id_menu, m.nama_menu, m.kategori, m.deskripsi, m.stok, m.harga, m.gambar
               ORDER BY FIELD(m.kategori, ?, ?, ?, ?)';
$stmtPopuler = mysqli_prepare($koneksi, $sqlPopuler);

if ($stmtPopuler !== false) {
    mysqli_stmt_bind_param($stmtPopuler, 'ssss', ...KATEGORI_MENU);
    mysqli_stmt_execute($stmtPopuler);
    $hasilPopuler = mysqli_stmt_get_result($stmtPopuler);

    if ($hasilPopuler !== false) {
        while ($baris = mysqli_fetch_assoc($hasilPopuler)) {
            $menuPopuler[] = $baris;
        }
    }

    mysqli_stmt_close($stmtPopuler);
}

/* ---------------------------------------------------------
   3. 5 transaksi terakhir (INNER JOIN)
   --------------------------------------------------------- */

$transaksiTerakhir = [];
$sqlTransaksi = 'SELECT t.id_transaksi, t.nomor_nota, t.tanggal_pembelian, t.nama_kasir,
                        t.jumlah, t.harga_satuan, t.total_harga, t.metode_pembayaran,
                        m.nama_menu, m.kategori
                 FROM transaksi t
                 INNER JOIN menu m ON m.id_menu = t.id_menu
                 ORDER BY t.tanggal_pembelian DESC, t.id_transaksi DESC
                 LIMIT 5';
$stmtTransaksi = mysqli_prepare($koneksi, $sqlTransaksi);

if ($stmtTransaksi !== false) {
    mysqli_stmt_execute($stmtTransaksi);
    $hasilTransaksi = mysqli_stmt_get_result($stmtTransaksi);

    if ($hasilTransaksi !== false) {
        while ($baris = mysqli_fetch_assoc($hasilTransaksi)) {
            $transaksiTerakhir[] = $baris;
        }
    }

    mysqli_stmt_close($stmtTransaksi);
}

/* ---------------------------------------------------------
   4. Render
   --------------------------------------------------------- */

$halamanAktif     = 'index';
$judulHalaman     = 'Dashboard';
$eyebrowHalaman   = 'B L E N D · Admin Workspace';
$deskripsiHalaman = 'Ringkasan performa katalog menu dan transaksi Coffee Shop B L E N D.';

require_once __DIR__ . '/includes/header.php';
?>

<section class="kpi-grid">

    <article class="kpi-card">
        <div class="kpi-card__top">
            <span class="kpi-card__icon"><i data-lucide="coffee" aria-hidden="true"></i></span>
            <span class="kpi-card__index">01</span>
        </div>
        <div>
            <p class="kpi-card__label">Total Menu</p>
            <p class="kpi-card__value"><?= e($totalMenu) ?></p>
            <p class="kpi-card__meta">
                <i data-lucide="layers" aria-hidden="true"></i>
                <?= e($totalStok) ?> porsi tersedia · <?= e($menuHabis) ?> menu habis
            </p>
        </div>
    </article>

    <article class="kpi-card kpi-card--accent">
        <div class="kpi-card__top">
            <span class="kpi-card__icon"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
            <span class="kpi-card__index">02</span>
        </div>
        <div>
            <p class="kpi-card__label">Total Transaksi</p>
            <p class="kpi-card__value"><?= e($totalTransaksi) ?></p>
            <p class="kpi-card__meta">
                <i data-lucide="calendar-check" aria-hidden="true"></i>
                Seluruh transaksi tercatat
            </p>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-card__top">
            <span class="kpi-card__icon"><i data-lucide="banknote" aria-hidden="true"></i></span>
            <span class="kpi-card__index">03</span>
        </div>
        <div>
            <p class="kpi-card__label">Total Pendapatan</p>
            <p class="kpi-card__value"><?= e(formatRupiah($totalPendapatan)) ?></p>
            <p class="kpi-card__meta">
                <i data-lucide="trending-up" aria-hidden="true"></i>
                Akumulasi nilai penjualan
            </p>
        </div>
    </article>

</section>

<section class="section">
    <div class="section__head">
        <div>
            <p class="eyebrow">Katalog</p>
            <h2 class="section__title">Menu Perwakilan</h2>
            <p class="section__desc">Satu menu terbaru dari setiap kategori B L E N D &mdash; Coffee, Ice Blended, Tea, dan Non Coffee.</p>
        </div>
        <div class="btn-group">
            <a class="btn btn--ghost btn--sm" href="menu.php">
                <i data-lucide="layout-list" aria-hidden="true"></i>
                Lihat Semua Menu
            </a>
        </div>
    </div>

    <?php if ($menuPopuler === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><i data-lucide="coffee" aria-hidden="true"></i></span>
            <p class="empty-state__title">Belum Ada Menu</p>
            <p class="empty-state__desc">Tambahkan menu pertama Anda untuk mulai membangun katalog B L E N D.</p>
            <a class="btn btn--sm" href="menu_tambah.php">
                <i data-lucide="plus" aria-hidden="true"></i>
                Tambah Menu
            </a>
        </div>
    <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($menuPopuler as $menu): ?>
                <?php
                $statusStok  = getStatusStok((int) $menu['stok']);
                $totalJual   = (int) ($menu['total_terjual'] ?? 0);
                $urlGambar   = getMenuImageUrl((string) $menu['gambar'], (string) $menu['kategori']);
                $isCupFoto   = isCupImageUrl($urlGambar);
                ?>
                <article class="menu-card">
                    <div class="menu-card__media">
                        <span class="menu-card__tag">
                            <span class="kategori-dot <?= e(getKategoriDotClass((string) $menu['kategori'])) ?>" aria-hidden="true"></span>
                            <?= e($menu['kategori']) ?>
                        </span>
                        <div class="menu-card__stage">
                            <div class="menu-card__photo">
                                <img
                                    class="menu-card__image<?= $isCupFoto ? ' menu-card__image--cup' : '' ?>"
                                    src="<?= e($urlGambar) ?>"
                                    alt="Foto <?= e($menu['nama_menu']) ?>"
                                    loading="lazy"
                                >
                            </div>
                        </div>
                    </div>
                    <div class="menu-card__body">
                        <h3 class="menu-card__name"><?= e($menu['nama_menu']) ?></h3>
                        <p class="menu-card__desc"><?= e($menu['deskripsi']) ?></p>
                        <div class="menu-card__footer">
                            <div>
                                <p class="menu-card__price"><?= e(formatRupiah((int) $menu['harga'])) ?></p>
                                <p class="menu-card__stock"><?= e($totalJual) ?> terjual · stok <?= e($menu['stok']) ?></p>
                            </div>
                            <span class="badge <?= e($statusStok['class']) ?>"><?= e($statusStok['label']) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section__head">
        <div>
            <p class="eyebrow">Arus Kasir</p>
            <h2 class="section__title">Transaksi Terakhir</h2>
            <p class="section__desc">Lima transaksi terbaru hasil penggabungan data transaksi dan menu.</p>
        </div>
        <div class="btn-group">
            <a class="btn btn--sm" href="transaksi_tambah.php">
                <i data-lucide="plus" aria-hidden="true"></i>
                Catat Transaksi
            </a>
            <a class="btn btn--ghost btn--sm" href="transaksi.php">
                <i data-lucide="arrow-right" aria-hidden="true"></i>
                Semua Transaksi
            </a>
        </div>
    </div>

    <?php if ($transaksiTerakhir === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
            <p class="empty-state__title">Belum Ada Transaksi</p>
            <p class="empty-state__desc">Transaksi yang dicatat kasir akan tampil di sini secara otomatis.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Nomor Nota</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Kasir</th>
                        <th scope="col">Produk</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Metode</th>
                        <th scope="col" class="cell-num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksiTerakhir as $trx): ?>
                        <tr>
                            <td class="cell-code"><?= e($trx['nomor_nota']) ?></td>
                            <td><?= e(formatTanggalIndonesia((string) $trx['tanggal_pembelian'])) ?></td>
                            <td class="cell-strong"><?= e($trx['nama_kasir']) ?></td>
                            <td>
                                <div class="table-product">
                                    <div>
                                        <p class="table-product__name"><?= e($trx['nama_menu']) ?></p>
                                        <p class="table-product__meta"><?= e($trx['kategori']) ?> · <?= e(formatRupiah((int) $trx['harga_satuan'])) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($trx['jumlah']) ?> porsi</td>
                            <td><span class="badge badge--metode"><?= e($trx['metode_pembayaran']) ?></span></td>
                            <td class="cell-num cell-strong"><?= e(formatRupiah((int) $trx['total_harga'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
