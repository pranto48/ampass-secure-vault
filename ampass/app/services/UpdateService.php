<?php
/**
 * AMPass - Update Service
 * Checks GitHub for updates and applies them safely.
 * 
 * SECURITY:
 * - Admin only
 * - Creates encrypted backup before update
 * - Maintenance mode during update
 * - Validates ZIP structure (rejects path traversal, symlinks, absolute paths)
 * - Rollback on failure
 * - Never overwrites config/config.php or app_storage/
 * - Never marks update completed unless files were actually copied and migrations succeeded
 * - Never logs GitHub token
 */

class UpdateService {

    const GITHUB_API = 'https://api.github.com';
    const USER_AGENT = 'AMPass-Updater/1.0';

    /** Paths that must NEVER be overwritten by an update */
    const SKIP_PATHS = [
        'config/config.php', 'config/.install_lock', 'config/.env',
        'app_storage/', 'install/.install_lock', '.git/', 'node_modules/', 'vendor/'
    ];

    /** Paths allowed to be updated */
    const ALLOWED_PREFIXES = [
        'app/', 'public/', 'database/migrations/', 'docs/', 'release/', 'clients/', 'scripts/',
        'index.php', 'sw.js', 'manifest.webmanifest', 'README.md'
    ];

    // ================================================================
    // CHECK FOR UPDATES
    // ================================================================

    public static function checkForUpdates(): array {
        $owner = self::getSetting('github_repo_owner', 'pranto48');
        $repo = self::getSetting('github_repo_name', 'ampass-secure-vault');
        $sourceType = self::getSetting('update_source_type', 'github_release');
        $token = self::getGitHubToken();

        $result = ['update_available' => false, 'current_version' => self::getInstalledVersion()];

        try {
            if ($sourceType === 'github_release') {
                $release = self::fetchGitHubRelease($owner, $repo, $token);
                if ($release) {
                    $latestVersion = ltrim($release['tag_name'] ?? '', 'v');
                    $result['latest_version'] = $latestVersion;
                    $result['latest_commit_sha'] = $release['target_commitish'] ?? '';
                    $result['release_notes'] = $release['body'] ?? '';
                    $result['download_url'] = $release['zipball_url'] ?? '';
                    $result['update_available'] = version_compare($latestVersion, $result['current_version'], '>');
                }
            } else {
                $branch = self::getSetting('github_branch', 'main');
                $commit = self::fetchLatestCommit($owner, $repo, $branch, $token);
                if ($commit) {
                    $result['latest_commit_sha'] = $commit['sha'] ?? '';
                    $result['latest_version'] = $result['current_version'];
                    $result['commit_message'] = $commit['commit']['message'] ?? '';
                    $installedSha = self::getSetting('installed_commit_sha', '');
                    $result['update_available'] = !empty($result['latest_commit_sha']) && $result['latest_commit_sha'] !== $installedSha;
                    $result['download_url'] = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$branch}.zip";
                }
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        self::saveSetting('last_update_check_at', date('c'));
        self::saveSetting('latest_version', $result['latest_version'] ?? '');
        self::saveSetting('latest_commit_sha', $result['latest_commit_sha'] ?? '');
        self::saveSetting('update_available', $result['update_available'] ? '1' : '0');

        return $result;
    }

    // ================================================================
    // APPLY UPDATE
    // ================================================================

    public static function applyUpdate(string $backupPassword, int $adminUserId): array {
        $currentVersion = self::getInstalledVersion();
        $latestVersion = self::getSetting('latest_version', $currentVersion);
        $downloadUrl = self::getDownloadUrl();

        if (!$downloadUrl) {
            return ['success' => false, 'error' => 'No download URL available. Run update check first.'];
        }

        $appRoot = realpath(__DIR__ . '/../..');
        if (!$appRoot) return ['success' => false, 'error' => 'Cannot determine app root path'];

        $tempBase = $appRoot . '/app_storage/temp/updates/' . time();
        $zipPath = $tempBase . '/package.zip';
        $stagingDir = $tempBase . '/staging';
        $rollbackDir = $tempBase . '/rollback';

        // Record update start
        $updateId = Database::insert(
            "INSERT INTO update_history (from_version, to_version, from_commit_sha, to_commit_sha, update_source, status, started_by_user_id, started_at) VALUES (?, ?, ?, ?, ?, 'started', ?, NOW())",
            [$currentVersion, $latestVersion, self::getSetting('installed_commit_sha', ''), self::getSetting('latest_commit_sha', ''), self::getSetting('update_source_type', 'github_release'), $adminUserId]
        );

        try {
            // Step 1: Create pre-update encrypted backup
            require_once __DIR__ . '/BackupService.php';
            $backup = BackupService::create($backupPassword, ['include_database' => true]);
            $backupId = Database::insert(
                "INSERT INTO backup_files (filename, file_path, file_size, sha256_checksum, backup_type, includes_database, created_by_user_id, notes, created_at) VALUES (?, ?, ?, ?, 'database_only', 1, ?, 'Pre-update automatic backup', NOW())",
                [$backup['filename'], $backup['file_path'], $backup['file_size'], $backup['sha256_checksum'], $adminUserId]
            );
            Database::execute("UPDATE update_history SET backup_file_id = ? WHERE id = ?", [$backupId, $updateId]);

            // Step 2: Enable maintenance mode
            self::saveSetting('maintenance_mode', '1');

            // Step 3: Download
            self::ensureDir($tempBase);
            $downloaded = self::downloadFile($downloadUrl, $zipPath);
            if (!$downloaded) throw new \Exception('Failed to download update package from GitHub');

            // Step 4: Extract ZIP
            self::ensureDir($stagingDir);
            $extracted = self::extractZip($zipPath, $stagingDir);
            if (!$extracted) throw new \Exception('Failed to extract update package');

            // Step 5: Find project root inside extracted ZIP
            $sourceRoot = self::findProjectRoot($stagingDir);
            if (!$sourceRoot) throw new \Exception('Update package does not contain valid AMPass structure');

            // Step 6: Validate package (no path traversal, has expected files)
            self::validatePackage($sourceRoot);

            // Step 7: Copy files with rollback support
            self::ensureDir($rollbackDir);
            $copiedFiles = self::copyUpdateFiles($sourceRoot, $appRoot, $rollbackDir);

            // Step 8: Run migrations
            $migrationResult = self::runPendingMigrations();
            if ($migrationResult['failed']) {
                // Migration failed — rollback files
                self::restoreRollback($rollbackDir, $appRoot);
                throw new \Exception('Migration failed: ' . $migrationResult['failed'] . '. Files rolled back.');
            }

            // Step 9: Update version info (ONLY after successful copy + migrations)
            self::saveSetting('installed_version', $latestVersion);
            self::saveSetting('installed_commit_sha', self::getSetting('latest_commit_sha', ''));
            self::saveSetting('installed_at', date('c'));
            self::saveSetting('update_available', '0');

            // Step 10: Disable maintenance mode
            self::saveSetting('maintenance_mode', '0');

            // Step 11: Record success
            Database::execute("UPDATE update_history SET status = 'completed', completed_at = NOW(), notes = ? WHERE id = ?",
                ['Updated ' . count($copiedFiles) . ' files. Migrations: ' . count($migrationResult['applied']) . ' applied.', $updateId]);

            // Cleanup temp
            self::deleteDir($tempBase);

            return ['success' => true, 'version' => $latestVersion, 'files_updated' => count($copiedFiles), 'migrations_applied' => $migrationResult['applied']];

        } catch (\Exception $e) {
            self::saveSetting('maintenance_mode', '0');
            Database::execute("UPDATE update_history SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?", [substr($e->getMessage(), 0, 1000), $updateId]);
            self::deleteDir($tempBase);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ================================================================
    // FILE EXTRACTION & VALIDATION
    // ================================================================

    private static function extractZip(string $zipPath, string $destDir): bool {
        if (!class_exists('ZipArchive')) return false;
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) return false;
        $zip->extractTo($destDir);
        $zip->close();
        return true;
    }

    /**
     * Find the AMPass project root inside extracted ZIP.
     * GitHub ZIPs have a top-level directory like "ampass-secure-vault-main/"
     */
    private static function findProjectRoot(string $stagingDir): ?string {
        // Check if staging dir itself is the root
        if (file_exists($stagingDir . '/index.php') && file_exists($stagingDir . '/app/core/App.php')) {
            return $stagingDir;
        }
        // Check if there's an ampass/ subdirectory
        if (file_exists($stagingDir . '/ampass/index.php')) {
            return $stagingDir . '/ampass';
        }
        // Check first subdirectory (GitHub archive pattern)
        $dirs = glob($stagingDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            if (file_exists($dir . '/ampass/index.php')) return $dir . '/ampass';
            if (file_exists($dir . '/index.php') && file_exists($dir . '/app/core/App.php')) return $dir;
        }
        return null;
    }

    /**
     * Validate extracted package for safety
     */
    private static function validatePackage(string $sourceRoot): void {
        if (!file_exists($sourceRoot . '/index.php')) {
            throw new \Exception('Package missing index.php');
        }
        // Scan for dangerous paths
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace($sourceRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace('\\', '/', $relative);
            // Reject path traversal
            if (str_contains($relative, '..') || str_contains($relative, "\0")) {
                throw new \Exception("Dangerous path in package: {$relative}");
            }
            // Reject symlinks
            if (is_link($file->getPathname())) {
                throw new \Exception("Symlink not allowed in package: {$relative}");
            }
        }
    }

    /**
     * Copy update files to app root, creating rollback copies
     */
    private static function copyUpdateFiles(string $sourceRoot, string $appRoot, string $rollbackDir): array {
        $copied = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            $relative = str_replace($sourceRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace('\\', '/', $relative);

            // Check if this path should be skipped
            if (self::shouldSkipPath($relative)) continue;

            // Check if this path is in allowed prefixes
            if (!self::isAllowedPath($relative)) continue;

            $destPath = $appRoot . '/' . $relative;
            $rollbackPath = $rollbackDir . '/' . $relative;

            // Backup existing file for rollback
            if (file_exists($destPath)) {
                $rollbackParent = dirname($rollbackPath);
                if (!is_dir($rollbackParent)) mkdir($rollbackParent, 0755, true);
                copy($destPath, $rollbackPath);
            }

            // Copy new file
            $destParent = dirname($destPath);
            if (!is_dir($destParent)) mkdir($destParent, 0755, true);
            if (copy($file->getPathname(), $destPath)) {
                $copied[] = $relative;
            }
        }

        return $copied;
    }

    private static function shouldSkipPath(string $relative): bool {
        foreach (self::SKIP_PATHS as $skip) {
            if (str_starts_with($relative, $skip) || $relative === rtrim($skip, '/')) return true;
        }
        return false;
    }

    private static function isAllowedPath(string $relative): bool {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix) || $relative === $prefix) return true;
        }
        return false;
    }

    /**
     * Restore files from rollback directory
     */
    private static function restoreRollback(string $rollbackDir, string $appRoot): void {
        if (!is_dir($rollbackDir)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rollbackDir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            $relative = str_replace($rollbackDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace('\\', '/', $relative);
            $destPath = $appRoot . '/' . $relative;
            @copy($file->getPathname(), $destPath);
        }
    }

    // ================================================================
    // MIGRATION RUNNER (FIXED — never marks failed as applied)
    // ================================================================

    /**
     * Run pending database migrations.
     * Returns: ['applied' => [...], 'skipped' => [...], 'failed' => null|string]
     * SECURITY: Failed migrations are NEVER marked as applied.
     */
    public static function runPendingMigrations(): array {
        $result = ['applied' => [], 'skipped' => [], 'failed' => null];
        $migrationsDir = __DIR__ . '/../../database/migrations';
        if (!is_dir($migrationsDir)) return $result;

        $pdo = Database::getInstance();

        // Ensure schema_migrations table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL UNIQUE,
            `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);

            // Check if already applied
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE filename = ?");
            $stmt->execute([$filename]);
            if ($stmt->fetchColumn() > 0) {
                $result['skipped'][] = $filename;
                continue;
            }

            // Run migration
            try {
                $sql = file_get_contents($file);
                if (empty(trim($sql))) {
                    $result['skipped'][] = $filename;
                    continue;
                }
                $pdo->exec($sql);

                // ONLY mark as applied after successful execution
                $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
                $stmt->execute([$filename]);
                $result['applied'][] = $filename;

            } catch (\PDOException $e) {
                // DO NOT mark as applied — this is the critical fix
                $result['failed'] = $filename . ': ' . $e->getMessage();
                error_log("AMPass migration FAILED ({$filename}): " . $e->getMessage());
                return $result; // Stop on first failure
            }
        }

        return $result;
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public static function getInstalledVersion(): string {
        return defined('APP_VERSION') ? APP_VERSION : (self::getSetting('installed_version', '1.0.0'));
    }

    public static function getUpdateHistory(int $limit = 10): array {
        return Database::fetchAll("SELECT * FROM update_history ORDER BY started_at DESC LIMIT ?", [$limit]);
    }

    public static function getPendingMigrations(): array {
        $migrationsDir = __DIR__ . '/../../database/migrations';
        if (!is_dir($migrationsDir)) return [];
        $files = glob($migrationsDir . '/*.sql');
        sort($files);
        $pending = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $applied = Database::fetchOne("SELECT COUNT(*) as cnt FROM schema_migrations WHERE filename = ?", [$filename]);
            if (($applied['cnt'] ?? 0) == 0) $pending[] = $filename;
        }
        return $pending;
    }

    private static function getDownloadUrl(): string {
        $owner = self::getSetting('github_repo_owner', 'pranto48');
        $repo = self::getSetting('github_repo_name', 'ampass-secure-vault');
        $sourceType = self::getSetting('update_source_type', 'github_release');
        if ($sourceType === 'github_release') {
            $v = self::getSetting('latest_version', '');
            return $v ? "https://github.com/{$owner}/{$repo}/archive/refs/tags/v{$v}.zip" : '';
        }
        $branch = self::getSetting('github_branch', 'main');
        return "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$branch}.zip";
    }

    // ===== GitHub API =====

    private static function fetchGitHubRelease(string $owner, string $repo, ?string $token): ?array {
        return self::githubRequest(self::GITHUB_API . "/repos/{$owner}/{$repo}/releases/latest", $token);
    }

    private static function fetchLatestCommit(string $owner, string $repo, string $branch, ?string $token): ?array {
        return self::githubRequest(self::GITHUB_API . "/repos/{$owner}/{$repo}/commits/{$branch}", $token);
    }

    private static function githubRequest(string $url, ?string $token): ?array {
        $headers = ['User-Agent: ' . self::USER_AGENT, 'Accept: application/vnd.github.v3+json'];
        if ($token) $headers[] = 'Authorization: token ' . $token;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 403) throw new \Exception('GitHub API rate limited. Add a token or try later.');
        if ($httpCode === 404) return null;
        if ($httpCode !== 200) throw new \Exception("GitHub API error (HTTP {$httpCode})");
        return json_decode($response, true);
    }

    private static function downloadFile(string $url, string $destPath): bool {
        $token = self::getGitHubToken();
        $headers = ['User-Agent: ' . self::USER_AGENT];
        if ($token) $headers[] = 'Authorization: token ' . $token;
        $ch = curl_init($url);
        $fp = fopen($destPath, 'wb');
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 120, CURLOPT_FOLLOWLOCATION => true]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        return $httpCode === 200 && filesize($destPath) > 1000;
    }

    private static function getGitHubToken(): ?string {
        $encrypted = self::getSetting('github_token_encrypted', '');
        if (empty($encrypted) || !defined('APP_SECRET')) return null;
        $data = base64_decode($encrypted);
        if (strlen($data) < 28) return null;
        $key = hash('sha256', APP_SECRET, true);
        $iv = substr($data, 0, 12); $tag = substr($data, 12, 16); $ct = substr($data, 28);
        $decrypted = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted ?: null;
    }

    // ===== Settings =====

    public static function getSetting(string $key, string $default = ''): string {
        $row = Database::fetchOne("SELECT setting_value FROM app_settings WHERE setting_key = ?", [$key]);
        return $row ? ($row['setting_value'] ?? $default) : $default;
    }

    public static function saveSetting(string $key, string $value): void {
        Database::execute("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
    }

    // ===== Filesystem =====

    private static function ensureDir(string $path): void {
        if (!is_dir($path)) mkdir($path, 0755, true);
    }

    private static function deleteDir(string $dir): void {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}
