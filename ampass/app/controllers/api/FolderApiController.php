<?php
/**
 * AMPass - Folder API Controller
 */

require_once __DIR__ . '/../../models/Folder.php';

class FolderApiController {

    private int $userId;

    public function __construct() {
        $this->userId = Session::getUserId();
    }

    public function list(): void {
        $folders = Folder::getAllByUser($this->userId);
        echo json_encode(['success' => true, 'folders' => $folders]);
    }

    public function save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $name = Security::sanitize($input['name'] ?? '');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Folder name is required']);
            return;
        }

        $folderId = isset($input['id']) ? (int)$input['id'] : 0;

        if ($folderId > 0) {
            Folder::update($folderId, $this->userId, [
                'name' => $name,
                'icon' => $input['icon'] ?? null,
                'color' => $input['color'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => $folderId]);
        } else {
            $newId = Folder::create([
                'user_id' => $this->userId,
                'name' => $name,
                'icon' => $input['icon'] ?? null,
                'color' => $input['color'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => $newId]);
        }
    }

    public function delete(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $folderId = (int)($input['id'] ?? 0);

        if ($folderId) {
            Folder::delete($folderId, $this->userId);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Folder ID required']);
        }
    }
}
