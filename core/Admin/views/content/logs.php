<?php
/**
 * Admin Logs - Content Only View
 * 
 * Available variables:
 * - $logs: Array of log entries
 * - $users: Array of admin users
 */
?>
<!-- Users Section -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">group</span>
            Users
        </span>
        <span class="badge badge-muted"><?= count($users ?? []) ?></span>
    </div>
    <?php if (!empty($users)): ?>
    <div class="card-body">
        <?php foreach ($users as $email => $userData): ?>
        <div class="list-item">
            <span class="list-label">
                <span>
                    <?= htmlspecialchars($userData['name'] ?? $email) ?>
                    <span class="text-xs text-tertiary d-block"><?= htmlspecialchars($email) ?></span>
                </span>
            </span>
            <span class="list-value text-sm text-tertiary">
                <?php if (!empty($userData['last_login'])): ?>
                    <?= date('M j, H:i', strtotime($userData['last_login'])) ?>
                <?php else: ?>
                    Never
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="material-symbols-rounded">group</span>
        <p>No users</p>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">list_alt</span>
            Recent Activity
        </span>
        <span class="badge badge-muted"><?= count($logs) ?> entries</span>
    </div>
    <?php if (!empty($logs)): ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Level</th>
                    <th>Message</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $levelClass = match(strtoupper($log['level'])) {
                        'ERROR' => 'badge-danger',
                        'WARNING' => 'badge-warning',
                        'INFO' => 'badge-success',
                        default => 'badge-muted',
                    };
                    // Extract user agent short name
                    $ua = $log['user_agent'] ?? '';
                    $uaShort = 'Unknown';
                    if (preg_match('/Firefox\/[\d.]+/', $ua)) $uaShort = 'Firefox';
                    elseif (preg_match('/Chrome\/[\d.]+/', $ua)) $uaShort = 'Chrome';
                    elseif (preg_match('/Safari\/[\d.]+/', $ua) && !str_contains($ua, 'Chrome')) $uaShort = 'Safari';
                    elseif (preg_match('/Edge\/[\d.]+/', $ua)) $uaShort = 'Edge';
                    elseif (!empty($ua)) $uaShort = 'Other';
                ?>
                <tr>
                    <td>
                        <div class="text-sm"><?= htmlspecialchars($log['timestamp']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $levelClass ?>"><?= htmlspecialchars($log['level']) ?></span>
                    </td>
                    <td>
                        <span class="log-message"><?= htmlspecialchars($log['message']) ?></span>
                    </td>
                    <td>
                        <code class="text-xs"><?= htmlspecialchars($log['ip'] ?? '—') ?></code>
                    </td>
                    <td>
                        <span class="text-secondary text-xs" title="<?= htmlspecialchars($ua) ?>"><?= $uaShort ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="material-symbols-rounded">history</span>
        <p>No admin activity logged yet.</p>
        <span class="text-secondary text-sm">Logs are created for admin activity such as logins and content changes.</span>
    </div>
    <?php endif; ?>
</div>

