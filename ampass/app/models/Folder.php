<?php
/**
 * AMPass - Folder Model
 */

class Folder {

    public static function getAllByUser(int $userId): array {
        return Database::fetchAll(
            "SELECT f.*, (SELECT COUNT(*) FROM vault_items vi WHERE vi.folder_id = f.id) as item_count 
             FROM folders f WHERE f.user_id = ? ORDER BY f.sort_order, f.name",
            [$userId]
        );
    }

    public static function findById(int $id, int $userId): ?array {
        return Database::fetchOne(
            "SELECT * FROM folders WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    public static function create(array $data): int {
        return Database::insert(
            "INSERT INTO folders (user_id, name, icon, color, parent_id, sort_order, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['name'],
                $data['icon'] ?? null,
                $data['color'] ?? null,
                $data['parent_id'] ?? null,
                $data['sort_order'] ?? 0
            ]
        );
    }

    public static function update(int $id, int $userId, array $data): int {
        return Database::execute(
            "UPDATE folders SET name = ?, icon = ?, color = ?, updated_at = NOW() 
             WHERE id = ? AND user_id = ?",
            [
                $data['name'],
                $data['icon'] ?? null,
                $data['color'] ?? null,
                $id,
                $userId
            ]
        );
    }

    public static function delete(int $id, int $userId): int {
        // Items in this folder will have folder_id set to NULL (ON DELETE SET NULL)
        return Database::execute(
            "DELETE FROM folders WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
}
