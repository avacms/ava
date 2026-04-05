<?php

declare(strict_types=1);

namespace Ava\Routing;

use Ava\Application;
use Ava\Content\Repository;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

/**
 * Router
 *
 * Matches incoming requests to routes.
 *
 * Matching order:
 * 1. Hook interception (router.before_match filter)
 * 2. Trailing slash redirect (canonical URL enforcement)
 * 3. Redirects (from redirect_from frontmatter)
 * 4. System routes (registered at runtime via addRoute)
 * 5. Exact routes (from content cache)
 * 6. Preview mode (for draft content with valid token)
 * 7. Prefix routes (registered via addPrefixRoute)
 * 8. Taxonomy routes (archive and term pages)
 * 9. 404 (no match)
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

    /**
     * Match a request to a route.
     */
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

        // 1. Check prefix routes first — these serve non-content resources (assets, APIs)
        // and must not be subject to content URL trailing slash enforcement.
        foreach ($this->prefixRoutes as $prefix => $handler) {
            if (str_starts_with($path, $prefix)) {
                return $this->invokeHandler($handler, $request);
            }
        }

        // 2. Check for trailing slash redirect (content routes only)
        $redirectMatch = $this->checkTrailingSlash($request);
        if ($redirectMatch !== null) {
            return $redirectMatch;
        }

        // 3. Check redirects
        if (isset($routes['redirects'][$path])) {
            $redirect = $routes['redirects'][$path];
            return new RouteMatch(
                type: 'redirect',
                redirectUrl: $redirect['to'],
                redirectCode: $redirect['code'] ?? 301
            );
        }

        // 4. Check system routes (registered at runtime)
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

        // 5. Check exact routes (from cache)
        if (isset($routes['exact'][$path])) {
            return $this->handleExactRoute($routes['exact'][$path], $repository, $request);
        }

        // 5b. Preview mode: try to match unpublished content by URL pattern
        if ($this->hasPreviewAccess($request)) {
            $previewMatch = $this->tryPreviewMatch($path, $request);
            if ($previewMatch !== null) {
                return $previewMatch;
            }
        }

        // 6. Check taxonomy routes
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

        // 7. No match — 404
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
        $trailingSlash = $this->app->config('routing.trailing_slash', false);

        // Root path is always fine
        if ($path === '/') {
            return null;
        }

        // Normalize leading double-slashes to prevent protocol-relative open redirect
        // (e.g., //evil.com/ would be interpreted by browsers as http://evil.com/)
        if (str_starts_with($path, '//')) {
            $path = '/' . ltrim($path, '/');
        }

        $hasTrailingSlash = str_ends_with($path, '/');

        if ($trailingSlash && !$hasTrailingSlash) {
            // Should have trailing slash, doesn't
            return new RouteMatch(
                type: 'redirect',
                redirectUrl: $path . '/',
                redirectCode: 301
            );
        }

        if (!$trailingSlash && $hasTrailingSlash) {
            // Should not have trailing slash, does
            return new RouteMatch(
                type: 'redirect',
                redirectUrl: rtrim($path, '/'),
                redirectCode: 301
            );
        }

        return null;
    }

    /**
     * Handle an exact route match.
     */
    private function handleExactRoute(array $routeData, Repository $repository, Request $request): ?RouteMatch
    {
        $type = $routeData['type'] ?? 'single';

        if ($type === 'single') {
            // Use file path for lookup (more reliable for hierarchical content)
            if (!isset($routeData['file'])) {
                return null;
            }

            $item = $repository->getByPath($routeData['file']);

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

            $query = $query->fromParams($request->query());

            return new RouteMatch(
                type: 'archive',
                query: $query,
                template: $routeData['template'] ?? 'archive.php',
                params: ['content_type' => $routeData['content_type']]
            );
        }

        return null;
    }

    /**
     * Handle taxonomy index route.
     */
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

    /**
     * Handle taxonomy term route.
     */
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
     * Invoke a route handler.
     */
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
        if (!$request->query('preview')) {
            return false;
        }

        $token = $request->query('token');
        if (!$token || !is_string($token)) {
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
        return $routes['reverse'][$type . ':' . $slug] ?? null;
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
        return $base . '/' . $term;
    }
}
