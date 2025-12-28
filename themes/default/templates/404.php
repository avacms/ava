<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?= $ava->e($site['name']) ?></title>
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
            <article class="error-page">
                <h1>404 - Page Not Found</h1>
                <p>Sorry, the page you're looking for doesn't exist.</p>
                <p><a href="/">Return to homepage</a></p>
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
