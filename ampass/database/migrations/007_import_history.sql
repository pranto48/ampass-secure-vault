-- AMPass Migration 007: Import History
-- Tracks password import operations for audit and user reference.

CREATE TABLE IF NOT EXISTS `import_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `source` VARCHAR(50) NOT NULL COMMENT 'sticky_password, chrome, edge, firefox, brave, generic_csv',
    `filename_hash` VARCHAR(64) NULL COMMENT 'SHA-256 of original filename (never store plaintext filename)',
    `item_count_total` INT UNSIGNED DEFAULT 0,
    `item_count_imported` INT UNSIGNED DEFAULT 0,
    `item_count_skipped` INT UNSIGNED DEFAULT 0,
    `item_count_failed` INT UNSIGNED DEFAULT 0,
    `status` ENUM('started', 'completed', 'failed', 'cancelled') DEFAULT 'started',
    `warnings_json` JSON NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    INDEX `idx_import_user` (`user_id`),
    INDEX `idx_import_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
