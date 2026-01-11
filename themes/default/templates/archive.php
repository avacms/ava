<?php
/**
 * Archive Template
 * 
 * This template displays a paginated list of content items, typically used
 * for blog listing pages (e.g., /blog).
 * 
 * The router provides a pre-configured $query with the correct content type,
 * pagination, and sorting already applied based on the route configuration.
 * 
 * Available variables:
 *   $query         - Pre-configured Query object
 *   $route         - The matched route (contains content_type param)
 *   $request       - The HTTP request object
 *   $ava           - Template helper
 *   $site          - Site configuration array
 * 
 * @see https://ava.addy.zone/docs/theming
 * @see https://ava.addy.zone/docs/routing
 */

// Get the content type from route parameters (default: 'Archive')
$contentType = $route->getParam('content_type', 'Archive');
$pageTitle = ucfirst($contentType) . ' - ' . $site['name'];
?>
<?= $ava->partial('header', ['request' => $request, 'pageTitle' => $pageTitle]) ?>

        <div class="container">
            <header class="page-header">
                <h1><?= $ava->e(ucfirst($contentType)) ?></h1>
            </header>

            <?php
            /**
             * Execute the Query
             * 
             * $query->get() executes the query and returns an array of
             * content items. The query is lazy - it doesn't hit the index
             * until you call get() or iterate.
             * 
             * @see https://ava.addy.zone/docs/theming
             */
            $results = $query->get();
            ?>

            <?php if (empty($results)): ?>
                <div class="search-empty">
                    <p>No content found.</p>
                </div>
            <?php else: ?>
                <div class="archive-list">
                    <?php foreach ($results as $entry): ?>
                        <article class="archive-item">
                            <h2>
                                <?php
                                /**
                                 * Content URLs
                                 * 
                                 * $ava->url($type, $slug) generates the correct URL
                                 * for a content item based on your routing config.
                                 */
                                ?>
                                <a href="<?= $ava->url($entry->type(), $entry->slug()) ?>">
                                    <?= $ava->e($entry->title()) ?>
                                </a>
                            </h2>

                            <?php if ($entry->date()): ?>
                                <div class="meta">
                                    <time datetime="<?= $entry->date()->format('c') ?>">
                                        <?= $ava->date($entry->date()) ?>
                                    </time>
                                </div>
                            <?php endif; ?>

                            <?php
                            /**
                             * Excerpt
                             * 
                             * $entry->excerpt() returns the excerpt from frontmatter,
                             * or auto-generates one from content if not specified.
                             */
                            ?>
                            <?php if ($entry->excerpt()): ?>
                                <p class="excerpt"><?= $ava->e($entry->excerpt()) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php
                /**
                 * Pagination
                 * 
                 * $ava->pagination($query, $basePath) renders prev/next links
                 * and page info. The query object tracks the current page
                 * and total items.
                 * 
                 * @see https://ava.addy.zone/docs/theming
                 */
                ?>
                <?= $ava->pagination($query, $request->path()) ?>
            <?php endif; ?>
        </div>

<?= $ava->partial('footer') ?>
