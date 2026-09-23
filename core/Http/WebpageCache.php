<?php

declare(strict_types=1);

namespace Ava\Http;

use Ava\Application;
use Ava\Support\AtomicFile;

/**
 * Shared cache for anonymous HTML responses.
 * Each JSON entry contains its request identity, response headers, and body.
 */
final class WebpageCache
{
    /**
     * Highest ?paged value that may create an entry.
     *
     * The router already 404s past the last real page, so this only bounds how
     * far a misconfigured archive could grow the cache directory.
     */
    private const int MAX_CACHED_PAGE = 10_000;

    private string $cachePath;

    public function __construct(private Application $app)
    {
        $this->cachePath = $app->configPath('storage') . '/cache/pages';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->app->config('webpage_cache.enabled', false);
    }

    public function isCacheable(Request $request): bool
    {
        if (!$this->isEnabled() || $request->method() !== 'GET') {
            return false;
        }

        // Never share authenticated or session-dependent output. Request
        // Cache-Control/Pragma are deliberately not honoured: anyone could send
        // them, so obeying them is a one-header switch for turning the cache
        // off site-wide. RFC 9111 §5.2.1.4 lets a shared cache ignore a
        // client's no-cache, and CDNs do.
        if ($request->header('Authorization') !== null || $this->carriesPhpSession($request)) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }

        if ($this->cacheableQuery($request) === null) {
            return false;
        }

        // Only the configured site hostname (and any explicit aliases) may create
        // entries. Otherwise arbitrary Host headers could each spawn a near-identical
        // copy of every page and grow the cache directory without bound.
        if (!$this->hostIsAllowed($request)) {
            return false;
        }

        foreach ($this->app->config('webpage_cache.exclude', []) as $pattern) {
            if ($this->matchesPattern($request->path(), $pattern)) {
                return false;
            }
        }

        return true;
    }

    public function get(Request $request): ?Response
    {
        if (!$this->isCacheable($request)) {
            return null;
        }

        $file = $this->getCacheFilePath($request);
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return null;
        }

        $age = max(0, time() - $mtime);
        $ttl = $this->app->config('webpage_cache.ttl');
        if ($ttl !== null && $age >= $ttl) {
            @unlink($file);
            return null;
        }

        $entry = $this->readEntry($file);
        if ($entry === null || $entry['identity'] !== $this->identity($request)) {
            return null;
        }

        try {
            $response = new Response($entry['body'], 200, $entry['headers']);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!$this->isPublicResponse($response)) {
            return null;
        }

        return $response
            ->withHeader('X-Page-Cache', 'HIT')
            ->withHeader('X-Cache-Age', (string) $age);
    }

    public function put(Request $request, Response $response, ?bool $contentCacheOverride = null): bool
    {
        if ($contentCacheOverride === false || !$this->isCacheable($request) || !$this->isPublicResponse($response)) {
            return false;
        }

        // A theme may call header() or setcookie() instead of using Response,
        // so check what PHP has queued as well. Inspected only, never stored:
        // those headers belong to this one request (a template's one-off
        // header(), the server's X-Powered-By), and persisting them would
        // replay them to every later visitor.
        $nativeHeaders = [];
        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $nativeHeaders[trim($parts[0])] = trim($parts[1]);
            }
        }
        try {
            $nativeResponse = new Response('', 200, $nativeHeaders);
        } catch (\InvalidArgumentException) {
            return false;
        }
        if (!$this->isPublicResponse($nativeResponse)) {
            return false;
        }

        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0755, true) && !is_dir($this->cachePath)) {
            return false;
        }

        $content = $response->content();
        if ($this->app->config('generator_comment', false)) {
            $content = $this->addCacheComment($content, date('Y-m-d H:i:s'));
        }

        try {
            $entry = json_encode([
                'identity' => $this->identity($request),
                'path' => $request->path(),
                'headers' => $response->headers(),
                'body' => $content,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return false;
        }

        return AtomicFile::write($this->getCacheFilePath($request), $entry);
    }

    private function isPublicResponse(Response $response): bool
    {
        if ($response->status() !== 200) {
            return false;
        }

        // Explicit HTTP cache policy is left to the browser/proxy, rather than
        // silently replacing its freshness/revalidation rules with our own TTL.
        foreach (['Set-Cookie', 'Vary', 'Cache-Control', 'Pragma', 'Expires', 'Content-Length', 'Content-Encoding', 'Content-Range', 'Transfer-Encoding'] as $header) {
            if ($response->header($header) !== null) {
                return false;
            }
        }

        $contentType = $response->header('Content-Type');
        return $contentType === null
            || self::mediaType($contentType) === 'text/html'
            || self::isFeedType($contentType);
    }

    /**
     * XML documents cached like pages: feeds and sitemaps change only when
     * content does, and bots poll them constantly.
     */
    public static function isFeedType(?string $contentType): bool
    {
        return $contentType !== null && in_array(self::mediaType($contentType), [
            'application/xml', 'text/xml', 'application/rss+xml', 'application/atom+xml', 'text/xsl',
        ], true);
    }

    private static function mediaType(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    public function clear(): int
    {
        $count = 0;
        foreach (glob($this->cachePath . '/*.json') ?: [] as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function clearPattern(string $pattern): int
    {
        $count = 0;
        foreach (glob($this->cachePath . '/*.json') ?: [] as $file) {
            $entry = $this->readEntry($file);
            if ($entry !== null && $this->matchesPattern($entry['path'], $pattern) && unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function stats(): array
    {
        $files = glob($this->cachePath . '/*.json') ?: [];
        $size = 0;
        $oldest = null;
        $newest = null;
        foreach ($files as $file) {
            $size += filesize($file);
            $mtime = filemtime($file);
            $oldest = $oldest === null ? $mtime : min($oldest, $mtime);
            $newest = $newest === null ? $mtime : max($newest, $mtime);
        }

        return [
            'enabled' => $this->isEnabled(),
            'ttl' => $this->app->config('webpage_cache.ttl'),
            'count' => count($files),
            'size' => $size,
            'oldest' => $oldest === null ? null : date('Y-m-d H:i:s', $oldest),
            'newest' => $newest === null ? null : date('Y-m-d H:i:s', $newest),
        ];
    }

    private function readEntry(string $file): ?array
    {
        $contents = @file_get_contents($file);
        $entry = $contents === false ? null : json_decode($contents, true);
        if (!is_array($entry)
            || !is_string($entry['identity'] ?? null)
            || !is_string($entry['path'] ?? null)
            || !is_array($entry['headers'] ?? null)
            || !is_string($entry['body'] ?? null)
        ) {
            return null;
        }
        return $entry;
    }

    /**
     * Is this request's Host header a hostname we serve the canonical site for?
     *
     * The set is the authority (host, plus port when base_url states one) from
     * site.base_url, plus any hostnames listed in webpage_cache.hosts. When no
     * canonical host can be derived, enforcement is skipped and host stays part
     * of the cache key only.
     */
    private function hostIsAllowed(Request $request): bool
    {
        $allowed = [];

        $baseUrl = trim((string) $this->app->config('site.base_url', ''));
        if ($baseUrl !== '') {
            // Tolerate a scheme-less base_url ("example.com") by forcing authority parsing.
            $parsable = preg_match('#^[a-z][a-z0-9+.-]*://#i', $baseUrl) ? $baseUrl : '//' . $baseUrl;
            $baseHost = parse_url($parsable, PHP_URL_HOST);
            if (is_string($baseHost) && $baseHost !== '') {
                $basePort = parse_url($parsable, PHP_URL_PORT);
                $allowed[] = strtolower($baseHost) . ($basePort !== null ? ':' . $basePort : '');
            }
        }

        foreach ((array) $this->app->config('webpage_cache.hosts', []) as $host) {
            if (is_string($host) && $host !== '') {
                $allowed[] = strtolower($host);
            }
        }

        if ($allowed === []) {
            return true;
        }

        return in_array(strtolower($request->host()), $allowed, true);
    }

    /**
     * Does this request carry PHP's own session cookie?
     *
     * On a cache read nothing has rendered yet (in manual index mode the theme
     * has not even loaded), so isCacheable()'s session_status() check sees
     * nothing. This cookie is the only signal available that early.
     *
     * Just this one name, matched exactly. Ava reads no cookies itself, so
     * every other cookie belongs to something this class can't interpret, and
     * bypassing on any cookie means analytics alone costs every returning
     * visitor the cache. Genuinely per-visitor output declares itself instead:
     * isPublicResponse() refuses to store anything carrying Set-Cookie, Vary
     * or Cache-Control, and webpage_cache.exclude also applies to reads.
     */
    private function carriesPhpSession(Request $request): bool
    {
        $header = $request->header('Cookie');
        if ($header === null) {
            return false;
        }

        $sessionName = session_name() ?: 'PHPSESSID';

        foreach (explode(';', $header) as $cookie) {
            if (trim(explode('=', $cookie, 2)[0]) === $sessionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * The part of the query string this entry is keyed on, or null if the
     * request may not be cached at all.
     *
     * Only ?paged is keyed. It's the one visitor-facing parameter that
     * legitimately changes a whole page, and refusing it left archive
     * pagination as an unbounded supply of uncached, index-scanning requests.
     * Search terms, per_page and ad-hoc ordering still bypass: their key space
     * is unbounded, so keying them would let a visitor fill the cache
     * directory instead.
     *
     * @return array<string, int>|null
     */
    private function cacheableQuery(Request $request): ?array
    {
        $query = $request->query();

        // Campaign tags never reach the renderer, so tagged URLs share the
        // plain URL's entry rather than duplicating it per campaign.
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);

        if ($query === []) {
            return [];
        }

        if (array_keys($query) !== ['paged']) {
            return null;
        }

        // Canonical decimals only: ?paged=01 and ?paged=+2 render page 2 but
        // would otherwise each claim their own entry.
        $paged = $query['paged'];
        if (!is_string($paged) || preg_match('/^[1-9]\d{0,6}$/D', $paged) !== 1) {
            return null;
        }

        $page = (int) $paged;
        if ($page > self::MAX_CACHED_PAGE) {
            return null;
        }

        // ?paged=1 is the same page as no parameter at all.
        return $page === 1 ? [] : ['paged' => $page];
    }

    private function identity(Request $request): string
    {
        // Length-delimited serialization prevents ambiguous host/path boundaries.
        return hash('sha256', serialize([
            $request->isSecure(),
            strtolower($request->host()),
            $request->path(),
            $this->cacheableQuery($request) ?? [],
        ]));
    }

    private function getCacheFilePath(Request $request): string
    {
        return $this->cachePath . '/' . $this->identity($request) . '.json';
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        $regex = str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/D', $path) === 1;
    }

    private function addCacheComment(string $content, string $timestamp): string
    {
        $comment = "<!-- Page cached: {$timestamp} -->\n";
        if (preg_match('/^<!DOCTYPE[^>]*>/i', $content, $matches)) {
            return $matches[0] . "\n" . $comment . substr($content, strlen($matches[0]));
        }
        return $comment . $content;
    }
}
