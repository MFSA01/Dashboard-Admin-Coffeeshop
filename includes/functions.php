<?php
/**
 * B L E N D — Helper Global
 * ---------------------------------------------------------
 * PHP 8.3 native · procedural mysqli
 * Berisi array konstanta, fungsi keamanan output, format,
 * validasi stok, radio renderer, upload gambar & flash message.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   1. ARRAY KONSTANTA
   ========================================================= */

const KATEGORI_MENU      = ['Coffee', 'Ice Blended', 'Tea', 'Non Coffee'];
const METODE_PEMBAYARAN  = ['Tunai', 'QRIS', 'Transfer Bank'];
const ALLOWED_IMAGE_EXT  = ['jpg', 'jpeg', 'png', 'webp'];

const MAKS_UKURAN_GAMBAR = 2 * 1024 * 1024; // 2 MB
const DIREKTORI_GAMBAR   = __DIR__ . '/../uploads/menu/';
const URL_GAMBAR         = 'uploads/menu/';
const GAMBAR_DEFAULT     = 'assets/img/default-coffee.svg';

/**
 * Foto cup resmi bawaan per kategori.
 * File ini dipakai bersama oleh seluruh menu dalam kategori yang sama,
 * sehingga TIDAK BOLEH dihapus saat menu diedit atau dihapus.
 */
const KATEGORI_DEFAULT_IMAGE = [
    'Coffee'      => 'biru.jpeg',
    'Ice Blended' => 'kuning.jpeg',
    'Tea'         => 'hijau.jpeg',
    'Non Coffee'  => 'merah.jpeg',
];

/* =========================================================
   2. KEAMANAN OUTPUT
   ========================================================= */

/**
 * Escape output HTML (wajib dipakai di semua echo data dinamis).
 */
function e(mixed $nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   3. FORMAT TAMPILAN
   ========================================================= */

/**
 * Format angka menjadi Rupiah tanpa desimal. Contoh: Rp 32.000
 */
function formatRupiah(int|float|string|null $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/**
 * Format tanggal database (Y-m-d) menjadi gaya editorial ringkas.
 */
function formatTanggalIndonesia(?string $tanggal): string
{
    $timestamp = strtotime((string) $tanggal);

    if ($timestamp === false) {
        return '-';
    }

    $bulan = [
        1  => 'Jan',
        2  => 'Feb',
        3  => 'Mar',
        4  => 'Apr',
        5  => 'Mei',
        6  => 'Jun',
        7  => 'Jul',
        8  => 'Agu',
        9  => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    return date('d', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

/* =========================================================
   4. PERHITUNGAN & STATUS STOK
   ========================================================= */

/**
 * Hitung total harga transaksi (harga satuan x jumlah).
 */
function hitungTotalHarga(int|float|string $hargaSatuan, int|float|string $jumlah): int
{
    $harga  = max(0, (int) $hargaSatuan);
    $banyak = max(0, (int) $jumlah);

    return $harga * $banyak;
}

/**
 * Tentukan label & class badge berdasarkan jumlah stok.
 *
 * @return array{label: string, class: string}
 */
function getStatusStok(int $stok): array
{
    if ($stok <= 0) {
        return ['label' => 'Habis', 'class' => 'badge--danger'];
    }

    if ($stok <= 10) {
        return ['label' => 'Menipis', 'class' => 'badge--warning'];
    }

    return ['label' => 'Tersedia', 'class' => 'badge--success'];
}

/* =========================================================
   5. RENDER RADIO BUTTON DARI ARRAY
   ========================================================= */

/**
 * Render sekumpulan radio button dari array (foreach).
 *
 * @param string               $name     Atribut name radio.
 * @param array<int, string>   $options  Daftar opsi.
 * @param string               $selected Nilai yang terpilih.
 * @param string               $idPrefix Prefix id agar unik.
 */
function renderRadioOptions(string $name, array $options, string $selected = '', string $idPrefix = ''): string
{
    $idPrefix = $idPrefix !== '' ? $idPrefix : $name;
    $html     = '';

    foreach ($options as $index => $option) {
        $value    = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
        $label    = is_array($option) ? (string) ($option['label'] ?? $value) : (string) $option;
        $id       = $idPrefix . '-' . $index;
        $checked  = ((string) $selected === $value) ? ' checked' : '';

        $html .= '<label class="radio-option" for="' . e($id) . '">';
        $html .= '<input type="radio" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $checked . '>';
        $html .= '<span class="radio-option__dot" aria-hidden="true"></span>';
        $html .= '<span class="radio-option__label">' . e($label) . '</span>';
        $html .= '</label>';
    }

    return $html;
}

/* =========================================================
   6. NOMOR NOTA OTOMATIS
   ========================================================= */

/**
 * Generate nomor nota format BLD-YYYYMMDD-XXX.
 */
function generateNomorNota(mysqli $koneksi, string $tanggalPembelian): string
{
    $timestamp = strtotime($tanggalPembelian);

    if ($timestamp === false) {
        $timestamp = time();
    }

    $prefix = 'BLD-' . date('Ymd', $timestamp) . '-';
    $sql    = 'SELECT nomor_nota FROM transaksi WHERE nomor_nota LIKE ? ORDER BY nomor_nota DESC LIMIT 1';
    $stmt   = mysqli_prepare($koneksi, $sql);

    if ($stmt === false) {
        return $prefix . '001';
    }

    $like = $prefix . '%';
    mysqli_stmt_bind_param($stmt, 's', $like);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $baris = $hasil !== false ? mysqli_fetch_assoc($hasil) : null;
    mysqli_stmt_close($stmt);

    $urutan = 1;

    if (is_array($baris) && isset($baris['nomor_nota'])) {
        $urutan = (int) substr((string) $baris['nomor_nota'], strlen($prefix)) + 1;
    }

    return $prefix . str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
}

/* =========================================================
   7. UPLOAD GAMBAR MENU
   ========================================================= */

/**
 * Validasi & pindahkan gambar menu ke uploads/menu/.
 *
 * @param array<string, mixed> $file        Elemen dari $_FILES.
 * @param string|null          $pesanError  Pesan validasi (by reference).
 * @return string|null Nama file baru, atau null jika tidak ada file / gagal.
 */
function uploadGambarMenu(array $file, ?string &$pesanError = null): ?string
{
    $pesanError = null;

    // Field tidak dikirim / kosong sama sekali → anggap tidak ada upload.
    if ($file === [] || !isset($file['error'])) {
        return null;
    }

    if (is_array($file['error'])) {
        $pesanError = 'Berkas gambar tidak valid.';
        return null;
    }

    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $pesanError = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar melebihi batas maksimal 2 MB.',
            UPLOAD_ERR_PARTIAL                        => 'Upload gambar tidak selesai, silakan coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Folder sementara upload tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE                     => 'Server gagal menulis berkas gambar.',
            default                                   => 'Gagal mengunggah gambar (kode ' . (int) $file['error'] . ').',
        };
        return null;
    }

    $ukuran = (int) ($file['size'] ?? 0);

    if ($ukuran <= 0) {
        $pesanError = 'Berkas gambar kosong atau tidak terbaca.';
        return null;
    }

    if ($ukuran > MAKS_UKURAN_GAMBAR) {
        $pesanError = 'Ukuran gambar maksimal 2 MB.';
        return null;
    }

    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        $pesanError = 'Berkas upload tidak valid.';
        return null;
    }

    $ekstensi = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if (!in_array($ekstensi, ALLOWED_IMAGE_EXT, true)) {
        $pesanError = 'Format gambar harus ' . implode(', ', ALLOWED_IMAGE_EXT) . '.';
        return null;
    }

    $infoGambar = @getimagesize((string) $file['tmp_name']);

    if ($infoGambar === false) {
        $pesanError = 'Berkas yang diunggah bukan gambar yang valid.';
        return null;
    }

    if (!is_dir(DIREKTORI_GAMBAR) && !mkdir(DIREKTORI_GAMBAR, 0775, true) && !is_dir(DIREKTORI_GAMBAR)) {
        $pesanError = 'Folder upload gambar tidak dapat dibuat.';
        return null;
    }

    try {
        $acak = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $acak = (string) mt_rand(1000, 9999);
    }

    $namaBaru = 'menu-' . date('YmdHis') . '-' . $acak . '.' . $ekstensi;
    $tujuan   = DIREKTORI_GAMBAR . $namaBaru;

    if (!move_uploaded_file((string) $file['tmp_name'], $tujuan)) {
        $pesanError = 'Gambar gagal disimpan ke folder upload.';
        return null;
    }

    return $namaBaru;
}

/**
 * Cek apakah nama file termasuk aset gambar bawaan (tidak boleh dihapus).
 * Meliputi gambar default lama & 4 foto cup resmi per kategori.
 */
function isProtectedMenuImage(?string $filename): bool
{
    $nama = strtolower(basename(trim((string) $filename)));

    if ($nama === '') {
        return false;
    }

    $bawaan = ['default.jpg', 'default-coffee.svg'];

    foreach (KATEGORI_DEFAULT_IMAGE as $gambarKategori) {
        $bawaan[] = strtolower($gambarKategori);
    }

    return in_array($nama, $bawaan, true);
}

/**
 * Nama file foto cup bawaan untuk sebuah kategori.
 */
function getDefaultImageByKategori(string $kategori): string
{
    return KATEGORI_DEFAULT_IMAGE[$kategori] ?? 'default.jpg';
}

/**
 * Hapus file gambar menu dari uploads/menu/ (kecuali gambar bawaan).
 * Aman dipanggil jika file tidak ada.
 */
function hapusGambarMenu(?string $gambar): bool
{
    $nama = basename(trim((string) $gambar));

    if ($nama === '' || isProtectedMenuImage($nama)) {
        return false;
    }

    // Hanya izinkan ekstensi gambar yang dikenal.
    $ekstensi = strtolower(pathinfo($nama, PATHINFO_EXTENSION));

    if (!in_array($ekstensi, ALLOWED_IMAGE_EXT, true)) {
        return false;
    }

    $path = DIREKTORI_GAMBAR . $nama;

    if (!is_file($path)) {
        return false;
    }

    return @unlink($path);
}

/**
 * URL gambar menu dengan urutan prioritas:
 * 1. File fisik ada di uploads/menu/ → pakai file itu.
 * 2. Nama kosong / 'default.jpg' (atau file tidak ditemukan) → foto cup sesuai kategori.
 * 3. Tidak ada semuanya → ilustrasi default B L E N D.
 */
function getMenuImageUrl(?string $gambar, string $kategori = ''): string
{
    $nama = basename(trim((string) $gambar));

    if (
        $nama !== ''
        && strcasecmp($nama, 'default.jpg') !== 0
        && is_file(DIREKTORI_GAMBAR . $nama)
    ) {
        return URL_GAMBAR . rawurlencode($nama);
    }

    $cupKategori = KATEGORI_DEFAULT_IMAGE[$kategori] ?? '';

    if ($cupKategori !== '' && is_file(DIREKTORI_GAMBAR . $cupKategori)) {
        return URL_GAMBAR . rawurlencode($cupKategori);
    }

    return GAMBAR_DEFAULT;
}

/**
 * Cek apakah URL gambar mengarah ke salah satu foto cup resmi bawaan.
 */
function isCupImageUrl(?string $url): bool
{
    $nama = strtolower(basename((string) parse_url((string) $url, PHP_URL_PATH)));

    foreach (KATEGORI_DEFAULT_IMAGE as $gambarKategori) {
        if ($nama === strtolower($gambarKategori)) {
            return true;
        }
    }

    return false;
}

/**
 * Kelas CSS warna dot kategori.
 */
function getKategoriDotClass(string $kategori): string
{
    return match ($kategori) {
        'Coffee'      => 'is-coffee',
        'Ice Blended' => 'is-ice-blended',
        'Tea'         => 'is-tea',
        'Non Coffee'  => 'is-non-coffee',
        default       => '',
    };
}

/* =========================================================
   8. FLASH MESSAGE
   ========================================================= */

/**
 * Simpan pesan sekali tampil ke session.
 */
function setFlashMessage(string $tipe, string $pesan): void
{
    $_SESSION['flash_message'] = [
        'tipe'  => $tipe,
        'pesan' => $pesan,
    ];
}

/**
 * Ambil & hapus pesan flash.
 *
 * @return array{tipe: string, pesan: string}|null
 */
function getFlashMessage(): ?array
{
    if (empty($_SESSION['flash_message']) || !is_array($_SESSION['flash_message'])) {
        return null;
    }

    $flash = [
        'tipe'  => (string) ($_SESSION['flash_message']['tipe'] ?? 'info'),
        'pesan' => (string) ($_SESSION['flash_message']['pesan'] ?? ''),
    ];

    unset($_SESSION['flash_message']);

    return $flash;
}
