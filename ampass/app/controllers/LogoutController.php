<?php
/**
 * AMPass - Logout Controller
 */

require_once __DIR__ . '/../models/AuditLog.php';

class LogoutController {

    public function index(): void {
        $userId = Session::getUserId();
        if ($userId) {
            AuditLog::log('logout', $userId);
        }
        Session::destroy();
        Session::start();
        Session::flash('success', 'You have been logged out.');
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}
