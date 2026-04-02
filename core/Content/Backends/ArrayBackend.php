<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

use Ava\Content\QueryProcessor;

/**
 * Array Backend
 *
 * The original binary-serialized array backend for content indexes.
 * Uses .bin files with igbinary or PHP serialize.
 *
 * Implements a tiered caching strategy:
 * - recent_cache.bin: Fast path for archive pages 1-20
 * - slug_lookup.bin: Fast single-item lookups
 * - content_index.bin: Full index for complex queries
 *
 * Best for: Small to medium sites (<10,000 posts)
 * Memory: Loads entire index into memory
 */
final class ArrayBackend implements BackendInterface
{
    // In-memory cache
    private ?array $contentIndex = null;
    private ?array $taxIndex = null;
    private ?array $routes = null;
    private ?array $recentCache = null;
    private ?array $slugLookup = null;

    public function __construct(
        private string $storagePath,
        private string $contentPath
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'array';
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        // Array backend is always available if cache files exist
        $indexPath = $this->getCachePath('content_index.bin');
        return file_exists($indexPath);
    }

    // -------------------------------------------------------------------------
    // Single Item Retrieval
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function getBySlug(string $type, string $slug): ?array
    {
        // Try fast path using slug lookup first
        $lookup = $this->loadSlugLookup();
        $entry = $lookup[$type][$slug] ?? null;

        if ($entry === null) {
            return null;
        }

        // Return the lookup entry (minimal data)
        // The caller can parse the file if needed
        return [
            'type' => $type,
            'slug' => $slug,
            'file_path' => $this->contentPath . '/' . $entry['file'],
            'relative_path' => $entry['file'],
            'id' => $entry['id'] ?? null,
            'status' => $entry['status'] ?? 'published',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getById(string $id): ?array
    {
        $index = $this->loadContentIndex();
        return $index['by_id'][$id] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getByPath(string $relativePath): ?array
    {
        $index = $this->loadContentIndex();
        return $index['by_path'][$relativePath] ?? null;
    }

    // -------------------------------------------------------------------------
    // Bulk Retrieval
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function allRaw(string $type): array
    {
        $index = $this->loadContentIndex();
        return $index['by_type'][$type] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function types(): array
    {
        $index = $this->loadContentIndex();
        return array_keys($index['by_type'] ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $type, ?string $status = null): int
    {
        $index = $this->loadContentIndex();
        $items = $index['by_type'][$type] ?? [];

        if ($status === null) {
            return count($items);
        }

        return count(array_filter(
            $items,
            fn(array $data) => ($data['status'] ?? 'published') === $status
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $type, string $slug): bool
    {
        $index = $this->loadContentIndex();
        return isset($index['by_type'][$type][$slug]);
    }

    // -------------------------------------------------------------------------
    // Query Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function query(array $params): array
    {
        $type = $params['type'] ?? null;
        $status = $params['status'] ?? null;
        $taxonomies = $params['taxonomies'] ?? [];
        $fields = $params['fields'] ?? [];
        $search = $params['search'] ?? null;
        $orderBy = $params['orderBy'] ?? 'date';
        $order = $params['order'] ?? 'desc';
        $page = $params['page'] ?? 1;
        $perPage = $params['perPage'] ?? 10;

        // Get raw items
        $rawItems = [];
        if ($type !== null) {
            $rawItems = $this->allRaw($type);
        } else {
            foreach ($this->types() as $contentType) {
                $rawItems = array_merge($rawItems, $this->allRaw($contentType));
            }
        }

        // Apply filters
        $rawItems = QueryProcessor::applyFilters($rawItems, $status, $taxonomies, $fields);

        // Apply search
        if ($search !== null && $search !== '') {
            $tokens = QueryProcessor::tokenize($search);
            $expandedTokens = array_map(fn($t) => [$t], $tokens);
            $rawItems = QueryProcessor::applySearch($rawItems, $search, $expandedTokens);
        }

        // Get total count before pagination
        $total = count($rawItems);

        // Apply sorting (skip if search active — already sorted by relevance)
        if ($search === null || $search === '') {
            $rawItems = QueryProcessor::applySort($rawItems, $orderBy, $order);
        }

        // Paginate
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rawItems, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Recent Cache Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function canUseFastCache(string $type, int $page, int $perPage): bool
    {
        $cache = $this->loadRecentCache();
        $typeCache = $cache[$type] ?? null;

        if ($typeCache === null) {
            return false;
        }

        $offset = ($page - 1) * $perPage;
        $maxOffset = count($typeCache['items']);

        return $offset + $perPage <= $maxOffset;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecentItems(string $type, int $page, int $perPage): array
    {
        $cache = $this->loadRecentCache();
        $typeCache = $cache[$type] ?? null;

        if ($typeCache === null) {
            return ['items' => [], 'total' => 0];
        }

        $offset = ($page - 1) * $perPage;
        $items = array_slice($typeCache['items'], $offset, $perPage);

        return [
            'items' => $items,
            'total' => $typeCache['total'],
        ];
    }

    // -------------------------------------------------------------------------
    // Taxonomy Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function terms(string $taxonomy): array
    {
        $index = $this->loadTaxIndex();
        return $index[$taxonomy]['terms'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function term(string $taxonomy, string $slug): ?array
    {
        $terms = $this->terms($taxonomy);
        return $terms[$slug] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function taxonomies(): array
    {
        $index = $this->loadTaxIndex();
        return array_keys($index);
    }

    // -------------------------------------------------------------------------
    // Route Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function routes(): array
    {
        return $this->loadRoutes();
    }

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function clearMemoryCache(): void
    {
        $this->contentIndex = null;
        $this->taxIndex = null;
        $this->routes = null;
        $this->recentCache = null;
        $this->slugLookup = null;
    }

    // -------------------------------------------------------------------------
    // Cache Loading (Private)
    // -------------------------------------------------------------------------

    private function loadContentIndex(): array
    {
        if ($this->contentIndex === null) {
            $this->contentIndex = $this->loadCacheFile('content_index');
        }
        return $this->contentIndex;
    }

    private function loadTaxIndex(): array
    {
        if ($this->taxIndex === null) {
            $this->taxIndex = $this->loadCacheFile('tax_index');
        }
        return $this->taxIndex;
    }

    private function loadRoutes(): array
    {
        if ($this->routes === null) {
            $this->routes = $this->loadCacheFile('routes');
        }
        return $this->routes;
    }

    private function loadRecentCache(): array
    {
        if ($this->recentCache === null) {
            $this->recentCache = $this->loadCacheFile('recent_cache');
        }
        return $this->recentCache;
    }

    private function loadSlugLookup(): array
    {
        if ($this->slugLookup === null) {
            $this->slugLookup = $this->loadCacheFile('slug_lookup');
        }
        return $this->slugLookup;
    }

    /**
     * Load a binary cache file.
     */
    private function loadCacheFile(string $name): array
    {
        $binPath = $this->getCachePath($name . '.bin');

        if (!file_exists($binPath)) {
            return [];
        }

        $content = file_get_contents($binPath);
        if ($content === false || strlen($content) < 36) {
            return [];
        }

        // Verify HMAC signature before deserializing (prevents tampering)
        $cacheDir = $this->storagePath . '/cache';
        $payload = \Ava\Content\Indexer::verifyAndExtractPayload($content, $cacheDir);
        if ($payload === null) {
            return [];
        }

        // Check format marker prefix
        $prefix = substr($payload, 0, 3);
        $serializedData = substr($payload, 3);

        if ($prefix === 'IG:') {
            if (!extension_loaded('igbinary')) {
                return [];
            }
            /** @var callable $unserialize */
            $unserialize = 'igbinary_unserialize';
            $data = @$unserialize($serializedData);
        } elseif ($prefix === 'SZ:') {
            $data = @unserialize($serializedData, ['allowed_classes' => false]);
        } else {
            // Invalid format - cache needs rebuild
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private function getCachePath(string $filename): string
    {
        return $this->storagePath . '/cache/' . $filename;
    }
}
