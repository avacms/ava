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

// Generate admin CSS path with cache busting (uses $admin_url from controller)
$adminCssPath = $admin_url . '/assets/admin.css';
$adminCssFile = dirname(__DIR__) . '/admin.css';
if (file_exists($adminCssFile)) {
    $adminCssPath .= '?v=' . filemtime($adminCssFile);
}

// CodeMirror assets (only loaded on editor pages when $useCodeMirror is true)
$cmCssPath = $admin_url . '/assets/codemirror/codemirror.css';
$cmJsPath = $admin_url . '/assets/codemirror/codemirror-init.js';
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
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ava Admin">
    <meta name="theme-color" content="#0f172a">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📝</text></svg>">
    <link rel="manifest" href="<?= htmlspecialchars($admin_url) ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($admin_url) ?>/assets/icon.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
    <?php if (!empty($useCodeMirror)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cmCssPath) ?>">
    <script type="module" src="<?= htmlspecialchars($cmJsPath) ?>"></script>
    <?php endif; ?>
    <?php include __DIR__ . '/_theme.php'; ?>
</head>
<body>
<!-- Skip links for keyboard accessibility -->
<a href="#main-content" class="skip-link">Skip to main content</a>
<?php if (isset($cmJsPath)): ?>
<a href="#editor-content" class="skip-link">Skip to editor <span class="skip-link-hint">(press Escape to exit editor)</span></a>
<?php endif; ?>

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
        
        <button class="topbar-btn topbar-search-btn" onclick="openAdminSearch()" id="search-btn">
            <span class="material-symbols-rounded">search</span>
            <span class="topbar-btn-label">Search</span>
            <kbd class="topbar-kbd" id="search-kbd"></kbd>
        </button>
        
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
        <div class="app-content" id="main-content">
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
    const isMobile = window.innerWidth <= 960;
    
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
    if (collapsed && window.innerWidth > 960) {
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

// Register service worker for PWA (scope defaults to /admin/assets/)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= htmlspecialchars($admin_url) ?>/assets/sw.js')
        .catch(() => {});
}

// Set up search shortcut display based on platform/touch
(function() {
    const kbd = document.getElementById('search-kbd');
    const btn = document.getElementById('search-btn');
    if (!kbd || !btn) return;
    
    // Detect touch device
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isTouchDevice) {
        kbd.style.display = 'none';
        btn.title = 'Search';
    } else {
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        kbd.textContent = isMac ? '⌘K' : 'Ctrl+K';
        btn.title = isMac ? 'Search (⌘K)' : 'Search (Ctrl+K)';
    }
})();

// -------------------------------------------------------------------------
// Quick Search Overlay
// -------------------------------------------------------------------------
(function() {
    const adminUrl = '<?= htmlspecialchars($admin_url) ?>';
    let overlay = null;
    let input = null;
    let results = null;
    let searchTimeout = null;
    let selectedIndex = -1;
    let isInitialized = false;

    function createOverlay() {
        if (overlay) return;
        
        overlay = document.createElement('div');
        overlay.className = 'search-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="search-overlay-content">
                <div class="search-overlay-header">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input 
                        type="text" 
                        id="admin-search-input" 
                        class="search-overlay-input"
                        placeholder="Search content & admin pages..."
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <kbd class="search-shortcut">ESC</kbd>
                </div>
                <div id="admin-search-results" class="search-overlay-results">
                    <div class="search-hint">Type to search...</div>
                </div>
                <div class="search-overlay-footer">
                    <div class="search-hint">
                        <kbd>↑</kbd> <kbd>↓</kbd> navigate
                        <kbd>↵</kbd> select
                        <kbd>ESC</kbd> close
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        input = document.getElementById('admin-search-input');
        results = document.getElementById('admin-search-results');
        
        // Close on backdrop click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeSearch();
        });
        
        // Input handler
        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const query = input.value.trim();
            
            if (query.length < 2) {
                results.innerHTML = '<div class="search-hint">Type at least 2 characters...</div>';
                selectedIndex = -1;
                return;
            }
            
            results.innerHTML = '<div class="search-hint">Searching...</div>';
            searchTimeout = setTimeout(() => performSearch(query), 200);
        });
        
        // Keyboard navigation
        input.addEventListener('keydown', (e) => {
            const items = results.querySelectorAll('.search-result');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0 && items[selectedIndex]) {
                e.preventDefault();
                items[selectedIndex].click();
            }
        });
    }
    
    function performSearch(query) {
        fetch(`${adminUrl}/api/search?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(displayResults)
            .catch(() => {
                results.innerHTML = '<div class="search-hint">Search unavailable</div>';
            });
    }
    
    function displayResults(data) {
        selectedIndex = -1;
        
        if (!data.items || data.items.length === 0) {
            let msg = '<div class="search-hint">No results found</div>';
            if (data.indexStale) {
                msg += '<div class="search-stale-notice">Index is stale – <a href="' + adminUrl + '">rebuild</a> for the most up-to-date results</div>';
            }
            results.innerHTML = msg;
            return;
        }
        
        let html = data.items.map((item, i) => `
            <a href="${escapeHtml(item.url)}" class="search-result" data-index="${i}">
                <span class="material-symbols-rounded search-result-icon">${escapeHtml(item.icon || 'description')}</span>
                <div class="search-result-content">
                    <div class="search-result-title">${escapeHtml(item.title)}</div>
                    <div class="search-result-meta">${escapeHtml(item.type)}</div>
                </div>
            </a>
        `).join('');
        
        if (data.indexStale) {
            html += '<div class="search-stale-notice">Index is stale – <a href="' + adminUrl + '">rebuild</a> for up-to-date results</div>';
        }
        
        results.innerHTML = html;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    function updateSelection(items) {
        items.forEach((item, i) => {
            item.classList.toggle('selected', i === selectedIndex);
        });
        if (selectedIndex >= 0 && items[selectedIndex]) {
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    
    window.openAdminSearch = function() {
        createOverlay();
        
        if (!isInitialized) {
            isInitialized = true;
            overlay.classList.add('initialized');
        }
        
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        // Focus with aggressive retry for reliability
        input.focus({ preventScroll: true });
        input.select();
        requestAnimationFrame(() => {
            input.focus({ preventScroll: true });
            input.select();
        });
    };
    
    function closeSearch() {
        if (!overlay) return;
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        input.value = '';
        results.innerHTML = '<div class="search-hint">Type to search...</div>';
        selectedIndex = -1;
        document.body.style.overflow = '';
    }
    
    // Global keyboard shortcut: Cmd/Ctrl + K
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            openAdminSearch();
        }
        if (e.key === 'Escape' && overlay?.classList.contains('active')) {
            closeSearch();
        }
    });
})();
</script>
<?php if (!empty($pageScripts)): ?>
<script>
<?= $pageScripts ?>
</script>
<?php endif; ?>
</body>
</html>
