<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;

/**
 * Content Query
 *
 * Fluent query builder for content, operating on cached indexes.
 * Supports WP-style parameters.
 */
final class Query
{
    private const int MAX_PAGE = 1_000_000;
    private const int MAX_SEARCH_LENGTH = 200;
    private const int MAX_TAXONOMY_FILTERS = 10;
    private const int MAX_TAXONOMY_TERM_LENGTH = 200;
    private const array PUBLIC_ORDER_FIELDS = [
        'date',
        'updated',
        'title',
        'order',
        'menu_order',
    ];

    private Application $app;
    private Repository $repository;

    // Query parameters
    private ?string $type = null;

    /** @var list<string>|null Several content types (see types()) */
    private ?array $types = null;

    /**
     * SECURITY: published-only until a caller opts out with anyStatus().
     *
     * A theme writing `$ava->query()->type('post')` for a related-posts list
     * must not leak draft titles, excerpts and bodies because it forgot
     * published(). The safe value is the one you get by not thinking about it.
     */
    private ?string $status = 'published';
    private array $taxonomyFilters = [];
    private array $fieldFilters = [];
    private string $orderBy = 'date';
    private string $order = 'desc';
    private int $perPage = 10;
    private int $page = 1;
    private ?string $search = null;
    private ?array $searchWeights = null;

    // Results cache
    private ?array $results = null;
    private ?int $totalCount = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->repository = $app->repository();
    }

    // -------------------------------------------------------------------------
    // Query building (fluent)
    // -------------------------------------------------------------------------

    /**
     * Filter by content type.
     * 
     * Also auto-loads search weights from content type config if defined.
     */
    public function type(string $type): self
    {
        $clone = clone $this;
        $clone->type = $type;
        $clone->types = null;
        $clone->results = null;

        // Auto-load search config from content type if not already set
        if ($clone->searchWeights === null) {
            $searchConfig = $clone->getContentTypeSearchConfig($type);
            if (!empty($searchConfig)) {
                $weights = $searchConfig['weights'] ?? [];
                // Add configured fields to search
                if (!empty($searchConfig['fields'])) {
                    $weights['fields'] = $searchConfig['fields'];
                }
                if (!empty($weights)) {
                    $clone->searchWeights = $weights;
                }
            }
        }
        
        return $clone;
    }

    /**
     * Filter to any of several content types (e.g. a combined feed).
     *
     * @param array<string> $types
     */
    public function types(array $types): self
    {
        $clone = clone $this;
        $clone->types = array_values(array_unique(array_map('strval', $types)));
        $clone->type = null;
        $clone->results = null;
        return $clone;
    }

    private function getContentTypeSearchConfig(string $type): array
    {
        return $this->app->contentTypes()[$type]['search'] ?? [];
    }

    /**
     * Filter by status.
     */
    public function status(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        $clone->results = null;
        return $clone;
    }

    /**
     * Filter to published only.
     *
     * This is the default; call it for emphasis, or to undo anyStatus().
     */
    public function published(): self
    {
        return $this->status('published');
    }

    /**
     * Drop the publication filter, including unpublished content.
     *
     * For callers with their own access control (previews, the CLI, an admin
     * listing). Never reachable from a visitor's query parameters.
     */
    public function anyStatus(): self
    {
        $clone = clone $this;
        $clone->status = null;
        $clone->results = null;
        return $clone;
    }

    /**
     * Filter by taxonomy term.
     */
    public function whereTax(string $taxonomy, string $term): self
    {
        $clone = clone $this;
        $clone->taxonomyFilters[$taxonomy] = $term;
        $clone->results = null;
        return $clone;
    }

    /**
     * Filter by a field value.
     */
    public function where(string $field, mixed $value, string $operator = '='): self
    {
        $clone = clone $this;
        $clone->fieldFilters[] = ['field' => $field, 'value' => $value, 'operator' => $operator];
        $clone->results = null;
        return $clone;
    }

    public function orderBy(string $field, string $direction = 'desc'): self
    {
        $clone = clone $this;
        $clone->orderBy = $field;
        $clone->order = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $clone->results = null;
        return $clone;
    }

    /**
     * Set items per page.
     */
    public function perPage(int $count): self
    {
        $clone = clone $this;
        $clone->perPage = max(1, min(100, $count)); // Cap at 100
        $clone->results = null;
        return $clone;
    }

    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->page = max(1, min(self::MAX_PAGE, $page));
        $clone->results = null;
        return $clone;
    }

    public function search(string $query): self
    {
        $clone = clone $this;
        $clone->search = self::normalizeSearch($query);
        $clone->results = null;
        return $clone;
    }

    /**
     * Set custom search weights.
     * 
     * Weights control how different matches affect result scoring.
     * Higher weights mean more relevance for that match type.
     * 
     * @param array $weights Associative array with keys:
     *   - title_phrase: Exact phrase match in title (default: 80)
     *   - title_all_tokens: All search tokens in title (default: 40)
     *   - title_token: Per-token match in title (default: 10, max 30)
     *   - excerpt_phrase: Exact phrase match in excerpt (default: 30)
     *   - excerpt_token: Per-token match in excerpt (default: 3, max 15)
     *   - body_phrase: Exact phrase match in body (default: 20)
     *   - body_token: Per-token match in body (default: 2, max 10)
     *   - featured: Bonus for featured items (default: 15)
     *   - fields: Array of meta field names to search (default: [])
     *   - field_weight: Weight per field match (default: 5)
     */
    public function searchWeights(array $weights): self
    {
        $clone = clone $this;
        $clone->searchWeights = $weights;
        $clone->results = null;
        return $clone;
    }

    /**
     * Apply WP-style query parameters.
     *
     * SECURITY: `status` is deliberately not read from $params. Accepting it
     * would let any visitor list drafts with `?status=draft` on an archive or
     * taxonomy page, so it can only be set in code, via status() / published()
     * / anyStatus().
     */
    public function fromParams(array $params): self
    {
        $clone = clone $this;

        $type = self::stringParam($params['type'] ?? null, 64);
        if ($type !== null && array_key_exists($type, $this->app->contentTypes())) {
            $clone->type = $type;
        }

        $orderBy = self::stringParam($params['orderby'] ?? null, 32);
        if ($orderBy !== null && in_array($orderBy, self::PUBLIC_ORDER_FIELDS, true)) {
            $clone->orderBy = $orderBy;
        }

        $order = self::stringParam($params['order'] ?? null, 4);
        if ($order !== null) {
            $order = strtolower($order);
            if (in_array($order, ['asc', 'desc'], true)) {
                $clone->order = $order;
            }
        }

        $perPage = self::integerParam($params['per_page'] ?? null);
        if ($perPage !== null) {
            $clone->perPage = max(1, min(100, $perPage));
        }

        $page = self::integerParam($params['paged'] ?? null);
        if ($page !== null) {
            $clone->page = max(1, min(self::MAX_PAGE, $page));
        }

        $search = self::stringParam($params['q'] ?? null, self::MAX_SEARCH_LENGTH);
        if ($search === null) {
            $search = self::stringParam($params['search'] ?? null, self::MAX_SEARCH_LENGTH);
        }
        if ($search !== null) {
            $clone->search = self::normalizeSearch($search);
        }

        // Taxonomy filters (tax_<taxonomy>=term)
        $allowedTaxonomies = $this->configuredTaxonomies();
        foreach ($params as $key => $value) {
            if (count($clone->taxonomyFilters) >= self::MAX_TAXONOMY_FILTERS) {
                break;
            }
            if (!is_string($key) || !str_starts_with($key, 'tax_')) {
                continue;
            }

            $taxonomy = substr($key, 4);
            $term = self::stringParam($value, self::MAX_TAXONOMY_TERM_LENGTH);
            if ($term !== null && $term !== '' && isset($allowedTaxonomies[$taxonomy])) {
                $clone->taxonomyFilters[$taxonomy] = $term;
            }
        }

        $clone->results = null;
        return $clone;
    }

    /**
     * Get taxonomy names declared by at least one configured content type.
     *
     * @return array<string, true>
     */
    private function configuredTaxonomies(): array
    {
        $taxonomies = [];
        foreach ($this->app->contentTypes() as $contentType) {
            foreach ($contentType['taxonomies'] ?? [] as $taxonomy) {
                if (is_string($taxonomy) && $taxonomy !== '') {
                    $taxonomies[$taxonomy] = true;
                }
            }
        }

        return $taxonomies;
    }

    private static function normalizeSearch(string $search): string
    {
        return trim(substr($search, 0, self::MAX_SEARCH_LENGTH));
    }

    private static function stringParam(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) || strlen($value) > $maxLength) {
            return null;
        }

        return $value;
    }

    private static function integerParam(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/^-?\d+$/D', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '-0');
        $normalized = ($negative ? '-' : '') . ($digits === '' ? '0' : $digits);
        $integer = filter_var($normalized, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Get all matching items (with pagination).
     *
     * @return array<Item>
     */
    public function get(): array
    {
        $this->execute();
        return $this->results;
    }

    /**
     * Get first matching item.
     */
    public function first(): ?Item
    {
        $results = $this->perPage(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Get total count (before pagination).
     */
    public function count(): int
    {
        $this->execute();
        return $this->totalCount;
    }

    /**
     * Get total number of pages.
     */
    public function totalPages(): int
    {
        return (int) ceil($this->count() / $this->perPage);
    }

    /**
     * Get current page number.
     */
    public function currentPage(): int
    {
        return $this->page;
    }

    /**
     * Get the current order-by field.
     */
    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    /**
     * Get the current sort direction.
     */
    public function getOrder(): string
    {
        return $this->order;
    }

    /**
     * Get the current publication status filter.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Check if there are more pages.
     */
    public function hasMore(): bool
    {
        return $this->page < $this->totalPages();
    }

    /**
     * Check if there are previous pages.
     */
    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    /**
     * Get pagination info.
     */
    public function pagination(): array
    {
        return [
            'current_page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->count(),
            'total_pages' => $this->totalPages(),
            'has_more' => $this->hasMore(),
            'has_previous' => $this->hasPrevious(),
        ];
    }

    /**
     * Check if query has results.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Execute the query.
     * 
     * Optimized to work with raw arrays and only create Item objects
     * for the final paginated result.
     * 
     * Uses the recent cache for simple queries (published, sorted by date desc,
     * no taxonomy filters, no search) to avoid loading the full content index.
     */
    private function execute(): void
    {
        if ($this->results !== null) {
            return;
        }

        // Try to use recent cache for simple queries
        if ($this->canUseRecentCache()) {
            $this->executeFromRecentCache();
            return;
        }

        // Fall back to full index for complex queries
        $this->executeFromFullIndex();
    }

    /**
     * Check if this query can be served from the recent cache.
     */
    private function canUseRecentCache(): bool
    {
        // Must have a single content type
        if ($this->type === null) {
            return false;
        }
        
        // The recent cache contains published content only.
        if ($this->status !== 'published') {
            return false;
        }
        
        // Must be sorted by date descending (the default)
        if ($this->orderBy !== 'date' || $this->order !== 'desc') {
            return false;
        }
        
        // Can't have taxonomy filters
        if (!empty($this->taxonomyFilters)) {
            return false;
        }
        
        // Can't have field filters
        if (!empty($this->fieldFilters)) {
            return false;
        }
        
        // Can't have search
        if ($this->search !== null && $this->search !== '') {
            return false;
        }
        
        // Check if the page range is within the cache
        return $this->repository->canUseRecentCache(
            $this->type,
            $this->page,
            $this->perPage
        );
    }

    /**
     * Execute query from the lightweight recent cache.
     */
    private function executeFromRecentCache(): void
    {
        $result = $this->repository->getRecentItems(
            $this->type,
            $this->page,
            $this->perPage
        );
        
        $this->totalCount = $result['total'];
        
        // Convert cache items to Item objects
        $this->results = array_map(
            fn(array $data) => Item::fromArray($data, ''),
            $result['items']
        );
    }

    /**
     * Execute query from the full content index.
     */
    private function executeFromFullIndex(): void
    {
        $params = [
            'type' => $this->type,
            'types' => $this->taxonomyFilters === [] ? $this->types : $this->queryTypes(),
            'status' => $this->status,
            'taxonomies' => $this->taxonomyFilters,
            'fields' => $this->fieldFilters,
            'search' => $this->search,
            'orderBy' => $this->orderBy,
            'order' => $this->order,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
        if ($this->search !== null && $this->search !== '') {
            $params['stopWords'] = $this->repository->getStopWords();
            $params['synonyms'] = $this->repository->getSynonyms();
            $params['searchWeights'] = $this->searchWeights;
        }

        $result = $this->repository->backend()->query($params);
        $this->totalCount = $result['total'];
        $this->results = array_map(fn(array $data) => Item::fromArray($data, ''), $result['items']);
    }

    /**
     * Get content types eligible for the query's taxonomy filters.
     *
     * @return array<string>
     */
    private function queryTypes(): array
    {
        $contentTypes = $this->app->contentTypes();
        // Eligibility is defined by configuration, even when a type currently
        // has no indexed items. Backends simply return no rows for empty types.
        $types = $this->types ?? ($this->type !== null ? [$this->type] : array_keys($contentTypes));

        return array_values(array_filter($types, function (string $type) use ($contentTypes): bool {
            $declaredTaxonomies = $contentTypes[$type]['taxonomies'] ?? [];

            foreach (array_keys($this->taxonomyFilters) as $taxonomy) {
                if (!in_array($taxonomy, $declaredTaxonomies, true)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
