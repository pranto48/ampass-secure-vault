<?php
/**
 * AMPass - Share API Controller
 * SECURITY: Sharing uses encrypted item keys. Server never sees plaintext.
 */

require_once __DIR__ . '/../../models/SharedItem.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/AuditLog.php';

class ShareApiController {

    private int $userId;

    public function __construct() {
        $this->userId = Session::getUserId();
    }

    /**
     * POST /api/share/create - Share an item
     */
    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $recipientLogin = trim($input['recipient'] ?? '');
        $vaultItemId = (int)($input['vault_item_id'] ?? 0);
        $permission = in_array($input['permission'] ?? '', ['view', 'edit']) ? $input['permission'] : 'view';
        $encryptedItemKey = $input['encrypted_item_key'] ?? '';
        $itemKeyIv = $input['item_key_iv'] ?? '';

        // SECURITY: Verify the user owns the vault item they're trying to share
        require_once __DIR__ . '/../../models/VaultItem.php';
        $ownedItem = VaultItem::findById($vaultItemId, $this->userId);
        if (!$ownedItem) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only share items you own']);
            return;
        }

        // Find recipient
        $recipient = User::findByLogin($recipientLogin);
        if (!$recipient) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        if ($recipient['id'] === $this->userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot share with yourself']);
            return;
        }

        if (empty($encryptedItemKey) || empty($itemKeyIv)) {
            http_response_code(400);
            echo json_encode(['error' => 'Encrypted key data required']);
            return;
        }

        $shareId = SharedItem::create([
            'vault_item_id' => $vaultItemId,
            'shared_by_user_id' => $this->userId,
            'shared_with_user_id' => $recipient['id'],
            'encrypted_item_key' => $encryptedItemKey,
            'item_key_iv' => $itemKeyIv,
            'permission' => $permission
        ]);

        AuditLog::log('item_shared', $this->userId, 'vault_item', $vaultItemId, [
            'shared_with' => $recipient['username'],
            'permission' => $permission
        ]);

        echo json_encode(['success' => true, 'share_id' => $shareId]);
    }

    /**
     * GET /api/share/list - Get shared items
     */
    public function list(): void {
        $sharedWith = SharedItem::getSharedWithUser($this->userId);
        $sharedBy = SharedItem::getSharedByUser($this->userId);
        $pending = SharedItem::getPending($this->userId);

        echo json_encode([
            'success' => true,
            'shared_with_me' => $sharedWith,
            'shared_by_me' => $sharedBy,
            'pending' => $pending
        ]);
    }

    /**
     * POST /api/share/accept - Accept a share
     */
    public function accept(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $shareId = (int)($input['id'] ?? 0);

        SharedItem::accept($shareId, $this->userId);
        AuditLog::log('share_accepted', $this->userId, 'shared_item', $shareId);
        echo json_encode(['success' => true]);
    }

    /**
     * POST /api/share/revoke - Revoke a share
     */
    public function revoke(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $shareId = (int)($input['id'] ?? 0);

        SharedItem::revoke($shareId, $this->userId);
        AuditLog::log('share_revoked', $this->userId, 'shared_item', $shareId);
        echo json_encode(['success' => true]);
    }
}
