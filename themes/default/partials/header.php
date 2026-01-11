<?php
/**
 * Header Partial
 * 
 * This partial contains the document head and site header. It's included at
 * the start of every page template using $ava->partial('header', [...]).
 * 
 * Available variables (passed from templates):
 *   $request       - The current HTTP request object
 *   $pageTitle     - Custom page title (optional)
 *   $pageDescription - Custom meta description (optional)
 *   $item          - Content item for meta tags (optional)
 * 
 * Automatically available in all templates:
 *   $ava   - The template helper with all rendering methods
 *   $site  - Site configuration array from app/config/ava.php
 * 
 * @see https://ava.addy.zone/docs/theming
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    /**
     * Emoji Favicon
     * 
     * A simple way to add a favicon without creating an image file.
     * The SVG data URI renders an emoji as the favicon. Change the emoji
     * to customize your site's icon, or replace with a traditional favicon:
     * <link rel="icon" href="/favicon.ico">
     */
    ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✨</text></svg>">
    
    <?php
    /**
     * Essential SEO Meta Tags
     * 
     * These tags help search engines and social media understand your site.
     * Customize these for your specific site. For content pages, $ava->metaTags()
     * generates these automatically from your content.
     */
    ?>
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= $ava->e($site['name']) ?>">
    <meta property="og:site_name" content="<?= $ava->e($site['name']) ?>">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary">
    
    <?php if (isset($item)): ?>
        <?php
        /**
         * Meta Tags for Content Items
         * 
         * $ava->metaTags($item) generates SEO-friendly meta tags including:
         *   - <title> with site name appended
         *   - description from excerpt or content
         *   - Open Graph tags for social sharing
         *   - canonical URL
         * 
         * @see https://ava.addy.zone/docs/theming
         */
        ?>
        <?= $ava->metaTags($item) ?>
        
        <?php
        /**
         * Per-Item Assets
         * 
         * $ava->itemAssets($item) allows content items to load their own
         * CSS or JavaScript by specifying 'css' or 'js' in frontmatter.
         */
        ?>
        <?= $ava->itemAssets($item) ?>
    <?php else: ?>
        <?php
        /**
         * Manual Meta Tags
         * 
         * For non-content pages (like search or archives), set meta tags
         * manually. $ava->e() escapes output to prevent XSS attacks.
         * Always escape user-provided or dynamic content!
         * 
         * @see https://ava.addy.zone/docs/theming
         */
        $title = $pageTitle ?? $site['name'];
        $description = $pageDescription ?? $site['tagline'] ?? '';
        ?>
        <title><?= $ava->e($title) ?></title>
        <meta name="description" content="<?= $ava->e($description) ?>">
        <meta property="og:title" content="<?= $ava->e($title) ?>">
        <meta property="og:description" content="<?= $ava->e($description) ?>">
        <meta property="og:type" content="website">
    <?php endif; ?>
    
    <?php
    /**
     * Theme Assets
     * 
     * $ava->asset('filename') returns the URL to a file in your theme's
     * assets/ directory. URLs include a cache-busting hash based on file
     * modification time.
     * 
     * @see https://ava.addy.zone/docs/theming
     */
    ?>
    <link rel="stylesheet" href="<?= $ava->asset('style.css') ?>">
</head>
<body>
    <?php
    /**
     * Navigation Active State
     * 
     * Get the current path to highlight the active navigation item.
     * The Request object provides the current URL path.
     */
    $currentPath = isset($request) ? $request->path() : '/';
    ?>
    
    <header class="site-header">
        <div class="container">
            <?php
            /**
             * Site Logo/Name
             * 
             * $site['name'] comes from app/config/ava.php. Always escape
             * configuration values that might contain special characters.
             */
            ?>
            <a href="/" class="site-logo"><?= $ava->e($site['name']) ?></a>
            
            <nav class="site-nav">
                <?php
                /**
                 * Navigation Links
                 * 
                 * Add your own navigation items here. The 'active' class is
                 * applied when the current path matches the link.
                 * 
                 * For dynamic navigation based on content, you could query
                 * pages: $ava->query()->type('page')->published()->get()
                 */
                ?>
                <a href="/"<?= $currentPath === '/' ? ' class="active"' : '' ?>>Home</a>
                <a href="/about"<?= $currentPath === '/about' ? ' class="active"' : '' ?>>About</a>
                <a href="/blog"<?= str_starts_with($currentPath, '/blog') ? ' class="active"' : '' ?>>Blog</a>
                <a href="/search"<?= $currentPath === '/search' ? ' class="active"' : '' ?>>Search</a>
            </nav>
            
            <?php /* Mobile navigation toggle - see style.css for responsive styles */ ?>
            <button class="nav-toggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <main class="site-main">
