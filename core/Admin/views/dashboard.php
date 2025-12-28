<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<?php
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

<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <h1>✨ Ava <span class="version-badge">v<?= htmlspecialchars($version ?? '1.0') ?></span></h1>
        </div>
        <nav class="nav">
            <div class="nav-section">Overview</div>
            <a href="<?= $admin_url ?>" class="nav-item active">
                <span class="material-symbols-rounded">dashboard</span>
                Dashboard
            </a>

            <div class="nav-section">Content</div>
            <?php foreach ($content as $type => $stats): ?>
            <a href="<?= $admin_url ?>/content/<?= $type ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($type) ?>s
                <span class="nav-count"><?= $stats['total'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Taxonomies</div>
            <?php foreach ($taxonomies as $tax => $count): 
                $taxConfig = $taxonomyConfig[$tax] ?? [];
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= ($taxConfig['hierarchical'] ?? false) ? 'folder' : 'sell' ?></span>
                <?= htmlspecialchars($taxConfig['label'] ?? ucfirst($tax)) ?>
                <span class="nav-count"><?= $count ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Tools</div>
            <a href="<?= $admin_url ?>/lint" class="nav-item">
                <span class="material-symbols-rounded">check_circle</span>
                Lint Content
            </a>
            <a href="<?= $admin_url ?>/shortcodes" class="nav-item">
                <span class="material-symbols-rounded">code</span>
                Shortcodes
            </a>
            <a href="<?= $admin_url ?>/logs" class="nav-item">
                <span class="material-symbols-rounded">history</span>
                Admin Logs
            </a>
            <a href="<?= $admin_url ?>/themes" class="nav-item">
                <span class="material-symbols-rounded">palette</span>
                Themes
            </a>
            <a href="<?= $admin_url ?>/system" class="nav-item">
                <span class="material-symbols-rounded">dns</span>
                System Info
            </a>

            <?php if (!empty($customPages)): ?>
            <div class="nav-section">Plugins</div>
            <?php foreach ($customPages as $slug => $page): ?>
            <a href="<?= $admin_url ?>/<?= htmlspecialchars($slug) ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= htmlspecialchars($page['icon'] ?? 'extension') ?></span>
                <?= htmlspecialchars($page['label'] ?? ucfirst($slug)) ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="material-symbols-rounded">person</span>
                <?= htmlspecialchars($user ?? 'Admin') ?>
            </div>
            <a href="<?= $admin_url ?>/logout">
                <span class="material-symbols-rounded">logout</span>
                Sign Out
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="mobile-header">
            <button class="menu-btn" onclick="toggleSidebar()">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <h1>✨ Ava</h1>
        </div>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'rebuild'): ?>
        <div class="alert alert-success">
            <span class="material-symbols-rounded">check_circle</span>
            Cache rebuilt successfully in <?= htmlspecialchars($_GET['time'] ?? '?') ?>ms
        </div>
        <?php endif; ?>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded">dashboard</span>
                Dashboard
            </h2>
            <div class="header-actions">
                <a href="https://adamgreenough.github.io/ava/" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">menu_book</span>
                    <span class="hide-mobile">Docs</span>
                </a>
                <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">open_in_new</span>
                    <span class="hide-mobile">View Site</span>
                </a>
            </div>
        </div>

        <?php if ($updateCheck && $updateCheck['available']): ?>
        <div class="alert alert-info" style="margin-bottom: var(--sp-5);">
            <span class="material-symbols-rounded">system_update</span>
            <div style="flex: 1;">
                <strong>Update available:</strong> v<?= htmlspecialchars($updateCheck['latest']) ?>
                <?php if ($updateCheck['release']['name'] ?? null): ?>
                    — <?= htmlspecialchars($updateCheck['release']['name']) ?>
                <?php endif; ?>
            </div>
            <code class="text-xs" style="opacity: 0.8;">php bin/ava update:apply</code>
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
            <div class="stat-card">
                <div class="stat-label">
                    <span class="material-symbols-rounded">speed</span>
                    Render
                </div>
                <div class="stat-value"><?= $renderTime ?><span class="text-dim text-sm">ms</span></div>
                <div class="stat-meta">This page</div>
            </div>
        </div>

        <!-- Top Row: Cache, System, Site -->
        <div class="grid grid-3">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="material-symbols-rounded">cached</span>
                        Cache
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
                        <span class="list-label">Size</span>
                        <span class="list-value"><?= $formatBytes($cache['size'] ?? 0) ?></span>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Files</span>
                        <span class="list-value"><?= $cache['files'] ?? 0 ?></span>
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
                        <span class="list-value"><?= htmlspecialchars($theme ?? 'default') ?></span>
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

        <!-- CLI Reference -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title">
                    <span class="material-symbols-rounded">terminal</span>
                    CLI Reference
                </span>
            </div>
            <div class="table-wrap">
                <table class="cli-table">
                    <thead>
                        <tr>
                            <th>Command</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>./ava status</code></td><td>Show site status and content counts</td></tr>
                        <tr><td><code>./ava rebuild</code></td><td>Rebuild all cache files</td></tr>
                        <tr><td><code>./ava lint</code></td><td>Validate all content files</td></tr>
                        <tr><td><code>./ava make &lt;type&gt; "Title"</code></td><td>Create new content</td></tr>
                        <tr><td><code>./ava user:add &lt;email&gt; &lt;pass&gt;</code></td><td>Create admin user</td></tr>
                        <tr><td><code>./ava help</code></td><td>Show all commands</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('open');
}
</script>
</body>
</html>
