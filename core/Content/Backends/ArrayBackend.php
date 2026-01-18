<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

use Ava\Application;

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
    private Application $app;

    // In-memory cache
    private ?array $contentIndex = null;
    private ?array $taxIndex = null;
    private ?array $routes = null;
    private ?array $recentCache = null;
    private ?array $slugLookup = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

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
            'file_path' => $this->app->configPath('content') . '/' . $entry['file'],
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
        $rawItems = $this->applyFilters($rawItems, $status, $taxonomies, $fields);

        // Apply search
        if ($search !== null && $search !== '') {
            $rawItems = $this->applySearch($rawItems, $search);
        }

        // Get total count before pagination
        $total = count($rawItems);

        // Apply sorting
        $rawItems = $this->applySort($rawItems, $orderBy, $order);

        // Paginate
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rawItems, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Apply filters to raw items.
     */
    private function applyFilters(array $items, ?string $status, array $taxonomies, array $fields): array
    {
        return array_filter($items, function (array $data) use ($status, $taxonomies, $fields) {
            // Status filter
            if ($status !== null) {
                $itemStatus = $data['status'] ?? 'published';
                if ($itemStatus !== $status) {
                    return false;
                }
            }

            // Taxonomy filters
            foreach ($taxonomies as $taxonomy => $term) {
                $terms = $data['taxonomies'][$taxonomy] ?? [];
                if (!in_array($term, $terms, true)) {
                    return false;
                }
            }

            // Field filters
            foreach ($fields as $filter) {
                if (!$this->matchesFieldFilter($data, $filter)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if item matches a field filter.
     */
    private function matchesFieldFilter(array $data, array $filter): bool
    {
        $field = $filter['field'];
        $expected = $filter['value'];
        $operator = $filter['operator'];

        $value = $data['meta'][$field] ?? $data[$field] ?? null;

        return match ($operator) {
            '=' => $value === $expected,
            '!=' => $value !== $expected,
            '>' => $value > $expected,
            '>=' => $value >= $expected,
            '<' => $value < $expected,
            '<=' => $value <= $expected,
            'in' => is_array($expected) && in_array($value, $expected, true),
            'not_in' => is_array($expected) && !in_array($value, $expected, true),
            'like' => is_string($value) && str_contains(strtolower($value), strtolower($expected)),
            default => false,
        };
    }

    /**
     * Apply search to raw items.
     */
    private function applySearch(array $items, string $search): array
    {
        $query = strtolower($search);
        $tokens = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        $scored = [];
        foreach ($items as $data) {
            $score = $this->scoreItem($data, $query, $tokens);
            if ($score > 0) {
                $scored[] = ['data' => $data, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($s) => $s['data'], $scored);
    }

    /**
     * Score item for search relevance.
     */
    private function scoreItem(array $data, string $phrase, array $tokens): int
    {
        $score = 0;
        $title = strtolower($data['title'] ?? '');
        $excerpt = strtolower($data['meta']['excerpt'] ?? $data['excerpt'] ?? '');

        // Title phrase match: +80
        if (str_contains($title, $phrase)) {
            $score += 80;
        }

        // Title contains all tokens: +40
        $allInTitle = true;
        $tokenHits = 0;
        foreach ($tokens as $token) {
            if (str_contains($title, $token)) {
                $tokenHits++;
            } else {
                $allInTitle = false;
            }
        }
        if ($allInTitle && count($tokens) > 1) {
            $score += 40;
        }

        // Title token hits: +10 each (cap +30)
        $score += min(30, $tokenHits * 10);

        // Excerpt phrase match: +30
        if (str_contains($excerpt, $phrase)) {
            $score += 30;
        }

        // Excerpt token hits: +3 each (cap +15)
        $excerptHits = 0;
        foreach ($tokens as $token) {
            if (str_contains($excerpt, $token)) {
                $excerptHits++;
            }
        }
        $score += min(15, $excerptHits * 3);

        // Featured boost: +15
        if (!empty($data['meta']['featured']) || !empty($data['featured'])) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Apply sorting to raw items.
     */
    private function applySort(array $items, string $orderBy, string $order): array
    {
        usort($items, function (array $a, array $b) use ($orderBy, $order) {
            $aVal = $this->getSortValue($a, $orderBy);
            $bVal = $this->getSortValue($b, $orderBy);

            $result = $aVal <=> $bVal;

            if ($order === 'desc') {
                $result = -$result;
            }

            // Tie-breaker: title ascending
            if ($result === 0) {
                $result = ($a['title'] ?? '') <=> ($b['title'] ?? '');
            }

            return $result;
        });

        return $items;
    }

    /**
     * Get sort value from raw data.
     */
    private function getSortValue(array $data, string $orderBy): mixed
    {
        return match ($orderBy) {
            'date' => $data['date'] ?? 0,
            'updated' => $data['updated'] ?? $data['date'] ?? 0,
            'title' => strtolower($data['title'] ?? ''),
            'order', 'menu_order' => $data['meta']['order'] ?? $data['order'] ?? 0,
            default => $data['meta'][$orderBy] ?? $data[$orderBy] ?? '',
        };
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
        $cacheDir = $this->app->configPath('storage') . '/cache';
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
        return $this->app->configPath('storage') . '/cache/' . $filename;
    }
}
