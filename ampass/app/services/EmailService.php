<?php
/**
 * AMPass - Email Service
 * Sends emails via Resend API using cURL (no Composer dependencies).
 * 
 * SECURITY:
 * - API key encrypted at rest
 * - OTP/tokens stored as hashes only
 * - No passwords or vault data in emails
 * - Rate limited sending
 */

class EmailService {

    private static ?string $apiKey = null;
    private static ?string $fromEmail = null;
    private static ?string $fromName = null;

    /**
     * Initialize from app_settings
     */
    private static function init(): bool {
        if (self::$apiKey !== null) return !empty(self::$apiKey);

        $settings = Database::fetchAll(
            "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('resend_api_key_encrypted','resend_from_email','resend_from_name')"
        );
        $map = [];
        foreach ($settings as $s) $map[$s['setting_key']] = $s['setting_value'];

        $encryptedKey = $map['resend_api_key_encrypted'] ?? '';
        self::$fromEmail = $map['resend_from_email'] ?? '';
        self::$fromName = $map['resend_from_name'] ?? 'AMPass';

        if (empty($encryptedKey) || empty(self::$fromEmail)) {
            self::$apiKey = '';
            return false;
        }

        // Decrypt API key
        self::$apiKey = self::decryptApiKey($encryptedKey);
        return !empty(self::$apiKey);
    }

    /**
     * Send an email via Resend API
     */
    public static function send(string $to, string $subject, string $html, string $text = '', array $tags = []): array {
        if (!self::init()) {
            return ['success' => false, 'error' => 'Email not configured'];
        }

        $payload = [
            'from' => self::$fromName . ' <' . self::$fromEmail . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $html
        ];
        if ($text) $payload['text'] = $text;
        if ($tags) $payload['tags'] = array_map(fn($t) => ['name' => $t], $tags);

        $replyTo = Database::fetchOne("SELECT setting_value FROM app_settings WHERE setting_key = 'resend_reply_to'");
        if ($replyTo && !empty($replyTo['setting_value'])) {
            $payload['reply_to'] = $replyTo['setting_value'];
        }

        // Send via cURL
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::$apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            self::logEmail(null, 'send', $to, 'failed', 'cURL error: ' . $curlError);
            return ['success' => false, 'error' => 'Network error sending email'];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            self::logEmail(null, 'send', $to, 'sent', null, $data['id'] ?? null);
            return ['success' => true, 'message_id' => $data['id'] ?? null];
        }

        $error = $data['message'] ?? $data['error'] ?? 'Unknown error';
        self::logEmail(null, 'send', $to, 'failed', $error);

        if ($httpCode === 401) return ['success' => false, 'error' => 'Invalid Resend API key'];
        if ($httpCode === 429) return ['success' => false, 'error' => 'Rate limited by Resend'];
        return ['success' => false, 'error' => 'Email send failed: ' . $error];
    }

    /**
     * Send a security alert email
     */
    public static function sendSecurityAlert(string $to, string $event, array $details = []): array {
        $html = self::renderTemplate('security-alert', [
            'event' => $event,
            'time' => date('M j, Y g:i A T'),
            'ip' => $details['ip'] ?? Security::getClientIP(),
            'device' => $details['device'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
            'action_url' => (defined('APP_URL') ? APP_URL : '') . '/settings/security'
        ]);
        return self::send($to, 'AMPass Security Alert: ' . $event, $html, '', ['security']);
    }

    /**
     * Send OTP email for 2FA
     */
    public static function sendOTP(string $to, string $otp, string $purpose = 'login'): array {
        $html = self::renderTemplate('login-otp', [
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_minutes' => 10
        ]);
        return self::send($to, 'AMPass Verification Code: ' . $otp, $html, "Your AMPass code is: {$otp}\nExpires in 10 minutes.", ['otp']);
    }

    /**
     * Send password reset email
     */
    public static function sendPasswordReset(string $to, string $resetUrl): array {
        $html = self::renderTemplate('password-reset', [
            'reset_url' => $resetUrl,
            'expires_minutes' => 30
        ]);
        return self::send($to, 'Reset Your AMPass Password', $html, "Reset your password: {$resetUrl}\nExpires in 30 minutes.", ['password-reset']);
    }

    /**
     * Render an email template
     */
    private static function renderTemplate(string $template, array $data = []): string {
        $templatePath = __DIR__ . '/../views/emails/' . $template . '.php';
        if (!file_exists($templatePath)) {
            // Fallback: simple HTML
            $html = '<h2>AMPass</h2>';
            foreach ($data as $k => $v) {
                if (!is_array($v)) $html .= '<p><strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars($v) . '</p>';
            }
            return $html;
        }

        extract($data);
        ob_start();
        require $templatePath;
        return ob_get_clean();
    }

    /**
     * Log email send attempt
     */
    private static function logEmail(?int $userId, string $type, string $to, string $status, ?string $error = null, ?string $messageId = null): void {
        try {
            Database::insert(
                "INSERT INTO email_logs (user_id, email_type, to_email_hash, provider, provider_message_id, status, error_message, created_at) VALUES (?, ?, ?, 'resend', ?, ?, ?, NOW())",
                [$userId, $type, hash('sha256', $to), $messageId, $status, $error]
            );
        } catch (\Exception $e) { /* ignore logging failures */ }
    }

    /**
     * Encrypt API key for storage
     */
    public static function encryptApiKey(string $apiKey): string {
        if (!defined('APP_SECRET')) return base64_encode($apiKey); // Fallback
        $key = hash('sha256', APP_SECRET, true);
        $iv = random_bytes(12);
        $encrypted = openssl_encrypt($apiKey, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt API key from storage
     */
    private static function decryptApiKey(string $encrypted): string {
        if (!defined('APP_SECRET')) return base64_decode($encrypted); // Fallback
        $data = base64_decode($encrypted);
        if (strlen($data) < 28) return ''; // iv(12) + tag(16) + at least 1 byte
        $key = hash('sha256', APP_SECRET, true);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted ?: '';
    }

    /**
     * Check if email is configured
     */
    public static function isConfigured(): bool {
        return self::init();
    }
}
