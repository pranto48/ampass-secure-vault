-- AMPass Release Downloads
-- Stores release files metadata for the web download center.

CREATE TABLE IF NOT EXISTS `release_downloads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_type` ENUM('windows_exe','windows_msi','chrome_extension','edge_extension','firefox_extension','pwa') NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `filename_original` VARCHAR(255) NOT NULL,
    `filename_stored` VARCHAR(255) NOT NULL COMMENT 'Random filename on disk',
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sha256_checksum` VARCHAR(64) NOT NULL,
    `mime_type` VARCHAR(100) NULL,
    `release_notes` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `download_count` INT UNSIGNED DEFAULT 0,
    `created_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_release_type` (`product_type`),
    INDEX `idx_release_active` (`is_active`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App setting for downloads page
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('downloads_enabled', '1');
