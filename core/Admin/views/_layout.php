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
?>
<!DOCTYPE html>
<html lang="en" data-accent="<?= htmlspecialchars($adminTheme ?? 'cyan') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · Ava Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="/assets/admin.css">
    <?php include __DIR__ . '/_theme.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="layout">
    <main class="main">
        <div class="mobile-header">
            <button class="menu-btn" onclick="toggleSidebar()">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <h1>✨ Ava</h1>
        </div>

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

        <div class="header">
            <h2>
                <span class="material-symbols-rounded"><?= htmlspecialchars($pageIcon ?? 'extension') ?></span>
                <?= htmlspecialchars($pageHeading ?? $pageTitle ?? 'Admin') ?>
            </h2>
            <?php if (!empty($headerActions)): ?>
            <div class="header-actions">
                <?= $headerActions ?>
            </div>
            <?php endif; ?>
        </div>

        <?= $pageContent ?? '' ?>

    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.sidebar-backdrop').classList.toggle('open');
}
</script>
<?php if (!empty($pageScripts)): ?>
<script>
<?= $pageScripts ?>
</script>
<?php endif; ?>
</body>
</html>
