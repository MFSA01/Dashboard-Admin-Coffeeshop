-- =========================================================
-- Database: blend_3487
-- Brand   : B L E N D Coffee Shop
-- Engine  : MySQL / MariaDB (Laragon)
-- =========================================================

CREATE DATABASE IF NOT EXISTS `blend_3487`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `blend_3487`;

-- Bersihkan tabel lama jika script dijalankan ulang (urutan child -> parent)
DROP TABLE IF EXISTS `transaksi`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------
-- 1. Tabel users (Autentikasi Admin)
-- ---------------------------------------------------------
CREATE TABLE `users` (
  `id_user` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 2. Tabel menu (Master Data Produk B L E N D)
-- ---------------------------------------------------------
CREATE TABLE `menu` (
  `id_menu` INT NOT NULL AUTO_INCREMENT,
  `nama_menu` VARCHAR(100) NOT NULL,
  `kategori` ENUM('Coffee', 'Ice Blended', 'Tea', 'Non Coffee') NOT NULL,
  `deskripsi` TEXT NOT NULL,
  `stok` INT NOT NULL DEFAULT 0,
  `harga` INT NOT NULL,
  `gambar` VARCHAR(255) NOT NULL DEFAULT 'default.jpg',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_menu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 3. Tabel transaksi (Pencatatan Pembelian — Relasi ke menu)
-- ---------------------------------------------------------
CREATE TABLE `transaksi` (
  `id_transaksi` INT NOT NULL AUTO_INCREMENT,
  `nomor_nota` VARCHAR(30) NOT NULL,
  `tanggal_pembelian` DATE NOT NULL,
  `nama_kasir` VARCHAR(100) NOT NULL,
  `id_menu` INT NOT NULL,
  `jumlah` INT NOT NULL,
  `harga_satuan` INT NOT NULL,
  `total_harga` INT NOT NULL,
  `metode_pembayaran` ENUM('Tunai', 'QRIS', 'Transfer Bank') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_transaksi`),
  UNIQUE KEY `uk_transaksi_nota` (`nomor_nota`),
  KEY `idx_transaksi_menu` (`id_menu`),
  CONSTRAINT `fk_transaksi_menu`
    FOREIGN KEY (`id_menu`)
    REFERENCES `menu` (`id_menu`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- DATA AWAL (SEED DATA)
-- =========================================================

-- Akun Default: username = admin | password = admin (hash bcrypt PHP 8.3)
INSERT INTO `users` (`username`, `password`, `nama_lengkap`) VALUES
('admin', '$2y$10$ZB4h1KEE1boEvB3uLkwLBe6s9mb5OkRXOeJwknCTB/UqbgVpbPEau', 'Administrator B L E N D');

-- Data Awal Menu B L E N D (4 kategori · foto cup resmi per kategori)
INSERT INTO `menu` (`nama_menu`, `kategori`, `deskripsi`, `stok`, `harga`, `gambar`) VALUES
('Signature Oat Latte', 'Coffee', 'Espresso house-blend dipadukan dengan susu oat creamy dan sentuhan gula aren ringan.', 28, 32000, 'biru.jpeg'),
('Midnight Cold Brew', 'Coffee', 'Kopi single-origin seduh dingin 16 jam dengan profil rasa cokelat gelap dan aftertaste bersih.', 19, 28000, 'biru.jpeg'),
('Iced Spanish Latte', 'Coffee', 'Double shot espresso dengan campuran susu segar dan susu kental manis yang seimbang.', 24, 30000, 'biru.jpeg'),
('Ceremonial Matcha Oat', 'Non Coffee', 'Bubuk matcha Jepang pilihan dikocok lembut bersama susu oat dingin tanpa pemanis berlebih.', 15, 35000, 'merah.jpeg'),
('Lychee Jasmine Tea', 'Tea', 'Seduhan teh melati aromatik dipadukan dengan sirup dan potongan buah leci segar.', 12, 25000, 'hijau.jpeg'),
('Caramel Java Ice Blended', 'Ice Blended', 'Perpaduan kopi, cokelat chip, dan saus karamel yang diblend halus dengan tekstur lembut.', 8, 36000, 'kuning.jpeg'),
('Cookies & Cream Blend', 'Ice Blended', 'Susu segar diblend bersama remahan biskuit cokelat renyah dan krim vanila lembut.', 20, 34000, 'kuning.jpeg'),
('Salted Caramel Frappe', 'Ice Blended', 'Minuman es blend rasa karamel gurih-manis dengan sensasi creamy yang menyegarkan.', 16, 35000, 'kuning.jpeg'),
('Peach Earl Grey Tea', 'Tea', 'Teh hitam Earl Grey beraroma bergamot berpadu dengan manis segar ekstrak buah persik.', 22, 26000, 'hijau.jpeg'),
('Honey Lemon Green Tea', 'Tea', 'Teh hijau seduh dingin dengan perasan lemon asli dan madu alami yang ringan.', 25, 24000, 'hijau.jpeg'),
('Belgian Dark Chocolate', 'Non Coffee', 'Cokelat hitam Belgia pekat yang disajikan dingin bersama susu segar lembut.', 18, 32000, 'merah.jpeg'),
('Red Velvet Cream Oat', 'Non Coffee', 'Minuman red velvet klasik dengan aroma kakao ringan dan susu oat yang velvety.', 14, 33000, 'merah.jpeg');

-- Data Awal Transaksi (total_harga = harga_satuan * jumlah)
INSERT INTO `transaksi` (`nomor_nota`, `tanggal_pembelian`, `nama_kasir`, `id_menu`, `jumlah`, `harga_satuan`, `total_harga`, `metode_pembayaran`) VALUES
('BLD-20261120-001', '2026-11-20', 'Raka Pratama', 1, 2, 32000, 64000, 'QRIS'),
('BLD-20261121-001', '2026-11-21', 'Nadia Putri', 5, 3, 25000, 75000, 'Tunai'),
('BLD-20261121-002', '2026-11-21', 'Raka Pratama', 2, 1, 28000, 28000, 'Transfer Bank'),
('BLD-20261122-001', '2026-11-22', 'Nadia Putri', 4, 2, 35000, 70000, 'QRIS');