<?php
/**
 * Admin Sidebar Partial
 * 
 * Required variables:
 * - $admin_url: Admin base URL
 * - $content: Content stats array
 * - $taxonomies: Taxonomy counts
 * - $taxonomyConfig: Taxonomy configuration
 * - $customPages: Plugin pages array
 * - $version: Ava version
 * - $user: Current user email
 * - $activePage: Current page identifier (e.g., 'dashboard', 'themes', 'lint')
 */
$activePage = $activePage ?? '';
?>
<script>
    (function() {
        const theme = document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1];
        if (theme && theme !== 'system') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    })();
</script>

<aside class="app-sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <span class="material-symbols-rounded">menu</span>
        </button>
        <span class="sidebar-brand">Ava CMS</span>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-group">
            <a href="<?= $admin_url ?>" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" data-tooltip="Dashboard" title="Dashboard">
                <span class="material-symbols-rounded">dashboard</span>
                <span class="nav-item-label">Dashboard</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Content</div>
            <?php foreach ($content as $type => $stats): ?>
            <a href="<?= $admin_url ?>/content/<?= $type ?>" class="nav-item <?= $activePage === 'content-' . $type ? 'active' : '' ?>" data-tooltip="<?= ucfirst($type) ?>s" title="<?= ucfirst($type) ?>s">
                <span class="material-symbols-rounded"><?= $type === 'page' ? 'description' : 'article' ?></span>
                <span class="nav-item-label"><?= ucfirst($type) ?>s</span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Taxonomies</div>
            <?php foreach ($taxonomies as $tax => $count): 
                $taxConfig = $taxonomyConfig[$tax] ?? [];
            ?>
            <a href="<?= $admin_url ?>/taxonomy/<?= $tax ?>" class="nav-item <?= $activePage === 'taxonomy-' . $tax ? 'active' : '' ?>" data-tooltip="<?= htmlspecialchars($taxConfig['label'] ?? ucfirst($tax)) ?>" title="<?= htmlspecialchars($taxConfig['label'] ?? ucfirst($tax)) ?>">
                <span class="material-symbols-rounded"><?= ($taxConfig['hierarchical'] ?? false) ? 'folder' : 'sell' ?></span>
                <span class="nav-item-label"><?= htmlspecialchars($taxConfig['label'] ?? ucfirst($tax)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Tools</div>
            <a href="<?= $admin_url ?>/media" class="nav-item <?= $activePage === 'media' ? 'active' : '' ?>" data-tooltip="Media" title="Media">
                <span class="material-symbols-rounded">image</span>
                <span class="nav-item-label">Media</span>
            </a>
            <a href="<?= $admin_url ?>/lint" class="nav-item <?= $activePage === 'lint' ? 'active' : '' ?>" data-tooltip="Lint Content" title="Lint Content">
                <span class="material-symbols-rounded">check_circle</span>
                <span class="nav-item-label">Lint Content</span>
            </a>
            <a href="<?= $admin_url ?>/logs" class="nav-item <?= $activePage === 'logs' ? 'active' : '' ?>" data-tooltip="Admin Logs" title="Admin Logs">
                <span class="material-symbols-rounded">history</span>
                <span class="nav-item-label">Admin Logs</span>
            </a>
            <a href="<?= $admin_url ?>/theme" class="nav-item <?= $activePage === 'theme' ? 'active' : '' ?>" data-tooltip="Theme" title="Theme">
                <span class="material-symbols-rounded">palette</span>
                <span class="nav-item-label">Theme</span>
            </a>
            <a href="<?= $admin_url ?>/system" class="nav-item <?= $activePage === 'system' ? 'active' : '' ?>" data-tooltip="System Info" title="System Info">
                <span class="material-symbols-rounded">dns</span>
                <span class="nav-item-label">System Info</span>
            </a>
        </div>

        <?php if (!empty($customPages)): ?>
        <div class="nav-group">
            <div class="nav-group-label">Plugins</div>
            <?php foreach ($customPages as $slug => $page): ?>
            <a href="<?= $admin_url ?>/<?= htmlspecialchars($slug) ?>" class="nav-item <?= $activePage === $slug ? 'active' : '' ?>" data-tooltip="<?= htmlspecialchars($page['label'] ?? ucfirst($slug)) ?>" title="<?= htmlspecialchars($page['label'] ?? ucfirst($slug)) ?>">
                <span class="material-symbols-rounded"><?= htmlspecialchars($page['icon'] ?? 'extension') ?></span>
                <span class="nav-item-label"><?= htmlspecialchars($page['label'] ?? ucfirst($slug)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </nav>
</aside>
