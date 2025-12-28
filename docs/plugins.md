# Plugins

Plugins extend Ava without modifying core files. They can add routes, shortcodes, content types, and hook into the rendering pipeline.

## Philosophy

Plugins in Ava are intentionally simple:

- **Just PHP** — No special syntax, no compilation, no autoloading magic.
- **Hooks-based** — Plugins interact via well-defined hook points.
- **Self-contained** — Each plugin is a folder with a manifest file.
- **Opt-in** — Plugins must be explicitly enabled in configuration.

## Plugin Location

Plugins live in `plugins/`, each in its own folder:

```
plugins/
└── my-plugin/
    ├── plugin.php      # Required: Plugin manifest
    ├── src/            # Optional: Additional PHP files
    └── assets/         # Optional: CSS, JS, images
```

## Creating a Plugin

### The Manifest

Every plugin needs a `plugin.php` that returns a manifest array:

```php
<?php
// plugins/my-plugin/plugin.php

use Ava\Plugins\Hooks;

return [
    // Plugin metadata
    'name' => 'My Plugin',
    'version' => '1.0.0',
    'description' => 'What this plugin does',
    'author' => 'Your Name',
    
    // Boot function - called when plugin loads
    'boot' => function($app) {
        // Your plugin code here
    }
];
```

### The Boot Function

The `boot` callback is where your plugin does its work. It receives the Application instance:

```php
'boot' => function($app) {
    // Access configuration
    $siteName = $app->config('site.name');
    
    // Access content repository
    $repo = $app->repository();
    
    // Register routes
    $router = $app->router();
    
    // Register shortcodes
    $shortcodes = $app->shortcodes();
}
```

## Hooks System

Plugins interact with Ava primarily through hooks. There are two types:

| Type | Purpose | Example |
|------|---------|---------|
| **Filters** | Modify data as it flows through | Change rendered HTML |
| **Actions** | React to events | Log when content is rendered |

### Registering Hooks

```php
use Ava\Plugins\Hooks;

// Filter: Modify data and return it
Hooks::add('render.context', function($context) {
    $context['my_custom_var'] = 'Hello from plugin!';
    return $context;
});

// Action: Respond to events
Hooks::add('content.after_index', function($items) {
    // Log, notify, or perform side effects
    error_log('Indexed ' . count($items) . ' items');
});
```

### Available Hooks

#### Content Hooks

| Hook | Description | Parameters |
|------|-------------|------------|
| `content.before_parse` | Before Markdown is parsed | `$content`, `$filePath` |
| `content.after_parse` | After Item is created | `$item` |
| `content.after_index` | After all content indexed | `$items[]` |

#### Rendering Hooks

| Hook | Description | Parameters |
|------|-------------|------------|
| `render.context` | Modify template context | `$context[]` |
| `render.before` | Before template renders | `$template`, `$context` |
| `render.after` | After HTML generated | `$html` |
| `markdown.before` | Before Markdown conversion | `$markdown` |
| `markdown.after` | After Markdown conversion | `$html` |

#### Routing Hooks

| Hook | Description | Parameters |
|------|-------------|------------|
| `router.before_match` | Before route matching | `$request`, `$router` |
| `router.after_match` | After route matched | `$match`, `$request` |

#### Shortcode Hooks

| Hook | Description | Parameters |
|------|-------------|------------|
| `shortcode.before` | Before shortcode processed | `$name`, `$attrs`, `$content` |
| `shortcode.after` | After shortcode processed | `$output`, `$name` |

### Hook Priority

Add a priority (lower runs first):

```php
// Run early (priority 5)
Hooks::add('render.context', $callback, 5);

// Run late (priority 100)
Hooks::add('render.context', $callback, 100);

// Default priority is 10
```

## Registering Shortcodes

```php
use Ava\Shortcodes\Engine;

'boot' => function($app) {
    $shortcodes = $app->service('shortcodes');
    
    $shortcodes->register('button', function($attrs, $content) {
        $href = $attrs['href'] ?? '#';
        $class = $attrs['class'] ?? 'btn';
        return "<a href=\"{$href}\" class=\"{$class}\">{$content}</a>";
    });
}
```

Usage in content:
```markdown
[button href="/contact" class="btn-primary"]Get in Touch[/button]
```

## Adding Routes

```php
'boot' => function($app) {
    $router = $app->router();
    
    // Simple route
    $router->addRoute('/api/posts', function($request, $params) {
        $repo = \Ava\Application::getInstance()->repository();
        $posts = $repo->published('post');
        
        return new \Ava\Routing\RouteMatch(
            type: 'api',
            template: '__raw__',
            params: [
                'response' => \Ava\Http\Response::json(
                    array_map(fn($p) => [
                        'title' => $p->title(),
                        'slug' => $p->slug(),
                    ], $posts)
                )
            ]
        );
    });
    
    // Prefix route (matches anything under /api/*)
    $router->addPrefixRoute('/api/', function($request, $params) {
        // Handle all /api/* requests
    });
}
```

## Adding Content Types

```php
'boot' => function($app) {
    Hooks::add('config.content_types', function($types) {
        $types['product'] = [
            'label' => 'Products',
            'content_dir' => 'products',
            'url' => [
                'type' => 'pattern',
                'pattern' => '/shop/{slug}',
                'archive' => '/shop',
            ],
            'templates' => [
                'single' => 'product.php',
                'archive' => 'shop.php',
            ],
        ];
        return $types;
    });
}
```

## Enabling Plugins

Add plugins to your `app/config/ava.php`:

```php
return [
    // ...
    
    'plugins' => [
        'my-plugin',
        'another-plugin',
    ],
];
```

## Example Plugin: Reading Time

A complete example that adds reading time to posts:

```php
<?php
// plugins/reading-time/plugin.php

use Ava\Plugins\Hooks;

return [
    'name' => 'Reading Time',
    'version' => '1.0.0',
    'description' => 'Adds estimated reading time to content items',
    'author' => 'Ava CMS',
    
    'boot' => function($app) {
        // Add reading_time to template context
        Hooks::add('render.context', function($context) {
            if (isset($context['page']) && $context['page'] instanceof \Ava\Content\Item) {
                $content = $context['page']->rawContent();
                $wordCount = str_word_count(strip_tags($content));
                $minutes = max(1, ceil($wordCount / 200));
                $context['reading_time'] = $minutes;
            }
            return $context;
        });
    }
];
```

Usage in templates:
```php
<?php if (isset($reading_time)): ?>
    <span class="reading-time"><?= $reading_time ?> min read</span>
<?php endif; ?>
```

## Plugin Assets

To include CSS or JS from your plugin:

```php
'boot' => function($app) {
    Hooks::add('render.context', function($context) {
        $context['plugin_assets'][] = '/plugins/my-plugin/assets/style.css';
        return $context;
    });
}
```

Then in your theme's `<head>`:
```php
<?php foreach ($plugin_assets ?? [] as $asset): ?>
    <?php if (str_ends_with($asset, '.css')): ?>
        <link rel="stylesheet" href="<?= $asset ?>">
    <?php else: ?>
        <script src="<?= $asset ?>"></script>
    <?php endif; ?>
<?php endforeach; ?>
```
