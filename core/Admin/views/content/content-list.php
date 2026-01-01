<?php
/**
 * Content List - Content Only View
 * 
 * Available variables:
 * - $type: Content type slug
 * - $typeConfig: Content type configuration
 * - $items: Array of content items
 * - $pagination: Pagination data
 * - $stats: Content stats
 * - $allContent: All content stats for sidebar
 * - $taxonomyConfig: Taxonomy configuration
 * - $taxonomyTerms: Terms for each taxonomy
 * - $routes: Routes array
 * - $site: Site configuration
 */

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
    $bytes = $bytes ?? 0;
    $units = ['B', 'KB', 'MB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 2) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
};
?>

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
                        <th>Size</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        $itemUrl = $getContentUrl($item);
                        $itemPath = $getContentPath($item);
                        $fileSize = file_exists($item->filePath()) ? filesize($item->filePath()) : 0;
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
                            <div class="text-sm"><?= $formatBytes($fileSize) ?></div>
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

        <?php if ($pagination['totalPages'] > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= (($pagination['page'] - 1) * $pagination['perPage']) + 1 ?>–<?= min($pagination['page'] * $pagination['perPage'], $pagination['totalItems']) ?> of <?= number_format($pagination['totalItems']) ?>
            </div>
            <div class="pagination-controls">
                <?php if ($pagination['hasPrev']): ?>
                <a href="?page=1" class="btn btn-xs btn-secondary" title="First page">
                    <span class="material-symbols-rounded">first_page</span>
                </a>
                <a href="?page=<?= $pagination['page'] - 1 ?>" class="btn btn-xs btn-secondary" title="Previous page">
                    <span class="material-symbols-rounded">chevron_left</span>
                </a>
                <?php else: ?>
                <span class="btn btn-xs btn-secondary btn-disabled"><span class="material-symbols-rounded">first_page</span></span>
                <span class="btn btn-xs btn-secondary btn-disabled"><span class="material-symbols-rounded">chevron_left</span></span>
                <?php endif; ?>

                <span class="pagination-current">Page <?= $pagination['page'] ?> of <?= $pagination['totalPages'] ?></span>

                <?php if ($pagination['hasMore']): ?>
                <a href="?page=<?= $pagination['page'] + 1 ?>" class="btn btn-xs btn-secondary" title="Next page">
                    <span class="material-symbols-rounded">chevron_right</span>
                </a>
                <a href="?page=<?= $pagination['totalPages'] ?>" class="btn btn-xs btn-secondary" title="Last page">
                    <span class="material-symbols-rounded">last_page</span>
                </a>
                <?php else: ?>
                <span class="btn btn-xs btn-secondary btn-disabled"><span class="material-symbols-rounded">chevron_right</span></span>
                <span class="btn btn-xs btn-secondary btn-disabled"><span class="material-symbols-rounded">last_page</span></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

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
                <div class="list-item"><span class="list-label">Total Size</span><span class="list-value"><?= $formatBytes($stats['totalSize'] ?? 0) ?></span></div>
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
