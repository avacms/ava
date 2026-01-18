<?php

declare(strict_types=1);

namespace Ava\Http;

use Ava\Application;

/**
 * On-Demand Webpage Cache
 *
 * Caches rendered HTML webpages to disk for ultra-fast serving.
 * Webpages are cached on first request and served directly on subsequent requests.
 */
final class WebpageCache
{
    private Application $app;
    private string $cachePath;
    private bool $enabled;
    private ?int $ttl;
    private array $exclude;
    
    /** @var array<string, string> Compiled regex patterns for exclusion matching */
    private array $excludePatterns = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->cachePath = $app->configPath('storage') . '/cache/pages';
        $this->enabled = (bool) $app->config('webpage_cache.enabled', false);
        $this->ttl = $app->config('webpage_cache.ttl'); // null = forever (until cleared)
        $this->exclude = $app->config('webpage_cache.exclude', []);
        
        // Pre-compile exclusion patterns to regex for faster matching
        foreach ($this->exclude as $pattern) {
            $regex = str_replace(
                ['*', '?'],
                ['.*', '.'],
                preg_quote($pattern, '/')
            );
            $this->excludePatterns[$pattern] = '/^' . $regex . '$/';
        }
    }

    /**
     * Check if webpage caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if a request is cacheable for READING from cache.
     * 
     * This is used to determine if we should serve a cached page.
     * We serve cached pages to everyone, including admins - if the cache
     * file exists, it was valid when generated.
     */
    public function isCacheableForRead(Request $request): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return false;
        }

        // Don't cache if there are query parameters (except allowed ones)
        $query = $request->query();
        unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);
        if (!empty($query)) {
            return false;
        }

        // Don't cache admin paths
        $adminPath = $this->app->config('admin.path', '/admin');
        if (str_starts_with($request->path(), $adminPath)) {
            return false;
        }

        // Check exclusion patterns
        $path = $request->path();
        foreach ($this->exclude as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a request is cacheable for WRITING to cache.
     * 
     * This is more restrictive than read - we don't cache when an admin
     * is logged in because they might be viewing preview/draft content.
     */
    public function isCacheableForWrite(Request $request): bool
    {
        if (!$this->isCacheableForRead($request)) {
            return false;
        }

        // Don't cache if user is logged in (they might see preview/draft content)
        if ($this->isUserLoggedIn()) {
            return false;
        }

        return true;
    }

    /**
     * Check if a request is cacheable.
     * 
     * @deprecated Use isCacheableForRead() or isCacheableForWrite() for clarity
     */
    public function isCacheable(Request $request): bool
    {
        return $this->isCacheableForWrite($request);
    }

    /**
     * Get a cached response for the request.
     */
    public function get(Request $request): ?Response
    {
        if (!$this->isCacheableForRead($request)) {
            return null;
        }

        return $this->getFromFile($request);
    }

    /**
     * Fast path: Get cached response without full cacheability check.
     * 
     * Used by Application::tryCachedResponse() to serve cached pages
     * before full boot. Assumes basic checks already passed.
     */
    public function getWithoutFullCheck(Request $request): ?Response
    {
        // Quick exclusion pattern check
        $path = $request->path();
        foreach ($this->exclude as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return null;
            }
        }

        return $this->getFromFile($request);
    }

    /**
     * Get cached response from file.
     * 
     * Optimized to minimize filesystem calls:
     * - Single stat() via filemtime() instead of file_exists() + filemtime()
     * - Store mtime to avoid double stat() call
     */
    private function getFromFile(Request $request): ?Response
    {
        $cacheFile = $this->getCacheFilePath($request);

        // Get mtime (returns false if file doesn't exist) - single stat() call
        $mtime = @filemtime($cacheFile);
        if ($mtime === false) {
            return null;
        }

        // Check TTL using stored mtime
        $now = time();
        $age = $now - $mtime;
        if ($this->ttl !== null && $age > $this->ttl) {
            @unlink($cacheFile);
            return null;
        }

        // Read file content
        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }

        // Build response with pre-computed age (avoid second stat)
        return Response::html($content)
            ->withHeader('X-Page-Cache', 'HIT')
            ->withHeader('X-Cache-Age', (string) $age);
    }

    /**
     * Store a response in the cache.
     */
    public function put(Request $request, Response $response, ?bool $contentCacheOverride = null): void
    {
        // Check if content explicitly disables caching
        if ($contentCacheOverride === false) {
            return;
        }

        // If content doesn't explicitly enable caching, check if request is cacheable for writing
        if ($contentCacheOverride !== true && !$this->isCacheableForWrite($request)) {
            return;
        }

        // Only cache successful HTML responses
        if ($response->status() !== 200) {
            return;
        }

        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        $cacheFile = $this->getCacheFilePath($request);
        $content = $response->content();

        // Add cache timestamp comment to HTML
        $timestamp = date('Y-m-d H:i:s');
        $content = $this->addCacheComment($content, $timestamp);

        file_put_contents($cacheFile, $content, LOCK_EX);
    }

    /**
     * Clear all cached pages.
     */
    public function clear(): int
    {
        if (!is_dir($this->cachePath)) {
            return 0;
        }

        $count = 0;
        $files = glob($this->cachePath . '/*.html');

        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear cached pages matching a pattern.
     */
    public function clearPattern(string $pattern): int
    {
        if (!is_dir($this->cachePath)) {
            return 0;
        }

        $count = 0;
        $files = glob($this->cachePath . '/*.html');

        foreach ($files as $file) {
            // Read first line to get original URL comment
            $handle = fopen($file, 'r');
            $firstLine = fgets($handle);
            fclose($handle);

            if (preg_match('/<!-- Cached: .+ \| (.+) -->/', $firstLine, $matches)) {
                $url = $matches[1];
                if ($this->matchesPattern($url, $pattern)) {
                    if (unlink($file)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Get cache statistics.
     */
    public function stats(): array
    {
        if (!is_dir($this->cachePath)) {
            return [
                'enabled' => $this->enabled,
                'count' => 0,
                'size' => 0,
                'oldest' => null,
                'newest' => null,
            ];
        }

        $files = glob($this->cachePath . '/*.html');
        $totalSize = 0;
        $oldest = null;
        $newest = null;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $mtime = filemtime($file);

            if ($oldest === null || $mtime < $oldest) {
                $oldest = $mtime;
            }
            if ($newest === null || $mtime > $newest) {
                $newest = $mtime;
            }
        }

        return [
            'enabled' => $this->enabled,
            'ttl' => $this->ttl,
            'count' => count($files),
            'size' => $totalSize,
            'oldest' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest' => $newest ? date('Y-m-d H:i:s', $newest) : null,
        ];
    }

    /**
     * Get the cache file path for a request.
     */
    private function getCacheFilePath(Request $request): string
    {
        $path = $request->path();

        // Create a safe filename from the path
        // Use hash to handle long/complex paths
        $hash = md5($path);
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', trim($path, '/'));
        $safeName = substr($safeName, 0, 50); // Limit length

        // Combine readable name with hash for uniqueness
        $filename = ($safeName ?: 'index') . '_' . substr($hash, 0, 8) . '.html';

        return $this->cachePath . '/' . $filename;
    }

    /**
     * Check if user is logged in as admin.
     */
    private function isUserLoggedIn(): bool
    {
        // Only check if session is already active - don't start one.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return isset($_SESSION['ava_admin_user']);
        }

        // If the admin session cookie exists, be conservative and assume the user might be logged in.
        // We intentionally do NOT start the session here to avoid session fixation and DoS risks.
        return isset($_COOKIE['ava_admin']);
    }

    /**
     * Check if path matches a pattern.
     * Uses pre-compiled regex from constructor for exclusion patterns,
     * falls back to dynamic compilation for other patterns.
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Use pre-compiled pattern if available (for exclusion patterns)
        if (isset($this->excludePatterns[$pattern])) {
            return (bool) preg_match($this->excludePatterns[$pattern], $path);
        }
        
        // Fallback: compile pattern dynamically (for clearPattern usage)
        $regex = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        );

        return (bool) preg_match('/^' . $regex . '$/', $path);
    }

    /**
     * Add cache comments to HTML content (header only, footer is already added by Application).
     */
    private function addCacheComment(string $content, string $timestamp): string
    {
        // Replace the "Rendered" footer comment with "Cached" version
        $content = preg_replace(
            '/<!-- Generated by Ava CMS v[\d.]+ \| Rendered: [^|]+ \| [^-]+ -->/',
            "<!-- Generated by Ava CMS v" . (defined('AVA_VERSION') ? AVA_VERSION : 'dev') . " | Cached: {$timestamp} -->",
            $content
        );

        // Add header comment after DOCTYPE
        $headerComment = "<!-- Page cached: {$timestamp} -->\n";

        if (preg_match('/^<!DOCTYPE[^>]*>/i', $content, $matches)) {
            $content = $matches[0] . "\n" . $headerComment . substr($content, strlen($matches[0]));
        } else {
            $content = $headerComment . $content;
        }

        return $content;
    }
}
