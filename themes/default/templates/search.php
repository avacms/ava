<?php
/**
 * Search Template
 * 
 * This template handles the search page. The search route is registered
 * in theme.php using the hook system to intercept /search requests.
 * 
 * Available variables (passed from theme.php):
 *   $query       - Pre-configured Query with search applied
 *   $searchQuery - The user's search term (string)
 *   $request     - The HTTP request object
 *   $ava         - Template helper
 *   $site        - Site configuration array
 * 
 * @see https://ava.addy.zone/#/themes?id=search
 * @see https://ava.addy.zone/#/api?id=search-endpoint
 */

$pageTitle = 'Search' . ($searchQuery ? ': ' . $searchQuery : '') . ' - ' . $site['name'];
?>
<?= $ava->partial('header', ['request' => $request, 'pageTitle' => $pageTitle]) ?>

        <div class="container">
            <header class="page-header">
                <h1>Search</h1>
            </header>

            <?php /* Search form - uses GET method so results are bookmarkable */ ?>
            <form class="search-form" action="/search" method="get">
                <input 
                    type="search" 
                    name="q" 
                    class="search-input" 
                    placeholder="Search content..." 
                    value="<?= $ava->e($searchQuery) ?>"
                    autofocus
                >
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <?php if ($searchQuery !== ''): ?>
                <?php
                /**
                 * Query Execution & Counting
                 * 
                 * $query->get() returns the paginated results.
                 * $query->count() returns the total number of matches
                 * (not just the current page).
                 */
                $results = $query->get();
                $total = $query->count();
                ?>

                <p class="search-results-info">
                    Found <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for "<?= $ava->e($searchQuery) ?>"
                </p>

                <?php if (empty($results)): ?>
                    <div class="search-empty">
                        <p>No results found. Try a different search term.</p>
                    </div>
                <?php else: ?>
                    <div class="archive-list">
                        <?php foreach ($results as $entry): ?>
                            <article class="archive-item">
                                <h2>
                                    <a href="<?= $ava->url($entry->type(), $entry->slug()) ?>">
                                        <?= $ava->e($entry->title()) ?>
                                    </a>
                                </h2>

                                <div class="meta">
                                    <?php /* Show content type so users know what they're clicking */ ?>
                                    <span><?= $ava->e(ucfirst($entry->type())) ?></span>
                                    <?php if ($entry->date()): ?>
                                        &middot;
                                        <time datetime="<?= $entry->date()->format('c') ?>">
                                            <?= $ava->date($entry->date()) ?>
                                        </time>
                                    <?php endif; ?>
                                </div>

                                <?php if ($entry->excerpt()): ?>
                                    <p class="excerpt"><?= $ava->e($entry->excerpt()) ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php /* Preserve search query in pagination URLs */ ?>
                    <?= $ava->pagination($query, '/search?q=' . urlencode($searchQuery)) ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="search-empty">
                    <p>Enter a search term above to find content.</p>
                </div>
            <?php endif; ?>
        </div>

<?= $ava->partial('footer') ?>
