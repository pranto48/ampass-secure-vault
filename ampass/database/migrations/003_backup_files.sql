-- AMPass Backup System Tables

CREATE TABLE IF NOT EXISTS `backup_files` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sha256_checksum` VARCHAR(64) NOT NULL,
    `backup_type` ENUM('database_only','database_files','full') DEFAULT 'database_only',
    `includes_database` TINYINT(1) DEFAULT 1,
    `includes_files` TINYINT(1) DEFAULT 0,
    `includes_audit_logs` TINYINT(1) DEFAULT 0,
    `created_by_user_id` INT UNSIGNED NULL,
    `downloaded_at` DATETIME NULL,
    `verified_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_backup_user` (`created_by_user_id`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup settings
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('backup_auto_enabled', '0'),
('backup_frequency', 'weekly'),
('backup_retention_count', '7'),
('backup_include_files', '0'),
('backup_include_audit', '0'),
('backup_notify_admin', '0'),
('backup_cron_token_hash', '');
