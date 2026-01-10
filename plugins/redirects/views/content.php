<?php
/**
 * Redirects Plugin Admin View - Content Only
 * 
 * Available variables:
 * - $redirects: Array of redirect entries
 * - $csrf: CSRF token for forms
 * - $statusCodes: Array of supported status codes
 * - $storagePath: Path to redirects.json
 * - $admin_url: Admin base URL
 * - $app: Application instance
 * - $jsonError: JSON parsing error message (if any)
 * - $adminPath: Configured admin path
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
    <!-- Create Redirect Form -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">add</span>
                Add Redirect
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= $admin_url ?>/redirects">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="from" class="form-label">Source Path <span class="text-danger">*</span></label>
                    <input type="text" id="from" name="from" class="form-control" required
                           placeholder="/old-page"
                           pattern="/.*"
                           value="<?= htmlspecialchars($_POST['from'] ?? '') ?>">
                </div>
                
                <div class="form-group" id="destination-group">
                    <label for="to" class="form-label">Destination</label>
                    <input type="text" id="to" name="to" class="form-control"
                           placeholder="/new-page or https://example.com"
                           value="<?= htmlspecialchars($_POST['to'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="code" class="form-label">Status Code</label>
                    <select id="code" name="code" class="form-control">
                        <?php foreach ($statusCodes as $codeNum => $info): ?>
                        <option value="<?= $codeNum ?>" <?= ($_POST['code'] ?? 301) == $codeNum ? 'selected' : '' ?> data-redirect="<?= $info['redirect'] ? 'true' : 'false' ?>">
                            <?= $codeNum ?> - <?= htmlspecialchars($info['label']) ?>
                            <?= $info['redirect'] ? '' : '(no destination)' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-rounded">add</span>
                    Add Redirect
                </button>
            </form>
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
        <span class="text-sm text-tertiary">Use the form above to add redirects</span>
    </div>
</div>
<?php endif; ?>

<script>
// Update destination field based on status code
const codeSelect = document.getElementById('code');
const destGroup = document.getElementById('destination-group');
const toField = document.getElementById('to');

function updateDestinationVisibility() {
    const selectedOption = codeSelect.options[codeSelect.selectedIndex];
    const needsDestination = selectedOption.dataset.redirect === 'true';
    
    if (needsDestination) {
        destGroup.style.display = '';
        toField.required = true;
        toField.placeholder = '/new-page or https://example.com';
    } else {
        destGroup.style.display = 'none';
        toField.required = false;
        toField.value = '';
    }
}

codeSelect.addEventListener('change', updateDestinationVisibility);
updateDestinationVisibility(); // Set initial state
</script>
