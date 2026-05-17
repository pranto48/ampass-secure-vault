<?php
/**
 * AMPass - Login Controller
 * SECURITY: Implements rate limiting, secure password verification, and session management.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class LoginController {

    public function index(): void {
        if (Session::isLoggedIn()) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        $error = Session::flash('error');
        $success = Session::flash('success');
        $csrfToken = CSRF::generateToken();

        // Check HTTPS warning
        $httpsWarning = !Security::isHTTPS() && !Security::isLocalhost();

        require __DIR__ . '/../views/auth/login.php';
    }

    public function submit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Validate CSRF
        CSRF::validateOrFail();

        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = Security::getClientIP();

        // Rate limiting check
        $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5;
        $lockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;

        if (!RateLimit::check($ip, 'login', $maxAttempts, $lockoutTime)) {
            $remaining = RateLimit::getLockoutRemaining($ip, 'login');
            Session::flash('error', "Too many login attempts. Please try again in " . ceil($remaining / 60) . " minutes.");
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Validate input
        if (empty($login) || empty($password)) {
            Session::flash('error', 'Please enter your username/email and password.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Find user
        $user = User::findByLogin($login);

        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            RateLimit::record($ip, 'login', $maxAttempts, $lockoutTime);
            AuditLog::log('login_failed', null, 'user', null, ['login' => $login]);
            Session::flash('error', 'Invalid username/email or password.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Check user status
        if ($user['status'] === 'suspended') {
            Session::flash('error', 'Your account has been suspended. Contact an administrator.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Successful login
        RateLimit::clear($ip, 'login');
        Session::regenerate();
        CSRF::regenerate(); // SECURITY: Prevent session fixation + CSRF attacks

        // Set session data
        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role']);
        Session::set('username', $user['username']);
        Session::set('full_name', $user['full_name']);

        // Update last login
        User::updateLastLogin($user['id']);

        // Rehash if needed
        if (Security::needsRehash($user['password_hash'])) {
            User::updatePassword($user['id'], Security::hashPassword($password));
        }

        // Log successful login
        AuditLog::log('login_success', $user['id']);

        // Check if force password reset
        if ($user['force_password_reset']) {
            header('Location: ' . APP_URL . '/settings/change-password');
            exit;
        }

        // Redirect to vault unlock
        header('Location: ' . APP_URL . '/unlock');
        exit;
    }

    public function logout(): void {
        $userId = Session::getUserId();
        if ($userId) {
            AuditLog::log('logout', $userId);
        }
        Session::destroy();
        // Start a new session for flash message
        Session::start();
        Session::flash('success', 'You have been logged out successfully.');
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}
