<?php
/**
 * Dashboard - Content Only View
 * 
 * Available variables:
 * - $site: Site configuration
 * - $cache: Cache status
 * - $webpageCache: Webpage cache stats
 * - $content: Content stats per type
 * - $taxonomies: Taxonomy term counts
 * - $taxonomyConfig: Taxonomy configuration
 * - $system: System info
 * - $recentContent: Recent content items
 * - $plugins: Active plugins
 * - $users: All users
 * - $contentTypes: Content type configuration
 * - $routes: Routes array
 * - $customPages: Custom admin pages
 * - $updateCheck: Update check result
 * - $recentErrorCount: Recent error count
 * - $csrf: CSRF token
 */

// Helper to get taxonomy URL base
$getTaxonomyBase = function($taxName) use ($taxonomyConfig, $site) {
    $config = $taxonomyConfig[$taxName] ?? [];
    $base = $config['rewrite']['base'] ?? '/' . $taxName;
    return rtrim($site['url'], '/') . $base;
};

// Get preview token for draft links
$previewToken = $ava->config('security.preview_token');

// Get URL path for an item from routes (or generate for drafts)
$getContentPath = function($item) use ($routes, $contentTypes) {
    $type = $item->type();
    $slug = $item->slug();
    
    // First check if it's in routes (published content)
    foreach ($routes['exact'] ?? [] as $routeUrl => $routeData) {
        if (($routeData['content_type'] ?? '') === $type && ($routeData['slug'] ?? '') === $slug) {
            return $routeUrl;
        }
    }
    
    // For drafts, generate URL from pattern
    if ($item->isDraft()) {
        $typeConfig = $contentTypes[$type] ?? [];
        $urlConfig = $typeConfig['url'] ?? [];
        $urlType = $urlConfig['type'] ?? 'pattern';
        $pattern = $urlConfig['pattern'] ?? ($urlType === 'hierarchical' ? '/{slug}' : '/' . $type . '/{slug}');
        
        // Simple replacement
        $path = str_replace('{slug}', $slug, $pattern);
        return $path;
    }
    
    return null;
};

// Helper to get content URL (with preview for drafts)
$getContentUrl = function($item) use ($site, $previewToken, $getContentPath) {
    $path = $getContentPath($item);
    if (!$path) {
        return null;
    }
    
    $url = rtrim($site['url'], '/') . $path;
    if ($item->isDraft() && $previewToken) {
        $url .= '?preview=1&token=' . urlencode($previewToken);
    }
    return $url;
};

// Format bytes helper
$formatBytes = function($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
};

// Stats
$totalContent = array_sum(array_column($content, 'total'));
$totalPublished = array_sum(array_column($content, 'published'));
$totalDrafts = array_sum(array_column($content, 'draft'));
$totalTerms = array_sum($taxonomies);
$renderTime = round((microtime(true) - $system['request_time']) * 1000, 2);

?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'rebuild'): ?>
<div class="alert alert-success">
    <span class="material-symbols-rounded">check_circle</span>
    <div class="flex-1">
        Content index rebuilt in <?= htmlspecialchars($_GET['time'] ?? '?') ?>ms
        <?php if (isset($_GET['keep_webpage_cache']) && $_GET['keep_webpage_cache'] === '1'): ?>
            <div class="text-xs opacity-80">Webpage cache kept.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'flush_pages'): ?>
<div class="alert alert-success">
    <span class="material-symbols-rounded">check_circle</span>
    Webpage cache cleared (<?= htmlspecialchars($_GET['count'] ?? '0') ?> webpages)
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'index_mode_changed'): ?>
<div class="alert alert-success">
    <span class="material-symbols-rounded">check_circle</span>
    Index mode changed to <code><?= htmlspecialchars($_GET['mode'] ?? 'unknown') ?></code>
</div>
<?php endif; ?>

<?php if ($cache['mode'] === 'always'): ?>
<div class="alert alert-danger">
    <span class="material-symbols-rounded">error</span>
    <div class="flex-1">
        <strong>Index mode: always</strong> — Rebuilds the entire index on <strong>every</strong> page load. This <strong>severely impacts performance</strong> and should only be used briefly for debugging. Switch back to <code>never</code> immediately when done.
    </div>
</div>
<?php elseif ($cache['mode'] === 'auto'): ?>
<div class="alert alert-info">
    <span class="material-symbols-rounded">info</span>
    <div class="flex-1">
        <strong>Index mode: auto</strong> — Checks for file changes before serving. Good for development and editing, but switch to <code>never</code> in production for optimal performance.
    </div>
</div>
<?php endif; ?>

<?php if (isset($recentErrorCount) && $recentErrorCount > 0): ?>
<div class="alert alert-warning">
    <span class="material-symbols-rounded">warning</span>
    <div class="flex-1">
        <strong><?= $recentErrorCount ?> error<?= $recentErrorCount !== 1 ? 's' : '' ?> logged in the last 24 hours</strong>
        <br>
        <span class="text-xs opacity-80">
            <a href="<?= htmlspecialchars($admin_url) ?>/system" class="link-inherit">View errors in System Info</a>
        </span>
    </div>
</div>
<?php endif; ?>

<?php if ($updateCheck && $updateCheck['available']): ?>
<div class="alert alert-info mb-5">
    <span class="material-symbols-rounded">system_update</span>
    <div class="flex-1">
        <strong>Update available:</strong> v<?= htmlspecialchars($updateCheck['latest']) ?>
        <br>
        <span class="text-xs opacity-70">
               Run <code>./ava update:apply</code> or <a href="https://ava.addy.zone/docs/updates" target="_blank" rel="noopener noreferrer" class="link-inherit">see the update guide</a>
        </span>
    </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">
            <span class="material-symbols-rounded">folder</span>
            Content
        </div>
        <div class="stat-value"><?= $totalContent ?></div>
        <div class="stat-meta"><?= count($content) ?> type<?= count($content) !== 1 ? 's' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">
            <span class="material-symbols-rounded">public</span>
            Published
        </div>
        <div class="stat-value text-success"><?= $totalPublished ?></div>
        <div class="stat-meta">Live on site</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">
            <span class="material-symbols-rounded">edit_note</span>
            Drafts
        </div>
        <div class="stat-value text-warning"><?= $totalDrafts ?></div>
        <div class="stat-meta"><?= $totalDrafts > 0 ? 'Pending' : 'None' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">
            <span class="material-symbols-rounded">sell</span>
            Terms
        </div>
        <div class="stat-value"><?= $totalTerms ?></div>
        <div class="stat-meta"><?= count($taxonomies) ?> taxonom<?= count($taxonomies) !== 1 ? 'ies' : 'y' ?></div>
    </div>
</div>

<!-- Top Row: Content Index, Webpage Cache, Site -->
<div class="grid grid-3">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">database</span>
                Content Index
            </span>
            <span class="badge <?= $cache['fresh'] ? 'badge-success' : 'badge-warning' ?>">
                <?= $cache['fresh'] ? 'Fresh' : 'Stale' ?>
            </span>
        </div>
        <div class="card-body">
            <?php
            $modeClass = match($cache['mode']) {
                'never' => 'index-mode-select-success',
                'auto' => 'index-mode-select-info',
                'always' => 'index-mode-select-danger',
                default => 'index-mode-select-info',
            };
            ?>
            <div class="list-item">
                <span class="list-label">Mode</span>
                <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/index-mode" class="d-inline" id="index-mode-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <select name="mode" class="index-mode-select <?= $modeClass ?>" onchange="this.form.submit()">
                        <option value="never" <?= $cache['mode'] === 'never' ? 'selected' : '' ?>>never (production)</option>
                        <option value="auto" <?= $cache['mode'] === 'auto' ? 'selected' : '' ?>>auto (development)</option>
                        <option value="always" <?= $cache['mode'] === 'always' ? 'selected' : '' ?>>always (debug)</option>
                    </select>
                </form>
            </div>
            <div class="list-item">
                <span class="list-label">Last Built</span>
                <span class="list-value text-sm"><?= htmlspecialchars($cache['built_at'] ?? 'Never') ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Total Size</span>
                <span class="list-value"><?= $formatBytes($cache['size'] ?? 0) ?></span>
            </div>
            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/rebuild" class="mt-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span class="material-symbols-rounded">autorenew</span>
                        Rebuild Now
                    </button>
                    <button type="submit" name="keep_webpage_cache" value="1" class="btn btn-secondary btn-sm">
                        <span class="material-symbols-rounded">file_copy</span>
                        Rebuild (Keep Cache)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">bolt</span>
                Webpage Cache
            </span>
            <span class="badge <?= $webpageCache['enabled'] ? 'badge-success' : 'badge-secondary' ?>">
                <?= $webpageCache['enabled'] ? 'On' : 'Off' ?>
            </span>
        </div>
        <div class="card-body">
            <?php if ($webpageCache['enabled']): ?>
            <div class="list-item">
                <span class="list-label">Cached Webpages</span>
                <span class="list-value"><?= $webpageCache['count'] ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Size</span>
                <span class="list-value"><?= $formatBytes($webpageCache['size'] ?? 0) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">TTL</span>
                <span class="list-value"><?= $webpageCache['ttl'] ? $webpageCache['ttl'] . 's' : 'Forever' ?></span>
            </div>
            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/flush-pages" class="mt-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="btn btn-secondary btn-sm" <?= $webpageCache['count'] > 0 ? '' : 'disabled' ?>>
                    <span class="material-symbols-rounded">delete_sweep</span>
                    Clear Webpage Cache
                </button>
            </form>
            <?php else: ?>
            <p class="text-dim text-sm m-0">Enable in config for faster page loads.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">language</span>
                Site
            </span>
                <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-secondary">View</a>
        </div>
        <div class="card-body">
            <div class="list-item">
                <span class="list-label">Name</span>
                <span class="list-value"><?= htmlspecialchars($site['name']) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">URL</span>
                <span class="list-value text-sm"><?= htmlspecialchars(preg_replace('#^https?://#', '', $site['url'])) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Theme</span>
                <span class="list-value"><?= htmlspecialchars($ava->config('theme', 'default')) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Timezone</span>
                <span class="list-value"><?= htmlspecialchars($site['timezone'] ?? 'UTC') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Content -->
<div class="card mt-5">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">schedule</span>
            Recent Content
        </span>
        <span class="badge badge-muted"><?= count($recentContent) ?></span>
    </div>
    <?php if (!empty($recentContent)): ?>
        <?php foreach ($recentContent as $item): 
            $itemUrl = $getContentUrl($item);
            $isDraft = !$item->isPublished();
        ?>
        <div class="content-item">
            <?php if ($itemUrl): ?>
                <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" rel="noopener noreferrer" class="content-item-link">
            <?php else: ?>
            <div class="content-item-link">
            <?php endif; ?>
                <div>
                    <div class="content-title"><?= htmlspecialchars($item->title()) ?></div>
                    <div class="content-meta">
                        <span><?= $item->type() ?></span>
                        <span>·</span>
                        <span><?= $item->date() ? $item->date()->format('M j, Y') : 'No date' ?></span>
                        <?php if ($isDraft && $previewToken): ?>
                        <span class="preview-link">
                            <span class="material-symbols-rounded">visibility</span>
                            Preview
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="badge <?= $item->isPublished() ? 'badge-success' : 'badge-warning' ?>">
                    <?= $item->status() ?>
                </span>
            <?php if ($itemUrl): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <span class="material-symbols-rounded">article</span>
            <p>No content yet</p>
        </div>
    <?php endif; ?>
</div>

