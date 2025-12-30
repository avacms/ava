# Ava CMS — AI Reference

> This document is a concise technical reference designed for AI language models. It helps AI assistants understand Ava's architecture, conventions, and coding patterns so they can provide accurate help when working with the codebase.

## Using This Reference

To give your AI assistant context about Ava, copy this file to your project as one of these:

- **GitHub Copilot**: `.github/copilot-instructions.md`
- **Claude**: `claude.md` or `CLAUDE.md` in project root
- **Cursor**: `.cursorrules` in project root
- **Other tools**: Check your AI tool's documentation for custom instructions

You can also paste the contents into a conversation when asking for help with Ava development.

---

## Project Overview

Ava is a flat-file CMS written in PHP 8.3+. Content lives in Markdown files with YAML frontmatter. There is no database — a binary content index provides fast lookups, and an optional HTML page cache handles high-traffic scenarios.

**Core philosophy:**
- Files are the source of truth (Markdown content, PHP config)
- No build steps — edit a file, refresh, see changes
- Minimal dependencies (League CommonMark, Symfony YAML)
- Admin interface is optional and read-only
- Designed for developers who want full control

---

## Requirements

| Requirement | Details |
|-------------|---------|
| PHP | 8.3 or later |
| Required Extensions | `mbstring`, `json`, `ctype` |
| Optional Extensions | `igbinary` (faster cache), `opcache`, `curl`, `gd` |

---

## Directory Structure

```
app/
  config/
    ava.php              # Main configuration
    content_types.php    # Content type definitions
    taxonomies.php       # Taxonomy definitions
    users.php            # Admin users (auto-generated)
  hooks.php              # Custom hooks
  shortcodes.php         # Custom shortcodes

content/
  pages/*.md             # Page content
  posts/*.md             # Post content
  _taxonomies/*.yml      # Term registries

core/                    # Framework code (don't modify)
  Application.php        # Singleton container
  Content/               # Parser, Indexer, Repository, Query, Item
  Http/                  # Request, Response, PageCache
  Routing/               # Router, RouteMatch
  Rendering/             # Engine, TemplateHelpers
  Shortcodes/            # Shortcode processing
  Plugins/               # Hook system
  Admin/                 # Admin panel

themes/<name>/
  theme.php              # Theme bootstrap
  templates/             # PHP templates
  partials/              # Reusable template parts
  assets/                # CSS, JS, images

plugins/<name>/
  plugin.php             # Plugin entry point
  views/                 # Plugin admin views

storage/
  cache/                 # Generated caches
    content_index.bin    # Content metadata (full index)
    slug_lookup.bin      # Fast single-item lookups
    recent_cache.bin     # Top 200 items per type
    tax_index.bin        # Taxonomy index
    routes.bin           # Route cache
    pages/*.html         # Cached HTML pages
  logs/                  # Log files
```

---

## Request Lifecycle

```
Request → Router → RouteMatch → Renderer → Response
              ↓
         Repository ← Content Index ← Indexer ← Content Files
```

1. Router matches URL against cached routes
2. Repository loads content item from binary index
3. Renderer processes template with content
4. Response sends HTML (optionally cached to page cache)

---

## Caching System

Ava uses a three-tier caching strategy for performance:

### Content Index Cache

Binary serialized cache of all content metadata:
- `storage/cache/content_index.bin` — Full content items (~45MB for 100k posts)
- `storage/cache/slug_lookup.bin` — Fast single-item lookups (~8.7MB for 100k posts)
- `storage/cache/recent_cache.bin` — Top 200 items per type (~51KB)
- `storage/cache/tax_index.bin` — Taxonomy terms
- `storage/cache/routes.bin` — Route mappings
- `storage/cache/fingerprint.json` — Change detection

**Tiered loading:**
- Archive pages 1-20: Uses recent_cache.bin (~3ms, ~2MB memory)
- Single post views: Uses slug_lookup.bin (~130ms, ~82MB memory)  
- Complex queries: Uses content_index.bin (~2.4s, ~323MB memory)

**Rebuild modes** (`content_index.mode`):
- `auto` (default) — Rebuild when fingerprint detects changes
- `never` — Only rebuild via CLI (production)
- `always` — Rebuild every request (debugging)

**Binary format:** Uses igbinary if available (~4-5× faster), falls back to PHP serialize. Files prefixed with `IG:` or `SZ:` marker for auto-detection.

### Page Cache

On-demand HTML file cache in `storage/cache/pages/`:
- Enabled globally via config, per-item via frontmatter
- Cache keys are MD5 hashes of URLs (secure against path traversal)
- Query parameters bypass cache (except UTM tracking params)
- Admin sessions bypass cache
- Only GET requests are cached

**Security:**
- XSS via query strings: Bypassed entirely
- Cache poisoning: Headers not in cache key
- Session leakage: Admin users never see cached pages
- Path traversal: Filenames are MD5 hashed

---

## Routing System

Routes are resolved in this order:

1. Trailing slash redirect (if enabled)
2. `redirect_from` frontmatter redirects (301)
3. System routes (runtime-registered by core/plugins)
4. Exact routes (from content)
5. Prefix routes (e.g., `/blog/*`)
6. Taxonomy routes (e.g., `/tag/php`)
7. 404 handler

**Route caching:** All routes are compiled and cached in `storage/cache/routes.bin`

---

## Content Model

Content files are Markdown with YAML frontmatter:

```yaml
---
id: 01JGMK...        # ULID (auto-generated, immutable)
title: Page Title     # Required
slug: page-title      # Required, URL-safe
status: published     # draft | published | private
date: 2024-12-28      # Publication date
excerpt: Summary      # Short description
cache: true           # Override page cache setting
categories:           # Taxonomy terms
  - tutorials
tags:
  - php
redirect_from:        # Old URLs (301 redirect)
  - /old-path
  - /legacy/old-path
template: custom      # Override default template
author: Jane Doe      # Custom fields supported
---

Markdown content here. **Bold**, *italic*, [links](/url).
```

**Core fields:**
- `id` — Unique ULID identifier (auto-generated)
- `title` — Content title
- `slug` — URL-friendly identifier
- `status` — `draft`, `published`, or `private`
- `date` — Publication date (for dated content types)
- `excerpt` — Short description
- `cache` — Override page cache setting
- `redirect_from` — Array of old URLs to 301 redirect
- `template` — Override default template

**Taxonomy fields:**
- `category` / `categories` — Primary taxonomy (singular or array)
- `tag` / `tags` — Additional taxonomy (singular or array)
- Custom taxonomies defined in config

**Custom fields:** Any YAML key not recognized as core field is stored as custom metadata

---

## Content Types

Defined in `app/config/content_types.php`:

```php
return [
    'post' => [
        'label' => 'Posts',
        'directory' => 'posts',
        'url' => '/blog/{slug}',
        'template' => 'post',
        'archive_template' => 'archive',
        'taxonomies' => ['categories', 'tags'],
        'date_based' => true,
        'sort' => ['date', 'desc'],
    ],
    'page' => [
        'label' => 'Pages',
        'directory' => 'pages',
        'url' => 'hierarchical',
        'template' => 'page',
        'taxonomies' => [],
        'date_based' => false,
        'sort' => ['title', 'asc'],
    ],
];
```

**URL types:**
- `hierarchical` — Nested paths from directory structure (`/about/team`)
- Pattern — Template with tokens (`/blog/{slug}`, `/blog/{yyyy}/{mm}/{slug}`)
- Tokens: `{slug}`, `{yyyy}`, `{mm}`, `{dd}`, `{id}`

---

## Taxonomies

Defined in `app/config/taxonomies.php`:

```php
return [
    'categories' => [
        'label' => 'Categories',
        'singular' => 'Category',
        'hierarchical' => false,
        'registry_path' => 'content/_taxonomies/category.yml',
        'url' => '/category/{slug}',
    ],
];
```

**Term registries** (`content/_taxonomies/*.yml`):

```yaml
---
getting-started:
  label: Getting Started
  description: Introductory guides
tutorials:
  label: Tutorials
  description: Step-by-step guides
```

---

## Core Classes

| Class | Purpose |
|-------|---------|
| `Application` | Singleton container, bootstrap, config access |
| `Content\Parser` | Parse Markdown + YAML frontmatter |
| `Content\Indexer` | Scan files, build binary cache |
| `Content\Repository` | Load content from cache, hydrate items |
| `Content\Query` | Fluent query builder for content |
| `Content\Item` | Content value object |
| `Http\Request` | HTTP request wrapper |
| `Http\Response` | HTTP response wrapper |
| `Http\PageCache` | HTML page cache management |
| `Routing\Router` | Match URLs to routes |
| `Routing\RouteMatch` | Route match result |
| `Rendering\Engine` | Template rendering |
| `Rendering\TemplateHelpers` | Template helper methods (`$ava`) |
| `Shortcodes\Engine` | Shortcode processing |
| `Plugins\Hooks` | Filter/action hook system |

---

## Query API

The Query class provides a fluent interface for retrieving content:

```php
use Ava\Content\Query;

$query = (new Query($app))
    ->type('post')                        // Content type
    ->published()                         // Only published items
    ->whereTax('categories', 'tutorials') // Taxonomy filter
    ->orderBy('date', 'desc')             // Sort by date
    ->perPage(10)                         // Results per page
    ->page(1)                             // Current page
    ->get();                              // Execute

// Access results
$query->items();   // Array of Item objects
$query->total();   // Total matching items
$query->hasMore(); // Has more pages
```

**Query methods:**
- `->type(string)` — Filter by content type
- `->published()` — Only published items
- `->where(string $field, mixed $value)` — Filter by field
- `->whereTax(string $taxonomy, string|array $terms)` — Filter by taxonomy
- `->orderBy(string $field, string $dir = 'asc')` — Sort results
- `->perPage(int)` — Results per page
- `->page(int)` — Current page number
- `->limit(int)` — Limit results
- `->offset(int)` — Skip results
- `->get()` — Execute and return Query object

**Performance:** Query operates on raw arrays from cache until the final `get()` call, then hydrates only the paginated results into Item objects.

---

## Item API

The Item class represents a single content item:

```php
// Core fields
$item->id()         // ULID identifier
$item->title()      // Content title
$item->slug()       // URL slug
$item->status()     // draft | published | private
$item->type()       // Content type
$item->url()        // Full URL

// Dates
$item->date($format = 'Y-m-d')     // Publication date
$item->updated($format = 'Y-m-d')  // Last modified date

// Content
$item->content()    // Rendered HTML
$item->excerpt()    // Short description
$item->raw()        // Raw Markdown

// Taxonomies
$item->tax($taxonomy)              // Array of terms
$item->hasTax($taxonomy, $term)    // Check if has term

// Custom fields
$item->get($key, $default = null)  // Get custom field

// Template
$item->template()   // Template name
```

---

## Template System

Templates are plain PHP files in `themes/<name>/templates/`:

- `page.php` — Single page template
- `post.php` — Single post template
- `archive.php` — Archive/listing template
- `taxonomy.php` — Taxonomy archive template
- `404.php` — 404 error page

**Partials** go in `themes/<name>/partials/`:
- `_header.php`, `_footer.php`, `_sidebar.php`, etc.

**Global variables available in templates:**

| Variable | Type | Description |
|----------|------|-------------|
| `$site` | array | Site config (`name`, `url`, `timezone`) |
| `$page` | Item | Current content (single templates) |
| `$query` | Query | Query object (archive templates) |
| `$tax` | array | Taxonomy info (taxonomy templates) |
| `$request` | Request | HTTP request |
| `$ava` | TemplateHelpers | Helper methods |

---

## Template Helpers ($ava)

The `$ava` object provides helper methods in templates:

**Content:**
```php
$ava->content($page)            // Render item content
$ava->markdown($string)         // Render Markdown string
$ava->partial($name, $vars)     // Include partial
```

**URLs:**
```php
$ava->url($type, $slug)         // URL for content item
$ava->termUrl($tax, $slug)      // URL for taxonomy term
$ava->asset($path)              // Theme asset with cache-busting
```

**Utilities:**
```php
$ava->metaTags($page)           // SEO meta tags HTML
$ava->pagination($query)        // Pagination HTML
$ava->recent($type, $limit)     // Recent items
$ava->e($string)                // HTML escape
$ava->date($date, $format)      // Format date
$ava->config($key, $default)    // Config value
$ava->expand($path)             // Expand path alias
```

**Path aliases:**
- `@media:` → `/media/`
- `@uploads:` → `/media/uploads/`
- `@assets:` → `/assets/`

---

## Shortcodes

Shortcodes are processed **after** Markdown rendering. They allow dynamic content insertion:

**Built-in shortcodes:**
```markdown
[year]                    # Current year
[site_name]               # Site name
[site_url]                # Site URL
```

**Custom shortcodes** in `app/shortcodes.php`:

```php
// Self-closing shortcode
$shortcodes->add('year', fn() => date('Y'));

// With attributes
$shortcodes->add('button', function($args) {
    $url = $args['url'] ?? '#';
    $text = $args['text'] ?? 'Click';
    return "<a href='$url' class='btn'>$text</a>";
});

// Usage: [button url="/about" text="Learn More"]

// Paired shortcode
$shortcodes->add('highlight', function($args, $content) {
    return "<mark>{$content}</mark>";
});

// Usage: [highlight]Important text[/highlight]
```

**Snippet shortcodes:**
```markdown
[snippet name="cta" heading="Subscribe" url="/newsletter"]
```

Loads `snippets/cta.php` with variables `$heading`, `$url`, etc.

**Limitations:**
- Nested shortcodes are not supported in v1
- Shortcodes in frontmatter are not processed

---

## Hook System

WordPress-style filters and actions via `Ava\Plugins\Hooks`:

**Filters** (modify data):
```php
use Ava\Plugins\Hooks;

// Register filter
Hooks::addFilter('hook_name', function($value, ...$args) {
    return $modifiedValue;
}, priority: 10);

// Apply filter
$result = Hooks::apply('hook_name', $initialValue, $arg1, $arg2);
```

**Actions** (side effects):
```php
// Register action
Hooks::addAction('hook_name', function(...$args) {
    // Perform side effect
}, priority: 10);

// Trigger action
Hooks::do('hook_name', $arg1, $arg2);
```

**Available hooks:**

| Hook | Type | Description |
|------|------|-------------|
| `content.before_parse` | Filter | Before parsing Markdown |
| `content.after_parse` | Filter | After parsing Markdown |
| `render.before` | Action | Before rendering template |
| `render.after` | Action | After rendering template |
| `shortcode.{name}` | Filter | Dynamic shortcode registration |
| `admin.register_pages` | Action | Register admin pages |
| `admin.sidebar_items` | Filter | Modify admin sidebar |

**Custom hooks** in `app/hooks.php`:

```php
use Ava\Plugins\Hooks;

Hooks::addFilter('content.after_parse', function($html, $item) {
    // Modify rendered HTML
    return $html;
});
```

---

## CLI Commands

Ava includes a command-line tool at `bin/ava`:

**Content management:**
```bash
php bin/ava status                   # Site overview and stats
php bin/ava lint                     # Validate content files
php bin/ava make post "Post Title"   # Create new content
php bin/ava content:list             # List all content
php bin/ava content:find "search"    # Search content
```

**Cache management:**
```bash
php bin/ava rebuild                  # Rebuild content index
php bin/ava cache:clear              # Clear all caches
php bin/ava pages:stats              # Page cache statistics
php bin/ava pages:clear [pattern]    # Clear page cache
```

**User management:**
```bash
php bin/ava user:add                 # Create admin user
php bin/ava user:password            # Update user password
php bin/ava user:remove              # Remove admin user
php bin/ava user:list                # List all users
```

**Updates:**
```bash
php bin/ava update:check             # Check for updates
php bin/ava update:apply             # Apply available update
```

**Testing:**
```bash
php bin/ava stress:generate post 100  # Generate test content
php bin/ava stress:clean post         # Remove test content
```

---

## Admin Panel

Optional read-only dashboard (disabled by default):

- Enable via `admin.enabled: true` in config
- Access at `/admin` (customizable)
- Bcrypt-hashed passwords in `app/config/users.php`
- Session-based authentication

**Features:**
- Content statistics and recent items
- Cache status and management
- Content validation (lint)
- System diagnostics
- Taxonomy term browser
- Log viewer

**Not an editor:** Admin is a web wrapper around CLI commands. Content editing happens in your preferred text editor.

---

## Coding Conventions

- **PHP 8.3+** with strict types (`declare(strict_types=1)`)
- **PSR-12** code style
- **No frameworks** — vanilla PHP
- **Dependency injection** via Application container
- **Immutable value objects** (Item, RouteMatch)
- **Early returns** over nested conditionals
- **Explicit over implicit** — no magic methods
- **Type hints everywhere** — parameters, returns, properties

**Example:**

```php
declare(strict_types=1);

namespace Ava\Content;

final class Item
{
    public function __construct(
        private readonly array $data,
        private readonly Application $app
    ) {}

    public function title(): string
    {
        return $this->data['title'] ?? '';
    }
}
```

---

## Non-Goals

These are explicitly out of scope:

- Database support
- WYSIWYG / visual editor
- Media upload UI
- File browser in admin
- Content editing in admin (use a text editor)
- Complex build pipelines
- Heavy frameworks or abstractions
- Multi-user collaborative editing
- Built-in version control

---

## Dependencies

```json
{
  "require": {
    "php": "^8.3",
    "league/commonmark": "^2.6",
    "symfony/yaml": "^7.2"
  }
}
```

Optional: `igbinary` extension for 15× faster cache serialization.

That's it. No frameworks, minimal dependencies, maximum control.
