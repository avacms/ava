<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

/**
 * Contract for content index backends.
 *
 * Query talks only to this interface, so array and SQLite storage are
 * interchangeable.
 */
interface BackendInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    // -------------------------------------------------------------------------
    // Single Item Retrieval
    // -------------------------------------------------------------------------

    /**
     * @param string $slug Slug for pattern types, path for hierarchical ones
     */
    public function getBySlug(string $type, string $slug): ?array;

    public function getById(string $id): ?array;

    /** @param string $relativePath Path relative to the content directory */
    public function getByPath(string $relativePath): ?array;

    // -------------------------------------------------------------------------
    // Bulk Retrieval
    // -------------------------------------------------------------------------

    /**
     * Every item of a type, keyed by content key.
     *
     * @param bool $withBody Include raw bodies (only search needs them).
     * @return array<string, array>
     */
    public function allRaw(string $type, bool $withBody = false): array;

    /** @return array<string> Types that have at least one item */
    public function types(): array;

    public function count(string $type, ?string $status = null): int;

    public function exists(string $type, string $slug): bool;

    // -------------------------------------------------------------------------
    // Query Operations
    // -------------------------------------------------------------------------

    /**
     * Filter, sort and paginate in whatever way the backend does best.
     *
     * @param array $params Query parameters:
     *   - type: string|null - Content type filter
     *   - types: array|null - Restrict eligible content types (takes precedence over type)
     *   - status: string|null - Status filter
     *   - taxonomies: array - Taxonomy filters [taxonomy => term]
     *   - fields: array - Field filters [{field, value, operator}]
     *   - search: string|null - Relevance search query (shared scorer)
     *   - stopWords: array - Search stop words
     *   - synonyms: array - Search synonym groups
     *   - searchWeights: array|null - Relevance weights and custom search fields
     *   - orderBy: string - Field to sort by
     *   - order: string - Sort direction (asc/desc)
     *   - page: int - Page number (1-based)
     *   - perPage: int - Items per page
     * @return array{items: array, total: int}
     */
    public function query(array $params): array;

    // -------------------------------------------------------------------------
    // Recent Cache Operations
    // -------------------------------------------------------------------------

    /**
     * Can this listing take the backend's optimised path?
     *
     * True only for the simple case: published, date descending, no filters.
     */
    public function canUseFastCache(string $type, int $page, int $perPage): bool;

    /** @return array{items: array, total: int} */
    public function getRecentItems(string $type, int $page, int $perPage): array;

    // -------------------------------------------------------------------------
    // Taxonomy Operations
    // -------------------------------------------------------------------------

    /** @return array<string, array> Terms indexed by slug */
    public function terms(string $taxonomy): array;

    public function term(string $taxonomy, string $slug): ?array;

    /** @return array<string> */
    public function taxonomies(): array;

    // -------------------------------------------------------------------------
    // Route Operations
    // -------------------------------------------------------------------------

    /**
     * The whole route table (redirects, exact, preview, taxonomy, reverse).
     * Plugins enumerating the site use this; request handling uses the
     * single-route lookups below.
     */
    public function routes(): array;

    /** Route data for a public URL path, or null. */
    public function exactRoute(string $path): ?array;

    /** Route data for an unpublished item's URL path (preview only), or null. */
    public function previewRoute(string $path): ?array;

    /** @return array{to: string, code: int}|null A redirect_from entry. */
    public function redirectRoute(string $path): ?array;

    /** URL path for "type:contentKey", or null. */
    public function reverseUrl(string $type, string $contentKey): ?string;

    /** @return array<string, array{base: string, hierarchical: bool}> */
    public function taxonomyRoutes(): array;

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    public function clearMemoryCache(): void;
}
