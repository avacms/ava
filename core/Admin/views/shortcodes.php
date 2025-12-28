<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortcodes · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
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
            <a href="<?= $admin_url ?>/shortcodes" class="nav-item active">
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
            <h1>Shortcodes</h1>
        </div>

        <div class="header">
            <h2>
                <span class="material-symbols-rounded">code</span>
                Shortcodes Reference
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

        <div class="grid grid-2">
            <!-- Shortcodes -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <span class="material-symbols-rounded">bolt</span>
                        Available Shortcodes
                    </span>
                    <span class="badge badge-accent"><?= count($shortcodes) ?></span>
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
                            <?php 
                            $shortcodeInfo = [
                                'year' => ['syntax' => '[year]', 'desc' => 'Current year'],
                                'date' => ['syntax' => '[date format="Y-m-d"]', 'desc' => 'Current date'],
                                'site_name' => ['syntax' => '[site_name]', 'desc' => 'Site name'],
                                'site_url' => ['syntax' => '[site_url]', 'desc' => 'Site URL'],
                                'email' => ['syntax' => '[email]you@example.com[/email]', 'desc' => 'Obfuscated email'],
                                'snippet' => ['syntax' => '[snippet name="..."]', 'desc' => 'Include snippet'],
                                'include' => ['syntax' => '[include file="..."]', 'desc' => 'Include file'],
                                'button' => ['syntax' => '[button url="..." text="..."]', 'desc' => 'Styled button'],
                            ];
                            foreach ($shortcodes as $tag): 
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
                            <tr><td colspan="3" class="text-tertiary text-center">No shortcodes registered</td></tr>
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
                    <span class="badge badge-accent"><?= count($snippets) ?></span>
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

        <!-- Usage Guide -->
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title">
                    <span class="material-symbols-rounded">help</span>
                    Usage Guide
                </span>
            </div>
            <div class="card-body">
                <p class="text-secondary text-sm" style="margin-bottom: var(--sp-3);">
                    Shortcodes are processed after Markdown rendering. Use them in your content files:
                </p>
                <div class="list-item"><span class="list-label">Basic</span><code>[shortcode]</code></div>
                <div class="list-item"><span class="list-label">With attributes</span><code>[shortcode attr="value"]</code></div>
                <div class="list-item"><span class="list-label">With content</span><code>[shortcode]content[/shortcode]</code></div>
                <div class="list-item"><span class="list-label">Snippet variables</span><code>$params, $content, $app, $ava</code></div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('open');
}

document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const text = this.dataset.copy;
        navigator.clipboard.writeText(text).then(() => {
            const icon = this.querySelector('.material-symbols-rounded');
            icon.textContent = 'check';
            this.classList.add('copied');
            setTimeout(() => {
                icon.textContent = 'content_copy';
                this.classList.remove('copied');
            }, 1500);
        });
    });
});
</script>
</body>
</html>
