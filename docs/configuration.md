# Configuration

All configuration lives in `app/config/` as plain PHP files that return arrays. No YAML, no JSON, no `.env` magic — just PHP you can read and version control.

## Philosophy

Ava's configuration is intentionally simple:

- **PHP arrays** — Full IDE support, type hints, comments, constants.
- **Version controlled** — Everything except `users.php` goes in Git.
- **No magic** — What you see is what you get. No environment variable parsing, no cascading configs.

## Configuration Files

| File | Purpose | In Git? |
|------|---------|---------|
| `ava.php` | Main configuration (site, paths, cache) | Yes |
| `content_types.php` | Define pages, posts, custom types | Yes |
| `taxonomies.php` | Define categories, tags, custom taxonomies | Yes |
| `users.php` | Admin credentials (auto-generated) | No |

---

## Main Config: `ava.php`

This is the primary configuration file. It returns an array with all site settings.

### Site Settings

```php
'site' => [
    'name' => 'My Site',        // Site title, used in templates
    'base_url' => 'https://example.com',  // Full URL with protocol
    'timezone' => 'UTC',        // PHP timezone identifier
    'locale' => 'en_GB',        // Locale for date formatting
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Your site's name. Available in templates as `$site['name']` |
| `base_url` | string | required | Full URL including protocol. Used for absolute URLs, sitemaps, feeds |
| `timezone` | string | `'UTC'` | PHP timezone. See [timezone list](https://www.php.net/manual/en/timezones.php) |
| `locale` | string | `'en_US'` | Locale for `$ava->date()` formatting |

### Paths

```php
'paths' => [
    'content' => 'content',     // Where markdown files live
    'themes' => 'themes',       // Theme directory
    'plugins' => 'plugins',     // Plugin directory
    'snippets' => 'snippets',   // PHP snippet files for shortcodes
    'storage' => 'storage',     // Cache, logs, temp files (gitignored)
    
    'aliases' => [
        '@media:' => '/media/',
        '@uploads:' => '/media/uploads/',
        '@assets:' => '/assets/',
    ],
],
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `content` | string | `'content'` | Root directory for all content files |
| `themes` | string | `'themes'` | Where themes are stored |
| `plugins` | string | `'plugins'` | Plugin directory |
| `snippets` | string | `'snippets'` | Safe PHP snippets for `[snippet]` shortcode |
| `storage` | string | `'storage'` | Generated files (cache, logs). Safe to delete |
| `aliases` | array | `[]` | Path aliases expanded in content. Use in markdown as `@media:image.jpg` |

### Theme

```php
'theme' => 'default',
```

The active theme name. Must match a folder in `themes/`.

### Cache

```php
'cache' => [
    'mode' => 'auto',
],
```

| Mode | Behavior | Best For |
|------|----------|----------|
| `'auto'` | Rebuild when content files change (fingerprint check) | Development, production |
| `'always'` | Rebuild on every request | Active development only |
| `'never'` | Only rebuild via `./ava rebuild` | High-traffic production |

Ava is designed to be edited live — not a static site generator. The `'auto'` mode is recommended for most production sites. It checks a lightweight fingerprint of your content files and only rebuilds the cache when something changes. The overhead is negligible for most sites.

Use `'never'` only if you need maximum performance on high-traffic sites, and trigger rebuilds via deployment scripts, webhooks, or the CLI.

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
| `frontmatter.format` | string | `'yaml'` | Frontmatter parser (only YAML supported) |
| `markdown.allow_html` | bool | `true` | Allow raw HTML in markdown content |
| `id.type` | string | `'ulid'` | ID format for new content: `'ulid'` (sortable) or `'uuid7'` |

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

> ⚠️ **Important**: Create admin users with `./ava user:create` before enabling.

### Plugins

```php
'plugins' => [
    'sitemap',
    'feed',
    'reading-time',
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

---

## Taxonomies: `taxonomies.php`

Define ways to categorize content.

```php
<?php
return [
    'category' => [
        'label' => 'Categories',
        'plural' => 'Categories',
        'hierarchical' => true,
        'url' => [
            'base' => '/category',
        ],
    ],

    'tag' => [
        'label' => 'Tag',
        'plural' => 'Tags',
        'hierarchical' => false,
        'url' => [
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
| `url.base` | string | `'/{name}'` | URL prefix for term archives |

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

```php
<?php
// app/config/ava.php

return [
    'site' => [
        'name' => 'Acme Corp',
        'base_url' => 'https://acme.com',
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
            '@cdn:' => 'https://cdn.acme.com/',
        ],
    ],

    'theme' => 'acme-theme',

    'cache' => [
        'mode' => 'never',  // Production
    ],

    'routing' => [
        'trailing_slash' => false,
    ],

    'content' => [
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
        'path' => '/_admin',
    ],

    'plugins' => [
        'sitemap',
        'feed',
        'seo',
    ],
];
```
