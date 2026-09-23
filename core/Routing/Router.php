<?php

declare(strict_types=1);

namespace Ava\Routing;

use Ava\Application;
use Ava\Content\Query;
use Ava\Content\Repository;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

/**
 * Matches incoming requests to routes, in this order:
 *
 * 1. Hook interception (router.before_match filter)
 * 2. Prefix routes (addPrefixRoute) — before trailing-slash enforcement,
 *    which applies to content URLs only
 * 3. Trailing slash redirect (canonical URL enforcement)
 * 4. Redirects (from redirect_from frontmatter)
 * 5. System routes (addRoute), exact then parameterised
 * 6. Exact routes (from content cache)
 * 7. Preview mode (unpublished content with a valid token)
 * 8. Taxonomy routes (index and term pages)
 * 9. 404
 */
final class Router
{
    private Application $app;

    /** @var array<string, callable> System routes - exact match (no params) */
    private array $exactSystemRoutes = [];

    /** @var array<string, callable> System routes with {param} placeholders */
    private array $paramSystemRoutes = [];

    /** @var array<string, callable> Prefix routes registered at runtime */
    private array $prefixRoutes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register a system route.
     * 
     * Routes are stored separately based on whether they have parameters,
     * allowing O(1) lookup for exact matches.
     */
    public function addRoute(string $path, callable $handler): void
    {
        if (str_contains($path, '{')) {
            $this->paramSystemRoutes[$path] = $handler;
        } else {
            $this->exactSystemRoutes[$path] = $handler;
        }
    }

    /**
     * Register a prefix route.
     */
    public function addPrefixRoute(string $prefix, callable $handler): void
    {
        $this->prefixRoutes[$prefix] = $handler;
    }

    public function match(Request $request): ?RouteMatch
    {
        $path = $this->normalizePath($request->path());
        $repository = $this->app->repository();
        $routes = $repository->routes();

        // Allow hooks to intercept routing
        $match = Hooks::apply('router.before_match', null, $request, $this);
        if ($match instanceof RouteMatch) {
            return $match;
        }
        // Allow hooks to return Response objects directly
        if ($match instanceof Response) {
            return new RouteMatch(
                type: 'response',
                response: $match
            );
        }

        // Prefix routes serve non-content resources (assets, APIs), so they run
        // before the trailing-slash enforcement that content URLs get.
        foreach ($this->prefixRoutes as $prefix => $handler) {
            if (str_starts_with($path, $prefix)) {
                return $this->invokeHandler($handler, $request);
            }
        }

        // Trailing slash redirect (content routes only)
        $redirectMatch = $this->checkTrailingSlash($request);
        if ($redirectMatch !== null) {
            return $redirectMatch;
        }

        // Redirects from redirect_from frontmatter
        if (isset($routes['redirects'][$path])) {
            $redirect = $routes['redirects'][$path];
            return new RouteMatch(
                type: 'redirect',
                redirectUrl: $redirect['to'],
                redirectCode: $redirect['code'] ?? 301
            );
        }

        // System routes registered at runtime
        // First, O(1) lookup for exact matches (no parameters)
        if (isset($this->exactSystemRoutes[$path])) {
            return $this->invokeHandler($this->exactSystemRoutes[$path], $request, []);
        }
        // Then check parameterized routes
        foreach ($this->paramSystemRoutes as $routePath => $handler) {
            $match = $this->matchSystemRoute($routePath, $path);
            if ($match !== null) {
                return $this->invokeHandler($handler, $request, $match);
            }
        }

        // Exact routes from the content cache
        if (isset($routes['exact'][$path])) {
            return $this->handleExactRoute($routes['exact'][$path], $repository, $request);
        }

        // Preview mode: match unpublished content by URL pattern
        if ($this->hasPreviewAccess($request)) {
            $previewMatch = $this->tryPreviewMatch($path, $request);
            if ($previewMatch !== null) {
                return $previewMatch;
            }
        }

        // Taxonomy index and term pages
        foreach ($routes['taxonomy'] ?? [] as $taxName => $taxRoute) {
            $base = rtrim($taxRoute['base'], '/');

            // Exact match to taxonomy base (index of all terms)
            if ($path === $base) {
                return $this->handleTaxonomyIndex($taxName, $repository->terms($taxName));
            }

            // Match term under taxonomy base
            if (str_starts_with($path, $base . '/')) {
                $termPath = substr($path, strlen($base) + 1);
                return $this->handleTaxonomyTerm($taxName, $termPath, $repository, $request);
            }
        }

        // No match
        return null;
    }

    /**
     * Normalize path for matching.
     */
    private function normalizePath(string $path): string
    {
        // Always compare without trailing slash (except for root)
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Match a system route pattern against a path.
     *
     * Supports {param} placeholders.
     * Returns array of matched params on success, null on failure.
     */
    private function matchSystemRoute(string $pattern, string $path): ?array
    {
        // Exact match (no placeholders)
        if (!str_contains($pattern, '{')) {
            return $pattern === $path ? [] : null;
        }

        // Convert {param} to regex
        $regex = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Filter to only named captures
            return array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Check for trailing slash redirect.
     */
    private function checkTrailingSlash(Request $request): ?RouteMatch
    {
        $path = $request->path();
        $trailingSlash = (bool) $this->app->config('routing.trailing_slash', false);

        // Root path is always fine
        if ($path === '/') {
            return null;
        }

        // Collapse any run of leading slashes AND backslashes into a single '/'
        // to prevent protocol-relative open redirects. Browsers treat both
        // //evil.com and /\evil.com (and \/evil.com) as http://evil.com, so a
        // redirect Location built from such a path would send visitors off-site.
        $path = preg_replace('#^[/\\\\]+#', '/', $path);

        // Paths with a file extension (e.g. .xml, .txt) should never be redirected
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return null;
        }

        $hasTrailingSlash = str_ends_with($path, '/');

        if ($trailingSlash === $hasTrailingSlash) {
            return null;
        }

        $target = $trailingSlash ? $path . '/' : (rtrim($path, '/') ?: '/');

        // Keep the query string: dropping it turned /blog/?paged=2 into /blog.
        $query = parse_url($request->uri(), PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $target .= '?' . $query;
        }

        return new RouteMatch(
            type: 'redirect',
            redirectUrl: $target,
            redirectCode: 301
        );
    }

    private function handleExactRoute(array $routeData, Repository $repository, Request $request): ?RouteMatch
    {
        $type = $routeData['type'] ?? 'single';

        if ($type === 'single') {
            // Use file path for lookup (more reliable for hierarchical content)
            if (!isset($routeData['file'])) {
                return null;
            }

            // Parse the single file directly from its route entry. This avoids
            // loading the full content index (with every item body) into memory
            // just to render one page.
            $item = $repository->getByFile(
                $routeData['file'],
                $routeData['content_type'] ?? '',
                $routeData['content_key'] ?? null
            );

            if ($item === null) {
                return null;
            }

            // Check preview access for non-published content
            // Unlisted items are accessible via direct URL without token
            if (!$item->isPublished() && !$item->isUnlisted() && !$this->hasPreviewAccess($request)) {
                return null;
            }

            return new RouteMatch(
                type: 'single',
                contentItem: $item,
                template: $routeData['template'] ?? 'single.php',
                params: ['content_type' => $routeData['content_type']]
            );
        }

        if ($type === 'archive') {
            $query = $this->app->query()
                ->type($routeData['content_type'])
                ->published();

            // Apply content type's configured sorting
            $contentType = $this->app->contentTypes()[$routeData['content_type']] ?? [];
            $sorting = $contentType['sorting'] ?? 'date_desc';
            $query = match ($sorting) {
                'date_asc' => $query->orderBy('date', 'asc'),
                'title' => $query->orderBy('title', 'asc'),
                'manual' => $query->orderBy('order', 'asc'),
                default => $query, // date_desc is the Query default
            };

            // The route fixes the content type; a visitor's ?type= must not
            // turn /blog into a listing of some other type.
            $params = $request->query();
            unset($params['type']);
            $query = $query->fromParams($params);

            if ($this->isBeyondLastPage($query)) {
                return null;
            }

            return new RouteMatch(
                type: 'archive',
                query: $query,
                template: $routeData['template'] ?? 'archive.php',
                params: ['content_type' => $routeData['content_type']]
            );
        }

        return null;
    }

    private function handleTaxonomyIndex(string $taxonomy, array $terms): RouteMatch
    {
        return new RouteMatch(
            type: 'taxonomy_index',
            taxonomy: [
                'name' => $taxonomy,
                'terms' => $terms,
            ],
            template: 'taxonomy-index.php'
        );
    }

    private function handleTaxonomyTerm(string $taxonomy, string $termPath, Repository $repository, Request $request): ?RouteMatch
    {
        $term = $repository->term($taxonomy, $termPath);

        if ($term === null) {
            return null;
        }

        // Build query for items with this term
        $query = $this->app->query()
            ->published()
            ->whereTax($taxonomy, $termPath)
            ->fromParams($request->query());

        if ($this->isBeyondLastPage($query)) {
            return null;
        }

        return new RouteMatch(
            type: 'taxonomy',
            query: $query,
            taxonomy: [
                'name' => $taxonomy,
                'term' => $term,
            ],
            template: 'taxonomy.php'
        );
    }

    /**
     * Is this listing paged past its last real page?
     *
     * Without this, ?paged accepts anything up to Query::MAX_PAGE and every
     * value renders an empty listing: an unbounded supply of requests that
     * each scan the index and can never be cached. Page 1 stays valid even for
     * an empty archive.
     *
     * The query memoises its results, so counting here doesn't cost the
     * template a second pass over the same instance.
     */
    private function isBeyondLastPage(Query $query): bool
    {
        return $query->currentPage() > 1 && $query->currentPage() > $query->totalPages();
    }

    private function invokeHandler(callable $handler, Request $request, array $params = []): ?RouteMatch
    {
        $result = $handler($request, $params);

        if ($result instanceof RouteMatch) {
            return $result;
        }

        // Handle direct Response objects from plugins
        if ($result instanceof \Ava\Http\Response) {
            return new RouteMatch(
                type: 'plugin',
                template: '__raw__',
                params: ['response' => $result]
            );
        }

        return null;
    }

    /**
     * Try to match a preview request against content type URL patterns.
     * 
     * This allows previewing draft content that isn't in the routes cache.
     */
    private function tryPreviewMatch(string $path, Request $request): ?RouteMatch
    {
        // Load content_types directly from file (not in main config)
        $contentTypesFile = $this->app->path('app/config/content_types.php');
        if (!file_exists($contentTypesFile)) {
            return null;
        }
        $contentTypes = require $contentTypesFile;
        $repository = $this->app->repository();

        foreach ($contentTypes as $typeName => $typeConfig) {
            $urlConfig = $typeConfig['url'] ?? [];
            $pattern = $urlConfig['pattern'] ?? '/' . $typeName . '/{slug}';

            // Convert pattern to regex
            $regex = preg_replace('/\{slug\}/', '([^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $matches)) {
                $slug = $matches[1] ?? null;
                if ($slug === null) {
                    continue;
                }

                // Try to get the content item (including drafts)
                $item = $repository->get($typeName, $slug);
                if ($item !== null) {
                    return new RouteMatch(
                        type: 'single',
                        contentItem: $item,
                        template: $item->template() ?? $typeConfig['templates']['single'] ?? 'single.php',
                        params: ['content_type' => $typeName]
                    );
                }
            }
        }

        return null;
    }

    /**
     * Check if request has preview access.
     * 
     * Validates the preview token using timing-safe comparison.
     */
    private function hasPreviewAccess(Request $request): bool
    {
        if (!$request->queryString('preview')) {
            return false;
        }

        $token = $request->queryString('token');
        if (!$token) {
            return false;
        }

        $expectedToken = $this->app->config('security.preview_token');

        // Reject if no token configured
        if ($expectedToken === null || $expectedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $token);
    }

    /**
     * Generate URL for a content item.
     * 
     * Uses O(1) reverse lookup index built during cache rebuild.
     */
    public function urlFor(string $type, string $slug): ?string
    {
        $repository = $this->app->repository();
        $routes = $repository->routes();

        // Use reverse lookup for O(1) performance (vs O(n) linear scan)
        // For hierarchical content, $slug is the path-based content key
        // (for example, "about/team"). Pattern content still uses its slug.
        $url = $routes['reverse'][$type . ':' . $slug] ?? null;

        return $url !== null ? $this->applyTrailingSlash($url) : null;
    }

    /**
     * Generate URL for a taxonomy term.
     */
    public function urlForTerm(string $taxonomy, string $term): ?string
    {
        $repository = $this->app->repository();
        $routes = $repository->routes();

        $taxRoute = $routes['taxonomy'][$taxonomy] ?? null;
        if ($taxRoute === null) {
            return null;
        }

        $base = rtrim($taxRoute['base'], '/');
        return $this->applyTrailingSlash($base . '/' . $term);
    }

    /**
     * Apply trailing slash preference to a URL path.
     */
    private function applyTrailingSlash(string $url): string
    {
        if ($url === '/') {
            return $url;
        }

        $trailingSlash = $this->app->config('routing.trailing_slash', false);

        if ($trailingSlash && !str_ends_with($url, '/')) {
            return $url . '/';
        }

        if (!$trailingSlash && str_ends_with($url, '/')) {
            return rtrim($url, '/');
        }

        return $url;
    }
}
