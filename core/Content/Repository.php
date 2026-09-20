<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;
use Ava\Content\Backends\BackendInterface;
use Ava\Content\Backends\ArrayBackend;
use Ava\Content\Backends\SqliteBackend;
use Ava\Plugins\Hooks;
use Ava\Support\SignedCache;

/**
 * Read access to indexed content.
 *
 * Metadata comes from cache; raw content is loaded on demand from files.
 * The backend is 'array' (binary serialized, default) or 'sqlite' (better
 * past ~10k items), set by content_index.backend.
 */
final class Repository
{
    private Application $app;
    private Parser $parser;
    private ?BackendInterface $backend = null;
    private ?string $backendOverride = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->parser = new Parser();
    }

    /**
     * Get the active backend.
     */
    public function backend(): BackendInterface
    {
        if ($this->backend === null) {
            $this->backend = $this->resolveBackend();
        }
        return $this->backend;
    }

    /**
     * Get the backend name (for debugging/status).
     */
    public function backendName(): string
    {
        return $this->backend()->name();
    }

    /**
     * Override the backend (for testing/benchmarking).
     */
    public function setBackendOverride(?string $backend): void
    {
        $this->backendOverride = $backend;
        $this->backend = null;
    }

    /**
     * Resolve which backend to use.
     */
    private function resolveBackend(): BackendInterface
    {
        // Check for explicit override (used by benchmarking)
        if ($this->backendOverride !== null) {
            return $this->createBackend($this->backendOverride);
        }

        // Check config - default is 'array'
        $configBackend = $this->app->config('content_index.backend', 'array');

        $storagePath = $this->app->configPath('storage');
        $contentPath = $this->app->configPath('content');

        if ($configBackend === 'sqlite') {
            $sqlite = new SqliteBackend($storagePath, $contentPath);
            if ($sqlite->isAvailable()) {
                return $sqlite;
            }

            if (!extension_loaded('pdo_sqlite')) {
                throw new \RuntimeException(
                    "SQLite is configured but the pdo_sqlite extension is unavailable. " .
                    "Install it or set content_index.backend to 'array'."
                );
            }

            throw new \RuntimeException(
                'SQLite is configured but its content index is unavailable. Run: php ava rebuild'
            );
        }

        return new ArrayBackend($storagePath, $contentPath);
    }

    /**
     * Create a specific backend.
     */
    private function createBackend(string $name): BackendInterface
    {
        $storagePath = $this->app->configPath('storage');
        $contentPath = $this->app->configPath('content');

        return match ($name) {
            'sqlite' => new SqliteBackend($storagePath, $contentPath),
            'array' => new ArrayBackend($storagePath, $contentPath),
            default => new ArrayBackend($storagePath, $contentPath),
        };
    }

    /**
     * Load raw content from a file and return the Item with content.
     */
    private function hydrateItem(array $data): Item
    {
        $filePath = $data['file_path'] ?? '';
        $rawContent = '';

        // Load raw content from the file if the path exists
        if ($filePath !== '' && file_exists($filePath)) {
            $item = $this->parser->parseFile($filePath, $data['type'] ?? '');
            $rawContent = $item->rawContent();
        }

        return Item::fromArray($data, $rawContent);
    }

    // === Content Retrieval ===

    /**
     * Get a content item by type and content key.
     * 
     * For hierarchical types (like pages), the key is the path (e.g., 'about/team').
     * For pattern types (like posts), the key is the slug (e.g., 'hello-world').
     * 
     * Uses the backend's optimized lookup, then parses the file directly
     * for full content. This avoids loading the full content index.
     *
     * NOTE: returns items of any status, including drafts, because preview
     * mode is built on it. Callers rendering to anonymous visitors must check
     * isPublished() themselves, as the router does.
     */
    public function get(string $type, string $key): ?Item
    {
        $data = $this->backend()->getBySlug($type, $key);

        if ($data === null) {
            return null;
        }

        // Parse the file directly for full content
        $resolved = $this->resolveInsideContentRoot((string) ($data['file_path'] ?? ''));
        if ($resolved === null) {
            return null;
        }

        $item = $this->parser->parseFile($resolved, $type)->withContentKey($key);

        // Allow hooks to modify the loaded item
        return Hooks::apply('content.loaded', $item);
    }

    /**
     * Get a content item directly from its content-relative file path.
     *
     * This is the fast path for rendering a single page: the route table
     * (routes.bin) already carries the file path, so we can parse that one
     * file directly instead of loading the entire content index into memory.
     */
    public function getByFile(string $relativeFile, string $type, ?string $contentKey = null): ?Item
    {
        if ($relativeFile === '') {
            return null;
        }

        $resolved = $this->resolveInsideContentRoot(
            $this->app->configPath('content') . '/' . $relativeFile
        );
        if ($resolved === null) {
            return null;
        }

        $item = $this->parser->parseFile($resolved, $type);
        if ($contentKey !== null) {
            $item = $item->withContentKey($contentKey);
        }

        // Allow hooks to modify the loaded item
        return Hooks::apply('content.loaded', $item);
    }

    /**
     * Resolve a content path and require it to live inside the content root.
     *
     * SECURITY: defence in depth. Index entries are the only source of these
     * paths today, but a stale or tampered index must not be able to turn a
     * lookup into an arbitrary file read.
     */
    private function resolveInsideContentRoot(string $candidate): ?string
    {
        // realpath('') resolves to the working directory on some platforms.
        if ($candidate === '') {
            return null;
        }

        $contentRoot = realpath($this->app->configPath('content'));
        $resolved = realpath($candidate);
        if ($contentRoot === false || $resolved === false) {
            return null;
        }

        $prefix = $contentRoot . DIRECTORY_SEPARATOR;
        if ($resolved !== $contentRoot && !str_starts_with($resolved, $prefix)) {
            return null;
        }

        return $resolved;
    }

    /**
     * Get a content item by type and content key with full index data.
     * 
     * Returns the cached item data from the backend.
     * Use this when you need access to all indexed metadata without re-parsing.
     */
    public function getFromIndex(string $type, string $key): ?Item
    {
        $data = $this->backend()->getBySlug($type, $key);

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get a content item by ID.
     */
    public function getById(string $id): ?Item
    {
        $data = $this->backend()->getById($id);

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get a content item by file path.
     */
    public function getByPath(string $relativePath): ?Item
    {
        $data = $this->backend()->getByPath($relativePath);

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get all items of a type (with full content loaded).
     * Warning: This reads files from disk. Use allMeta() when content is not needed.
     *
     * @return array<Item>
     */
    public function all(string $type): array
    {
        $items = $this->backend()->allRaw($type);

        return array_map(fn($data) => $this->hydrateItem($data), $items);
    }

    /**
     * Get all items of a type (metadata only, no file I/O).
     * Use this for listings, stats, and when raw content is not needed.
     *
     * @return array<Item>
     */
    public function allMeta(string $type): array
    {
        $items = $this->backend()->allRaw($type);

        return array_map(fn($data) => Item::fromArray($data, ''), $items);
    }

    /**
     * Get raw index data for a type (for optimized queries).
     * Returns array of arrays, not Item objects.
     *
     * @return array<array>
     */
    public function allRaw(string $type): array
    {
        return $this->backend()->allRaw($type);
    }

    /**
     * Get published items of a type (with full content loaded).
     *
     * @return array<Item>
     */
    public function published(string $type): array
    {
        return array_filter(
            $this->all($type),
            fn(Item $item) => $item->isPublished()
        );
    }

    /**
     * Get published items of a type (metadata only, no file I/O).
     *
     * @return array<Item>
     */
    public function publishedMeta(string $type): array
    {
        return array_filter(
            $this->allMeta($type),
            fn(Item $item) => $item->isPublished()
        );
    }

    /**
     * Get recent published items across all types (metadata only, no file I/O).
     * Optimized to avoid creating Item objects until after sorting/limiting.
     *
     * Published only: this reads like a listing helper, so it must not hand
     * drafts to a caller that never thought about status. Use allMeta() for
     * every status.
     *
     * @return array<Item>
     */
    public function recentMeta(int $limit = 5): array
    {
        // Collect raw data from all types using backend
        $allData = [];
        foreach ($this->backend()->types() as $type) {
            foreach ($this->backend()->allRaw($type) as $data) {
                if (($data['status'] ?? 'draft') !== 'published') {
                    continue;
                }
                $allData[] = $data;
            }
        }

        // Sort by date descending (using raw data, no Item objects)
        usort($allData, function(array $a, array $b) {
            $aDate = $a['date'] ?? null;
            $bDate = $b['date'] ?? null;
            if (!$aDate && !$bDate) return 0;
            if (!$aDate) return 1;
            if (!$bDate) return -1;
            // Compare as strings (Y-m-d format sorts correctly)
            return strcmp($bDate, $aDate);
        });

        // Only create Item objects for the items we need
        $result = [];
        foreach (array_slice($allData, 0, $limit) as $data) {
            $result[] = Item::fromArray($data, '');
        }

        return $result;
    }

    /**
     * Check if a content item exists.
     */
    public function exists(string $type, string $slug): bool
    {
        return $this->backend()->exists($type, $slug);
    }

    /**
     * Get content types that have items.
     *
     * @return array<string>
     */
    public function types(): array
    {
        return $this->backend()->types();
    }

    /**
     * Get count of items by type.
     * Uses the backend directly - no file I/O required.
     */
    public function count(string $type, ?string $status = null): int
    {
        return $this->backend()->count($type, $status);
    }

    // === Taxonomy Retrieval ===

    public function terms(string $taxonomy): array
    {
        return $this->backend()->terms($taxonomy);
    }

    /**
     * Get a specific term.
     */
    public function term(string $taxonomy, string $slug): ?array
    {
        return $this->backend()->term($taxonomy, $slug);
    }

    /**
     * Get content items with a specific term.
     *
     * @return array<Item>
     */
    public function itemsWithTerm(string $taxonomy, string $termSlug): array
    {
        $term = $this->term($taxonomy, $termSlug);
        if ($term === null) {
            return [];
        }

        $items = [];
        foreach ($term['items'] ?? [] as $key) {
            [$type, $slug] = explode(':', $key, 2);
            $item = $this->get($type, $slug);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get all taxonomy names.
     *
     * @return array<string>
     */
    public function taxonomies(): array
    {
        return $this->backend()->taxonomies();
    }

    // === Routes ===

    /**
     * Get the routes index.
     */
    public function routes(): array
    {
        return $this->backend()->routes();
    }

    /**
     * Find route data for a path.
     */
    public function routeFor(string $path): ?array
    {
        $routes = $this->backend()->routes();

        // Check redirects first
        if (isset($routes['redirects'][$path])) {
            return [
                'type' => 'redirect',
                'to' => $routes['redirects'][$path]['to'],
                'code' => $routes['redirects'][$path]['code'] ?? 301,
            ];
        }

        // Check exact routes
        if (isset($routes['exact'][$path])) {
            return $routes['exact'][$path];
        }

        // Check taxonomy routes
        foreach ($routes['taxonomy'] ?? [] as $taxName => $taxRoute) {
            $base = rtrim($taxRoute['base'], '/');
            if (str_starts_with($path, $base . '/')) {
                $termPath = substr($path, strlen($base) + 1);
                return [
                    'type' => 'taxonomy',
                    'taxonomy' => $taxName,
                    'term' => $termPath,
                    'template' => 'taxonomy.php',
                ];
            }
        }

        return null;
    }

    // === Recent Cache ===

    /**
     * Get recent items from the lightweight cache.
     *
     * Much faster than loading the full content index for simple archive
     * queries (no filters, first 20 pages).
     *
     * @return array{items: array, total: int, from_cache: bool}
     */
    public function getRecentItems(string $type, int $page = 1, int $perPage = 10): array
    {
        $result = $this->backend()->getRecentItems($type, $page, $perPage);
        return [
            'items' => $result['items'],
            'total' => $result['total'],
            'from_cache' => true,
        ];
    }

    /**
     * Check if a query can be served from the recent cache.
     */
    public function canUseRecentCache(string $type, int $page, int $perPage, array $filters = []): bool
    {
        if (!empty($filters)) {
            return false;
        }
        
        return $this->backend()->canUseFastCache($type, $page, $perPage);
    }

    // === Pre-rendered HTML Cache ===

    /** @var array<string, string>|null */
    private ?array $htmlCache = null;

    /**
     * Get pre-rendered HTML for a content item.
     *
     * Null if pre-rendering is disabled or the item isn't cached.
     */
    public function getPrerenderedHtml(string $type, string $key): ?string
    {
        if ($this->htmlCache === null) {
            $this->htmlCache = $this->loadHtmlCache();
        }
        
        $cacheKey = $type . ':' . $key;
        return $this->htmlCache[$cacheKey] ?? null;
    }

    /**
     * Load the HTML cache from disk.
     */
    private function loadHtmlCache(): array
    {
        return SignedCache::read($this->app->configPath('storage') . '/cache/html_cache.bin');
    }

    // === Search Synonyms ===

    /** @var array<string, array<string>>|null */
    private ?array $synonymsCache = null;
    private ?array $stopWordsCache = null;

    /**
     * Get search synonyms map (word => [synonyms]).
     */
    public function getSynonyms(): array
    {
        return $this->synonymsCache ??= $this->loadSearchCache('synonyms.bin');
    }

    /**
     * Get stop words set (word => true for fast lookup).
     */
    public function getStopWords(): array
    {
        return $this->stopWordsCache ??= $this->loadSearchCache('stopwords.bin');
    }

    private function loadSearchCache(string $filename): array
    {
        return SignedCache::read($this->app->configPath('storage') . '/cache/' . $filename);
    }

    // === Cache Management ===

    /**
     * Clear cached data (for testing or forced reload).
     */
    public function clearCache(): void
    {
        $this->backend()->clearMemoryCache();
        $this->htmlCache = null;
        $this->synonymsCache = null;
        $this->stopWordsCache = null;
    }
}
