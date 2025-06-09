-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2025 at 08:44 AM
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
  `id` int(11) NOT NULL COMMENT 'ລະຫັດ',
  `name` varchar(255) NOT NULL COMMENT 'ຊື່ພຣະສົງ',
  `lay_name` varchar(255) DEFAULT NULL COMMENT 'ຊື່ຄົນທົ່ວໄປກ່ອນບວດ',
  `pansa` int(11) NOT NULL COMMENT 'ຈໍານວນພັນສາ',
  `birth_date` date DEFAULT NULL COMMENT 'ວັນເກີດ',
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
(3, 'admin_email', 'admin@example.com', 'general', 'ອີເມລຜູ້ດູແລລະບົບ', 'email', '', '2025-06-09 00:57:00'),
(4, 'contact_phone', '', 'general', 'ເບີໂທຕິດຕໍ່', 'text', '', '2025-06-09 00:57:00'),
(5, 'footer_text', '© 2025 ລະບົບຈັດການວັດ. ສະຫງວນລິຂະສິດ.', 'general', 'ຂໍ້ຄວາມສ່ວນລຸ່ມເວັບໄຊທ໌', 'textarea', '', '2025-06-09 00:57:00'),
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
(25, 'allow_registration', '0', 'registration', 'ອະນຸຍາດໃຫ້ລົງທະບຽນຜູ່ໃຊ້ໃໝ່', 'checkbox', '', '2025-06-09 00:57:00'),
(26, 'default_user_role', 'user', 'registration', 'ບົດບາດເລີ່ມຕົ້ນຂອງຜູ່ໃຊ້ໃໝ່', 'select', 'user,admin', '2025-06-09 00:57:00'),
(27, 'require_email_verification', '1', 'registration', 'ຕ້ອງການການຢືນຢັນອີເມລ', 'checkbox', '', '2025-06-09 00:57:00'),
(28, 'max_login_attempts', '5', 'registration', 'ຈໍານວນສູງສຸດຂອງການພະຍາຍາມເຂົ້າສູ່ລະບົບ', 'number', '', '2025-06-09 00:57:00'),
(29, 'lockout_time', '30', 'registration', 'ເວລາລັອກ (ນາທີ)', 'number', '', '2025-06-09 00:57:00');

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

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `temple_id`, `plan_id`, `status`, `start_date`, `end_date`, `amount`, `payment_method`, `payment_reference`, `notes`, `created_at`, `updated_at`) VALUES
(2, 8, 18, 3, 'active', '2025-06-09', '2027-06-09', 300000.00, 'qr_payment', '1422414', '3222', '2025-06-09 11:35:50', '2025-06-09 13:25:39');

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

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `subscription_id`, `amount`, `payment_date`, `payment_method`, `payment_proof`, `status`, `notes`, `created_at`, `updated_at`, `processed_by`, `processed_at`) VALUES
(2, 2, 300000.00, '2025-06-09 07:23:00', 'mobile_banking', 'uploads/payments/payment_1749446701_6846702db9abd.png', 'approved', 'ປະຕິເສດ: ບໍ່ຜ່ານເງື່ອນໄຂ\r\nປະຕິເສດ: ບໍ່ຜ່ານເງື່ອນໄຂ', '2025-06-09 12:25:01', '2025-06-09 13:19:54', 3, '2025-06-09 13:19:54'),
(3, 2, 300000.00, '2025-06-09 08:25:39', 'mobile_banking', 'uploads/payments/payment_1749450339_68467e63d98cc.png', 'approved', '', '2025-06-09 13:25:39', '2025-06-09 13:25:39', NULL, NULL);

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
(17, 'ວັດພຣະທາດຫຼວງ', 'ຖະໜົນ ທາດຫຼວງ', 'ໄຊເສດຖາ', 'ນະຄອນຫຼວງວຽງຈັນ', '021-123456', 'contact@thathluang.la', 'https://thathluang.la', '1566-01-01', 'ພຣະຄູ່ປະສານທ່ານໃຫຍ່', 'ວັດໃຫຍ່ທີ່ມີປະຫວັດຫຼາຍຮ້ອຍປີ', 'uploads/temples/1749443453_a5768f1d-702e-4c05-955f-65becbf245a1.jpeg', 'uploads/temples/1749443461_logo_3.png', 17.97010900, 102.61861800, 'active', '2025-06-09 04:13:12', '2025-06-09 04:31:01'),
(18, 'ວັດຊຽງທອງ', 'ຖະໜົນ ຊຽງທອງ', 'ປາກຂອງ', 'ຫຼວງພຣະບາງ', '071-987654', 'info@xiengthong.la', 'https://xiengthong.la', '1560-05-20', 'ພຣະຄູ່ອຸດົມວົງ', 'ວັດເກົ່າແກ່ສະຫງ່າງາມໃນຫຼວງພຣະບາງ', 'uploads/temples/1749443420_Untitled-1.png', 'uploads/temples/1749443409_logo_L0GONOMGBOUTHONGTEMPLE4CMX6.18CM2.png', 19.89774500, 102.14227400, 'active', '2025-06-09 04:13:12', '2025-06-09 04:30:20'),
(19, 'ວັດພຸດທະພາດ', 'ບ້ານ ພຸດທະພາດ', 'ໄຊຍະບູລີ', 'ໄຊຍະບູລີ', '074-222111', 'buddhapad@temple.la', '', NULL, 'ພຣະຄູ່ຈັນທະວົງ', 'ວັດໃນເມືອງໄຊຍະບູລີທີ່ມີການສອນພຣະພິກສຸ', 'uploads/temples/1749443505_1.png', 'uploads/temples/1749443505_logo_border.png', 19.40305800, 101.76748200, 'active', '2025-06-09 04:13:12', '2025-06-09 04:31:45'),
(20, 'ວັດໂພນສະຫວາດ', 'ບ້ານ ໂພນສະຫວາດ', 'ຫາດສາຍຟອງ', 'ຄໍາມ່ວນ', '051-334455', NULL, NULL, NULL, 'ພຣະຄູ່ສີວິວິມົນ', 'ວັດຊົນບົດໃນເຂດເມືອງຫາດສາຍຟອງ', NULL, NULL, 17.38946000, 104.80770800, 'active', '2025-06-09 04:13:12', '2025-06-09 04:13:12'),
(21, 'ວັດໂພນທອງ', 'ຖະໜົນ ວັດໂພນທອງ', 'ເມືອງໄຊ', 'ອຸດົມໄຊ', '075-778899', 'phonthong@temple.la', NULL, NULL, 'ພຣະຄູ່ປັນຍາສາລາ', 'ວັດທີ່ມີພະໄວຮຽນສຶກສາທາງທຳ', NULL, NULL, 20.68885000, 101.99203400, 'active', '2025-06-09 04:13:12', '2025-06-09 04:13:12');

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
  `created_at` datetime DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `temple_id`, `username`, `password`, `name`, `role`, `created_at`, `email`, `phone`) VALUES
(3, NULL, 'superadmin', 'ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f', 'Super Admin', 'superadmin', '2025-06-08 19:51:20', 'phathasyla@gmail.com', '77772338'),
(8, 18, 'user1', '$2y$10$lhUi791hP54bg6a23T65wu5.j.Q2I36ZTTFAhBB76tKsm8a94tMt2', 'user1', 'admin', '2025-06-09 11:34:57', 'user@gmail.com', '123456789');

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
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_monk`
--
ALTER TABLE `event_monk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `monks`
--
ALTER TABLE `monks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດ', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `temples`
--
ALTER TABLE `temples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`temple_id`) REFERENCES `temples` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
