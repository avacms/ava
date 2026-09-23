<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;
use Ava\Content\Backends\ArrayBackend;
use Ava\Content\Backends\BackendInterface;
use Ava\Content\Backends\NullBackend;
use Ava\Content\Backends\SqliteBackend;
use Ava\Content\Index\IndexStore;
use Ava\Content\Index\PrerenderedHtml;
use Ava\Plugins\Hooks;
use Ava\Support\SignedCacheException;

/**
 * Read access to indexed content.
 *
 * Metadata comes from the live index generation; raw content is loaded on
 * demand from files. The generation records which backend built it ('array'
 * or 'sqlite'), so changing content_index.backend takes effect at the next
 * rebuild and never points a reader at an index that does not exist yet.
 */
final class Repository
{
    private Application $app;
    private IndexStore $store;
    private Parser $parser;
    private ?BackendInterface $backend = null;
    private ?string $backendOverride = null;

    /** @var array<string, array<string, true>|array<string, list<string>>> Loaded search files */
    private array $searchCaches = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->store = $app->indexStore();
        $this->parser = new Parser();
    }

    /**
     * Get the active backend.
     */
    public function backend(): BackendInterface
    {
        return $this->backend ??= $this->resolveBackend();
    }

    /**
     * Get the backend name (for debugging/status).
     */
    public function backendName(): string
    {
        return $this->backend()->name();
    }

    /**
     * Read the live generation through a specific backend (benchmarking).
     * The generation must have been built with that backend.
     */
    public function setBackendOverride(?string $backend): void
    {
        $this->backendOverride = $backend;
        $this->backend = null;
    }

    private function resolveBackend(): BackendInterface
    {
        $path = $this->store->currentPath();
        if ($path === null) {
            return new NullBackend();
        }

        $name = $this->backendOverride ?? $this->store->backend() ?? 'array';
        $contentPath = $this->app->configPath('content');

        if ($name === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                throw new \RuntimeException(
                    'The content index was built with SQLite but the pdo_sqlite extension is unavailable. '
                    . "Install it, or set content_index.backend to 'array' and run ./ava rebuild."
                );
            }

            return new SqliteBackend($path . '/content_index.sqlite', $contentPath);
        }

        return new ArrayBackend($path, $contentPath, $this->store->keyDirectory());
    }

    /**
     * Load raw content from a file and return the Item with content.
     */
    private function hydrateItem(array $data): Item
    {
        $rawContent = '';
        $candidate = isset($data['relative_path'])
            ? $this->app->configPath('content') . '/' . $data['relative_path']
            : (string) ($data['file_path'] ?? '');

        $resolved = $this->resolveInsideContentRoot($candidate);
        if ($resolved !== null) {
            $rawContent = $this->parser->parseFile($resolved, $data['type'] ?? '')->rawContent();
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

        $relative = $data['relative_path'] ?? null;
        $resolved = $this->resolveInsideContentRoot(
            is_string($relative)
                ? $this->app->configPath('content') . '/' . $relative
                : (string) ($data['file_path'] ?? '')
        );
        if ($resolved === null) {
            return null;
        }

        $item = $this->parser->parseFile($resolved, $type)->withContentKey($key);

        return Hooks::apply('content.loaded', $item);
    }

    /**
     * Get a content item directly from its content-relative file path.
     *
     * This is the fast path for rendering a single page: the route table
     * already carries the file path, so we parse that one file instead of
     * loading the content index.
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
        if ($contentRoot === false || $resolved === false || !is_file($resolved)) {
            return null;
        }

        return str_starts_with($resolved, $contentRoot . DIRECTORY_SEPARATOR) ? $resolved : null;
    }

    /**
     * Get a content item by type and content key, with raw content.
     */
    public function getFromIndex(string $type, string $key): ?Item
    {
        $data = $this->backend()->getBySlug($type, $key);

        return $data === null ? null : $this->hydrateItem($data);
    }

    public function getById(string $id): ?Item
    {
        $data = $this->backend()->getById($id);

        return $data === null ? null : $this->hydrateItem($data);
    }

    public function getByPath(string $relativePath): ?Item
    {
        $data = $this->backend()->getByPath($relativePath);

        return $data === null ? null : $this->hydrateItem($data);
    }

    /**
     * Get all items of a type (with full content loaded).
     * Warning: This reads every file from disk. Use allMeta() when content is not needed.
     *
     * @return array<Item>
     */
    public function all(string $type): array
    {
        return array_map(fn($data) => $this->hydrateItem($data), $this->backend()->allRaw($type));
    }

    /**
     * Get all items of a type (metadata only, no file I/O).
     *
     * @return array<Item>
     */
    public function allMeta(string $type): array
    {
        return array_map(fn($data) => Item::fromArray($data, ''), $this->backend()->allRaw($type));
    }

    /**
     * Get raw index data for a type (for optimized queries).
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
        return array_filter($this->all($type), fn(Item $item) => $item->isPublished());
    }

    /**
     * Get published items of a type (metadata only, no file I/O).
     *
     * @return array<Item>
     */
    public function publishedMeta(string $type): array
    {
        return array_filter($this->allMeta($type), fn(Item $item) => $item->isPublished());
    }

    /**
     * Get recent published items across all types (metadata only, no file I/O).
     *
     * Published only: this reads like a listing helper, so it must not hand
     * drafts to a caller that never thought about status.
     *
     * @return array<Item>
     */
    public function recentMeta(int $limit = 5): array
    {
        return (new Query($this->app))->perPage($limit)->get();
    }

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
     * Get a term by slug. Any spelling of the term is accepted ("Web Dev",
     * "web-dev").
     */
    public function term(string $taxonomy, string $slug): ?array
    {
        return $this->backend()->term($taxonomy, Terms::slug($slug));
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
            [$type, $contentKey] = explode(':', $key, 2);
            $item = $this->get($type, $contentKey);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string>
     */
    public function taxonomies(): array
    {
        return $this->backend()->taxonomies();
    }

    // === Routes ===

    /**
     * The whole route table. Request handling uses the lookups below, which
     * the SQLite backend answers without loading every route.
     */
    public function routes(): array
    {
        return $this->backend()->routes();
    }

    public function exactRoute(string $path): ?array
    {
        return $this->backend()->exactRoute($path);
    }

    public function previewRoute(string $path): ?array
    {
        return $this->backend()->previewRoute($path);
    }

    public function redirectRoute(string $path): ?array
    {
        return $this->backend()->redirectRoute($path);
    }

    public function reverseUrl(string $type, string $contentKey): ?string
    {
        return $this->backend()->reverseUrl($type, $contentKey);
    }

    /**
     * @return array<string, array{base: string, hierarchical: bool}>
     */
    public function taxonomyRoutes(): array
    {
        return $this->backend()->taxonomyRoutes();
    }

    /**
     * Find route data for a path.
     */
    public function routeFor(string $path): ?array
    {
        $redirect = $this->redirectRoute($path);
        if ($redirect !== null) {
            return ['type' => 'redirect', 'to' => $redirect['to'], 'code' => $redirect['code'] ?? 301];
        }

        $exact = $this->exactRoute($path);
        if ($exact !== null) {
            return $exact;
        }

        foreach ($this->taxonomyRoutes() as $taxName => $taxRoute) {
            $base = rtrim($taxRoute['base'], '/');
            if (str_starts_with($path, $base . '/')) {
                return [
                    'type' => 'taxonomy',
                    'taxonomy' => $taxName,
                    'term' => substr($path, strlen($base) + 1),
                    'template' => 'taxonomy.php',
                ];
            }
        }

        return null;
    }

    // === Recent Cache ===

    /**
     * @return array{items: array, total: int, from_cache: bool}
     */
    public function getRecentItems(string $type, int $page = 1, int $perPage = 10): array
    {
        $result = $this->backend()->getRecentItems($type, $page, $perPage);

        return ['items' => $result['items'], 'total' => $result['total'], 'from_cache' => true];
    }

    public function canUseRecentCache(string $type, int $page, int $perPage, array $filters = []): bool
    {
        return $filters === [] && $this->backend()->canUseFastCache($type, $page, $perPage);
    }

    // === Pre-rendered HTML ===

    /**
     * Build-time HTML for an item, if it was rendered from this exact source.
     */
    public function prerenderedHtml(Item $item): ?string
    {
        // Only an optimisation: if the file can't be read, render on demand.
        try {
            $entry = $this->readGenerationFile(PrerenderedHtml::relativePath($item->type(), $item->contentKey()));
        } catch (\RuntimeException $e) {
            error_log('Ava: ' . $e->getMessage());
            return null;
        }

        return PrerenderedHtml::match($entry, $item);
    }

    /**
     * Build-time HTML by type and key, without checking it against the
     * source file. Prefer prerenderedHtml().
     */
    public function getPrerenderedHtml(string $type, string $key): ?string
    {
        $entry = $this->readGenerationFile(PrerenderedHtml::relativePath($type, $key));

        return is_string($entry['html'] ?? null) ? $entry['html'] : null;
    }

    // === Search Configuration ===

    /**
     * Get search synonyms map (word => [synonyms]).
     */
    public function getSynonyms(): array
    {
        return $this->searchCaches['synonyms'] ??= $this->readGenerationFile('synonyms.bin') ?? [];
    }

    /**
     * Get stop words set (word => true for fast lookup).
     */
    public function getStopWords(): array
    {
        return $this->searchCaches['stopwords'] ??= $this->readGenerationFile('stopwords.bin') ?? [];
    }

    private function readGenerationFile(string $name): ?array
    {
        $path = $this->store->currentPath();
        if ($path === null) {
            return null;
        }

        try {
            return $this->store->readBinary($path, $name);
        } catch (SignedCacheException $e) {
            throw new \RuntimeException('The content index is unreadable: ' . $e->getMessage(), 0, $e);
        }
    }

    // === Cache Management ===

    /**
     * Forget everything loaded from the index, e.g. after a rebuild.
     */
    public function clearCache(): void
    {
        $this->backend?->clearMemoryCache();
        $this->backend = null;
        $this->searchCaches = [];
        $this->store->reloadState();
    }
}
