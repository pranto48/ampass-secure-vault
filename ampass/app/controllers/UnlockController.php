<?php
/**
 * AMPass - Vault Unlock Controller
 * SECURITY: Verifies master password before granting vault access.
 * The master password is used client-side to derive the encryption key.
 */

require_once __DIR__ . '/../models/UserSecurity.php';
require_once __DIR__ . '/../models/AuditLog.php';

class UnlockController {

    public function index(): void {
        if (!Session::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        if (Session::isVaultUnlocked()) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        $error = Session::flash('error');
        $csrfToken = CSRF::generateToken();
        $userId = Session::getUserId();

        // Get derivation params for client-side key derivation
        $derivationParams = UserSecurity::getDerivationParams($userId);

        require __DIR__ . '/../views/auth/unlock.php';
    }

    public function submit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/unlock');
            exit;
        }

        CSRF::validateOrFail();

        $masterPassword = $_POST['master_password'] ?? '';
        $userId = Session::getUserId();
        $ip = Security::getClientIP();

        // Rate limit unlock attempts
        if (!RateLimit::check($ip . '_' . $userId, 'unlock', 5, 300)) {
            Session::flash('error', 'Too many unlock attempts. Please wait a few minutes.');
            header('Location: ' . APP_URL . '/unlock');
            exit;
        }

        if (empty($masterPassword)) {
            Session::flash('error', 'Please enter your master password.');
            header('Location: ' . APP_URL . '/unlock');
            exit;
        }

        // Verify master password
        if (!UserSecurity::verifyMasterPassword($userId, $masterPassword)) {
            RateLimit::record($ip . '_' . $userId, 'unlock', 5, 300);
            AuditLog::log('vault_unlock_failed', $userId);
            Session::flash('error', 'Invalid master password.');
            header('Location: ' . APP_URL . '/unlock');
            exit;
        }

        // Unlock vault
        RateLimit::clear($ip . '_' . $userId, 'unlock');
        Session::unlockVault();
        AuditLog::log('vault_unlocked', $userId);

        header('Location: ' . APP_URL . '/dashboard');
        exit;
    }
}
