<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Content\Backends\BackendInterface;

/**
 * Query Processor
 *
 * Shared array query execution and relevance scoring for both backends.
 *
 * All methods are stateless and operate on raw item arrays.
 */
final class QueryProcessor
{
    private const int MAX_SEARCH_TOKENS = 10;

    /**
     * Execute an array query. SQLite uses this only for relevance search.
     *
     * @return array{items: array, total: int}
     */
    /**
     * @param callable(array): string|null $bodyOf Looks up an item's body for
     *        scoring, so search need not copy every body into its item.
     *        Without it, bodies are loaded into the items.
     */
    public static function query(BackendInterface $backend, array $params, ?callable $bodyOf = null): array
    {
        $types = $params['types'] ?? (isset($params['type']) ? [$params['type']] : $backend->types());
        $search = $params['search'] ?? '';
        $items = [];
        foreach ($types as $type) {
            // Append values: associative content keys may repeat across types.
            foreach ($backend->allRaw($type, withBody: $search !== '' && $bodyOf === null) as $item) {
                $items[] = $item;
            }
        }

        $items = self::applyFilters(
            $items,
            $params['status'] ?? null,
            $params['taxonomies'] ?? [],
            $params['fields'] ?? []
        );

        if ($search !== '') {
            $tokens = self::expandTokens(
                self::tokenize($search),
                $params['stopWords'] ?? [],
                $params['synonyms'] ?? []
            );
            $items = $tokens === [] ? [] : self::applySearch($items, $search, $tokens, $params['searchWeights'] ?? null, $bodyOf);
        } else {
            $items = self::applySort($items, $params['orderBy'] ?? 'date', strtolower($params['order'] ?? 'desc'));
        }

        $perPage = $params['perPage'] ?? 10;
        $offset = (($params['page'] ?? 1) - 1) * $perPage;
        return ['items' => array_slice($items, $offset, $perPage), 'total' => count($items)];
    }

    /**
     * Filter raw items by status, taxonomy, and field conditions.
     */
    public static function applyFilters(
        array $items,
        ?string $status,
        array $taxonomies,
        array $fields
    ): array {
        // Terms compare by slug, so "Web Dev", "web-dev" and a URL all agree.
        $taxonomies = array_map(static fn($term) => Terms::slug((string) $term), $taxonomies);

        return array_filter($items, function (array $data) use ($status, $taxonomies, $fields) {
            // Status filter
            if ($status !== null) {
                $itemStatus = $data['status'] ?? 'published';
                if ($itemStatus !== $status) {
                    return false;
                }
            }

            // Taxonomy filters: indexed items carry their terms as slugs already
            foreach ($taxonomies as $taxonomy => $term) {
                $slugs = $data['taxonomies'][$taxonomy] ?? null;
                if (!is_array($slugs)) {
                    $terms = $data['frontmatter'][$taxonomy] ?? [];
                    $terms = array_filter(is_array($terms) ? $terms : [$terms], static fn($value) => is_scalar($value));
                    $slugs = Terms::slugs(array_map('strval', $terms));
                }

                if (!in_array($term, $slugs, true)) {
                    return false;
                }
            }

            // Field filters
            foreach ($fields as $filter) {
                if (!self::matchesFieldFilter($data, $filter)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if a raw item matches a single field filter.
     */
    public static function matchesFieldFilter(array $data, array $filter): bool
    {
        $field = $filter['field'];
        $expected = $filter['value'];
        $operator = $filter['operator'];

        // Check both meta and frontmatter for compatibility
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
     * Sort raw items by a field and direction with title tie-breaker.
     */
    public static function applySort(array $items, string $orderBy, string $order): array
    {
        $keys = [];
        $titles = [];
        foreach ($items as $item) {
            $keys[] = self::getSortValue($item, $orderBy);
            $titles[] = $item['title'] ?? '';
        }
        $positions = array_keys($keys);
        $items = array_values($items);

        // Ties: title ascending, then original order
        array_multisort($keys, $order === 'desc' ? SORT_DESC : SORT_ASC, $titles, SORT_ASC, $positions, $items);

        return $items;
    }

    /**
     * Get the value to sort by from raw item data.
     */
    public static function getSortValue(array $data, string $orderBy): mixed
    {
        $meta = $data['meta'] ?? $data['frontmatter'] ?? [];
        return match ($orderBy) {
            'date' => $data['date'] ?? 0,
            'updated' => $data['updated'] ?? $data['date'] ?? 0,
            'title' => strtolower($data['title'] ?? ''),
            'order', 'menu_order' => $meta['order'] ?? $data['order'] ?? 0,
            default => $meta[$orderBy] ?? $data[$orderBy] ?? '',
        };
    }

    /**
     * Apply search scoring and filter to raw items. Returns items sorted by relevance.
     *
     * @param array $items Raw item arrays
     * @param string $search Search query string
     * @param array $expandedTokens Array of token groups (each group = [original, ...synonyms])
     * @param array|null $weights Custom scoring weights (null = defaults)
     */
    public static function applySearch(
        array $items,
        string $search,
        array $expandedTokens,
        ?array $weights = null,
        ?callable $bodyOf = null
    ): array {
        $phrase = strtolower($search);
        $expandedTokens = array_slice($expandedTokens, 0, self::MAX_SEARCH_TOKENS);

        $scored = [];
        foreach ($items as $data) {
            $score = self::scoreItem($data, $phrase, $expandedTokens, $weights, $bodyOf === null ? null : $bodyOf($data));
            if ($score > 0) {
                $scored[] = ['data' => $data, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($s) => $s['data'], $scored);
    }

    /**
     * Score a single item for search relevance.
     *
     * @param array $data Raw item data
     * @param string $phrase Lowercased search phrase
     * @param array $expandedTokens Array of token groups (each group = [original, ...synonyms])
     * @param array|null $weights Custom scoring weights (null = defaults)
     */
    public static function scoreItem(
        array $data,
        string $phrase,
        array $expandedTokens,
        ?array $weights = null,
        ?string $body = null
    ): int {
        $score = 0;
        $meta = $data['meta'] ?? $data['frontmatter'] ?? [];
        $title = strtolower(self::searchableText($data['title'] ?? ''));
        $excerpt = strtolower(self::searchableText($meta['excerpt'] ?? $data['excerpt'] ?? ''));
        $body = strtolower(self::searchableText($body ?? $data['body'] ?? $meta['body'] ?? ''));

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
        ], $weights ?? []);

        // Title phrase match (exact only)
        if ($w['title_phrase'] > 0 && str_contains($title, $phrase)) {
            $score += $w['title_phrase'];
        }

        // Title token matches (with synonyms)
        $titleHits = 0;
        foreach ($expandedTokens as $variants) {
            if (self::matchesAny($title, $variants)) {
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
                if (self::matchesAny($excerpt, $variants)) {
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
                if (self::matchesAny($body, $variants)) {
                    $hits++;
                }
            }
            $score += min($w['body_token_max'], $hits * $w['body_token']);
        }

        // Custom field matches
        if (!empty($w['fields'])) {
            foreach ($w['fields'] as $field) {
                $value = strtolower(self::searchableText($meta[$field] ?? ''));
                if ($value !== '') {
                    foreach ($expandedTokens as $variants) {
                        if (self::matchesAny($value, $variants)) {
                            $score += $w['field_weight'];
                        }
                    }
                }
            }
        }

        // Featured boost: ranks matches higher, but is not itself a match.
        if ($score > 0 && $w['featured'] > 0 && (!empty($meta['featured']) || !empty($data['featured']))) {
            $score += $w['featured'];
        }

        return $score;
    }

    /**
     * Flatten a frontmatter value (scalar or list) into searchable text.
     */
    public static function searchableText(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map(self::searchableText(...), $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Check if any variant in a token group matches the text.
     */
    public static function matchesAny(string $text, array $variants): bool
    {
        foreach ($variants as $v) {
            if (str_contains($text, $v)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tokenize a search query string into individual tokens.
     */
    public static function tokenize(string $search): array
    {
        $query = strtolower($search);
        return preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Expand tokens with synonyms and filter stop words.
     *
     * @return array Array of token groups (each group = [original, ...synonyms])
     *               Returns empty array if all tokens were stop words.
     */
    public static function expandTokens(array $tokens, array $stopWords, array $synonyms): array
    {
        $tokens = array_values(array_filter($tokens, fn($t) => !isset($stopWords[$t])));
        if (empty($tokens)) {
            return [];
        }
        $tokens = array_slice($tokens, 0, self::MAX_SEARCH_TOKENS);

        return array_map(
            fn($t) => array_unique(array_merge([$t], $synonyms[$t] ?? [])),
            $tokens
        );
    }
}
