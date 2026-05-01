-- ============================================================
--  PropSight Real-Time System — Migration Script
--  Run this ONCE against your existing database if needed.
--  Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS).
-- ============================================================

-- 1. Ensure bookings.updated_at exists and auto-updates
-- (Already present in the bundled schema; only needed for older installs)
ALTER TABLE `bookings`
    MODIFY COLUMN `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Ensure notifications table exists
CREATE TABLE IF NOT EXISTS `notifications` (
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
    CONSTRAINT `fk_notif_user_rt`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Index to speed up realtime polling queries
-- (polling queries filter on updated_at and created_at)
ALTER TABLE `bookings`
    ADD INDEX IF NOT EXISTS `idx_bookings_updated_at` (`updated_at`),
    ADD INDEX IF NOT EXISTS `idx_bookings_created_at` (`created_at`);

-- Note: "ADD INDEX IF NOT EXISTS" requires MySQL 8.0+.
-- For MySQL 5.7, remove the IF NOT EXISTS clause:
-- ALTER TABLE `bookings` ADD INDEX `idx_bookings_updated_at` (`updated_at`);

-- Done.
