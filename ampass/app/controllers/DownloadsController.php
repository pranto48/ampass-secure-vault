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
     */
    public function file(?string $id = null): void {
        $fileId = (int)($id ?? $_GET['id'] ?? 0);
        if (!$fileId) { http_response_code(404); echo 'File not found'; return; }

        $file = Database::fetchOne(
            "SELECT * FROM release_downloads WHERE id = ? AND is_active = 1", [$fileId]
        );

        if (!$file) { http_response_code(404); echo 'File not found or inactive'; return; }

        $filePath = __DIR__ . '/../../' . $file['file_path'];
        if (!file_exists($filePath)) { http_response_code(404); echo 'File missing from server'; return; }

        // Increment download count
        Database::execute("UPDATE release_downloads SET download_count = download_count + 1 WHERE id = ?", [$fileId]);

        // Audit log
        if (class_exists('AuditLog')) {
            AuditLog::log('release_downloaded', Session::getUserId(), 'release', $fileId, [
                'product' => $file['product_type'], 'version' => $file['version']
            ]);
        }

        // Stream file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['filename_original'] . '"');
        header('Content-Length: ' . $file['file_size']);
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }
}
