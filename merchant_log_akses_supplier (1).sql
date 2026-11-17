-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 17, 2026 at 04:41 AM
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
-- Table structure for table `merchant_log_akses_supplier`
--

CREATE TABLE `merchant_log_akses_supplier` (
  `id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `jumlah` int NOT NULL DEFAULT '0',
  `satuan_id` int DEFAULT NULL,
  `waktu_masuk` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `merchant_log_akses_supplier`
--

INSERT INTO `merchant_log_akses_supplier` (`id`, `supplier_id`, `jumlah`, `satuan_id`, `waktu_masuk`) VALUES
(8, 1, 0, NULL, '2026-11-17 10:39:53'),
(9, 1, 7, 1, '2026-11-17 10:40:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `merchant_log_akses_supplier`
--
ALTER TABLE `merchant_log_akses_supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_supplier` (`supplier_id`),
  ADD KEY `idx_log_satuan` (`satuan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `merchant_log_akses_supplier`
--
ALTER TABLE `merchant_log_akses_supplier`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `merchant_log_akses_supplier`
--
ALTER TABLE `merchant_log_akses_supplier`
  ADD CONSTRAINT `fk_log_satuan` FOREIGN KEY (`satuan_id`) REFERENCES `merchant_satuan_barang` (`satuan_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `merchant_log_akses_supplier_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `merchant_supplier` (`supplier_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
