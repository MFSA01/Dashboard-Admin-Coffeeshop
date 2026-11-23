# B L E N D

**Admin Dashboard & Sistem Transaksi Coffee Shop**

Aplikasi web internal untuk mengelola katalog menu dan mencatat transaksi penjualan Coffee Shop **B L E N D**. Dibangun dengan tampilan editorial modern bernuansa **navy & oat**, aplikasi ini memusatkan seluruh operasional harian kasir dan admin dalam satu dashboard.

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![CSS](https://img.shields.io/badge/CSS-Vanilla-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-Internal%20Use%20Only-b91c1c)

---

## Daftar Isi

- [Tentang Aplikasi](#tentang-aplikasi)
- [Fitur Utama](#fitur-utama)
- [Tech Stack](#tech-stack)
- [Struktur Proyek](#struktur-proyek)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Akun Default](#akun-default)
- [Cara Penggunaan](#cara-penggunaan)
- [Struktur Database](#struktur-database)
- [Keamanan & Aturan Teknis](#keamanan--aturan-teknis)
- [Troubleshooting](#troubleshooting)
- [Lisensi](#lisensi)

---

## Tentang Aplikasi

**B L E N D** adalah aplikasi administrasi internal yang dirancang untuk menggantikan pencatatan manual katalog menu dan transaksi coffee shop. Aplikasi ini **bukan** storefront atau e-commerce — tidak ada halaman pembelian untuk pelanggan. Seluruh fitur berfokus pada kebutuhan operasional staf di balik meja kasir.

### Tujuan

- Menyediakan satu sumber data terpusat untuk menu, stok, dan riwayat penjualan.
- Mempercepat proses pencatatan transaksi sekaligus menjaga konsistensi stok.
- Memberikan ringkasan performa penjualan melalui dashboard yang mudah dibaca.

### Fungsi Utama

| Fungsi | Deskripsi |
| --- | --- |
| **Autentikasi Admin** | Login aman dengan verifikasi hash password dan proteksi session pada seluruh halaman internal. |
| **Dashboard** | Ringkasan KPI (total menu, transaksi, pendapatan, stok) serta preview katalog dan transaksi terbaru. |
| **Kelola Menu** | Tambah, lihat, ubah, dan hapus menu beserta kategori, harga, stok, deskripsi, dan gambar. |
| **Kelola Transaksi** | Catat transaksi kasir dengan nomor nota otomatis, perhitungan total di server, dan sinkronisasi stok. |

---

## Fitur Utama

### 🔐 Autentikasi & Session

- Login menggunakan `password_verify()` terhadap hash bcrypt di tabel `users`.
- `session_regenerate_id(true)` setelah login berhasil untuk mencegah session fixation.
- Seluruh halaman dashboard dan CRUD diproteksi melalui `includes/auth_check.php`.
- Logout menghapus session secara menyeluruh.

### 📊 Dashboard

- KPI total menu, total transaksi, total pendapatan, dan total stok.
- Indikator jumlah menu yang stoknya habis.
- Katalog perwakilan: **satu menu terbaru per kategori** (Coffee, Ice Blended, Tea, Non Coffee).
- Daftar **5 transaksi terakhir** lengkap dengan nama menu dan kategori.

### ☕ Manajemen Menu

- CRUD menu lengkap: nama, kategori, deskripsi, stok, harga, dan gambar.
- Filter kategori dinamis (tab dihasilkan langsung dari konstanta `KATEGORI_MENU`) beserta jumlah item per kategori.
- Pencarian menu berdasarkan nama.
- Tampilan kartu dengan bingkai navy dan panggung foto terang agar siluet cup terlihat utuh.
- Badge kategori dengan penanda warna:

| Kategori | Warna Dot |
| --- | --- |
| Coffee | `#1D4ED8` |
| Ice Blended | `#EAB308` |
| Tea | `#15803D` |
| Non Coffee | `#DC2626` |

### 🖼️ Foto Cup per Kategori

- Empat foto cup resmi menjadi gambar default tiap kategori:

| Kategori | File Default |
| --- | --- |
| Coffee | `biru.jpeg` |
| Ice Blended | `kuning.jpeg` |
| Tea | `hijau.jpeg` |
| Non Coffee | `merah.jpeg` |

- Saat menambah menu **tanpa upload**, sistem otomatis memakai foto cup sesuai kategori.
- Saat kategori diubah dan menu masih memakai foto cup bersama (tanpa upload baru), gambar otomatis diganti ke cup kategori baru.
- Keempat foto cup bersama **dilindungi** (`isProtectedMenuImage()`) dan tidak pernah dihapus saat menu dihapus atau diganti — termasuk `default.jpg`.
- Upload manual tetap didukung (JPG, JPEG, PNG, WEBP, maksimal 2 MB) dan tidak akan ditimpa saat kategori berubah.

### 🧾 Manajemen Transaksi

- CRUD transaksi: nomor nota, tanggal pembelian, nama kasir, menu, jumlah, harga satuan, total, dan metode pembayaran.
- Nomor nota dibuat otomatis dengan format **`BLD-YYYYMMDD-XXX`** dan dijamin unik.
- **Perhitungan total dilakukan ulang di server**; nilai `harga_satuan` dan `total_harga` dari client diabaikan.
- Stok menu bertambah/berkurang otomatis di dalam **database transaction** dengan `SELECT ... FOR UPDATE`, sehingga tidak terjadi race condition.
- Metode pembayaran: **Tunai**, **QRIS**, **Transfer Bank**.
- Filter berdasarkan metode pembayaran + pencarian (nomor nota, nama kasir, nama menu).
- Ringkasan hasil filter (jumlah transaksi & total nominal) dihitung dari data yang tampil.

### 🎨 Antarmuka

- 100% **Vanilla CSS** tanpa framework (tanpa Bootstrap/Tailwind).
- Palet **navy & oat** dengan tipografi editorial (Anton + Plus Jakarta Sans).
- Ikon Lucide, animasi reveal saat halaman dimuat, dan transisi halus.
- Responsif: tabel berubah menjadi kartu pada layar kecil, tab filter tetap terbaca.
- Konfirmasi sebelum menghapus data, preview gambar sebelum upload, dan kalkulasi total transaksi secara langsung.

---

## Tech Stack

| Layer | Teknologi |
| --- | --- |
| **Bahasa** | PHP 8.3 (native, procedural — tanpa framework) |
| **Database** | MySQL / MariaDB (Laragon), database `blend_3487` |
| **Driver DB** | **Procedural `mysqli`** + Prepared Statements |
| **Frontend** | HTML5, 100% Vanilla CSS, Vanilla JavaScript |
| **Font** | Google Fonts — *Anton* & *Plus Jakarta Sans* |
| **Ikon** | [Lucide](https://unpkg.com/lucide@latest) via CDN |
| **Web Server** | Laragon (Apache/Nginx) + PHP 8.3 |
| **Tools** | phpMyAdmin, Git |

> **Catatan:** proyek ini sengaja tidak menggunakan Composer, autoloader, PDO, maupun gaya OOP. Seluruh akses database ditulis dengan fungsi prosedural `mysqli_*`.

---

## Struktur Proyek

```text
blend/
├── assets/
│   ├── css/
│   │   └── style.css            # Seluruh styling (Vanilla CSS)
│   ├── img/
│   │   └── default-coffee.svg   # Fallback gambar menu
│   └── js/
│       └── main.js              # Ikon, alert, konfirmasi, preview, kalkulasi
├── config/
│   └── koneksi.php              # Koneksi mysqli (singleton per request)
├── database/
│   └── blend_3487.sql           # Skema + seed data
├── includes/
│   ├── auth_check.php           # Proteksi halaman (redirect ke login)
│   ├── functions.php            # Helper, konstanta, upload, gambar
│   ├── header.php               # Layout atas + navigasi
│   └── footer.php               # Layout bawah
├── uploads/
│   └── menu/                    # Gambar upload + 4 foto cup resmi
├── index.php                    # Dashboard
├── login.php                    # Halaman login
├── logout.php                   # Proses logout
├── menu.php                     # Daftar menu (filter + pencarian)
├── menu_tambah.php              # Form tambah menu
├── menu_edit.php                # Form ubah menu
├── menu_hapus.php               # Proses hapus menu
├── transaksi.php                # Riwayat transaksi (filter + pencarian)
├── transaksi_tambah.php         # Form tambah transaksi
├── transaksi_edit.php           # Form ubah transaksi
├── transaksi_hapus.php          # Proses hapus transaksi
├── PRD.md                       # Product Requirements Document
└── README.md
```

---

## Persyaratan Sistem

| Kebutuhan | Versi / Keterangan |
| --- | --- |
| PHP | **8.3 atau lebih baru** |
| Ekstensi PHP | `mysqli`, `mbstring`, `session` |
| Database | MySQL 5.7+ / MariaDB 10.4+ |
| Web Server | Apache atau Nginx (disarankan Laragon) |
| Browser | Chrome, Edge, Firefox, atau Safari versi modern |
| Disk | Ruang cukup untuk gambar menu di `uploads/menu/` |

Konfigurasi PHP yang disarankan:

```ini
upload_max_filesize = 4M
post_max_size       = 8M
```

> `getimagesize()` adalah fungsi bawaan PHP, sehingga ekstensi GD **tidak wajib** diaktifkan.

---

## Instalasi

### 1. Clone repositori

```bash
git clone https://github.com/MFSA01/Dashboard-Admin-Coffeeshop.git blend
```

Letakkan folder proyek di direktori web server, misalnya `D:\laragon\www\blend`.

### 2. Jalankan Laragon

Aktifkan **Apache** (atau Nginx) dan **MySQL/MariaDB** dari panel Laragon.

### 3. Impor database

**Via phpMyAdmin**

1. Buka `http://localhost/phpmyadmin`.
2. Buat database baru bernama `blend_3487` (jika belum ada).
3. Pilih database tersebut → tab **Import** → pilih `database/blend_3487.sql` → **Go**.

**Via terminal (opsional)**

```bash
mysql -u root blend_3487 < database/blend_3487.sql
```

### 4. Sesuaikan konfigurasi koneksi

Buka `config/koneksi.php` dan sesuaikan bila lingkungan Anda berbeda:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'blend_3487');
```

### 5. Pastikan folder upload dapat ditulis

Folder `uploads/menu/` harus memiliki izin tulis oleh web server. Folder ini sudah berisi `.gitkeep` dan empat foto cup resmi.

### 6. Akses aplikasi

- Virtual host Laragon: `http://blend.test`
- Atau: `http://localhost/blend`

Anda akan diarahkan ke halaman login.

> ⚠️ Aplikasi **harus** dijalankan melalui web server (HTTP). Membuka file lewat `file://` tidak akan menjalankan PHP.

---

## Akun Default

| Field | Nilai |
| --- | --- |
| Username | `admin` |
| Password | `admin` |

> 🔒 **Peringatan keamanan:** kredensial di atas hanya untuk pengembangan lokal. Ganti password sebelum aplikasi dipakai di lingkungan nyata, dan jangan pernah menggunakan akun default di produksi.

---

## Cara Penggunaan

1. **Login** menggunakan akun admin.
2. **Dashboard** menampilkan ringkasan KPI, katalog perwakilan, dan transaksi terbaru.
3. **Menu → Tambah Menu** untuk menambahkan item baru. Biarkan kolom gambar kosong agar sistem memakai foto cup sesuai kategori, atau unggah gambar sendiri.
4. **Menu** memungkinkan filter kategori dan pencarian nama menu.
5. **Transaksi → Catat Transaksi** untuk mencatat penjualan. Pilih menu, isi jumlah, lalu sistem menghitung total dan nomor nota secara otomatis.
6. **Stok** berkurang otomatis saat transaksi dibuat, dan dikembalikan/disesuaikan saat transaksi diubah atau dihapus.
7. **Transaksi** menyediakan filter metode pembayaran dan pencarian riwayat.
8. **Logout** melalui ikon keluar di pojok kanan atas.

---

## Struktur Database

Database: `blend_3487` — engine InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.

### `users`

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id_user` | INT (PK, AI) | ID pengguna |
| `username` | VARCHAR(50) | Unik |
| `password` | VARCHAR(255) | Hash bcrypt |
| `nama_lengkap` | VARCHAR(100) | Nama tampilan |
| `created_at` | TIMESTAMP | Waktu dibuat |

### `menu`

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id_menu` | INT (PK, AI) | ID menu |
| `nama_menu` | VARCHAR(100) | Nama menu |
| `kategori` | ENUM | `Coffee`, `Ice Blended`, `Tea`, `Non Coffee` |
| `deskripsi` | TEXT | Deskripsi menu |
| `stok` | INT | Jumlah stok tersedia |
| `harga` | INT | Harga satuan (Rupiah) |
| `gambar` | VARCHAR(255) | Nama file di `uploads/menu/` |
| `created_at` | TIMESTAMP | Waktu dibuat |

### `transaksi`

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| `id_transaksi` | INT (PK, AI) | ID transaksi |
| `nomor_nota` | VARCHAR(30) | Unik, format `BLD-YYYYMMDD-XXX` |
| `tanggal_pembelian` | DATE | Tanggal transaksi |
| `nama_kasir` | VARCHAR(100) | Nama kasir |
| `id_menu` | INT (FK) | Relasi ke `menu.id_menu` |
| `jumlah` | INT | Kuantitas pembelian |
| `harga_satuan` | INT | Harga saat transaksi |
| `total_harga` | INT | `harga_satuan × jumlah` (dihitung server) |
| `metode_pembayaran` | ENUM | `Tunai`, `QRIS`, `Transfer Bank` |
| `created_at` | TIMESTAMP | Waktu dibuat |

**Relasi:** `transaksi.id_menu` → `menu.id_menu` (foreign key, `ON UPDATE CASCADE`, `ON DELETE RESTRICT`).

---

## Keamanan & Aturan Teknis

Proyek ini menerapkan aturan berikut secara konsisten:

- **Prepared Statements** untuk seluruh query yang melibatkan input pengguna (termasuk query dinamis filter/pencarian).
- **Escaping output** melalui helper `e()` (`htmlspecialchars` dengan `ENT_QUOTES` dan `UTF-8`) pada semua data yang dicetak ke HTML.
- **Proteksi halaman** melalui `includes/auth_check.php` pada seluruh halaman dashboard dan CRUD.
- **Hash password** bcrypt + `password_verify()`; tidak ada password plaintext di database.
- **Perhitungan ulang di server** untuk harga dan total transaksi; input dari client tidak dipercaya.
- **Transaksi database** (`mysqli_begin_transaction`, `commit`, `rollback`) dengan row lock `SELECT ... FOR UPDATE` saat mengubah stok.
- **Validasi upload**: ekstensi (jpg/jpeg/png/webp), ukuran maksimal 2 MB, dan verifikasi bahwa file benar-benar gambar melalui `getimagesize()`.
- **Perlindungan aset bersama**: `isProtectedMenuImage()` mencegah penghapusan `default.jpg` dan empat foto cup resmi.

---

## Troubleshooting

| Masalah | Kemungkinan Penyebab | Solusi |
| --- | --- | --- |
| "Koneksi database gagal" | MySQL belum berjalan / kredensial salah | Jalankan MySQL di Laragon; cek `config/koneksi.php` |
| Halaman putih / blank | Error PHP tidak ditampilkan | Aktifkan `display_errors` di `php.ini` dan periksa log PHP |
| Upload gambar gagal | Folder tidak writable atau file terlalu besar | Cek izin `uploads/menu/` dan naikkan `upload_max_filesize` |
| Gambar tidak muncul | Nama file tidak ada di `uploads/menu/` | Pastikan file terunggah; sistem akan memakai cup kategori atau SVG fallback |
| Login selalu gagal | Database belum diimpor / password diubah | Impor ulang `database/blend_3487.sql`; gunakan `admin` / `admin` |
| 404 saat membuka proyek | Diakses lewat `file://` atau path salah | Gunakan `http://localhost/blend` atau virtual host Laragon |
| Data hilang setelah import | Database tujuan salah | Pastikan impor ke database `blend_3487` |

---

## Lisensi

**Internal Use Only — Proprietary.**

Proyek ini dibuat khusus untuk keperluan internal **B L E N D Coffee Shop**. Hak cipta dan seluruh hak atas kode, desain, serta aset di dalamnya dimiliki oleh pemilik proyek.

**Dilarang:**

- Menggunakan proyek ini, sebagian maupun seluruhnya, untuk tujuan komersial pihak lain.
- Menjual, menyewakan, atau mendistribusikan ulang kode dan aset.
- Menggunakan proyek ini sebagai dasar produk atau layanan bisnis lain tanpa izin tertulis.

**Diizinkan:**

- Penggunaan dan modifikasi untuk operasional internal B L E N D.
- Pengarsipan dan pencadangan (backup) oleh pemilik proyek.

---

<div align="center">

**B L E N D** — *Coffee, blended with care.*

Dibangun dengan PHP native, disiplin keamanan, dan secangkir kopi.

</div>
