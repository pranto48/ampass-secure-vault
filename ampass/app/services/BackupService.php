<?php
/**
 * AMPass - Encrypted Backup Service
 * 
 * SECURITY:
 * - Backups are encrypted with Argon2id-derived key + AES-256-GCM (or XChaCha20-Poly1305)
 * - Backup password is never stored
 * - Each backup uses random salt and nonce
 * - File header contains KDF params but never the key
 */

class BackupService {

    const MAGIC = 'AMPASS_BACKUP_V1';
    const HEADER_SIZE = 4096; // Fixed header block size

    private static function appVersion(): string {
        return defined('AMPASS_VERSION_SEMVER') ? AMPASS_VERSION_SEMVER : (defined('APP_VERSION') ? APP_VERSION : '1.0.0');
    }

    /**
     * Create an encrypted backup
     */
    public static function create(string $password, array $options = []): array {
        $includeDb = $options['include_database'] ?? true;
        $includeFiles = $options['include_files'] ?? false;
        $includeAudit = $options['include_audit'] ?? false;

        // Build backup data
        $manifest = [
            'magic' => self::MAGIC,
            'version' => '1.0',
            'app_version' => self::appVersion(),
            'created_at' => date('c'),
            'includes_database' => $includeDb,
            'includes_files' => $includeFiles,
            'includes_audit_logs' => $includeAudit
        ];

        $package = ['manifest' => $manifest];

        // Export database
        if ($includeDb) {
            $package['database'] = self::exportDatabase($includeAudit);
        }

        // Export files
        if ($includeFiles) {
            $package['files'] = self::exportFiles();
        }

        // Serialize package
        $plaintext = json_encode($package, JSON_UNESCAPED_UNICODE);
        $checksumPlain = hash('sha256', $plaintext);

        // Encrypt
        $encrypted = self::encrypt($plaintext, $password);

        // Build final file
        $header = json_encode([
            'magic' => self::MAGIC,
            'version' => '1.0',
            'app_version' => self::appVersion(),
            'created_at' => date('c'),
            'kdf' => $encrypted['kdf'],
            'kdf_params' => $encrypted['kdf_params'],
            'cipher' => $encrypted['cipher'],
            'salt' => $encrypted['salt'],
            'nonce' => $encrypted['nonce'],
            'manifest_hash' => $checksumPlain
        ]);

        // Pad header to fixed size
        $headerPadded = str_pad($header, self::HEADER_SIZE - 1, "\0") . "\n";

        $backupData = $headerPadded . $encrypted['ciphertext'];

        // Save to file
        $filename = 'ampass_backup_' . date('Y-m-d_His') . '.ampass-backup';
        $storageDir = __DIR__ . '/../../app_storage/backups';
        if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

        $filePath = $storageDir . '/' . $filename;
        file_put_contents($filePath, $backupData);

        $fileSize = filesize($filePath);
        $checksum = hash_file('sha256', $filePath);

        return [
            'filename' => $filename,
            'file_path' => 'app_storage/backups/' . $filename,
            'file_size' => $fileSize,
            'sha256_checksum' => $checksum,
            'includes_database' => $includeDb,
            'includes_files' => $includeFiles,
            'includes_audit_logs' => $includeAudit
        ];
    }

    /**
     * Verify a backup file without restoring
     */
    public static function verify(string $filePath, string $password): array {
        $content = file_get_contents($filePath);
        if (!$content || strlen($content) < self::HEADER_SIZE) {
            return ['valid' => false, 'error' => 'File too small or unreadable'];
        }

        // Parse header
        $headerRaw = substr($content, 0, self::HEADER_SIZE);
        $headerJson = rtrim($headerRaw, "\0\n");
        $header = json_decode($headerJson, true);

        if (!$header || ($header['magic'] ?? '') !== self::MAGIC) {
            return ['valid' => false, 'error' => 'Not a valid AMPass backup file'];
        }

        // Decrypt
        $ciphertext = substr($content, self::HEADER_SIZE);
        try {
            $plaintext = self::decrypt($ciphertext, $password, $header);
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Decryption failed — wrong password or corrupted file'];
        }

        // Verify hash
        $actualHash = hash('sha256', $plaintext);
        if ($actualHash !== ($header['manifest_hash'] ?? '')) {
            return ['valid' => false, 'error' => 'Integrity check failed — file may be corrupted'];
        }

        $package = json_decode($plaintext, true);
        if (!$package || !isset($package['manifest'])) {
            return ['valid' => false, 'error' => 'Invalid backup structure'];
        }

        return [
            'valid' => true,
            'manifest' => $package['manifest'],
            'header' => $header,
            'has_database' => isset($package['database']),
            'has_files' => isset($package['files']),
            'table_count' => isset($package['database']) ? count($package['database']) : 0
        ];
    }

    /**
     * Export database tables as SQL statements using PDO
     */
    private static function exportDatabase(bool $includeAudit = false): array {
        $tables = [
            'app_settings', 'users', 'user_security', 'vault_items', 'vault_item_fields',
            'folders', 'tags', 'vault_item_tags', 'shared_items', 'sessions', 'devices',
            'password_resets', 'encrypted_backups', 'rate_limits', 'schema_migrations',
            'extension_devices', 'extension_tokens', 'release_downloads',
            'backup_files', 'email_queue', 'email_logs', 'login_challenges', 'password_reset_tokens'
        ];

        if ($includeAudit) {
            $tables[] = 'audit_logs';
            $tables[] = 'extension_audit_logs';
        }

        $export = [];
        $pdo = Database::getInstance();

        foreach ($tables as $table) {
            try {
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                $export[$table] = $rows;
            } catch (\PDOException $e) {
                // Table may not exist yet — skip
                continue;
            }
        }

        return $export;
    }

    /**
     * Export release files as base64
     */
    private static function exportFiles(): array {
        $files = [];
        $dir = __DIR__ . '/../../app_storage/releases';
        if (!is_dir($dir)) return $files;

        foreach (glob($dir . '/*') as $file) {
            if (is_file($file) && basename($file) !== '.htaccess') {
                $files[basename($file)] = base64_encode(file_get_contents($file));
            }
        }
        return $files;
    }

    /**
     * Encrypt data with password-derived key
     */
    private static function encrypt(string $plaintext, string $password): array {
        $salt = random_bytes(32);
        $nonce = random_bytes(24); // XChaCha20 nonce or 12 for AES-GCM

        // Derive key
        if (function_exists('sodium_crypto_pwhash')) {
            $key = sodium_crypto_pwhash(
                32,
                $password,
                $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plaintext, '', $nonce, $key
            );
            sodium_memzero($key);

            return [
                'kdf' => 'argon2id',
                'kdf_params' => ['ops' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, 'mem' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE],
                'cipher' => 'xchacha20-poly1305',
                'salt' => bin2hex($salt),
                'nonce' => bin2hex($nonce),
                'ciphertext' => $ciphertext
            ];
        }

        // Fallback: PBKDF2 + AES-256-GCM
        $key = hash_pbkdf2('sha256', $password, $salt, 310000, 32, true);
        $iv = substr($nonce, 0, 12);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return [
            'kdf' => 'pbkdf2-sha256',
            'kdf_params' => ['iterations' => 310000],
            'cipher' => 'aes-256-gcm',
            'salt' => bin2hex($salt),
            'nonce' => bin2hex($iv),
            'ciphertext' => $ciphertext . $tag
        ];
    }

    /**
     * Decrypt data with password-derived key
     */
    private static function decrypt(string $ciphertext, string $password, array $header): string {
        $salt = hex2bin($header['salt']);
        $nonce = hex2bin($header['nonce']);

        if ($header['kdf'] === 'argon2id' && function_exists('sodium_crypto_pwhash')) {
            $key = sodium_crypto_pwhash(
                32, $password, $salt,
                $header['kdf_params']['ops'] ?? SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                $header['kdf_params']['mem'] ?? SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, $key);
            sodium_memzero($key);
            if ($plaintext === false) throw new \Exception('Decryption failed');
            return $plaintext;
        }

        // PBKDF2 + AES-256-GCM
        $iterations = $header['kdf_params']['iterations'] ?? 310000;
        $key = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
        $tag = substr($ciphertext, -16);
        $ct = substr($ciphertext, 0, -16);
        $plaintext = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plaintext === false) throw new \Exception('Decryption failed');
        return $plaintext;
    }
}
