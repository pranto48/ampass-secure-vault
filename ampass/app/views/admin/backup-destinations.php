<?php
$destinations = $data['destinations'] ?? [];
$backups = $data['backups'] ?? [];
$csrfToken = $data['csrfToken'] ?? CSRF::generateToken();
$success = Session::flash('success');
$error = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Backup Destinations - AMPass Admin</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css"></head>
<body>
<div class="admin-page">
    <div class="admin-header"><a href="<?= APP_URL ?>/admin" class="btn-back">← Admin</a><h1>Remote Backup Destinations</h1></div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Add Destination -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Add Destination</h2></div>
        <div class="card-body">
            <div class="alert alert-warning" style="margin-bottom:12px;">⚠️ Plain FTP is not recommended. Use FTPS, SFTP, or OneDrive when possible. Only encrypted .ampass-backup files are uploaded.</div>
            <form method="POST" action="<?= APP_URL ?>/admin/backupDestinations/save">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" required placeholder="My Backup Server"></div>
                    <div class="form-group"><label class="form-label">Provider</label><select name="provider" class="form-select" required><option value="ftps">FTPS (recommended)</option><option value="ftp">FTP (insecure)</option><option value="sftp">SFTP</option><option value="onedrive">OneDrive</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Host</label><input type="text" name="host" class="form-input" placeholder="ftp.example.com"></div>
                    <div class="form-group"><label class="form-label">Port</label><input type="number" name="port" class="form-input" value="21"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-input"></div>
                </div>
                <div class="form-group"><label class="form-label">Remote Directory</label><input type="text" name="remote_directory" class="form-input" value="/ampass-backups"></div>
                <label class="checkbox-label"><input type="checkbox" name="passive_mode" checked> Passive mode (FTP/FTPS)</label>
                <button type="submit" class="btn btn-primary" style="margin-top:12px;">Add Destination</button>
            </form>
        </div>
    </div>

    <!-- Existing Destinations -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Destinations</h2><span class="badge"><?= count($destinations) ?></span></div>
        <div class="card-body">
            <?php if (empty($destinations)): ?><p class="text-muted">No remote destinations configured.</p>
            <?php else: ?>
            <table class="data-table"><thead><tr><th>Name</th><th>Provider</th><th>Status</th><th>Last Success</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($destinations as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($d['provider']) ?></span></td>
                <td><?= $d['enabled'] ? '✅ Enabled' : '❌ Disabled' ?></td>
                <td><?= $d['last_success_at'] ? date('M j g:i A', strtotime($d['last_success_at'])) : '—' ?></td>
                <td>
                    <form method="POST" action="<?= APP_URL ?>/admin/backupDestinations/test" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="btn btn-sm btn-ghost">Test</button></form>
                    <form method="POST" action="<?= APP_URL ?>/admin/backupDestinations/delete" style="display:inline;" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload to Remote -->
    <?php if (!empty($destinations) && !empty($backups)): ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Upload Backup to Remote</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= APP_URL ?>/admin/backupDestinations/upload">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Backup</label><select name="backup_id" class="form-select"><?php foreach ($backups as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['filename']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Destination</label><select name="destination_id" class="form-select"><?php foreach ($destinations as $d): ?><?php if ($d['enabled']): ?><option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['provider'] ?>)</option><?php endif; ?><?php endforeach; ?></select></div>
                </div>
                <button type="submit" class="btn btn-primary">Upload to Remote</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
