-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2025 at 08:53 AM
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
-- Database: `tp-system`
--

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(11) NOT NULL,
  `district_name` varchar(100) NOT NULL,
  `district_code` varchar(10) DEFAULT NULL,
  `province_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `district_name`, `district_code`, `province_id`, `created_at`, `updated_at`) VALUES
(1, 'ບໍ່ແຕນ', 'BT', 7, '2025-06-20 06:35:09', '2025-06-21 05:52:08'),
(3, 'ຈັນທະບູລີ', 'CHL', 1, '2025-06-20 07:14:11', '2025-06-21 00:28:58'),
(4, 'ສີໂຄດຕະບອງ', 'SIKH', 1, '2025-06-20 07:14:35', '2025-06-21 00:29:51'),
(6, 'ນາຊາຍທອງ', 'NAS', 1, '2025-06-20 07:15:23', '2025-06-21 00:29:12'),
(7, 'ຫາດຊາຍພອງ', 'HSP', 1, '2025-06-20 07:16:15', '2025-06-21 00:30:04'),
(8, 'ປາກງື່ມ', 'PONG', 1, '2025-06-20 07:20:09', '2025-06-21 00:29:26'),
(9, 'ສັງທອງ', 'STONG', 1, '2025-06-20 07:20:33', '2025-06-21 00:29:39'),
(10, 'ໄຊທານີ', 'STN', 1, '2025-06-20 09:06:18', '2025-06-21 00:30:13'),
(11, 'ທ່າແຂກ', 'TK', 8, '2025-06-20 15:09:30', '2025-06-20 15:09:30'),
(12, 'ມະຫາໄຊ', 'MS', 8, '2025-06-20 15:09:52', '2025-06-20 15:09:52'),
(13, 'ໜອງບົກ', 'NB', 8, '2025-06-20 15:10:24', '2025-06-20 15:10:24'),
(14, 'ຫີນບູນ', 'HB', 8, '2025-06-20 15:10:37', '2025-06-20 15:10:37'),
(15, 'ຍົມມະລາດ', 'YML', 8, '2025-06-20 15:10:53', '2025-06-20 15:10:53'),
(16, 'ບົວລະພາ', 'BLP', 8, '2025-06-20 15:11:05', '2025-06-20 15:11:05'),
(17, 'ນາກາຍ', 'NK', 8, '2025-06-20 15:11:15', '2025-06-20 15:11:15'),
(18, 'ເຊບັ້ງໄຟ', 'SBP', 8, '2025-06-20 15:11:30', '2025-06-20 15:11:30'),
(19, 'ໄຊບົວທອງ', 'SBT', 8, '2025-06-20 15:11:44', '2025-06-20 15:11:44'),
(20, 'ຄູນຄຳ', 'KK', 8, '2025-06-20 15:12:02', '2025-06-20 15:12:02'),
(21, 'ປາກເຊ', 'PS', 3, '2025-06-20 15:14:38', '2025-06-20 15:14:38'),
(22, 'ຊະນະສົມບູນ', 'SNSB', 3, '2025-06-20 15:14:54', '2025-06-20 15:14:54'),
(23, 'ບາຈຽງຈະເລີນສຸກ', 'BLS', 3, '2025-06-20 15:15:18', '2025-06-20 15:15:18'),
(24, 'ປາກຊ່ອງ', 'PS', 3, '2025-06-20 15:15:29', '2025-06-20 15:15:29'),
(25, 'ປະທຸມພອນ', 'PTP', 3, '2025-06-20 15:16:01', '2025-06-20 15:16:01'),
(26, 'ໂພນທອງ', 'PT', 3, '2025-06-20 15:16:14', '2025-06-20 15:16:14'),
(27, 'ຈຳປາສັກ', 'CPS', 3, '2025-06-20 15:16:30', '2025-06-20 15:16:30'),
(28, 'ສຸຂຸມາ', 'SKM', 3, '2025-06-20 15:16:44', '2025-06-20 15:16:44'),
(29, 'ມູນລະປະໂມກ', 'MLM', 3, '2025-06-20 15:16:57', '2025-06-20 15:16:57'),
(30, 'ໂຂງ', 'K', 3, '2025-06-20 15:17:06', '2025-06-20 15:17:06'),
(31, 'ໂພນສະຫວັນ', 'PSV', 11, '2025-06-20 15:18:39', '2025-06-20 15:18:39'),
(32, 'ຄຳ', 'KH', 11, '2025-06-20 15:18:57', '2025-06-20 15:18:57'),
(33, 'ໜອງແຮດ', 'NH', 11, '2025-06-20 15:19:10', '2025-06-20 15:19:10'),
(34, 'ຄູນ', 'K', 11, '2025-06-20 15:19:23', '2025-06-20 15:19:23'),
(35, 'ໝອກ', 'M', 11, '2025-06-20 15:19:34', '2025-06-20 15:19:34'),
(36, 'ພູກູດ', 'PK', 11, '2025-06-20 15:19:45', '2025-06-20 15:19:45'),
(37, 'ຜາໄຊ', 'PX', 11, '2025-06-20 15:19:58', '2025-06-20 15:19:58'),
(38, 'ປາກຊັນ', 'PS', 16, '2025-06-20 15:21:22', '2025-06-20 15:21:22'),
(39, 'ທ່າພະບາດ', 'TPB', 16, '2025-06-20 15:21:37', '2025-06-20 15:21:37'),
(40, 'ປາກກະດິງ', 'PKD', 16, '2025-06-20 15:21:49', '2025-06-20 15:21:49'),
(41, 'ຄຳເກີດ', 'KK', 16, '2025-06-20 15:22:04', '2025-06-20 15:22:04'),
(42, 'ບໍລິຄັນ', 'BLK', 16, '2025-06-20 15:22:17', '2025-06-20 15:22:17'),
(43, 'ວຽງທອງ', 'VT', 16, '2025-06-20 15:22:30', '2025-06-20 15:22:30'),
(44, 'ໄຊຈຳພອນ', 'XCP', 16, '2025-06-20 15:22:53', '2025-06-20 15:22:53'),
(45, 'ຫ້ວຍຊາຍ', 'HCH', 14, '2025-06-20 15:24:31', '2025-06-20 15:24:31'),
(46, 'ຕົ້ນເຜິ້ງ', 'TP', 14, '2025-06-20 15:24:42', '2025-06-20 15:24:42'),
(47, 'ເມິງ', 'M', 14, '2025-06-20 15:24:54', '2025-06-20 15:24:54'),
(48, 'ຜາອຸດົມ', 'POD', 14, '2025-06-20 15:25:06', '2025-06-20 15:25:06'),
(49, 'ປາກທາ', 'PT', 14, '2025-06-20 15:25:19', '2025-06-20 15:25:19'),
(50, 'ບຸນໃຕ້', 'BT', 17, '2025-06-20 15:29:32', '2025-06-20 15:29:32'),
(51, 'ຂວາ', 'KH', 17, '2025-06-20 15:29:43', '2025-06-20 15:29:43'),
(52, 'ໃໝ່', 'MAI', 17, '2025-06-20 15:29:53', '2025-06-20 15:29:53'),
(53, 'ຍອດອູ', 'YO', 17, '2025-06-20 15:30:05', '2025-06-20 15:30:05'),
(54, 'ຜົ້ງສາລີ', 'PSL', 17, '2025-06-20 15:30:17', '2025-06-20 15:30:17'),
(55, 'ສຳພັນ', 'SP', 17, '2025-06-20 15:30:27', '2025-06-20 15:30:27'),
(56, 'ບຸນເໜືອ', 'BN', 17, '2025-06-20 15:30:37', '2025-06-20 15:30:37'),
(57, 'ເຟືອງ', 'PEA', 15, '2025-06-20 15:31:36', '2025-06-20 15:31:36'),
(58, 'ຫີນເຫີບ', 'HIH', 15, '2025-06-20 15:31:51', '2025-06-20 15:31:51'),
(59, 'ກາສີ', 'KAS', 15, '2025-06-20 15:32:05', '2025-06-20 15:32:05'),
(60, 'ແກ້ວອຸດົມ', 'KOD', 15, '2025-06-20 15:32:18', '2025-06-20 15:32:18'),
(61, 'ແມດ', 'MEA', 15, '2025-06-20 15:32:29', '2025-06-20 15:32:29'),
(62, 'ໂພນໂຮງ', 'PH', 15, '2025-06-20 15:32:44', '2025-06-20 15:32:44'),
(63, 'ທຸລະຄົມ', 'TLK', 15, '2025-06-20 15:32:55', '2025-06-20 15:32:55'),
(64, 'ວັງວຽງ', 'VV', 15, '2025-06-20 15:33:04', '2025-06-20 15:33:04'),
(65, 'ວຽງຄຳ', 'VK', 15, '2025-06-20 15:33:14', '2025-06-20 15:33:14'),
(66, 'ຊະນະຄາມ', 'SNK', 15, '2025-06-20 15:33:26', '2025-06-20 15:33:26'),
(67, 'ໝື່ນ', 'MEU', 15, '2025-06-20 15:33:40', '2025-06-20 15:33:40'),
(68, 'ໄກສອນ ພົມວິຫານ', 'KSP', 4, '2025-06-20 15:39:18', '2025-06-20 15:39:18'),
(69, 'ອຸທຸມພອນ', 'OTP', 4, '2025-06-20 15:39:31', '2025-06-20 15:39:31'),
(70, 'ອາດສະພັງທອງ', 'OSP', 4, '2025-06-20 15:39:46', '2025-06-20 15:39:46'),
(71, 'ພີນ', 'PIN', 4, '2025-06-20 15:39:57', '2025-06-20 15:39:57'),
(72, 'ເຊໂປນ', 'SP', 4, '2025-06-20 15:40:07', '2025-06-20 15:40:07'),
(73, 'ນອງ', 'NON', 4, '2025-06-20 15:40:17', '2025-06-20 15:40:17'),
(74, 'ທ່າປາງທອງ', 'TPT', 4, '2025-06-20 15:40:30', '2025-06-20 15:40:30'),
(75, 'ສອງຄອນ', 'SK', 4, '2025-06-20 15:40:51', '2025-06-20 15:40:51'),
(76, 'ຈຳພອນ', 'CHP', 4, '2025-06-20 15:41:02', '2025-06-20 15:41:02'),
(77, 'ຊົນນະບູລີ', 'SNL', 4, '2025-06-20 15:41:15', '2025-06-20 15:41:15'),
(78, 'ໄຊບູລີ', 'SBL', 4, '2025-06-20 15:41:25', '2025-06-20 15:41:25'),
(79, 'ວິລະບູລີ', 'VLB', 4, '2025-06-20 15:41:38', '2025-06-20 15:41:38'),
(80, 'ອາດສະພອນ', 'OSP', 4, '2025-06-20 15:41:50', '2025-06-20 15:41:50'),
(81, 'ໄຊພູທອງ', 'SPT', 4, '2025-06-20 15:42:02', '2025-06-20 15:42:02'),
(82, 'ພະລານໄຊ', 'PLS', 4, '2025-06-20 15:42:13', '2025-06-20 15:42:13'),
(83, 'ສາລະວັນ', 'SLV', 9, '2025-06-20 15:45:48', '2025-06-20 15:45:48'),
(84, 'ລະຄອນເພັງ', 'LKP', 9, '2025-06-20 15:47:16', '2025-06-20 15:47:16'),
(85, 'ວາປີ', 'VP', 9, '2025-06-20 15:47:28', '2025-06-20 15:47:28'),
(86, 'ເລົ່າງາມ', 'LONG', 9, '2025-06-20 15:47:47', '2025-06-20 15:47:47'),
(87, 'ຕຸ້ມລານ', 'TL', 9, '2025-06-20 15:47:57', '2025-06-20 15:47:57'),
(88, 'ຕະໂອ້ຍ', 'TO', 9, '2025-06-20 15:48:14', '2025-06-20 15:48:14'),
(89, 'ຄົງເຊໂດນ', 'KXD', 9, '2025-06-20 15:48:33', '2025-06-20 15:48:33'),
(90, 'ສະມ້ວຍ', 'SM', 9, '2025-06-20 15:48:45', '2025-06-20 15:48:45'),
(91, 'ຊຳເໜືອ', 'SN', 10, '2025-06-20 15:51:40', '2025-06-20 15:51:40'),
(92, 'ຊຽງຄໍ້', 'XK', 10, '2025-06-20 15:52:13', '2025-06-20 15:52:13'),
(93, 'ຮ້ຽມ', 'HEM', 10, '2025-06-20 15:52:47', '2025-06-20 15:52:47'),
(94, 'ວຽງໄຊ', 'VX', 10, '2025-06-20 15:53:38', '2025-06-20 15:53:38'),
(95, 'ຫົວເມືອງ', 'HM', 10, '2025-06-20 15:53:53', '2025-06-20 15:53:53'),
(96, 'ຊຳໃຕ້', 'ST', 10, '2025-06-20 15:54:11', '2025-06-20 15:54:11'),
(97, 'ສົບເບົາ', 'SP', 10, '2025-06-20 15:54:29', '2025-06-20 15:54:29'),
(98, 'ແອດ', 'AD', 10, '2025-06-20 15:54:52', '2025-06-20 15:54:52'),
(99, 'ກວັນ', 'KEN', 10, '2025-06-20 15:55:12', '2025-06-20 15:55:12'),
(100, 'ຊ່ອນ', 'SON', 10, '2025-06-20 15:55:26', '2025-06-20 15:55:26'),
(101, 'ຫຼວງນໍ້າທາ', 'LNT', 5, '2025-06-20 15:56:53', '2025-06-20 15:56:53'),
(102, 'ລອງ', 'LON', 5, '2025-06-20 15:58:02', '2025-06-20 15:58:02'),
(103, 'ວຽງພູຄາ', 'VPK', 5, '2025-06-20 15:58:13', '2025-06-20 15:58:13'),
(104, 'ນາແລ', 'NL', 5, '2025-06-20 15:58:22', '2025-06-20 15:58:22'),
(105, 'ຫຼວງພະບາງ', 'LPB', 2, '2025-06-20 15:59:10', '2025-06-20 15:59:10'),
(106, 'ຊຽງເງິນ', 'XONG', 2, '2025-06-20 15:59:37', '2025-06-20 15:59:37'),
(107, 'ນານ', 'NAN', 2, '2025-06-20 15:59:49', '2025-06-20 15:59:49'),
(108, 'ປາກອູ', 'PAO', 2, '2025-06-20 16:00:07', '2025-06-20 16:00:07'),
(109, 'ນ້ຳບາກ', 'NAM', 2, '2025-06-20 16:00:17', '2025-06-20 16:00:17'),
(110, 'ງອຍ', 'ONG', 2, '2025-06-20 16:00:26', '2025-06-20 16:00:26'),
(111, 'ປາກແຊງ', 'PAKS', 2, '2025-06-20 16:00:41', '2025-06-20 16:00:41'),
(112, 'ໂພນໄຊ', 'PONX', 2, '2025-06-20 16:00:53', '2025-06-20 16:00:53'),
(113, 'ຈອມເພັດ', 'CHOP', 2, '2025-06-20 16:01:04', '2025-06-20 16:01:04'),
(114, 'ວຽງຄຳ', 'VKH', 2, '2025-06-20 16:01:15', '2025-06-20 16:01:15'),
(115, 'ພູຄູນ', 'PKH', 2, '2025-06-20 16:01:25', '2025-06-20 16:01:25'),
(116, 'ໂພນທອງ', 'PONT', 2, '2025-06-20 16:01:37', '2025-06-20 16:01:37'),
(117, 'ໄຊເຊດຖາ', 'XAIT', 12, '2025-06-20 16:02:14', '2025-06-20 16:02:14'),
(118, 'ສາມັກຄີໄຊ', 'SAMX', 12, '2025-06-20 16:02:29', '2025-06-20 16:02:29'),
(119, 'ສະໜາມໄຊ', 'SAN', 12, '2025-06-20 16:02:39', '2025-06-20 16:02:39'),
(120, 'ຊານໄຊ', 'SANX', 12, '2025-06-20 16:02:51', '2025-06-20 16:02:51'),
(121, 'ພູວົງ', 'POUV', 12, '2025-06-20 16:03:02', '2025-06-20 16:03:02'),
(122, 'ໄຊ', 'XAI', 6, '2025-06-20 16:03:38', '2025-06-20 16:03:38'),
(123, 'ຫລາ', 'LA', 6, '2025-06-20 16:03:49', '2025-06-20 16:03:49'),
(124, 'ນາໝໍ້', 'NAM', 6, '2025-06-20 16:04:01', '2025-06-20 16:04:01'),
(125, 'ງາ', 'ONGA', 6, '2025-06-20 16:04:13', '2025-06-20 16:04:13'),
(126, 'ແບ່ງ', 'PEA', 6, '2025-06-20 16:04:46', '2025-06-20 16:04:46'),
(127, 'ຮຸນ', 'HUN', 6, '2025-06-20 16:05:06', '2025-06-20 16:05:06'),
(128, 'ປາກແບ່ງ', 'PAKP', 6, '2025-06-20 16:05:19', '2025-06-20 16:05:19'),
(129, 'ທ່າແຕງ', 'THAT', 13, '2025-06-20 16:05:55', '2025-06-20 16:05:55'),
(130, 'ລະມາມ', 'LAM', 13, '2025-06-20 16:06:11', '2025-06-20 16:06:11'),
(131, 'ກະລຶມ', 'KAL', 13, '2025-06-20 16:06:25', '2025-06-20 16:06:25'),
(132, 'ດັກຈຶງ', 'DAKC', 13, '2025-06-20 16:06:41', '2025-06-20 16:06:41'),
(133, 'ຫົງສາ', 'HONG', 7, '2025-06-20 16:07:49', '2025-06-20 16:07:49'),
(134, 'ແກ່ນທ້າວ', 'KENT', 7, '2025-06-20 16:07:58', '2025-06-20 16:07:58'),
(135, 'ຄອບ', 'KAP', 7, '2025-06-20 16:08:15', '2025-06-20 16:08:15'),
(136, 'ເງິນ', 'ONG', 7, '2025-06-20 16:08:26', '2025-06-20 16:08:26'),
(138, 'ພຽງ', 'PONG', 7, '2025-06-20 16:08:56', '2025-06-20 16:08:56'),
(139, 'ທົ່ງມີໄຊ', 'TMX', 7, '2025-06-20 16:09:09', '2025-06-20 16:09:09'),
(140, 'ໄຊຍະບູລີ', 'XYL', 7, '2025-06-20 16:09:24', '2025-06-20 16:09:24'),
(141, 'ຊຽງຮ່ອນ', 'XHON', 7, '2025-06-20 16:09:35', '2025-06-20 16:09:35'),
(143, 'ລ້ອງແຈ້ງ', 'LONG', 18, '2025-06-20 16:10:43', '2025-06-20 16:10:43'),
(144, 'ທ່າໂທມ', 'TAT', 18, '2025-06-20 16:10:56', '2025-06-20 16:10:56'),
(145, 'ອະນຸວົງ', 'ANV', 18, '2025-06-20 16:11:06', '2025-06-20 16:11:06'),
(146, 'ລ້ອງຊານ', 'LONGX', 18, '2025-06-20 16:11:23', '2025-06-20 16:11:23'),
(147, 'ຮົ່ມ', 'HOM', 18, '2025-06-20 16:11:32', '2025-06-20 16:11:32'),
(148, 'ປາກລາຍ', 'PAKL', 7, '2025-06-21 05:54:29', '2025-06-21 05:54:29');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `temple_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_monk`
--

CREATE TABLE `event_monk` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `monk_id` int(11) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monks`
--

CREATE TABLE `monks` (
  `id` int(11) NOT NULL COMMENT 'ລະຫັດ',
  `prefix` enum('ພຣະ','ຄຸນແມ່ຂາວ','ສ.ນ','ສັງກະລີ') DEFAULT NULL,
  `name` varchar(255) NOT NULL COMMENT 'ຊື່ພຣະສົງ',
  `lay_name` varchar(255) DEFAULT NULL COMMENT 'ຊື່ຄົນທົ່ວໄປກ່ອນບວດ',
  `pansa` int(11) NOT NULL COMMENT 'ຈໍານວນພັນສາ',
  `birth_date` date DEFAULT NULL COMMENT 'ວັນເກີດ',
  `birth_province` varchar(100) DEFAULT NULL,
  `ordination_date` date DEFAULT NULL COMMENT 'ວັນບວດ',
  `education` varchar(255) DEFAULT NULL COMMENT 'ການສຶກສາທົ່ວໄປ',
  `dharma_education` varchar(255) DEFAULT NULL COMMENT 'ການສຶກສາທາງທຳມະ',
  `contact_number` varchar(50) DEFAULT NULL COMMENT 'ເບີໂທຕິດຕໍ່',
  `temple_id` int(11) NOT NULL COMMENT 'ວັດທີ່ສັງກັດ',
  `position` varchar(255) DEFAULT NULL COMMENT 'ຕໍາແໜ່ງໃນວັດ',
  `photo` varchar(255) DEFAULT 'uploads/monks/default.png' COMMENT 'ຮູບພາບ',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'ສະຖານະ (ບວດຢູ່ / ສຶກແລ້ວ)',
  `created_at` datetime NOT NULL COMMENT 'ວັນທີ່ສ້າງ',
  `updated_at` datetime NOT NULL COMMENT 'ວັນທີ່ປັບປຸງຫຼ້າສຸດ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ຕາຕະລາງຂໍ້ມູນພຣະສົງ';

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `province_id` int(11) NOT NULL,
  `province_name` varchar(100) NOT NULL,
  `province_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`province_id`, `province_name`, `province_code`, `created_at`, `updated_at`) VALUES
(1, 'ນະຄອນຫຼວງວຽງຈັນ', 'VTE', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(2, 'ຫຼວງພະບາງ', 'LPB', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(3, 'ຈຳປາສັກ', 'CPS', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(4, 'ສະຫວັນນະເຂດ', 'SVK', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(5, 'ຫຼວງນໍ້າທາ', 'LNT', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(6, 'ອຸດົມໄຊ', 'ODX', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(7, 'ໄຊຍະບູລີ', 'XYB', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(8, 'ຄໍາມ່ວນ', 'KMN', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(9, 'ສາລະວັນ', 'SLV', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(10, 'ຫົວພັນ', 'HPN', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(11, 'ຊຽງຂວາງ', 'XKG', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(12, 'ອັດຕະປື', 'ATP', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(13, 'ເຊກອງ', 'XKN', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(14, 'ບໍ່ແກ້ວ', 'BKO', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(15, 'ວຽງຈັນ', 'VTP', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(16, 'ບໍລິຄໍາໄຊ', 'BLX', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(17, 'ຜົ້ງສາລີ', 'PSL', '2025-06-19 14:16:31', '2025-06-19 14:16:31'),
(18, 'ໄຊສົມບູນ', 'XSB', '2025-06-19 14:16:31', '2025-06-19 14:16:31');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'text',
  `options` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `type`, `options`, `updated_at`) VALUES
(1, 'site_name', 'ລະບົບຈັດການວັດ', 'general', 'ຊື່ເວັບໄຊທ໌', 'text', '', '2025-06-09 00:57:00'),
(2, 'site_description', 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ກິດຈະກໍາ', 'general', 'ຄໍາອະທິບາຍເວັບໄຊທ໌', 'textarea', '', '2025-06-09 00:57:00'),
(3, 'admin_email', 'phathasyla@gmail.com', 'general', 'ອີເມລຜູ້ດູແລລະບົບ', 'email', '', '2025-06-15 10:08:42'),
(4, 'contact_phone', '77772338', 'general', 'ເບີໂທຕິດຕໍ່', 'text', '', '2025-06-15 10:08:42'),
(5, 'footer_text', '© 2025 ລະບົບຈັດການວັດ. ສະຫງວນລິຂະສິດ ພັດທະນາໂດຍ ປອ.ອານັນທະສັກ ພັດທະສີລາ.', 'general', 'ຂໍ້ຄວາມສ່ວນລຸ່ມເວັບໄຊທ໌', 'textarea', '', '2025-06-20 21:34:30'),
(6, 'items_per_page', '10', 'system', 'ຈໍານວນລາຍການຕໍ່ຫນ້າ', 'number', '', '2025-06-09 00:57:00'),
(7, 'date_format', 'd/m/Y', 'system', 'ຮູບແບບວັນທີ', 'text', '', '2025-06-09 00:57:00'),
(8, 'time_format', 'H:i', 'system', 'ຮູບແບບເວລາ', 'text', '', '2025-06-09 00:57:00'),
(9, 'timezone', 'Asia/Bangkok', 'system', 'ເຂດເວລາ', 'text', '', '2025-06-09 00:57:00'),
(10, 'maintenance_mode', '1', 'system', 'ໂຫມດບໍາລຸງຮັກສາ', 'checkbox', '', '2025-06-09 00:57:42'),
(11, 'mail_driver', 'smtp', 'email', 'ຕົວຂັບເຄື່ອນອີເມລ', 'select', 'smtp,mail,sendmail', '2025-06-09 00:57:00'),
(12, 'mail_host', 'smtp.example.com', 'email', 'SMTP Host', 'text', '', '2025-06-09 00:57:00'),
(13, 'mail_port', '587', 'email', 'SMTP Port', 'number', '', '2025-06-09 00:57:00'),
(14, 'mail_username', '', 'email', 'SMTP Username', 'text', '', '2025-06-09 00:57:00'),
(15, 'mail_password', '', 'email', 'SMTP Password', 'password', '', '2025-06-09 00:57:00'),
(16, 'mail_encryption', 'tls', 'email', 'SMTP Encryption', 'select', 'tls,ssl,', '2025-06-09 00:57:00'),
(17, 'mail_from_address', 'noreply@example.com', 'email', 'ອີເມລຜູ້ສົ່ງ', 'email', '', '2025-06-09 00:57:00'),
(18, 'mail_from_name', 'ລະບົບຈັດການວັດ', 'email', 'ຊື່ຜູ້ສົ່ງ', 'text', '', '2025-06-09 00:57:00'),
(19, 'password_min_length', '8', 'security', 'ຄວາມຍາວຂັ້ນຕ່ຳຂອງລະຫັດຜ່ານ', 'number', '', '2025-06-09 00:57:00'),
(20, 'password_require_special', '1', 'security', 'ຕ້ອງການຕົວອັກສອນພິເສດໃນລະຫັດຜ່ານ', 'checkbox', '', '2025-06-09 00:57:00'),
(21, 'password_require_number', '1', 'security', 'ຕ້ອງການຕົວເລກໃນລະຫັດຜ່ານ', 'checkbox', '', '2025-06-09 00:57:00'),
(22, 'password_require_uppercase', '1', 'security', 'ຕ້ອງການຕົວອັກສອນໃຫຍ່ໃນລະຫັດຜ່ານ', 'checkbox', '', '2025-06-09 00:57:00'),
(23, 'session_lifetime', '120', 'security', 'ເວລາໝົດອາຍຸຂອງເຊສຊັນ (ນາທີ)', 'number', '', '2025-06-09 00:57:00'),
(24, 'enable_2fa', '0', 'security', 'ເປີດໃຊ້ການຢືນຢັນສອງຂັ້ນຕອນ', 'checkbox', '', '2025-06-09 00:57:00'),
(25, 'allow_registration', '1', 'registration', 'ອະນຸຍາດໃຫ້ລົງທະບຽນຜູ່ໃຊ້ໃໝ່', 'checkbox', '', '2025-06-15 10:09:19'),
(26, 'default_user_role', 'user', 'registration', 'ບົດບາດເລີ່ມຕົ້ນຂອງຜູ່ໃຊ້ໃໝ່', 'select', 'user,admin', '2025-06-09 00:57:00'),
(27, 'require_email_verification', '1', 'registration', 'ຕ້ອງການການຢືນຢັນອີເມລ', 'checkbox', '', '2025-06-09 00:57:00'),
(28, 'max_login_attempts', '5', 'registration', 'ຈໍານວນສູງສຸດຂອງການພະຍາຍາມເຂົ້າສູ່ລະບົບ', 'number', '', '2025-06-09 00:57:00'),
(29, 'lockout_time', '30', 'registration', 'ເວລາລັອກ (ນາທີ)', 'number', '', '2025-06-09 00:57:00');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message_id` varchar(64) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `status` varchar(20) DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `temple_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `status` enum('active','expired','cancelled','pending') NOT NULL DEFAULT 'pending',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_months` int(11) NOT NULL DEFAULT 1,
  `features` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `description`, `price`, `duration_months`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ໄລຍະເວລາ 3 ເດືອນ', '', 100000.00, 3, 'ຈັດການພຣະສົງ', 'active', '2025-06-09 01:20:00', '2025-06-09 06:40:12'),
(2, 'ແພກເກັດ 6 ເດືອນ', '', 200000.00, 6, 'ຈັດການພຣະສົງ\r\nສ້າງກິດຈະກຳ', 'active', '2025-06-09 01:46:55', '2025-06-09 06:39:57'),
(3, 'ແພກເກດ 12 ເດືອນ', '12 ເດືອນ', 300000.00, 12, 'ສ້າງກິດຈະກຳ\r\nເພີ່ມພຣະສົງ\r\nສ້າງລາຍງານ', 'active', '2025-06-09 01:48:02', '2025-06-09 06:38:27');

-- --------------------------------------------------------

--
-- Table structure for table `temples`
--

CREATE TABLE `temples` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `founding_date` date DEFAULT NULL,
  `abbot_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `province_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temples`
--

INSERT INTO `temples` (`id`, `name`, `address`, `phone`, `email`, `website`, `founding_date`, `abbot_name`, `description`, `photo`, `logo`, `latitude`, `longitude`, `status`, `created_at`, `updated_at`, `province_id`, `district_id`) VALUES
(25, 'ວັດສີຖານເໜືອ', 'ບ້ານສີຖານເໜືອ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485436_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 05:57:16', '2025-06-21 05:57:16', 1, 4),
(26, 'ວັດສີມຸງຄຸນຊຽງແວ່', 'ບ້ານສີຖານເໜືອ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485458_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 05:57:38', '2025-06-21 05:57:38', 1, 4),
(27, 'ວັດຫົວເມືອງ ນະຄຣາຣາມ', 'ບ້ານສີຖານເໜືອ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485480_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 05:58:00', '2025-06-21 05:58:00', 1, 4),
(28, 'ວັດຂຸນຕາ ວະຣາຣາມ', 'ບ້ານຂຸນຕາທ່າ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485506_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 05:58:26', '2025-06-21 05:58:26', 1, 4),
(29, 'ວັດອູບມຸງຄາຣາມ', 'ບ້ານອູບມຸງ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485588_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 05:59:48', '2025-06-21 05:59:48', 1, 4),
(30, 'ວັດໄຕນ້ອຍ ຣັດຕະນະມຸງຄຸນ', 'ບ້ານໜອງປາໃນ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485611_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:00:11', '2025-06-21 06:00:11', 1, 4),
(31, 'ວັດໄຕໃຫຍ່ຂັນທະຣາມ', 'ບ້ານໄຕໃຫຍ່ທ່າ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485633_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:00:33', '2025-06-21 06:00:33', 1, 4),
(32, 'ວັດສິຣິວັດທະນາຣາມ', 'ບ້ານໜອງດ້ວງໃຕ້', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485660_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:01:00', '2025-06-21 06:01:00', 1, 4),
(33, 'ວັດໂຊກໄຊ ມຸງຄຸນໄຊຍະຣາມ', 'ບ້ານດົງນາໂຊກເໜືອ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485682_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:01:22', '2025-06-21 06:01:22', 1, 4),
(34, 'ວັດຈອມໄຕຣ ໄຊມຸງຄຸນຍາຣາມ', 'ບ້ານດົງນາໂຊກໃຕ້', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485703_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:01:43', '2025-06-21 06:01:43', 1, 4),
(35, 'ວັດຕົ້ນຕະກູນສີຊົມຊື່ນ ນາຄາຣາມ', 'ບ້ານໜອງບົວທອງເໜືອ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485757_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:02:37', '2025-06-21 06:02:37', 1, 4),
(36, 'ວັດມະຫາຣຸກຂະ ວະຣາຣາມ (ປ່າໜອງບົວທອງໃຕ້)', 'ບ້ານໜອງບົວທອງໃຕ້', '', '', 'http://laotemples.com', NULL, '', '', NULL, NULL, NULL, NULL, 'active', '2025-06-21 06:03:09', '2025-06-21 06:19:15', 1, 4),
(37, 'ວັດວຽງແກ້ວ ສຸດຊາດາຣາມ', 'ບ້ານໂພນຄໍາ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485807_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:03:27', '2025-06-21 06:03:27', 1, 4),
(38, 'ວັດປາກທ້າງໃຕ້', 'ບ້ານປາກທ້າງ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485835_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:03:55', '2025-06-21 06:03:55', 1, 4),
(39, 'ວັດປາກທ້າງເໜືອ', 'ບ້ານປາກທ້າງ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485857_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:04:17', '2025-06-21 06:04:17', 1, 4),
(40, 'ວັດເມືອງວາ ໂພທິຍາຣາມ', 'ບ້ານສີໄຄ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485876_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:04:36', '2025-06-21 06:04:36', 1, 4),
(41, 'ວັດສີໄຄ ໄຊຍະຣາມ', 'ບ້ານສີໄຄ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485894_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:04:54', '2025-06-21 06:04:54', 1, 4),
(42, 'ວັດຍາພະ ໂພທິຍາຣາມ', 'ບ້ານຍາພະ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485913_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:05:13', '2025-06-21 06:05:13', 1, 4),
(43, 'ວັດສີບຸນເຮືອງຄົງຄາ ວະຣາຣາມ', 'ບ້ານສີບຸນເຮືອງ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485934_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:05:34', '2025-06-21 06:52:42', 1, 4),
(44, 'ວັດແກ້ວປ່າ ໄຊຍາຣາມ', 'ບ້ານວຽງສະຫວັນ', '', '', 'http://laotemples.com', NULL, '', '', 'uploads/temples/1750485951_ລົງທະບຽນຮຽນ.png', NULL, NULL, NULL, 'active', '2025-06-21 06:05:51', '2025-06-21 06:05:51', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `temple_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin','user','province_admin') DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','pending','inactive') NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `temple_id`, `username`, `password`, `name`, `role`, `created_at`, `email`, `phone`, `status`, `updated_at`, `reset_token`, `reset_token_expires`) VALUES
(3, NULL, 'superadmin', '$2y$10$SlcJArPV9WfAK30C3ysa4OYoppYRek8V0wlNmTjWFtFy9gnYsac1m', 'Super Admin', 'superadmin', '2025-06-08 19:51:20', 'phathasyla@gmail.com', '77772338', 'active', '2025-06-17 14:21:13', '0f2ebbcb52fe7761ffe2153239355e707a8bd1c466c056ef24890a54ff010735', '2025-06-18 09:55:21'),
(12, NULL, 'thonglun', '$2y$10$54LL5HFaaDd23l4aLNm6xO.HydxFrqflfNOMZpGNePF2TD8yJXRr2', 'thonglunsy', 'province_admin', '2025-06-11 23:58:28', 'thonlun@gmail.com', '65653212', 'active', '2025-06-20 06:05:52', NULL, NULL),
(18, NULL, 'superadmin2', '$2y$10$iO7sdZAThzMO0Ti72mEHWO4hOktnnrBPsScyAvVhS.EK1TFu0kCjq', 'super', 'province_admin', '2025-06-17 21:00:51', 'sup@gmail.com', '123456785', 'active', '2025-06-20 07:13:07', NULL, NULL),
(19, NULL, 'Nong', '$2y$10$i9sSt1Q7zQTn201tgPGwZuv.vGwMJNcJoMtMpZBcdz6kVQiF7VEYq', 'nong', 'province_admin', '2025-06-20 08:41:56', 'nong@gmail.com', '2012325645', 'active', '2025-06-21 06:31:31', NULL, NULL),
(20, NULL, 'von1', '$2y$10$X114XPOFVKtngC53ojbVK.zwWg1jDIRVzjrEpjEKvlyM0DUQlYOY6', 'von1', 'province_admin', '2025-06-20 10:48:00', 'von1@gmail.com', '123456897', 'active', '2025-06-21 00:19:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_province_access`
--

CREATE TABLE `user_province_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user_province_access`
--

INSERT INTO `user_province_access` (`id`, `user_id`, `province_id`, `assigned_by`, `assigned_at`) VALUES
(8, 12, 7, 3, '2025-06-20 06:05:52'),
(9, 18, 1, 3, '2025-06-20 07:13:07'),
(29, 20, 7, 3, '2025-06-21 00:19:21'),
(47, 19, 8, 3, '2025-06-21 06:31:31'),
(48, 19, 3, 3, '2025-06-21 06:31:31'),
(49, 19, 11, 3, '2025-06-21 06:31:31'),
(50, 19, 1, 3, '2025-06-21 06:31:31'),
(51, 19, 16, 3, '2025-06-21 06:31:31'),
(52, 19, 14, 3, '2025-06-21 06:31:31'),
(53, 19, 17, 3, '2025-06-21 06:31:31'),
(54, 19, 15, 3, '2025-06-21 06:31:31'),
(55, 19, 4, 3, '2025-06-21 06:31:31'),
(56, 19, 9, 3, '2025-06-21 06:31:31'),
(57, 19, 10, 3, '2025-06-21 06:31:31'),
(58, 19, 5, 3, '2025-06-21 06:31:31'),
(59, 19, 2, 3, '2025-06-21 06:31:31'),
(60, 19, 12, 3, '2025-06-21 06:31:31'),
(61, 19, 6, 3, '2025-06-21 06:31:31'),
(62, 19, 13, 3, '2025-06-21 06:31:31'),
(63, 19, 7, 3, '2025-06-21 06:31:31'),
(64, 19, 18, 3, '2025-06-21 06:31:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`district_id`),
  ADD KEY `province_id` (`province_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temple_id` (`temple_id`);

--
-- Indexes for table `event_monk`
--
ALTER TABLE `event_monk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `monk_id` (`monk_id`);

--
-- Indexes for table `monks`
--
ALTER TABLE `monks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temple_id` (`temple_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`province_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phone` (`phone`),
  ADD KEY `sent_at` (`sent_at`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `temple_id` (`temple_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscription_payment` (`subscription_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `temples`
--
ALTER TABLE `temples`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_id` (`province_id`),
  ADD KEY `district_id` (`district_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temple_id` (`temple_id`);

--
-- Indexes for table `user_province_access`
--
ALTER TABLE `user_province_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `province_id` (`province_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `district_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event_monk`
--
ALTER TABLE `event_monk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `monks`
--
ALTER TABLE `monks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດ', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `province_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `temples`
--
ALTER TABLE `temples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_province_access`
--
ALTER TABLE `user_province_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `districts`
--
ALTER TABLE `districts`
  ADD CONSTRAINT `districts_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`province_id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);

--
-- Constraints for table `event_monk`
--
ALTER TABLE `event_monk`
  ADD CONSTRAINT `event_monk_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `event_monk_ibfk_2` FOREIGN KEY (`monk_id`) REFERENCES `monks` (`id`);

--
-- Constraints for table `monks`
--
ALTER TABLE `monks`
  ADD CONSTRAINT `monks_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`),
  ADD CONSTRAINT `subscriptions_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`);

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `fk_subscription_payment` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `temples`
--
ALTER TABLE `temples`
  ADD CONSTRAINT `temples_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`province_id`),
  ADD CONSTRAINT `temples_ibfk_2` FOREIGN KEY (`district_id`) REFERENCES `districts` (`district_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);

--
-- Constraints for table `user_province_access`
--
ALTER TABLE `user_province_access`
  ADD CONSTRAINT `user_province_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_province_access_ibfk_2` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`province_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_province_access_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
