<?php
/**
 * Taxonomy View - Content Only View
 * 
 * Available variables:
 * - $taxonomy: Taxonomy slug
 * - $config: Taxonomy configuration
 * - $terms: Array of terms with counts
 * - $allContent: Content stats for sidebar
 * - $taxonomyConfig: All taxonomy configurations
 * - $site: Site configuration
 */

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
                            <div class="d-flex align-items-center gap-2">
                                <span class="material-symbols-rounded text-tertiary icon-sm">label</span>
                                <span class="font-medium"><?= htmlspecialchars($termData['name']) ?></span>
                            </div>
                        </td>
                        <td><code class="text-xs"><?= htmlspecialchars($slug) ?></code></td>
                        <td>
                            <span class="badge <?= $termData['count'] > 0 ? 'badge-accent' : 'badge-muted' ?>">
                                <?= $termData['count'] ?>
                            </span>
                        </td>
                        <td class="w-120">
                            <div class="progress-bar">
                                <div class="progress-fill accent" style="width: <?= $usagePercent ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="<?= htmlspecialchars($termUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-secondary" title="View archive">
                                    <span class="material-symbols-rounded">open_in_new</span>
                                </a>
                                <a href="<?= htmlspecialchars($admin_url) ?>/taxonomy/<?= htmlspecialchars($taxonomy) ?>/<?= htmlspecialchars($slug) ?>/delete" class="btn btn-xs btn-secondary" title="Delete term">
                                    <span class="material-symbols-rounded">delete</span>
                                </a>
                            </div>
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
            <a href="<?= htmlspecialchars($admin_url) ?>/taxonomy/<?= htmlspecialchars($taxonomy) ?>/create" class="btn btn-primary mt-3">
                <span class="material-symbols-rounded">add</span>
                Add Term
            </a>
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

