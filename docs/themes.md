# Themes

Ava themes are HTML-first templates with PHP available when you need it. Start with normal HTML, then sprinkle in `<?= ?>` to output data or call helpers. There's no custom templating language, no build step, and no new syntax to learn.

```php
<!-- Output a title -->
<h1><?= $content->title() ?></h1>

<!-- Render the Markdown content as HTML -->
<div class="content">
    <?= $ava->body($content) ?>
</div>

<!-- Link to a stylesheet in your theme's assets folder -->
<link rel="stylesheet" href="<?= $ava->asset('style.css') ?>">

<!-- Loop through recent posts -->
<?php foreach ($ava->recent('post', 5) as $entry): ?>
    <article>
        <h2><a href="<?= $ava->url('post', $entry->slug()) ?>"><?= $entry->title() ?></a></h2>
        <time><?= $ava->date($entry->date()) ?></time>
    </article>
<?php endforeach; ?>
```

You decide how much custom PHP to use: none for simple pages, or more for dynamic layouts. The helpers are there when you want them, but HTML remains the core.

## Why HTML + PHP (and not a custom templating language)?

**What you gain**
- **Familiar building blocks** — If you know HTML, you can start immediately. Output is just `<?= $variable ?>` and the `$ava` helper.
- **No build pipeline** — Save the file, refresh the browser. No compilers, watchers, or caches to clear.
- **Full power available** — Need a loop, conditional, or a custom helper? Use plain PHP—no special template language or custom syntax.
- **Easy to debug** — Standard PHP errors, standard stack traces. Nothing is hidden behind a template engine.

**What you trade off**
- Syntax is a bit noisier than `{{ }}`-style engines
- You should know (or be willing to learn) a few PHP basics: `<?= ?>`, `if`, `foreach`
- Very complex view logic should live in helpers or `theme.php` to keep templates readable

**Who this suits**
- Designers comfortable with HTML/CSS who want minimal new concepts
- Developers who want flexibility without adopting a custom template language or dealing with additional tooling
- Beginners who want to learn web fundamentals instead of framework-specific magic
- Teams that prefer transparency and portability 

<div class="beginner-box">

### What is `<?= ?>`?

`<?= ?>` is a short way to output a value in PHP. It’s exactly the same as writing `<?php echo ?>`, just shorter and easier to read.

It’s called a **short echo tag** and is **always enabled in modern PHP**.

⚠️ It does **not** escape output automatically. Use `htmlspecialchars()` or `$ava->e()` when outputting user-provided data to prevent XSS attacks.

**Don’t confuse it with `<? ?>`:**  
That older shorthand (without the `=`) is discouraged and disabled by default.  As long as you include the `=`, you’re using the correct syntax.

[Learn more about PHP tags →](https://www.php.net/manual/en/language.basic-syntax.phptags.php)

</div>

## Theme Structure

A theme is just a folder in `themes/`. Here's a typical layout:

```
themes/
└── default/
    ├── templates/        # Your page layouts
    │   ├── index.php     # The default layout
    │   ├── page.php      # For standard pages
    │   ├── post.php      # For blog posts
    │   └── 404.php       # "Page not found" error
    ├── partials/         # Reusable template fragments
    │   ├── header.php
    │   └── footer.php
    ├── assets/           # CSS, JS, images
    │   ├── style.css
    │   └── script.js
    └── theme.php         # Optional setup code
```

## Using Assets

Ava makes it easy to include your CSS and JS files. It even handles cache-busting automatically, so your visitors always see the latest version based on the files modified time.

```php
<!-- Just ask $ava for the asset URL -->
<link rel="stylesheet" href="<?= $ava->asset('style.css') ?>">
<script src="<?= $ava->asset('script.js') ?>"></script>
```

?> This outputs a URL like `/theme/style.css?v=123456`, ensuring instant updates when you change the file without worrying about browser or CDN caching.

## Template Basics

In your template files (like `page.php`), you have access to your content and helper variables.

```php
<!-- templates/post.php -->
<?= $ava->partial('header', ['title' => $content->title()]) ?>

<article>
    <h1><?= $content->title() ?></h1>
    
    <div class="content">
        <?= $ava->body($content) ?>
    </div>
    
    <?php if ($content->date()): ?>
        <time><?= $ava->date($content->date()) ?></time>
    <?php endif; ?>
</article>

<?= $ava->partial('footer') ?>
```

It's just HTML with simple tags to show your data.

---

## Quick Reference

This section gives you a complete overview of what's available in templates.

### Template Variables

These variables are available in your templates:

| Variable | Type | Description |
|----------|------|-------------|
| `$content` | `Item` | The current content item (single post/page templates only) |
| `$query` | `Query` | Query object for archive/listing templates |
| `$tax` | `array` | Taxonomy info: `name`, `term`, `items` (taxonomy templates only) |
| `$site` | `array` | Site config: `name`, `url`, `timezone` |
| `$theme` | `array` | Theme info: `name`, `path`, `url` |
| `$request` | `Request` | Current HTTP request (path, query params, etc.) |
| `$route` | `RouteMatch` | Matched route information |
| `$ava` | `TemplateHelpers` | Helper methods for rendering, URLs, queries, and more |

?> Not all variables are present in every template. For example, `$content` only exists on single content pages, while `$query` is for archives.

### The `$content` Object — All Properties

When displaying a single piece of content (a page, post, etc.), use `$content` to access its data:

| Method | Returns | Description |
|--------|---------|-------------|
| **Identity** | | |
| `id()` | `string\|null` | Unique identifier (ULID) |
| `title()` | `string` | Title from frontmatter |
| `slug()` | `string` | URL-friendly identifier |
| `type()` | `string` | Content type (`page`, `post`, etc.) |
| `status()` | `string` | `draft`, `published`, or `unlisted` |
| **Status Checks** | | |
| `isPublished()` | `bool` | Is status "published"? |
| `isDraft()` | `bool` | Is status "draft"? |
| `isUnlisted()` | `bool` | Is status "unlisted"? |
| **Dates** | | |
| `date()` | `DateTimeImmutable\|null` | Publication date (and time if specified) |
| `updated()` | `DateTimeImmutable\|null` | Last updated (falls back to `date()`) |
| **Content** | | |
| `rawContent()` | `string` | Raw Markdown body (before rendering) |
| `excerpt()` | `string\|null` | Excerpt from frontmatter |
| **Taxonomies** | | |
| `terms()` | `array` | All taxonomy terms |
| `terms('category')` | `array` | Terms for a specific taxonomy |
| **SEO** | | |
| `metaTitle()` | `string\|null` | Custom meta title |
| `metaDescription()` | `string\|null` | Meta description |
| `noindex()` | `bool` | Should search engines skip this? |
| `canonical()` | `string\|null` | Canonical URL |
| `ogImage()` | `string\|null` | Open Graph image URL |
| **Custom Fields** | | |
| `get('field')` | `mixed` | Get any frontmatter field |
| `get('field', 'default')` | `mixed` | Get field with default value |
| `has('field')` | `bool` | Check if field exists |
| **Assets & Structure** | | |
| `css()` | `array` | Per-item CSS files |
| `js()` | `array` | Per-item JS files |
| `template()` | `string\|null` | Custom template name |
| `parent()` | `string\|null` | Parent page slug |
| `order()` | `int` | Manual sort order |
| `redirectFrom()` | `array` | Old URLs that redirect here |
| `filePath()` | `string` | Path to the Markdown file |

### The `$ava` Helper — All Methods

The `$ava` object provides helper methods for common tasks:

| Method | Description |
|--------|-------------|
| **Rendering** | |
| `body($content)` | Render content's Markdown body to HTML |
| `markdown($string)` | Render a Markdown string to HTML |
| `partial($name, $data)` | Render a partial template |
| `expand($path)` | Expand path aliases (e.g., `@media:`) |
| **URLs** | |
| `url($type, $slug)` | URL for a content item |
| `termUrl($taxonomy, $term)` | URL for a taxonomy term page |
| `asset($path)` | Theme asset URL with cache-busting |
| `fullUrl($path)` | Full absolute URL |
| **Queries** | |
| `query()` | Start a new content query |
| `recent($type, $count)` | Get recent items (shortcut) |
| `get($type, $slug)` | Get a specific item by slug |
| `terms($taxonomy)` | Get all terms for a taxonomy |
| **Dates** | |
| `date($date, $format)` | Format a date (uses site timezone) |
| `ago($date)` | Relative time ("2 days ago") |
| **HTML** | |
| `metaTags($content)` | Output SEO meta tags |
| `itemAssets($content)` | Output per-content CSS/JS |
| `pagination($query, $path)` | Render pagination links |
| **Utilities** | |
| `e($value)` | Escape HTML (for user input) |
| `excerpt($text, $words)` | Truncate text to word count |
| `config($key)` | Get a config value |

---

## Detailed Guide

### Rendering Content with `$ava->body()`

To display the main content of a page or post, use `$ava->body($content)`:

```php
<div class="content">
    <?= $ava->body($content) ?>
</div>
```

<div class="beginner-box">

**Why `$ava->body($content)` instead of `$content->body()`?**

When you write content in Markdown, Ava needs to *process* it before displaying:

1. Convert Markdown → HTML
2. Process shortcodes like `[button]`
3. Expand path aliases like `@media:`
4. Apply any plugin filters

This processing requires the rendering engine, which lives in the `$ava` helper. The `$content` object is a simple data container—it holds your content but doesn't know how to render it.

Think of it like this: `$content` is the *ingredients*, and `$ava` is the *kitchen* that turns them into a finished dish.

</div>

### Working with Dates and Times

Dates in frontmatter can include times:

```yaml
---
title: My Post
date: 2025-12-31           # Date only (midnight assumed)
date: 2025-12-31 14:30     # Date with time
date: 2025-12-31T14:30:00  # ISO 8601 format
---
```

Display dates in templates using `$ava->date()`:

```php
<!-- Uses site's default format from config -->
<?= $ava->date($content->date()) ?>

<!-- Or specify a format -->
<?= $ava->date($content->date(), 'F j, Y') ?>        // December 31, 2025
<?= $ava->date($content->date(), 'Y-m-d') ?>         // 2025-12-31
<?= $ava->date($content->date(), 'M j, g:ia') ?>     // Dec 31, 2:30pm
<?= $ava->date($content->date(), 'l, F jS') ?>       // Wednesday, December 31st
<?= $ava->date($content->date(), 'c') ?>             // ISO 8601 (for datetime attributes)
```

The date is automatically converted to your site's timezone (set in `app/config/ava.php`).

You can set a default date format in your config:

```php
'site' => [
    'name' => 'My Site',
    'timezone' => 'America/New_York',
    'date_format' => 'F j, Y',  // Used when no format specified
],
```

For relative times, use `$ava->ago()`:

```php
<?= $ava->ago($content->date()) ?>  // "2 hours ago", "3 days ago"
```

?> Date formats use [PHP's date() format codes](https://www.php.net/manual/en/datetime.format.php). Common codes: `Y` (year), `m` (month), `d` (day), `F` (full month name), `M` (short month), `j` (day without zero), `g` (12-hour), `H` (24-hour), `i` (minutes), `a` (am/pm).

### Escaping HTML with `$ava->e()`

The `$ava->e()` method escapes special HTML characters to prevent security issues:

```php
<?= $ava->e($value) ?>
```

<div class="beginner-box">

**When do you need `$ava->e()`?**

**You need it for user-submitted data:**
- Comment text from visitors
- Search queries from URL parameters
- Form input being displayed back

**You don't need it for your own content:**
- Titles, excerpts, custom fields you wrote yourself
- Data from your Markdown files
- Site configuration values

Since you control all content in Ava (there's no public user input by default), you typically don't need `$ava->e()` in most templates. It's there when you add features that accept user input, like a search form:

```php
<!-- User input from URL - escape it! -->
<p>Search results for: <?= $ava->e($request->query('q')) ?></p>
```

</div>

### Accessing Custom Fields

Any field you add to frontmatter is accessible via `get()`:

```yaml
---
title: Team Member
role: Designer
featured: true
website: https://example.com
---
```

```php
<p>Role: <?= $content->get('role', 'Unknown') ?></p>

<?php if ($content->get('featured')): ?>
    <span class="badge">Featured</span>
<?php endif; ?>

<?php if ($content->has('website')): ?>
    <a href="<?= $content->get('website') ?>">Visit Website</a>
<?php endif; ?>
```

### Displaying Taxonomies

Show categories, tags, or any taxonomy terms:

```php
<?php foreach ($content->terms('category') as $term): ?>
    <a href="<?= $ava->termUrl('category', $term) ?>"><?= $term ?></a>
<?php endforeach; ?>
```

Get all terms for a taxonomy across your site:

```php
<?php foreach ($ava->terms('category') as $term): ?>
    <a href="<?= $ava->termUrl('category', $term) ?>"><?= $term ?></a>
<?php endforeach; ?>
```

### Querying Content

The `$ava->query()` method returns a fluent query builder:

```php
// Get the 5 most recent published posts
$posts = $ava->query()
    ->type('post')
    ->published()
    ->orderBy('date', 'desc')
    ->perPage(5)
    ->get();

foreach ($posts as $entry) {
    echo $entry->title();
}
```

#### Query Methods

| Method | Description | Example |
|--------|-------------|---------|
| `type($type)` | Filter by content type | `->type('post')` |
| `status($status)` | Filter by status | `->status('published')` |
| `published()` | Shortcut for published status | `->published()` |
| `whereTax($tax, $term)` | Filter by taxonomy term | `->whereTax('category', 'tutorials')` |
| `where($field, $value)` | Filter by field value | `->where('featured', true)` |
| `orderBy($field, $dir)` | Sort results | `->orderBy('date', 'desc')` |
| `perPage($count)` | Items per page (max 100) | `->perPage(10)` |
| `page($num)` | Current page number | `->page(2)` |
| `search($query)` | Full-text search | `->search('php tutorial')` |

#### Result Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `get()` | `Item[]` | Execute and get items |
| `first()` | `Item\|null` | Get first match |
| `count()` | `int` | Total count (before pagination) |
| `totalPages()` | `int` | Number of pages |
| `hasMore()` | `bool` | Are there more pages? |
| `isEmpty()` | `bool` | No results? |

#### Shortcuts

```php
// Recent items
$posts = $ava->recent('post', 5);

// Get specific item
$about = $ava->get('page', 'about');
```

### Using Partials

Partials are reusable template fragments in `themes/{theme}/partials/`:

```php
<!-- Render a partial -->
<?= $ava->partial('header') ?>

<!-- Pass data to it -->
<?= $ava->partial('header', ['title' => $content->title()]) ?>
```

Inside partials, passed data becomes variables:

```php
<!-- partials/header.php -->
<header>
    <h1><?= $title ?? $site['name'] ?></h1>
</header>
```

Partials automatically inherit `$site`, `$theme`, `$request`, and `$ava`.

### URLs

```php
// Content URL
<?= $ava->url('post', 'hello-world') ?>  // /blog/hello-world

// Taxonomy term URL
<?= $ava->termUrl('category', 'tutorials') ?>  // /category/tutorials

// Theme asset (with cache-busting)
<?= $ava->asset('style.css') ?>  // /theme/style.css?v=123456

// Public asset (leading slash)
<?= $ava->asset('/media/photo.jpg') ?>  // /media/photo.jpg

// Full absolute URL
<?= $ava->fullUrl('/about') ?>  // https://example.com/about
```

### SEO and Meta Tags

Output all SEO meta tags for a content item:

```php
<head>
    <?= $ava->metaTags($content) ?>
</head>
```

This outputs meta description, Open Graph tags, canonical URL, and noindex if set.

For per-content CSS/JS files:

```php
<?= $ava->itemAssets($content) ?>
```

---

## Template Resolution

When a content item is requested, Ava looks for a template in this order:

1. **Frontmatter `template` field** — If the item specifies `template: landing`, use `templates/landing.php`
2. **Content type's template** — From `content_types.php`, e.g., posts use `post.php`
3. **`single.php` fallback** — A generic single-item template
4. **`index.php` fallback** — The ultimate default

For archives and taxonomy pages:
- `archive.php` for content type listings
- `taxonomy.php` for taxonomy term pages
- Falls back to `index.php` if not found

---

## Complete Examples

### Header Partial

```php
<!-- partials/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? $site['name'] ?></title>
    <?php if (isset($content)): ?>
        <?= $ava->metaTags($content) ?>
        <?= $ava->itemAssets($content) ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $ava->asset('style.css') ?>">
</head>
<body>
    <header class="site-header">
        <a href="/" class="logo"><?= $site['name'] ?></a>
        <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
            <a href="/blog">Blog</a>
        </nav>
    </header>
    <main>
```

### Footer Partial

```php
<!-- partials/footer.php -->
    </main>
    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= $site['name'] ?></p>
    </footer>
    <script src="<?= $ava->asset('script.js') ?>"></script>
</body>
</html>
```

### Page Template

```php
<!-- templates/page.php -->
<?= $ava->partial('header', ['title' => $content->title(), 'content' => $content]) ?>

<article class="page">
    <h1><?= $content->title() ?></h1>
    
    <div class="content">
        <?= $ava->body($content) ?>
    </div>
</article>

<?= $ava->partial('footer') ?>
```

### Post Template

```php
<!-- templates/post.php -->
<?= $ava->partial('header', ['title' => $content->title(), 'content' => $content]) ?>

<article class="post">
    <header class="post-header">
        <h1><?= $content->title() ?></h1>
        
        <?php if ($content->date()): ?>
            <time datetime="<?= $content->date()->format('c') ?>">
                <?= $ava->date($content->date()) ?>
            </time>
        <?php endif; ?>
        
        <?php if ($categories = $content->terms('category')): ?>
            <div class="categories">
                <?php foreach ($categories as $term): ?>
                    <a href="<?= $ava->termUrl('category', $term) ?>"><?= $term ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>
    
    <div class="content">
        <?= $ava->body($content) ?>
    </div>
</article>

<?= $ava->partial('footer') ?>
```

### Archive Template

```php
<!-- templates/archive.php -->
<?= $ava->partial('header', ['title' => 'Blog']) ?>

<h1>Blog</h1>

<?php foreach ($query->get() as $entry): ?>
    <article class="post-summary">
        <h2><a href="<?= $ava->url('post', $entry->slug()) ?>"><?= $entry->title() ?></a></h2>
        <time><?= $ava->date($entry->date()) ?></time>
        <?php if ($entry->excerpt()): ?>
            <p><?= $entry->excerpt() ?></p>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<?= $ava->pagination($query, $request->path()) ?>

<?= $ava->partial('footer') ?>
```

---

## Theme Bootstrap

`theme.php` runs when your theme loads. It should return a function that receives the application instance. Use it for hooks, shortcodes, and custom routes:

```php
<?php
// themes/yourtheme/theme.php

use Ava\Application;
use Ava\Plugins\Hooks;

return function (Application $app): void {
    // Register shortcodes
    $app->shortcodes()->register('theme_version', fn() => '1.0.0');

    // Add data to all templates
    Hooks::addFilter('render.context', function (array $context) {
        $context['social_links'] = [
            'twitter' => 'https://twitter.com/yoursite',
            'github' => 'https://github.com/yoursite',
        ];
        return $context;
    });

    // Custom route
    $app->router()->addRoute('/search', function ($request) use ($app) {
        // Handle search...
    });
};
```

### Organising Larger Themes

If your `theme.php` grows unwieldy, split it into multiple files. Pass `$app` to each include:

```php
<?php
// themes/yourtheme/theme.php

return function (\Ava\Application $app): void {
    (require __DIR__ . '/inc/shortcodes.php')($app);
    (require __DIR__ . '/inc/hooks.php')($app);
    (require __DIR__ . '/inc/routes.php')($app);
};
```

Each included file follows the same pattern:

```php
<?php
// themes/yourtheme/inc/shortcodes.php

return function (\Ava\Application $app): void {
    $app->shortcodes()->register('button', function (array $attrs, ?string $content) {
        // ...
    });
};
```

This keeps your theme organised while maintaining portability—everything travels with your theme folder.
