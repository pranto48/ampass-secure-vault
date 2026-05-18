<?php
/**
 * AMPass - Admin Controller
 * SECURITY: All admin routes require admin role verification.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/ExtensionDevice.php';
require_once __DIR__ . '/../models/ExtensionAudit.php';
require_once __DIR__ . '/../models/ExtensionToken.php';

class AdminController {

    public function __construct() {
        if (!Session::isAdmin()) {
            http_response_code(403);
            die('Access denied');
        }
    }

    public function index(): void {
        $totalUsers = User::count();
        $users = User::getAll(20, 0);
        $recentLogs = AuditLog::getAll(20);

        $data = [
            'totalUsers' => $totalUsers,
            'users' => $users,
            'recentLogs' => $recentLogs,
            'csrfToken' => CSRF::generateToken()
        ];

        require __DIR__ . '/../views/admin/index.php';
    }

    public function users(): void {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = User::getAll($limit, $offset);
        $totalUsers = User::count();

        $data = [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'currentPage' => $page,
            'totalPages' => ceil($totalUsers / $limit),
            'csrfToken' => CSRF::generateToken()
        ];

        require __DIR__ . '/../views/admin/users.php';
    }

    public function suspendUser(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/users');
            exit;
        }
        CSRF::validateOrFail();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId && $userId !== Session::getUserId()) {
            User::suspend($userId);
            AuditLog::log('user_suspended', Session::getUserId(), 'user', $userId);
        }

        header('Location: ' . APP_URL . '/admin/users');
        exit;
    }

    public function activateUser(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/users');
            exit;
        }
        CSRF::validateOrFail();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId) {
            User::activate($userId);
            AuditLog::log('user_activated', Session::getUserId(), 'user', $userId);
        }

        header('Location: ' . APP_URL . '/admin/users');
        exit;
    }

    public function settings(): void {
        $csrfToken = CSRF::generateToken();
        
        // Get current settings
        $settings = Database::fetchAll("SELECT setting_key, setting_value FROM app_settings");
        $settingsMap = [];
        foreach ($settings as $s) {
            $settingsMap[$s['setting_key']] = $s['setting_value'];
        }

        $data = [
            'settings' => $settingsMap,
            'csrfToken' => $csrfToken
        ];

        require __DIR__ . '/../views/admin/settings.php';
    }

    public function saveSettings(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/settings');
            exit;
        }
        CSRF::validateOrFail();

        $allowedSettings = ['site_name', 'registration_enabled', 'vault_lock_timeout', 'max_login_attempts', 'lockout_duration'];

        foreach ($allowedSettings as $key) {
            if (isset($_POST[$key])) {
                $value = Security::sanitize($_POST[$key]);
                Database::execute(
                    "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $value, $value]
                );
            }
        }

        AuditLog::log('settings_updated', Session::getUserId());
        Session::flash('success', 'Settings saved successfully.');
        header('Location: ' . APP_URL . '/admin/settings');
        exit;
    }

    public function logs(): void {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $action = $_GET['action_filter'] ?? null;

        $logs = AuditLog::getAll($limit, $offset, $action);

        $data = [
            'logs' => $logs,
            'currentPage' => $page,
            'actionFilter' => $action
        ];

        require __DIR__ . '/../views/admin/logs.php';
    }

    // ================================================================
    // EXTENSION MANAGEMENT
    // ================================================================

    public function extensions(): void {
        $devices = ExtensionDevice::listAll(50, 0);
        $logs = ExtensionAudit::getAll(25, 0);

        // Get extension settings
        $settingsRows = Database::fetchAll(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'extension_%'"
        );
        $settings = [];
        foreach ($settingsRows as $s) {
            $settings[$s['setting_key']] = $s['setting_value'];
        }

        $data = [
            'devices' => $devices,
            'logs' => $logs,
            'settings' => $settings,
            'csrfToken' => CSRF::generateToken()
        ];

        require __DIR__ . '/../views/admin/extensions.php';
    }

    public function saveExtensionSettings(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/extensions');
            exit;
        }
        CSRF::validateOrFail();

        $extensionSettings = [
            'extension_api_enabled' => isset($_POST['extension_api_enabled']) ? '1' : '0',
            'extension_allowed_origins' => trim($_POST['extension_allowed_origins'] ?? ''),
            'extension_token_lifetime_days' => max(1, min(365, (int)($_POST['extension_token_lifetime_days'] ?? 30))),
            'extension_max_devices_per_user' => max(1, min(50, (int)($_POST['extension_max_devices_per_user'] ?? 10)))
        ];

        foreach ($extensionSettings as $key => $value) {
            Database::execute(
                "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, (string)$value, (string)$value]
            );
        }

        AuditLog::log('extension_settings_updated', Session::getUserId(), null, null, $extensionSettings);
        Session::flash('success', 'Extension settings saved.');
        header('Location: ' . APP_URL . '/admin/extensions');
        exit;
    }

    public function revokeExtensionDevice(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/extensions');
            exit;
        }
        CSRF::validateOrFail();

        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId) {
            ExtensionDevice::adminRevoke($deviceId);
            AuditLog::log('extension_device_revoked_admin', Session::getUserId(), 'device', $deviceId);
            Session::flash('success', 'Device revoked successfully.');
        }

        header('Location: ' . APP_URL . '/admin/extensions');
        exit;
    }

    // ================================================================
    // RELEASE DOWNLOADS MANAGEMENT
    // Routes: /admin/releases, /admin/releases/upload, /admin/releases/toggle, /admin/releases/delete
    // ================================================================

    public function releases(?string $subAction = null): void {
        // Sub-route dispatch for /admin/releases/{action}
        switch ($subAction) {
            case 'upload': $this->releasesUpload(); return;
            case 'toggle': $this->releasesToggle(); return;
            case 'delete': $this->releasesDelete(); return;
        }

        // Default: list releases
        $releases = Database::fetchAll(
            "SELECT * FROM release_downloads ORDER BY created_at DESC"
        );

        $settingsRows = Database::fetchAll(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key = 'downloads_enabled'"
        );
        $settings = [];
        foreach ($settingsRows as $s) $settings[$s['setting_key']] = $s['setting_value'];

        $data = [
            'releases' => $releases,
            'settings' => $settings,
            'csrfToken' => CSRF::generateToken()
        ];

        require __DIR__ . '/../views/admin/releases.php';
    }

    public function releasesUpload(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }
        CSRF::validateOrFail();

        // Validate product type
        $allowedTypes = ['windows_exe', 'windows_msi', 'chrome_extension', 'edge_extension', 'firefox_extension', 'pwa'];
        $productType = $_POST['product_type'] ?? '';
        if (!in_array($productType, $allowedTypes, true)) {
            Session::flash('error', 'Invalid product type.');
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        $version = trim($_POST['version'] ?? '');
        if (empty($version) || !preg_match('/^[0-9a-zA-Z.\-]+$/', $version)) {
            Session::flash('error', 'Invalid version format.');
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // Validate file upload
        if (!isset($_FILES['release_file']) || $_FILES['release_file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'File upload failed. Error code: ' . ($_FILES['release_file']['error'] ?? 'none'));
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        $file = $_FILES['release_file'];
        $originalName = basename($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // SECURITY: Strict extension allowlist
        $allowedExtensions = ['exe', 'msi', 'zip', 'xpi'];
        if (!in_array($extension, $allowedExtensions, true)) {
            Session::flash('error', 'File type not allowed. Only .exe, .msi, .zip, .xpi are accepted.');
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // SECURITY: Enforce product type to file extension mapping
        $typeExtensionMap = [
            'windows_exe' => ['exe'],
            'windows_msi' => ['msi'],
            'chrome_extension' => ['zip'],
            'edge_extension' => ['zip'],
            'firefox_extension' => ['xpi', 'zip'],
            'pwa' => ['zip']
        ];
        $allowedForType = $typeExtensionMap[$productType] ?? [];
        if (!in_array($extension, $allowedForType, true)) {
            Session::flash('error', 'Selected product type does not match uploaded file extension. Expected: .' . implode(' or .', $allowedForType));
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // SECURITY: Block dangerous extensions even if disguised
        $dangerousPatterns = ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd', 'ps1', 'htaccess'];
        foreach ($dangerousPatterns as $pattern) {
            if (stripos($originalName, '.' . $pattern) !== false) {
                Session::flash('error', 'Dangerous file type rejected.');
                header('Location: ' . APP_URL . '/admin/releases');
                exit;
            }
        }

        // Generate random stored filename
        $storedFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $storageDir = __DIR__ . '/../../app_storage/releases';

        // Ensure storage directory exists
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $destPath = $storageDir . '/' . $storedFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Session::flash('error', 'Failed to store file. Check directory permissions.');
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // SECURITY: Verify stored path is inside app_storage/releases
        $realDest = realpath($destPath);
        $realBase = realpath($storageDir);
        if (!$realDest || !$realBase || strpos($realDest, $realBase) !== 0) {
            @unlink($destPath);
            Session::flash('error', 'File path validation failed.');
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // Calculate checksum and size
        $sha256 = hash_file('sha256', $destPath);
        $fileSize = filesize($destPath);
        $mimeType = mime_content_type($destPath) ?: 'application/octet-stream';
        $releaseNotes = trim($_POST['release_notes'] ?? '');

        // Insert into database
        Database::insert(
            "INSERT INTO release_downloads (product_type, version, filename_original, filename_stored, file_path, file_size, sha256_checksum, mime_type, release_notes, is_active, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
            [
                $productType,
                $version,
                $originalName,
                $storedFilename,
                'app_storage/releases/' . $storedFilename,
                $fileSize,
                $sha256,
                $mimeType,
                $releaseNotes,
                Session::getUserId()
            ]
        );

        AuditLog::log('release_uploaded', Session::getUserId(), 'release', null, [
            'product' => $productType, 'version' => $version, 'file' => $originalName
        ]);

        Session::flash('success', "Release uploaded: {$originalName} (v{$version}, SHA-256: " . substr($sha256, 0, 12) . "…)");
        header('Location: ' . APP_URL . '/admin/releases');
        exit;
    }

    public function releasesToggle(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }
        CSRF::validateOrFail();

        // Toggle downloads_enabled setting
        if (isset($_POST['setting']) && $_POST['setting'] === 'downloads_enabled') {
            $value = $_POST['value'] === '1' ? '1' : '0';
            Database::execute(
                "INSERT INTO app_settings (setting_key, setting_value) VALUES ('downloads_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [$value, $value]
            );
            AuditLog::log($value === '1' ? 'downloads_enabled' : 'downloads_disabled', Session::getUserId());
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        // Toggle individual release active status
        $id = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['active'] ?? 0);
        if ($id) {
            Database::execute("UPDATE release_downloads SET is_active = ?, updated_at = NOW() WHERE id = ?", [$active, $id]);
            AuditLog::log($active ? 'release_enabled' : 'release_disabled', Session::getUserId(), 'release', $id);
            Session::flash('success', 'Release ' . ($active ? 'enabled' : 'disabled') . '.');
        }

        header('Location: ' . APP_URL . '/admin/releases');
        exit;
    }

    public function releasesDelete(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }
        CSRF::validateOrFail();

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: ' . APP_URL . '/admin/releases');
            exit;
        }

        $release = Database::fetchOne("SELECT * FROM release_downloads WHERE id = ?", [$id]);
        if ($release) {
            // SECURITY: Validate file path stays inside app_storage/releases before unlinking
            $baseDir = realpath(__DIR__ . '/../../app_storage/releases');
            $filePath = realpath(__DIR__ . '/../../' . $release['file_path']);

            if ($baseDir && $filePath && strpos($filePath, $baseDir) === 0) {
                @unlink($filePath);
            } else {
                // Path invalid or outside allowed directory — log warning, skip file delete
                error_log("AMPass: Release delete skipped file unlink — path outside app_storage/releases (id={$id})");
            }

            // Delete DB record regardless
            Database::execute("DELETE FROM release_downloads WHERE id = ?", [$id]);

            AuditLog::log('release_deleted', Session::getUserId(), 'release', $id, [
                'product' => $release['product_type'], 'version' => $release['version']
            ]);
            Session::flash('success', 'Release deleted.');
        }

        header('Location: ' . APP_URL . '/admin/releases');
        exit;
    }
}
