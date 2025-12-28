<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['label'] ?? ucfirst($taxonomy)) ?> · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<?php
$getTaxonomyBase = function($taxName) use ($taxonomyConfig, $site) {
    $tc = $taxonomyConfig[$taxName] ?? [];
    $base = $tc['rewrite']['base'] ?? '/' . $taxName;
    return rtrim($site['url'], '/') . $base;
};
$taxBase = $getTaxonomyBase($taxonomy);
$isHierarchical = $config['hierarchical'] ?? false;
$totalTerms = count($terms);
$totalItems = array_sum(array_column($terms, 'count'));
$behaviour = $config['behaviour'] ?? [];
$ui = $config['ui'] ?? [];
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
            <?php foreach ($allContent as $t => $stats): ?>
            <a href="<?= $admin_url ?>/content/<?= $t ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= $t === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($t) ?>s
                <span class="nav-count"><?= $stats['total'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Taxonomies</div>
            <?php foreach ($taxonomies as $tax => $count): 
                $tc = $taxonomyConfig[$tax] ?? [];
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item <?= $tax === $taxonomy ? 'active' : '' ?>">
                <span class="material-symbols-rounded"><?= ($tc['hierarchical'] ?? false) ? 'folder' : 'sell' ?></span>
                <?= htmlspecialchars($tc['label'] ?? ucfirst($tax)) ?>
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
            <h1><?= htmlspecialchars($config['label'] ?? ucfirst($taxonomy)) ?></h1>
        </div>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded"><?= $isHierarchical ? 'folder' : 'sell' ?></span>
                <?= htmlspecialchars($config['label'] ?? ucfirst($taxonomy)) ?>
            </h2>
            <div class="header-actions">
                <a href="<?= htmlspecialchars($taxBase) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">visibility</span>
                    View Archive
                </a>
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
            <!-- Terms List -->
            <div class="card content-main">
                <?php if (!empty($terms)): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Term</th>
                                <th>Slug</th>
                                <th>Content</th>
                                <th>Usage</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $maxCount = max(1, max(array_column($terms, 'count')));
                            foreach ($terms as $slug => $termData): 
                                $termUrl = $taxBase . '/' . $slug;
                                $usagePercent = round(($termData['count'] / $maxCount) * 100);
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: var(--sp-2);">
                                        <span class="material-symbols-rounded text-tertiary" style="font-size: 16px;">label</span>
                                        <span style="font-weight: 500;"><?= htmlspecialchars($termData['name']) ?></span>
                                    </div>
                                </td>
                                <td><code class="text-xs"><?= htmlspecialchars($slug) ?></code></td>
                                <td>
                                    <span class="badge <?= $termData['count'] > 0 ? 'badge-accent' : 'badge-muted' ?>">
                                        <?= $termData['count'] ?>
                                    </span>
                                </td>
                                <td style="width: 120px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill accent" style="width: <?= $usagePercent ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?= htmlspecialchars($termUrl) ?>" target="_blank" class="btn btn-xs btn-secondary">
                                        <span class="material-symbols-rounded">open_in_new</span>
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <span class="material-symbols-rounded">sell</span>
                    <p>No terms in this taxonomy yet</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="config-sidebar">
                <!-- Stats -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">bar_chart</span>
                            Statistics
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item"><span class="list-label">Terms</span><span class="list-value"><?= $totalTerms ?></span></div>
                        <div class="list-item"><span class="list-label">Content Items</span><span class="list-value"><?= $totalItems ?></span></div>
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
                        <div class="list-item"><span class="list-label">Config</span><code class="text-xs">app/config/taxonomies.php</code></div>
                        <div class="list-item"><span class="list-label">Terms</span><code class="text-xs">content/_taxonomies/<?= htmlspecialchars($taxonomy) ?>.yml</code></div>
                        <div class="list-item"><span class="list-label">Type</span><span class="list-value"><?= $isHierarchical ? 'Hierarchical' : 'Flat' ?></span></div>
                        <div class="list-item"><span class="list-label">Public</span><span class="badge <?= ($config['public'] ?? true) ? 'badge-success' : 'badge-muted' ?>"><?= ($config['public'] ?? true) ? 'Yes' : 'No' ?></span></div>
                        <div class="list-item"><span class="list-label">URL Base</span><code class="text-xs"><?= htmlspecialchars($config['rewrite']['base'] ?? '/' . $taxonomy) ?></code></div>
                        <?php if ($isHierarchical && isset($config['rewrite']['separator'])): ?>
                        <div class="list-item"><span class="list-label">Separator</span><code><?= htmlspecialchars($config['rewrite']['separator']) ?></code></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Behaviour -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">tune</span>
                            Behaviour
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item">
                            <span class="list-label">Allow Unknown</span>
                            <span class="badge <?= ($behaviour['allow_unknown_terms'] ?? false) ? 'badge-success' : 'badge-muted' ?>">
                                <?= ($behaviour['allow_unknown_terms'] ?? false) ? 'Yes' : 'No' ?>
                            </span>
                        </div>
                        <?php if ($isHierarchical): ?>
                        <div class="list-item">
                            <span class="list-label">Rollup</span>
                            <span class="badge <?= ($behaviour['hierarchy_rollup'] ?? false) ? 'badge-success' : 'badge-muted' ?>">
                                <?= ($behaviour['hierarchy_rollup'] ?? false) ? 'On' : 'Off' ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- UI Options -->
                <div class="card mt-3">
                    <div class="card-header">
                        <span class="card-title">
                            <span class="material-symbols-rounded">visibility</span>
                            UI Options
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="list-item">
                            <span class="list-label">Show Counts</span>
                            <span class="badge <?= ($ui['show_counts'] ?? true) ? 'badge-success' : 'badge-muted' ?>">
                                <?= ($ui['show_counts'] ?? true) ? 'Yes' : 'No' ?>
                            </span>
                        </div>
                        <div class="list-item"><span class="list-label">Sort</span><code class="text-xs"><?= htmlspecialchars($ui['sort_terms'] ?? 'name_asc') ?></code></div>
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
