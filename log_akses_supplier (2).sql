-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 12, 2026 at 07:06 AM
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
-- Table structure for table `log_akses_supplier`
--

CREATE TABLE `log_akses_supplier` (
  `id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `detail_barang` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `waktu_masuk` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `log_akses_supplier`
--

INSERT INTO `log_akses_supplier` (`id`, `supplier_id`, `detail_barang`, `waktu_masuk`) VALUES
(1, 1, '10 pcs', '2026-11-02 09:13:32'),
(3, 1, 'celana kantor 30 pcs', '2026-11-02 09:48:44'),
(4, 1, '-', '2026-11-03 14:41:23'),
(5, 4, 'sss', '2026-11-03 14:45:14'),
(6, 1, 'yang terbaru', '2026-11-06 08:43:35'),
(7, 1, 'kaos 5 pcs', '2026-11-11 15:26:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `log_akses_supplier`
--
ALTER TABLE `log_akses_supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_supplier` (`supplier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `log_akses_supplier`
--
ALTER TABLE `log_akses_supplier`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `log_akses_supplier`
--
ALTER TABLE `log_akses_supplier`
  ADD CONSTRAINT `log_akses_supplier_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `merchant_supplier` (`supplier_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
