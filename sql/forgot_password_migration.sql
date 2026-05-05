-- ============================================================
--  PropSight — Forgot Password Migration
--  Run this ONCE against your database.
--  Safe to run multiple times (uses IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)      NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `token`      VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_email` (`email`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_pr_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table to track password reset attempts for security monitoring and rate limiting
CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(255) NOT NULL,
    `ip_address`   VARCHAR(45)  NOT NULL, -- IPv4/IPv6 support
    `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_found`   TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_email_ip` (`email`, `ip_address`),
    KEY `idx_attempted_at` (`attempted_at`),
    KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Clean up old attempts after 30 days (optional cleanup)
-- You can run this as a scheduled job: DELETE FROM password_reset_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
