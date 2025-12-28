<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Themes · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<?php
$formatBytes = function($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
};

$formatDate = function($timestamp) {
    return date('Y-m-d H:i', $timestamp);
};

$assetIcon = function($type) {
    return match ($type) {
        'stylesheet' => 'css',
        'javascript' => 'javascript',
        'image' => 'image',
        'font' => 'font_download',
        'data' => 'data_object',
        default => 'draft',
    };
};

$assetBadge = function($type) {
    return match ($type) {
        'stylesheet' => 'badge-info',
        'javascript' => 'badge-warning',
        'image' => 'badge-success',
        'font' => 'badge-muted',
        default => 'badge-muted',
    };
};
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
            <?php foreach ($content as $type => $stats): ?>
            <a href="<?= $admin_url ?>/content/<?= $type ?>" class="nav-item">
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <?= ucfirst($type) ?>s
                <span class="nav-count"><?= $stats['total'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="nav-section">Taxonomies</div>
            <?php foreach ($taxonomies as $tax => $count): 
                $tc = $taxonomyConfig[$tax] ?? [];
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item">
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
            <a href="<?= $admin_url ?>/themes" class="nav-item active">
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
            <h1>Themes</h1>
        </div>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded">palette</span>
                Themes
            </h2>
            <div class="header-actions">
                <a href="https://adamgreenough.github.io/ava/#/themes" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">menu_book</span>
                    <span class="hide-mobile">Docs</span>
                </a>
                <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-rounded">open_in_new</span>
                    <span class="hide-mobile">View Site</span>
                </a>
            </div>
        </div>

        <!-- Current Theme Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">palette</span> Active Theme</div>
                <div class="stat-value"><?= htmlspecialchars($currentTheme) ?></div>
                <div class="stat-meta">themes/<?= htmlspecialchars($currentTheme) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">view_quilt</span> Templates</div>
                <div class="stat-value"><?= count($themeInfo['templates'] ?? []) ?></div>
                <div class="stat-meta">PHP templates</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">folder</span> Assets</div>
                <div class="stat-value"><?= count($themeInfo['assets'] ?? []) ?></div>
                <div class="stat-meta">CSS, JS, images</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><span class="material-symbols-rounded">storage</span> Total Size</div>
                <div class="stat-value"><?= $formatBytes($themeInfo['total_size'] ?? 0) ?></div>
                <div class="stat-meta">All theme files</div>
            </div>
        </div>

        <!-- How Theme Assets Work -->
        <div class="alert alert-info">
            <span class="material-symbols-rounded">info</span>
            <div>
                <strong>Theme assets are served via PHP</strong> at the <code>/theme/</code> URL prefix. 
                This keeps all theme files self-contained in the <code>themes/<?= htmlspecialchars($currentTheme) ?>/</code> directory.
                Use <code>$ava->asset('filename.css')</code> in templates to generate versioned URLs with cache-busting.
            </div>
        </div>

        <div class="grid grid-2">
            <!-- Templates -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">view_quilt</span> Templates</span>
                    <span class="badge badge-muted"><?= count($themeInfo['templates'] ?? []) ?> files</span>
                </div>
                <div class="card-body">
                    <?php if (empty($themeInfo['templates'])): ?>
                    <p class="text-tertiary text-sm">No templates found.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th class="text-right">Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($themeInfo['templates'] as $name => $template): ?>
                            <tr>
                                <td>
                                    <span class="material-symbols-rounded text-tertiary" style="font-size: 16px; vertical-align: middle;">description</span>
                                    <?= htmlspecialchars($name) ?>.php
                                </td>
                                <td class="text-right text-tertiary"><?= $formatBytes($template['size']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Theme Structure -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><span class="material-symbols-rounded">folder_open</span> Theme Structure</span>
                </div>
                <div class="card-body">
                    <div class="code-block">
<pre>themes/<?= htmlspecialchars($currentTheme) ?>/
├── theme.php           <?= $themeInfo['has_theme_php'] ? '✓' : '✗ (optional)' ?>

├── templates/
<?php 
$templateNames = array_keys($themeInfo['templates'] ?? []);
$lastTemplate = end($templateNames);
foreach ($themeInfo['templates'] ?? [] as $name => $t): 
    $prefix = $name === $lastTemplate ? '└──' : '├──';
?>
│   <?= $prefix ?> <?= $name ?>.php
<?php endforeach; ?>
<?php if (empty($themeInfo['templates'])): ?>
│   └── (none)
<?php endif; ?>
│
└── assets/
<?php 
$assets = $themeInfo['assets'] ?? [];
$lastAsset = end($assets);
foreach ($assets as $asset): 
    $prefix = $asset === $lastAsset ? '└──' : '├──';
?>
    <?= $prefix ?> <?= htmlspecialchars($asset['file']) ?>

<?php endforeach; ?>
<?php if (empty($assets)): ?>
    └── (none)
<?php endif; ?>
</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">folder</span> Theme Assets</span>
                <span class="badge badge-muted"><?= count($themeInfo['assets'] ?? []) ?> files</span>
            </div>
            <div class="card-body">
                <?php if (empty($themeInfo['assets'])): ?>
                <p class="text-tertiary">No assets found in themes/<?= htmlspecialchars($currentTheme) ?>/assets/</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Type</th>
                            <th>URL</th>
                            <th class="text-right">Size</th>
                            <th class="text-right">Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($themeInfo['assets'] as $asset): ?>
                        <tr>
                            <td>
                                <span class="material-symbols-rounded text-tertiary" style="font-size: 16px; vertical-align: middle;"><?= $assetIcon($asset['type']) ?></span>
                                <?= htmlspecialchars($asset['file']) ?>
                            </td>
                            <td><span class="badge <?= $assetBadge($asset['type']) ?>"><?= htmlspecialchars($asset['type']) ?></span></td>
                            <td><code class="text-sm"><?= htmlspecialchars($asset['url']) ?></code></td>
                            <td class="text-right text-tertiary"><?= $formatBytes($asset['size']) ?></td>
                            <td class="text-right text-tertiary"><?= $formatDate($asset['modified']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Asset Helper Reference -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">code</span> Using Theme Assets</span>
            </div>
            <div class="card-body">
                <p class="mb-3">Use the <code>$ava->asset()</code> helper in your templates to reference theme assets with automatic cache-busting:</p>
                
                <div class="code-block mb-3">
<pre>&lt;!-- Theme assets (no leading slash) --&gt;
&lt;link rel="stylesheet" href="&lt;?= $ava->asset('style.css') ?&gt;"&gt;
&lt;script src="&lt;?= $ava->asset('js/app.js') ?&gt;"&gt;&lt;/script&gt;
&lt;img src="&lt;?= $ava->asset('images/logo.svg') ?&gt;"&gt;

&lt;!-- Output example: --&gt;
&lt;link rel="stylesheet" href="/theme/style.css?v=1703782400"&gt;</pre>
                </div>

                <p class="text-tertiary text-sm mb-3">
                    Assets without a leading slash are served from <code>themes/<?= htmlspecialchars($currentTheme) ?>/assets/</code>.
                    The <code>?v=</code> query parameter is the file's modification time for cache-busting.
                </p>

                <div class="code-block">
<pre>&lt;!-- Public assets (with leading slash) --&gt;
&lt;link rel="stylesheet" href="&lt;?= $ava->asset('/assets/admin.css') ?&gt;"&gt;

&lt;!-- These are served directly from public/ by your web server --&gt;</pre>
                </div>
            </div>
        </div>

        <!-- Other Themes -->
        <?php if (count($availableThemes) > 1): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">apps</span> Available Themes</span>
                <span class="badge badge-muted"><?= count($availableThemes) ?> themes</span>
            </div>
            <div class="card-body">
                <p class="text-tertiary text-sm mb-3">
                    To switch themes, update <code>theme</code> in <code>app/config/ava.php</code>:
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Theme</th>
                            <th>Templates</th>
                            <th>Assets</th>
                            <th>theme.php</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableThemes as $t): ?>
                        <tr>
                            <td>
                                <span class="material-symbols-rounded text-tertiary" style="font-size: 16px; vertical-align: middle;">palette</span>
                                <?= htmlspecialchars($t['name']) ?>
                            </td>
                            <td><?= $t['template_count'] ?></td>
                            <td><?= $t['has_assets'] ? '✓' : '—' ?></td>
                            <td><?= $t['has_theme_php'] ? '✓' : '—' ?></td>
                            <td>
                                <?php if ($t['name'] === $currentTheme): ?>
                                <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                <span class="badge badge-muted">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cache Headers Info -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><span class="material-symbols-rounded">speed</span> Caching Headers</span>
            </div>
            <div class="card-body">
                <p class="mb-3">Theme assets are served with aggressive caching headers for optimal performance:</p>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Value</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>Cache-Control</code></td>
                            <td><code>public, max-age=31536000, immutable</code></td>
                            <td>Browser caches for 1 year</td>
                        </tr>
                        <tr>
                            <td><code>ETag</code></td>
                            <td><code>"[md5 hash]"</code></td>
                            <td>Validates content hasn't changed</td>
                        </tr>
                        <tr>
                            <td><code>Last-Modified</code></td>
                            <td><code>[file mtime]</code></td>
                            <td>Conditional request support</td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="text-tertiary text-sm mt-3">
                    The <code>?v=</code> query parameter changes when files are modified, forcing browsers to fetch the new version.
                </p>
            </div>
        </div>

    </main>
</div>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('open');
}
</script>
</body>
</html>
