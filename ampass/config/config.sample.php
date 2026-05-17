<?php
/**
 * AMPass Configuration File (Sample)
 * Copy this to config.php and fill in your values.
 * The installer will generate this automatically.
 * 
 * SECURITY: Never commit config.php to version control.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ampass_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'AMPass');
define('APP_URL', 'http://localhost/ampass');
define('APP_VERSION', '1.0.0');

// Security Keys (generated during installation)
define('APP_SECRET', ''); // 64-char hex string
define('ENCRYPTION_KEY', ''); // 64-char hex string for server-side operations
define('CSRF_SECRET', ''); // 32-char hex string

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'ampass_session');
define('VAULT_LOCK_TIMEOUT', 300); // 5 minutes of inactivity locks vault

// Rate Limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Registration
define('REGISTRATION_ENABLED', true);

// Installation lock
define('INSTALL_LOCKED', true);

// Debug mode (NEVER enable in production)
define('DEBUG_MODE', false);

// Timezone
define('APP_TIMEZONE', 'UTC');
