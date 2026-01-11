<?php
/**
 * Page Template
 * 
 * This template renders static pages (content in content/pages/).
 * 
 * Pages use this template by default, or can specify a custom template
 * in frontmatter: template: custom.php
 * 
 * Available variables:
 *   $content  - The page content item (Ava\Content\Item)
 *   $request  - The HTTP request object
 *   $route    - The matched route
 *   $ava      - Template helper
 *   $site     - Site configuration array
 * 
 * @see https://ava.addy.zone/docs/theming
 */
?>
<?= $ava->partial('header', ['request' => $request, 'pageTitle' => $content->title() . ' - ' . $site['name']]) ?>

        <div class="container">
            <article class="entry">
                <header class="entry-header">
                    <?php /* Page title - $ava->e() escapes special characters */ ?>
                    <h1><?= $ava->e($content->title()) ?></h1>
                </header>

                <div class="entry-content">
                    <?php
                    /**
                     * Render Content Body
                     * 
                     * $ava->body($content) converts Markdown to HTML and
                     * processes any shortcodes in the content.
                     * 
                     * The output is already escaped where needed, so don't
                     * wrap this in $ava->e().
                     * 
                     * @see https://ava.addy.zone/docs/theming
                     */
                    ?>
                    <?= $ava->body($content) ?>
                </div>
            </article>
        </div>

<?= $ava->partial('footer') ?>
