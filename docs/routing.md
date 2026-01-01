# Routing

In Ava, you don't need to write complex route files. URLs are generated automatically based on your content.

## How It Works

Ava looks at your `content/` folder and your configuration to decide what URL each file gets.

1. **💾 You save a file.**
2. **👀 Ava sees it.**
3. **✨ The URL works.**

## URL Styles

You can control how URLs look in `app/config/content_types.php`.

### 1. Folder Style (Hierarchical)

Great for standard pages. The URL matches the folder structure.

- `content/pages/about.md` → `/about`
- `content/pages/services/web.md` → `/services/web`

```php
'page' => [
    'url' => [
        'type' => 'hierarchical',
        'base' => '/',
    ],
]
```

### 2. Pattern Style (Blog Posts)

Great for blogs, where you want a consistent structure like `/blog/{slug}` or `/2024/{slug}`.

- `content/posts/hello-world.md` → `/blog/hello-world`

```php
'post' => [
    'url' => [
        'type' => 'pattern',
        'pattern' => '/blog/{slug}', // You can use {year}, {month}, {day} too!
    ],
]
```

## Redirects

Need to move a page? Just add `redirect_from` to the file's frontmatter. Ava handles the 301 redirect for you.

For more complex routing needs, check out the [Configuration guide](configuration.md).

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

Routes are compiled to a binary cache file (`storage/cache/routes.bin`) for fast lookup. This happens automatically when the content index is rebuilt.

The cache contains:

| Section | Purpose |
|---------|---------|
| `redirects` | 301 redirects from `redirect_from` frontmatter |
| `exact` | Direct URL → content mappings |
| `taxonomy` | Taxonomy archive configurations |

**Route matching order:**

1. **Hook interception** — `router.before_match` filter can intercept early
2. **Trailing slash redirect** — Enforces canonical URL style
3. **Redirects** — 301 redirects from `redirect_from` frontmatter
4. **System routes** — Custom routes registered via `addRoute()`
5. **Exact routes** — Content URLs from cache (e.g., `/blog/my-post`)
6. **Preview mode** — Allows draft access with valid token
7. **Prefix routes** — Custom routes registered via `addPrefixRoute()`
8. **Taxonomy routes** — Archives like `/category/tutorials`
9. **404** — No match found

Routes are rebuilt automatically when content changes (with `content_index.mode = 'auto'`) or manually via [`./ava rebuild`](cli.md?id=rebuild).

For more on performance, see [Performance](performance.md).

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

In `theme.php` (where `$app` is available):

```php
use Ava\Application;

return function (Application $app): void {
    $router = $app->router();

    // Exact route
    $router->addRoute('/api/search', function ($request) use ($app) {
        // Return RouteMatch or handle directly
    });

    // Prefix route
    $router->addPrefixRoute('/api/', function ($request) use ($app) {
        // Handles all /api/* requests
    });
};
```

In a plugin's `boot` function:

```php
'boot' => function($app) {
    $router = $app->router();
    
    $router->addRoute('/api/search', function ($request) use ($app) {
        // ...
    });
}
```
