<?php
/**
 * AMPass - Secure Password Vault
 * Main entry point / Front controller
 * 
 * SECURITY: This file routes all requests through the application.
 * Ensure .htaccess is properly configured to route all traffic here.
 */

// Check if installed
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: /install/index.php');
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
