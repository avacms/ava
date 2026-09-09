<?php

declare(strict_types=1);

namespace Ava;

use Ava\Content\Indexer;
use Ava\Content\Repository;
use Ava\Http\WebpageCache;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;
use Ava\Rendering\Engine as RenderingEngine;
use Ava\Routing\Router;
use Ava\Shortcodes\Engine as ShortcodeEngine;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

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

        // Load plugins
        $this->loadPlugins();

        // Check cache freshness and rebuild if needed
        $this->ensureCacheFresh();

        // Load theme
        $this->loadTheme();

        $this->booted = true;
    }

    /**
     * Handle an HTTP request.
     */
    public function handle(Request $request): Response
    {
        // Automatic modes check source freshness before serving cached HTML.
        // Manual mode can serve a hit before boot; a rebuild clears its cache.
        if ($this->config('content_index.mode', 'auto') !== 'never') {
            $this->boot();
        }

        $response = $this->webpageCache()->get($request);
        if ($response === null) {
            $this->boot();
            $response = $this->dispatch($request);
        }

        return $this->applyPublicSecurityHeaders($response, $request);
    }

    private function dispatch(Request $request): Response
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

        // Handle routes that return raw Response (plugin routes)
        if (in_array($match->getType(), ['plugin', 'response'], true)) {
            $response = $match->getParam('response');
            if ($response instanceof Response) {
                return $response;
            }
        }

        // Render the matched route
        $response = $this->renderRoute($match, $request);

        // Store in webpage cache if enabled
        if ($this->webpageCache()->isEnabled() && $response->status() === 200) {
            // Check for content-level cache override. Accept every YAML
            // spelling of false (false, 0, "no", "off") rather than the
            // boolean alone, so `cache: 0` is not silently cached; anything
            // unrecognised leaves the default policy in place.
            $cacheOverride = null;
            $override = $match->getContentItem()?->get('cache');
            if ($override !== null) {
                $cacheOverride = filter_var($override, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            $stored = $this->webpageCache()->put($request, $response, $cacheOverride);
            $response = $response->withHeader('X-Page-Cache', $stored ? 'MISS' : 'BYPASS');
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

    /**
     * Get content type definitions (cached).
     */
    public function contentTypes(): array
    {
        static $cache = null;
        if ($cache === null) {
            $path = $this->path('app/config/content_types.php');
            $cache = file_exists($path) ? require $path : [];
        }
        return $cache;
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
     * Get or create the shared Markdown converter.
     * 
     * This is used by both the rendering engine and the indexer to ensure
     * consistent Markdown processing and avoid duplicating setup code.
     */
    public function markdown(array $options = []): MarkdownConverter
    {
        $profile = Hooks::apply('markdown.profile', $options);
        $serviceName = $profile === [] ? 'markdown' : 'markdown:' . hash('sha256', serialize($profile));

        return $this->service($serviceName, function () use ($options) {
            $enableHeadingIds = $this->config('content.markdown.heading_ids', true);

            $config = [
                'html_input' => $this->config('content.markdown.allow_html', true)
                    ? 'allow'
                    : 'strip',
                'allow_unsafe_links' => false,
                'disallowed_raw_html' => [
                    'disallowed_tags' => $this->config('content.markdown.disallowed_tags', []),
                ],
            ];

            if ($enableHeadingIds) {
                $config['heading_permalink'] = [
                    'apply_id_to_heading' => true,
                    'insert' => 'none',
                    'min_heading_level' => 1,
                    'max_heading_level' => 6,
                ];
            }

            $config = Hooks::apply('markdown.config', $config, $options);

            $environment = new Environment($config);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            if ($enableHeadingIds) {
                $environment->addExtension(new HeadingPermalinkExtension());
            }

            // Allow plugins to add extensions
            Hooks::doAction('markdown.configure', $environment, $options);

            return new MarkdownConverter($environment);
        });
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
        $indexer = $this->indexer();

        // Keep all cache files on one generation for the duration of the
        // request. Rebuilds wait until active readers have finished.
        $indexer->acquireReadLock();

        if ($mode === 'never') {
            return;
        }

        if ($mode === 'always') {
            $indexer->rebuild();
            return;
        }

        if (!$indexer->isCacheFresh()) {
            $indexer->rebuildIfStale();
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
            // Security: Validate plugin name to prevent path traversal
            // Even though config is filesystem-based, this is defense-in-depth
            if (!is_string($plugin) || !preg_match('/^[a-z0-9_-]+$/i', $plugin)) {
                continue;
            }
            
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
        
        // Security: Validate theme name to prevent path traversal
        if (!is_string($theme) || !preg_match('/^[a-z0-9_-]+$/i', $theme)) {
            $theme = 'default';
        }
        
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
    * Security: Only serves files with allowed extensions (CSS, JS, images, fonts, media).
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

            // Normalize path separators for Windows compatibility
            $normalizedAssetPath = str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
            $fullPath = $assetsDir . DIRECTORY_SEPARATOR . $normalizedAssetPath;
            $realPath = realpath($fullPath);

            // Ensure the resolved path is within the assets directory
            // Use DIRECTORY_SEPARATOR for cross-platform compatibility (Windows uses backslashes)
            if ($realPath === false || !str_starts_with($realPath, $assetsDir . DIRECTORY_SEPARATOR)) {
                return null; // Let it 404
            }

            if (!is_file($realPath)) {
                return null; // Let it 404
            }

            return $this->serveAsset($request, $realPath);
        });
    }



    /**
     * Serve a static asset file with appropriate headers.
     * 
     * Returns null for disallowed extensions or hidden files.
     */
    private function serveAsset(Request $request, string $fullPath): ?Response
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

        $mtime = @filemtime($fullPath);
        if ($mtime === false) {
            return null;
        }

        $size = @filesize($fullPath);
        $etag = '"' . dechex($mtime) . '-' . dechex($size !== false ? $size : 0) . '"';

        $headers = [
            'Content-Type' => $allowedExtensions[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag' => $etag,
        ];

        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return new Response('', 304, $headers);
        }

        $ifModifiedSince = $request->header('if-modified-since');
        if ($ifModifiedSince !== null) {
            $since = strtotime($ifModifiedSince);
            if ($since !== false && $since >= $mtime) {
                return new Response('', 304, $headers);
            }
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        return new Response($content, 200, $headers);
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

        // Add generator footer comment (opt-in: see addGeneratorComment)
        if ($this->config('generator_comment', false)) {
            $content = $this->addGeneratorComment($content);
        }

        return new Response($content, 200);
    }

    /**
     * Apply public security headers from configuration without overriding existing headers.
     */
    private function applyPublicSecurityHeaders(Response $response, Request $request): Response
    {
        // Preview URLs carry a secret and may expose unpublished content. Never
        // allow browsers, proxies, or CDNs to retain them, and avoid leaking the
        // query-string token through a referrer.
        if ($request->queryString('preview')) {
            $response = $response->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $headerConfig = $this->config('security.headers', []);

        // Helper to normalize header values (arrays joined with appropriate separators)
        $normalizeHeader = function (mixed $value, string $separator = '; '): ?string {
            if (is_array($value)) {
                return implode($separator, $value);
            }
            return is_string($value) && $value !== '' ? $value : null;
        };

        $map = [
            'Content-Security-Policy' => $normalizeHeader($headerConfig['content_security_policy'] ?? null),
            'Permissions-Policy' => $normalizeHeader($headerConfig['permissions_policy'] ?? null, ', '),
            'Cross-Origin-Opener-Policy' => $normalizeHeader($headerConfig['cross_origin_opener_policy'] ?? null),
            'Cross-Origin-Resource-Policy' => $normalizeHeader($headerConfig['cross_origin_resource_policy'] ?? null),
        ];

        foreach ($map as $name => $value) {
            if ($value !== null && $response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Add generator comment to HTML content.
     *
     * Off by default. The comment names the exact Ava version on every page,
     * which is convenient for one site and unhelpful across all of them: after
     * a security release it turns "find every unpatched install" into a single
     * search query. Enable it when the diagnostics are worth that.
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
     * Allowlist of permitted asset file extensions and their MIME types.
     * 
     * Only these file types can be served via /theme/ routes.
     * This prevents serving PHP source code, config files, or other sensitive files.
     * 
     * Defined as a constant to avoid re-creating the array on every asset request.
     */
    private const ALLOWED_ASSET_EXTENSIONS = [
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
        // Audio
        'mp3'   => 'audio/mpeg',
        'ogg'   => 'audio/ogg',
        'wav'   => 'audio/wav',
        'm4a'   => 'audio/mp4',
        // Video
        'webm'  => 'video/webm',
        'mp4'   => 'video/mp4',
    ];

    /**
     * @return array<string, string> Extension => MIME type mapping
     */
    private function getAllowedAssetExtensions(): array
    {
        return self::ALLOWED_ASSET_EXTENSIONS;
    }
}
