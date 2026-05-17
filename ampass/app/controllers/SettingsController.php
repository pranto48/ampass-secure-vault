<?php
/**
 * AMPass - User Settings Controller
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserSecurity.php';
require_once __DIR__ . '/../models/AuditLog.php';

class SettingsController {

    public function index(): void {
        $userId = Session::getUserId();
        $user = User::findById($userId);
        $csrfToken = CSRF::generateToken();

        $data = [
            'user' => $user,
            'csrfToken' => $csrfToken
        ];

        require __DIR__ . '/../views/settings/index.php';
    }

    public function profile(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/settings');
            exit;
        }
        CSRF::validateOrFail();

        $userId = Session::getUserId();
        $fullName = Security::sanitize($_POST['full_name'] ?? '');
        $email = Security::sanitizeEmail($_POST['email'] ?? '');

        $errors = [];
        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (!Security::isValidEmail($email)) $errors[] = 'Valid email is required.';
        if (User::emailExists($email, $userId)) $errors[] = 'Email is already in use.';

        if (!empty($errors)) {
            Session::flash('error', implode('<br>', $errors));
            header('Location: ' . APP_URL . '/settings');
            exit;
        }

        User::update($userId, ['full_name' => $fullName, 'email' => $email]);
        AuditLog::log('profile_updated', $userId);
        Session::flash('success', 'Profile updated successfully.');
        header('Location: ' . APP_URL . '/settings');
        exit;
    }

    public function changePassword(): void {
        $csrfToken = CSRF::generateToken();
        $data = ['csrfToken' => $csrfToken];
        require __DIR__ . '/../views/settings/change-password.php';
    }

    public function updatePassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }
        CSRF::validateOrFail();

        $userId = Session::getUserId();
        $ip = Security::getClientIP();

        // SECURITY: Rate limit password change attempts (prevents brute-forcing current password)
        if (!RateLimit::check($ip . '_pwchange_' . $userId, 'password_change', 5, 900)) {
            Session::flash('error', 'Too many attempts. Please wait before trying again.');
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Verify current password
        $user = Database::fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
        if (!Security::verifyPassword($currentPassword, $user['password_hash'])) {
            RateLimit::record($ip . '_pwchange_' . $userId, 'password_change', 5, 900);
            AuditLog::log('password_change_failed', $userId, null, null, ['reason' => 'wrong_current_password']);
            Session::flash('error', 'Current password is incorrect.');
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'New passwords do not match.');
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }

        $check = Security::isStrongPassword($newPassword);
        if (!$check['valid']) {
            Session::flash('error', implode('<br>', $check['errors']));
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }

        User::updatePassword($userId, Security::hashPassword($newPassword));
        
        // Clear force reset flag
        Database::execute("UPDATE users SET force_password_reset = 0 WHERE id = ?", [$userId]);

        AuditLog::log('password_changed', $userId);
        Session::flash('success', 'Password changed successfully.');
        header('Location: ' . APP_URL . '/settings');
        exit;
    }

    public function security(): void {
        $userId = Session::getUserId();
        $logs = AuditLog::getByUser($userId, 20);
        $csrfToken = CSRF::generateToken();

        $data = [
            'logs' => $logs,
            'csrfToken' => $csrfToken
        ];

        require __DIR__ . '/../views/settings/security.php';
    }
}
