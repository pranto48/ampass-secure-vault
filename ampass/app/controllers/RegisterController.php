<?php
/**
 * AMPass - Registration Controller
 * SECURITY: Validates all input, checks registration status, hashes passwords.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserSecurity.php';
require_once __DIR__ . '/../models/AuditLog.php';

class RegisterController {

    public function index(): void {
        // Check if registration is enabled
        if (!defined('REGISTRATION_ENABLED') || !REGISTRATION_ENABLED) {
            Session::flash('error', 'Registration is currently disabled.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // Prevent browser caching of register page (stale CSRF tokens)
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $error = Session::flash('error');
        $csrfToken = CSRF::generateToken();
        require __DIR__ . '/../views/auth/register.php';
    }

    public function submit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/register');
            exit;
        }

        // Validate CSRF — redirects to /register with friendly message on failure
        CSRF::validateOrRedirect(APP_URL . '/register');

        if (!defined('REGISTRATION_ENABLED') || !REGISTRATION_ENABLED) {
            Session::flash('error', 'Registration is currently disabled.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        // SECURITY: Rate limit registration to prevent mass account creation
        $ip = Security::getClientIP();
        if (!RateLimit::check($ip, 'register', 3, 3600)) {
            Session::flash('error', 'Too many registration attempts. Please try again later.');
            header('Location: ' . APP_URL . '/register');
            exit;
        }
        RateLimit::record($ip, 'register', 3, 3600);

        // Collect and sanitize input
        $fullName = Security::sanitize($_POST['full_name'] ?? '');
        $email = Security::sanitizeEmail($_POST['email'] ?? '');
        $username = Security::sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Client-side encryption data
        $encryptionSalt = $_POST['encryption_salt'] ?? '';
        $encryptedVaultKey = $_POST['encrypted_vault_key'] ?? '';
        $vaultKeyIv = $_POST['vault_key_iv'] ?? '';
        // SECURITY: Master password hash uses the same password as login.
        // The password is sent via the standard form field (over HTTPS).
        // It is hashed server-side and never stored in plaintext.

        // Validation
        $errors = [];

        if (empty($fullName) || strlen($fullName) < 2) {
            $errors[] = 'Full name is required (minimum 2 characters).';
        }
        if (!Security::isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($username) || strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username must be at least 3 characters (letters, numbers, underscores only).';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        $passwordCheck = Security::isStrongPassword($password);
        if (!$passwordCheck['valid']) {
            $errors = array_merge($errors, $passwordCheck['errors']);
        }

        if (User::usernameExists($username)) {
            $errors[] = 'Username is already taken.';
        }
        if (User::emailExists($email)) {
            $errors[] = 'Email is already registered.';
        }

        // Check encryption data from client
        if (empty($encryptionSalt) || empty($encryptedVaultKey) || empty($vaultKeyIv)) {
            $errors[] = 'Encryption setup failed. Please enable JavaScript and try again.';
        }

        if (!empty($errors)) {
            Session::flash('error', implode('<br>', $errors));
            header('Location: ' . APP_URL . '/register');
            exit;
        }

        // Create user
        try {
            Database::beginTransaction();

            $userId = User::create([
                'username' => $username,
                'email' => $email,
                'full_name' => $fullName,
                'password_hash' => Security::hashPassword($password),
                'role' => 'user',
                'status' => 'active'
            ]);

            // Create security record with encryption data
            UserSecurity::create([
                'user_id' => $userId,
                'master_password_hash' => Security::hashPassword($password),
                'encryption_salt' => $encryptionSalt,
                'encrypted_vault_key' => $encryptedVaultKey,
                'vault_key_iv' => $vaultKeyIv,
                'key_iterations' => 100000
            ]);

            Database::commit();

            AuditLog::log('user_registered', $userId);
            Session::flash('success', 'Account created successfully! Please log in.');
            header('Location: ' . APP_URL . '/login');
            exit;

        } catch (Exception $e) {
            Database::rollback();
            Session::flash('error', 'Registration failed. Please try again.');
            header('Location: ' . APP_URL . '/register');
            exit;
        }
    }
}
