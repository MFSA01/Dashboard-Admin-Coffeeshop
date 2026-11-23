# Product Requirements Document (PRD)

## B L E N D — Admin Dashboard & Sistem Transaksi Coffee Shop

### 1. Ringkasan Proyek

Membangun aplikasi web dinamis untuk operasional internal Coffee Shop **B L E N D**. Aplikasi berfokus pada dashboard admin untuk mengelola katalog menu (minuman dan makanan) serta pencatatan transaksi pembelian oleh kasir, dengan tampilan editorial modern berbasis palet **Biru Tua & Oat**.

### 2. Tech Stack & Aturan Teknis (WAJIB DIPATUHI)

- **Backend:** PHP 8.3 Native (Dilarang menggunakan framework PHP seperti Laravel/CodeIgniter).
- **Database:** MySQL / MariaDB (Laragon) — nama database: `blend_3487` (`host: localhost`, `user: root`, `password: ""`). File skema & seed data sudah tersedia di `database/blend_3487.sql` dan sudah diimpor ke phpMyAdmin.
- **Driver Database:** WAJIB menggunakan **Procedural `mysqli`** (`mysqli_connect`, `mysqli_prepare`, `mysqli_stmt_bind_param`, `mysqli_stmt_execute`, `mysqli_stmt_get_result`, dll.). Dilarang memakai PDO atau gaya OOP.
- **Keamanan:**
  - Semua query yang melibatkan input/parameter wajib memakai **Prepared Statements**.
  - Semua output ke HTML wajib melewati helper `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
  - Semua halaman dashboard dan CRUD wajib diproteksi `$_SESSION` melalui `includes/auth_check.php` (redirect ke `login.php` jika belum login).
  - Verifikasi login wajib menggunakan `password_verify()` (password `admin` di tabel `users` sudah disimpan dalam hash bcrypt).
- **Styling:** **100% Vanilla CSS** (`assets/css/style.css`). Dilarang memakai Bootstrap, Tailwind, atau framework CSS lainnya.
- **Library Pendukung via CDN:**
  - Font: Google Fonts `Anton` (display editorial uppercase) & `Plus Jakarta Sans` (400, 500, 600, 700 untuk UI/body).
  - Icon: Lucide Icons (`https://unpkg.com/lucide@latest`).
- **JavaScript:** Vanilla JS secukupnya di `assets/js/main.js` (init Lucide, auto-close alert, konfirmasi hapus, preview upload gambar, dan live hitung total harga transaksi).

### 3. Role Pengguna

- **Single Role: Admin**
  - Kredensial default di database `blend_3487`: `username = admin`, `password = admin`.

### 4. Skema Database (`blend_3487`)

Mengacu penuh pada file `database/blend_3487.sql`:

1. **`users`**: `id_user` (PK), `username` (UNIQUE), `password` (bcrypt), `nama_lengkap`, `created_at`.
2. **`menu`**: `id_menu` (PK), `nama_menu`, `kategori` (`ENUM('Coffee','Ice Blended','Tea','Non Coffee')`), `deskripsi` (`TEXT`), `stok` (`INT`), `harga` (`INT`), `gambar` (`VARCHAR(255)` default `'default.jpg'`), `created_at`.
   - **Foto cup bawaan per kategori** di `uploads/menu/`: `Coffee` → `biru.jpeg`, `Ice Blended` → `kuning.jpeg`, `Tea` → `hijau.jpeg`, `Non Coffee` → `merah.jpeg`. File ini dipakai bersama oleh semua menu dalam kategori yang sama sehingga **tidak boleh dihapus** saat edit/hapus menu.
3. **`transaksi`**: `id_transaksi` (PK), `nomor_nota` (UNIQUE), `tanggal_pembelian` (`DATE`), `nama_kasir`, `id_menu` (FK -> `menu.id_menu` `ON UPDATE CASCADE ON DELETE RESTRICT`), `jumlah`, `harga_satuan`, `total_harga`, `metode_pembayaran` (`ENUM('Tunai','QRIS','Transfer Bank')`), `created_at`.

### 5. Spesifikasi Fitur & Implementasi Wajib Konsep PHP

#### a. Konsep PHP Wajib (`includes/functions.php` & halaman form/CRUD)

1. **Array:** `KATEGORI_MENU` (`['Coffee', 'Ice Blended', 'Tea', 'Non Coffee']`), `KATEGORI_DEFAULT_IMAGE` (peta kategori → nama file foto cup bawaan), `METODE_PEMBAYARAN` (`['Tunai', 'QRIS', 'Transfer Bank']`), `ALLOWED_IMAGE_EXT` (`['jpg', 'jpeg', 'png', 'webp']`).
2. **Perulangan (`foreach` & `while`):** Merender opsi radio button dari array dan menampilkan daftar data MySQL.
3. **Percabangan (`if/elseif/else`):** Validasi file upload, cek ketersediaan stok, dan penentuan status badge stok (`Habis` = 0, `Menipis` <= 10, `Tersedia` > 10).
4. **Function:** `e()`, `formatRupiah()`, `hitungTotalHarga()`, `getStatusStok()`, `renderRadioOptions()`, `generateNomorNota()` (format `BLD-YYYYMMDD-XXX`), `uploadGambarMenu()` (maks 2MB ke `uploads/menu/`), `isProtectedMenuImage()` (mencegah `unlink()` pada 4 foto cup bawaan), `hapusGambarMenu()`, `getMenuImageUrl($gambar, $kategori)` (file fisik → foto cup kategori → fallback `assets/img/default-coffee.svg`), `setFlashMessage()`, `getFlashMessage()`.

#### b. Cakupan Modul

- **Autentikasi (`login.php`, `logout.php`):** Form login username & password, redirect ke `index.php` saat sukses, serta logout dengan `session_unset()` + `session_destroy()` kembali ke `login.php`.
- **Dashboard (`index.php`):** 3 KPI Card (Total Menu, Total Transaksi, Total Pendapatan), 4 kartu menu perwakilan (1 menu terbaru per kategori) memakai `.menu-card` dengan frame navy & dot warna kategori, dan tabel 5 transaksi terakhir hasil `INNER JOIN` `transaksi` & `menu`.
- **CRUD Menu (`menu.php`, `menu_tambah.php`, `menu_edit.php`, `menu_hapus.php`):**
  - Input wajib: `input text` (nama menu, stok, harga), `radio button` (kategori), `textarea` (deskripsi), `file upload` (gambar — opsional).
  - Filter kategori (`Semua | Coffee | Ice Blended | Tea | Non Coffee`) dirender dinamis dari `KATEGORI_MENU` dan pencarian cepat.
  - Upload gambar **opsional**: jika dikosongkan, `gambar` otomatis diisi foto cup sesuai kategori via `KATEGORI_DEFAULT_IMAGE`.
  - Saat kategori diubah tanpa upload baru dan gambar lama berupa cup bawaan, `gambar` ikut disinkronkan ke cup kategori baru.
  - Hapus/edit hanya menghapus file gambar lama bila bukan aset bawaan (`isProtectedMenuImage()` melindungi `default.jpg` + 4 foto cup), serta menangani error Foreign Key secara ramah jika menu sudah pernah dipakai transaksi.
  - Kartu produk: frame navy dengan panggung foto `#F7F7F7`, `object-fit: contain`, padding proporsional, dan badge kategori berdot warna (Coffee `#1D4ED8`, Ice Blended `#EAB308`, Tea `#15803D`, Non Coffee `#DC2626`).
- **CRUD Transaksi (`transaksi.php`, `transaksi_tambah.php`, `transaksi_edit.php`, `transaksi_hapus.php`):**
  - Input wajib: `input text` (nomor nota, nama kasir), `input date` (tanggal pembelian), `select dropdown` produk dari tabel `menu`, `input number` (jumlah), tampilan `harga_satuan` & `total_harga` (hitung ulang di server PHP), `radio button` (metode pembayaran).
  - Sinkronisasi stok otomatis: simpan transaksi -> validasi `jumlah <= stok` & kurangi stok; edit transaksi -> kembalikan stok lama lalu potong stok baru; hapus transaksi -> kembalikan stok ke tabel `menu`.

### 6. Panduan Desain Visual & Referensi Gambar (`image_8e57b8.jpg`)

Gunakan gambar referensi `image_8e57b8.jpg` sebagai patokan struktur layout, proporsi ruang kosong, gaya kartu dengan `border-radius`, tipografi editorial uppercase bertumpuk, dan kartu produk dengan frame blok gelap di sekeliling foto. **JANGAN gunakan palet cokelat pada referensi**; konversi ke palet **Biru Tua & Oat** berikut:

```css
:root {
  --navy-primary: #14213d; /* Navbar active pill, tombol utama, KPI 1 & 3, frame foto menu, footer */
  --navy-hover: #1e325c; /* Hover tombol & link aktif */
  --oat-bg: #f4efe6; /* Background utama halaman */
  --oat-accent: #e2d5be; /* KPI 2 (tengah), badge kategori, tombol sekunder */
  --oat-border: #d5c8b0; /* Border tipis kartu, tabel, input */
  --surface-white: #fffdf9; /* Card form, card produk, kontainer tabel */
  --text-main: #0f172a; /* Judul & teks utama */
  --text-muted: #5a6475; /* Deskripsi & metadata */
  --status-success: #2d6a4f; /* Badge stok tersedia / alert sukses */
  --status-warning: #b45309; /* Badge stok menipis */
  --status-danger: #b91c1c; /* Tombol hapus / alert error */
}
```
