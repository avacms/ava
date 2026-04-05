<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;
use Ava\Support\Arr;

/**
 * Content Query
 *
 * Fluent query builder for content, operating on cached indexes.
 * Supports WP-style parameters.
 */
final class Query
{
    private Application $app;
    private Repository $repository;

    // Query parameters
    private ?string $type = null;
    private ?string $status = null;
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
     * Get search config for a content type.
     */
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
     */
    public function published(): self
    {
        return $this->status('published');
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

    /**
     * Set ordering.
     */
    public function orderBy(string $field, string $direction = 'desc'): self
    {
        $clone = clone $this;
        $clone->orderBy = $field;
        $clone->order = strtolower($direction);
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

    /**
     * Set current page.
     */
    public function page(int $page): self
    {
        $clone = clone $this;
        $clone->page = max(1, $page);
        $clone->results = null;
        return $clone;
    }

    /**
     * Set search query.
     */
    public function search(string $query): self
    {
        $clone = clone $this;
        $clone->search = trim($query);
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
     */
    public function fromParams(array $params): self
    {
        $clone = clone $this;

        if (isset($params['type'])) {
            $clone->type = $params['type'];
        }
        if (isset($params['status'])) {
            $clone->status = $params['status'];
        }
        if (isset($params['orderby'])) {
            $clone->orderBy = $params['orderby'];
        }
        if (isset($params['order'])) {
            $clone->order = strtolower($params['order']);
        }
        if (isset($params['per_page'])) {
            $clone->perPage = max(1, min(100, (int) $params['per_page']));
        }
        if (isset($params['paged'])) {
            $clone->page = max(1, (int) $params['paged']);
        }
        if (isset($params['q']) || isset($params['search'])) {
            $clone->search = trim($params['q'] ?? $params['search'] ?? '');
        }

        // Taxonomy filters (tax_<taxonomy>=term)
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'tax_')) {
                $taxonomy = substr($key, 4);
                $clone->taxonomyFilters[$taxonomy] = $value;
            }
        }

        $clone->results = null;
        return $clone;
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
        
        // Must be querying published content (or no status filter)
        if ($this->status !== null && $this->status !== 'published') {
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
        // Get raw data (arrays, not Item objects)
        $rawItems = [];
        if ($this->type !== null) {
            $rawItems = $this->repository->allRaw($this->type);
        } else {
            // Query across all types
            foreach ($this->repository->types() as $type) {
                $rawItems = array_merge($rawItems, $this->repository->allRaw($type));
            }
        }

        // Apply filters on raw arrays
        $rawItems = QueryProcessor::applyFilters(
            $rawItems,
            $this->status,
            $this->taxonomyFilters,
            $this->fieldFilters
        );

        // Apply search if present
        if ($this->search !== null && $this->search !== '') {
            $tokens = QueryProcessor::tokenize($this->search);
            $expandedTokens = QueryProcessor::expandTokens(
                $tokens,
                $this->repository->getStopWords(),
                $this->repository->getSynonyms()
            );

            if (empty($expandedTokens)) {
                // All tokens were stop words
                $rawItems = [];
            } else {
                $rawItems = QueryProcessor::applySearch(
                    $rawItems,
                    $this->search,
                    $expandedTokens,
                    $this->searchWeights
                );
            }
        }

        // Store total count before pagination
        $this->totalCount = count($rawItems);

        // Sort on raw arrays (skip if search is active - already sorted by relevance)
        if ($this->search === null || $this->search === '') {
            $rawItems = QueryProcessor::applySort($rawItems, $this->orderBy, $this->order);
        }

        // Paginate - get just the slice we need
        $offset = ($this->page - 1) * $this->perPage;
        $slice = array_slice($rawItems, $offset, $this->perPage);

        // Only NOW create Item objects for the final result
        $this->results = array_map(
            fn(array $data) => Item::fromArray($data, ''),
            $slice
        );
    }
}
