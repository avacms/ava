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
- Admin interface is optional and writes to files
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

content/
  pages/*.md             # Page content
  posts/*.md             # Post content
  _taxonomies/*.yml      # Term registries

core/                    # Framework code (don't modify)
  Application.php        # Singleton container
  Content/               # Parser, Indexer, Repository, Query, Item
  Http/                  # Request, Response, WebpageCache
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
4. Response sends HTML (optionally cached to webpage cache)

---

## Caching System

Ava uses a three-tier caching strategy for performance:

### Content Index Cache

Binary serialized cache of all content metadata:
- `storage/cache/content_index.bin` — Full content items (array backend)
- `storage/cache/content_index.sqlite` — SQLite database (sqlite backend)
- `storage/cache/slug_lookup.bin` — Fast single-item lookups (~8.7MB for 100k posts)
- `storage/cache/recent_cache.bin` — Top 200 items per type (~51KB)
- `storage/cache/tax_index.bin` — Taxonomy terms
- `storage/cache/routes.bin` — Route mappings
- `storage/cache/fingerprint.json` — Change detection

**Index backends** (`content_index.backend`):
- `array` (default) — Binary serialized arrays, works great for most sites
- `sqlite` — SQLite database (opt-in for 10k+ items, constant memory)

**Tiered loading (array backend):**
- Archive pages 1-20: Uses recent_cache.bin (~3ms, ~2MB memory)
- Single post views: Uses slug_lookup.bin (~130ms, ~82MB memory)  
- Complex queries: Uses content_index.bin (~2.4s, ~323MB memory)

**SQLite backend advantages:**
- Constant memory usage regardless of content size
- 10-50× faster for counts and lookups at 10k+ items
- No memory limits at 100k+ items

**Rebuild modes** (`content_index.mode`):
- `auto` (default) — Rebuild when fingerprint detects changes
- `never` — Only rebuild via CLI (production)
- `always` — Rebuild every request (debugging)

**Binary format (array backend):** Uses igbinary if available (~4-5× faster), falls back to PHP serialize. Files prefixed with `IG:` or `SZ:` marker for auto-detection.

### Webpage Cache

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

## Debug Mode

Debug configuration in `app/config/ava.php`:

```php
'debug' => [
    'enabled' => false,        // Master switch
    'display_errors' => false, // Show in browser (dev only!)
    'log_errors' => true,      // Write to storage/logs/error.log
    'level' => 'errors',       // 'all', 'errors', 'none'
],
```

**Error levels:**
- `all` — All errors, warnings, notices, deprecations
- `errors` — Fatal errors and exceptions only
- `none` — Suppress all error reporting

**Log files:**
- `storage/logs/error.log` — PHP errors and exceptions
- `storage/logs/admin.log` — Admin login attempts and actions
- `storage/logs/indexer.log` — Content indexing errors

**Log rotation** (automatic via `logs` config):
- `max_size` — Rotate when log exceeds this size (default: 10MB)
- `max_files` — Keep N rotated copies (default: 3)
- CLI: `logs:stats`, `logs:tail`, `logs:clear`

**Debug features when enabled:**
- Custom error/exception handlers with enhanced logging
- Request timing and memory usage tracking
- Error log viewer in admin System page

---

## Routing System

Routes are resolved in this order:

1. Hook interception (`router.before_match` filter)
2. Trailing slash redirect (canonical URL enforcement)
3. `redirect_from` frontmatter redirects (301)
4. System routes (runtime-registered via `addRoute()`)
5. Exact routes (from content cache)
6. Preview mode (draft content with valid token)
7. Prefix routes (runtime-registered via `addPrefixRoute()`)
8. Taxonomy routes (e.g., `/category/php`)
9. 404 handler

**Route caching:** All routes are compiled and cached in `storage/cache/routes.bin`

---

## Content Model

Content files are Markdown with YAML frontmatter:

```yaml
---
title: Page Title     # Required
slug: page-title      # Required, URL-safe
status: published     # draft | published | unlisted
date: 2024-12-28      # Publication date
id: 01JGMK...        # ULID (optional, for stable references)
excerpt: Summary      # Short description
cache: true           # Override webpage cache setting
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
- `title` — Content title (required)
- `slug` — URL-friendly identifier (required)
- `status` — `draft`, `published`, or `unlisted`
- `date` — Publication date (for dated content types)
- `id` — Optional ULID for stable references across slug changes
- `excerpt` — Short description
- `cache` — Override webpage cache setting
- `redirect_from` — Array of old URLs to 301 redirect
- `template` — Override default template

**Taxonomy fields:**
- `category` / `categories` — Primary taxonomy (singular or array)
- `tag` / `tags` — Additional taxonomy (singular or array)
- Custom taxonomies defined in config

**Custom fields:** Any YAML key not recognised as core field is stored as custom metadata

---

## Content Types

Defined in `app/config/content_types.php`:

```php
return [
    'post' => [
        'label' => 'Posts',
        'content_dir' => 'posts',
        'url' => [
            'type' => 'pattern',
            'pattern' => '/blog/{slug}',
            'archive' => '/blog',
        ],
        'templates' => [
            'single' => 'post.php',
            'archive' => 'archive.php',
        ],
        'taxonomies' => ['category', 'tag'],
        'sorting' => 'date_desc',
    ],
    'page' => [
        'label' => 'Pages',
        'content_dir' => 'pages',
        'url' => [
            'type' => 'hierarchical',
            'base' => '/',
        ],
        'templates' => [
            'single' => 'page.php',
        ],
        'taxonomies' => [],
        'sorting' => 'manual',
    ],
];
```

**URL types:**
- `hierarchical` — Nested paths from directory structure (`/about/team`)
- `pattern` — Template with tokens (`/blog/{slug}`, `/blog/{yyyy}/{mm}/{slug}`)
- Tokens: `{slug}`, `{yyyy}`, `{mm}`, `{dd}`, `{id}`

**Sorting options:** `date_desc`, `date_asc`, `title_asc`, `title_desc`, `manual`

---

## Taxonomies

Defined in `app/config/taxonomies.php`:

```php
return [
    'category' => [
        'label' => 'Categories',
        'hierarchical' => true,
        'public' => true,
        'rewrite' => [
            'base' => '/category',       // /category/tutorials
            'separator' => '/',          // /category/tutorials/php
        ],
        'behaviour' => [
            'allow_unknown_terms' => true,   // Auto-create terms from content
            'hierarchy_rollup' => true,      // Include child terms when filtering parent
        ],
    ],
    'tag' => [
        'label' => 'Tags',
        'hierarchical' => false,
        'public' => true,
        'rewrite' => [
            'base' => '/tag',            // /tag/php
        ],
        'behaviour' => [
            'allow_unknown_terms' => true,
        ],
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
| `Http\WebpageCache` | HTML webpage cache management |
| `Routing\Router` | Match URLs to routes |
| `Routing\RouteMatch` | Route match result |
| `Rendering\Engine` | Template rendering |
| `Rendering\TemplateHelpers` | Template helper methods (`$ava`) |
| `Shortcodes\Engine` | Shortcode processing |
| `Plugins\Hooks` | Filter/action hook system |
| `Testing\TestRunner` | Lightweight test runner |
| `Testing\TestCase` | Base class for test cases |

---

## Query API

The Query class provides a fluent interface for retrieving content:

```php
// Via Application (recommended in plugins/routes)
$query = $app->query()
    ->type('post')                        // Content type
    ->published()                         // Only published items
    ->whereTax('category', 'tutorials')   // Taxonomy filter
    ->orderBy('date', 'desc')             // Sort by date
    ->perPage(10)                         // Results per page
    ->page(1);                            // Current page

// Execute and get results
$items = $query->get();       // Array of Item objects
$count = $query->count();     // Total matching items (before pagination)
$hasMore = $query->hasMore(); // Has more pages?
```

**Query methods:**
- `->type(string)` — Filter by content type (auto-loads search config)
- `->published()` — Only published items
- `->status(string)` — Filter by status
- `->where(string $field, mixed $value)` — Filter by field
- `->whereTax(string $taxonomy, string|array $terms)` — Filter by taxonomy
- `->search(string $query)` — Full-text search with relevance scoring
- `->searchWeights(array $weights)` — Override search weights (see below)
- `->orderBy(string $field, string $dir = 'asc')` — Sort results
- `->perPage(int)` — Results per page (max 100)
- `->page(int)` — Current page number
- `->get()` — Execute and return array of Items
- `->first()` — Get first matching item or null
- `->count()` — Total count before pagination
- `->totalPages()` — Number of pages
- `->hasMore()` — More pages available?
- `->hasPrevious()` — Previous pages available?
- `->isEmpty()` — No results?
- `->pagination()` — Full pagination info array

**Search Weights:**
Content types can define search scoring in `content_types.php`:
```php
'search' => [
    'fields' => ['title', 'excerpt', 'body'],
    'weights' => [
        'title_phrase' => 80,    // Exact phrase in title
        'title_token' => 10,     // Per-word in title (max 30)
        'excerpt_phrase' => 30,  // Exact phrase in excerpt
        'body_phrase' => 20,     // Exact phrase in body
        'featured' => 15,        // Featured item boost
    ],
],
```

Override per-query: `->searchWeights(['title_phrase' => 100, 'body_phrase' => 50])`

**Performance:** Query operates on raw arrays from cache until the final `get()` call, then hydrates only the paginated results into Item objects.

---

## Item API

The Item class represents a single content item:

```php
// Core fields
$item->id()           // ULID identifier (or null)
$item->title()        // Content title
$item->slug()         // URL slug
$item->status()       // draft | published | unlisted
$item->type()         // Content type
$item->filePath()     // Path to source file

// Status helpers
$item->isPublished()  // Is status "published"?
$item->isDraft()      // Is status "draft"?
$item->isUnlisted()   // Is status "unlisted"?

// Dates (returns DateTimeImmutable or null)
$item->date()         // Publication date
$item->updated()      // Last modified (falls back to date)

// Content
$item->rawContent()   // Raw Markdown body
$item->html()         // Rendered HTML (if set via withHtml)
$item->withHtml($html) // Return new Item with HTML set (immutable)
$item->excerpt()      // Short description

// Taxonomies
$item->terms('category')  // Array of terms for taxonomy
$item->terms()            // All terms (if using 'tax' format)

// SEO
$item->metaTitle()        // Custom meta title
$item->metaDescription()  // Meta description
$item->noindex()          // Should be noindexed?
$item->canonical()        // Canonical URL
$item->ogImage()          // Open Graph image

// Assets
$item->css()          // Per-item CSS files array
$item->js()           // Per-item JS files array

// Hierarchy
$item->parent()       // Parent slug (for pages)
$item->order()        // Sort order

// Custom fields
$item->get($key, $default = null)  // Get any frontmatter field
$item->has($key)                   // Check if field exists
$item->frontmatter()               // All frontmatter as array

// Template
$item->template()     // Custom template name (or null)
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
| `$content` | Item | Current content (single templates) |
| `$query` | Query | Query object (archive templates) |
| `$tax` | array | Taxonomy info (taxonomy templates) |
| `$request` | Request | HTTP request |
| `$ava` | TemplateHelpers | Helper methods |

---

## Template Helpers ($ava)

The `$ava` object provides helper methods in templates:

**Content:**
```php
$ava->body($content)            // Render content body
$ava->markdown($string)         // Render Markdown string
$ava->partial($name, $vars)     // Include partial
```

**URLs:**
```php
$ava->url($type, $slug)         // URL for content item
$ava->termUrl($tax, $slug)      // URL for taxonomy term
$ava->asset($path)              // Theme asset with cache-busting
$ava->fullUrl($path)            // Full absolute URL
$ava->baseUrl()                 // Site base URL
```

**Utilities:**
```php
$ava->metaTags($item)           // SEO meta tags HTML
$ava->itemAssets($item)         // Per-item CSS/JS tags
$ava->pagination($query)        // Pagination HTML
$ava->recent($type, $limit)     // Recent items
$ava->e($string)                // HTML escape
$ava->date($date, $format)      // Format date
$ava->ago($date)                // Relative time ("2 days ago")
$ava->config($key, $default)    // Config value
$ava->expand($path)             // Expand path alias
```

**Path aliases:**
- `@media:` → `/media/` (for images, downloads, user uploads)

---

## Shortcodes

Shortcodes are processed **after** Markdown rendering. They allow dynamic content insertion:

**Built-in shortcodes:**
```markdown
[year]                    # Current year
[site_name]               # Site name
[site_url]                # Site URL
```

**Custom shortcodes** in `theme.php` (receives `$app`):

```php
use Ava\Application;

return function (Application $app): void {
    $shortcodes = $app->shortcodes();

    // Self-closing shortcode
    $shortcodes->register('year', fn() => date('Y'));

    // Paired shortcode
    $shortcodes->register('highlight', fn($attrs, $content) => "<mark>{$content}</mark>");

    // Usage: [highlight]Important text[/highlight]
};
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
Hooks::doAction('hook_name', $arg1, $arg2);
```

**Available hooks:**

| Hook | Type | Description |
|------|------|-------------|
| `router.before_match` | Filter | Before route matching (return RouteMatch to override) |
| `content.loaded` | Filter | Modify content item after loading from repository |
| `render.context` | Filter | Modify template context before rendering |
| `render.output` | Filter | Modify final HTML output after template rendering |
| `markdown.configure` | Action | Configure CommonMark environment |
| `admin.register_pages` | Filter | Register custom admin pages |
| `admin.sidebar_items` | Filter | Add items to admin sidebar |

**Custom hooks** in `theme.php` or a plugin:

```php
use Ava\Plugins\Hooks;

// Add variables to all templates
Hooks::addFilter('render.context', function($context) {
    $context['analytics_id'] = 'UA-12345';
    return $context;
});

// Add custom Markdown extensions (GFM is enabled by default)
Hooks::addAction('markdown.configure', function($environment) {
    $environment->addExtension(new \League\CommonMark\Extension\Footnote\FootnoteExtension());
});
```

---

## CLI Commands

Ava includes a command-line tool at `./ava` (or `php bin/ava`):

**Site management:**
```bash
./ava status                   # Site overview and stats
./ava rebuild                  # Rebuild content index
./ava lint                     # Validate content files
./ava benchmark                # Test content index performance
./ava benchmark --compare      # Compare all backends
```

**Content creation:**
```bash
./ava make post "Post Title"   # Create new content
./ava make page "Page Title"   # Create page
./ava prefix add post          # Add date prefixes to filenames
./ava prefix remove post       # Remove date prefixes
```

**Webpage cache:**
```bash
./ava cache:stats              # Webpage cache statistics (alias: cache)
./ava cache:clear              # Clear all cached pages
./ava cache:clear /blog/*      # Clear matching pattern
```

**Logs:**
```bash
./ava logs:stats               # Log file statistics (alias: logs)
./ava logs:tail                # Tail indexer.log (default)
./ava logs:tail admin -n 50    # Tail admin.log, 50 lines
./ava logs:clear               # Clear all log files
```

**User management:**
```bash
./ava user:add email pass      # Create admin user (alias: user:add)
./ava user:password email pass # Update password
./ava user:remove email        # Remove admin user
./ava user:list                # List all users (alias: user)
```

**Updates:**
```bash
./ava update:check             # Check for updates (alias: update)
./ava update:check --force     # Force fresh check (bypass cache)
./ava update:apply             # Download and apply update
./ava update:apply -y          # Skip confirmation prompts
```

**Testing:**
```bash
./ava test                     # Run the test suite
./ava test Str                 # Filter by class name
./ava test -q                  # Quiet mode (summary only)
./ava stress:generate post 100 # Generate test content
./ava stress:clean post        # Remove test content
```

---

## Test Suite

Ava includes a lightweight, zero-dependency test framework:

**Running tests:**
```bash
./ava test                  # Run all tests
./ava test Str              # Filter by class name
./ava test -q               # Quiet output
```

**Test structure:**
```
tests/
  Admin/
    DebugTest.php          # Debug configuration and logging
  Config/
    ConfigTest.php         # Configuration access patterns
  Content/
    ItemTest.php           # Content item value object
    ParserTest.php         # Markdown/YAML parser
  Core/
    UpdaterTest.php        # Update system
  Http/
    HttpsEnforcementTest.php  # HTTPS/localhost detection
    RequestTest.php        # HTTP request
    ResponseTest.php       # HTTP response
  Plugins/
    HooksTest.php          # Hook system
  Rendering/
    MarkdownTest.php       # Markdown rendering
  Routing/
    RouteMatchTest.php     # Route match value object
  Shortcodes/
    EngineTest.php         # Shortcode engine
  Support/
    ArrTest.php            # Array utilities
    PathTest.php           # Path utilities
    StrTest.php            # String utilities
    UlidTest.php           # ULID generator
```

**Writing tests:**
```php
namespace Ava\Tests\MyFeature;

use Ava\Testing\TestCase;

final class MyTest extends TestCase
{
    public function setUp(): void
    {
        // Runs before each test
    }

    public function testSomething(): void
    {
        $this->assertEquals('expected', 'actual');
        $this->assertTrue(condition);
        $this->assertThrows(Exception::class, fn() => throw new Exception());
    }
}
```

**Available assertions:**
- `assertTrue($value)`, `assertFalse($value)`
- `assertEquals($expected, $actual)`, `assertNotEquals()`
- `assertSame($expected, $actual)`, `assertNotSame()`
- `assertNull($value)`, `assertNotNull($value)`
- `assertCount($expected, $array)`
- `assertEmpty($value)`, `assertNotEmpty($value)`
- `assertContains($needle, $haystack)`
- `assertArrayHasKey($key, $array)`
- `assertStringContains($needle, $haystack)`
- `assertStringStartsWith($prefix, $string)`
- `assertStringEndsWith($suffix, $string)`
- `assertMatchesRegex($pattern, $string)`
- `assertInstanceOf($class, $object)`
- `assertThrows($exceptionClass, $callable)`
- `assertGreaterThan($expected, $actual)`
- `assertLessThan($expected, $actual)`

---

## Admin Panel

Optional admin dashboard (disabled by default):

- Enable via `admin.enabled: true` in config
- Access at `/admin` (customisable)
- Bcrypt-hashed passwords in `app/config/users.php`
- Session-based authentication

**Features:**
- Content statistics and recent items
- Cache status and management
- Create/edit/delete content (single-file editor: frontmatter + body)
- Content validation (lint)
- System diagnostics
- Taxonomy term management
- Log viewer

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
