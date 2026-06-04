-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 04, 2026 at 01:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_eloncamp`
--

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id_produk` int(11) NOT NULL,
  `nama_alat` varchar(100) DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `harga_sewa` int(11) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `stok` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id_produk`, `nama_alat`, `kategori`, `harga_sewa`, `deskripsi`, `stok`, `foto`, `gambar`) VALUES
(7, 'sepatu gunung', NULL, 25000, 'sepatu gunung', 16, '6a09652b1157b.jpeg', NULL),
(9, 'Sleeping Bag', NULL, 15000, 'Kantong tidur yang berfungsi menjaga suhu tubuh tetap stabil dan mencegah hipotermia saat berkemah di alam terbuka. Pemilihan sleeping bag yang tepat sangat bergantung pada bentuk, bahan penahan panas (insulasi), serta suhu lokasi kegiatan Anda.', 20, '6a09c56f0015b.jpg', NULL),
(10, 'Nesting', NULL, 15000, 'perangkat alat masak portabel untuk berkemah yang terdiri dari beberapa panci, wajan yang dirancang agar dapat ditumpuk dan dimasukkan satu sama lain', 21, '6a09c611ee875.jpg', NULL),
(11, 'Kompor Gas ', NULL, 20000, 'alat masak kompak berbobot ringan yang dirancang khusus untuk mempermudah kegiatan memasak di alam bebas. Alat ini menggunakan bahan bakar gas kaleng instan, dilengkapi pemantik mekanis tanpa korek, serta memiliki sistem lipat atau pelindung angin (windshield) guna memastikan api tetap stabil saat menghadapi cuaca gunung.', 20, '6a09c6aeb2727.jpg', NULL),
(12, 'Kompor Spirtus Lengkap', NULL, 35000, 'alternatif alat masak camping yang bekerja menggunakan bahan bakar cair seperti spirtus, etanol, atau metanol', 20, '6a09c72ddbe26.jpg', NULL),
(13, 'Matras', NULL, 5000, 'Matras camping berfungsi sebagai lapisan isolator yang memutus hantaran dingin dari permukaan tanah ke tubuh sekaligus memberikan alas tidur yang empuk di dalam tenda', 20, '6a09c77c87f07.jpg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) DEFAULT 1,
  `tgl_sewa` date NOT NULL,
  `tgl_kembali` date NOT NULL,
  `tgl_realisasi_kembali` date DEFAULT NULL,
  `total_harga` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `denda` int(11) NOT NULL DEFAULT 0,
  `kondisi` varchar(255) DEFAULT NULL,
  `metode_ambil` enum('ambil_di_tempat','kirim_ke_alamat') NOT NULL DEFAULT 'ambil_di_tempat',
  `nama_penerima` varchar(100) DEFAULT NULL,
  `no_hp_penerima` varchar(20) DEFAULT NULL,
  `alamat_kirim` text DEFAULT NULL,
  `foto_ktp` varchar(150) DEFAULT NULL,
  `selfie_ktp` varchar(150) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `ongkos_kirim` int(11) NOT NULL DEFAULT 0,
  `bukti_transfer` varchar(150) DEFAULT NULL,
  `refund_status` enum('diajukan','disetujui','ditolak') DEFAULT NULL,
  `refund_info` varchar(255) DEFAULT NULL,
  `no_resi` varchar(100) DEFAULT NULL,
  `ekspedisi` varchar(50) DEFAULT NULL,
  `video_terima` varchar(150) DEFAULT NULL,
  `kondisi_terima` varchar(20) DEFAULT NULL,
  `member_sudah_kirim` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `role` enum('admin','member') DEFAULT 'member'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `nama`, `email`, `password`, `no_hp`, `role`) VALUES
(1, 'Admin ElonCamp', 'admin@eloncamp.com', '$2y$10$zEkdsX8nBwfETn2Cd.MOeuoBo8XwuPZCyDzlp0IG/bIniZ4FhNesK', NULL, 'admin'),
(2, 'tian', 'tian@member.com', '$2y$10$VSseMvVWO3ATggGPDjStpOB0loBd.IkowYhtwaxxAfTwpRYx0RMom', '08123456789', 'member');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
