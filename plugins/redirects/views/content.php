<?php
/**
 * Redirects Plugin Admin View - Content Only
 * 
 * Redirects are managed via CLI for security. This view shows
 * existing redirects and CLI documentation.
 * 
 * Available variables:
 * - $redirects: Array of redirect entries
 * - $csrf: CSRF token for forms
 * - $statusCodes: Array of supported status codes
 * - $storagePath: Path to redirects.json
 * - $admin_url: Admin base URL
 * - $app: Application instance
 * - $jsonError: JSON parsing error message (if any)
 */
?>

<?php if ($jsonError): ?>
<div class="alert alert-danger">
    <span class="material-symbols-rounded">warning</span>
    <div>
        <strong>Malformed redirects file</strong><br>
        <?= htmlspecialchars($jsonError) ?><br>
        <code style="font-size: var(--text-xs); opacity: 0.8;"><?= htmlspecialchars($storagePath) ?></code>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">terminal</span>
                Managing Redirects
            </span>
        </div>
        <div class="card-body">
            <p class="text-secondary text-sm" style="margin-bottom: var(--sp-4);">
                Redirects are managed via the command line for security. Use these commands:
            </p>
            
            <div style="background: var(--bg-surface); border-radius: var(--radius-md); padding: var(--sp-3); margin-bottom: var(--sp-3);">
                <p class="text-xs text-tertiary" style="margin-bottom: var(--sp-1);">Add a redirect:</p>
                <code class="text-sm" style="color: var(--text-accent);">./ava redirects:add /old-path /new-path</code>
            </div>
            
            <div style="background: var(--bg-surface); border-radius: var(--radius-md); padding: var(--sp-3); margin-bottom: var(--sp-3);">
                <p class="text-xs text-tertiary" style="margin-bottom: var(--sp-1);">Add with status code:</p>
                <code class="text-sm" style="color: var(--text-accent);">./ava redirects:add /old-path /new-path 302</code>
            </div>
            
            <div style="background: var(--bg-surface); border-radius: var(--radius-md); padding: var(--sp-3); margin-bottom: var(--sp-3);">
                <p class="text-xs text-tertiary" style="margin-bottom: var(--sp-1);">Mark page as gone (410):</p>
                <code class="text-sm" style="color: var(--text-accent);">./ava redirects:add /deleted-page "" 410</code>
            </div>
            
            <div style="background: var(--bg-surface); border-radius: var(--radius-md); padding: var(--sp-3); margin-bottom: var(--sp-3);">
                <p class="text-xs text-tertiary" style="margin-bottom: var(--sp-1);">List all redirects:</p>
                <code class="text-sm" style="color: var(--text-accent);">./ava redirects:list</code>
            </div>
            
            <div style="background: var(--bg-surface); border-radius: var(--radius-md); padding: var(--sp-3);">
                <p class="text-xs text-tertiary" style="margin-bottom: var(--sp-1);">Remove a redirect:</p>
                <code class="text-sm" style="color: var(--text-accent);">./ava redirects:remove /old-path</code>
            </div>
            
            <p class="text-tertiary text-xs" style="margin-top: var(--sp-4);">
                <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle;">info</span>
                You can also edit <code>storage/redirects.json</code> directly, or use <code>redirect_from</code> in content frontmatter.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">info</span>
                Status Codes
            </span>
        </div>
        <div class="card-body">
            <div class="list-item">
                <span class="list-label">Storage File</span>
                <span class="list-value text-sm"><code style="font-size: var(--text-xs);"><?= htmlspecialchars(str_replace($app->path(''), '', $storagePath)) ?></code></span>
            </div>
            
            <p class="text-secondary text-sm" style="margin-top: var(--sp-4); margin-bottom: var(--sp-3);">
                <strong>Supported codes:</strong>
            </p>
            
            <?php foreach ($statusCodes as $code => $info): ?>
            <div class="list-item" style="padding: var(--sp-2) 0;">
                <span class="list-label">
                    <span class="badge <?= $info['redirect'] ? ($code === 301 || $code === 308 ? 'badge-success' : 'badge-warning') : 'badge-danger' ?>"><?= $code ?></span>
                </span>
                <span class="list-value text-sm text-secondary">
                    <?= htmlspecialchars($info['label']) ?>
                    <?= $info['redirect'] ? '' : '<span class="text-xs text-tertiary">(no dest.)</span>' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($redirects)): ?>
<div class="card mt-5">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">list</span>
            Active Entries
        </span>
        <span class="badge badge-muted"><?= count($redirects) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>From</th>
                    <th>Response</th>
                    <th>To</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($redirects as $redirect): 
                    $code = (int) ($redirect['code'] ?? 301);
                    $codeInfo = $statusCodes[$code] ?? ['label' => 'Unknown', 'redirect' => true];
                    $isRedirect = $codeInfo['redirect'];
                    $badgeClass = $isRedirect ? ($code === 301 || $code === 308 ? 'badge-success' : 'badge-warning') : 'badge-danger';
                ?>
                <tr>
                    <td><code><?= htmlspecialchars($redirect['from']) ?></code></td>
                    <td>
                        <span class="badge <?= $badgeClass ?>"><?= $code ?></span>
                        <span class="text-xs text-tertiary"><?= htmlspecialchars($codeInfo['label']) ?></span>
                    </td>
                    <td>
                        <?php if ($isRedirect && !empty($redirect['to'])): ?>
                            <?php if (str_starts_with($redirect['to'], 'http')): ?>
                                <a href="<?= htmlspecialchars($redirect['to']) ?>" target="_blank" class="text-accent">
                                    <?= htmlspecialchars($redirect['to']) ?>
                                    <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle;">open_in_new</span>
                                </a>
                            <?php else: ?>
                                <code><?= htmlspecialchars($redirect['to']) ?></code>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-tertiary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-tertiary"><?= htmlspecialchars($redirect['created'] ?? 'Unknown') ?></td>
                    <td>
                        <form method="POST" action="<?= $admin_url ?>/redirects" style="display: inline;">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="from" value="<?= htmlspecialchars($redirect['from']) ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Delete this entry?')">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card mt-5">
    <div class="empty-state">
        <span class="material-symbols-rounded">swap_horiz</span>
        <p>No entries configured</p>
        <span class="text-sm text-tertiary">Use <code>./ava redirects:add</code> to add redirects</span>
    </div>
</div>
<?php endif; ?>
