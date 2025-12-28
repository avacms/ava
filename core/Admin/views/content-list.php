<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($type) ?>s · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<?php
// Get preview token for draft links
$previewToken = $ava->config('security.preview_token');

// Get just the URL path for an item from routes (or generate for drafts)
$getContentPath = function($item) use ($routes, $typeConfig, $type) {
    $itemType = $item->type();
    $slug = $item->slug();
    
    // First check if it's in routes (published content)
    foreach ($routes['exact'] ?? [] as $routeUrl => $routeData) {
        if (($routeData['content_type'] ?? '') === $itemType && ($routeData['slug'] ?? '') === $slug) {
            return $routeUrl;
        }
    }
    
    // For drafts, generate URL from pattern
    if (!$item->isPublished()) {
        $urlConfig = $typeConfig['url'] ?? [];
        $urlType = $urlConfig['type'] ?? 'pattern';
        $pattern = $urlConfig['pattern'] ?? ($urlType === 'hierarchical' ? '/{slug}' : '/' . $type . '/{slug}');
        
        // Simple replacement - handle hierarchical paths
        $path = str_replace('{slug}', $slug, $pattern);
        return $path;
    }
    
    return null;
};

// Build full URL for content item (with preview support for drafts)
$getContentUrl = function($item, $forcePreview = false) use ($routes, $site, $previewToken, $getContentPath) {
    $path = $getContentPath($item);
    if (!$path) {
        return null;
    }
    
    $url = rtrim($site['url'], '/') . $path;
    
    // For drafts, add preview token if available
    if ((!$item->isPublished() || $forcePreview) && $previewToken) {
        $url .= '?preview=1&token=' . urlencode($previewToken);
    }
    
    return $url;
};

$urlType = $typeConfig['url']['type'] ?? 'pattern';
$urlPattern = $typeConfig['url']['pattern'] ?? ($urlType === 'hierarchical' ? '/{slug}' : '/' . $type . '/{slug}');
$archiveUrl = $typeConfig['url']['archive'] ?? null;
$contentDir = $typeConfig['content_dir'] ?? $type . 's';
$taxonomiesForType = $typeConfig['taxonomies'] ?? [];

$formatBytes = function($bytes) {
    $units = ['B', 'KB', 'MB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 2) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
};
$avgWords = count($items) > 0 ? round($stats['totalWords'] / count($items)) : 0;
?>

<div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <h1>✨ Ava <span class="version-badge">v1.0</span></h1>
        </div>
        <nav class="nav">
            <div class="nav-section">Overview</div>
            <a href="<?= $admin_url ?>" class="nav-item">
                <span class="material-symbols-rounded">dashboard</span>
                Dashboard
            </a>

            <div class="nav-section">Content</div>
            <?php foreach ($allContent as $t => $cstats): ?>
            <a href="<?= $admin_url ?>/content/<?= $t ?>" class="nav-item <?= $t === $type ? 'active' : '' ?>">
                <span class="material-symbols-rounded"><?= $t === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($t) ?>s
                <span class="nav-count"><?= $cstats['total'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Taxonomies</div>
            <?php foreach ($taxonomyConfig as $tax => $tc): 
                $termCount = count($taxonomyTerms[$tax] ?? []);
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= ($tc['hierarchical'] ?? false) ? 'folder' : 'sell' ?></span>
                <?= htmlspecialchars($tc['label'] ?? ucfirst($tax)) ?>
                <span class="nav-count"><?= $termCount ?></span>
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
            <h1><?= ucfirst($type) ?>s</h1>
        </div>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($type) ?>s
            </h2>
            <div class="header-actions">
                <?php if ($archiveUrl): ?>
                <a href="<?= htmlspecialchars($site['url'] . $archiveUrl) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">list</span>
                    Archive
                </a>
                <?php endif; ?>
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

        <div class="content-layout">
            <!-- Content List -->
            <div class="card content-main">
                <?php if (!empty($items)): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>File</th>
                                <th>URL</th>
                                <th>Date</th>
                                <th>Words</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $itemUrl = $getContentUrl($item);
                                $itemPath = $getContentPath($item);
                                $wordCount = str_word_count(strip_tags($item->rawContent()));
                                $readTime = max(1, ceil($wordCount / 200));
                                $isDraft = !$item->isPublished();
                            ?>
                            <tr>
                                <td>
                                    <div class="table-title"><?= htmlspecialchars($item->title()) ?></div>
                                </td>
                                <td>
                                    <code class="text-xs"><?= htmlspecialchars(basename($item->filePath())) ?></code>
                                </td>
                                <td>
                                    <code class="text-xs <?= $isDraft ? 'text-tertiary' : '' ?>"><?= $itemPath ? htmlspecialchars($itemPath) : '—' ?></code>
                                </td>
                                <td>
                                    <?php if ($item->date()): ?>
                                    <div class="text-sm"><?= $item->date()->format('M j, Y') ?></div>
                                    <div class="text-xs text-tertiary"><?= $item->date()->format('H:i') ?></div>
                                    <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm"><?= number_format($wordCount) ?></div>
                                    <div class="text-xs text-tertiary"><?= $readTime ?> min</div>
                                </td>
                                <td>
                                    <span class="badge <?= $item->isPublished() ? 'badge-success' : ($item->status() === 'draft' ? 'badge-warning' : 'badge-muted') ?>">
                                        <?= $item->status() ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($itemUrl): ?>
                                    <a href="<?= htmlspecialchars($itemUrl) ?>" target="_blank" class="btn btn-xs btn-secondary">
                                        <span class="material-symbols-rounded"><?= $isDraft ? 'visibility' : 'open_in_new' ?></span>
                                        <?= $isDraft ? 'Preview' : 'View' ?>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                    <p>No <?= $type ?>s yet</p>
                    <code>./ava make <?= $type ?> "Your Title"</code>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="config-sidebar">
                <!-- Stats -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">analytics</span>
                            Statistics
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item"><span class="list-label">Total</span><span class="list-value"><?= count($items) ?></span></div>
                        <div class="list-item"><span class="list-label">Published</span><span class="list-value text-success"><?= $allContent[$type]['published'] ?? 0 ?></span></div>
                        <div class="list-item"><span class="list-label">Drafts</span><span class="list-value text-warning"><?= $allContent[$type]['draft'] ?? 0 ?></span></div>
                        <div class="list-item"><span class="list-label">Total Words</span><span class="list-value"><?= number_format($stats['totalWords']) ?></span></div>
                        <div class="list-item"><span class="list-label">Avg Words</span><span class="list-value"><?= number_format($avgWords) ?></span></div>
                        <div class="list-item"><span class="list-label">Size</span><span class="list-value"><?= $formatBytes($stats['totalSize']) ?></span></div>
                    </div>
                </div>

                <!-- Configuration -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">settings</span>
                            Configuration
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item"><span class="list-label">Config</span><code class="text-xs">app/config/content_types.php</code></div>
                        <div class="list-item"><span class="list-label">Directory</span><code>content/<?= htmlspecialchars($contentDir) ?>/</code></div>
                        <div class="list-item"><span class="list-label">URL Type</span><span class="badge badge-muted"><?= htmlspecialchars($urlType) ?></span></div>
                        <div class="list-item"><span class="list-label">Pattern</span><code class="text-xs"><?= htmlspecialchars($urlPattern) ?></code></div>
                        <?php if ($archiveUrl): ?>
                        <div class="list-item"><span class="list-label">Archive</span><code class="text-xs"><?= htmlspecialchars($archiveUrl) ?></code></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Templates -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">code</span>
                            Templates
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item"><span class="list-label">Single</span><code class="text-xs"><?= htmlspecialchars($typeConfig['templates']['single'] ?? $type . '.php') ?></code></div>
                        <?php if (isset($typeConfig['templates']['archive'])): ?>
                        <div class="list-item"><span class="list-label">Archive</span><code class="text-xs"><?= htmlspecialchars($typeConfig['templates']['archive']) ?></code></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($taxonomiesForType)): ?>
                <!-- Taxonomies -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">sell</span>
                            Taxonomies
                        </span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($taxonomiesForType as $tax): 
                            $tc = $taxonomyConfig[$tax] ?? [];
                            $termCount = count($taxonomyTerms[$tax] ?? []);
                        ?>
                        <div class="list-item">
                            <span class="list-label">
                                <span class="material-symbols-rounded" style="font-size: 14px;"><?= ($tc['hierarchical'] ?? false) ? 'folder' : 'label' ?></span>
                                <?= htmlspecialchars($tc['label'] ?? ucfirst($tax)) ?>
                            </span>
                            <span class="badge badge-muted"><?= $termCount ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Options -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">tune</span>
                            Options
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item"><span class="list-label">Sorting</span><code class="text-xs"><?= htmlspecialchars($typeConfig['sorting'] ?? 'date_desc') ?></code></div>
                        <?php if (isset($typeConfig['search'])): ?>
                        <div class="list-item">
                            <span class="list-label">Searchable</span>
                            <span class="badge <?= ($typeConfig['search']['enabled'] ?? false) ? 'badge-success' : 'badge-muted' ?>">
                                <?= ($typeConfig['search']['enabled'] ?? false) ? 'Yes' : 'No' ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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
