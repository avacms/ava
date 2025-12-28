# Themes

Themes control how content is rendered. They're plain PHP templates — no template language to learn.

## Philosophy

Ava themes are deliberately simple:

- **Plain PHP** — Use `<?= ?>` for output. No Blade, no Twig, no new syntax.
- **Helper object** — The `$ava` helper provides content queries, URLs, formatting.
- **Full control** — You write the HTML. Ava doesn't impose a structure.
- **Zero build step** — No compilation. Edit and refresh.

## Structure

```
themes/
└── default/
    ├── theme.php         # Theme bootstrap (optional)
    ├── templates/        # Page templates
    │   ├── index.php     # Fallback template
    │   ├── page.php      # Pages
    │   ├── post.php      # Posts
    │   ├── archive.php   # Archive listings
    │   ├── taxonomy.php  # Taxonomy archives
    │   └── 404.php       # Not found
    ├── partials/         # Reusable parts
    │   ├── header.php
    │   └── footer.php
    └── assets/           # Theme assets (CSS, JS, images)
```

## Template Context

Templates receive a `$context` array (also extracted to variables):

| Variable | Description |
|----------|-------------|
| `$site` | Site info (name, url, timezone) |
| `$theme` | Theme info (name, path, url) |
| `$request` | Current Request object |
| `$route` | RouteMatch object |
| `$page` | Content Item (for single pages) |
| `$query` | Query object (for archives) |
| `$tax` | Taxonomy info (for taxonomy archives) |
| `$ava` | TemplateHelpers instance |

## The `$ava` Helper

Templates have access to `$ava` with these methods:

### Content Rendering

```php
// Render item's Markdown body
<?= $ava->content($page) ?>

// Render Markdown string
<?= $ava->markdown('**bold**') ?>

// Render a partial
<?= $ava->partial('header', ['title' => 'Custom']) ?>

// Expand path aliases
<?= $ava->expand('@uploads:image.jpg') ?>
```

### URLs

```php
// URL for content item
<?= $ava->url('post', 'hello-world') ?>

// URL for taxonomy term
<?= $ava->termUrl('category', 'tutorials') ?>

// Asset URL with cache busting
<?= $ava->asset('/assets/style.css') ?>

// Full URL
<?= $ava->fullUrl('/about') ?>
```

### Queries

```php
// New query
$posts = $ava->query()
    ->type('post')
    ->published()
    ->orderBy('date', 'desc')
    ->perPage(5)
    ->get();

// Recent items shortcut
$recent = $ava->recent('post', 5);

// Get specific item
$about = $ava->get('page', 'about');

// Get taxonomy terms
$categories = $ava->terms('category');
```

### SEO

```php
// Meta tags for item
<?= $ava->metaTags($page) ?>

// Per-item CSS/JS
<?= $ava->itemAssets($page) ?>
```

### Pagination

```php
// Pagination HTML
<?= $ava->pagination($query, $request->path()) ?>
```

### Utilities

```php
// Escape HTML
<?= $ava->e($value) ?>

// Format date
<?= $ava->date($page->date(), 'F j, Y') ?>

// Relative time
<?= $ava->ago($page->date()) ?>

// Truncate to words
<?= $ava->excerpt($text, 55) ?>

// Get config value
<?= $ava->config('site.name') ?>
```

## Example Template

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $ava->metaTags($page) ?>
    <?= $ava->itemAssets($page) ?>
    <link rel="stylesheet" href="<?= $ava->asset('/assets/style.css') ?>">
</head>
<body>
    <header>
        <a href="/"><?= $ava->e($site['name']) ?></a>
    </header>

    <main>
        <article>
            <h1><?= $ava->e($page->title()) ?></h1>
            
            <?php if ($page->date()): ?>
                <time datetime="<?= $page->date()->format('c') ?>">
                    <?= $ava->date($page->date()) ?>
                </time>
            <?php endif; ?>

            <div class="content">
                <?= $ava->content($page) ?>
            </div>
        </article>
    </main>

    <footer>
        &copy; <?= date('Y') ?> <?= $ava->e($site['name']) ?>
    </footer>
</body>
</html>
```

## Template Resolution

Templates are resolved in order:

1. Frontmatter `template` field
2. Content type's configured template
3. `single.php` fallback
4. `index.php` fallback

## Theme Bootstrap

`theme.php` can register hooks and shortcodes:

```php
<?php

use Ava\Plugins\Hooks;
use Ava\Application;

// Add theme shortcode
Application::getInstance()->shortcodes()->register('theme_version', fn() => '1.0.0');

// Modify template context
Hooks::addFilter('render.context', function (array $context) {
    $context['theme_setting'] = 'value';
    return $context;
});
```
