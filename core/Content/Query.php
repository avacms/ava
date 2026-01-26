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
        $rawItems = $this->applyFiltersRaw($rawItems);

        // Apply search if present
        if ($this->search !== null && $this->search !== '') {
            $rawItems = $this->applySearchRaw($rawItems);
        }

        // Store total count before pagination
        $this->totalCount = count($rawItems);

        // Sort on raw arrays (skip if search is active - already sorted by relevance)
        if ($this->search === null || $this->search === '') {
            $rawItems = $this->applySortRaw($rawItems);
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

    /**
     * Apply filters to raw item arrays.
     */
    private function applyFiltersRaw(array $items): array
    {
        return array_filter($items, function (array $data) {
            // Status filter
            if ($this->status !== null) {
                $itemStatus = $data['status'] ?? 'published';
                if ($itemStatus !== $this->status) {
                    return false;
                }
            }

            // Taxonomy filters
            foreach ($this->taxonomyFilters as $taxonomy => $term) {
                // Check both locations: top-level 'taxonomies' (recent_cache format)
                // and frontmatter (content_index format)
                $terms = $data['taxonomies'][$taxonomy] 
                    ?? $data['frontmatter'][$taxonomy] 
                    ?? [];
                
                // Normalize to array (taxonomy can be string or array in frontmatter)
                if (!is_array($terms)) {
                    $terms = [$terms];
                }
                
                if (!in_array($term, $terms, true)) {
                    return false;
                }
            }

            // Field filters
            foreach ($this->fieldFilters as $filter) {
                if (!$this->matchesFieldFilterRaw($data, $filter)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if raw item data matches a field filter.
     */
    private function matchesFieldFilterRaw(array $data, array $filter): bool
    {
        $field = $filter['field'];
        $expected = $filter['value'];
        $operator = $filter['operator'];

        // Get value from nested data (check both meta and frontmatter for compatibility)
        $meta = $data['meta'] ?? $data['frontmatter'] ?? [];
        $value = $meta[$field] ?? $data[$field] ?? null;

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
     * Apply search to raw item arrays.
     */
    private function applySearchRaw(array $items): array
    {
        // Compute search phrase and tokens once, outside the per-item loop
        $phrase = strtolower($this->search);
        $tokens = preg_split('/\s+/', $phrase, -1, PREG_SPLIT_NO_EMPTY);

        // Filter stop words, expand with synonyms
        $stopWords = $this->repository->getStopWords();
        $tokens = array_values(array_filter($tokens, fn($t) => !isset($stopWords[$t])));
        if (empty($tokens)) {
            return []; // All tokens were stop words
        }
        $synonyms = $this->repository->getSynonyms();
        $expandedTokens = array_map(
            fn($t) => array_unique(array_merge([$t], $synonyms[$t] ?? [])),
            $tokens
        );

        // Score each item
        $scored = [];
        foreach ($items as $data) {
            $score = $this->scoreItemRaw($data, $phrase, $expandedTokens);
            if ($score > 0) {
                $scored[] = ['data' => $data, 'score' => $score];
            }
        }

        // Sort by score descending if search is active
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($s) => $s['data'], $scored);
    }

    /**
     * Check if any variant in a token group matches the text.
     */
    private function matchesAny(string $text, array $variants): bool
    {
        foreach ($variants as $v) {
            if (str_contains($text, $v)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Score raw item data for search relevance.
     */
    private function scoreItemRaw(array $data, string $phrase, array $expandedTokens): int
    {
        $score = 0;
        $meta = $data['meta'] ?? $data['frontmatter'] ?? [];
        $title = strtolower($data['title'] ?? '');
        $excerpt = strtolower($meta['excerpt'] ?? $data['excerpt'] ?? '');
        $body = strtolower($data['body'] ?? $meta['body'] ?? '');

        // Get weights with defaults
        $w = array_merge([
            'title_phrase' => 80,
            'title_all_tokens' => 40,
            'title_token' => 10,
            'title_token_max' => 30,
            'excerpt_phrase' => 30,
            'excerpt_token' => 3,
            'excerpt_token_max' => 15,
            'body_phrase' => 20,
            'body_token' => 2,
            'body_token_max' => 10,
            'featured' => 15,
            'fields' => [],
            'field_weight' => 5,
        ], $this->searchWeights ?? []);

        // Title phrase match (exact only)
        if ($w['title_phrase'] > 0 && str_contains($title, $phrase)) {
            $score += $w['title_phrase'];
        }

        // Title token matches (with synonyms)
        $titleHits = 0;
        foreach ($expandedTokens as $variants) {
            if ($this->matchesAny($title, $variants)) {
                $titleHits++;
            }
        }
        if ($titleHits === count($expandedTokens) && count($expandedTokens) > 1 && $w['title_all_tokens'] > 0) {
            $score += $w['title_all_tokens'];
        }
        if ($w['title_token'] > 0) {
            $score += min($w['title_token_max'], $titleHits * $w['title_token']);
        }

        // Excerpt phrase match
        if ($w['excerpt_phrase'] > 0 && str_contains($excerpt, $phrase)) {
            $score += $w['excerpt_phrase'];
        }

        // Excerpt token matches
        if ($w['excerpt_token'] > 0) {
            $hits = 0;
            foreach ($expandedTokens as $variants) {
                if ($this->matchesAny($excerpt, $variants)) {
                    $hits++;
                }
            }
            $score += min($w['excerpt_token_max'], $hits * $w['excerpt_token']);
        }

        // Body phrase match
        if ($w['body_phrase'] > 0 && str_contains($body, $phrase)) {
            $score += $w['body_phrase'];
        }

        // Body token matches
        if ($w['body_token'] > 0) {
            $hits = 0;
            foreach ($expandedTokens as $variants) {
                if ($this->matchesAny($body, $variants)) {
                    $hits++;
                }
            }
            $score += min($w['body_token_max'], $hits * $w['body_token']);
        }

        // Custom field matches (use $meta already defined above)
        if (!empty($w['fields'])) {
            foreach ($w['fields'] as $field) {
                $value = strtolower((string) ($meta[$field] ?? ''));
                if ($value !== '') {
                    foreach ($expandedTokens as $variants) {
                        if ($this->matchesAny($value, $variants)) {
                            $score += $w['field_weight'];
                        }
                    }
                }
            }
        }

        // Featured boost
        if ($w['featured'] > 0 && (!empty($meta['featured']) || !empty($data['featured']))) {
            $score += $w['featured'];
        }

        return $score;
    }

    /**
     * Apply sorting to raw item arrays.
     */
    private function applySortRaw(array $items): array
    {
        usort($items, function (array $a, array $b) {
            $aVal = $this->getSortValueRaw($a);
            $bVal = $this->getSortValueRaw($b);

            $result = $aVal <=> $bVal;

            // Descending order reverses the comparison
            if ($this->order === 'desc') {
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
     * Get the value to sort by from raw data.
     */
    private function getSortValueRaw(array $data): mixed
    {
        $meta = $data['meta'] ?? $data['frontmatter'] ?? [];
        return match ($this->orderBy) {
            'date' => $data['date'] ?? 0,
            'updated' => $data['updated'] ?? $data['date'] ?? 0,
            'title' => strtolower($data['title'] ?? ''),
            'order', 'menu_order' => $meta['order'] ?? $data['order'] ?? 0,
            default => $meta[$this->orderBy] ?? $data[$this->orderBy] ?? '',
        };
    }
}
