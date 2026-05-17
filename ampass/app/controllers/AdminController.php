<?php
/**
 * AMPass - Admin Controller
 * SECURITY: All admin routes require admin role verification.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

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
}
