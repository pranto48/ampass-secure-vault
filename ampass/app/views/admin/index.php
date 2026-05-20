<?php
/**
 * AMPass - Admin Panel
 */
$totalUsers = $data['totalUsers'] ?? 0;
$users = $data['users'] ?? [];
$recentLogs = $data['recentLogs'] ?? [];
$csrfToken = $data['csrfToken'] ?? CSRF::generateToken();
$success = Session::flash('success');
$updateAvailable = $data['updateAvailable'] ?? false;
$latestVersion = $data['latestVersion'] ?? '';
$lastBackupDate = $data['lastBackupDate'] ?? null;
$lastRemoteBackupStatus = $data['lastRemoteBackupStatus'] ?? null;
$lastRemoteBackupDate = $data['lastRemoteBackupDate'] ?? null;
$emailConfigured = $data['emailConfigured'] ?? false;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - AMPass</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>
    <div class="admin-page">
        <div class="admin-header">
            <a href="<?= APP_URL ?>/dashboard" class="btn-back">← Back to Dashboard</a>
            <h1>Admin Panel</h1>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Admin Nav -->
        <div class="admin-nav">
            <a href="<?= APP_URL ?>/admin" class="admin-nav-item active">Overview</a>
            <a href="<?= APP_URL ?>/admin/users" class="admin-nav-item">Users</a>
            <a href="<?= APP_URL ?>/admin/settings" class="admin-nav-item">Settings</a>
            <a href="<?= APP_URL ?>/admin/logs" class="admin-nav-item">Audit Logs</a>
            <a href="<?= APP_URL ?>/admin/extensions" class="admin-nav-item">Extensions</a>
            <a href="<?= APP_URL ?>/admin/updates" class="admin-nav-item">Updates</a>
            <a href="<?= APP_URL ?>/admin/backups" class="admin-nav-item">Backups</a>
            <a href="<?= APP_URL ?>/admin/backup-destinations" class="admin-nav-item">Remote Backup</a>
            <a href="<?= APP_URL ?>/admin/releases" class="admin-nav-item">Releases</a>
            <a href="<?= APP_URL ?>/admin/email" class="admin-nav-item">Email</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= $totalUsers ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= defined('INSTALL_LOCKED') && INSTALL_LOCKED ? '🔒 Locked' : '⚠️ Open' ?></span>
                    <span class="stat-label">Installer Status</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= Security::isHTTPS() ? '✅ Active' : '⚠️ Off' ?></span>
                    <span class="stat-label">HTTPS</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= PHP_VERSION ?></span>
                    <span class="stat-label">PHP Version</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= $updateAvailable ? '⬆️ v' . htmlspecialchars($latestVersion) : '✅ Current' ?></span>
                    <span class="stat-label"><a href="<?= APP_URL ?>/admin/updates" style="color:inherit;text-decoration:none;">Updates</a></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= $lastBackupDate ? date('M j', strtotime($lastBackupDate)) : '—' ?></span>
                    <span class="stat-label"><a href="<?= APP_URL ?>/admin/backups" style="color:inherit;text-decoration:none;">Last Backup</a></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= $lastRemoteBackupStatus === 'uploaded' ? '✅ OK' : ($lastRemoteBackupStatus === 'failed' ? '❌ Failed' : '—') ?></span>
                    <span class="stat-label"><a href="<?= APP_URL ?>/admin/backup-destinations" style="color:inherit;text-decoration:none;">Remote Backup</a></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-value"><?= $emailConfigured ? '✅ Active' : '⚠️ Not Set' ?></span>
                    <span class="stat-label"><a href="<?= APP_URL ?>/admin/email" style="color:inherit;text-decoration:none;">Email</a></span>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Users</h2>
                <a href="<?= APP_URL ?>/admin/users" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($users, 0, 10) as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Audit Logs -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Activity</h2>
                <a href="<?= APP_URL ?>/admin/logs" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <div class="audit-log-list">
                    <?php foreach (array_slice($recentLogs, 0, 10) as $log): ?>
                    <div class="audit-log-item">
                        <div class="log-action"><?= htmlspecialchars($log['action']) ?></div>
                        <div class="log-details">
                            <span class="log-user"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                            <span class="log-ip"><?= htmlspecialchars($log['ip_address'] ?? '') ?></span>
                            <span class="log-time"><?= date('M j g:i A', strtotime($log['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
