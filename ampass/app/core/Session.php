<?php
/**
 * AMPass - Secure Session Management
 * SECURITY: Implements secure session handling with regeneration, 
 * timeout, and vault lock state management.
 */

class Session {
    
    /**
     * Start a secure session
     */
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'ampass_session');
        
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie (expires when browser closes)
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();

        // SECURITY: Session fingerprint validation (bind session to user-agent)
        $fingerprint = self::generateFingerprint();
        if (isset($_SESSION['_fingerprint'])) {
            if (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
                // Possible session hijacking - destroy and restart
                self::destroy();
                session_start();
                $_SESSION['_fingerprint'] = $fingerprint;
                return;
            }
        } else {
            $_SESSION['_fingerprint'] = $fingerprint;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
            if (time() - $_SESSION['last_activity'] > $timeout) {
                self::destroy();
                return;
            }
        }
        $_SESSION['last_activity'] = time();

        // Check vault lock timeout
        if (isset($_SESSION['vault_unlocked_at'])) {
            $lockTimeout = defined('VAULT_LOCK_TIMEOUT') ? VAULT_LOCK_TIMEOUT : 300;
            if (time() - $_SESSION['vault_unlocked_at'] > $lockTimeout) {
                $_SESSION['vault_unlocked'] = false;
                unset($_SESSION['vault_unlocked_at']);
            }
        }
    }

    /**
     * Generate a session fingerprint based on client characteristics
     * SECURITY: Helps detect session hijacking by binding session to user-agent
     */
    private static function generateFingerprint(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return hash('sha256', $ua . '|' . $accept);
    }

    /**
     * Regenerate session ID (call after login)
     */
    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    /**
     * Destroy session completely
     */
    public static function destroy(): void {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }

    /**
     * Set a session value
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Check if vault is unlocked
     */
    public static function isVaultUnlocked(): bool {
        return isset($_SESSION['vault_unlocked']) && $_SESSION['vault_unlocked'] === true;
    }

    /**
     * Unlock the vault
     */
    public static function unlockVault(): void {
        $_SESSION['vault_unlocked'] = true;
        $_SESSION['vault_unlocked_at'] = time();
    }

    /**
     * Lock the vault
     */
    public static function lockVault(): void {
        $_SESSION['vault_unlocked'] = false;
        unset($_SESSION['vault_unlocked_at']);
    }

    /**
     * Get current user ID
     */
    public static function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function getUserRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Flash message (show once)
     */
    public static function flash(string $key, string $message = null) {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}
