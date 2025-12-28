<?php

declare(strict_types=1);

namespace Ava;

use Ava\Content\Indexer;
use Ava\Content\Repository;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Rendering\Engine as RenderingEngine;
use Ava\Routing\Router;
use Ava\Shortcodes\Engine as ShortcodeEngine;

/**
 * Ava CMS Application
 *
 * Central application container and service locator.
 * Implements singleton pattern for global access.
 */
final class Application
{
    private static ?self $instance = null;

    private array $config;
    private bool $booted = false;

    /** @var array<string, object> */
    private array $services = [];

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize the application with configuration.
     */
    public static function init(array $config): self
    {
        if (self::$instance !== null) {
            throw new \RuntimeException('Application already initialized');
        }

        self::$instance = new self($config);
        return self::$instance;
    }

    /**
     * Get the application instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application not initialized');
        }

        return self::$instance;
    }

    /**
     * Reset the application (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
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
        return $this->renderRoute($match, $request);
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
        $mode = $this->config('cache.mode', 'auto');

        if ($mode === 'never') {
            return;
        }

        if ($mode === 'always' || !$this->indexer()->isCacheFresh()) {
            $this->indexer()->rebuild();
        }
    }

    private function loadPlugins(): void
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
            require $themePath;
        }

        // Register theme assets route
        $this->registerThemeAssetsRoute($theme);
    }

    /**
     * Register a route to serve theme assets with proper caching.
     */
    private function registerThemeAssetsRoute(string $theme): void
    {
        $themesPath = $this->configPath('themes');

        $this->router()->addPrefixRoute('/theme/', function (Request $request) use ($themesPath, $theme) {
            $path = $request->path();
            // Remove /theme/ prefix
            $assetPath = substr($path, 7);

            // Security: prevent directory traversal
            $assetPath = str_replace(['..', "\0"], '', $assetPath);

            $fullPath = $themesPath . '/' . $theme . '/assets/' . $assetPath;

            if (!file_exists($fullPath) || !is_file($fullPath)) {
                return null; // Let it 404
            }

            // Determine content type
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $contentTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
                'ico' => 'image/x-icon',
            ];

            $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
            $content = file_get_contents($fullPath);
            $mtime = filemtime($fullPath);

            return new Response($content, 200, [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
                'ETag' => '"' . md5_file($fullPath) . '"',
            ]);
        });
    }

    private function registerAdminRoutes(): void
    {
        if ($this->config('admin.enabled', false)) {
            $adminRouter = new Admin\AdminRouter($this);
            $adminRouter->register();
        }
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

        // Add page context for single routes
        if ($match->getContentItem() !== null) {
            $context['page'] = $match->getContentItem();
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

        return new Response($content, 200);
    }
}
