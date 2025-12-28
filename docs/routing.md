# Routing

Ava uses a custom router with no external dependencies. URLs are derived from your content and configuration — no route files to maintain.

## How It Works

1. **Content is indexed** — When cache builds, Ava scans all content and generates a route map.
2. **Routes are compiled** — The route map is saved as a PHP array for fast lookups.
3. **Requests are matched** — Incoming URLs are matched against the compiled routes.

You don't define routes manually. They're generated from your content types, taxonomies, and content files.

## Route Matching Order

When a request comes in, Ava checks in this order:

| Priority | Type | Example |
|----------|------|---------|
| 1 | Trailing slash redirect | `/about/` → `/about` |
| 2 | Redirects (from frontmatter) | `/old-url` → `/new-url` |
| 3 | System routes (plugins, admin) | `/admin`, `/api/posts` |
| 4 | Exact content routes | `/about`, `/blog/hello` |
| 5 | Taxonomy routes | `/category/tutorials` |
| 6 | 404 | No match found |

## URL Configuration

### Hierarchical URLs (Pages)

Files map directly to URLs:

```yaml
# content_types.php
'page' => [
    'url' => [
        'type' => 'hierarchical',
        'base' => '/',
    ],
]
```

| File | URL |
|------|-----|
| `content/pages/index.md` | `/` |
| `content/pages/about.md` | `/about` |
| `content/pages/services/web.md` | `/services/web` |

### Pattern URLs (Posts)

URLs built from a pattern:

```yaml
'post' => [
    'url' => [
        'type' => 'pattern',
        'pattern' => '/blog/{slug}',
        'archive' => '/blog',
    ],
]
```

Pattern tokens:
- `{slug}` — Item slug
- `{yyyy}` — Year (4 digits)
- `{mm}` — Month (2 digits)
- `{dd}` — Day (2 digits)
- `{id}` — Item ID

### Taxonomy URLs

Configured per taxonomy:

```yaml
# taxonomies.php
'category' => [
    'rewrite' => [
        'base' => '/category',
    ],
]
```

Results in:
- `/category/tutorials`
- `/category/news`

For hierarchical taxonomies, terms can have paths:
- `/topic/guides/basics`

## Redirects

Add `redirect_from` to frontmatter:

```yaml
---
title: New Page
slug: new-page
redirect_from:
  - /old-page
  - /legacy/page
---
```

Requests to `/old-page` redirect 301 to the new URL.

## Trailing Slash

Configure in `ava.php`:

```php
'routing' => [
    'trailing_slash' => false,  // /about (not /about/)
]
```

Non-canonical URLs redirect to canonical form.

## Route Caching

Routes are compiled to `storage/cache/routes.php`:

```php
return [
    'redirects' => [
        '/old-url' => ['to' => '/new-url', 'code' => 301],
    ],
    'exact' => [
        '/' => ['type' => 'single', 'content_type' => 'page', 'slug' => 'index', ...],
        '/about' => ['type' => 'single', 'content_type' => 'page', 'slug' => 'about', ...],
        '/blog' => ['type' => 'archive', 'content_type' => 'post', ...],
        '/blog/hello-world' => ['type' => 'single', 'content_type' => 'post', ...],
    ],
    'taxonomy' => [
        'category' => ['base' => '/category', 'hierarchical' => true],
        'tag' => ['base' => '/tag', 'hierarchical' => false],
    ],
];
```

## Preview Mode

Drafts and private content accessible with token:

```
/blog/draft-post?preview=1&token=YOUR_TOKEN
```

Configure token in `ava.php`:

```php
'security' => [
    'preview_token' => 'your-secret-token',
]
```

## Adding Custom Routes

In plugins or `app/hooks.php`:

```php
use Ava\Application;

$router = Application::getInstance()->router();

// Exact route
$router->addRoute('/api/search', function ($request) {
    // Return RouteMatch or handle directly
});

// Prefix route
$router->addPrefixRoute('/api/', function ($request) {
    // Handles all /api/* requests
});
```
