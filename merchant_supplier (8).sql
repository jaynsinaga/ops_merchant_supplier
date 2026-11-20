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
-- Table structure for table `merchant_supplier`
--

CREATE TABLE `merchant_supplier` (
  `supplier_id` int NOT NULL,
  `nama_supplier` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `alamat` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `kategori_id` int DEFAULT NULL,
  `password` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `merchant_supplier`
--

INSERT INTO `merchant_supplier` (`supplier_id`, `nama_supplier`, `alamat`, `no_hp`, `kategori_id`, `password`) VALUES
(1, 'NIKEN', 'KLATEN', '085196213843', 3, 'NIKEN'),
(2, 'HARYONO', 'PRAMBANAN', '0852200643', 2, 'HARYONO'),
(3, 'BU SITI', 'SOLO', '0877836229540', 3, 'BUSITI'),
(4, 'BU PRIHATIN', 'PRAMBANAN', '', 1, 'BUPRIHATIN'),
(5, 'RANI', 'KLATEN', '085640461106', 3, 'RANI'),
(6, 'SUKINO', 'MANISRENGGO', '0856956075009', 2, 'SUKINO'),
(7, 'ERLIA', 'KLATEN', '08213757577795', 3, 'ERLIA'),
(8, 'ANTOK', 'SOLO', '085293544789', 3, 'ANTOK'),
(9, 'EKO MUHBADI', 'JOGONALAN', '085868782226', 2, 'EKOMUHBADI'),
(10, 'ZULHAM', 'BAYAT', '085878072350', 4, 'ZULHAM'),
(11, 'AGUS', 'KAJORAN WEDI', '08994180354', 3, 'AGUS'),
(12, 'ENDANG', 'KLATEN', '081235644145', 3, 'ENDANG'),
(13, 'DIKA', 'WEDI', '085293842638', 3, 'DIKA'),
(14, 'BU YUN', 'KLATEN', '085803448247', 1, 'BUYUN'),
(15, 'BU RUS', 'DUKUH', '087866957876', 1, 'BURUS'),
(16, 'SLAMET', 'SAMBUNGAN', '085868517417', 7, 'SLAMET'),
(17, 'GIYATI', 'PRAMBANAN', '085161638511', 1, 'GIYATI'),
(18, 'DWI', 'PRAMBANAN', '088224166230', 4, 'DWI'),
(19, 'AGUS', 'JOGJA', '081229236683', 2, 'AGUS'),
(20, 'GANIS', 'SOLO', '085601451923', 3, 'GANIS'),
(21, 'PARYANTO', 'SRAGEN', '085681330074', 3, 'PARYANTO'),
(22, 'BANTENG', 'BAYAT', '', 4, 'BANTENG'),
(23, 'ARIL', '', '081374899112', 4, 'ARIL'),
(24, 'MUH HAFID', '', '', 4, 'MUHHAFID'),
(25, 'BU RINI', 'JOGJA', '', 4, 'BURINI'),
(26, 'TOTOK', 'PRAMBANAN', '', 4, 'TOTOK'),
(27, 'KUNTARI', 'SOLO', '0813297225000', 3, 'KUNTARI'),
(28, 'SURAJI', 'JOGJA', '', 4, 'SURAJI'),
(29, 'WAHYONO', 'SLEMAN', '085725890903', 4, 'WAHYONO'),
(30, 'WIDODO', 'KLATEN', '0882006942374', 4, 'WIDODO'),
(31, 'SAMSUL', '', '', 3, 'SAMSUL'),
(32, 'RITA', 'JOGJA', '085935661391', 7, 'RITA'),
(33, 'ERWIN', 'PRAMBANAN', '085647579440', 2, 'ERWIN'),
(34, 'WAWAN', 'JOGJA', '', 4, 'WAWAN'),
(35, 'YULIANTO', 'JOGJA', '081225214608', 4, 'YULIANTO'),
(36, 'PAK MEN', 'PRAMBANAN', '', 1, 'PAKMEN'),
(37, 'DWI LATIFAH', 'KLATEN', '081393529073', 3, 'DWILATIFAH'),
(38, 'BUDI', 'JOGJA', '081228032660', 3, 'BUDI'),
(39, 'DANIK', 'SOLO', '', 3, 'DANIK'),
(40, 'MAS IS', 'IMOGIRI', '082134840949', 4, 'MASIS'),
(41, 'EKO MUH BUDI', 'JAGALAN', '', 2, 'EKOMUHBUDI'),
(42, 'BU WULAN', 'SUKOARJO', '085869458073', 3, 'BUWULAN'),
(43, 'DARU', 'KLATEN', '087856037433', 4, 'DARU'),
(44, 'UPIK', 'SELO', '082113663887', 7, 'UPIK'),
(45, 'SUMINI', 'BENER', '', 7, 'SUMINI'),
(46, 'BU PAR', 'TEGAL KEMPUL', '', 1, 'BUPAR'),
(47, 'NURUDIN', 'KALIKOTES', '', 1, 'NURUDIN'),
(48, 'MUNADI', 'TASKUMBANG', '081392425144', 1, 'MUNADI'),
(49, 'SULASMI', 'DENGOK ETAN', '081568393012', 1, 'SULASMI'),
(50, 'BU SRI M', 'MBENDAN', '', 1, 'BUSRIM'),
(51, 'GIANTI', 'CAGERAN', '', 1, 'GIANTI'),
(52, 'ANGGORO', 'SOLO', '085291848707', 3, 'ANGGORO'),
(53, 'BAWAR', 'JOJJA', '088980316035', 2, 'BAWAR'),
(54, 'NISA', 'WONOSARI', '087733158351', 4, 'NISA'),
(55, 'PAK WAWAN', 'SOLO', '082134279313', 3, 'PAKWAWAN'),
(56, 'HESTI', 'KLATEN', '', 3, 'HESTI'),
(57, 'PAMUJI', 'SUKOARJO', '087873556772', 3, 'PAMUJI'),
(58, 'SUTIKNO', 'BENER', '', 4, 'SUTIKNO'),
(59, 'SONDANG', 'TLOGO', '', 1, 'SONDANG'),
(60, 'SUTRISNO', 'MIRI', '', 3, 'SUTRISNO'),
(61, 'JUMINI', 'KULON PROGO', '085743066422', 4, 'JUMINI'),
(62, 'JAILANI', 'JOGJA', '', 4, 'JAILANI'),
(63, 'SUPRIADI', 'JOGJA', '08562879217', 4, 'SUPRIADI'),
(64, 'YADI', 'JOGJA', '0895363189944', 3, 'YADI'),
(65, 'BAYU', 'JOGJA', '', 2, 'BAYU'),
(66, 'IWAN', 'JOGJA', '', 4, 'IWAN'),
(67, 'BU NIK', 'KLATEN', '081357548893', 3, 'BUNIK'),
(68, 'SANDI', 'JOGJA', '085601736515', 3, 'SANDI'),
(69, 'RUSNIYATI', 'BANTUL', '', 4, 'RUSNIYATI'),
(70, 'ISNA', 'SOLO', '087715753438', 3, 'ISNA'),
(71, 'SRI SUDARSIH', 'PRAMBANAN', '', 1, 'SRISUDARSIH'),
(72, 'INA USWATUN', 'KLATEN', '', 3, 'INAUSWATUN'),
(73, 'ERWIN', 'PRAMBANAN', '', 2, 'ERWIN'),
(74, 'BUDI', 'BANTUL', '081904112908', 4, 'BUDI'),
(75, 'RIA', 'DELANGGU', '088216685931', 4, 'RIA'),
(76, 'PRAYETNO', 'TURI', '085643347622', 2, 'PRAYETNO'),
(77, 'DODI', 'BEGER', '081315503044', 2, 'DODI'),
(78, 'SRI ATUN', 'PADAN', '', 1, 'SRIATUN'),
(79, 'TUKIJO', 'NAGSRI', '', 2, 'TUKIJO'),
(80, 'KADARISMAN', 'WIYORO', '0895424007625', 3, 'KADARISMAN'),
(81, 'EPI', 'PRAMBANAN', '082202263430', 2, 'EPI'),
(82, 'DEDI', 'TAMANAN', '081391103240', 4, 'DEDI'),
(83, 'SAROH', 'KLATEN', '', 1, 'SAROH'),
(84, 'SARIYANTI', 'KLATEN', '', 3, 'SARIYANTI'),
(85, 'TETEH IMAS', 'PRAMBANAN', '', 1, 'TETEHIMAS'),
(86, 'INDRA', 'SOLO', '081326308260', 3, 'INDRA'),
(87, 'HERU', 'SOLO', '085229889929', 3, 'HERU'),
(88, 'AKHSAN', 'BANTUL', '082242289554', 3, 'AKHSAN'),
(89, 'WIWIK', 'SOLO', '088216685600', 3, 'WIWIK'),
(90, 'RUMI', 'KLATEN', '', 1, 'RUMI'),
(91, 'WULAN', 'TASKOMBANG', '', 1, 'WULAN');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `merchant_supplier`
--
ALTER TABLE `merchant_supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_supplier_kategori` (`kategori_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `merchant_supplier`
--
ALTER TABLE `merchant_supplier`
  MODIFY `supplier_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `merchant_supplier`
--
ALTER TABLE `merchant_supplier`
  ADD CONSTRAINT `fk_supplier_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `merchant_kategori_barang` (`kategori_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
