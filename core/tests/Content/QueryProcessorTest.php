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

    public function testFieldFilterEquals(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['featured' => true]],
            ['title' => 'B', 'meta' => ['featured' => false]],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'featured', 'value' => true, 'operator' => '='],
        ]);

        $this->assertCount(1, $result);
    }

    public function testFieldFilterNotEquals(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['author' => 'Alice']],
            ['title' => 'B', 'meta' => ['author' => 'Bob']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'author', 'value' => 'Alice', 'operator' => '!='],
        ]);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('B', $titles);
    }

    public function testFieldFilterGreaterThan(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['order' => 5]],
            ['title' => 'B', 'meta' => ['order' => 10]],
            ['title' => 'C', 'meta' => ['order' => 1]],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'order', 'value' => 5, 'operator' => '>'],
        ]);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('B', $titles);
    }

    public function testFieldFilterGreaterThanOrEqual(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['order' => 5]],
            ['title' => 'B', 'meta' => ['order' => 10]],
            ['title' => 'C', 'meta' => ['order' => 1]],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'order', 'value' => 5, 'operator' => '>='],
        ]);

        $this->assertCount(2, $result);
    }

    public function testFieldFilterLessThan(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['order' => 5]],
            ['title' => 'B', 'meta' => ['order' => 10]],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'order', 'value' => 10, 'operator' => '<'],
        ]);

        $this->assertCount(1, $result);
    }

    public function testFieldFilterLessThanOrEqual(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['order' => 5]],
            ['title' => 'B', 'meta' => ['order' => 10]],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'order', 'value' => 10, 'operator' => '<='],
        ]);

        $this->assertCount(2, $result);
    }

    public function testFieldFilterIn(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['author' => 'Alice']],
            ['title' => 'B', 'meta' => ['author' => 'Bob']],
            ['title' => 'C', 'meta' => ['author' => 'Charlie']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'author', 'value' => ['Alice', 'Charlie'], 'operator' => 'in'],
        ]);

        $this->assertCount(2, $result);
    }

    public function testFieldFilterNotIn(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['author' => 'Alice']],
            ['title' => 'B', 'meta' => ['author' => 'Bob']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'author', 'value' => ['Alice'], 'operator' => 'not_in'],
        ]);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('B', $titles);
    }

    public function testFieldFilterLike(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['excerpt' => 'PHP tutorial for beginners']],
            ['title' => 'B', 'meta' => ['excerpt' => 'JavaScript basics']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'excerpt', 'value' => 'php', 'operator' => 'like'],
        ]);

        $this->assertCount(1, $result);
    }

    public function testFieldFilterLikeIsCaseInsensitive(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['excerpt' => 'PHP Tutorial']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'excerpt', 'value' => 'php tutorial', 'operator' => 'like'],
        ]);

        $this->assertCount(1, $result);
    }

    public function testFieldFilterUnknownOperatorReturnsFalse(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['foo' => 'bar']],
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'foo', 'value' => 'bar', 'operator' => 'invalid_op'],
        ]);

        $this->assertCount(0, $result);
    }

    public function testFieldFilterNullValueHandling(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['featured' => true]],
            ['title' => 'B', 'meta' => []],  // field not set
        ];

        $result = QueryProcessor::applyFilters($items, null, [], [
            ['field' => 'featured', 'value' => true, 'operator' => '='],
        ]);

        $this->assertCount(1, $result);
    }

    public function testMultipleFiltersApplied(): void
    {
        $items = [
            ['title' => 'A', 'status' => 'published', 'meta' => ['featured' => true, 'author' => 'Alice']],
            ['title' => 'B', 'status' => 'published', 'meta' => ['featured' => false, 'author' => 'Alice']],
            ['title' => 'C', 'status' => 'published', 'meta' => ['featured' => true, 'author' => 'Bob']],
        ];

        $result = QueryProcessor::applyFilters($items, 'published', [], [
            ['field' => 'featured', 'value' => true, 'operator' => '='],
            ['field' => 'author', 'value' => 'Alice', 'operator' => '='],
        ]);

        $this->assertCount(1, $result);
        $titles = array_column($result, 'title');
        $this->assertContains('A', $titles);
    }

    // =========================================================================
    // Sorting
    // =========================================================================

    public function testSortByDateDescending(): void
    {
        $items = [
            ['title' => 'Old', 'date' => '2024-01-01'],
            ['title' => 'New', 'date' => '2024-12-01'],
            ['title' => 'Mid', 'date' => '2024-06-01'],
        ];

        $result = QueryProcessor::applySort($items, 'date', 'desc');

        $this->assertEquals('New', $result[0]['title']);
        $this->assertEquals('Mid', $result[1]['title']);
        $this->assertEquals('Old', $result[2]['title']);
    }

    public function testSortByDateAscending(): void
    {
        $items = [
            ['title' => 'New', 'date' => '2024-12-01'],
            ['title' => 'Old', 'date' => '2024-01-01'],
        ];

        $result = QueryProcessor::applySort($items, 'date', 'asc');

        $this->assertEquals('Old', $result[0]['title']);
        $this->assertEquals('New', $result[1]['title']);
    }

    public function testSortByTitleAlphabetical(): void
    {
        $items = [
            ['title' => 'Zebra', 'date' => '2024-01-01'],
            ['title' => 'Apple', 'date' => '2024-01-01'],
            ['title' => 'Mango', 'date' => '2024-01-01'],
        ];

        $result = QueryProcessor::applySort($items, 'title', 'asc');

        $this->assertEquals('Apple', $result[0]['title']);
        $this->assertEquals('Mango', $result[1]['title']);
        $this->assertEquals('Zebra', $result[2]['title']);
    }

    public function testSortByOrderField(): void
    {
        $items = [
            ['title' => 'C', 'meta' => ['order' => 3]],
            ['title' => 'A', 'meta' => ['order' => 1]],
            ['title' => 'B', 'meta' => ['order' => 2]],
        ];

        $result = QueryProcessor::applySort($items, 'order', 'asc');

        $this->assertEquals('A', $result[0]['title']);
        $this->assertEquals('B', $result[1]['title']);
        $this->assertEquals('C', $result[2]['title']);
    }

    public function testSortTieBreakerByTitle(): void
    {
        $items = [
            ['title' => 'Zebra', 'date' => '2024-01-01'],
            ['title' => 'Apple', 'date' => '2024-01-01'],
        ];

        $result = QueryProcessor::applySort($items, 'date', 'asc');

        // Same date, should tie-break by title ascending
        $this->assertEquals('Apple', $result[0]['title']);
        $this->assertEquals('Zebra', $result[1]['title']);
    }

    public function testSortByCustomField(): void
    {
        $items = [
            ['title' => 'A', 'meta' => ['priority' => 'high']],
            ['title' => 'B', 'meta' => ['priority' => 'low']],
        ];

        $result = QueryProcessor::applySort($items, 'priority', 'asc');

        $this->assertEquals('A', $result[0]['title']);
        $this->assertEquals('B', $result[1]['title']);
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

        // "cms" has synonym "content management system" — user searched "cms"
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
