# Creating Plugins

Plugins let you extend Ava with reusable, shareable functionality that lives outside your theme.

## Plugins vs theme.php

You might wonder: *"Can't I just put everything in theme.php?"*

Yes! For many sites, `theme.php` is all you need. But plugins are better when:

| Use theme.php | Use a Plugin |
|---------------|--------------|
| Theme-specific features | Features that work with any theme |
| Site customisations | Code you want to share with others |
| Simple hooks and shortcodes | Admin dashboard pages |
| Quick, one-off additions | CLI commands |

**Think of it this way:** If you switch themes, anything in `theme.php` disappears. Plugins survive theme changes because they live in a separate folder.

The bundled plugins (sitemap, feed, redirects) are good examples — they work regardless of which theme you use.

## Your First Plugin

1. Create a folder: `plugins/my-plugin/`
2. Create a file: `plugins/my-plugin/plugin.php`

```php
<?php
return [
    'name' => 'My First Plugin',
    'version' => '1.0',
    
    'boot' => function($app) {
        // Your code goes here!
        
        // Example: Add a custom route
        $app->router()->addRoute('/hello', function() {
            return new \Ava\Http\Response('Hello World!');
        });
    }
];
```

3. Enable it in `app/config/ava.php`:

```php
'plugins' => [
    'sitemap',
    'feed',
    'my-plugin',  // Add your plugin here
],
```

That's it! Visit `/hello` and see your plugin in action.

---

## Understanding Hooks

Hooks are the backbone of Ava's plugin system. They let your code run at specific moments during Ava's lifecycle—like when content is being parsed, when a template is about to render, or when the admin sidebar is being built.

There are two types of hooks:

### Filters

Filters let you **modify data** as it passes through Ava. You receive a value, change it, and return it:

```php
use Ava\Plugins\Hooks;

// Add a variable to every template
Hooks::addFilter('render.context', function($context) {
    $context['my_plugin_version'] = '1.0';
    return $context;  // Must return the modified value
});
```

### Actions

Actions let you **react to events** without modifying data. Useful for logging, sending notifications, or side effects:

```php
use Ava\Plugins\Hooks;

// Log when content is indexed
Hooks::addAction('content.after_index', function($items) {
    error_log('Indexed ' . count($items) . ' items');
});
```

### Hook Priority

Hooks run in priority order (lower numbers first). Default is 10:

```php
// Run early (before most other hooks)
Hooks::addFilter('render.context', $callback, 5);

// Run late (after most other hooks)
Hooks::addFilter('render.context', $callback, 100);
```

---

## Available Hooks Reference

Ava provides these hooks for plugins and themes to extend functionality:

### Routing Hooks

| Hook | Type | Description | Parameters |
|------|------|-------------|------------|
| `router.before_match` | Filter | Intercept routing before content lookup | `$match` (null), `$request`, `$router` |

```php
// Return a RouteMatch to override routing, or a Response for immediate response
Hooks::addFilter('router.before_match', function($match, $request, $router) {
    if ($request->path() === '/custom') {
        return new RouteMatch(type: 'custom', template: 'custom');
    }
    return $match; // Let normal routing continue
}, priority: 10);
```

### Content Hooks

| Hook | Type | Description | Parameters |
|------|------|-------------|------------|
| `content.loaded` | Filter | Modify content item after loading from repository | `$content` |

```php
// Add computed fields to content items
Hooks::addFilter('content.loaded', function($content) {
    // Add reading time estimate
    $words = str_word_count(strip_tags($content->rawContent()));
    $content->set('reading_time', max(1, (int) ceil($words / 200)));
    return $content;
});
```

### Rendering Hooks

| Hook | Type | Description | Parameters |
|------|------|-------------|------------|
| `render.context` | Filter | Modify template variables before rendering | `$context[]` |
| `render.output` | Filter | Modify final HTML output after template rendering | `$output`, `$templatePath`, `$context` |
| `markdown.configure` | Action | Configure CommonMark environment | `$environment` |

```php
// Add a custom variable to all templates
Hooks::addFilter('render.context', function($context) {
    $context['analytics_id'] = 'UA-12345';
    return $context;
});

// Modify final HTML output
Hooks::addFilter('render.output', function($output, $templatePath, $context) {
    // Add analytics script before </body>
    $script = '<script>/* analytics */</script>';
    return str_replace('</body>', $script . '</body>', $output);
});

// Add a CommonMark extension
Hooks::addAction('markdown.configure', function($environment) {
    $environment->addExtension(new \League\CommonMark\Extension\Table\TableExtension());
});
```

### Admin Hooks

These let you extend the admin dashboard.

| Hook | Type | Description | Parameters |
|------|------|-------------|------------|
| `admin.register_pages` | Filter | Register custom admin pages | `$pages[]`, `$app` |
| `admin.sidebar_items` | Filter | Add items to admin sidebar | `$items[]`, `$app` |

```php
// Add a custom admin page
Hooks::addFilter('admin.register_pages', function(array $pages, $app) {
    $pages['analytics'] = [
        'label' => 'Analytics',
        'icon' => 'analytics',           // Material Symbols icon name
        'section' => 'Plugins',          // Sidebar section
        'handler' => function($request, $app, $controller) {
            // Return a Response
        },
    ];
    return $pages;
});

// Add a sidebar link
Hooks::addFilter('admin.sidebar_items', function(array $items, $app) {
    $items[] = [
        'label' => 'Documentation',
        'url' => 'https://ava.addy.zone',
        'icon' => 'menu_book',
        'external' => true,
    ];
    return $items;
});
```

---

## Adding Routes

Plugins can register custom routes:

```php
use Ava\Http\Request;
use Ava\Http\Response;

'boot' => function($app) {
    $router = $app->router();
    
    // Simple route
    $router->addRoute('/api/posts', function(Request $request) use ($app) {
        $posts = $app->query()
            ->type('post')
            ->published()
            ->get();
        
        return Response::json(
            array_map(fn($p) => [
                'title' => $p->title(),
                'slug' => $p->slug(),
            ], $posts)
        );
    });
    
    // Route with parameters
    $router->addRoute('/api/posts/{slug}', function(Request $request, array $params) use ($app) {
        $post = $app->repository()->get('post', $params['slug']);
        if (!$post) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['title' => $post->title()]);
    });
    
    // Prefix route (catch-all for /api/*)
    $router->addPrefixRoute('/api/', function(Request $request) {
        return Response::json(['error' => 'Unknown endpoint'], 404);
    });
}
```

## Adding Admin Pages

Plugins can add custom pages to the admin dashboard. The recommended approach uses `renderPluginPage()` which automatically wraps your content in the admin layout (sidebar, header, footer). This ensures your plugin stays compatible with future admin updates.

### Basic Example

```php
use Ava\Plugins\Hooks;
use Ava\Http\Request;
use Ava\Application;

'boot' => function($app) {
    Hooks::addFilter('admin.register_pages', function(array $pages) {
        $pages['my-plugin'] = [
            'label' => 'My Plugin',           // Sidebar label
            'icon' => 'extension',            // Material icon name
            'section' => 'Plugins',           // Sidebar section
            'handler' => function(Request $request, Application $app, $controller) {
                // Your page content (just the main content, no layout)
                $content = '<div class="card">
                    <div class="card-header">
                        <span class="card-title">My Plugin Settings</span>
                    </div>
                    <div class="card-body">
                        <p>Your plugin content goes here.</p>
                    </div>
                </div>';

                // Use renderPluginPage() to wrap in admin layout
                return $controller->renderPluginPage([
                    'title' => 'My Plugin',      // Browser tab title
                    'icon' => 'extension',       // Header icon
                    'activePage' => 'my-plugin', // Highlights in sidebar
                ], $content);
            },
        ];
        return $pages;
    });
}
```

### Using a View File

For more complex pages, create a content-only view file:

**plugins/my-plugin/views/content.php:**
```php
<?php
// Only the main content - no <html>, <head>, sidebar, etc.
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">
            <span class="material-symbols-rounded">check</span>
            Status
        </div>
        <div class="stat-value"><?= $isActive ? 'Active' : 'Inactive' ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">settings</span>
            Configuration
        </span>
    </div>
    <div class="card-body">
        <p><?= htmlspecialchars($message) ?></p>
    </div>
</div>
```

**plugins/my-plugin/plugin.php:**
```php
'handler' => function(Request $request, Application $app, $controller) {
    // Prepare your data
    $isActive = true;
    $message = 'Plugin is working!';

    // Render your content-only view
    ob_start();
    include __DIR__ . '/views/content.php';
    $content = ob_get_clean();

    // Wrap in admin layout
    return $controller->renderPluginPage([
        'title' => 'My Plugin',
        'icon' => 'extension',
        'activePage' => 'my-plugin',
    ], $content);
},
```

### renderPluginPage() Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Page title (shown in browser tab) |
| `heading` | string | Main heading (defaults to title) |
| `icon` | string | Material icon name for header |
| `activePage` | string | Page slug for sidebar highlighting |
| `headerActions` | string | HTML for header action buttons |
| `alertSuccess` | string | Success message to display |
| `alertError` | string | Error message to display |
| `alertWarning` | string | Warning message to display |

### Header Actions Example

```php
return $controller->renderPluginPage([
    'title' => 'My Plugin',
    'icon' => 'extension',
    'activePage' => 'my-plugin',
    'headerActions' => '<a href="/my-plugin/export" class="btn btn-primary btn-sm">
        <span class="material-symbols-rounded">download</span>
        Export
    </a>',
], $content);
```

### Handling Forms with Alerts

```php
'handler' => function(Request $request, Application $app, $controller) {
    $message = null;
    $error = null;

    if ($request->isMethod('POST')) {
        $csrf = $request->post('_csrf', '');
        if (!$controller->auth()->verifyCsrf($csrf)) {
            $error = 'Invalid request. Please try again.';
        } else {
            // Process form...
            $message = 'Settings saved!';
            $controller->auth()->regenerateCsrf();
        }
    }

    $csrf = $controller->auth()->csrfToken();

    ob_start();
    include __DIR__ . '/views/content.php';
    $content = ob_get_clean();

    return $controller->renderPluginPage([
        'title' => 'My Plugin',
        'icon' => 'extension',
        'activePage' => 'my-plugin',
        'alertSuccess' => $message,
        'alertError' => $error,
    ], $content);
},
```

### Available CSS Classes

Your content can use these admin CSS classes:

| Class | Description |
|-------|-------------|
| `.card`, `.card-header`, `.card-body` | Card containers |
| `.stat-grid`, `.stat-card` | Statistics display |
| `.grid`, `.grid-2`, `.grid-3` | Grid layouts |
| `.list-item`, `.list-label`, `.list-value` | List displays |
| `.btn`, `.btn-primary`, `.btn-secondary` | Buttons |
| `.badge`, `.badge-success`, `.badge-muted` | Badges |
| `.alert`, `.alert-success`, `.alert-danger` | Alerts |
| `.table`, `.table-wrap` | Tables |
| `.text-sm`, `.text-tertiary` | Text styles |
| `.mt-4`, `.mt-5` | Margin utilities |

### Why Content-Only Views?

**Don't include the full admin layout in plugin views.** This includes:
- No `<!DOCTYPE html>`, `<html>`, `<head>`, `<body>` tags
- No sidebar markup
- No CSS/JS includes
- No mobile header
- No footer scripts

The admin layout is maintained by Ava core. When the layout is updated (new features, design changes, bug fixes), your plugin will automatically benefit without changes.

## Enabling Plugins

Add plugins to `app/config/ava.php`:

```php
'plugins' => [
    'sitemap',
    'feed',
    'redirects',
    'my-plugin',
],
```

Plugins load in the order listed.

---

## Complete Example: Reading Time

A full plugin that adds reading time estimates to posts:

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
        Hooks::addFilter('render.context', function($context) {
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

In your template:
```php
<?php if (isset($reading_time)): ?>
    <span class="reading-time"><?= $reading_time ?> min read</span>
<?php endif; ?>
```

## Plugin Assets

Include CSS or JS from your plugin:

```php
'boot' => function($app) {
    Hooks::addFilter('render.context', function($context) {
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

## CLI Commands

Plugins can register custom CLI commands that appear in `./ava help` and can be invoked from the command line.

### Registering Commands

Add a `commands` key to your plugin's return array:

```php
return [
    'name' => 'My Plugin',
    'version' => '1.0.0',
    'description' => 'Does something useful',
    'author' => 'Your Name',

    'boot' => function($app) {
        // Your boot logic...
    },

    'commands' => [
        [
            'name' => 'myplugin:status',
            'description' => 'Show plugin status',
            'handler' => function (array $args, $cli) {
                $cli->header('My Plugin Status');
                $cli->info('Everything is working!');
                $cli->writeln('');
                return 0;
            },
        ],
    ],
];
```

### Command Handler

The handler receives two parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `$args` | array | Command line arguments after the command name |
| `$cli` | Application | The CLI application instance with output helpers |

### Available CLI Methods

Your handler can use these methods on the `$cli` object:

```php
// Headers and sections
$cli->header('Section Title');      // Bold section header with underline

// Messages
$cli->info('Informational note');   // ℹ prefixed cyan message
$cli->success('It worked!');        // ✓ prefixed green message
$cli->warning('Be careful');        // ⚠ prefixed yellow message
$cli->error('Something failed');    // ✗ prefixed red message

// Output
$cli->writeln('Plain text');        // Write a line
$cli->writeln('');                  // Blank line

// Tables
$cli->table(
    ['Name', 'Value', 'Status'],    // Headers
    [
        ['item-1', '100', 'active'],
        ['item-2', '200', 'pending'],
    ]
);
```

### Command Naming Convention

Use a prefix based on your plugin name to avoid conflicts:

```php
'commands' => [
    ['name' => 'analytics:report', ...],
    ['name' => 'analytics:reset', ...],
],
```

### Multiple Commands Example

```php
'commands' => [
    [
        'name' => 'backup:create',
        'description' => 'Create a backup',
        'handler' => function (array $args, $cli) {
            $name = $args[0] ?? 'backup-' . date('Y-m-d');
            $cli->header('Creating Backup');
            // ... backup logic ...
            $cli->success("Backup created: {$name}");
            return 0;
        },
    ],
    [
        'name' => 'backup:list',
        'description' => 'List available backups',
        'handler' => function (array $args, $cli) {
            $cli->header('Available Backups');
            // ... list logic ...
            return 0;
        },
    ],
],
```

### Accessing the Application

Your command handler receives the application as its third argument:

```php
'handler' => function (array $args, $cli, \Ava\Application $app) {
    $repository = $app->repository();
    
    $posts = $repository->published('post');
    $cli->info('Found ' . count($posts) . ' posts');
    
    return 0;
},
```

### Return Values

Commands should return an integer exit code:

- `0` — Success
- `1` — Error
- Other non-zero values indicate specific error conditions
