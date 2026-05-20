-- AMPass Update System & Remote Backup Destinations

-- Update history
CREATE TABLE IF NOT EXISTS `update_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from_version` VARCHAR(20) NOT NULL,
    `to_version` VARCHAR(20) NOT NULL,
    `from_commit_sha` VARCHAR(40) NULL,
    `to_commit_sha` VARCHAR(40) NULL,
    `update_source` VARCHAR(50) DEFAULT 'github_release',
    `status` ENUM('started','completed','failed','rolled_back') DEFAULT 'started',
    `backup_file_id` INT UNSIGNED NULL,
    `started_by_user_id` INT UNSIGNED NULL,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `notes` TEXT NULL,
    INDEX `idx_update_status` (`status`),
    FOREIGN KEY (`backup_file_id`) REFERENCES `backup_files`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`started_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remote backup destinations
CREATE TABLE IF NOT EXISTS `remote_backup_destinations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `provider` ENUM('ftp','ftps','sftp','onedrive') NOT NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    `encrypted_config` TEXT NOT NULL COMMENT 'AES-GCM encrypted JSON config',
    `last_test_at` DATETIME NULL,
    `last_success_at` DATETIME NULL,
    `last_error` VARCHAR(500) NULL,
    `created_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remote backup upload records
CREATE TABLE IF NOT EXISTS `remote_backup_uploads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `backup_file_id` INT UNSIGNED NOT NULL,
    `destination_id` INT UNSIGNED NOT NULL,
    `provider` VARCHAR(20) NOT NULL,
    `remote_path` VARCHAR(500) NULL,
    `status` ENUM('pending','uploading','uploaded','failed') DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED DEFAULT 0,
    `last_error` VARCHAR(500) NULL,
    `uploaded_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_remote_upload_backup` (`backup_file_id`),
    INDEX `idx_remote_upload_dest` (`destination_id`),
    FOREIGN KEY (`backup_file_id`) REFERENCES `backup_files`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`destination_id`) REFERENCES `remote_backup_destinations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update and remote backup settings
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('installed_version', '1.0.0'),
('installed_commit_sha', ''),
('installed_at', NOW()),
('last_update_check_at', ''),
('latest_version', ''),
('latest_commit_sha', ''),
('update_available', '0'),
('update_channel', 'stable'),
('update_enabled', '1'),
('update_source_type', 'github_release'),
('github_repo_owner', 'pranto48'),
('github_repo_name', 'ampass-secure-vault'),
('github_branch', 'main'),
('github_token_encrypted', ''),
('auto_check_updates', '1'),
('auto_update_enabled', '0'),
('notify_admin_update_available', '1'),
('maintenance_mode', '0'),
('backup_remote_upload_enabled', '0'),
('backup_remote_retention_count', '7'),
('cron_backup_token_hash', ''),
('cron_update_token_hash', '');
