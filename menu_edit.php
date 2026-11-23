<?php
/**
 * B L E N D — Edit Menu (Update)
 * ---------------------------------------------------------
 * Gambar baru valid → simpan baru & hapus file lama.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   1. Ambil data menu berdasarkan parameter id
   --------------------------------------------------------- */

$idMenu = (int) ($_GET['id'] ?? 0);

if ($idMenu <= 0) {
    setFlashMessage('error', 'ID menu tidak valid.');
    header('Location: menu.php');
    exit;
}

$sqlAmbil = 'SELECT id_menu, nama_menu, kategori, deskripsi, stok, harga, gambar FROM menu WHERE id_menu = ? LIMIT 1';
$stmtAmbil = mysqli_prepare($koneksi, $sqlAmbil);

if ($stmtAmbil === false) {
    setFlashMessage('error', 'Terjadi kesalahan saat mengambil data menu.');
    header('Location: menu.php');
    exit;
}

mysqli_stmt_bind_param($stmtAmbil, 'i', $idMenu);
mysqli_stmt_execute($stmtAmbil);
$hasilAmbil = mysqli_stmt_get_result($stmtAmbil);
$menu       = $hasilAmbil !== false ? mysqli_fetch_assoc($hasilAmbil) : null;
mysqli_stmt_close($stmtAmbil);

if (!is_array($menu)) {
    setFlashMessage('error', 'Menu yang Anda cari tidak ditemukan.');
    header('Location: menu.php');
    exit;
}

/* ---------------------------------------------------------
   2. Nilai awal form dari database
   --------------------------------------------------------- */

$nama_menu = (string) $menu['nama_menu'];
$kategori  = (string) $menu['kategori'];
$deskripsi = (string) $menu['deskripsi'];
$stok      = (string) $menu['stok'];
$harga     = (string) $menu['harga'];
$gambarLama = (string) $menu['gambar'];
$errors    = [];

/* ---------------------------------------------------------
   3. Proses submit
   --------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_menu = trim((string) ($_POST['nama_menu'] ?? ''));
    $kategori  = trim((string) ($_POST['kategori'] ?? ''));
    $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
    $stok      = trim((string) ($_POST['stok'] ?? ''));
    $harga     = trim((string) ($_POST['harga'] ?? ''));

    if ($nama_menu === '') {
        $errors['nama_menu'] = 'Nama menu wajib diisi.';
    } elseif (mb_strlen($nama_menu) > 100) {
        $errors['nama_menu'] = 'Nama menu maksimal 100 karakter.';
    }

    if (!in_array($kategori, KATEGORI_MENU, true)) {
        $errors['kategori'] = 'Pilih kategori menu yang valid.';
    }

    if ($deskripsi === '') {
        $errors['deskripsi'] = 'Deskripsi menu wajib diisi.';
    }

    if ($stok === '' || !ctype_digit($stok)) {
        $errors['stok'] = 'Stok wajib berupa angka bulat 0 atau lebih.';
    } elseif ((int) $stok > 100000) {
        $errors['stok'] = 'Stok tidak boleh melebihi 100.000.';
    }

    if ($harga === '' || !ctype_digit($harga)) {
        $errors['harga'] = 'Harga wajib berupa angka bulat 0 atau lebih.';
    } elseif ((int) $harga > 1000000000) {
        $errors['harga'] = 'Harga tidak boleh melebihi Rp 1.000.000.000.';
    }

    $namaGambar = $gambarLama;
    $gambarBaru = null;

    if ($errors === []) {
        $pesanUpload = null;
        $hasilUpload = uploadGambarMenu($_FILES['gambar'] ?? [], $pesanUpload);

        if ($pesanUpload !== null) {
            $errors['gambar'] = $pesanUpload;
        } elseif (is_string($hasilUpload) && $hasilUpload !== '') {
            $gambarBaru = $hasilUpload;
            $namaGambar = $hasilUpload;
        } else {
            // Tanpa upload baru: jika gambar lama adalah cup bawaan, ikuti kategori baru.
            if (isProtectedMenuImage($gambarLama) && $kategori !== (string) $menu['kategori']) {
                $namaGambar = getDefaultImageByKategori($kategori);
            }
        }
    }

    if ($errors === []) {
        $sql  = 'UPDATE menu SET nama_menu = ?, kategori = ?, deskripsi = ?, stok = ?, harga = ?, gambar = ? WHERE id_menu = ?';
        $stmt = mysqli_prepare($koneksi, $sql);

        if ($stmt === false) {
            $errors['umum'] = 'Gagal menyiapkan pembaruan data menu.';
        } else {
            $nilaiStok  = (int) $stok;
            $nilaiHarga = (int) $harga;

            mysqli_stmt_bind_param(
                $stmt,
                'sssiisi',
                $nama_menu,
                $kategori,
                $deskripsi,
                $nilaiStok,
                $nilaiHarga,
                $namaGambar,
                $idMenu
            );

            $terbarui = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($terbarui) {
                // Hapus gambar lama hanya bila benar-benar diganti & bukan aset bawaan.
                if ($gambarBaru !== null && $gambarBaru !== '' && $gambarLama !== '' && $gambarLama !== $gambarBaru) {
                    hapusGambarMenu($gambarLama);
                }

                setFlashMessage('success', 'Menu "' . $nama_menu . '" berhasil diperbarui.');
                header('Location: menu.php');
                exit;
            }

            // Gagal update: bersihkan file baru yang sudah terlanjur diunggah.
            if ($gambarBaru !== null) {
                hapusGambarMenu($gambarBaru);
            }

            $errors['umum'] = 'Menu gagal diperbarui. Silakan periksa kembali data Anda.';
        }
    }
}

/* ---------------------------------------------------------
   4. Render
   --------------------------------------------------------- */

$urlGambarSaatIni = getMenuImageUrl($gambarLama, $kategori);
$isGambarFallback = $urlGambarSaatIni === GAMBAR_DEFAULT;
$isGambarCup      = isCupImageUrl($urlGambarSaatIni);

$halamanAktif     = 'menu';
$judulHalaman     = 'Edit Menu';
$eyebrowHalaman   = 'B L E N D · Master Data';
$deskripsiHalaman = 'Perbarui detail menu "' . $nama_menu . '" pada katalog B L E N D.';

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-layout">
    <div class="form-card">
        <div class="form-card__head">
            <span class="form-card__icon"><i data-lucide="pencil" aria-hidden="true"></i></span>
            <div>
                <h2 class="form-card__title">Perbarui Detail Menu</h2>
                <p class="form-card__desc">Mengubah data menu #<?= e($idMenu) ?> · kolom <span class="required">*</span> wajib diisi.</p>
            </div>
        </div>

        <?php if (isset($errors['umum'])): ?>
            <div class="alert alert--error" role="alert">
                <i class="alert__icon" data-lucide="alert-circle" aria-hidden="true"></i>
                <p class="alert__message"><?= e($errors['umum']) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="menu_edit.php?id=<?= e($idMenu) ?>" enctype="multipart/form-data" novalidate>
            <div class="form-grid">

                <div class="form-field form-field--full">
                    <label class="form-label" for="nama_menu">Nama Menu <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['nama_menu']) ? 'is-invalid' : '' ?>"
                        type="text"
                        id="nama_menu"
                        name="nama_menu"
                        value="<?= e($nama_menu) ?>"
                        placeholder="Contoh: Signature Oat Latte"
                        maxlength="100"
                        required
                    >
                    <?php if (isset($errors['nama_menu'])): ?>
                        <p class="form-error"><?= e($errors['nama_menu']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <span class="form-label">Kategori <span class="required">*</span></span>
                    <div class="radio-group" role="radiogroup" aria-label="Kategori menu">
                        <?= renderRadioOptions('kategori', KATEGORI_MENU, $kategori, 'kategori') ?>
                    </div>
                    <?php if (isset($errors['kategori'])): ?>
                        <p class="form-error"><?= e($errors['kategori']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <label class="form-label" for="deskripsi">Deskripsi <span class="required">*</span></label>
                    <textarea
                        class="form-textarea <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>"
                        id="deskripsi"
                        name="deskripsi"
                        placeholder="Jelaskan karakter rasa, bahan utama, dan penyajian menu ini."
                        rows="4"
                        required
                    ><?= e($deskripsi) ?></textarea>
                    <?php if (isset($errors['deskripsi'])): ?>
                        <p class="form-error"><?= e($errors['deskripsi']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" for="stok">Stok Tersedia <span class="required">*</span></label>
                    <input
                        class="form-control <?= isset($errors['stok']) ? 'is-invalid' : '' ?>"
                        type="number"
                        id="stok"
                        name="stok"
                        value="<?= e($stok) ?>"
                        min="0"
                        step="1"
                        inputmode="numeric"
                        required
                    >
                    <p class="form-hint">Stok 0 = Habis · &le; 10 = Menipis · &gt; 10 = Tersedia.</p>
                    <?php if (isset($errors['stok'])): ?>
                        <p class="form-error"><?= e($errors['stok']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" for="harga">Harga Satuan <span class="required">*</span></label>
                    <div class="input-prefix">
                        <span class="input-prefix__label">Rp</span>
                        <input
                            class="form-control <?= isset($errors['harga']) ? 'is-invalid' : '' ?>"
                            type="number"
                            id="harga"
                            name="harga"
                            value="<?= e($harga) ?>"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            required
                        >
                    </div>
                    <p class="form-hint">Masukkan angka tanpa titik atau koma.</p>
                    <?php if (isset($errors['harga'])): ?>
                        <p class="form-error"><?= e($errors['harga']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-field form-field--full">
                    <span class="form-label">Gambar Menu</span>
                    <div
                        class="upload-field"
                        data-upload-field
                        data-cup-images="<?= e(json_encode(KATEGORI_DEFAULT_IMAGE)) ?>"
                    >
                        <div class="upload-preview upload-preview--contain">
                            <img
                                src="<?= e($urlGambarSaatIni) ?>"
                                alt="Pratinjau gambar menu <?= e($nama_menu) ?>"
                                data-upload-preview
                                data-default-src="<?= e($urlGambarSaatIni) ?>"
                            >
                        </div>
                        <div class="upload-input-wrap">
                            <input
                                class="upload-input"
                                type="file"
                                id="gambar"
                                name="gambar"
                                accept=".jpg,.jpeg,.png,.webp"
                                data-upload-input
                            >
                            <p class="form-hint">Format JPG, JPEG, PNG, atau WEBP · maksimal 2 MB.</p>
                            <p class="form-hint" data-upload-filename>Belum ada file dipilih.</p>
                            <p class="form-hint">
                                Kosongkan untuk mempertahankan gambar saat ini &mdash; foto cup otomatis
                                mengikuti kategori yang Anda pilih.
                            </p>
                            <?php if (isset($errors['gambar'])): ?>
                                <p class="form-error"><?= e($errors['gambar']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <button class="btn" type="submit">
                    <i data-lucide="save" aria-hidden="true"></i>
                    Simpan Perubahan
                </button>
                <a class="btn btn--ghost" href="menu.php">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                    Batal
                </a>
                <span class="form-actions__spacer"></span>
                <button
                    class="btn btn--danger btn--sm"
                    type="submit"
                    form="form-hapus-menu"
                    data-confirm-delete="Yakin ingin menghapus menu &quot;<?= e($nama_menu) ?>&quot;? Tindakan ini tidak dapat dibatalkan."
                >
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                    Hapus Menu
                </button>
            </div>
        </form>

        <form id="form-hapus-menu" method="post" action="menu_hapus.php" hidden>
            <input type="hidden" name="id_menu" value="<?= e($idMenu) ?>">
        </form>
    </div>

    <aside class="form-aside">
        <div class="summary-card">
            <p class="summary-card__label">Ringkasan Menu</p>
            <p class="summary-card__value"><?= e(formatRupiah((int) $harga)) ?></p>
            <div class="stack-sm mt-lg">
                <div class="summary-card__row">
                    <span>Kategori</span>
                    <strong><?= e($kategori) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>Stok</span>
                    <strong><?= e($stok) ?> porsi</strong>
                </div>
                <div class="summary-card__row">
                    <span>Status</span>
                    <strong><?= e(getStatusStok((int) $stok)['label']) ?></strong>
                </div>
                <div class="summary-card__row">
                    <span>ID Menu</span>
                    <strong>#<?= e($idMenu) ?></strong>
                </div>
            </div>
        </div>

        <div class="card card--pad mt-lg">
            <p class="card__title">Gambar Saat Ini</p>
            <img
                class="aside-thumb<?= $isGambarCup ? ' aside-thumb--cup' : '' ?>"
                src="<?= e($urlGambarSaatIni) ?>"
                alt="Gambar menu saat ini: <?= e($nama_menu) ?>"
            >
            <p class="form-hint mt-sm">
                <?= $isGambarFallback ? 'Masih memakai ilustrasi default B L E N D.' : 'Gambar akan diganti jika Anda mengunggah berkas baru.' ?>
            </p>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
