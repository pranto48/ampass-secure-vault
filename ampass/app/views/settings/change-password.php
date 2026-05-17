<?php
$csrfToken = $csrfToken ?? CSRF::generateToken();
$error = Session::flash('error');
$success = Session::flash('success');
?>

<div class="page-header">
    <a href="<?= APP_URL ?>/settings" class="btn-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
    </a>
    <h1 class="page-title">Change Password</h1>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="max-width: 500px;">
    <div class="card-body">
        <form method="POST" action="<?= APP_URL ?>/settings/updatePassword">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-input" required autocomplete="new-password" minlength="12">
                <span class="form-hint">Minimum 12 characters with mixed case, numbers, and symbols</span>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-input" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>
