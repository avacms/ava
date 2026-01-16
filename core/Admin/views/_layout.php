<?php
/**
 * Admin Layout Template
 * 
 * This layout wraps all admin pages (core and plugin) with consistent:
 * - HTML head (CSS, fonts, meta tags)
 * - Sidebar navigation
 * - Main content area
 * - Footer scripts
 * 
 * Required variables:
 * - $admin_url: Admin base URL
 * - $pageTitle: Title shown in browser tab
 * - $pageIcon: Material icon name for header
 * - $pageHeading: Main heading text
 * - $activePage: Current page identifier for sidebar highlighting
 * - $headerActions: (optional) HTML for header action buttons
 * - $pageContent: The main page content HTML
 * - $pageScripts: (optional) Additional JavaScript for the page
 * 
 * Standard variables (from getSidebarData()):
 * - $content: Content stats
 * - $taxonomies: Taxonomy counts
 * - $taxonomyConfig: Taxonomy configuration
 * - $customPages: Plugin pages array
 * - $version: Ava version
 * - $user: Current user email
 * - $site: Site config array
 * - $adminTheme: Admin color theme (cyan, pink, purple, green, blue, amber)
 */

// Generate admin CSS path with cache busting
$adminCssPath = '/admin-assets/admin.css';
$adminCssFile = dirname(__DIR__) . '/admin.css';
if (file_exists($adminCssFile)) {
    $adminCssPath .= '?v=' . filemtime($adminCssFile);
}

// CodeMirror assets (only loaded on editor pages when $useCodeMirror is true)
$cmCssPath = '/admin-assets/codemirror/codemirror.css';
$cmJsPath = '/admin-assets/codemirror/codemirror-init.js';
$cmCssFile = dirname(__DIR__) . '/assets/codemirror/codemirror.css';
if (file_exists($cmCssFile)) {
    $cmCssPath .= '?v=' . filemtime($cmCssFile);
}
?>
<!DOCTYPE html>
<html lang="en" data-accent="<?= htmlspecialchars($adminTheme ?? 'cyan') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📝</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
    <?php if (!empty($useCodeMirror)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cmCssPath) ?>">
    <script type="module" src="<?= htmlspecialchars($cmJsPath) ?>"></script>
    <?php endif; ?>
    <?php include __DIR__ . '/_theme.php'; ?>
</head>
<body>
<div class="app-shell no-transition" id="app-shell">
    <div class="sidebar-backdrop" onclick="toggleSidebar()"></div>
    
    <?php include __DIR__ . '/_sidebar.php'; ?>
    
    <header class="app-topbar">
        <button class="topbar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
            <span class="material-symbols-rounded">menu</span>
        </button>
        
        <div class="topbar-brand">
            <a href="<?= htmlspecialchars($site['url'] ?? '/') ?>" class="topbar-brand-link" target="_blank" rel="noopener noreferrer" title="Visit site">
                <span class="topbar-brand-text"><?= htmlspecialchars($site['name'] ?? 'Site') ?></span>
            </a>
        </div>
        
<span class="topbar-sep">/</span>
        
        <div class="topbar-context">
            <span class="topbar-page"><?= htmlspecialchars($pageHeading ?? $pageTitle ?? 'Admin') ?></span>
        </div>
        
        <div class="topbar-spacer"></div>
        
        <a href="https://ava.addy.zone/docs" target="_blank" rel="noopener noreferrer" class="topbar-btn topbar-docs-btn" title="Documentation">
            <span class="material-symbols-rounded">menu_book</span>
            <span class="topbar-btn-label">Docs</span>
        </a>
        
        <div class="topbar-user">
            <span class="topbar-user-name"><?= htmlspecialchars($user ?? 'Admin') ?></span>
            <button onclick="toggleTheme()" class="topbar-btn topbar-theme-btn" title="Toggle theme">
                <span class="material-symbols-rounded theme-icon-light">light_mode</span>
                <span class="material-symbols-rounded theme-icon-dark">dark_mode</span>
                <span class="material-symbols-rounded theme-icon-system">contrast</span>
            </button>
            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/logout" class="topbar-logout-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="topbar-btn topbar-btn-danger" title="Sign out">
                    <span class="material-symbols-rounded">logout</span>
                </button>
            </form>
        </div>
    </header>
    
    <div class="app-main">
        <div class="app-content">
            <?php if (!empty($alertSuccess)): ?>
            <div class="alert alert-success">
                <span class="material-symbols-rounded">check_circle</span>
                <?= htmlspecialchars($alertSuccess) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($alertError)): ?>
            <div class="alert alert-danger">
                <span class="material-symbols-rounded">error</span>
                <?= htmlspecialchars($alertError) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($alertWarning)): ?>
            <div class="alert alert-warning">
                <span class="material-symbols-rounded">warning</span>
                <?= htmlspecialchars($alertWarning) ?>
            </div>
            <?php endif; ?>

            <?php if (empty($hidePageHeader)): ?>
            <div class="page-header">
                <h1 class="page-title">
                    <span class="material-symbols-rounded"><?= htmlspecialchars($pageIcon ?? 'extension') ?></span>
                    <?= htmlspecialchars($pageHeading ?? $pageTitle ?? 'Admin') ?>
                </h1>
                <?php if (!empty($headerActions)): ?>
                <div class="page-actions">
                    <?= $headerActions ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?= $pageContent ?? '' ?>
            
            <footer class="admin-footer">
                <div class="admin-footer-content">
                    <span class="admin-footer-powered">
                        Powered by <a href="https://ava.addy.zone" target="_blank" rel="noopener noreferrer">Ava CMS</a>
                        <a href="https://github.com/avacms/ava/releases" target="_blank" rel="noopener noreferrer" class="admin-footer-version">v<?= htmlspecialchars($version ?? '1.0.0') ?></a>
                    </span>
                    <nav class="admin-footer-links">
                        <a href="https://ava.addy.zone/docs" target="_blank" rel="noopener noreferrer">Docs</a>
                        <a href="https://ava.addy.zone/themes" target="_blank" rel="noopener noreferrer">Themes</a>
                        <a href="https://ava.addy.zone/plugins" target="_blank" rel="noopener noreferrer">Plugins</a>
                        <a href="https://github.com/avacms/ava" target="_blank" rel="noopener noreferrer">GitHub</a>
                        <a href="https://discord.gg/fZwW4jBVh5" target="_blank" rel="noopener noreferrer">Discord</a>
                        <a href="https://github.com/avacms/ava/blob/main/LICENSE" target="_blank" rel="noopener noreferrer">License</a>
                    </nav>
                </div>
            </footer>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const shell = document.getElementById('app-shell');
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        sidebar.classList.toggle('open');
        document.querySelector('.sidebar-backdrop').classList.toggle('open');
    } else {
        shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', shell.classList.contains('sidebar-collapsed'));
    }
}

// Restore sidebar state on load
(function() {
    const collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (collapsed && window.innerWidth > 768) {
        document.getElementById('app-shell').classList.add('sidebar-collapsed');
    }
    // Remove no-transition class after initial state is set
    requestAnimationFrame(() => {
        document.getElementById('app-shell').classList.remove('no-transition');
    });
})();

// Theme toggle with 3 states: light -> dark -> system
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    let next;
    if (current === 'light') {
        next = 'dark';
    } else if (current === 'dark') {
        next = 'system';
    } else {
        next = 'light';
    }
    
    if (next === 'system') {
        html.removeAttribute('data-theme');
        document.cookie = 'theme=; path=/; max-age=0';
    } else {
        html.setAttribute('data-theme', next);
        document.cookie = `theme=${next}; path=/; max-age=31536000`;
    }
    
    updateThemeIcon();
}

function updateThemeIcon() {
    const html = document.documentElement;
    const theme = html.getAttribute('data-theme');
    const btn = document.querySelector('.topbar-theme-btn');
    if (!btn) return;
    
    btn.classList.remove('theme-light', 'theme-dark', 'theme-system');
    if (theme === 'light') {
        btn.classList.add('theme-light');
    } else if (theme === 'dark') {
        btn.classList.add('theme-dark');
    } else {
        btn.classList.add('theme-system');
    }
}

// Initialize theme icon on load
updateThemeIcon();
</script>
<?php if (!empty($pageScripts)): ?>
<script>
<?= $pageScripts ?>
</script>
<?php endif; ?>
</body>
</html>
