<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\QueryProcessor;
use Ava\Testing\TestCase;

/**
 * Tests for the QueryProcessor.
 *
 * Covers filtering, sorting, and search scoring logic used by
 * both ArrayBackend and the Query class.
 */
final class QueryProcessorTest extends TestCase
{
    // =========================================================================
    // Filtering - Status
    // =========================================================================

    public function testFilterByStatusPublished(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published'],
            ['title' => 'B', 'status' => 'draft'],
            ['title' => 'C', 'status' => 'published'],
        ];

        $result = QueryProcessor::applyFilters($items, 'published', [], []);

        $this->assertCount(2, $result);
    }

    public function testFilterByStatusDraft(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published'],
            ['title' => 'B', 'status' => 'draft'],
        ];

        $result = QueryProcessor::applyFilters($items, 'draft', [], []);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('B', $titles);
    }

    public function testFilterNullStatusReturnsAll(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published'],
            ['title' => 'B', 'status' => 'draft'],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], []);

        $this->assertCount(2, $result);
    }

    public function testFilterDefaultsToPublishedWhenStatusMissing(): void
    {
        $items = [
            ['title' => 'A'],  // no status field
        ];

        // Should match because default status is 'published'
        $result = QueryProcessor::applyFilters($items, 'published', [], []);
        $this->assertCount(1, $result);

        // Should not match draft filter
        $result = QueryProcessor::applyFilters($items, 'draft', [], []);
        $this->assertCount(0, $result);
    }

    // =========================================================================
    // Filtering - Taxonomies
    // =========================================================================

    public function testFilterByTaxonomyTopLevel(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published', 'taxonomies' => ['category' => ['php', 'js']]],
            ['title' => 'B', 'status' => 'published', 'taxonomies' => ['category' => ['python']]],
        ];

        $result = QueryProcessor::applyFilters($items, null, ['category' => 'php'], []);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('A', $titles);
    }

    public function testFilterByTaxonomyFrontmatterLocation(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published', 'frontmatter' => ['category' => ['php']]],
            ['title' => 'B', 'status' => 'published', 'frontmatter' => ['category' => ['python']]],
        ];

        $result = QueryProcessor::applyFilters($items, null, ['category' => 'php'], []);

        $this->assertCount(1, $result);
    }

    public function testFilterByTaxonomyStringNormalization(): void
    {
        // When taxonomy is a single string in frontmatter, not array
        $items = [
            ['title' => 'A', 'status' => 'published', 'frontmatter' => ['category' => 'php']],
        ];

        $result = QueryProcessor::applyFilters($items, null, ['category' => 'php'], []);

        $this->assertCount(1, $result);
    }

    public function testFilterByTaxonomyMissingField(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published'],
        ];

        $result = QueryProcessor::applyFilters($items, null, ['category' => 'php'], []);

        $this->assertCount(0, $result);
    }

    // =========================================================================
    // Filtering - Field operators
    // =========================================================================

    /**
     * Each case is [operator, filter value, expected matching titles].
     * Items carry the same three fields so operators are compared like for like.
     */
    public function testFieldFilterOperators(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['order' => 5, 'author' => 'Alice', 'excerpt' => 'PHP Tutorial']],
            ['title' => 'B', 'meta' => ['order' => 10, 'author' => 'Bob', 'excerpt' => 'JavaScript basics']],
            ['title' => 'C', 'meta' => ['order' => 1, 'author' => 'Charlie', 'excerpt' => '']],
        ];

        $cases = [
            ['order', '=', 5, ['A']],
            ['author', '!=', 'Alice', ['B', 'C']],
            ['order', '>', 5, ['B']],
            ['order', '>=', 5, ['A', 'B']],
            ['order', '<', 5, ['C']],
            ['order', '<=', 5, ['A', 'C']],
            ['author', 'in', ['Alice', 'Charlie'], ['A', 'C']],
            ['author', 'not_in', ['Alice'], ['B', 'C']],
            ['excerpt', 'like', 'php', ['A']],
            // like is case-insensitive on both sides.
            ['excerpt', 'like', 'php tutorial', ['A']],
            // An unrecognised operator must not fall through to a match.
            ['author', 'invalid_op', 'Alice', []],
            // Items missing the field never match.
            ['missing', '=', 'anything', []],
        ];

        foreach ($cases as [$field, $operator, $value, $expected]) {
            $result = QueryProcessor::applyFilters($items, null, [], [
                ['field' => $field, 'value' => $value, 'operator' => $operator],
            ]);

            $this->assertEquals(
                $expected,
                array_values(array_column($result, 'title')),
                "$field $operator " . json_encode($value)
            );
        }
    }

    public function testMultipleFiltersAreAnded(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published', 'meta' => ['featured' => true, 'author' => 'Alice']],
            ['title' => 'B', 'status' => 'published', 'meta' => ['featured' => false, 'author' => 'Alice']],
            ['title' => 'C', 'status' => 'draft', 'meta' => ['featured' => true, 'author' => 'Alice']],
            ['title' => 'D', 'status' => 'published', 'meta' => ['featured' => true, 'author' => 'Bob']],
        ];

        $result = QueryProcessor::applyFilters($items, 'published', [], [
            ['field' => 'featured', 'value' => true, 'operator' => '='],
            ['field' => 'author', 'value' => 'Alice', 'operator' => '='],
        ]);

        $this->assertEquals(['A'], array_values(array_column($result, 'title')));
    }

    // =========================================================================
    // Sorting
    // =========================================================================

    public function testSortByFieldAndDirection(): void
    {
        $dated = [
            ['title' => 'Old', 'date' => '2024-01-01'],
            ['title' => 'New', 'date' => '2024-12-01'],
            ['title' => 'Mid', 'date' => '2024-06-01'],
        ];
        $ordered = [
            ['title' => 'C', 'meta' => ['order' => 3, 'priority' => 'low']],
            ['title' => 'A', 'meta' => ['order' => 1, 'priority' => 'high']],
            ['title' => 'B', 'meta' => ['order' => 2, 'priority' => 'low']],
        ];
        // Equal dates must fall back to title ascending for a stable order.
        $tied = [
            ['title' => 'Zebra', 'date' => '2024-01-01'],
            ['title' => 'Apple', 'date' => '2024-01-01'],
        ];

        $cases = [
            [$dated, 'date', 'desc', ['New', 'Mid', 'Old']],
            [$dated, 'date', 'asc', ['Old', 'Mid', 'New']],
            [$dated, 'title', 'asc', ['Mid', 'New', 'Old']],
            [$ordered, 'order', 'asc', ['A', 'B', 'C']],
            [$ordered, 'priority', 'asc', ['A', 'B', 'C']],
            [$tied, 'date', 'asc', ['Apple', 'Zebra']],
        ];

        foreach ($cases as [$items, $field, $direction, $expected]) {
            $this->assertEquals(
                $expected,
                array_column(QueryProcessor::applySort($items, $field, $direction), 'title'),
                "$field $direction"
            );
        }
    }

    // =========================================================================
    // Search scoring
    // =========================================================================

    public function testSearchTitlePhraseMatch(): void
    {
        $items = [
            ['title' => 'PHP Tutorial', 'excerpt' => '', 'body' => ''],
            ['title' => 'JavaScript Guide', 'excerpt' => '', 'body' => ''],
        ];

        $result = QueryProcessor::applySearch($items, 'php tutorial', [['php'], ['tutorial']], null);

        $this->assertCount(1, $result);
        $this->assertEquals('PHP Tutorial', $result[0]['title']);
    }

    public function testSearchTokenMatchesAcrossFields(): void
    {
        $items = [
            ['title' => 'My Guide', 'excerpt' => 'Learn PHP basics', 'body' => ''],
            ['title' => 'Other', 'excerpt' => '', 'body' => ''],
        ];

        $result = QueryProcessor::applySearch($items, 'php', [['php']], null);

        $this->assertCount(1, $result);
        $this->assertEquals('My Guide', $result[0]['title']);
    }

    public function testSearchBodyMatch(): void
    {
        $items = [
            ['title' => 'A', 'excerpt' => '', 'body' => 'This covers PHP patterns.'],
            ['title' => 'B', 'excerpt' => '', 'body' => 'This covers JavaScript.'],
        ];

        $result = QueryProcessor::applySearch($items, 'php', [['php']], null);

        $this->assertCount(1, $result);
        $this->assertEquals('A', $result[0]['title']);
    }

    public function testSearchTitleMatchScoresHigher(): void
    {
        $items = [
            ['title' => 'PHP Mastery', 'excerpt' => '', 'body' => ''],
            ['title' => 'Web Development', 'excerpt' => '', 'body' => 'Uses PHP extensively.'],
        ];

        $result = QueryProcessor::applySearch($items, 'php', [['php']], null);

        // Title match should score higher than body match
        $this->assertCount(2, $result);
        $this->assertEquals('PHP Mastery', $result[0]['title']);
    }

    public function testFeaturedItemsAreBoostedNotMatched(): void
    {
        // The featured bonus used to count as a match, so featured items
        // appeared in the results of every search.
        $items = [
            ['title' => 'Unrelated', 'excerpt' => '', 'body' => 'nothing here', 'meta' => ['featured' => true]],
            ['title' => 'Relevant', 'excerpt' => '', 'body' => 'php stuff', 'meta' => []],
        ];

        $result = QueryProcessor::applySearch($items, 'php', [['php']], null);

        $this->assertEquals(['Relevant'], array_column($result, 'title'));
    }

    public function testSearchScoresListAndNumericFields(): void
    {
        $items = [['title' => 1984, 'excerpt' => '', 'body' => '', 'meta' => ['tags' => ['php', 'cms']]]];

        $this->assertCount(1, QueryProcessor::applySearch($items, 'cms', [['cms']], ['fields' => ['tags']]));
        $this->assertCount(1, QueryProcessor::applySearch($items, '1984', [['1984']], null));
    }

    public function testSearchFeaturedBoost(): void
    {
        $items = [
            ['title' => 'Normal', 'excerpt' => '', 'body' => 'php stuff', 'meta' => ['featured' => false]],
            ['title' => 'Featured', 'excerpt' => '', 'body' => 'php stuff', 'meta' => ['featured' => true]],
        ];

        $result = QueryProcessor::applySearch($items, 'php', [['php']], null);

        // Featured item should come first with same content match
        $this->assertEquals('Featured', $result[0]['title']);
    }

    public function testSearchNoMatchReturnsEmpty(): void
    {
        $items = [
            ['title' => 'PHP Guide', 'excerpt' => '', 'body' => ''],
        ];

        $result = QueryProcessor::applySearch($items, 'rust', [['rust']], null);

        $this->assertCount(0, $result);
    }

    public function testSearchWithSynonyms(): void
    {
        $items = [
            ['title' => 'CMS Guide', 'excerpt' => '', 'body' => ''],
            ['title' => 'Other', 'excerpt' => '', 'body' => ''],
        ];

        // "cms" has synonym "content management system" â€” user searched "cms"
        // but title has "cms" directly, so it matches
        $result = QueryProcessor::applySearch($items, 'cms', [['cms', 'content management']], null);

        $this->assertCount(1, $result);
    }

    public function testSearchWithCustomWeights(): void
    {
        $items = [
            ['title' => 'Match', 'excerpt' => '', 'body' => ''],
        ];

        // Zero out title weight, should still match on token
        $weights = ['title_phrase' => 0, 'title_token' => 1, 'title_token_max' => 10];
        $score1 = QueryProcessor::scoreItem(
            $items[0], 'match', [['match']], $weights
        );

        // High title weight
        $weights2 = ['title_phrase' => 100, 'title_token' => 1, 'title_token_max' => 10];
        $score2 = QueryProcessor::scoreItem(
            $items[0], 'match', [['match']], $weights2
        );

        $this->assertGreaterThan($score1, $score2);
    }

    // =========================================================================
    // Tokenization and expansion
    // =========================================================================

    public function testTokenize(): void
    {
        $result = QueryProcessor::tokenize('PHP  tutorial  basics');
        $this->assertCount(3, $result);
        $this->assertContains('php', $result);
        $this->assertContains('tutorial', $result);
        $this->assertContains('basics', $result);
    }

    public function testTokenizeEmptyString(): void
    {
        $result = QueryProcessor::tokenize('');
        $this->assertCount(0, $result);
    }

    public function testExpandTokensFiltersStopWords(): void
    {
        $stopWords = ['the' => true, 'is' => true, 'a' => true];
        $result = QueryProcessor::expandTokens(['the', 'php', 'is', 'great'], $stopWords, []);

        // Should filter out stop words, keep 'php' and 'great'
        $this->assertCount(2, $result);
    }

    public function testExpandTokensCapsSearchWorkAfterStopWords(): void
    {
        $tokens = array_merge(['the'], array_map('strval', range(1, 100)));
        $result = QueryProcessor::expandTokens($tokens, ['the' => true], []);

        $this->assertCount(10, $result);
        $this->assertContains('1', $result[0]);
    }

    public function testExpandTokensWithSynonyms(): void
    {
        $synonyms = ['cms' => ['content management system']];
        $result = QueryProcessor::expandTokens(['cms'], [], $synonyms);

        $this->assertCount(1, $result);
        $this->assertContains('cms', $result[0]);
        $this->assertContains('content management system', $result[0]);
    }

    public function testExpandTokensAllStopWordsReturnsEmpty(): void
    {
        $stopWords = ['the' => true, 'is' => true];
        $result = QueryProcessor::expandTokens(['the', 'is'], $stopWords, []);

        $this->assertCount(0, $result);
    }

    // =========================================================================
    // matchesAny helper
    // =========================================================================

    public function testMatchesAnyFindsMatch(): void
    {
        $this->assertTrue(QueryProcessor::matchesAny('hello world', ['world', 'foo']));
    }

    public function testMatchesAnyNoMatch(): void
    {
        $this->assertFalse(QueryProcessor::matchesAny('hello world', ['foo', 'bar']));
    }
}
