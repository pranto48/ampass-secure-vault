<?php
/**
 * AMPass - Shared Item Model
 * SECURITY: Sharing uses encrypted item keys. The server never sees plaintext credentials.
 */

class SharedItem {

    /**
     * Share an item with another user
     */
    public static function create(array $data): int {
        return Database::insert(
            "INSERT INTO shared_items 
             (vault_item_id, shared_by_user_id, shared_with_user_id, encrypted_item_key, item_key_iv, permission, status, expires_at, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
            [
                $data['vault_item_id'],
                $data['shared_by_user_id'],
                $data['shared_with_user_id'],
                $data['encrypted_item_key'],
                $data['item_key_iv'],
                $data['permission'] ?? 'view',
                $data['expires_at'] ?? null
            ]
        );
    }

    /**
     * Get items shared with a user
     */
    public static function getSharedWithUser(int $userId): array {
        return Database::fetchAll(
            "SELECT si.*, vi.encrypted_data, vi.encryption_iv, vi.item_type, u.username as shared_by_username 
             FROM shared_items si 
             JOIN vault_items vi ON si.vault_item_id = vi.id 
             JOIN users u ON si.shared_by_user_id = u.id 
             WHERE si.shared_with_user_id = ? AND si.status = 'accepted'
             ORDER BY si.created_at DESC",
            [$userId]
        );
    }

    /**
     * Get items shared by a user
     */
    public static function getSharedByUser(int $userId): array {
        return Database::fetchAll(
            "SELECT si.*, u.username as shared_with_username, u.email as shared_with_email 
             FROM shared_items si 
             JOIN users u ON si.shared_with_user_id = u.id 
             WHERE si.shared_by_user_id = ? AND si.status != 'revoked'
             ORDER BY si.created_at DESC",
            [$userId]
        );
    }

    /**
     * Get pending shares for a user
     */
    public static function getPending(int $userId): array {
        return Database::fetchAll(
            "SELECT si.*, u.username as shared_by_username 
             FROM shared_items si 
             JOIN users u ON si.shared_by_user_id = u.id 
             WHERE si.shared_with_user_id = ? AND si.status = 'pending'
             ORDER BY si.created_at DESC",
            [$userId]
        );
    }

    /**
     * Accept a share
     */
    public static function accept(int $id, int $userId): int {
        return Database::execute(
            "UPDATE shared_items SET status = 'accepted', updated_at = NOW() 
             WHERE id = ? AND shared_with_user_id = ?",
            [$id, $userId]
        );
    }

    /**
     * Revoke a share
     */
    public static function revoke(int $id, int $userId): int {
        return Database::execute(
            "UPDATE shared_items SET status = 'revoked', updated_at = NOW() 
             WHERE id = ? AND (shared_by_user_id = ? OR shared_with_user_id = ?)",
            [$id, $userId, $userId]
        );
    }

    /**
     * Find by ID
     */
    public static function findById(int $id): ?array {
        return Database::fetchOne("SELECT * FROM shared_items WHERE id = ?", [$id]);
    }
}
