<?php

declare(strict_types=1);

namespace Ava;

use Ava\Content\Index\IndexStore;
use Ava\Content\Indexer;
use Ava\Content\Repository;
use Ava\Http\ThemeAssets;
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
    private bool $environmentPrepared = false;
    private bool $pluginsLoaded = false;
    private bool $themeLoaded = false;
    private bool $indexRefreshed = false;

    /** @var array<string, object> */
    private array $services = [];

    private ?array $contentTypes = null;
    private ?array $taxonomies = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Boot the application: plugins, theme, and a current content index.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->prepareEnvironment();
        $this->loadExtensions();
        $this->refreshIndex();

        $this->booted = true;
    }

    /**
     * Handle an HTTP request.
     *
     * Three paths, cheapest first:
     * 1. Theme assets depend only on the file, so they are served without
     *    loading plugins or looking at the content index.
     * 2. Cacheable pages: manual index mode serves a cached copy straight
     *    away; automatic modes first confirm the index is current (a
     *    throttled, metadata-only check; a rebuild clears stale pages).
     * 3. Everything else boots fully and is routed.
     */
    public function handle(Request $request): Response
    {
        $this->prepareEnvironment();

        if (str_starts_with($request->path(), ThemeAssets::PREFIX)) {
            $asset = $this->themeAssets()->serve($request);
            if ($asset !== null) {
                return $this->applyPublicSecurityHeaders($asset, $request);
            }
        }

        $cache = $this->webpageCache();
        if ($cache->isCacheable($request)) {
            if ($this->indexMode() !== 'never') {
                $this->refreshIndex();
            }

            $hit = $cache->get($request);
            if ($hit !== null) {
                return $this->applyPublicSecurityHeaders($hit, $request);
            }
        }

        $this->boot();

        return $this->applyPublicSecurityHeaders($this->dispatch($request), $request);
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router()->match($request);

        if ($match === null) {
            return $this->render404($request);
        }

        if ($match->isRedirect()) {
            return Response::redirect($match->getRedirectUrl(), $match->getRedirectCode());
        }

        if ($match->hasResponse()) {
            return $match->getResponse();
        }

        // Handle routes that return raw Response (plugin routes)
        if (in_array($match->getType(), ['plugin', 'response'], true)) {
            $response = $match->getParam('response');
            if ($response instanceof Response) {
                // Feeds and sitemaps are cached like pages. Other plugin
                // output is left alone: it may be per-visitor without saying so.
                return WebpageCache::isFeedType($response->header('Content-Type'))
                    ? $this->cacheResponse($request, $response, null)
                    : $response;
            }
        }

        $response = $this->renderRoute($match, $request);

        // Content can opt out of (or explicitly into) caching. Every YAML
        // spelling of false (false, 0, "no", "off") counts, not the boolean
        // alone; anything unrecognised leaves the default policy in place.
        $cacheOverride = null;
        $override = $match->getContentItem()?->get('cache');
        if ($override !== null) {
            $cacheOverride = filter_var($override, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return $this->cacheResponse($request, $response, $cacheOverride, $match->getQuery() !== null);
    }

    private function cacheResponse(Request $request, Response $response, ?bool $override, bool $paginated = false): Response
    {
        if (!$this->webpageCache()->isEnabled() || $response->status() !== 200) {
            return $response;
        }

        $stored = $this->webpageCache()->put($request, $response, $override, $paginated);

        return $response->withHeader('X-Page-Cache', $stored ? 'MISS' : 'BYPASS');
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

    /** Standard locations, used when paths.<key> is not configured. */
    private const DEFAULT_PATHS = [
        'content' => 'content',
        'themes' => 'app/themes',
        'plugins' => 'app/plugins',
        'snippets' => 'app/snippets',
        'storage' => 'storage',
    ];

    /**
     * Get a path from config and make it absolute.
     */
    public function configPath(string $key): string
    {
        $relative = $this->config("paths.{$key}") ?? self::DEFAULT_PATHS[$key] ?? null;
        if (!is_string($relative)) {
            throw new \InvalidArgumentException("Unknown path key: {$key}");
        }
        return $this->path($relative);
    }

    /**
     * Content type definitions (app/config/content_types.php).
     */
    public function contentTypes(): array
    {
        return $this->contentTypes ??= $this->loadConfigFile('content_types.php');
    }

    /**
     * Taxonomy definitions (app/config/taxonomies.php).
     */
    public function taxonomies(): array
    {
        return $this->taxonomies ??= $this->loadConfigFile('taxonomies.php');
    }

    /**
     * The active theme's folder name (validated; falls back to 'default').
     */
    public function themeName(): string
    {
        $theme = $this->config('theme', 'default');

        return is_string($theme) && preg_match('/^[a-z0-9_-]+$/i', $theme) ? $theme : 'default';
    }

    /**
     * Enabled plugin folder names, in load order. Names that could escape
     * the plugins directory are dropped.
     *
     * @return list<string>
     */
    public function pluginNames(): array
    {
        $plugins = $this->config('plugins', []);

        return array_values(array_filter(
            is_array($plugins) ? $plugins : [],
            static fn($plugin): bool => is_string($plugin) && preg_match('/^[a-z0-9_-]+$/i', $plugin) === 1
        ));
    }

    /**
     * 'auto', 'never' or 'always'.
     */
    public function indexMode(): string
    {
        $mode = $this->config('content_index.mode', 'auto');

        return in_array($mode, ['auto', 'never', 'always'], true) ? $mode : 'auto';
    }

    // -------------------------------------------------------------------------
    // Services
    // -------------------------------------------------------------------------

    public function router(): Router
    {
        return $this->service('router', fn() => new Router($this));
    }

    public function indexStore(): IndexStore
    {
        return $this->service('index_store', fn() => new IndexStore($this->configPath('storage')));
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

    public function themeAssets(): ThemeAssets
    {
        return $this->service('theme_assets', fn() => new ThemeAssets(
            $this->configPath('themes') . '/' . $this->themeName() . '/assets'
        ));
    }

    /**
     * Get or create the shared Markdown converter.
     *
     * Used by both the rendering engine and the indexer so build-time and
     * on-demand rendering are identical.
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
    // Boot steps
    // -------------------------------------------------------------------------

    /**
     * Timezone and storage directories: needed by every request path.
     */
    private function prepareEnvironment(): void
    {
        if ($this->environmentPrepared) {
            return;
        }
        $this->environmentPrepared = true;

        date_default_timezone_set($this->config('site.timezone', 'UTC'));

        $storagePath = $this->configPath('storage');
        foreach (['cache', 'logs', 'tmp'] as $dir) {
            $path = $storagePath . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Load plugins and the theme (once). The indexer calls this before a
     * build, so build-time rendering sees the same hooks as a page request.
     */
    public function loadExtensions(): void
    {
        $this->loadPlugins();
        $this->loadTheme();
    }

    /**
     * Bring the content index up to date (once per request).
     */
    private function refreshIndex(): void
    {
        if ($this->indexRefreshed) {
            return;
        }
        $this->indexRefreshed = true;

        $this->indexer()->refresh($this->indexMode());
    }

    /**
     * Load enabled plugins (once).
     */
    public function loadPlugins(): void
    {
        if ($this->pluginsLoaded) {
            return;
        }
        $this->pluginsLoaded = true;

        $pluginsPath = $this->configPath('plugins');
        foreach ($this->pluginNames() as $plugin) {
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
        if ($this->themeLoaded) {
            return;
        }
        $this->themeLoaded = true;

        $themePath = $this->configPath('themes') . '/' . $this->themeName() . '/theme.php';
        if (file_exists($themePath)) {
            $themeBootstrap = require $themePath;
            if (is_callable($themeBootstrap)) {
                $themeBootstrap($this);
            }
        }

        // handle() serves assets before booting; this route covers requests
        // that reach the router some other way.
        $this->router()->addPrefixRoute(
            ThemeAssets::PREFIX,
            fn(Request $request) => $this->themeAssets()->serve($request)
        );
    }

    private function loadConfigFile(string $file): array
    {
        $path = $this->path('app/config/' . $file);
        $data = file_exists($path) ? require $path : [];

        return is_array($data) ? $data : [];
    }

    private function render404(Request $request): Response
    {
        $content = $this->renderer()->render('404', [
            'request' => $request,
        ]);

        return new Response($content, 404);
    }

    private function renderRoute(Routing\RouteMatch $match, Request $request): Response
    {
        $context = [
            'request' => $request,
            'route' => $match,
        ];

        if ($match->getContentItem() !== null) {
            $context['content'] = $match->getContentItem();
        }

        if ($match->getQuery() !== null) {
            $context['query'] = $match->getQuery();
        }

        if ($match->getTaxonomy() !== null) {
            $context['tax'] = $match->getTaxonomy();
        }

        $content = $this->renderer()->render($match->getTemplate(), $context);

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

        if (preg_match('/<\/html>\s*$/i', $content)) {
            return preg_replace('/<\/html>\s*$/i', $comment . "\n</html>", $content);
        }

        return $content . $comment;
    }
}
