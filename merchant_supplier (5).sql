-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 17, 2026 at 04:40 AM
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
  `jenis_barang` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `kategori_id` int DEFAULT NULL,
  `password` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `merchant_supplier`
--

INSERT INTO `merchant_supplier` (`supplier_id`, `nama_supplier`, `alamat`, `no_hp`, `jenis_barang`, `kategori_id`, `password`) VALUES
(1, 'NIKEN', 'KLATEN', '085196213843', 'BATIK', 3, '7848'),
(2, 'HARYONO', 'PRAMBANAN', '0852200643', 'BAKPIA', 2, '0186'),
(3, 'BU SITI', 'SOLO', '0877836229540', 'BATIK', 3, '7387'),
(4, 'BU PRIHATIN', 'PRAMBANAN', '', 'TELUR', 1, '6376'),
(5, 'RANI', 'KLATEN', '085640461106', 'BATIK', 3, '9720'),
(6, 'SUKINO', 'MANISRENGGO', '0856956075009', 'OLEH OLEH', 2, '9475'),
(7, 'ERLIA', 'KLATEN', '08213757577795', 'BATIK', 3, '8214'),
(8, 'ANTOK ', 'SOLO', '085293544789', 'BATIK', 3, '2646'),
(9, 'EKO MUHBADI', 'JOGONALAN', '085868782226', 'SALAK', 1, '8590'),
(10, 'ZULHAM', 'BAYAT', '085878072350', 'GERABAH', 4, '5009'),
(11, 'AGUS', 'KAJORAN WEDI', '08994180354', 'BATIK', 3, '9279'),
(12, 'ENDANG', 'KLATEN', '081235644145', 'BATIK', 3, '1367'),
(13, 'DIKA', 'WEDI', '085293842638', 'BATIK', 3, '8999'),
(14, 'BU YUN', 'KLATEN', '085803448247', 'JAJANAN PASAR', 1, '0896'),
(15, 'BU RUS', 'DUKUH', '087866957876', 'LOTIS', 1, '7482'),
(16, 'SLAMET', 'SAMBUNGAN', '085868517417', 'DEGAN', 1, '4724'),
(17, 'GIYATI', 'PRAMBANAN', '085161638511', 'JAJANAN PASAR', 1, '1175'),
(18, 'DWI', 'PRAMBANAN', '088224166230', 'DOMPET', 4, '1704'),
(19, 'AGUS', 'JOGJA', '081229236683', 'BAKPIA', 2, '4996'),
(20, 'GANIS', 'SOLO', '085601451923', 'PAKAIAN', 3, '9866'),
(21, 'PARYANTO', 'SRAGEN', '085681330074', 'BLANGKON', 3, '4346'),
(22, 'BANTENG', 'BAYAT', '', 'GERABAH', 4, '2129'),
(23, 'ARIL', '', '081374899112', 'GELANG', 4, '7607'),
(24, 'MUH HAFID', '', '', 'SOUVENIR', 4, '1650'),
(25, 'BU RINI', 'JOGJA', '', 'SOUVENIR', 4, '5429'),
(26, 'TOTOK', 'PRAMBANAN', '', 'TAS', 4, '2194'),
(27, 'KUNTARI', 'SOLO', '0813297225000', 'BATIK', 3, '4687'),
(28, 'SURAJI', 'JOGJA', '', 'WAYANG', 4, '6850'),
(29, 'WAHYONO', 'SLEMAN', '085725890903', 'TAS', 4, '0191'),
(30, 'WIDODO', 'KLATEN', '0882006942374', 'SOUVENIR', 4, '0405'),
(31, 'SAMSUL', '', '', 'BATIK', 3, '1451'),
(32, 'RITA', 'JOGJA', '085935661391', 'JAHE SASET', 1, '6042'),
(33, 'ERWIN', 'PRAMBANAN', '085647579440', 'GEPLAK', 2, '5858'),
(34, 'WAWAN', 'JOGJA', '', 'TAS', 4, '1166'),
(35, 'YULIANTO', 'JOGJA', '081225214608', 'TAS', 4, '8254'),
(36, 'PAK MEN', 'PRAMBANAN', '', 'MIE AYAM', 1, '7772'),
(37, 'DWI LATIFAH', 'KLATEN', '081393529073', 'BATIK', 3, '4101'),
(38, 'BUDI', 'JOGJA', '081228032660', 'KAOS', 3, '7188'),
(39, 'DANIK', 'SOLO', '', 'BATIK', 3, '3636'),
(40, 'MAS IS', 'IMOGIRI', '082134840949', 'TAS', 4, '6618'),
(41, 'EKO MUH BUDI', 'JAGALAN', '', 'SALAK', 1, '2180'),
(42, 'BU WULAN', 'SUKOARJO', '085869458073', 'CELANA', 3, '1050'),
(43, 'DARU', 'KLATEN', '087856037433', 'TAS', 4, '8709'),
(44, 'UPIK', 'SELO', '082113663887', 'YAKULT', 1, '0395'),
(45, 'SUMINI', 'BENER', '', 'JAMU', 1, '5851'),
(46, 'BU PAR', 'TEGAL KEMPUL', '', 'JAJANAN PASAR', 1, '8069'),
(47, 'NURUDIN', 'KALIKOTES', '', 'KRUPUK', 1, '2794'),
(48, 'MUNADI', 'TASKUMBANG', '081392425144', 'LONTONG', 1, '9764'),
(49, 'SULASMI', 'DENGOK ETAN', '081568393012', 'NASI AYAM', 1, '0436'),
(50, 'BU SRI M', 'MBENDAN', '', 'AYAM', 1, '2889'),
(51, 'GIANTI', 'CAGERAN', '', 'GETUK', 1, '3139'),
(52, 'ANGGORO', 'SOLO', '085291848707', 'CELANA', 3, '7027'),
(53, 'BAWAR', 'JOJJA', '088980316035', 'BAKPIA', 2, '5718'),
(54, 'NISA', 'WONOSARI', '087733158351', 'TAS', 4, '7511'),
(55, 'PAK WAWAN', 'SOLO', '082134279313', 'UDENG', 3, '0401'),
(56, 'HESTI', 'KLATEN', '', 'BAJU', 3, '9471'),
(57, 'PAMUJI', 'SUKOARJO', '087873556772', 'PAKAIAN', 3, '6152'),
(58, 'SUTIKNO', 'BENER', '', 'TISU', 4, '2349'),
(59, 'SONDANG', 'TLOGO', '', 'JAJANAN', 1, '3290'),
(60, 'SUTRISNO', 'MIRI', '', 'SANDAL', 3, '9403'),
(61, 'JUMINI', 'KULON PROGO', '085743066422', 'TAS', 4, '7146'),
(62, 'JAILANI', 'JOGJA', '', 'DOMPET', 4, '7519'),
(63, 'SUPRIADI', 'JOGJA', '08562879217', 'GANTUNGAN KUNCI', 4, '6160'),
(64, 'YADI', 'JOGJA', '0895363189944', 'BLANGKON', 3, '8243'),
(65, 'BAYU', 'JOGJA', '', 'BAKPIA', 2, '2737'),
(66, 'IWAN', 'JOGJA', '', 'TAS', 4, '8956'),
(67, 'BU NIK', 'KLATEN', '081357548893', 'BATIK', 3, '6570'),
(68, 'SANDI', 'JOGJA', '085601736515', 'PAKAIAN', 3, '5980'),
(69, 'RUSNIYATI', 'BANTUL', '', 'SOUVENIR', 4, '0191'),
(70, 'ISNA', 'SOLO', '087715753438', 'BATIK', 3, '3018'),
(71, 'SRI SUDARSIH', 'PRAMBANAN', '', 'SAYURAN', 1, '4514'),
(72, 'INA USWATUN', 'KLATEN', '', 'BATIK', 3, '3518'),
(73, 'ERWIN', 'PRAMBANAN', '', 'GEPLAK', 2, '4050'),
(74, 'BUDI', 'BANTUL', '081904112908', 'TAS', 4, '9697'),
(75, 'RIA ', 'DELANGGU', '088216685931', 'DOMPET', 4, '6336'),
(76, 'PRAYETNO', 'TURI', '085643347622', 'SALAK', 1, '2588'),
(77, 'DODI', 'BEGER', '081315503044', 'BAKPIA', 2, '3932'),
(78, 'SRI ATUN', 'PADAN', '', 'MAKANAN', 1, '1898'),
(79, 'TUKIJO', 'NAGSRI', '', 'GEPLAK', 2, '7693'),
(80, 'KADARISMAN', 'WIYORO', '0895424007625', 'TOPI', 3, '2770'),
(81, 'EPI', 'PRAMBANAN', '082202263430', 'BAKPIA', 2, '0775'),
(82, 'DEDI', 'TAMANAN', '081391103240', 'UBORAMPE', 4, '5562'),
(83, 'SAROH', 'KLATEN', '', 'SATE', 1, '5489'),
(84, 'SARIYANTI', 'KLATEN', '', 'BAJU', 3, '0757'),
(85, 'TETEH IMAS', 'PRAMBANAN', '', 'JAJANAN', 1, '7320'),
(86, 'INDRA', 'SOLO', '081326308260', 'DASTER', 3, '4331'),
(87, 'HERU', 'SOLO', '085229889929', 'PAKAIAN', 3, '9694'),
(88, 'AKHSAN', 'BANTUL', '082242289554', 'KAOS', 3, '5479'),
(89, 'WIWIK', 'SOLO', '088216685600', 'BLANGKON', 3, '8311'),
(90, 'RUMI', 'KLATEN', '', 'JAJANAN', 1, '5121'),
(91, 'WULAN', 'TASKOMBANG', '', 'JAJANAN', 1, '0672');

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
  MODIFY `supplier_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

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
