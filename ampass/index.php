<?php
/**
 * AMPass - Secure Password Vault
 * Main entry point / Front controller
 * 
 * SECURITY: This file routes all requests through the application.
 * Ensure .htaccess is properly configured to route all traffic here.
 */

// Check if installed — redirect to installer if config doesn't exist
if (!file_exists(__DIR__ . '/config/config.php')) {
    // Use relative redirect so it works in any subdirectory (XAMPP, cPanel, etc.)
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $scriptDir . '/install/index.php');
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/core/App.php';
require_once __DIR__ . '/app/core/Database.php';
require_once __DIR__ . '/app/core/Session.php';
require_once __DIR__ . '/app/core/CSRF.php';
require_once __DIR__ . '/app/core/RateLimit.php';
require_once __DIR__ . '/app/core/Security.php';

// Initialize security headers
Security::setHeaders();

// Start secure session
Session::start();

// Route the request
$app = new App();
$app->run();
