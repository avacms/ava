# Documentation Update: Plugin Admin Assets

## Summary

Plugins can now serve their own CSS and JavaScript assets in the admin area with automatic cache busting.

## Admin CSS Location

The core admin stylesheet (`admin.css`) is now located at `core/Admin/admin.css` instead of `public/assets/admin.css`. This keeps all admin-related files together and simplifies updates.

The stylesheet is served via PHP at `/admin-assets/admin.css` with cache busting headers.

## New Feature: Admin Asset Route

A new route `/admin-assets/` has been added to serve static assets for the admin interface. This allows plugins to include their own stylesheets and scripts in admin pages.

### Plugin Assets

Plugins can serve assets from their `assets/` folder using the URL pattern:

```
/admin-assets/{plugin-name}/{file-path}
```

**Example:** A plugin named `analytics` with file `app/plugins/analytics/assets/styles.css` would be accessible at:

```
/admin-assets/analytics/styles.css
```

### Usage in Plugin Admin Pages

When rendering a plugin admin page, include assets with cache busting:

```php
Hooks::addFilter('admin.register_pages', function($pages) {
    $pages['my-plugin'] = [
        'label' => 'My Plugin',
        'icon' => 'extension',
        'handler' => function($request, $app, $controller) {
            // Get asset path with cache busting
            $pluginPath = $app->configPath('plugins') . '/my-plugin/assets';
            $cssFile = $pluginPath . '/styles.css';
            $cssUrl = '/admin-assets/my-plugin/styles.css';
            if (file_exists($cssFile)) {
                $cssUrl .= '?v=' . filemtime($cssFile);
            }
            
            $content = <<<HTML
            <link rel="stylesheet" href="{$cssUrl}">
            <div class="card">
                <!-- Plugin content -->
            </div>
            HTML;
            
            return $controller->renderPluginPage([
                'title' => 'My Plugin',
                'icon' => 'extension',
                'activePage' => 'my-plugin',
            ], $content);
        },
    ];
    return $pages;
});
```

### Asset Caching

All assets served through `/admin-assets/` include:
- `Cache-Control: public, max-age=31536000, immutable` 
- `ETag` header based on file hash
- `Last-Modified` header

Use the `?v={timestamp}` query parameter for cache busting when files change.

### Supported File Types

The following file types are supported with proper MIME types:
- CSS (`.css`)
- JavaScript (`.js`)
- JSON (`.json`)
- Images (`.svg`, `.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`, `.ico`)
- Fonts (`.woff`, `.woff2`, `.ttf`, `.eot`)

### Security

The asset route includes security protections:
- Path traversal attacks are blocked
- Assets must be within the plugin's `assets/` directory
- Directory listings are not exposed

## Plugin Directory Structure

```
app/plugins/my-plugin/
├── plugin.php
└── assets/
    ├── styles.css
    ├── scripts.js
    └── images/
        └── icon.svg
```
