<?php
/**
 * Themes - Content Only View
 * 
 * Available variables:
 * - $currentTheme: Current theme name
 * - $themeInfo: Theme information array
 * - $availableThemes: Array of available themes
 * - $themesPath: Path to themes directory
 */

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
            <table class="table">
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
                            <span class="material-symbols-rounded text-tertiary icon-sm">description</span>
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
        <p class="text-tertiary">No assets found in app/themes/<?= htmlspecialchars($currentTheme) ?>/assets/</p>
        <?php else: ?>
        <table class="table">
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
                        <span class="material-symbols-rounded text-tertiary icon-sm"><?= $assetIcon($asset['type']) ?></span>
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
&lt;link rel="stylesheet" href="&lt;?= $ava->asset('/assets/style.css') ?&gt;"&gt;

&lt;!-- These are served directly from public/assets/ by your web server --&gt;</pre>
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
        <table class="table">
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
                        <span class="material-symbols-rounded text-tertiary icon-sm">palette</span>
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
        
        <table class="table table-flush">
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

<!-- Shortcodes Reference Section -->
<?php
$shortcodeInfo = [
    'year' => ['syntax' => '[year]', 'desc' => 'Current year'],
    'date' => ['syntax' => '[date format="Y-m-d"]', 'desc' => 'Current date'],
    'site_name' => ['syntax' => '[site_name]', 'desc' => 'Site name'],
    'site_url' => ['syntax' => '[site_url]', 'desc' => 'Site URL'],
    'email' => ['syntax' => '[email]you@example.com[/email]', 'desc' => 'Obfuscated email'],
    'snippet' => ['syntax' => '[snippet name="..."]', 'desc' => 'Include snippet'],
    'include' => ['syntax' => '[include file="..."]', 'desc' => 'Include file'],
];
?>

<h2 class="section-title mt-6">
    <span class="material-symbols-rounded">code</span>
    Shortcodes Reference
</h2>

<div class="grid grid-2">
    <!-- Shortcodes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">bolt</span>
                Available Shortcodes
            </span>
            <span class="badge badge-accent"><?= count($shortcodes ?? []) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Usage</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shortcodes ?? [] as $tag): 
                        $info = $shortcodeInfo[$tag] ?? ['syntax' => "[{$tag}]", 'desc' => 'Custom shortcode'];
                    ?>
                    <tr>
                        <td><span class="badge badge-muted"><?= htmlspecialchars($tag) ?></span></td>
                        <td><code class="text-xs"><?= htmlspecialchars($info['syntax']) ?></code></td>
                        <td><span class="text-sm text-secondary"><?= htmlspecialchars($info['desc']) ?></span></td>
                        <td>
                            <button class="btn btn-xs btn-secondary copy-btn" data-copy="<?= htmlspecialchars($info['syntax']) ?>">
                                <span class="material-symbols-rounded">content_copy</span>
                                Copy
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shortcodes)): ?>
                    <tr><td colspan="4" class="text-tertiary text-center">No shortcodes registered</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Snippets -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">widgets</span>
                Available Snippets
            </span>
            <span class="badge badge-accent"><?= count($snippets ?? []) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Usage</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($snippets)): ?>
                    <?php foreach ($snippets as $name => $snippet): 
                        $usage = '[snippet name="' . $name . '"]';
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-muted"><?= htmlspecialchars($name) ?></span>
                        </td>
                        <td><code class="text-xs"><?= htmlspecialchars($usage) ?></code></td>
                        <td>
                            <button class="btn btn-xs btn-secondary copy-btn" data-copy="<?= htmlspecialchars($usage) ?>">
                                <span class="material-symbols-rounded">content_copy</span>
                                Copy
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="3" class="text-tertiary text-center">No snippets in <code>snippets/</code></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shortcode Usage Guide -->
<div class="card mt-4">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">help</span>
            Shortcode Usage
        </span>
    </div>
    <div class="card-body">
        <p class="text-secondary text-sm mb-3">
            Shortcodes are processed after Markdown rendering. Use them in your content files:
        </p>
        <div class="list-item"><span class="list-label">Basic</span><code>[shortcode]</code></div>
        <div class="list-item"><span class="list-label">With attributes</span><code>[shortcode attr="value"]</code></div>
        <div class="list-item"><span class="list-label">With content</span><code>[shortcode]content[/shortcode]</code></div>
        <div class="list-item"><span class="list-label">Snippet variables</span><code>$params, $content, $app, $ava</code></div>
    </div>
</div>

