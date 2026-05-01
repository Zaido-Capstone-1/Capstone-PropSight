-- ============================================================
--  PropSight — COMPLETE DATABASE SCHEMA
--  Generated: 2026-03-25
--  Includes all original tables + missing tables/fields
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Drop all tables in safe order (child → parent)
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `support_tickets`;
DROP TABLE IF EXISTS `support_messages`;
DROP TABLE IF EXISTS `loyalty_points`;
DROP TABLE IF EXISTS `loyalty_redemptions`;
DROP TABLE IF EXISTS `saved_units`;
-- ============================================================
-- MIGRATION: run this on existing databases to add attachment support
-- ALTER TABLE `messages` ADD COLUMN `attachment_url` VARCHAR(500) DEFAULT NULL AFTER `body`;
-- ALTER TABLE `bookings` ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL AFTER `paid_at`;
-- ============================================================


DROP TABLE IF EXISTS `user_settings`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `unit_amenities`;
DROP TABLE IF EXISTS `unit_images`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `blocked_dates`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `amenities`;
DROP TABLE IF EXISTS `units`;
DROP TABLE IF EXISTS `properties`;
DROP TABLE IF EXISTS `tenants`;
DROP TABLE IF EXISTS `financial_reports`;
DROP TABLE IF EXISTS `maintenance_requests`;
DROP TABLE IF EXISTS `admin_settings`;
DROP TABLE IF EXISTS `staff_permissions`;
DROP TABLE IF EXISTS `otp_tokens`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `user_id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `first_name`          VARCHAR(100)  NOT NULL,
  `last_name`           VARCHAR(100)  NOT NULL,
  `email`               VARCHAR(100)  NOT NULL,
  `phone`               VARCHAR(20)   DEFAULT NULL,
  `nationality`         VARCHAR(100)  DEFAULT NULL,
  `birthday`            VARCHAR(100)  DEFAULT NULL,
  `gender`              ENUM('Male','Female','Prefer not to say') DEFAULT NULL,
  `password`            VARCHAR(255)  NOT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role`                ENUM('user','admin','manager','frontdesk','accounting','maintenance') NOT NULL DEFAULT 'user',
  `verification_status` ENUM('Not Verified','Verified') NOT NULL DEFAULT 'Not Verified',
  `login_attempts`      INT(11)       DEFAULT 0,
  `last_attempt`        DATETIME      DEFAULT NULL,
  `is_locked`           TINYINT(1)    DEFAULT 0,
  `locked_until`        DATETIME      DEFAULT NULL,
  `is_blacklisted`      TINYINT(1)    NOT NULL DEFAULT 0,
  `is_active`           TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login`          DATETIME      DEFAULT NULL,
  `profile_photo`       VARCHAR(500)  DEFAULT NULL,
  `address`             TEXT          DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- otp_tokens (for 2FA login)
-- --------------------------------------------------------
CREATE TABLE `otp_tokens` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `otp_code`   VARCHAR(10)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- properties
-- --------------------------------------------------------
CREATE TABLE `properties` (
  `property_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `property_name` VARCHAR(150) NOT NULL,
  `address`       TEXT         DEFAULT NULL,
  `city`          VARCHAR(100) DEFAULT NULL,
  `state`         VARCHAR(100) DEFAULT NULL,
  `zip`           VARCHAR(20)  DEFAULT NULL,
  `status`        VARCHAR(20)  DEFAULT 'Active',
  `property_type` VARCHAR(50)  DEFAULT NULL,
  `description`   TEXT         DEFAULT NULL,
  `photo`         VARCHAR(500) DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- tenants
-- --------------------------------------------------------
CREATE TABLE `tenants` (
  `tenant_id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`    VARCHAR(100) NOT NULL,
  `phone`        VARCHAR(20)  DEFAULT NULL,
  `email`        VARCHAR(100) DEFAULT NULL,
  `move_in_date` DATE         DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- units
-- --------------------------------------------------------
CREATE TABLE `units` (
  `unit_id`      INT(11)        NOT NULL AUTO_INCREMENT,
  `property_id`  INT(11)        DEFAULT NULL,
  `unit_number`  VARCHAR(50)    DEFAULT NULL,
  `unit_name`    VARCHAR(100)   DEFAULT NULL,
  `unit_type`    VARCHAR(50)    DEFAULT NULL,
  `floor`        INT(11)        DEFAULT NULL,
  `rent_amount`  DECIMAL(10,2)  DEFAULT NULL,
  `status`       ENUM('occupied','vacant','maintenance') DEFAULT 'vacant',
  `tenant_name`  VARCHAR(100)   DEFAULT NULL,
  `description`  TEXT           DEFAULT NULL,
  `max_guests`   INT(3)         NOT NULL DEFAULT 2,
  `bedrooms`     INT(3)         DEFAULT 1,
  `bathrooms`    INT(3)         DEFAULT 1,
  `area_sqm`     DECIMAL(8,2)   DEFAULT NULL,
  `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  KEY `property_id` (`property_id`),
  CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- amenities
-- --------------------------------------------------------
CREATE TABLE `amenities` (
  `amenity_id`  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` INT(11)          NOT NULL,
  `name`        VARCHAR(120)     NOT NULL,
  `icon`        VARCHAR(30)      NOT NULL DEFAULT 'security',
  `status`      ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`amenity_id`),
  KEY `idx_property` (`property_id`),
  CONSTRAINT `fk_amenities_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- unit_amenities
-- --------------------------------------------------------
CREATE TABLE `unit_amenities` (
  `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`    INT(10) UNSIGNED NOT NULL,
  `amenity_id` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_amenity` (`unit_id`,`amenity_id`),
  KEY `fk_ua_unit`    (`unit_id`),
  KEY `fk_ua_amenity` (`amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- unit_images
-- --------------------------------------------------------
CREATE TABLE `unit_images` (
  `image_id`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`    INT(11)          NOT NULL,
  `image_path` VARCHAR(500)     NOT NULL,
  `sort_order` TINYINT(3) UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_unit` (`unit_id`),
  CONSTRAINT `fk_unit_images` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- bookings  (EXPANDED with payment fields)
-- --------------------------------------------------------
CREATE TABLE `bookings` (
  `booking_id`       INT(11)        NOT NULL AUTO_INCREMENT,
  `unit_id`          INT(11)        NOT NULL,
  `tenant_id`        INT(11)        NOT NULL DEFAULT 1,
  `user_id`          INT(11)        NOT NULL,
  `checkin_date`     DATE           NOT NULL,
  `checkout_date`    DATE           NOT NULL,
  `guests`           INT(3)         NOT NULL DEFAULT 1,
  `total_amount`     DECIMAL(10,2)  NOT NULL,
  `status`           ENUM('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  `special_requests` TEXT           DEFAULT NULL,
  `payment_method`   VARCHAR(50)    DEFAULT NULL,
  `payment_ref`      VARCHAR(100)   DEFAULT NULL,
  `payment_notes`    TEXT           DEFAULT NULL,
  `paid_at`          DATETIME       DEFAULT NULL,
  `confirmed_at`     DATETIME       DEFAULT NULL,
  `checkin_status`   ENUM('pending','done') NOT NULL DEFAULT 'pending',
  `checkout_status`  ENUM('pending','done') NOT NULL DEFAULT 'pending',
  `checkin_actual`   DATETIME       DEFAULT NULL,
  `checkout_actual`  DATETIME       DEFAULT NULL,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `fk_booking_unit`   (`unit_id`),
  KEY `fk_booking_tenant` (`tenant_id`),
  KEY `fk_booking_user`   (`user_id`),
  CONSTRAINT `fk_booking_unit`   FOREIGN KEY (`unit_id`)   REFERENCES `units`   (`unit_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_booking_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`   (`user_id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- payments  (EXPANDED with booking_id FK)
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `payment_id`     INT(11)        NOT NULL AUTO_INCREMENT,
  `booking_id`     INT(11)        DEFAULT NULL,
  `payment_date`   DATE           DEFAULT NULL,
  `amount_paid`    DECIMAL(10,2)  DEFAULT NULL,
  `payment_method` VARCHAR(50)    DEFAULT NULL,
  `payment_status` ENUM('paid','pending','late') DEFAULT 'pending',
  `notes`          TEXT           DEFAULT NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_booking` (`booking_id`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- transactions  (NEW — general ledger for finance module)
-- --------------------------------------------------------
CREATE TABLE `transactions` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `reference_no`     VARCHAR(50)    NOT NULL,
  `description`      VARCHAR(255)   DEFAULT NULL,
  `category`         VARCHAR(100)   DEFAULT NULL,
  `type`             ENUM('Income','Expense') NOT NULL,
  `amount`           DECIMAL(12,2)  NOT NULL,
  `transaction_date` DATE           NOT NULL,
  `property_id`      INT(11)        DEFAULT NULL,
  `booking_id`       INT(11)        DEFAULT NULL,
  `recorded_by`      INT(11)        DEFAULT NULL,
  `notes`            TEXT           DEFAULT NULL,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `booking_id`  (`booking_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- expenses
-- --------------------------------------------------------
CREATE TABLE `expenses` (
  `expense_id`       INT(11)       NOT NULL AUTO_INCREMENT,
  `property_id`      INT(11)       DEFAULT NULL,
  `expense_category` VARCHAR(100)  DEFAULT NULL,
  `description`      TEXT          DEFAULT NULL,
  `amount`           DECIMAL(10,2) DEFAULT NULL,
  `expense_date`     DATE          DEFAULT NULL,
  `recorded_by`      INT(11)       DEFAULT NULL,
  PRIMARY KEY (`expense_id`),
  KEY `property_id` (`property_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users`      (`user_id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- financial_reports
-- --------------------------------------------------------
CREATE TABLE `financial_reports` (
  `report_id`        INT(11)        NOT NULL AUTO_INCREMENT,
  `report_month`     INT(11)        DEFAULT NULL,
  `report_year`      INT(11)        DEFAULT NULL,
  `total_income`     DECIMAL(12,2)  DEFAULT NULL,
  `total_expenses`   DECIMAL(12,2)  DEFAULT NULL,
  `net_profit`       DECIMAL(12,2)  DEFAULT NULL,
  `generated_by`     INT(11)        DEFAULT NULL,
  `generated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- maintenance_requests
-- --------------------------------------------------------
CREATE TABLE `maintenance_requests` (
  `request_id`        INT(11)  NOT NULL AUTO_INCREMENT,
  `tenant_id`         INT(11)  DEFAULT NULL,
  `unit_id`           INT(11)  DEFAULT NULL,
  `issue_description` TEXT     DEFAULT NULL,
  `request_status`    ENUM('pending','in_progress','completed') DEFAULT 'pending',
  `priority`          ENUM('low','medium','high') DEFAULT 'medium',
  `request_date`      DATE     DEFAULT NULL,
  `resolved_date`     DATE     DEFAULT NULL,
  `notes`             TEXT     DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `unit_id`   (`unit_id`),
  CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_ibfk_2` FOREIGN KEY (`unit_id`)   REFERENCES `units`   (`unit_id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- blocked_dates
-- --------------------------------------------------------
CREATE TABLE `blocked_dates` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocked_date` DATE             NOT NULL,
  `reason`       VARCHAR(255)     DEFAULT NULL,
  `created_by`   INT(10) UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocked_date` (`blocked_date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- activity_logs
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
  `log_id`      INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)    DEFAULT NULL,
  `action`      TEXT       DEFAULT NULL,
  `module`      VARCHAR(50) DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `action_date` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- messages  (NEW — admin/tenant messaging system)
-- --------------------------------------------------------
CREATE TABLE `messages` (
  `message_id`     INT(11)      NOT NULL AUTO_INCREMENT,
  `from_user`      INT(11)      NOT NULL,
  `to_user`        INT(11)      NOT NULL,
  `subject`        VARCHAR(255) DEFAULT NULL,
  `body`           TEXT         NOT NULL,
  `attachment_url` VARCHAR(500) DEFAULT NULL,
  `is_read`        TINYINT(1)   NOT NULL DEFAULT 0,
  `parent_id`      INT(11)      DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `from_user` (`from_user`),
  KEY `to_user`   (`to_user`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- support_tickets  (NEW — user support system)
-- --------------------------------------------------------
CREATE TABLE `support_tickets` (
  `ticket_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `category`    VARCHAR(100) DEFAULT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `priority`    ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `status`      ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- support_messages  (NEW — replies within a ticket)
-- --------------------------------------------------------
CREATE TABLE `support_messages` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `ticket_id`  INT(11)   NOT NULL,
  `user_id`    INT(11)   NOT NULL,
  `body`       TEXT      NOT NULL,
  `is_admin`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id`   (`user_id`),
  CONSTRAINT `fk_smsg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_smsg_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- loyalty_points  (NEW — per-user loyalty tracking)
-- --------------------------------------------------------
CREATE TABLE `loyalty_points` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `points`      INT(11)      NOT NULL DEFAULT 0,
  `type`        ENUM('earn','redeem','bonus','expire') NOT NULL DEFAULT 'earn',
  `description` VARCHAR(255) DEFAULT NULL,
  `booking_id`  INT(11)      DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`   (`user_id`),
  KEY `booking_id`(`booking_id`),
  CONSTRAINT `fk_lp_user`    FOREIGN KEY (`user_id`)   REFERENCES `users`    (`user_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_lp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- saved_units  (NEW — user wishlist)
-- --------------------------------------------------------
CREATE TABLE `saved_units` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)   NOT NULL,
  `unit_id`    INT(11)   NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved` (`user_id`,`unit_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users`  (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`  (`unit_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- payment_methods  (NEW — stored user payment cards/ewallets)
-- --------------------------------------------------------
CREATE TABLE `payment_methods` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)      NOT NULL,
  `type`           ENUM('card','ewallet') NOT NULL DEFAULT 'card',
  `provider`       VARCHAR(50)  NOT NULL,
  `label`          VARCHAR(100) DEFAULT NULL,
  `last4`          VARCHAR(4)   DEFAULT NULL,
  `expiry_month`   INT(2)       DEFAULT NULL,
  `expiry_year`    INT(4)       DEFAULT NULL,
  `holder_name`    VARCHAR(100) DEFAULT NULL,
  `account_number` VARCHAR(20)  DEFAULT NULL,
  `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- user_settings  (NEW — notification/privacy preferences)
-- --------------------------------------------------------
CREATE TABLE `user_settings` (
  `id`                    INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`               INT(11)    NOT NULL,
  `notif_booking_confirm` TINYINT(1) DEFAULT 1,
  `notif_checkin_remind`  TINYINT(1) DEFAULT 1,
  `notif_promotions`      TINYINT(1) DEFAULT 0,
  `notif_loyalty`         TINYINT(1) DEFAULT 1,
  `notif_newsletter`      TINYINT(1) DEFAULT 0,
  `notif_sms`             TINYINT(1) DEFAULT 0,
  `privacy_profile`       ENUM('public','private') DEFAULT 'private',
  `privacy_activity`      TINYINT(1) DEFAULT 0,
  `two_factor_enabled`    TINYINT(1) DEFAULT 0,
  `language`              VARCHAR(10) DEFAULT 'en',
  `timezone`              VARCHAR(50) DEFAULT 'Asia/Manila',
  `updated_at`            TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_settings` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- notifications  (NEW — system notifications)
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `type`       VARCHAR(50)  DEFAULT 'info',
  `title`      VARCHAR(150) NOT NULL,
  `body`       TEXT         DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- admin_settings  (NEW — global system settings)
-- --------------------------------------------------------
CREATE TABLE `admin_settings` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `value`       TEXT         DEFAULT NULL,
  `updated_by`  INT(11)      DEFAULT NULL,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- staff_permissions  (NEW — granular role permissions)
-- --------------------------------------------------------
CREATE TABLE `staff_permissions` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)     NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `granted`    TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_permission` (`user_id`,`permission`),
  CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SEED DATA (matching existing records + enhancements)
-- ============================================================

INSERT INTO `users` (`user_id`,`first_name`,`last_name`,`email`,`phone`,`nationality`,`birthday`,`gender`,`password`,`created_at`,`role`,`verification_status`,`login_attempts`,`last_attempt`,`is_locked`,`locked_until`,`is_blacklisted`,`is_active`,`last_login`) VALUES
(2,'Jr','Marticio','jr@gmail.com','09876543211','Filipino','2004-09-08','Male','$2y$10$SLpufN.25x34BMQG0l.Wou8NQGxCPbxv6T0L0G2kqhrfqByh/rQXa','2026-03-18 14:34:21','user','Not Verified',0,'2026-03-21 21:05:33',0,NULL,0,1,NULL),
(4,'Myra','Jonson','myrajonson@gmail.com','09876543210',NULL,NULL,NULL,'$2y$10$wXIDzETTGkxbTwkIbBaKDOzxIUvFq2DSwEu2AqILaC8rWofz1pZXa','2026-03-20 11:30:54','admin','Not Verified',0,'2026-03-21 21:10:44',0,NULL,0,1,NULL),
(6,'Sonny','Wagas','sonny@phinmaed.com','09324123512',NULL,NULL,NULL,'$2y$10$/Ry3y5remClD8wUdj8/zIuf3CPp8vJqh.3KTFCHrJsu5ZVpvGPazi','2026-03-21 15:28:14','user','Not Verified',0,NULL,0,NULL,0,1,NULL),
(11,'Marlon','Garcia','marlonvillegas00@gmail.com','09497680942',NULL,NULL,NULL,'$2y$10$OifSfqsYgGUCM3s2BWuNCesGD30kEDy35r0gYBjVMVy6zxDmzua8W','2026-03-21 15:45:48','admin','Not Verified',0,'2026-03-21 23:48:57',0,NULL,0,1,NULL),
(12,'Marlon','Pogi','marlonvillegas86@gmail.com','09497695123',NULL,NULL,NULL,'$2y$10$hlRbO5WSPVIa00gygt.uyemTYt6Tx12yb3qejqdWmPu/42hJYEZPO','2026-03-21 15:48:32','user','Not Verified',0,NULL,0,NULL,0,1,NULL),
(13,'Sean','Peniero','sean@gmail.com','09235612571',NULL,NULL,NULL,'$2y$10$RKfNhMcgrvbBLghTdvbgSuyf4qUQq.8xdjaZY3cnbpNUPSqcbmTyS','2026-03-22 05:34:17','user','Not Verified',0,NULL,0,NULL,0,1,NULL),
(14,'John Michael','Arcido','joda.arcido.ui@phinmaed.com','09497695099','Filipino','','Female','$2y$10$.oxDJrUYsLqb2y9yOipeWeAB7rKpVgX9U7.YbYIi9p4XoWWb4BYOC','2026-03-22 11:14:33','user','Not Verified',0,NULL,0,NULL,0,1,NULL);

INSERT INTO `properties` (`property_id`,`property_name`,`address`,`city`,`state`,`zip`,`status`,`property_type`,`created_at`) VALUES
(14,'Casa De Primera','Tulubhan Barangay Manoc-manoc, Boracay Island, Malay, Aklan 5608','Boracay Island, Malay','Aklan','5608','Active','Residential','2026-03-20 18:05:05'),
(15,'Casa Camilla Beachfront','Station 3 Angol Barangay Manoc-manoc, Boracay Island, Malay, Aklan 5608','Boracay Island, Malay','Aklan','5608','Active','Residential','2026-03-22 07:38:11'),
(17,'Roxon Residences','Station 3 Ambulong Barangay Manoc-manoc, Boracay Island, Malay, Aklan 5608','Boracay Island, Malay','Aklan','5608','Active','Residential','2026-03-22 14:01:16'),
(18,'Ocean Garden Villas','Newcoast Barangay Yapak, Boracay Island, Malay, Aklan 5608','Boracay Island, Malay','Aklan','5608','Active','Residential','2026-03-22 14:02:29');

INSERT INTO `tenants` (`tenant_id`,`full_name`,`phone`,`email`,`move_in_date`,`created_at`) VALUES
(1,'John Michael Arcido',NULL,'joda.arcido.ui@phinmaed.com','2026-03-23','2026-03-22 13:04:43');

INSERT INTO `units` (`unit_id`,`property_id`,`unit_number`,`unit_name`,`unit_type`,`floor`,`rent_amount`,`status`,`tenant_name`,`description`,`max_guests`) VALUES
(11,15,'Unit 10','','Studio',0,50000.00,'vacant','',NULL,2),
(13,14,'Unit A18','','Penthouse',10,150000.00,'vacant','','',4),
(14,18,'Unit A','','Loft',5,25000.00,'vacant','','',2),
(15,18,'Unit H','','3 Bedroom',4,25000.00,'vacant','','',6),
(16,17,'Unit 5','','2 Bedroom',2,25000.00,'vacant','','',4),
(17,18,'','','1 Bedroom',6,0.00,'vacant','','',2),
(18,14,'','','Penthouse',10,2555555.00,'vacant','','',4);

INSERT INTO `amenities` (`amenity_id`,`property_id`,`name`,`icon`,`status`,`created_at`) VALUES
(2,14,'Free Wifi','wifi','available','2026-03-22 07:34:38'),
(5,14,'Free Shower','shower','unavailable','2026-03-22 07:41:19'),
(6,15,'Water','water','available','2026-03-22 07:52:57'),
(8,15,'Rooftop','rooftop','available','2026-03-22 08:20:56');

INSERT INTO `unit_amenities` (`id`,`unit_id`,`amenity_id`) VALUES
(9,13,2),(10,18,2),(7,11,6),(8,11,8);

INSERT INTO `unit_images` (`image_id`,`unit_id`,`image_path`,`sort_order`,`created_at`) VALUES
(7,11,'uploads/units/11/unit_69bfaa4b4e6043.93688765.jpg',0,'2026-03-22 08:37:31'),
(9,13,'uploads/units/13/unit_69bff4ac7481e4.66003486.jpg',0,'2026-03-22 13:54:52'),
(10,14,'uploads/units/14/unit_69bff6d0afd442.12060825.jpg',0,'2026-03-22 14:04:00'),
(11,15,'uploads/units/15/unit_69bff7440c81f1.43632377.jpg',0,'2026-03-22 14:05:56'),
(12,16,'uploads/units/16/unit_69bff769d92183.90596974.jpg',0,'2026-03-22 14:06:33'),
(13,17,'uploads/units/17/unit_69bff795600a53.56970741.jpg',0,'2026-03-22 14:07:17'),
(14,18,'uploads/units/18/unit_69bff90ce31088.28439973.jpg',0,'2026-03-22 14:13:32');

INSERT INTO `bookings` (`booking_id`,`unit_id`,`tenant_id`,`user_id`,`checkin_date`,`checkout_date`,`guests`,`total_amount`,`status`,`special_requests`,`created_at`) VALUES
(1,11,1,14,'2026-03-23','2026-03-26',2,150000.00,'cancelled',NULL,'2026-03-22 15:00:36'),
(2,13,1,14,'2026-03-23','2026-03-26',2,450000.00,'completed',NULL,'2026-03-22 15:01:54'),
(3,14,1,14,'2026-03-23','2026-03-26',2,75000.00,'cancelled',NULL,'2026-03-22 15:02:23'),
(5,11,1,14,'2026-03-23','2026-03-26',2,150000.00,'completed',NULL,'2026-03-22 15:26:00'),
(10,11,1,14,'2026-04-23','2026-04-26',2,150000.00,'cancelled',NULL,'2026-03-22 15:39:17');

INSERT INTO `payments` (`payment_id`,`booking_id`,`payment_date`,`amount_paid`,`payment_method`,`payment_status`,`notes`) VALUES
(1,2,'2026-03-23',450000.00,'GCash','paid','Full payment on check-in'),
(2,5,'2026-03-23',150000.00,'Cash','paid','Paid at front desk');

INSERT INTO `transactions` (`reference_no`,`description`,`category`,`type`,`amount`,`transaction_date`,`property_id`,`booking_id`) VALUES
('TXN-001','Booking #2 payment - Unit A18','Room Revenue','Income',450000.00,'2026-03-23',14,2),
('TXN-002','Booking #5 payment - Unit 10','Room Revenue','Income',150000.00,'2026-03-23',15,5),
('TXN-003','Utilities - Casa De Primera','Utilities','Expense',8500.00,'2026-03-20',14,NULL),
('TXN-004','Cleaning supplies','Maintenance','Expense',2200.00,'2026-03-21',15,NULL),
('TXN-005','Internet subscription','Utilities','Expense',3500.00,'2026-03-15',18,NULL);

INSERT INTO `loyalty_points` (`user_id`,`points`,`type`,`description`,`booking_id`) VALUES
(14,420,'earn','Booking #2 — Unit A18 stay',2),
(14,150,'earn','Booking #5 — Casa Camilla stay',5),
(14,-100,'redeem','Redeemed for room discount',NULL),
(14,100,'bonus','New member welcome bonus',NULL);

INSERT INTO `saved_units` (`user_id`,`unit_id`) VALUES
(14,13),(14,15),(14,16),(2,11),(2,14);

INSERT INTO `user_settings` (`user_id`,`notif_booking_confirm`,`notif_checkin_remind`,`notif_promotions`,`notif_loyalty`,`notif_newsletter`,`notif_sms`,`privacy_profile`,`two_factor_enabled`,`language`,`timezone`) VALUES
(14,1,1,0,1,0,0,'private',0,'en','Asia/Manila'),
(2,1,1,1,1,1,0,'private',0,'en','Asia/Manila');

INSERT INTO `admin_settings` (`setting_key`,`value`) VALUES
('site_name','Filipino Homes'),
('site_email','admin@filipinohomes.com'),
('currency','PHP'),
('currency_symbol','₱'),
('checkout_time','12:00'),
('checkin_time','14:00'),
('min_nights',1),
('max_nights',90),
('loyalty_points_per_peso','0.1'),
('booking_cancellation_hours',48),
('smtp_host','smtp.gmail.com'),
('smtp_port','587'),
('tax_rate','0.12');

INSERT INTO `support_tickets` (`ticket_id`,`user_id`,`category`,`subject`,`priority`,`status`,`created_at`) VALUES
(1,14,'Booking & Reservations','Unable to modify booking dates','medium','resolved','2026-03-20 10:00:00'),
(2,2,'Check-in & Check-out','Request for late check-out','low','open','2026-03-22 08:30:00');

INSERT INTO `support_messages` (`ticket_id`,`user_id`,`body`,`is_admin`,`created_at`) VALUES
(1,14,'I am trying to change my check-in date but the system is not allowing it.',0,'2026-03-20 10:00:00'),
(1,4,'Hello! We have updated your booking dates as requested. Please check your bookings page.',1,'2026-03-20 11:30:00'),
(2,2,'Hi, is it possible to check out at 2pm instead of 12pm?',0,'2026-03-22 08:30:00');

INSERT INTO `notifications` (`user_id`,`type`,`title`,`body`,`is_read`) VALUES
(14,'booking','Booking Confirmed','Your booking for Unit A18 has been confirmed.', 1),
(14,'loyalty','Points Earned','You earned 420 loyalty points from your recent stay!', 1),
(14,'system','Welcome to Filipino Homes','Thanks for joining! Explore available units.', 1),
(2,'booking','New Booking Request','A new booking request has been submitted for Unit 10.', 0);

COMMIT;
