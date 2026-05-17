<?php
/**
 * AMPass - CSRF Protection
 * SECURITY: Generates and validates CSRF tokens for all state-changing requests.
 * Uses HMAC-based tokens tied to the user session.
 */

class CSRF {

    /**
     * Generate a CSRF token
     */
    public static function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Get HTML hidden input field with CSRF token
     */
    public static function tokenField(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate CSRF token from request
     */
    public static function validate(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Validate and throw exception if invalid
     */
    public static function validateOrFail(?string $token = null): void {
        if (!self::validate($token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid security token. Please refresh and try again.']));
        }
    }

    /**
     * Regenerate token (call after successful form submission)
     */
    public static function regenerate(): void {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Get current token value (for JavaScript/AJAX)
     */
    public static function getToken(): string {
        return self::generateToken();
    }
}
