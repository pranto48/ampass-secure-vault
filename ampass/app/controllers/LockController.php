<?php
/**
 * AMPass - Lock Controller
 * Locks the vault without logging out.
 */

require_once __DIR__ . '/../models/AuditLog.php';

class LockController {

    public function index(): void {
        Session::lockVault();
        AuditLog::log('vault_locked', Session::getUserId());
        header('Location: ' . APP_URL . '/unlock');
        exit;
    }
}
