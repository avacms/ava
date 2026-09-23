<?php

declare(strict_types=1);

namespace Ava\Routing;

use Ava\Application;
use Ava\Content\Query;
use Ava\Content\Repository;
use Ava\Content\Terms;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Http\UrlPath;
use Ava\Plugins\Hooks;

/**
 * Matches incoming requests to routes, in this order:
 *
 * 1. Canonical encoding redirect (/%61bout -> /about, lowercase hex -> upper)
 * 2. Hook interception (router.before_match filter)
 * 3. Prefix routes (addPrefixRoute) — before trailing-slash enforcement,
 *    which applies to content URLs only
 * 4. Trailing slash redirect (canonical URL enforcement)
 * 5. Redirects (from redirect_from frontmatter)
 * 6. System routes (addRoute), exact then parameterised
 * 7. Exact routes (from the content index)
 * 8. Preview (unpublished content, with a valid preview link)
 * 9. Taxonomy routes (index and term pages)
 * 10. 404
 *
 * Paths are matched decoded ("/café"), so non-ASCII URLs work.
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

    private ?PreviewLinks $previewLinks = null;

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

    public function previewLinks(): PreviewLinks
    {
        return $this->previewLinks ??= new PreviewLinks($this->app->config('security.preview_token'));
    }

    public function match(Request $request): ?RouteMatch
    {
        // One spelling per URL: otherwise every percent-encoding variant of
        // a page is a separate page (and a separate webpage-cache entry).
        $canonical = UrlPath::canonical($request->path());
        if ($canonical !== $request->path() && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return new RouteMatch(
                type: 'redirect',
                // Exactly one leading slash: "//host" would leave the site.
                redirectUrl: $this->withQuery($request, '/' . ltrim($canonical, '/')),
                redirectCode: 301
            );
        }

        $path = $this->normalizePath(UrlPath::decode($request->path()));
        $repository = $this->app->repository();

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
        $redirect = $repository->redirectRoute($path);
        if ($redirect !== null) {
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
            $params = $this->matchSystemRoute($routePath, $path);
            if ($params !== null) {
                return $this->invokeHandler($handler, $request, $params);
            }
        }

        // Exact routes from the content index
        $route = $repository->exactRoute($path);
        if ($route !== null) {
            return $this->handleExactRoute($route, $repository, $request, $path);
        }

        // Unpublished content, at the URL it will be published at
        if ($this->hasPreviewAccess($request, $path)) {
            $route = $repository->previewRoute($path);
            if ($route !== null) {
                return $this->handleExactRoute($route, $repository, $request, $path);
            }
        }

        // Taxonomy index and term pages
        foreach ($repository->taxonomyRoutes() as $taxName => $taxRoute) {
            $base = rtrim($taxRoute['base'], '/');

            if ($path === $base) {
                return $this->handleTaxonomyIndex($taxName, $repository->terms($taxName));
            }

            if (str_starts_with($path, $base . '/')) {
                return $this->handleTaxonomyTerm($taxName, $base, substr($path, strlen($base) + 1), $repository, $request);
            }
        }

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

        return $path === '' ? '/' : $path;
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

        // Convert {param} to regex; literal parts are quoted so a "." in
        // "/feed/{type}.xml" matches only a dot.
        $parts = preg_split('/(\{[^}]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $name) === 1
                ? '(?P<' . $name[1] . '>[^/]+)'
                : preg_quote($part, '#');
        }

        if (preg_match('#^' . $regex . '$#u', $path, $matches)) {
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
        return new RouteMatch(
            type: 'redirect',
            redirectUrl: $this->withQuery($request, $target),
            redirectCode: 301
        );
    }

    private function withQuery(Request $request, string $target): string
    {
        $query = parse_url($request->uri(), PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $target . '?' . $query : $target;
    }

    private function handleExactRoute(array $routeData, Repository $repository, Request $request, ?string $path = null): ?RouteMatch
    {
        $type = $routeData['type'] ?? 'single';

        if ($type === 'single') {
            if (!isset($routeData['file'])) {
                return null;
            }

            // Parse the single file named by the route: rendering one page
            // must not load the whole content index.
            $item = $repository->getByFile(
                $routeData['file'],
                $routeData['content_type'] ?? '',
                $routeData['content_key'] ?? null
            );

            if ($item === null) {
                return null;
            }

            // The file is re-read on every request, so its status may be
            // newer than the index's. Unlisted items are reachable by URL.
            if (!$item->isPublished() && !$item->isUnlisted()
                && !$this->hasPreviewAccess($request, $path ?? $this->normalizePath(UrlPath::decode($request->path())))
            ) {
                return null;
            }

            return new RouteMatch(
                type: 'single',
                contentItem: $item,
                template: $item->template() ?? $routeData['template'] ?? 'single.php',
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

    private function handleTaxonomyTerm(
        string $taxonomy,
        string $base,
        string $termPath,
        Repository $repository,
        Request $request
    ): ?RouteMatch {
        $slug = Terms::slug($termPath);
        $term = $slug === '' ? null : $repository->term($taxonomy, $slug);
        if ($term === null) {
            return null;
        }

        // /tag/Web-Dev and /tag/web-dev are one page; send visitors (and
        // search engines) to the canonical spelling.
        if ($slug !== $termPath) {
            return new RouteMatch(
                type: 'redirect',
                redirectUrl: $this->withQuery($request, $this->applyTrailingSlash($base . '/' . $slug)),
                redirectCode: 301
            );
        }

        $query = $this->app->query()
            ->published()
            ->whereTax($taxonomy, $slug)
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
        if ($result instanceof Response) {
            return new RouteMatch(
                type: 'plugin',
                template: '__raw__',
                params: ['response' => $result]
            );
        }

        return null;
    }

    /**
     * May this request see unpublished content at $path?
     */
    private function hasPreviewAccess(Request $request, string $path): bool
    {
        return $this->previewLinks()->allows($request, $path);
    }

    /**
     * Generate URL for a content item (O(1) reverse lookup).
     *
     * For hierarchical content, $slug is the path-based content key
     * (for example "about/team"). Pattern content uses its slug.
     */
    public function urlFor(string $type, string $slug): ?string
    {
        $url = $this->app->repository()->reverseUrl($type, $slug);

        return $url !== null ? $this->applyTrailingSlash($url) : null;
    }

    /**
     * Generate URL for a taxonomy term. Any spelling of the term works
     * ("Web Dev" and "web-dev" give the same URL).
     */
    public function urlForTerm(string $taxonomy, string $term): ?string
    {
        $taxRoute = $this->app->repository()->taxonomyRoutes()[$taxonomy] ?? null;
        $slug = Terms::slug($term);
        if ($taxRoute === null || $slug === '') {
            return null;
        }

        return $this->applyTrailingSlash(rtrim($taxRoute['base'], '/') . '/' . $slug);
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
