<?php

declare(strict_types=1);

namespace Ava\Content;

/**
 * Query Processor
 *
 * Shared filtering, sorting, and search scoring logic used by both
 * the ArrayBackend (for backend-level queries) and the Query class
 * (for full-index queries with advanced features like synonyms).
 *
 * All methods are stateless and operate on raw item arrays.
 */
final class QueryProcessor
{
    /**
     * Filter raw items by status, taxonomy, and field conditions.
     */
    public static function applyFilters(
        array $items,
        ?string $status,
        array $taxonomies,
        array $fields
    ): array {
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
        usort($items, function (array $a, array $b) use ($orderBy, $order) {
            $aVal = self::getSortValue($a, $orderBy);
            $bVal = self::getSortValue($b, $orderBy);

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
        ?array $weights = null
    ): array {
        $phrase = strtolower($search);

        $scored = [];
        foreach ($items as $data) {
            $score = self::scoreItem($data, $phrase, $expandedTokens, $weights);
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
    public static function scoreItem(array $data, string $phrase, array $expandedTokens, ?array $weights = null): int
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
                $value = strtolower((string) ($meta[$field] ?? ''));
                if ($value !== '') {
                    foreach ($expandedTokens as $variants) {
                        if (self::matchesAny($value, $variants)) {
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
        return preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
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

        return array_map(
            fn($t) => array_unique(array_merge([$t], $synonyms[$t] ?? [])),
            $tokens
        );
    }
}
