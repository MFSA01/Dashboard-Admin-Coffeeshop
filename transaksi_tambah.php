<?php
/**
 * B L E N D — Catat Transaksi (Create + Potong Stok Atomik)
 * ---------------------------------------------------------
 * Harga & total selalu dihitung ulang di server.
 * Stok dipotong dalam satu database transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Nilai awal form
   --------------------------------------------------------- */

$tanggalHariIni = date('Y-m-d');

$nomor_nota         = generateNomorNota($koneksi, $tanggalHariIni);
$tanggal_pembelian  = $tanggalHariIni;
$nama_kasir         = (string) ($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? '');
$id_menu_terpilih   = 0;
$jumlah             = '1';
$metode_pembayaran  = METODE_PEMBAYARAN[0];
$errors             = [];

/* ---------------------------------------------------------
   2. Proses submit
   --------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_nota        = trim((string) ($_POST['nomor_nota'] ?? ''));
    $tanggal_pembelian = trim((string) ($_POST['tanggal_pembelian'] ?? ''));
    $nama_kasir        = trim((string) ($_POST['nama_kasir'] ?? ''));
    $id_menu_terpilih  = (int) ($_POST['id_menu'] ?? 0);
    $jumlah            = trim((string) ($_POST['jumlah'] ?? ''));
    $metode_pembayaran = trim((string) ($_POST['metode_pembayaran'] ?? ''));

    // --- Validasi dasar ---
    if ($nomor_nota === '') {
        $errors['nomor_nota'] = 'Nomor nota wajib diisi.';
    } elseif (mb_strlen($nomor_nota) > 30) {
        $errors['nomor_nota'] = 'Nomor nota maksimal 30 karakter.';
    }

    $tanggalValid = DateTimeImmutable::createFromFormat('Y-m-d', $tanggal_pembelian);

    if ($tanggal_pembelian === '' || $tanggalValid === false
        || $tanggalValid->format('Y-m-d') !== $tanggal_pembelian) {
        $errors['tanggal_pembelian'] = 'Tanggal pembelian wajib berupa tanggal yang valid.';
    }

    if ($nama_kasir === '') {
        $errors['nama_kasir'] = 'Nama kasir wajib diisi.';
    } elseif (mb_strlen($nama_kasir) > 100) {
        $errors['nama_kasir'] = 'Nama kasir maksimal 100 karakter.';
    }

    if ($id_menu_terpilih <= 0) {
        $errors['id_menu'] = 'Pilih menu yang dibeli.';
    }

    if ($jumlah === '' || !ctype_digit($jumlah) || (int) $jumlah < 1) {
        $errors['jumlah'] = 'Jumlah wajib berupa angka bulat minimal 1.';
    }

    if (!in_array($metode_pembayaran, METODE_PEMBAYARAN, true)) {
        $errors['metode_pembayaran'] = 'Pilih metode pembayaran yang valid.';
    }

    // --- Cek duplikat nomor nota ---
    if (!isset($errors['nomor_nota'])) {
        $stmtCek = mysqli_prepare($koneksi, 'SELECT COUNT(*) AS total FROM transaksi WHERE nomor_nota = ?');

        if ($stmtCek !== false) {
            mysqli_stmt_bind_param($stmtCek, 's', $nomor_nota);
            mysqli_stmt_execute($stmtCek);
            $hasilCek = mysqli_stmt_get_result($stmtCek);
            $barisCek = $hasilCek !== false ? mysqli_fetch_assoc($hasilCek) : null;
            mysqli_stmt_close($stmtCek);

            if ((int) ($barisCek['total'] ?? 0) > 0) {
                $errors['nomor_nota'] = 'Nomor nota sudah dipakai. Gunakan nomor lain.';
            }
        }
    }

    // --- Simpan dengan database transaction ---
    if ($errors === []) {
        $jumlahBeli = (int) $jumlah;

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            mysqli_begin_transaction($koneksi);

            // Harga & stok asli dari database (dikunci selama transaksi).
            $stmtMenu = mysqli_prepare($koneksi, 'SELECT nama_menu, harga, stok FROM menu WHERE id_menu = ? FOR UPDATE');

            if ($stmtMenu === false) {
                throw new RuntimeException('Gagal membaca data menu.');
            }

            mysqli_stmt_bind_param($stmtMenu, 'i', $id_menu_terpilih);
            mysqli_stmt_execute($stmtMenu);
            $hasilMenu = mysqli_stmt_get_result($stmtMenu);
            $menu      = $hasilMenu !== false ? mysqli_fetch_assoc($hasilMenu) : null;
            mysqli_stmt_close($stmtMenu);

            if (!is_array($menu)) {
                throw new RuntimeException('Menu yang dipilih tidak ditemukan.');
            }

            $stokTersedia = (int) $menu['stok'];
            $hargaSatuan  = (int) $menu['harga'];
            $namaMenu     = (string) $menu['nama_menu'];

            if ($jumlahBeli > $stokTersedia) {
                mysqli_rollback($koneksi);
                mysqli_report(MYSQLI_REPORT_OFF);

                $errors['jumlah'] = 'Stok "' . $namaMenu . '" tidak cukup. '
                    . 'Sisa stok tersedia: ' . $stokTersedia . ' porsi.';
            } else {
                $totalHarga = hitungTotalHarga($hargaSatuan, $jumlahBeli);

                $stmtInsert = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO transaksi
                        (nomor_nota, tanggal_pembelian, nama_kasir, id_menu, jumlah, harga_satuan, total_harga, metode_pembayaran)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                if ($stmtInsert === false) {
                    throw new RuntimeException('Gagal menyiapkan penyimpanan transaksi.');
                }

                mysqli_stmt_bind_param(
                    $stmtInsert,
                    'sssiiiis',
                    $nomor_nota,
                    $tanggal_pembelian,
                    $nama_kasir,
                    $id_menu_terpilih,
                    $jumlahBeli,
                    $hargaSatuan,
                    $totalHarga,
                    $metode_pembayaran
                );

                mysqli_stmt_execute($stmtInsert);
                mysqli_stmt_close($stmtInsert);

                // Potong stok setelah transaksi tercatat.
                $stmtStok = mysqli_prepare($koneksi, 'UPDATE menu SET stok = stok - ? WHERE id_menu = ?');

                if ($stmtStok === false) {
                    throw new RuntimeException('Gagal menyiapkan pengurangan stok.');
                }

                mysqli_stmt_bind_param($stmtStok, 'ii', $jumlahBeli, $id_menu_terpilih);
                mysqli_stmt_execute($stmtStok);
                mysqli_stmt_close($stmtStok);

                mysqli_commit($koneksi);
                mysqli_report(MYSQLI_REPORT_OFF);

                setFlashMessage(
                    'success',
                    'Transaksi "' . $nomor_nota . '" berhasil dicatat. Stok "' . $namaMenu . '" berkurang '
                    . $jumlahBeli . ' porsi.'
                );

                header('Location: transaksi.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($koneksi);
            mysqli_report(MYSQLI_REPORT_OFF);

            if ((int) $e->getCode() === 1062) {
                $errors['nomor_nota'] = 'Nomor nota sudah dipakai. Gunakan nomor lain.';
            } else {
                $errors['umum'] = 'Transaksi gagal disimpan: ' . $e->getMessage();
            }
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            mysqli_report(MYSQLI_REPORT_OFF);
            $errors['umum'] = $e->getMessage();
        }
    }
}

/* ---------------------------------------------------------
   3. Data dropdown menu
   --------------------------------------------------------- */

$daftarMenu = [];
$hasilMenu  = mysqli_query(
    $koneksi,
    'SELECT id_menu, nama_menu, kategori, stok, harga FROM menu ORDER BY kategori ASC, nama_menu ASC'
);

if ($hasilMenu !== false) {
    while ($barisMenu = mysqli_fetch_assoc($hasilMenu)) {
        $daftarMenu[] = $barisMenu;
    }
}

// Menu terpilih (untuk kalkulasi awal & preview).
$menuTerpilih = null;

foreach ($daftarMenu as $kandidat) {
    if ((int) $kandidat['id_menu'] === $id_menu_terpilih) {
        $menuTerpilih = $kandidat;
        break;
    }
}

if ($menuTerpilih === null && $daftarMenu !== []) {
    $menuTerpilih     = $daftarMenu[0];
    $id_menu_terpilih = (int) $menuTerpilih['id_menu'];
}

$hargaSatuanAwal = $menuTerpilih !== null ? (int) $menuTerpilih['harga'] : 0;
$jumlahAwal      = max(1, (int) $jumlah);
$totalHargaAwal  = hitungTotalHarga($hargaSatuanAwal, $jumlahAwal);

/* ---------------------------------------------------------
   4. Render
   --------------------------------------------------------- */

$halamanAktif     = 'transaksi';
$judulHalaman     = 'Catat Transaksi';
$eyebrowHalaman   = 'B L E N D · Arus Kasir';
$deskripsiHalaman = 'Catat pembelian baru. Stok menu otomatis berkurang setelah transaksi tersimpan.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-layout">
    <div class="form-card">
        <div class="form-card__head">
            <span class="form-card__icon"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
            <div>
                <h2 class="form-card__title">Detail Transaksi Baru</h2>
                <p class="form-card__desc">Kolom bertanda <span class="required">*</span> wajib diisi.</p>
            </div>
        </div>

        <?php if (isset($errors['umum'])): ?>
            <div class="alert alert--error" role="alert">
                <i class="alert__icon" data-lucide="alert-circle" aria-hidden="true"></i>
                <p class="alert__message"><?= e($errors['umum']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($daftarMenu === []): ?>
            <div class="alert alert--warning" role="alert">
                <i class="alert__icon" data-lucide="alert-triangle" aria-hidden="true"></i>
                <p class="alert__message">
                    Belum ada menu terdaftar. <a href="menu_tambah.php">Tambahkan menu</a> terlebih dahulu sebelum mencatat transaksi.
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="transaksi_tambah.php" novalidate>
            <div class="form-grid">

                <div class="form-field">
                    <label class="form-label" for="nomor_nota">Nomor Nota <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['nomor_nota']) ? 'is-invalid' : '' ?>"
                        type="text"
                        id="nomor_nota"
                        name="nomor_nota"
                        value="<?= e($nomor_nota) ?>"
                        placeholder="BLD-YYYYMMDD-XXX"
                        maxlength="30"
                        required
                    >
                    <p class="form-hint">Dibuat otomatis, tetap bisa disesuaikan.</p>
                    <?php if (isset($errors['nomor_nota'])): ?>
                        <p class="form-error"><?= e($errors['nomor_nota']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" for="tanggal_pembelian">Tanggal Pembelian <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['tanggal_pembelian']) ? 'is-invalid' : '' ?>"
                        type="date"
                        id="tanggal_pembelian"
                        name="tanggal_pembelian"
                        value="<?= e($tanggal_pembelian) ?>"
                        required
                    >
                    <?php if (isset($errors['tanggal_pembelian'])): ?>
                        <p class="form-error"><?= e($errors['tanggal_pembelian']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <label class="form-label" for="nama_kasir">Nama Kasir <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['nama_kasir']) ? 'is-invalid' : '' ?>"
                        type="text"
                        id="nama_kasir"
                        name="nama_kasir"
                        value="<?= e($nama_kasir) ?>"
                        placeholder="Nama kasir yang bertugas"
                        maxlength="100"
                        required
                    >
                    <?php if (isset($errors['nama_kasir'])): ?>
                        <p class="form-error"><?= e($errors['nama_kasir']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <label class="form-label" for="id_menu">Produk <span class="required">*</span></label>
                    <select
                        class="form-select <?= isset($errors['id_menu']) ? 'is-invalid' : '' ?>"
                        id="id_menu"
                        name="id_menu"
                        data-harga-source
                        required
                    >
                        <?php foreach ($daftarMenu as $opsi): ?>
                            <?php
                            $opsiStok   = (int) $opsi['stok'];
                            $opsiHarga  = (int) $opsi['harga'];
                            $opsiId     = (int) $opsi['id_menu'];
                            $terpilih   = $opsiId === $id_menu_terpilih;
                            ?>
                            <option
                                value="<?= e($opsiId) ?>"
                                data-harga="<?= e($opsiHarga) ?>"
                                data-stok="<?= e($opsiStok) ?>"
                                data-nama="<?= e($opsi['nama_menu']) ?>"
                                <?= $terpilih ? 'selected' : '' ?>
                                <?= $opsiStok === 0 ? 'disabled' : '' ?>
                            >
                                <?= e($opsi['nama_menu']) ?> — <?= e(formatRupiah($opsiHarga)) ?>
                                · stok <?= e($opsiStok) ?> · <?= e($opsi['kategori']) ?>
                                <?= $opsiStok === 0 ? ' (HABIS)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" data-stok-info>Pilih produk untuk melihat ketersediaan stok.</p>
                    <?php if (isset($errors['id_menu'])): ?>
                        <p class="form-error"><?= e($errors['id_menu']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" for="jumlah">Jumlah <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['jumlah']) ? 'is-invalid' : '' ?>"
                        type="number"
                        id="jumlah"
                        name="jumlah"
                        value="<?= e($jumlah) ?>"
                        min="1"
                        step="1"
                        inputmode="numeric"
                        data-jumlah-input
                        required
                    >
                    <?php if (isset($errors['jumlah'])): ?>
                        <p class="form-error"><?= e($errors['jumlah']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" for="harga_satuan_display">Harga Satuan</label>
                    <div class="input-prefix">
                        <span class="input-prefix__label">Rp</span>
                        <input
                            class="form-control calc-readonly"
                            type="text"
                            id="harga_satuan_display"
                            value="<?= e(number_format($hargaSatuanAwal, 0, ',', '.')) ?>"
                            data-harga-satuan
                            data-format="angka"
                            readonly
                            tabindex="-1"
                            aria-live="polite"
                        >
                    </div>
                    <p class="form-hint">Ditentukan otomatis dari data menu.</p>
                </div>

                <div class="form-field form-field--full">
                    <span class="form-label">Metode Pembayaran <span class="required">*</span></span>
                    <div class="radio-group" role="radiogroup" aria-label="Metode pembayaran">
                        <?= renderRadioOptions('metode_pembayaran', METODE_PEMBAYARAN, $metode_pembayaran, 'metode') ?>
                    </div>
                    <?php if (isset($errors['metode_pembayaran'])): ?>
                        <p class="form-error"><?= e($errors['metode_pembayaran']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <div class="calc-box">
                        <div class="calc-box__head">
                            <span class="calc-box__label">Total Pembayaran</span>
                            <span class="calc-box__badge">
                                <i data-lucide="calculator" aria-hidden="true"></i>
                                Dihitung otomatis
                            </span>
                        </div>
                        <p class="calc-box__value" data-total-harga><?= e(formatRupiah($totalHargaAwal)) ?></p>
                        <p class="calc-box__note">
                            Total final tetap dihitung ulang di server saat transaksi disimpan.
                        </p>
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <button class="btn" type="submit" <?= $daftarMenu === [] ? 'disabled' : '' ?>>
                    <i data-lucide="save" aria-hidden="true"></i>
                    Simpan Transaksi
                </button>
                <a class="btn btn--ghost" href="transaksi.php">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                    Batal
                </a>
                <span class="form-actions__spacer"></span>
                <span class="form-actions__note">
                    <i data-lucide="info" aria-hidden="true"></i>
                    Stok berkurang otomatis.
                </span>
            </div>
        </form>
    </div>

    <aside class="form-aside">
        <div class="summary-card">
            <p class="summary-card__label">Ringkasan</p>
            <p class="summary-card__value" data-ringkasan-total><?= e(formatRupiah($totalHargaAwal)) ?></p>
            <div class="stack-sm mt-lg">
                <div class="summary-card__row">
                    <span>Produk</span>
                    <strong data-ringkasan-produk><?= e($menuTerpilih['nama_menu'] ?? '-') ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Harga satuan</span>
                    <strong data-ringkasan-harga><?= e(formatRupiah($hargaSatuanAwal)) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Jumlah</span>
                    <strong><span data-ringkasan-jumlah><?= e($jumlahAwal) ?></span> porsi</strong>
                </div>
                <div class="summary-card__row">
                    <span>Stok tersisa</span>
                    <strong><span data-ringkasan-stok><?= e(max(0, (int) ($menuTerpilih['stok'] ?? 0) - $jumlahAwal)) ?></span> porsi</strong>
                </div>
            </div>
        </div>

        <div class="card card--pad mt-lg">
            <p class="card__title">Alur Pencatatan</p>
            <ul class="tip-list">
                <li><i data-lucide="mouse-pointer-click" aria-hidden="true"></i> Pilih produk dan tentukan jumlah porsi.</li>
                <li><i data-lucide="shield-check" aria-hidden="true"></i> Sistem memvalidasi sisa stok sebelum menyimpan.</li>
                <li><i data-lucide="package-minus" aria-hidden="true"></i> Stok menu langsung berkurang setelah transaksi tersimpan.</li>
            </ul>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
