<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ava->e($route->getParam('content_type', 'Archive')) ?> - <?= $ava->e($site['name']) ?></title>
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
            <header class="archive-header">
                <h1>
                    <?php
                    $contentType = $route->getParam('content_type', '');
                    echo $ava->e(ucfirst($contentType));
                    ?>
                </h1>
            </header>

            <?php $items = $query->get(); ?>

            <?php if (empty($items)): ?>
                <p>No content found.</p>
            <?php else: ?>
                <div class="archive-list">
                    <?php foreach ($items as $item): ?>
                        <article class="archive-item">
                            <h2>
                                <a href="<?= $ava->url($item->type(), $item->slug()) ?>">
                                    <?= $ava->e($item->title()) ?>
                                </a>
                            </h2>

                            <?php if ($item->date()): ?>
                                <time datetime="<?= $item->date()->format('c') ?>">
                                    <?= $ava->date($item->date()) ?>
                                </time>
                            <?php endif; ?>

                            <?php if ($item->excerpt()): ?>
                                <p class="excerpt"><?= $ava->e($item->excerpt()) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?= $ava->pagination($query, $request->path()) ?>
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
