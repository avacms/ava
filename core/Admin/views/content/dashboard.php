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
    if (!$item->isPublished()) {
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
    if (!$item->isPublished() && $previewToken) {
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
    Content index rebuilt in <?= htmlspecialchars($_GET['time'] ?? '?') ?>ms
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'flush_pages'): ?>
<div class="alert alert-success">
    <span class="material-symbols-rounded">check_circle</span>
    Webpage cache cleared (<?= htmlspecialchars($_GET['count'] ?? '0') ?> webpages)
</div>
<?php endif; ?>

<?php if (isset($recentErrorCount) && $recentErrorCount > 0): ?>
<div class="alert alert-warning">
    <span class="material-symbols-rounded">warning</span>
    <div style="flex: 1;">
        <strong><?= $recentErrorCount ?> error<?= $recentErrorCount !== 1 ? 's' : '' ?> logged in the last 24 hours</strong>
        <br>
        <span class="text-xs" style="opacity: 0.8;">
            <a href="<?= htmlspecialchars($admin_url) ?>/system" style="color: inherit; text-decoration: underline;">View errors in System Info</a>
        </span>
    </div>
</div>
<?php endif; ?>

<?php if ($updateCheck && $updateCheck['available']): ?>
<div class="alert alert-info" style="margin-bottom: var(--sp-5);">
    <span class="material-symbols-rounded">system_update</span>
    <div style="flex: 1;">
        <strong>Update available:</strong> v<?= htmlspecialchars($updateCheck['latest']) ?>
        <br>
        <span class="text-xs" style="opacity: 0.7;">
            Run <code>./ava update:apply</code> or <a href="https://ava.addy.zone/#/updates" target="_blank" style="color: inherit;">see the update guide</a>
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

<!-- Top Row: Content Index, Webpage Cache, System, Site -->
<div class="grid grid-4">
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
            <div class="list-item">
                <span class="list-label">Mode</span>
                <code><?= htmlspecialchars($cache['mode']) ?></code>
            </div>
            <div class="list-item">
                <span class="list-label">Last Built</span>
                <span class="list-value text-sm"><?= htmlspecialchars($cache['built_at'] ?? 'Never') ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Total Size</span>
                <span class="list-value"><?= $formatBytes($cache['size'] ?? 0) ?></span>
            </div>
            <form method="POST" action="<?= $admin_url ?>/rebuild" style="margin-top: var(--sp-4);">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-rounded">refresh</span>
                    Rebuild Now
                </button>
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
            <?php if ($webpageCache['count'] > 0): ?>
            <form method="POST" action="<?= $admin_url ?>/flush-pages" style="margin-top: var(--sp-4);">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">delete_sweep</span>
                    Flush Webpages
                </button>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-dim text-sm" style="margin: 0;">Enable in config for faster page loads.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">dns</span>
                System
            </span>
            <a href="<?= $admin_url ?>/system" class="btn btn-sm btn-secondary">Details</a>
        </div>
        <div class="card-body">
            <div class="list-item">
                <span class="list-label">PHP</span>
                <span class="list-value"><?= $system['php_version'] ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Memory</span>
                <span class="list-value"><?= $formatBytes($system['memory_used']) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Theme</span>
                <span class="list-value"><?= htmlspecialchars($ava->config('theme', 'default')) ?></span>
            </div>
            <?php if ($system['opcache'] && $system['opcache']['enabled']): ?>
            <div class="list-item">
                <span class="list-label">OPcache</span>
                <span class="list-value text-success"><?= $system['opcache']['hit_rate'] ?>% hit</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">language</span>
                Site
            </span>
            <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" class="btn btn-sm btn-secondary">View</a>
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
                <span class="list-label">Timezone</span>
                <span class="list-value"><?= htmlspecialchars($site['timezone'] ?? 'UTC') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Content Types & Recent -->
<div class="grid grid-2 mt-5">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">folder_open</span>
                Content Types
            </span>
            <span class="badge badge-muted"><?= count($content) ?></span>
        </div>
        <?php foreach ($content as $type => $stats): 
            $typeConfig = $contentTypes[$type] ?? [];
            $urlType = $typeConfig['url']['type'] ?? 'pattern';
            $urlPattern = $typeConfig['url']['pattern'] ?? '/' . $type . '/{slug}';
        ?>
        <div class="config-section">
            <div class="config-section-title">
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <?= htmlspecialchars($typeConfig['label'] ?? ucfirst($type) . 's') ?>
                <a href="<?= $admin_url ?>/content/<?= $type ?>" class="badge badge-accent" style="margin-left: auto;"><?= $stats['total'] ?> →</a>
            </div>
            <div class="config-row">
                <span class="label">Directory</span>
                <span class="value"><code>content/<?= htmlspecialchars($typeConfig['content_dir'] ?? $type . 's') ?>/</code></span>
            </div>
            <div class="config-row">
                <span class="label">URL Pattern</span>
                <span class="value"><code><?= htmlspecialchars($urlPattern) ?></code></span>
            </div>
            <div class="config-row">
                <span class="label">Template</span>
                <span class="value"><code><?= htmlspecialchars($typeConfig['templates']['single'] ?? $type . '.php') ?></code></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
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
                <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" class="content-item-link">
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
</div>

<!-- Plugins, Routes & Users -->
<div class="grid grid-3 mt-5">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">extension</span>
                Plugins
            </span>
            <span class="badge badge-muted"><?= count($plugins ?? []) ?></span>
        </div>
        <?php if (!empty($plugins)): ?>
        <div class="card-body">
            <?php foreach ($plugins as $plugin): ?>
            <div class="list-item">
                <span class="list-label">
                    <span class="material-symbols-rounded">check_circle</span>
                    <?= htmlspecialchars($plugin) ?>
                </span>
                <span class="badge badge-success">Active</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="material-symbols-rounded">extension</span>
            <p>No plugins active</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">route</span>
                Routes
            </span>
            <span class="badge badge-muted"><?= count($routes['exact'] ?? []) + count($routes['pattern'] ?? []) ?></span>
        </div>
        <div class="card-body">
            <div class="list-item">
                <span class="list-label">Exact</span>
                <span class="list-value"><?= count($routes['exact'] ?? []) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Pattern</span>
                <span class="list-value"><?= count($routes['pattern'] ?? []) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Taxonomy</span>
                <span class="list-value"><?= count($routes['taxonomy'] ?? []) ?></span>
            </div>
            <div class="list-item">
                <span class="list-label">Redirects</span>
                <span class="list-value"><?= count($routes['redirects'] ?? []) ?></span>
            </div>
        </div>
    </div>

    <div class="card">
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
                        <span class="text-xs text-tertiary" style="display: block;"><?= htmlspecialchars($email) ?></span>
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
</div>
