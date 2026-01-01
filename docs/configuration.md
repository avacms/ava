# Configuration

Ava's configuration is simple and transparent. All settings live in `app/config/` as plain PHP files.

## Why PHP Configs?

We use PHP arrays instead of YAML or JSON because:
1. **It's Readable:** You can add comments to explain *why* you changed a setting.
2. **It's Powerful:** You can use constants, logic, or helper functions right in your config.
3. **It's Standard:** No special parsers or hidden `.env` files to debug.

## The Config Files

| File | What it controls |
|------|------------------|
| `ava.php` | Main site settings (name, URL, cache). |
| `content_types.php` | Defines your content (Pages, Posts, etc.). See [Content](content.md). |
| `taxonomies.php` | Defines how you group content (Categories, Tags). See [Taxonomy Fields](content.md?id=taxonomy-fields). |
| `users.php` | Admin users (generated automatically). See [User Management](cli.md?id=user-management). |

## Main Settings (`ava.php`)

This is where you set up your site's identity.

```php
return [
    'site' => [
        'name' => 'My Awesome Site',
        'base_url' => 'https://example.com',
        'timezone' => 'Europe/London',
        'locale' => 'en_GB',
    ],
    // ...
];
```

### Key Options

| Option | Description |
|--------|-------------|
| `site.name` | Your site's display name (used in templates, feeds, etc.) |
| `site.base_url` | Full URL where your site lives (no trailing slash). Used for sitemaps and absolute links. |
| `site.timezone` | Timezone for dates. Use a [PHP timezone identifier](https://www.php.net/manual/en/timezones.php). |
| `site.locale` | Locale for date/number formatting. See [list of locale codes](https://www.php.net/manual/en/function.setlocale.php#refsect1-function-setlocale-notes). |
| `site.date_format` | Default format for `$ava->date()`. Uses [PHP date() format codes](https://www.php.net/manual/en/datetime.format.php). Default: `F j, Y`. |
| `paths` | Where Ava finds content, themes, plugins. Usually no need to change. || `paths.aliases` | Path aliases for use in content. See [Path Aliases](#path-aliases) below. |

### Path Aliases

Path aliases let you reference files without hard-coding URLs. Define them in `paths.aliases`:

```php
'paths' => [
    'content' => 'content',
    'themes' => 'themes',
    // ...
    'aliases' => [
        '@media:' => '/media/',
        '@cdn:' => 'https://cdn.example.com/',
    ],
],
```

Then use them in your content Markdown:

```markdown
![Photo](@media:images/photo.jpg)
[Download](@media:files/guide.pdf)
```

At render time, `@media:` expands to `/media/`. This makes it easy to reorganise assets or switch to a CDN later without updating every content file.

**See:** [Writing Content - Path Aliases](content.md#path-aliases) for usage examples.
### Content Index

The content index is a binary snapshot of all your content metadata—used to avoid parsing Markdown on every request.

```php
'content_index' => [
    'mode' => 'auto',
    'backend' => 'array',
],
```

| Option | Values | Description |
|--------|--------|-------------|
| `mode` | `auto`, `never`, `always` | When to rebuild the index |
| `backend` | `array`, `sqlite` | Storage backend for the index |

**Mode options:**

| Mode | Behaviour |
|------|----------|
| `auto` | Rebuilds when content files change. Best for development. |
| `never` | Only rebuilds via [`./ava rebuild`](cli.md?id=rebuild). Best for production. |
| `always` | Rebuilds every request. For debugging only. |

**Backend options:**

| Backend | Behaviour |
|---------|----------|
| `array` | Binary serialized PHP arrays. Works everywhere. **This is the default.** |
| `sqlite` | SQLite database file. Opt-in for large sites (10k+ items). Requires `pdo_sqlite`. |

The `array` backend automatically uses the best available serialization method:

- **igbinary** (default when available): ~5x faster serialization, ~9x smaller cache files
- **PHP serialize** (fallback): Used when igbinary extension isn't installed

Control this with `use_igbinary` (defaults to `true`):

```php
'content_index' => [
    'backend' => 'array',
    'use_igbinary' => true,  // Use igbinary if available
],
```

Ava detects which format each cache file uses via prefix markers (`IG:` or `SZ:`), so you can switch between them safely without rebuilding.

<div class="beginner-box">

**Which backend should I use?**

Stick with `array` — it works great for most sites. Only switch to `sqlite` if you have 10,000+ posts, notice slow queries or have server memory issues.

See [Performance](performance.md) for detailed benchmarks.

</div>

### Webpage Cache

The webpage cache stores fully-rendered HTML for instant serving. This applies to all URLs on your site—pages, posts, archive listings, taxonomy pages, and any custom content types you define.

```php
'webpage_cache' => [
    'enabled' => true,
    'ttl' => null,
    'exclude' => [
        '/api/*',
        '/preview/*',
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable HTML webpage caching |
| `ttl` | int\|null | `null` | Lifetime in seconds. `null` = until rebuild |
| `exclude` | array | `[]` | URL patterns to never cache |

**How it works:**
- First visit: Webpage rendered and saved to `storage/cache/pages/`
- Subsequent visits: Cached HTML served (~0.1ms vs ~30ms)
- On `./ava rebuild`: Webpage cache is cleared
- On content change (with `content_index.mode = 'auto'`): Webpage cache is cleared
- Logged-in admin users bypass the cache

**Per-page override:**

```yaml
---
title: My Dynamic Page
cache: false
---
```

**CLI commands:**
- `./ava cache:stats` - View cache statistics
- `./ava cache:clear` - Clear all cached webpages
- `./ava cache:clear /blog/*` - Clear matching pattern

For details, see [Performance](performance.md).

### Routing

```php
'routing' => [
    'trailing_slash' => false,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `trailing_slash` | bool | `false` | If `true`, URLs end with `/`. Mismatched requests get 301 redirected |

### Content Parsing

```php
'content' => [
    'frontmatter' => [
        'format' => 'yaml',     // Only YAML supported currently
    ],
    'markdown' => [
        'allow_html' => true,   // Allow raw HTML in markdown
    ],
    'id' => [
        'type' => 'ulid',       // ulid or uuid7
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `frontmatter.format` | string | `'yaml'` | Frontmatter parser (only YAML supported currently) |
| `markdown.allow_html` | bool | `true` | Allow raw HTML in markdown content |
| `id.type` | string | `'ulid'` | ID format for new content: `'ulid'` (easily sortable) or `'uuid7'` |

### Security

```php
'security' => [
    'shortcodes' => [
        'allow_php_snippets' => true,
    ],
    'preview_token' => null,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `shortcodes.allow_php_snippets` | bool | `true` | Enable `[snippet]` shortcode for PHP includes |
| `preview_token` | string\|null | `null` | Secret token for previewing draft content via `?preview=1&token=xxx` |

?> **Tip:** Set `preview_token` to a long, random string if you want to preview drafts without logging in. Keep it secret—anyone with the token can view unpublished content.

### Admin

```php
'admin' => [
    'enabled' => false,
    'path' => '/admin',
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable the admin dashboard |
| `path` | string | `'/admin'` | URL path for admin (e.g., `/admin`, `/dashboard`, `/_ava`) |

!> **Important**: Create admin users with `./ava user:add` before enabling.

### Debug Mode

Control error visibility and logging for development and troubleshooting.

```php
'debug' => [
    'enabled' => false,
    'display_errors' => false,
    'log_errors' => true,
    'level' => 'errors',
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Master switch for debug features |
| `display_errors` | bool | `false` | Show PHP errors in browser (**never enable in production!**) |
| `log_errors` | bool | `true` | Write errors to `storage/logs/error.log` |
| `level` | string | `'errors'` | Error reporting level |

**Error levels:**

| Level | What's reported |
|-------|-----------------|
| `all` | All errors, warnings, notices, and deprecations |
| `errors` | Only fatal errors and exceptions (default) |
| `none` | Suppress all error reporting |

**Recommended settings:**

```php
// Development - see everything
'debug' => [
    'enabled' => true,
    'display_errors' => true,
    'log_errors' => true,
    'level' => 'all',
],

// Production - log only, never display
'debug' => [
    'enabled' => false,
    'display_errors' => false,
    'log_errors' => true,
    'level' => 'errors',
],
```

!> **Security Warning**: Never enable `display_errors` in production—it can expose sensitive information like file paths, database details, and stack traces.

?> **Security headers**: Ava automatically adds security headers to all responses (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) to protect against common attacks like clickjacking and MIME-sniffing.

The admin System page shows debug status, performance metrics, and recent error log entries when enabled.

### Logs

Control log file size and automatic rotation to prevent disk space issues.

```php
'logs' => [
    'max_size' => 10 * 1024 * 1024,   // 10 MB
    'max_files' => 3,
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_size` | int | `10485760` | Maximum log file size in bytes before rotation (10 MB) |
| `max_files` | int | `3` | Number of rotated log files to keep |

**How rotation works:**

When a log file (e.g., `indexer.log`) exceeds `max_size`:

1. Old rotated logs shift: `.2` → `.3`, `.1` → `.2`
2. Current log becomes `.1`
3. A fresh log file starts
4. If there are more than `max_files` rotations, the oldest is deleted

**Example with defaults (10 MB, 3 files):**

```
indexer.log       ← current, up to 10 MB
indexer.log.1     ← previous rotation
indexer.log.2     ← older rotation
indexer.log.3     ← oldest (deleted when .4 would be created)
```

**CLI commands:**

```bash
./ava logs:stats              # View log file sizes and settings
./ava logs:tail indexer       # Show last 20 lines
./ava logs:tail indexer -n 50 # Show last 50 lines
./ava logs:clear              # Clear all logs (with confirmation)
./ava logs:clear indexer.log  # Clear specific log
```

See [CLI - Logs](cli.md?id=logs) for more details.

### CLI

Customize the command-line interface appearance.

```php
'cli' => [
    'theme' => 'cyan',
],
```

| Theme | Description |
|-------|-------------|
| `cyan` | Cool cyan/aqua (default) |
| `pink` | Vibrant pink |
| `purple` | Classic purple |
| `green` | Matrix green |
| `blue` | Standard blue |
| `amber` | Warm amber/orange |
| `disabled` | No colors (plain text) |

Use `disabled` for CI/CD pipelines or terminals that don't support ANSI colors.

### Plugins

```php
'plugins' => [
    'sitemap',
    'feed',
    'redirects',
],
```

Array of plugin folder names to activate. Plugins load in the order listed.

---

## Content Types: `content_types.php`

Define what kinds of content your site has.

```php
<?php
return [
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
];
```

### Content Type Options

| Option | Type | Description |
|--------|------|-------------|
| `label` | string | Human-readable name for admin UI |
| `content_dir` | string | Folder inside `content/` for this type |
| `url.type` | string | `'hierarchical'` or `'pattern'` |
| `url.base` | string | URL prefix for hierarchical types |
| `url.pattern` | string | URL template with placeholders |
| `url.archive` | string | Archive page URL (for pattern types) |
| `templates.single` | string | Template for single items |
| `templates.archive` | string | Template for archive/listing pages |
| `taxonomies` | array | Which taxonomies apply to this type |
| `sorting` | string | Default sort: `'date_desc'`, `'date_asc'`, `'title'`, `'manual'` |
| `search` | array | Search config: `enabled`, `fields`, `weights` |

### URL Types

**Hierarchical** — URL mirrors file path:
```
content/pages/about.md        → /about
content/pages/about/team.md   → /about/team
content/pages/services/web.md → /services/web
```

**Pattern** — URL from template with placeholders:
```php
'pattern' => '/blog/{slug}'           // → /blog/my-post
'pattern' => '/blog/{yyyy}/{slug}'    // → /blog/2024/my-post
'pattern' => '/{category}/{slug}'     // → /tutorials/my-post
```

| Placeholder | Description |
|-------------|-------------|
| `{slug}` | Item's slug |
| `{id}` | Item's unique ID |
| `{yyyy}` | 4-digit year |
| `{mm}` | 2-digit month |
| `{dd}` | 2-digit day |

### Search Configuration

Control how content types are searched:

```php
'post' => [
    // ... other options
    'search' => [
        'enabled' => true,
        'fields' => ['title', 'excerpt', 'body', 'author'],  // Fields to search
        'weights' => [                    // Optional: customise scoring
            'title_phrase' => 80,         // Exact phrase in title
            'title_all_tokens' => 40,     // All search words in title
            'title_token' => 10,          // Per-word match in title (max 30)
            'excerpt_phrase' => 30,       // Exact phrase in excerpt
            'excerpt_token' => 3,         // Per-word match in excerpt (max 15)
            'body_phrase' => 20,          // Exact phrase in body
            'body_token' => 2,            // Per-word match in body (max 10)
            'featured' => 15,             // Boost for featured items
            'field_weight' => 5,          // Per custom field match
        ],
    ],
],
```

The `fields` array is automatically searched. Default weights are used if `weights` is not specified.

You can also set weights per-query:

```php
$results = $ava->query()
    ->type('post')
    ->searchWeights([
        'title_phrase' => 100,    // Make title matches more important
        'body_phrase' => 50,      // Boost body matches
        'featured' => 0,          // Disable featured boost
    ])
    ->search('tutorial')
    ->get();
```

---

## Taxonomies: `taxonomies.php`

Define ways to categorise content.

```php
<?php
return [
    'category' => [
        'label' => 'Categories',
        'plural' => 'Categories',
        'hierarchical' => true,
        'rewrite' => [
            'base' => '/category',
        ],
    ],

    'tag' => [
        'label' => 'Tag',
        'plural' => 'Tags',
        'hierarchical' => false,
        'rewrite' => [
            'base' => '/tag',
        ],
    ],
];
```

### Taxonomy Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `label` | string | required | Singular name |
| `plural` | string | label + 's' | Plural name for UI |
| `hierarchical` | bool | `false` | Support parent/child relationships |
| `rewrite.base` | string | `'/{name}'` | URL prefix for term archives |

### Using Taxonomies in Content

```yaml
---
title: My Post
category: Tutorials
tag:
  - php
  - cms
  - beginner
---
```

---

## Environment-Specific Config

Override settings per environment:

```php
// app/config/ava.php

$config = [
    'site' => [
        'name' => 'My Site',
        'base_url' => 'https://example.com',
    ],
    'cache' => [
        'mode' => 'never',
    ],
];

// Override for local development
if (getenv('APP_ENV') === 'development') {
    $config['site']['base_url'] = 'http://localhost:8000';
    $config['cache']['mode'] = 'always';
    $config['admin']['enabled'] = true;
}

return $config;
```

---

## Complete Example

A comprehensive production-ready configuration:

```php
<?php
// app/config/ava.php

return [
    'site' => [
        'name' => 'Example Site',
        'base_url' => 'https://example.com',
        'timezone' => 'America/New_York',
        'locale' => 'en_US',
    ],

    'paths' => [
        'content' => 'content',
        'themes' => 'themes',
        'plugins' => 'plugins',
        'snippets' => 'snippets',
        'storage' => 'storage',
        'aliases' => [
            '@media:' => '/media/',
            '@cdn:' => 'https://cdn.example.com/',
        ],
    ],

    'theme' => 'default',

    'content_index' => [
        'mode' => 'never',              // Production: rebuild manually
        'backend' => 'array',           // Use 'sqlite' for 10k+ items
        'use_igbinary' => true,
    ],

    'webpage_cache' => [
        'enabled' => true,
        'ttl' => null,
        'exclude' => [
            '/api/*',
            '/preview/*',
        ],
    ],

    'routing' => [
        'trailing_slash' => false,
    ],

    'content' => [
        'frontmatter' => [
            'format' => 'yaml',
        ],
        'markdown' => [
            'allow_html' => true,
        ],
        'id' => [
            'type' => 'ulid',
        ],
    ],

    'security' => [
        'shortcodes' => [
            'allow_php_snippets' => true,
        ],
        'preview_token' => getenv('PREVIEW_TOKEN') ?: null,
    ],

    'admin' => [
        'enabled' => true,
        'path' => '/admin',
    ],

    'plugins' => [
        'sitemap',
        'feed',
        'redirects',
    ],

    'cli' => [
        'colors' => true,
    ],

    'logs' => [
        'max_size' => 10 * 1024 * 1024,
        'max_files' => 3,
    ],

    'debug' => [
        'enabled' => false,
        'display_errors' => false,
        'log_errors' => true,
        'level' => 'errors',
    ],
];
```
