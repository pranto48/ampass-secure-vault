-- AMPass Email & Security Tables

-- Email queue for async sending
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `html_body` LONGTEXT NOT NULL,
    `text_body` TEXT NULL,
    `provider` VARCHAR(50) DEFAULT 'resend',
    `status` ENUM('pending','sent','failed') DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED DEFAULT 0,
    `last_error` TEXT NULL,
    `scheduled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_status` (`status`),
    INDEX `idx_email_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email send logs
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `email_type` VARCHAR(50) NOT NULL COMMENT 'login_otp, password_reset, new_device, security_alert, backup_alert',
    `to_email_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of recipient email',
    `provider` VARCHAR(50) DEFAULT 'resend',
    `provider_message_id` VARCHAR(255) NULL,
    `status` ENUM('sent','failed') NOT NULL,
    `error_message` VARCHAR(500) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_log_user` (`user_id`),
    INDEX `idx_email_log_type` (`email_type`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login challenges (email OTP, 2FA)
CREATE TABLE IF NOT EXISTS `login_challenges` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `challenge_token_hash` VARCHAR(64) NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL COMMENT 'Argon2id/bcrypt hash of 6-digit OTP',
    `purpose` ENUM('login_2fa','new_device','password_reset') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` TINYINT UNSIGNED DEFAULT 0,
    `consumed_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_challenge_user` (`user_id`),
    INDEX `idx_challenge_token` (`challenge_token_hash`),
    INDEX `idx_challenge_expires` (`expires_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of reset token',
    `expires_at` DATETIME NOT NULL,
    `consumed_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reset_token` (`token_hash`),
    INDEX `idx_reset_user` (`user_id`),
    INDEX `idx_reset_expires` (`expires_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add email 2FA columns to user_security if not exists
-- Using ALTER TABLE with IF NOT EXISTS workaround for MySQL
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_security' AND COLUMN_NAME = 'email_2fa_enabled');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE user_security ADD COLUMN email_2fa_enabled TINYINT(1) DEFAULT 0 AFTER key_iterations', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Email settings
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('email_provider', 'resend'),
('resend_api_key_encrypted', ''),
('resend_from_email', ''),
('resend_from_name', 'AMPass'),
('resend_reply_to', ''),
('security_email_enabled', '0'),
('password_reset_email_enabled', '0'),
('new_device_email_enabled', '0'),
('two_factor_email_enabled', '0'),
('backup_restore_email_enabled', '0');
