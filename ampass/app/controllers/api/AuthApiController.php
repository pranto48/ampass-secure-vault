<?php
/**
 * AMPass - Auth API Controller
 * SECURITY: All state-changing endpoints require CSRF validation and rate limiting.
 * The master password is sent over HTTPS for server-side verification.
 * Client-side key derivation happens independently in the browser.
 */

require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/UserSecurity.php';
require_once __DIR__ . '/../../models/AuditLog.php';

class AuthApiController {

    /**
     * GET /api/auth/status - Check auth status
     */
    public function status(): void {
        echo json_encode([
            'logged_in' => Session::isLoggedIn(),
            'vault_unlocked' => Session::isVaultUnlocked(),
            'csrf_token' => CSRF::getToken()
        ]);
    }

    /**
     * GET /api/auth/derivation-params - Get key derivation parameters
     * SECURITY: Only returns params after authentication. These are needed
     * by the client to derive the encryption key from the master password.
     */
    public function derivationParams(): void {
        if (!Session::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            return;
        }

        $params = UserSecurity::getDerivationParams(Session::getUserId());
        if (!$params) {
            http_response_code(404);
            echo json_encode(['error' => 'Security data not found']);
            return;
        }

        echo json_encode(['success' => true, 'params' => $params]);
    }

    /**
     * POST /api/auth/verify-master - Verify master password (AJAX)
     * SECURITY: Rate limited, CSRF protected, audit logged.
     * The master password is verified server-side to confirm vault unlock.
     * The actual decryption key derivation happens client-side only.
     */
    public function verifyMaster(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // CSRF validation
        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!Session::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            return;
        }

        $userId = Session::getUserId();
        $ip = Security::getClientIP();

        // Rate limiting - prevent brute force on master password
        if (!RateLimit::check($ip . '_unlock_' . $userId, 'api_unlock', 5, 300)) {
            http_response_code(429);
            AuditLog::log('vault_unlock_rate_limited', $userId, null, null, ['ip' => $ip]);
            echo json_encode(['error' => 'Too many attempts. Please wait before trying again.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            return;
        }

        $masterPassword = $input['master_password'] ?? '';

        if (empty($masterPassword) || strlen($masterPassword) > 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid password']);
            return;
        }

        if (UserSecurity::verifyMasterPassword($userId, $masterPassword)) {
            RateLimit::clear($ip . '_unlock_' . $userId, 'api_unlock');
            Session::unlockVault();
            AuditLog::log('vault_unlocked', $userId, null, null, ['method' => 'api']);
            echo json_encode(['success' => true]);
        } else {
            RateLimit::record($ip . '_unlock_' . $userId, 'api_unlock', 5, 300);
            AuditLog::log('vault_unlock_failed', $userId, null, null, ['method' => 'api', 'ip' => $ip]);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid master password']);
        }
    }

    /**
     * POST /api/auth/lock - Lock vault
     * SECURITY: CSRF protected, audit logged.
     */
    public function lock(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        $userId = Session::getUserId();
        Session::lockVault();
        AuditLog::log('vault_locked', $userId, null, null, ['method' => 'api']);
        echo json_encode(['success' => true]);
    }

    /**
     * POST /api/auth/initVaultKey - Initialize vault encryption key (first-time setup)
     * SECURITY: Called once when user's vault has not been initialized.
     * Accepts the encrypted vault key (encrypted client-side with master password).
     * The server NEVER sees the raw vault key.
     */
    public function initVaultKey(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        CSRF::validateOrFail($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!Session::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            return;
        }

        $userId = Session::getUserId();

        // Verify vault actually needs initialization
        $security = UserSecurity::findByUserId($userId);
        if (!$security || ($security['key_iterations'] > 0 && $security['encrypted_vault_key'] !== 'VAULT_NOT_INITIALIZED')) {
            http_response_code(400);
            echo json_encode(['error' => 'Vault is already initialized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            return;
        }

        $salt = $input['encryption_salt'] ?? '';
        $encryptedKey = $input['encrypted_vault_key'] ?? '';
        $iv = $input['vault_key_iv'] ?? '';
        $iterations = (int)($input['key_iterations'] ?? 100000);

        if (empty($salt) || empty($encryptedKey) || empty($iv)) {
            http_response_code(400);
            echo json_encode(['error' => 'encryption_salt, encrypted_vault_key, and vault_key_iv are required']);
            return;
        }

        if ($iterations < 10000 || $iterations > 1000000) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid key_iterations']);
            return;
        }

        // Update the user_security record with real encrypted vault key
        UserSecurity::updateVaultKey($userId, [
            'master_password_hash' => $security['master_password_hash'],
            'encryption_salt' => $salt,
            'encrypted_vault_key' => $encryptedKey,
            'vault_key_iv' => $iv,
            'key_iterations' => $iterations
        ]);

        AuditLog::log('vault_initialized', $userId);
        echo json_encode(['success' => true, 'message' => 'Vault key initialized']);
    }
}
