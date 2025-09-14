-- Create notifications table for the temple management system
-- This will store in-app notifications

USE `db_temples`;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'ຜູ້ຮັບການແຈ້ງເຕືອນ',
  `from_user_id` int(11) DEFAULT NULL COMMENT 'ຜູ້ສົ່ງການແຈ້ງເຕືອນ',
  `type` enum('user_approved','user_rejected','user_suspended','monk_added','monk_updated','temple_updated','system_notification') NOT NULL DEFAULT 'system_notification',
  `title` varchar(255) NOT NULL COMMENT 'ຫົວຂໍ້ການແຈ້ງເຕືອນ',
  `message` text NOT NULL COMMENT 'ຂໍ້ຄວາມການແຈ້ງເຕືອນ',
  `data` json DEFAULT NULL COMMENT 'ຂໍ້ມູນເພີ່ມເຕີມ',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ອ່ານແລ້ວຫຼືຍັງ',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'ເວລາທີ່ອ່ານ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notifications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_from_user_id_fk` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ຕາຕະລາງການແຈ້ງເຕືອນ';

-- Create notification_settings table for user preferences
CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ເປີດການແຈ້ງເຕືອນ SMS',
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ເປີດການແຈ້ງເຕືອນ Email',
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ເປີດການແຈ້ງເຕືອນ Push',
  `user_approval_sms` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'SMS ເມື່ອຜູ້ໃຊ້ຖືກອະນຸມັດ',
  `user_approval_push` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Push ເມື່ອຜູ້ໃຊ້ຖືກອະນຸມັດ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `notification_settings_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ການຕັ້ງຄ່າການແຈ້ງເຕືອນຂອງຜູ້ໃຊ້';

-- Insert default notification settings for existing users
INSERT IGNORE INTO `notification_settings` (`user_id`, `sms_enabled`, `email_enabled`, `push_enabled`, `user_approval_sms`, `user_approval_push`)
SELECT `id`, 1, 1, 1, 1, 1 FROM `users`;
