<?php
$csrfToken = $data['csrfToken'] ?? CSRF::generateToken();
$success = Session::flash('success');
$error = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Updates - AMPass Admin</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css"></head>
<body>
<div class="admin-page">
    <div class="admin-header"><a href="<?= APP_URL ?>/admin" class="btn-back">← Admin</a><h1>Updates</h1></div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Version Info -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Version</h2></div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><span>Installed:</span><strong>v<?= htmlspecialchars($data['current_version']) ?></strong></div>
                <div class="info-item"><span>Commit:</span><strong><code><?= htmlspecialchars(substr($data['installed_sha'], 0, 8) ?: '—') ?></code></strong></div>
                <div class="info-item"><span>Latest:</span><strong><?= $data['update_available'] ? '<span style="color:var(--warning);">v' . htmlspecialchars($data['latest_version']) . ' available</span>' : 'Up to date ✅' ?></strong></div>
                <div class="info-item"><span>Last checked:</span><strong><?= htmlspecialchars($data['last_checked']) ?></strong></div>
            </div>
            <form method="POST" action="<?= APP_URL ?>/admin/updates/check" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-secondary">Check for Updates</button>
            </form>
        </div>
    </div>

    <?php if ($data['update_available']): ?>
    <!-- Apply Update -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Apply Update</h2></div>
        <div class="card-body">
            <div class="alert alert-warning">⚠️ An encrypted backup will be created before updating. Do not close this page during update.</div>
            <form method="POST" action="<?= APP_URL ?>/admin/updates/apply">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-group"><label class="form-label">Backup Password (for pre-update backup)</label><input type="password" name="backup_password" class="form-input" required minlength="8"></div>
                <div class="form-group"><label class="form-label">Type <code>UPDATE AMPASS</code> to confirm</label><input type="text" name="confirmation" class="form-input" required pattern="UPDATE AMPASS" placeholder="UPDATE AMPASS"></div>
                <button type="submit" class="btn btn-primary">Update Now</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Migrations -->
    <?php if (!empty($data['pending_migrations'])): ?>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Pending Migrations</h2><span class="badge badge-warning"><?= count($data['pending_migrations']) ?></span></div>
        <div class="card-body">
            <ul style="padding-left:20px;font-size:0.85rem;"><?php foreach ($data['pending_migrations'] as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach; ?></ul>
            <form method="POST" action="<?= APP_URL ?>/admin/updates/migrations" style="margin-top:8px;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Run Pending Migrations</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update History -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Update History</h2></div>
        <div class="card-body">
            <?php if (empty($data['history'])): ?><p class="text-muted">No updates applied yet.</p>
            <?php else: ?>
            <table class="data-table"><thead><tr><th>From</th><th>To</th><th>Status</th><th>Date</th></tr></thead><tbody>
            <?php foreach ($data['history'] as $h): ?>
            <tr><td>v<?= htmlspecialchars($h['from_version']) ?></td><td>v<?= htmlspecialchars($h['to_version']) ?></td><td><span class="badge badge-<?= $h['status'] === 'completed' ? 'active' : 'suspended' ?>"><?= htmlspecialchars($h['status']) ?></span></td><td><?= date('M j, Y', strtotime($h['started_at'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted" style="margin-top:16px;font-size:0.72rem;">Source: github.com/pranto48/ampass-secure-vault • ⚠️ Requires professional security audit.</p>
</div>
</body>
</html>
