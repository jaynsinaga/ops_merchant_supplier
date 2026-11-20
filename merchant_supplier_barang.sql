-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 20, 2026 at 07:29 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ops`
--

-- --------------------------------------------------------

--
-- Table structure for table `merchant_supplier_barang`
--

CREATE TABLE `merchant_supplier_barang` (
  `barang_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `merchant_supplier_barang`
--

INSERT INTO `merchant_supplier_barang` (`barang_id`, `supplier_id`, `nama_barang`, `created_at`) VALUES
(1, 1, 'BATIK', '2026-11-19 08:07:28'),
(2, 2, 'BAKPIA', '2026-11-19 08:07:28'),
(3, 3, 'BATIK', '2026-11-19 08:07:28'),
(4, 4, 'TELUR', '2026-11-19 08:07:28'),
(5, 5, 'BATIK', '2026-11-19 08:07:28'),
(6, 6, 'OLEH OLEH', '2026-11-19 08:07:28'),
(7, 7, 'BATIK', '2026-11-19 08:07:28'),
(8, 8, 'BATIK', '2026-11-19 08:07:28'),
(9, 9, 'SALAK', '2026-11-19 08:07:28'),
(10, 10, 'GERABAH', '2026-11-19 08:07:28'),
(11, 11, 'BATIK', '2026-11-19 08:07:28'),
(12, 12, 'BATIK', '2026-11-19 08:07:28'),
(13, 13, 'BATIK', '2026-11-19 08:07:28'),
(14, 14, 'JAJANAN PASAR', '2026-11-19 08:07:28'),
(15, 15, 'LOTIS', '2026-11-19 08:07:28'),
(16, 16, 'DEGAN', '2026-11-19 08:07:28'),
(17, 17, 'JAJANAN PASAR', '2026-11-19 08:07:28'),
(18, 18, 'DOMPET', '2026-11-19 08:07:28'),
(19, 19, 'BAKPIA', '2026-11-19 08:07:28'),
(20, 20, 'PAKAIAN', '2026-11-19 08:07:28'),
(21, 21, 'BLANGKON', '2026-11-19 08:07:28'),
(22, 22, 'GERABAH', '2026-11-19 08:07:28'),
(23, 23, 'GELANG', '2026-11-19 08:07:28'),
(24, 24, 'SOUVENIR', '2026-11-19 08:07:28'),
(25, 25, 'SOUVENIR', '2026-11-19 08:07:28'),
(26, 26, 'TAS', '2026-11-19 08:07:28'),
(27, 27, 'BATIK', '2026-11-19 08:07:28'),
(28, 28, 'WAYANG', '2026-11-19 08:07:28'),
(29, 29, 'TAS', '2026-11-19 08:07:28'),
(30, 30, 'SOUVENIR', '2026-11-19 08:07:28'),
(31, 31, 'BATIK', '2026-11-19 08:07:28'),
(32, 32, 'JAHE SASET', '2026-11-19 08:07:28'),
(33, 33, 'GEPLAK', '2026-11-19 08:07:28'),
(34, 34, 'TAS', '2026-11-19 08:07:28'),
(35, 35, 'TAS', '2026-11-19 08:07:28'),
(36, 36, 'MIE AYAM', '2026-11-19 08:07:28'),
(37, 37, 'BATIK', '2026-11-19 08:07:28'),
(38, 38, 'KAOS', '2026-11-19 08:07:28'),
(39, 39, 'BATIK', '2026-11-19 08:07:28'),
(40, 40, 'TAS', '2026-11-19 08:07:28'),
(41, 41, 'SALAK', '2026-11-19 08:07:28'),
(42, 42, 'CELANA', '2026-11-19 08:07:28'),
(43, 43, 'TAS', '2026-11-19 08:07:28'),
(44, 44, 'YAKULT', '2026-11-19 08:07:28'),
(45, 45, 'JAMU', '2026-11-19 08:07:28'),
(46, 46, 'JAJANAN PASAR', '2026-11-19 08:07:28'),
(47, 47, 'KRUPUK', '2026-11-19 08:07:28'),
(48, 48, 'LONTONG', '2026-11-19 08:07:28'),
(49, 49, 'NASI AYAM', '2026-11-19 08:07:28'),
(50, 50, 'AYAM', '2026-11-19 08:07:28'),
(51, 51, 'GETUK', '2026-11-19 08:07:28'),
(52, 52, 'CELANA', '2026-11-19 08:07:28'),
(53, 53, 'BAKPIA', '2026-11-19 08:07:28'),
(54, 54, 'TAS', '2026-11-19 08:07:28'),
(55, 55, 'UDENG', '2026-11-19 08:07:28'),
(56, 56, 'BAJU', '2026-11-19 08:07:28'),
(57, 57, 'PAKAIAN', '2026-11-19 08:07:28'),
(58, 58, 'TISU', '2026-11-19 08:07:28'),
(59, 59, 'JAJANAN', '2026-11-19 08:07:28'),
(60, 60, 'SANDAL', '2026-11-19 08:07:28'),
(61, 61, 'TAS', '2026-11-19 08:07:28'),
(62, 62, 'DOMPET', '2026-11-19 08:07:28'),
(63, 63, 'GANTUNGAN KUNCI', '2026-11-19 08:07:28'),
(64, 64, 'BLANGKON', '2026-11-19 08:07:28'),
(65, 65, 'BAKPIA', '2026-11-19 08:07:28'),
(66, 66, 'TAS', '2026-11-19 08:07:28'),
(67, 67, 'BATIK', '2026-11-19 08:07:28'),
(68, 68, 'PAKAIAN', '2026-11-19 08:07:28'),
(69, 69, 'SOUVENIR', '2026-11-19 08:07:28'),
(70, 70, 'BATIK', '2026-11-19 08:07:28'),
(71, 71, 'SAYURAN', '2026-11-19 08:07:28'),
(72, 72, 'BATIK', '2026-11-19 08:07:28'),
(73, 73, 'GEPLAK', '2026-11-19 08:07:28'),
(74, 74, 'TAS', '2026-11-19 08:07:28'),
(75, 75, 'DOMPET', '2026-11-19 08:07:28'),
(76, 76, 'SALAK', '2026-11-19 08:07:28'),
(77, 77, 'BAKPIA', '2026-11-19 08:07:28'),
(78, 78, 'MAKANAN', '2026-11-19 08:07:28'),
(79, 79, 'GEPLAK', '2026-11-19 08:07:28'),
(80, 80, 'TOPI', '2026-11-19 08:07:28'),
(81, 81, 'BAKPIA', '2026-11-19 08:07:28'),
(82, 82, 'UBORAMPE', '2026-11-19 08:07:28'),
(83, 83, 'SATE', '2026-11-19 08:07:28'),
(84, 84, 'BAJU', '2026-11-19 08:07:28'),
(85, 85, 'JAJANAN', '2026-11-19 08:07:28'),
(86, 86, 'DASTER', '2026-11-19 08:07:28'),
(87, 87, 'PAKAIAN', '2026-11-19 08:07:28'),
(88, 88, 'KAOS', '2026-11-19 08:07:28'),
(89, 89, 'BLANGKON', '2026-11-19 08:07:28'),
(90, 90, 'JAJANAN', '2026-11-19 08:07:28'),
(132, 91, 'JAJANAN', '2026-11-19 09:43:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `merchant_supplier_barang`
--
ALTER TABLE `merchant_supplier_barang`
  ADD PRIMARY KEY (`barang_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `merchant_supplier_barang`
--
ALTER TABLE `merchant_supplier_barang`
  MODIFY `barang_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `merchant_supplier_barang`
--
ALTER TABLE `merchant_supplier_barang`
  ADD CONSTRAINT `merchant_supplier_barang_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `merchant_supplier` (`supplier_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
