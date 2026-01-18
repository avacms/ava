<!DOCTYPE html>
<?php
// Generate admin CSS path with cache busting
$adminCssPath = '/admin-assets/admin.css';
$adminCssFile = dirname(__DIR__) . '/admin.css';
if (file_exists($adminCssFile)) {
    $adminCssPath .= '?v=' . filemtime($adminCssFile);
}
?>
<html lang="en" data-accent="<?= htmlspecialchars($adminTheme ?? 'cyan') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · Ava CMS</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
    <?php include __DIR__ . '/_theme.php'; ?>
</head>
<body class="login-page">
<div class="login-card">
    <div class="login-header">
        <h1>Sign in to continue</h1>
        <p>Powered by Ava CMS</p>
    </div>

    <?php if (!$hasUsers): ?>
    <div class="login-notice">
        <span class="material-symbols-rounded">info</span>
        <div>
            <strong>No admin users configured</strong>
            <code>./ava user:add email@example.com password</code>
        </div>
    </div>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="login-error">
        <span class="material-symbols-rounded">error</span>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($loginUrl) ?>">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        
        <div class="form-group">
            <label for="email">
                <span class="material-symbols-rounded">mail</span>
                Email
            </label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">
                <span class="material-symbols-rounded">lock</span>
                Password
            </label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <span class="material-symbols-rounded">login</span>
            Sign In
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
