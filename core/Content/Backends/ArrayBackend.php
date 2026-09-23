<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

use Ava\Content\QueryProcessor;
use Ava\Support\SignedCache;
use Ava\Support\SignedCacheException;

/**
 * Array Backend
 *
 * Signed, serialised PHP arrays (igbinary when available) in one index
 * generation directory. Each file is loaded on first use:
 *
 * - routes.bin:        route tables (every request)
 * - slug_lookup.bin:   type/key => file, for single items
 * - recent_cache.bin:  pre-sorted first pages of each archive
 * - content_index.bin: metadata for every item, stored once
 * - bodies.bin:        raw bodies, only for search
 * - tax_index.bin:     taxonomy terms
 *
 * Best for: most sites. Memory grows with the number of items when a request
 * needs the full metadata index (deep pagination, sitemaps, search).
 */
final class ArrayBackend implements BackendInterface
{
    /** @var array<string, array> Loaded files, by name */
    private array $loaded = [];

    public function __construct(
        private string $indexPath,
        private string $contentPath,
        private ?string $keyDirectory = null
    ) {
    }

    public function name(): string
    {
        return 'array';
    }

    public function isAvailable(): bool
    {
        return is_file($this->indexPath . '/content_index.bin');
    }

    // -------------------------------------------------------------------------
    // Single Item Retrieval
    // -------------------------------------------------------------------------

    public function getBySlug(string $type, string $slug): ?array
    {
        $entry = $this->file('slug_lookup')[$type][$slug] ?? null;
        if ($entry === null) {
            return null;
        }

        return [
            'type' => $type,
            'slug' => $entry['slug'] ?? $slug,
            'content_key' => $slug,
            'file_path' => $this->contentPath . '/' . $entry['file'],
            'relative_path' => $entry['file'],
            'id' => $entry['id'] ?? null,
            'status' => $entry['status'] ?? 'draft',
        ];
    }

    public function getById(string $id): ?array
    {
        return $this->resolve($this->file('content_index')['by_id'][$id] ?? null);
    }

    public function getByPath(string $relativePath): ?array
    {
        return $this->resolve($this->file('content_index')['by_path'][$relativePath] ?? null);
    }

    // -------------------------------------------------------------------------
    // Bulk Retrieval
    // -------------------------------------------------------------------------

    public function allRaw(string $type, bool $withBody = false): array
    {
        $items = $this->file('content_index')['by_type'][$type] ?? [];
        if (!$withBody) {
            return $items;
        }

        $bodies = $this->file('bodies');
        foreach ($items as $key => $data) {
            $items[$key]['body'] = $bodies[$type . ':' . $key] ?? '';
        }

        return $items;
    }

    public function types(): array
    {
        return array_map('strval', array_keys($this->file('content_index')['by_type'] ?? []));
    }

    public function count(string $type, ?string $status = null): int
    {
        $items = $this->file('content_index')['by_type'][$type] ?? [];
        if ($status === null) {
            return count($items);
        }

        return count(array_filter($items, fn(array $data) => ($data['status'] ?? 'draft') === $status));
    }

    public function exists(string $type, string $slug): bool
    {
        return isset($this->file('slug_lookup')[$type][$slug]);
    }

    // -------------------------------------------------------------------------
    // Query Operations
    // -------------------------------------------------------------------------

    public function query(array $params): array
    {
        // Search scores bodies in place rather than copying one into every
        // item, which roughly halves its peak memory.
        $bodies = null;
        $bodyOf = function (array $data) use (&$bodies): string {
            $bodies ??= $this->file('bodies');

            return $bodies[($data['type'] ?? '') . ':' . ($data['content_key'] ?? '')] ?? '';
        };

        return QueryProcessor::query($this, $params, $bodyOf);
    }

    // -------------------------------------------------------------------------
    // Recent Cache Operations
    // -------------------------------------------------------------------------

    public function canUseFastCache(string $type, int $page, int $perPage): bool
    {
        $typeCache = $this->file('recent_cache')[$type] ?? null;
        if ($typeCache === null) {
            return false;
        }

        // Also serves the final, partial page when every item fits.
        $offset = ($page - 1) * $perPage;
        $cached = count($typeCache['items']);

        return $offset + $perPage <= $cached || $cached === $typeCache['total'];
    }

    public function getRecentItems(string $type, int $page, int $perPage): array
    {
        $typeCache = $this->file('recent_cache')[$type] ?? null;
        if ($typeCache === null) {
            return ['items' => [], 'total' => 0];
        }

        return [
            'items' => array_slice($typeCache['items'], ($page - 1) * $perPage, $perPage),
            'total' => $typeCache['total'],
        ];
    }

    // -------------------------------------------------------------------------
    // Taxonomy Operations
    // -------------------------------------------------------------------------

    public function terms(string $taxonomy): array
    {
        return $this->file('tax_index')[$taxonomy]['terms'] ?? [];
    }

    public function term(string $taxonomy, string $slug): ?array
    {
        return $this->terms($taxonomy)[$slug] ?? null;
    }

    public function taxonomies(): array
    {
        return array_map('strval', array_keys($this->file('tax_index')));
    }

    // -------------------------------------------------------------------------
    // Route Operations
    // -------------------------------------------------------------------------

    public function routes(): array
    {
        return $this->file('routes') + [
            'redirects' => [], 'exact' => [], 'preview' => [], 'patterns' => [], 'taxonomy' => [], 'reverse' => [],
        ];
    }

    public function exactRoute(string $path): ?array
    {
        return $this->file('routes')['exact'][$path] ?? null;
    }

    public function previewRoute(string $path): ?array
    {
        return $this->file('routes')['preview'][$path] ?? null;
    }

    public function redirectRoute(string $path): ?array
    {
        return $this->file('routes')['redirects'][$path] ?? null;
    }

    public function reverseUrl(string $type, string $contentKey): ?string
    {
        return $this->file('routes')['reverse'][$type . ':' . $contentKey] ?? null;
    }

    public function taxonomyRoutes(): array
    {
        return $this->file('routes')['taxonomy'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    public function clearMemoryCache(): void
    {
        $this->loaded = [];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * @param array{0: string, 1: string}|null $reference [type, content key]
     */
    private function resolve(?array $reference): ?array
    {
        if ($reference === null) {
            return null;
        }

        [$type, $key] = $reference;

        return $this->file('content_index')['by_type'][$type][$key] ?? null;
    }

    private function file(string $name): array
    {
        if (!isset($this->loaded[$name])) {
            $path = $this->indexPath . '/' . $name . '.bin';
            try {
                $data = SignedCache::read($path, $this->keyDirectory);
            } catch (SignedCacheException $e) {
                throw new \RuntimeException('The content index is unreadable: ' . $e->getMessage(), 0, $e);
            }

            if ($data === null) {
                throw new \RuntimeException(
                    "The content index is incomplete ({$name}.bin is missing). Run: ./ava rebuild"
                );
            }

            $this->loaded[$name] = $data;
        }

        return $this->loaded[$name];
    }
}
