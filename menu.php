<?php
/**
 * B L E N D — Katalog Menu (Read, Filter Kategori & Pencarian)
 * ---------------------------------------------------------
 * Query pencarian & filter wajib memakai Prepared Statements.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Parameter filter & pencarian
   --------------------------------------------------------- */

$kategoriDipilih = trim((string) ($_GET['kategori'] ?? ''));
$kataKunci       = trim((string) ($_GET['q'] ?? ''));

if ($kategoriDipilih !== '' && !in_array($kategoriDipilih, KATEGORI_MENU, true)) {
    $kategoriDipilih = '';
}

/* ---------------------------------------------------------
   2. Query daftar menu (prepared statement dinamis)
   --------------------------------------------------------- */

$where  = [];
$types  = '';
$params = [];

if ($kategoriDipilih !== '') {
    $where[]  = 'm.kategori = ?';
    $types   .= 's';
    $params[] = $kategoriDipilih;
}

if ($kataKunci !== '') {
    $where[]  = 'm.nama_menu LIKE ?';
    $types   .= 's';
    $params[] = '%' . $kataKunci . '%';
}

$sql = 'SELECT m.id_menu, m.nama_menu, m.kategori, m.deskripsi, m.stok, m.harga, m.gambar, m.created_at,
               COALESCE(SUM(t.jumlah), 0) AS total_terjual
        FROM menu m
        LEFT JOIN transaksi t ON t.id_menu = m.id_menu';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY m.id_menu, m.nama_menu, m.kategori, m.deskripsi, m.stok, m.harga, m.gambar, m.created_at
          ORDER BY m.created_at DESC, m.id_menu DESC';

$daftarMenu = [];
$stmt       = mysqli_prepare($koneksi, $sql);

if ($stmt !== false) {
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);

    if ($hasil !== false) {
        while ($baris = mysqli_fetch_assoc($hasil)) {
            $daftarMenu[] = $baris;
        }
    }

    mysqli_stmt_close($stmt);
}

/* ---------------------------------------------------------
   3. Jumlah menu per kategori (untuk label tab filter)
   --------------------------------------------------------- */

$jumlahKategori = array_fill_keys(KATEGORI_MENU, 0);
$jumlahSemua    = 0;
$hasilHitung    = mysqli_query($koneksi, 'SELECT kategori, COUNT(*) AS total FROM menu GROUP BY kategori');

if ($hasilHitung !== false) {
    while ($barisHitung = mysqli_fetch_assoc($hasilHitung)) {
        $kunci = (string) $barisHitung['kategori'];

        if (array_key_exists($kunci, $jumlahKategori)) {
            $jumlahKategori[$kunci] = (int) $barisHitung['total'];
        }

        $jumlahSemua += (int) $barisHitung['total'];
    }
}

/* ---------------------------------------------------------
   4. URL bantu untuk tab filter (mempertahankan kata kunci)
   --------------------------------------------------------- */

$buatUrlFilter = static function (string $kategori) use ($kataKunci): string {
    $query = [];

    if ($kategori !== '') {
        $query['kategori'] = $kategori;
    }

    if ($kataKunci !== '') {
        $query['q'] = $kataKunci;
    }

    return 'menu.php' . ($query !== [] ? '?' . http_build_query($query) : '');
};

$tabFilter = ['' => 'Semua'];

foreach (KATEGORI_MENU as $namaKategori) {
    $tabFilter[$namaKategori] = $namaKategori;
}

/* ---------------------------------------------------------
   5. Render
   --------------------------------------------------------- */

$halamanAktif     = 'menu';
$judulHalaman     = 'Katalog Menu';
$eyebrowHalaman   = 'B L E N D · Master Data';
$deskripsiHalaman = 'Kelola seluruh menu Coffee, Ice Blended, Tea, dan Non Coffee, pantau stok, serta perbarui katalog B L E N D.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
    <nav class="filter-tabs" aria-label="Filter kategori menu">
        <?php foreach ($tabFilter as $nilaiKategori => $labelKategori): ?>
            <?php
            $jumlahTab = $nilaiKategori === '' ? $jumlahSemua : ($jumlahKategori[$nilaiKategori] ?? 0);
            $isAktif   = $kategoriDipilih === $nilaiKategori;
            ?>
            <a class="filter-tabs__link <?= $isAktif ? 'is-active' : '' ?>"
               href="<?= e($buatUrlFilter($nilaiKategori)) ?>"
               <?= $isAktif ? 'aria-current="page"' : '' ?>>
                <?= e($labelKategori) ?>
                <span class="filter-tabs__count"><?= e($jumlahTab) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="page-actions__right">
        <form class="search-form" method="get" action="menu.php" role="search">
            <?php if ($kategoriDipilih !== ''): ?>
                <input type="hidden" name="kategori" value="<?= e($kategoriDipilih) ?>">
            <?php endif; ?>

            <label class="sr-only" for="pencarian-menu">Cari nama menu</label>
            <input
                class="search-form__input"
                type="search"
                id="pencarian-menu"
                name="q"
                value="<?= e($kataKunci) ?>"
                placeholder="Cari nama menu..."
                autocomplete="off"
            >
            <button class="btn btn--sm" type="submit">
                <i data-lucide="search" aria-hidden="true"></i>
                Cari
            </button>
        </form>

        <a class="btn" href="menu_tambah.php">
            <i data-lucide="plus" aria-hidden="true"></i>
            Tambah Menu
        </a>
    </div>
</div>

<?php if ($kataKunci !== '' || $kategoriDipilih !== ''): ?>
    <p class="result-note">
        <i data-lucide="filter" aria-hidden="true"></i>
        Menampilkan <strong><?= e(count($daftarMenu)) ?></strong> menu
        <?php if ($kategoriDipilih !== ''): ?>
            pada kategori <strong><?= e($kategoriDipilih) ?></strong>
        <?php endif; ?>
        <?php if ($kataKunci !== ''): ?>
            dengan kata kunci &ldquo;<?= e($kataKunci) ?>&rdquo;
        <?php endif; ?>
        · <a href="menu.php">Reset filter</a>
    </p>
<?php endif; ?>

<?php if ($daftarMenu === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><i data-lucide="coffee" aria-hidden="true"></i></span>
        <?php if ($kataKunci !== '' || $kategoriDipilih !== ''): ?>
            <p class="empty-state__title">Menu Tidak Ditemukan</p>
            <p class="empty-state__desc">
                Tidak ada menu yang cocok dengan filter atau kata kunci pencarian Anda.
                Coba kata kunci lain atau tampilkan seluruh menu.
            </p>
            <div class="btn-group">
                <a class="btn btn--secondary btn--sm" href="menu.php">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Reset Filter
                </a>
                <a class="btn btn--sm" href="menu_tambah.php">
                    <i data-lucide="plus" aria-hidden="true"></i>
                    Tambah Menu
                </a>
            </div>
        <?php else: ?>
            <p class="empty-state__title">Katalog Masih Kosong</p>
            <p class="empty-state__desc">
                Belum ada menu terdaftar. Tambahkan menu pertama untuk mulai membangun katalog B L E N D.
            </p>
            <a class="btn btn--sm" href="menu_tambah.php">
                <i data-lucide="plus" aria-hidden="true"></i>
                Tambah Menu
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="menu-grid">
        <?php foreach ($daftarMenu as $menu): ?>
            <?php
            $statusStok = getStatusStok((int) $menu['stok']);
            $urlGambar  = getMenuImageUrl((string) $menu['gambar'], (string) $menu['kategori']);
            $isCupFoto  = isCupImageUrl($urlGambar);
            $konfirmasi = 'Yakin ingin menghapus menu "' . $menu['nama_menu'] . '"? Tindakan ini tidak dapat dibatalkan.';
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
                            <p class="menu-card__stock">
                                Stok <?= e($menu['stok']) ?> · <?= e($menu['total_terjual']) ?> terjual
                            </p>
                        </div>
                        <span class="badge <?= e($statusStok['class']) ?>"><?= e($statusStok['label']) ?></span>
                    </div>

                    <div class="menu-card__actions">
                        <a class="btn btn--ghost btn--sm" href="menu_edit.php?id=<?= e($menu['id_menu']) ?>">
                            <i data-lucide="pencil" aria-hidden="true"></i>
                            Edit
                        </a>
                        <form class="menu-card__delete" method="post" action="menu_hapus.php">
                            <input type="hidden" name="id_menu" value="<?= e($menu['id_menu']) ?>">
                            <button
                                class="btn btn--danger btn--sm"
                                type="submit"
                                data-confirm-delete="<?= e($konfirmasi) ?>"
                            >
                                <i data-lucide="trash-2" aria-hidden="true"></i>
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
