<?php
/**
 * AMPass - Rate Limiting
 * SECURITY: Prevents brute force attacks on login and other sensitive endpoints.
 * Uses database-backed rate limiting with IP-based tracking.
 */

class RateLimit {

    /**
     * Check if an action is rate limited
     * 
     * @param string $identifier IP address or user identifier
     * @param string $action Action being rate limited (e.g., 'login', 'register')
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public static function check(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 900): bool {
        $db = Database::getInstance();
        
        // Clean up old entries
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$windowSeconds * 2]);

        // Check current state
        $stmt = $db->prepare(
            "SELECT attempts, first_attempt_at, locked_until FROM rate_limits 
             WHERE identifier = ? AND action = ?"
        );
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return true; // No previous attempts
        }

        // Check if currently locked
        if ($record['locked_until'] && strtotime($record['locked_until']) > time()) {
            return false;
        }

        // Check if window has expired
        if (strtotime($record['first_attempt_at']) < (time() - $windowSeconds)) {
            // Reset the counter
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$identifier, $action]);
            return true;
        }

        // Check attempt count
        return $record['attempts'] < $maxAttempts;
    }

    /**
     * Record an attempt
     */
    public static function record(string $identifier, string $action, int $maxAttempts = 5, int $lockoutSeconds = 900): void {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT id, attempts FROM rate_limits WHERE identifier = ? AND action = ?"
        );
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $stmt = $db->prepare(
                "INSERT INTO rate_limits (identifier, action, attempts, first_attempt_at, last_attempt_at) 
                 VALUES (?, ?, 1, NOW(), NOW())"
            );
            $stmt->execute([$identifier, $action]);
        } else {
            $newAttempts = $record['attempts'] + 1;
            $lockedUntil = null;
            
            if ($newAttempts >= $maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $lockoutSeconds);
            }

            $stmt = $db->prepare(
                "UPDATE rate_limits SET attempts = ?, last_attempt_at = NOW(), locked_until = ? 
                 WHERE id = ?"
            );
            $stmt->execute([$newAttempts, $lockedUntil, $record['id']]);
        }
    }

    /**
     * Clear rate limit for an identifier/action (e.g., after successful login)
     */
    public static function clear(string $identifier, string $action): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?");
        $stmt->execute([$identifier, $action]);
    }

    /**
     * Get remaining attempts
     */
    public static function getRemainingAttempts(string $identifier, string $action, int $maxAttempts = 5): int {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT attempts FROM rate_limits WHERE identifier = ? AND action = ?"
        );
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - $record['attempts']);
    }

    /**
     * Get lockout remaining time in seconds
     */
    public static function getLockoutRemaining(string $identifier, string $action): int {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT locked_until FROM rate_limits WHERE identifier = ? AND action = ?"
        );
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record || !$record['locked_until']) {
            return 0;
        }

        $remaining = strtotime($record['locked_until']) - time();
        return max(0, $remaining);
    }
}
