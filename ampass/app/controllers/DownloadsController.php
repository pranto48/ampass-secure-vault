<?php
/**
 * AMPass - Downloads Controller
 * Public download center for AMPass apps.
 */

class DownloadsController {

    public function index(): void {
        // Check if downloads page is enabled
        $setting = Database::fetchOne("SELECT setting_value FROM app_settings WHERE setting_key = 'downloads_enabled'");
        if (!$setting || $setting['setting_value'] !== '1') {
            http_response_code(404);
            App::loadView('errors/404');
            return;
        }

        // Get active releases
        $releases = Database::fetchAll(
            "SELECT id, product_type, version, filename_original, file_size, sha256_checksum, release_notes, download_count, created_at 
             FROM release_downloads WHERE is_active = 1 ORDER BY product_type, created_at DESC"
        );

        $grouped = [];
        foreach ($releases as $r) {
            $grouped[$r['product_type']][] = $r;
        }

        $data = ['releases' => $grouped];
        require __DIR__ . '/../views/downloads/index.php';
    }

    /**
     * Stream a release file download
     * SECURITY: Validates file path, sanitizes headers, streams in chunks.
     */
    public function file(?string $id = null): void {
        $fileId = (int)($id ?? $_GET['id'] ?? 0);
        if (!$fileId) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }

        $file = Database::fetchOne(
            "SELECT * FROM release_downloads WHERE id = ? AND is_active = 1", [$fileId]
        );

        if (!$file) { http_response_code(404); echo json_encode(['error' => 'File not found or inactive']); return; }

        $basePath = realpath(__DIR__ . '/../../app_storage/releases');
        $filePath = realpath(__DIR__ . '/../../' . $file['file_path']);

        // SECURITY: Verify file path resolves inside app_storage/releases
        if (!$basePath || !$filePath || strpos($filePath, $basePath) !== 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File unavailable']);
            return;
        }

        // Sanitize filename for Content-Disposition
        $safeFilename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $file['filename_original']);

        // Increment download count
        Database::execute("UPDATE release_downloads SET download_count = download_count + 1 WHERE id = ?", [$fileId]);

        // Audit log
        if (class_exists('AuditLog')) {
            AuditLog::log('release_downloaded', Session::getUserId(), 'release', $fileId, [
                'product' => $file['product_type'], 'version' => $file['version']
            ]);
        }

        // Security headers
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform, no-cache');

        // Stream in chunks (memory-safe for large files)
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
        exit;
    }
}
