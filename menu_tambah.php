<?php
/**
 * B L E N D — Tambah Menu (Create)
 * ---------------------------------------------------------
 * Validasi server-side + Prepared Statement INSERT.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

/* ---------------------------------------------------------
   Nilai awal form
   --------------------------------------------------------- */

$nama_menu = '';
$kategori  = KATEGORI_MENU[0];
$deskripsi = '';
$stok      = '0';
$harga     = '0';
$errors    = [];

/* ---------------------------------------------------------
   Proses submit
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

    $namaGambar = getDefaultImageByKategori($kategori);

    if ($errors === []) {
        $pesanUpload = null;
        $hasilUpload = uploadGambarMenu($_FILES['gambar'] ?? [], $pesanUpload);

        if ($pesanUpload !== null) {
            $errors['gambar'] = $pesanUpload;
        } elseif (is_string($hasilUpload) && $hasilUpload !== '') {
            $namaGambar = $hasilUpload;
        }
    }

    if ($errors === []) {
        $sql  = 'INSERT INTO menu (nama_menu, kategori, deskripsi, stok, harga, gambar) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($koneksi, $sql);

        if ($stmt === false) {
            $errors['umum'] = 'Gagal menyiapkan penyimpanan data menu.';
        } else {
            $nilaiStok  = (int) $stok;
            $nilaiHarga = (int) $harga;

            mysqli_stmt_bind_param(
                $stmt,
                'sssiis',
                $nama_menu,
                $kategori,
                $deskripsi,
                $nilaiStok,
                $nilaiHarga,
                $namaGambar
            );

            $tersimpan = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($tersimpan) {
                setFlashMessage('success', 'Menu "' . $nama_menu . '" berhasil ditambahkan ke katalog.');
                header('Location: menu.php');
                exit;
            }

            $errors['umum'] = 'Menu gagal disimpan. Silakan periksa kembali data Anda.';
        }
    }
}

/* ---------------------------------------------------------
   Render
   --------------------------------------------------------- */

$halamanAktif     = 'menu';
$judulHalaman     = 'Tambah Menu';
$eyebrowHalaman   = 'B L E N D · Master Data';
$deskripsiHalaman = 'Lengkapi detail menu baru untuk katalog Coffee Shop B L E N D.';

// Pratinjau tetap aman walau kategori yang dikirim tidak valid.
$kategoriPratinjau = in_array($kategori, KATEGORI_MENU, true) ? $kategori : KATEGORI_MENU[0];
$urlPratinjau      = getMenuImageUrl('', $kategoriPratinjau);

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-layout">
    <div class="form-card">
        <div class="form-card__head">
            <span class="form-card__icon"><i data-lucide="coffee" aria-hidden="true"></i></span>
            <div>
                <h2 class="form-card__title">Detail Menu Baru</h2>
                <p class="form-card__desc">Kolom bertanda <span class="required">*</span> wajib diisi.</p>
            </div>
        </div>

        <?php if (isset($errors['umum'])): ?>
            <div class="alert alert--error" role="alert">
                <i class="alert__icon" data-lucide="alert-circle" aria-hidden="true"></i>
                <p class="alert__message"><?= e($errors['umum']) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="menu_tambah.php" enctype="multipart/form-data" novalidate>
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
                                src="<?= e($urlPratinjau) ?>"
                                alt="Pratinjau gambar menu"
                                data-upload-preview
                                data-default-src="<?= e($urlPratinjau) ?>"
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
                                Opsional &mdash; jika dikosongkan, sistem otomatis memakai foto cup sesuai kategori.
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
                    Simpan Menu
                </button>
                <a class="btn btn--ghost" href="menu.php">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                    Batal
                </a>
                <span class="form-actions__spacer"></span>
                <span class="form-actions__note">
                    <i data-lucide="info" aria-hidden="true"></i>
                    Data masuk langsung ke katalog.
                </span>
            </div>
        </form>
    </div>

    <aside class="form-aside">
        <div class="summary-card">
            <p class="summary-card__label">Panduan Cepat</p>
            <p class="summary-card__value">Menu</p>
            <div class="stack-sm mt-lg">
                <div class="summary-card__row">
                    <span>Nama menu</span>
                    <strong>Maks. 100 karakter</strong>
                </div>
                <div class="summary-card__row">
                    <span>Kategori</span>
                    <strong>4 kategori aktif</strong>
                </div>
                <div class="summary-card__row">
                    <span>Gambar</span>
                    <strong>Maks. 2 MB</strong>
                </div>
                <div class="summary-card__row">
                    <span>Status stok</span>
                    <strong>Otomatis</strong>
                </div>
            </div>
        </div>

        <div class="card card--pad mt-lg">
            <p class="card__title">Tips Foto Menu</p>
            <ul class="tip-list">
                <li><i data-lucide="sun" aria-hidden="true"></i> Gunakan pencahayaan alami dan latar polos.</li>
                <li><i data-lucide="square" aria-hidden="true"></i> Rasio 1:1 menghasilkan kartu paling rapi.</li>
                <li><i data-lucide="sparkles" aria-hidden="true"></i> Fokus pada tekstur produk, bukan properti.</li>
            </ul>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
