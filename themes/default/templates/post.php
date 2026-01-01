<?php
/**
 * Post Template
 * 
 * This template renders blog posts (content in content/posts/).
 * 
 * Posts typically include:
 *   - Publication date
 *   - Categories and tags (taxonomies)
 *   - Excerpt for previews
 * 
 * Available variables:
 *   $content  - The post content item (Ava\Content\Item)
 *   $request  - The HTTP request object
 *   $route    - The matched route
 *   $ava      - Template helper
 *   $site     - Site configuration array
 * 
 * @see https://ava.addy.zone/#/themes?id=templates
 * @see https://ava.addy.zone/#/content?id=taxonomies
 */
?>
<?= $ava->partial('header', ['request' => $request]) ?>

        <div class="container">
            <article class="entry">
                <header class="entry-header">
                    <h1><?= $ava->e($content->title()) ?></h1>
                    
                    <div class="entry-meta">
                        <?php
                        /**
                         * Date Display
                         * 
                         * $content->date() returns a DateTimeImmutable object
                         * (or null if no date set). Use ->format() for machine-
                         * readable dates and $ava->date() for human-friendly display.
                         * 
                         * The date format can be configured in app/config/ava.php.
                         */
                        ?>
                        <?php if ($content->date()): ?>
                            <time datetime="<?= $content->date()->format('c') ?>">
                                <?= $ava->date($content->date()) ?>
                            </time>
                        <?php endif; ?>

                        <?php
                        /**
                         * Taxonomy Terms (Categories)
                         * 
                         * $content->terms('taxonomy_name') returns an array of term
                         * slugs assigned to this content. Taxonomies are defined in
                         * app/config/taxonomies.php and term metadata is stored in
                         * content/_taxonomies/.
                         * 
                         * $ava->termUrl() generates the URL to a taxonomy term page.
                         * 
                         * @see https://ava.addy.zone/#/content?id=taxonomies
                         */
                        ?>
                        <?php $categories = $content->terms('category'); ?>
                        <?php if (!empty($categories)): ?>
                            <span>
                                in
                                <?php foreach ($categories as $i => $cat): ?>
                                    <a href="<?= $ava->termUrl('category', $cat) ?>"><?= $ava->e($cat) ?></a><?= $i < count($categories) - 1 ? ', ' : '' ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="entry-content">
                    <?= $ava->body($content) ?>
                </div>

                <?php /* Tags displayed at the bottom of the post */ ?>
                <?php $tags = $content->terms('tag'); ?>
                <?php if (!empty($tags)): ?>
                    <footer class="entry-footer">
                        <div class="entry-tags">
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?= $ava->termUrl('tag', $tag) ?>" class="tag">#<?= $ava->e($tag) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </footer>
                <?php endif; ?>
            </article>
        </div>

<?= $ava->partial('footer') ?>
