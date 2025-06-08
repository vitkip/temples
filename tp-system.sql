-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2025 at 05:44 PM
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
  `id` int(11) NOT NULL,
  `temple_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `ordination_date` date DEFAULT NULL,
  `pansa` int(11) DEFAULT NULL,
  `status` enum('active','left','deceased') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `temple_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `slip_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temples`
--

CREATE TABLE `temples` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temples`
--

INSERT INTO `temples` (`id`, `name`, `address`, `district`, `province`, `phone`, `email`, `website`, `founding_date`, `abbot_name`, `description`, `photo`, `logo`, `latitude`, `longitude`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ວັດພຣະທາດຫຼວງ', 'ຖະໜົນ ທາດຫຼວງ', 'ໄຊເສດຖາ', 'ນະຄອນຫຼວງວຽງຈັນ', '021-123456', 'contact@thathluang.la', 'https://thathluang.la', '1566-01-01', 'ພຣະຄູ່ປະສານທ່ານໃຫຍ່', 'ວັດໃຫຍ່ທີ່ມີປະຫວັດຫຼາຍຮ້ອຍປີ', NULL, NULL, 17.97010900, 102.61861800, 'active', '2025-05-31 17:33:14', '2025-05-31 17:33:14'),
(2, 'ວັດຊຽງທອງ', 'ຖະໜົນ ຊຽງທອງ', 'ປາກຂອງ', 'ຫຼວງພຣະບາງ', '071-987654', 'info@xiengthong.la', 'https://xiengthong.la', '1560-05-20', 'ພຣະຄູ່ອຸດົມວົງ', 'ວັດເກົ່າແກ່ສະຫງ່າງາມໃນຫຼວງພຣະບາງ', NULL, NULL, 19.89774500, 102.14227400, 'active', '2025-05-31 17:33:14', '2025-05-31 17:33:14'),
(3, 'ວັດພຸດທະພາດ', 'ບ້ານ ພຸດທະພາດ', 'ໄຊຍະບູລີ', 'ໄຊຍະບູລີ', '074-222111', 'buddhapad@temple.la', NULL, NULL, 'ພຣະຄູ່ຈັນທະວົງ', 'ວັດໃນເມືອງໄຊຍະບູລີທີ່ມີການສອນພຣະພິກສຸ', NULL, NULL, 19.40305800, 101.76748200, 'active', '2025-05-31 17:33:14', '2025-05-31 17:33:14'),
(4, 'ວັດໂພນສະຫວາດ', 'ບ້ານ ໂພນສະຫວາດ', 'ຫາດສາຍຟອງ', 'ຄໍາມ່ວນ', '051-334455', NULL, NULL, NULL, 'ພຣະຄູ່ສີວິວິມົນ', 'ວັດຊົນບົດໃນເຂດເມືອງຫາດສາຍຟອງ', NULL, NULL, 17.38946000, 104.80770800, 'active', '2025-05-31 17:33:14', '2025-05-31 17:33:14'),
(5, 'ວັດໂພນທອງ', 'ຖະໜົນ ວັດໂພນທອງ', 'ເມືອງໄຊ', 'ອຸດົມໄຊ', '075-778899', 'phonthong@temple.la', NULL, NULL, 'ພຣະຄູ່ປັນຍາສາລາ', 'ວັດທີ່ມີພະໄວຮຽນສຶກສາທາງທຳ', NULL, NULL, 20.68885000, 101.99203400, 'active', '2025-05-31 17:33:14', '2025-05-31 17:33:14');

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
  `role` enum('superadmin','admin','user') DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `temple_id`, `username`, `password`, `name`, `role`, `created_at`) VALUES
(1, NULL, 'superadmin', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 'Super Admin', 'superadmin', '2025-05-31 21:06:26'),
(2, NULL, 'super', 'ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f', 'Super Admin', 'superadmin', '2025-05-31 21:51:40');

--
-- Indexes for dumped tables
--

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
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temple_id` (`temple_id`);

--
-- Indexes for table `temples`
--
ALTER TABLE `temples`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `temple_id` (`temple_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_monk`
--
ALTER TABLE `event_monk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monks`
--
ALTER TABLE `monks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temples`
--
ALTER TABLE `temples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `monks_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
