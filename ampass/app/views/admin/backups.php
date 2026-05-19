<?php
$backups = $data['backups'] ?? [];
$csrfToken = $data['csrfToken'] ?? CSRF::generateToken();
$success = Session::flash('success');
$error = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backups - AMPass Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>
<div class="admin-page">
    <div class="admin-header"><a href="<?= APP_URL ?>/admin" class="btn-back">← Admin</a><h1>Encrypted Backups</h1></div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Create Backup -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Create Encrypted Backup</h2></div>
        <div class="card-body">
            <div class="alert alert-warning" style="margin-bottom:12px;">⚠️ If you lose the backup password, AMPass cannot recover this backup.</div>
            <form method="POST" action="<?= APP_URL ?>/admin/backups/create">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Backup Password</label><input type="password" name="backup_password" class="form-input" required minlength="8"></div>
                    <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" name="backup_password_confirm" class="form-input" required></div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label"><input type="checkbox" name="include_files"> Include release files</label>
                    <label class="checkbox-label"><input type="checkbox" name="include_audit"> Include audit logs</label>
                </div>
                <button type="submit" class="btn btn-primary">Create Backup</button>
            </form>
        </div>
    </div>

    <!-- Existing Backups -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Backup Files</h2><span class="badge"><?= count($backups) ?></span></div>
        <div class="card-body">
            <?php if (empty($backups)): ?>
            <p class="text-muted">No backups created yet.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>File</th><th>Size</th><th>Type</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['filename']) ?></td>
                    <td><?= number_format($b['file_size']/1048576, 1) ?> MB</td>
                    <td><span class="badge"><?= htmlspecialchars($b['backup_type']) ?></span></td>
                    <td><?= date('M j, Y g:i A', strtotime($b['created_at'])) ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/backups?download=<?= $b['id'] ?>" class="btn btn-sm btn-ghost">Download</a>
                        <form method="POST" action="<?= APP_URL ?>/admin/backups/delete" style="display:inline;" onsubmit="return confirm('Delete this backup?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted" style="margin-top:16px;font-size:0.75rem;">⚠️ AMPass requires professional security audit before real credential storage.</p>
</div>
</body>
</html>
