<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $ava->metaTags($page) ?>
    <?= $ava->itemAssets($page) ?>
    <link rel="stylesheet" href="<?= $ava->asset('style.css') ?>">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="/" class="site-title"><?= $ava->e($site['name']) ?></a>
            <nav class="main-nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <article class="single post">
                <header class="entry-header">
                    <h1><?= $ava->e($page->title()) ?></h1>

                    <div class="entry-meta">
                        <?php if ($page->date()): ?>
                            <time datetime="<?= $page->date()->format('c') ?>">
                                <?= $ava->date($page->date()) ?>
                            </time>
                        <?php endif; ?>

                        <?php $categories = $page->terms('category'); ?>
                        <?php if (!empty($categories)): ?>
                            <span class="categories">
                                in
                                <?php foreach ($categories as $cat): ?>
                                    <a href="<?= $ava->termUrl('category', $cat) ?>"><?= $ava->e($cat) ?></a>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="entry-content">
                    <?= $ava->content($page) ?>
                </div>

                <footer class="entry-footer">
                    <?php $tags = $page->terms('tag'); ?>
                    <?php if (!empty($tags)): ?>
                        <div class="tags">
                            Tags:
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?= $ava->termUrl('tag', $tag) ?>">#<?= $ava->e($tag) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </footer>
            </article>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= $ava->e($site['name']) ?></p>
        </div>
    </footer>
</body>
</html>
