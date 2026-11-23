<?php
/**
 * B L E N D — Edit Transaksi (Update + Penyesuaian Stok Aman)
 * ---------------------------------------------------------
 * Stok lama dikembalikan dulu, lalu stok baru dipotong.
 * Semua langkah berjalan dalam satu database transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Ambil transaksi berdasarkan parameter id
   --------------------------------------------------------- */

$idTransaksi = (int) ($_GET['id'] ?? 0);

if ($idTransaksi <= 0) {
    setFlashMessage('error', 'ID transaksi tidak valid.');
    header('Location: transaksi.php');
    exit;
}

$sqlAmbil  = 'SELECT id_transaksi, nomor_nota, tanggal_pembelian, nama_kasir, id_menu,
                     jumlah, harga_satuan, total_harga, metode_pembayaran
              FROM transaksi WHERE id_transaksi = ? LIMIT 1';
$stmtAmbil = mysqli_prepare($koneksi, $sqlAmbil);

if ($stmtAmbil === false) {
    setFlashMessage('error', 'Terjadi kesalahan saat mengambil data transaksi.');
    header('Location: transaksi.php');
    exit;
}

mysqli_stmt_bind_param($stmtAmbil, 'i', $idTransaksi);
mysqli_stmt_execute($stmtAmbil);
$hasilAmbil    = mysqli_stmt_get_result($stmtAmbil);
$transaksiLama = $hasilAmbil !== false ? mysqli_fetch_assoc($hasilAmbil) : null;
mysqli_stmt_close($stmtAmbil);

if (!is_array($transaksiLama)) {
    setFlashMessage('error', 'Transaksi yang Anda cari tidak ditemukan.');
    header('Location: transaksi.php');
    exit;
}

/* ---------------------------------------------------------
   2. Nilai awal form dari database
   --------------------------------------------------------- */

$nomor_nota        = (string) $transaksiLama['nomor_nota'];
$tanggal_pembelian = (string) $transaksiLama['tanggal_pembelian'];
$nama_kasir        = (string) $transaksiLama['nama_kasir'];
$id_menu_terpilih  = (int) $transaksiLama['id_menu'];
$jumlah            = (string) $transaksiLama['jumlah'];
$metode_pembayaran = (string) $transaksiLama['metode_pembayaran'];
$errors            = [];

$oldIdMenu = (int) $transaksiLama['id_menu'];
$oldJumlah = (int) $transaksiLama['jumlah'];

/* ---------------------------------------------------------
   3. Proses submit
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

    // --- Cek duplikat nomor nota (selain transaksi ini) ---
    if (!isset($errors['nomor_nota'])) {
        $stmtCek = mysqli_prepare(
            $koneksi,
            'SELECT COUNT(*) AS total FROM transaksi WHERE nomor_nota = ? AND id_transaksi <> ?'
        );

        if ($stmtCek !== false) {
            mysqli_stmt_bind_param($stmtCek, 'si', $nomor_nota, $idTransaksi);
            mysqli_stmt_execute($stmtCek);
            $hasilCek = mysqli_stmt_get_result($stmtCek);
            $barisCek = $hasilCek !== false ? mysqli_fetch_assoc($hasilCek) : null;
            mysqli_stmt_close($stmtCek);

            if ((int) ($barisCek['total'] ?? 0) > 0) {
                $errors['nomor_nota'] = 'Nomor nota sudah dipakai transaksi lain.';
            }
        }
    }

    /* -----------------------------------------------------
       Simpan: kembalikan stok lama -> potong stok baru
       ----------------------------------------------------- */
    if ($errors === []) {
        $jumlahBaru = (int) $jumlah;

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $rollbackDilakukan = false;

        try {
            mysqli_begin_transaction($koneksi);

            // Kunci baris transaksi agar tidak berubah di tengah proses.
            $stmtKunci = mysqli_prepare(
                $koneksi,
                'SELECT id_menu, jumlah FROM transaksi WHERE id_transaksi = ? FOR UPDATE'
            );

            if ($stmtKunci === false) {
                throw new RuntimeException('Gagal mengunci data transaksi.');
            }

            mysqli_stmt_bind_param($stmtKunci, 'i', $idTransaksi);
            mysqli_stmt_execute($stmtKunci);
            $hasilKunci = mysqli_stmt_get_result($stmtKunci);
            $barisKunci = $hasilKunci !== false ? mysqli_fetch_assoc($hasilKunci) : null;
            mysqli_stmt_close($stmtKunci);

            if (!is_array($barisKunci)) {
                throw new RuntimeException('Transaksi sudah tidak tersedia.');
            }

            $oldIdMenu = (int) $barisKunci['id_menu'];
            $oldJumlah = (int) $barisKunci['jumlah'];

            // 1. Kembalikan stok lama.
            $stmtBalik = mysqli_prepare($koneksi, 'UPDATE menu SET stok = stok + ? WHERE id_menu = ?');

            if ($stmtBalik === false) {
                throw new RuntimeException('Gagal menyiapkan pengembalian stok lama.');
            }

            mysqli_stmt_bind_param($stmtBalik, 'ii', $oldJumlah, $oldIdMenu);
            mysqli_stmt_execute($stmtBalik);
            mysqli_stmt_close($stmtBalik);

            // 2. Ambil harga & stok terbaru untuk menu tujuan.
            $stmtMenu = mysqli_prepare(
                $koneksi,
                'SELECT nama_menu, harga, stok FROM menu WHERE id_menu = ? FOR UPDATE'
            );

            if ($stmtMenu === false) {
                throw new RuntimeException('Gagal membaca data menu baru.');
            }

            mysqli_stmt_bind_param($stmtMenu, 'i', $id_menu_terpilih);
            mysqli_stmt_execute($stmtMenu);
            $hasilMenu = mysqli_stmt_get_result($stmtMenu);
            $menuBaru  = $hasilMenu !== false ? mysqli_fetch_assoc($hasilMenu) : null;
            mysqli_stmt_close($stmtMenu);

            if (!is_array($menuBaru)) {
                throw new RuntimeException('Menu yang dipilih tidak ditemukan.');
            }

            $stokTersedia = (int) $menuBaru['stok'];
            $hargaBaru    = (int) $menuBaru['harga'];
            $namaMenuBaru = (string) $menuBaru['nama_menu'];

            // 3. Validasi stok setelah stok lama dikembalikan.
            if ($jumlahBaru > $stokTersedia) {
                mysqli_rollback($koneksi);
                $rollbackDilakukan = true;

                $errors['jumlah'] = 'Stok "' . $namaMenuBaru . '" tidak cukup. '
                    . 'Sisa stok tersedia: ' . $stokTersedia . ' porsi.';
            } else {
                // 4. Potong stok baru.
                $stmtPotong = mysqli_prepare($koneksi, 'UPDATE menu SET stok = stok - ? WHERE id_menu = ?');

                if ($stmtPotong === false) {
                    throw new RuntimeException('Gagal menyiapkan pengurangan stok baru.');
                }

                mysqli_stmt_bind_param($stmtPotong, 'ii', $jumlahBaru, $id_menu_terpilih);
                mysqli_stmt_execute($stmtPotong);
                mysqli_stmt_close($stmtPotong);

                // 5. Hitung ulang total & perbarui transaksi.
                $totalHargaBaru = hitungTotalHarga($hargaBaru, $jumlahBaru);

                $stmtUpdate = mysqli_prepare(
                    $koneksi,
                    'UPDATE transaksi
                        SET nomor_nota = ?, tanggal_pembelian = ?, nama_kasir = ?, id_menu = ?,
                            jumlah = ?, harga_satuan = ?, total_harga = ?, metode_pembayaran = ?
                      WHERE id_transaksi = ?'
                );

                if ($stmtUpdate === false) {
                    throw new RuntimeException('Gagal menyiapkan pembaruan transaksi.');
                }

                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    'sssiiiisi',
                    $nomor_nota,
                    $tanggal_pembelian,
                    $nama_kasir,
                    $id_menu_terpilih,
                    $jumlahBaru,
                    $hargaBaru,
                    $totalHargaBaru,
                    $metode_pembayaran,
                    $idTransaksi
                );

                mysqli_stmt_execute($stmtUpdate);
                mysqli_stmt_close($stmtUpdate);

                mysqli_commit($koneksi);
                mysqli_report(MYSQLI_REPORT_OFF);

                setFlashMessage(
                    'success',
                    'Transaksi "' . $nomor_nota . '" berhasil diperbarui. Stok "' . $namaMenuBaru
                    . '" kini berkurang ' . $jumlahBaru . ' porsi.'
                );

                header('Location: transaksi.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            if (!$rollbackDilakukan) {
                mysqli_rollback($koneksi);
            }

            mysqli_report(MYSQLI_REPORT_OFF);

            if ((int) $e->getCode() === 1062) {
                $errors['nomor_nota'] = 'Nomor nota sudah dipakai transaksi lain.';
            } else {
                $errors['umum'] = 'Transaksi gagal diperbarui: ' . $e->getMessage();
            }
        } catch (Throwable $e) {
            if (!$rollbackDilakukan) {
                mysqli_rollback($koneksi);
            }

            mysqli_report(MYSQLI_REPORT_OFF);
            $errors['umum'] = $e->getMessage();
        }

        if ($rollbackDilakukan) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
    }
}

/* ---------------------------------------------------------
   4. Data dropdown menu
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

// Stok efektif menu terpilih: menu lama mendapat pengembalian stok lama.
$stokEfektifAwal = (int) ($menuTerpilih['stok'] ?? 0);

if ($menuTerpilih !== null && (int) $menuTerpilih['id_menu'] === $oldIdMenu) {
    $stokEfektifAwal += $oldJumlah;
}

$stokSisaAwal = max(0, $stokEfektifAwal - $jumlahAwal);

/* ---------------------------------------------------------
   5. Render
   --------------------------------------------------------- */

$halamanAktif     = 'transaksi';
$judulHalaman     = 'Edit Transaksi';
$eyebrowHalaman   = 'B L E N D · Arus Kasir';
$deskripsiHalaman = 'Perbarui transaksi "' . $nomor_nota . '". Stok lama dikembalikan lalu stok baru dipotong otomatis.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-layout">
    <div class="form-card">
        <div class="form-card__head">
            <span class="form-card__icon"><i data-lucide="pencil" aria-hidden="true"></i></span>
            <div>
                <h2 class="form-card__title">Perbarui Transaksi</h2>
                <p class="form-card__desc">Transaksi #<?= e($idTransaksi) ?> · kolom <span class="required">*</span> wajib diisi.</p>
            </div>
        </div>

        <?php if (isset($errors['umum'])): ?>
            <div class="alert alert--error" role="alert">
                <i class="alert__icon" data-lucide="alert-circle" aria-hidden="true"></i>
                <p class="alert__message"><?= e($errors['umum']) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="transaksi_edit.php?id=<?= e($idTransaksi) ?>" novalidate>
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
                            $opsiStok  = (int) $opsi['stok'];
                            $opsiHarga = (int) $opsi['harga'];
                            $opsiId    = (int) $opsi['id_menu'];
                            $terpilih  = $opsiId === $id_menu_terpilih;

                            // Menu lama tetap bisa dipilih walau stok 0 karena stoknya akan dikembalikan.
                            $stokLive = $opsiId === $oldIdMenu ? $opsiStok + $oldJumlah : $opsiStok;
                            $nonaktif = $stokLive <= 0 && !$terpilih;
                            ?>
                            <option
                                value="<?= e($opsiId) ?>"
                                data-harga="<?= e($opsiHarga) ?>"
                                data-stok="<?= e($stokLive) ?>"
                                data-nama="<?= e($opsi['nama_menu']) ?>"
                                <?= $terpilih ? 'selected' : '' ?>
                                <?= $nonaktif ? 'disabled' : '' ?>
                            >
                                <?= e($opsi['nama_menu']) ?> — <?= e(formatRupiah($opsiHarga)) ?>
                                · stok <?= e($stokLive) ?> · <?= e($opsi['kategori']) ?>
                                <?= $stokLive <= 0 ? ' (HABIS)' : '' ?>
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
                            Total final tetap dihitung ulang di server saat perubahan disimpan.
                        </p>
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <button class="btn" type="submit">
                    <i data-lucide="save" aria-hidden="true"></i>
                    Simpan Perubahan
                </button>
                <a class="btn btn--ghost" href="transaksi.php">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                    Batal
                </a>
                <span class="form-actions__spacer"></span>
                <button
                    class="btn btn--danger btn--sm"
                    type="submit"
                    form="form-hapus-transaksi"
                    data-confirm-delete="Yakin ingin menghapus transaksi &quot;<?= e($nomor_nota) ?>&quot;? Stok akan dikembalikan."
                >
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                    Hapus Transaksi
                </button>
            </div>
        </form>

        <form id="form-hapus-transaksi" method="post" action="transaksi_hapus.php" hidden>
            <input type="hidden" name="id_transaksi" value="<?= e($idTransaksi) ?>">
        </form>
    </div>

    <aside class="form-aside">
        <div class="summary-card">
            <p class="summary-card__label">Ringkasan Transaksi</p>
            <p class="summary-card__value" data-ringkasan-total><?= e(formatRupiah($totalHargaAwal)) ?></p>
            <div class="stack-sm mt-lg">
                <div class="summary-card__row">
                    <span>Nomor nota</span>
                    <strong><?= e($nomor_nota) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Tanggal</span>
                    <strong><?= e(formatTanggalIndonesia($tanggal_pembelian)) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Metode</span>
                    <strong><?= e($metode_pembayaran) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Produk</span>
                    <strong data-ringkasan-produk><?= e($menuTerpilih['nama_menu'] ?? '-') ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Jumlah</span>
                    <strong><span data-ringkasan-jumlah><?= e($jumlahAwal) ?></span> porsi</strong>
                </div>
                <div class="summary-card__row">
                    <span>Stok tersisa</span>
                    <strong><span data-ringkasan-stok><?= e($stokSisaAwal) ?></span> porsi</strong>
                </div>
            </div>
        </div>

        <div class="card card--pad mt-lg">
            <p class="card__title">Catatan Stok</p>
            <p class="form-hint">
                Stok lama (<strong><?= e($oldJumlah) ?> porsi</strong>) dikembalikan terlebih dahulu,
                lalu stok baru dipotong sesuai jumlah pada form ini. Keduanya berjalan dalam satu transaksi database.
            </p>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
