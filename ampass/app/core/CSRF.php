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

    public static function validate(?string $token = null): bool {
        if ($token === null || $token === '') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

            // Fallback: check raw JSON body
            if (empty($token)) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $json = json_decode($rawInput, true);
                    if ($json && isset($json['csrf_token'])) {
                        $token = $json['csrf_token'];
                    }
                }
            }
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $isValid = false;
        if (!empty($token) && !empty($sessionToken)) {
            $isValid = hash_equals($sessionToken, $token);
        }

        // Log validation attempt
        $logMsg = date('Y-m-d H:i:s') . " - CSRF Validate. Req: '$token', Session: '$sessionToken', Valid: " . ($isValid ? 'true' : 'false') . ", URL: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
        @file_put_contents('/tmp/ampass_debug.log', $logMsg, FILE_APPEND);

        return $isValid;
    }

    /**
     * Validate and die with JSON if invalid.
     * Use ONLY for API/AJAX endpoints that always expect JSON responses.
     */
    public static function validateOrFail(?string $token = null): void {
        if (!self::validate($token)) {
            self::regenerate();
            http_response_code(403);
            if (self::isAjaxRequest()) {
                die(json_encode(['error' => 'Invalid security token. Please refresh and try again.', 'code' => 'CSRF_INVALID']));
            }
            die(json_encode(['error' => 'Invalid security token. Please refresh and try again.', 'code' => 'CSRF_INVALID']));
        }
    }

    /**
     * Validate CSRF token for HTML form submissions.
     * On failure: regenerates token, flashes error, redirects back.
     * On success: returns normally.
     * 
     * Use this for all normal HTML form POST handlers (login, register, unlock, admin forms).
     * Never shows raw JSON to the user.
     */
    public static function validateOrRedirect(string $redirectUrl, string $message = 'Security token expired. Please try again.'): void {
        if (self::validate()) {
            return; // Valid — continue
        }

        // Token invalid — regenerate for next attempt
        self::regenerate();

        // If this is an AJAX/API request, return JSON
        if (self::isAjaxRequest()) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(['error' => $message, 'code' => 'CSRF_INVALID']));
        }

        // Normal HTML form — redirect with flash message
        Session::flash('error', $message);
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Detect if the current request is AJAX/API (expects JSON response).
     */
    public static function isAjaxRequest(): bool {
        // XMLHttpRequest header (jQuery, fetch with custom header, etc.)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // Accept header prefers JSON
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        // Route starts with /api
        $route = trim($_GET['route'] ?? '', '/');
        if (str_starts_with($route, 'api/')) {
            return true;
        }

        // X-CSRF-TOKEN header present (typically sent by JS, not HTML forms)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return true;
        }

        return false;
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
