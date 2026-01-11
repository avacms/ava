<?php
/**
 * Taxonomy Index Template
 * 
 * This template displays all terms in a taxonomy as a grid of cards.
 * For example, /category shows all category terms with item counts.
 * 
 * Use this template to let users browse available categories, tags,
 * or other taxonomy terms.
 * 
 * Available variables:
 *   $tax     - Taxonomy data array containing:
 *              ['name'] - Taxonomy slug (e.g., 'category')
 *              ['terms'] - All terms with their metadata and item counts
 *              ['config'] - Taxonomy configuration from taxonomies.php
 *   $request - The HTTP request object
 *   $ava     - Template helper
 *   $site    - Site configuration array
 * 
 * @see https://ava.addy.zone/docs/content
 * @see https://ava.addy.zone/docs/configuration
 */

$taxLabel = $tax['config']['label'] ?? ucfirst($tax['name']);
$pageTitle = $taxLabel . ' - ' . $site['name'];
?>
<?= $ava->partial('header', ['request' => $request, 'pageTitle' => $pageTitle]) ?>

        <div class="container">
            <header class="page-header">
                <h1><?= $ava->e($taxLabel) ?></h1>
            </header>

            <?php
            /**
             * Taxonomy Terms
             * 
             * $tax['terms'] is an associative array where keys are term slugs
             * and values contain term metadata:
             *   - name: Display name
             *   - description: Optional description
             *   - items: Array of content IDs with this term
             * 
             * Term metadata is defined in content/_taxonomies/{taxonomy}.yml
             */
            $terms = $tax['terms'] ?? [];
            ?>

            <?php if (empty($terms)): ?>
                <div class="search-empty">
                    <p>No terms in this taxonomy yet.</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php 
                    // Get the base URL from taxonomy config (e.g., '/category')
                    $baseUrl = $tax['config']['rewrite']['base'] ?? '/' . $tax['name'];
                    
                    foreach ($terms as $slug => $termData): 
                        $itemCount = count($termData['items'] ?? []);
                    ?>
                        <a href="<?= $ava->e($baseUrl . '/' . $slug) ?>" class="card">
                            <div class="card-title"><?= $ava->e($termData['name'] ?? $slug) ?></div>
                            <div class="card-count"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></div>
                            <?php if (!empty($termData['description'])): ?>
                                <p class="card-description"><?= $ava->e($termData['description']) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

<?= $ava->partial('footer') ?>
