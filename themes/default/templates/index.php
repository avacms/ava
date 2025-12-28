<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (isset($page)): ?>
        <?= $ava->metaTags($page) ?>
        <?= $ava->itemAssets($page) ?>
    <?php else: ?>
        <title><?= $ava->e($site['name']) ?></title>
    <?php endif; ?>
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
            <?php if (isset($page)): ?>
                <article class="single">
                    <header class="entry-header">
                        <h1><?= $ava->e($page->title()) ?></h1>
                        <?php if ($page->date()): ?>
                            <time datetime="<?= $page->date()->format('c') ?>">
                                <?= $ava->date($page->date()) ?>
                            </time>
                        <?php endif; ?>
                    </header>

                    <div class="entry-content">
                        <?= $ava->content($page) ?>
                    </div>
                </article>
            <?php elseif (isset($query)): ?>
                <?php $items = $query->get(); ?>
                <?php if (empty($items)): ?>
                    <p>No content found.</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <article class="archive-item">
                            <h2>
                                <a href="<?= $ava->url($item->type(), $item->slug()) ?>">
                                    <?= $ava->e($item->title()) ?>
                                </a>
                            </h2>
                            <?php if ($item->excerpt()): ?>
                                <p><?= $ava->e($item->excerpt()) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>

                    <?= $ava->pagination($query) ?>
                <?php endif; ?>
            <?php else: ?>
                <p>Welcome to <?= $ava->e($site['name']) ?></p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= $ava->e($site['name']) ?></p>
        </div>
    </footer>
</body>
</html>
