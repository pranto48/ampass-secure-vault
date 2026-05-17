<?php
$logs = $logs ?? [];
?>

<div class="page-header">
    <a href="<?= APP_URL ?>/settings" class="btn-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
    </a>
    <h1 class="page-title">Security Log</h1>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($logs)): ?>
        <p class="text-muted">No security events recorded yet.</p>
        <?php else: ?>
        <div class="audit-log-list">
            <?php foreach ($logs as $log): ?>
            <div class="audit-log-item">
                <div class="log-action"><?= htmlspecialchars($log['action']) ?></div>
                <div class="log-details">
                    <span class="log-ip"><?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?></span>
                    <span class="log-time"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
