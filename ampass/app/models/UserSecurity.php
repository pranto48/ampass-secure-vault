<?php
/**
 * AMPass - User Security Model
 * Manages encryption keys, salts, and vault key data.
 * SECURITY: This stores the encrypted vault key and derivation parameters.
 * The actual vault key is NEVER stored in plaintext - only the user can decrypt it
 * using their master password.
 */

class UserSecurity {

    /**
     * Get security data for a user
     */
    public static function findByUserId(int $userId): ?array {
        return Database::fetchOne(
            "SELECT * FROM user_security WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Create security record for a new user
     * Called during registration/setup when user sets their master password.
     * 
     * @param array $data Must contain:
     *   - user_id: int
     *   - master_password_hash: string (Argon2id hash of master password)
     *   - encryption_salt: string (random salt for PBKDF2 key derivation)
     *   - encrypted_vault_key: string (vault key encrypted with derived key)
     *   - vault_key_iv: string (IV used for vault key encryption)
     *   - key_iterations: int (PBKDF2 iteration count)
     */
    public static function create(array $data): int {
        return Database::insert(
            "INSERT INTO user_security 
             (user_id, master_password_hash, encryption_salt, encrypted_vault_key, vault_key_iv, key_iterations, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['master_password_hash'],
                $data['encryption_salt'],
                $data['encrypted_vault_key'],
                $data['vault_key_iv'],
                $data['key_iterations'] ?? 100000
            ]
        );
    }

    /**
     * Update encrypted vault key (e.g., when master password changes)
     */
    public static function updateVaultKey(int $userId, array $data): int {
        return Database::execute(
            "UPDATE user_security SET 
             master_password_hash = ?,
             encryption_salt = ?,
             encrypted_vault_key = ?,
             vault_key_iv = ?,
             key_iterations = ?,
             updated_at = NOW()
             WHERE user_id = ?",
            [
                $data['master_password_hash'],
                $data['encryption_salt'],
                $data['encrypted_vault_key'],
                $data['vault_key_iv'],
                $data['key_iterations'] ?? 100000,
                $userId
            ]
        );
    }

    /**
     * Update recovery data
     */
    public static function updateRecovery(int $userId, array $data): int {
        return Database::execute(
            "UPDATE user_security SET 
             recovery_key_hash = ?,
             encrypted_recovery_data = ?,
             recovery_iv = ?,
             updated_at = NOW()
             WHERE user_id = ?",
            [
                $data['recovery_key_hash'],
                $data['encrypted_recovery_data'],
                $data['recovery_iv'],
                $userId
            ]
        );
    }

    /**
     * Verify master password
     */
    public static function verifyMasterPassword(int $userId, string $masterPassword): bool {
        $security = self::findByUserId($userId);
        if (!$security) return false;
        
        return Security::verifyPassword($masterPassword, $security['master_password_hash']);
    }

    /**
     * Get encryption parameters needed by client for key derivation
     * Returns null if user has no security record.
     * Returns with 'needs_setup' = true if vault is not yet initialized (key_iterations = 0).
     */
    public static function getDerivationParams(int $userId): ?array {
        $security = self::findByUserId($userId);
        if (!$security) return null;

        $iterations = (int) $security['key_iterations'];

        return [
            'encryption_salt' => $security['encryption_salt'],
            'key_iterations' => $iterations,
            'encrypted_vault_key' => $security['encrypted_vault_key'],
            'vault_key_iv' => $security['vault_key_iv'],
            'needs_setup' => ($iterations === 0 || $security['encrypted_vault_key'] === 'VAULT_NOT_INITIALIZED')
        ];
    }
}
