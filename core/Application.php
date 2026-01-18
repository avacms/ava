<?php

declare(strict_types=1);

namespace Ava;

use Ava\Content\Indexer;
use Ava\Content\Repository;
use Ava\Http\WebpageCache;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Rendering\Engine as RenderingEngine;
use Ava\Routing\Router;
use Ava\Shortcodes\Engine as ShortcodeEngine;

/**
 * Ava CMS Application
 *
 * Central application container and service locator.
 * Created once at bootstrap and passed explicitly to all components.
 */
final class Application
{
    private array $config;
    private bool $booted = false;

    /** @var array<string, object> */
    private array $services = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Try to serve a cached page without full boot.
     * 
     * This is the fast path - if we have a cached page, serve it immediately
     * without loading plugins, themes, or checking content freshness.
     * 
     * @return Response|null Cached response if available, null to continue with full boot
     */
    public function tryCachedResponse(Request $request): ?Response
    {
        // Only attempt if webpage cache is enabled
        if (!$this->config('webpage_cache.enabled', false)) {
            return null;
        }

        // Quick checks that don't require full boot
        if ($request->method() !== 'GET') {
            return null;
        }

        // Don't serve cache for admin paths
        $adminPath = $this->config('admin.path', '/admin');
        if (str_starts_with($request->path(), $adminPath)) {
            return null;
        }

        // Check for query parameters (skip UTM params)
        $query = $request->query();
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);
        if (!empty($query)) {
            return null;
        }

        // Check for admin session cookie (don't serve cache to logged-in users)
        if (isset($_COOKIE['ava_admin'])) {
            return null;
        }

        // Try to get from cache
        $webpageCache = $this->webpageCache();
        return $webpageCache->getWithoutFullCheck($request);
    }

    /**
     * Boot the application.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Set timezone
        date_default_timezone_set($this->config('site.timezone', 'UTC'));

        // Ensure storage directories exist
        $this->ensureStorageDirectories();

        // Check cache freshness and rebuild if needed
        $this->ensureCacheFresh();

        // Load plugins
        $this->loadPlugins();

        // Load theme
        $this->loadTheme();

        // Register admin routes
        $this->registerAdminRoutes();

        $this->booted = true;
    }

    /**
     * Handle an HTTP request.
     */
    public function handle(Request $request): Response
    {
        // Check webpage cache first
        $webpageCache = $this->webpageCache();
        if ($webpageCache->isEnabled()) {
            $cached = $webpageCache->get($request);
            if ($cached !== null) {
                return $cached;
            }
        }

        $router = $this->router();
        $match = $router->match($request);

        if ($match === null) {
            return $this->render404($request);
        }

        // Handle redirect routes
        if ($match->isRedirect()) {
            return Response::redirect($match->getRedirectUrl(), $match->getRedirectCode());
        }

        // Handle routes with embedded Response objects
        if ($match->hasResponse()) {
            return $match->getResponse();
        }

        // Handle routes that return raw Response (admin, plugin routes)
        if (in_array($match->getType(), ['admin', 'plugin', 'response'], true)) {
            $response = $match->getParam('response');
            if ($response instanceof Response) {
                return $response;
            }
        }

        // Render the matched route
        $response = $this->renderRoute($match, $request);

        // Store in webpage cache if enabled
        if ($webpageCache->isEnabled() && $response->status() === 200) {
            // Check for content-level cache override
            $cacheOverride = null;
            $contentItem = $match->getContentItem();
            if ($contentItem !== null) {
                $cacheOverride = $contentItem->get('cache');
            }

            $webpageCache->put($request, $response, $cacheOverride);
            $response = $response->withHeader('X-Webpage-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Get a configuration value using dot notation.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return Support\Arr::get($this->config, $key, $default);
    }

    /**
     * Get the full configuration array.
     */
    public function allConfig(): array
    {
        return $this->config;
    }

    /**
     * Get an absolute path from a relative path.
     */
    public function path(string $relative = ''): string
    {
        return AVA_ROOT . ($relative ? '/' . ltrim($relative, '/') : '');
    }

    /**
     * Get a path from config and make it absolute.
     */
    public function configPath(string $key): string
    {
        $relative = $this->config("paths.{$key}");
        if ($relative === null) {
            throw new \InvalidArgumentException("Unknown path key: {$key}");
        }
        return $this->path($relative);
    }

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    public function router(): Router
    {
        return $this->service('router', fn() => new Router($this));
    }

    public function indexer(): Indexer
    {
        return $this->service('indexer', fn() => new Indexer($this));
    }

    public function repository(): Repository
    {
        return $this->service('repository', fn() => new Repository($this));
    }

    public function renderer(): RenderingEngine
    {
        return $this->service('renderer', fn() => new RenderingEngine($this));
    }

    public function shortcodes(): ShortcodeEngine
    {
        return $this->service('shortcodes', fn() => new ShortcodeEngine($this));
    }

    public function webpageCache(): WebpageCache
    {
        return $this->service('webpage_cache', fn() => new WebpageCache($this));
    }

    /**
     * Create a new content query.
     * 
     * Unlike other services, this returns a new instance each time
     * since queries are single-use and immutable.
     */
    public function query(): Content\Query
    {
        return new Content\Query($this);
    }

    /**
     * Get or create a service.
     */
    private function service(string $name, callable $factory): object
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $factory();
        }
        return $this->services[$name];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function ensureStorageDirectories(): void
    {
        $storagePath = $this->configPath('storage');
        $dirs = ['cache', 'logs', 'tmp'];

        foreach ($dirs as $dir) {
            $path = $storagePath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function ensureCacheFresh(): void
    {
        $mode = $this->config('content_index.mode', 'auto');

        if ($mode === 'never') {
            return;
        }

        if ($mode === 'always' || !$this->indexer()->isCacheFresh()) {
            $this->indexer()->rebuild();
        }
    }

    /**
     * Load enabled plugins.
     */
    public function loadPlugins(): void
    {
        $plugins = $this->config('plugins', []);
        $pluginsPath = $this->configPath('plugins');

        foreach ($plugins as $plugin) {
            $pluginFile = $pluginsPath . '/' . $plugin . '/plugin.php';
            if (file_exists($pluginFile)) {
                $manifest = require $pluginFile;
                if (is_array($manifest) && isset($manifest['boot']) && is_callable($manifest['boot'])) {
                    $manifest['boot']($this);
                }
            }
        }
    }

    private function loadTheme(): void
    {
        $theme = $this->config('theme', 'default');
        $themePath = $this->configPath('themes') . '/' . $theme . '/theme.php';

        if (file_exists($themePath)) {
            $themeBootstrap = require $themePath;
            if (is_callable($themeBootstrap)) {
                $themeBootstrap($this);
            }
        }

        // Register theme assets route
        $this->registerThemeAssetsRoute($theme);
    }

    /**
     * Register a route to serve theme assets with proper caching.
     * 
     * Security: Only serves files with allowed extensions (CSS, JS, images, fonts).
     * Hidden files (dotfiles) and executable files (PHP, etc.) return 404.
     * Treat your theme's assets/ folder as a public directory.
     */
    private function registerThemeAssetsRoute(string $theme): void
    {
        $themesPath = $this->configPath('themes');

        $this->router()->addPrefixRoute('/theme/', function (Request $request) use ($themesPath, $theme) {
            $path = $request->path();
            // Remove /theme/ prefix
            $assetPath = substr($path, 7);

            // Security: block hidden files (dotfiles like .env, .htaccess)
            $filename = basename($assetPath);
            if (str_starts_with($filename, '.')) {
                return null; // 404
            }

            // Security: prevent directory traversal using realpath validation
            // Note: str_replace('..', '') is insufficient as '....//etc/passwd' becomes '../etc/passwd'
            $assetsDir = realpath($themesPath . '/' . $theme . '/assets');
            if ($assetsDir === false) {
                return null;
            }

            $fullPath = $assetsDir . '/' . $assetPath;
            $realPath = realpath($fullPath);

            // Ensure the resolved path is within the assets directory
            if ($realPath === false || !str_starts_with($realPath, $assetsDir . '/')) {
                return null; // Let it 404
            }

            if (!is_file($realPath)) {
                return null; // Let it 404
            }

            // Security: only serve files with allowed extensions (allowlist)
            // This prevents serving PHP source code or other sensitive files
            $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            $allowedExtensions = $this->getAllowedAssetExtensions();
            if (!isset($allowedExtensions[$ext])) {
                return null; // 404 for disallowed extensions
            }

            $content = file_get_contents($realPath);
            $mtime = filemtime($realPath);

            return new Response($content, 200, [
                'Content-Type' => $allowedExtensions[$ext],
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
                'ETag' => '"' . md5_file($realPath) . '"',
            ]);
        });
    }

    private function registerAdminRoutes(): void
    {
        if ($this->config('admin.enabled', false)) {
            $adminRouter = new Admin\AdminRouter($this);
            $adminRouter->register();
            $this->registerAdminAssetsRoute();
        }
    }

    /**
     * Register a route to serve admin assets with cache busting.
     * 
     * This serves:
     * - Core admin CSS from core/Admin/ (admin.css)
     * - Core admin assets from core/Admin/assets/ (codemirror, etc.)
     * - Plugin assets from app/plugins/{name}/assets/
     * - Other public assets from public/assets/
     */
    private function registerAdminAssetsRoute(): void
    {
        $corePath = $this->path('core/Admin');
        $coreAssetsPath = $this->path('core/Admin/assets');
        $publicPath = $this->path('public/assets');
        $pluginsPath = $this->configPath('plugins');

        $this->router()->addPrefixRoute('/admin-assets/', function (Request $request) use ($corePath, $coreAssetsPath, $publicPath, $pluginsPath) {
            $path = $request->path();
            // Remove /admin-assets/ prefix
            $assetPath = substr($path, 14);

            // Serve core admin.css from core/Admin/
            if ($assetPath === 'admin.css') {
                $adminCssPath = $corePath . '/admin.css';
                if (file_exists($adminCssPath)) {
                    return $this->serveAsset($adminCssPath);
                }
                return null;
            }

            // Serve core assets from core/Admin/assets/ (e.g., codemirror)
            $coreAssetsDir = realpath($coreAssetsPath);
            if ($coreAssetsDir !== false) {
                $fullPath = $coreAssetsDir . '/' . $assetPath;
                $realPath = realpath($fullPath);
                
                if ($realPath !== false && str_starts_with($realPath, $coreAssetsDir . '/') && is_file($realPath)) {
                    return $this->serveAsset($realPath);
                }
            }

            // Check if this is a plugin asset request: /admin-assets/plugin-name/...
            $parts = explode('/', $assetPath, 2);
            if (count($parts) === 2) {
                $pluginName = $parts[0];
                $pluginAssetPath = $parts[1];
                $pluginAssetsDir = realpath($pluginsPath . '/' . $pluginName . '/assets');
                
                if ($pluginAssetsDir !== false) {
                    $fullPath = $pluginAssetsDir . '/' . $pluginAssetPath;
                    $realPath = realpath($fullPath);
                    
                    if ($realPath !== false && str_starts_with($realPath, $pluginAssetsDir . '/') && is_file($realPath)) {
                        return $this->serveAsset($realPath);
                    }
                }
            }

            // Otherwise, serve from public/assets/
            $assetsDir = realpath($publicPath);
            if ($assetsDir === false) {
                return null;
            }

            $fullPath = $assetsDir . '/' . $assetPath;
            $realPath = realpath($fullPath);

            if ($realPath === false || !str_starts_with($realPath, $assetsDir . '/') || !is_file($realPath)) {
                return null;
            }

            return $this->serveAsset($realPath);
        });
    }

    /**
     * Serve a static asset file with appropriate headers.
     * 
     * Returns null for disallowed extensions or hidden files.
     */
    private function serveAsset(string $fullPath): ?Response
    {
        // Security: block hidden files (dotfiles)
        $filename = basename($fullPath);
        if (str_starts_with($filename, '.')) {
            return null;
        }

        // Security: only serve files with allowed extensions
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $allowedExtensions = $this->getAllowedAssetExtensions();
        if (!isset($allowedExtensions[$ext])) {
            return null;
        }

        $content = file_get_contents($fullPath);
        $mtime = filemtime($fullPath);

        return new Response($content, 200, [
            'Content-Type' => $allowedExtensions[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag' => '"' . md5_file($fullPath) . '"',
        ]);
    }

    private function render404(Request $request): Response
    {
        $renderer = $this->renderer();
        $content = $renderer->render('404', [
            'request' => $request,
        ]);

        return new Response($content, 404);
    }

    private function renderRoute(Routing\RouteMatch $match, Request $request): Response
    {
        $renderer = $this->renderer();

        $context = [
            'request' => $request,
            'route' => $match,
        ];

        // Add content context for single routes
        if ($match->getContentItem() !== null) {
            $context['content'] = $match->getContentItem();
        }

        // Add query context for archives
        if ($match->getQuery() !== null) {
            $context['query'] = $match->getQuery();
        }

        // Add taxonomy context
        if ($match->getTaxonomy() !== null) {
            $context['tax'] = $match->getTaxonomy();
        }

        $content = $renderer->render($match->getTemplate(), $context);

        // Add generator footer comment
        $content = $this->addGeneratorComment($content);

        return new Response($content, 200);
    }

    /**
     * Add generator comment to HTML content.
     */
    private function addGeneratorComment(string $content): string
    {
        $version = defined('AVA_VERSION') ? AVA_VERSION : 'dev';
        $timestamp = date('Y-m-d H:i:s');
        $renderTime = defined('AVA_START') ? round((microtime(true) - AVA_START) * 1000, 1) : 0;
        
        $comment = "\n<!-- Generated by Ava CMS v{$version} | Rendered: {$timestamp} | {$renderTime}ms -->";

        // Add before </html> if present
        if (preg_match('/<\/html>\s*$/i', $content)) {
            return preg_replace('/<\/html>\s*$/i', $comment . "\n</html>", $content);
        }

        return $content . $comment;
    }

    /**
     * Get the allowlist of permitted asset file extensions and their MIME types.
     * 
     * Only these file types can be served via /theme/ and /admin-assets/ routes.
     * This prevents serving PHP source code, config files, or other sensitive files.
     * 
     * @return array<string, string> Extension => MIME type mapping
     */
    private function getAllowedAssetExtensions(): array
    {
        return [
            // Stylesheets
            'css'   => 'text/css',
            // JavaScript
            'js'    => 'application/javascript',
            'mjs'   => 'application/javascript',
            // Data formats
            'json'  => 'application/json',
            'map'   => 'application/json', // Source maps
            // Images
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'avif'  => 'image/avif',
            // Fonts
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];
    }
}
