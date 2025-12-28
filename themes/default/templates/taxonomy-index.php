<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ava->e($tax['config']['label'] ?? ucfirst($tax['name'])) ?> - <?= $ava->e($site['name']) ?></title>
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
            <header class="taxonomy-header">
                <h1><?= $ava->e($tax['config']['label'] ?? ucfirst($tax['name'])) ?></h1>
            </header>

            <?php $terms = $tax['terms'] ?? []; ?>

            <?php if (empty($terms)): ?>
                <p>No terms in this taxonomy yet.</p>
            <?php else: ?>
                <div class="term-grid">
                    <?php 
                    $baseUrl = $tax['config']['rewrite']['base'] ?? '/' . $tax['name'];
                    foreach ($terms as $slug => $termData): 
                        $itemCount = count($termData['items'] ?? []);
                    ?>
                        <a href="<?= $ava->e($baseUrl . '/' . $slug) ?>" class="term-card">
                            <span class="term-name"><?= $ava->e($termData['name'] ?? $slug) ?></span>
                            <span class="term-count"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
                            <?php if (!empty($termData['description'])): ?>
                                <p class="term-description"><?= $ava->e($termData['description']) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
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
