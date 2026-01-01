<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Query;
use Ava\Testing\TestCase;

/**
 * Tests for the Query builder class.
 * 
 * These tests leverage dependency injection to test query building
 * with the real application and repository.
 */
final class QueryTest extends TestCase
{
    /**
     * Create a new query instance.
     */
    private function createQuery(): Query
    {
        return new Query($this->app);
    }

    // =========================================================================
    // Application Integration
    // =========================================================================

    public function testApplicationHasQueryMethod(): void
    {
        $query = $this->app->query();
        
        $this->assertInstanceOf(Query::class, $query);
    }

    public function testApplicationQueryReturnsNewInstanceEachTime(): void
    {
        $query1 = $this->app->query();
        $query2 = $this->app->query();
        
        $this->assertNotSame($query1, $query2);
    }

    // =========================================================================
    // Query Building (Immutability)
    // =========================================================================

    public function testQueryMethodsReturnNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->type('post');

        $this->assertNotSame($query1, $query2);
    }

    public function testStatusMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->status('published');

        $this->assertNotSame($query1, $query2);
    }

    public function testPublishedIsShortcutForStatus(): void
    {
        $query = $this->createQuery()->published();

        // Should return results (may be empty array but not error)
        $results = $query->get();
        $this->assertIsArray($results);
    }

    public function testWhereMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->where('featured', true);

        $this->assertNotSame($query1, $query2);
    }

    public function testWhereTaxMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->whereTax('category', 'tutorials');

        $this->assertNotSame($query1, $query2);
    }

    public function testOrderByMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->orderBy('title', 'asc');

        $this->assertNotSame($query1, $query2);
    }

    public function testPerPageMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->perPage(5);

        $this->assertNotSame($query1, $query2);
    }

    public function testPageMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->page(2);

        $this->assertNotSame($query1, $query2);
    }

    public function testSearchMethodReturnsNewInstance(): void
    {
        $query1 = $this->createQuery();
        $query2 = $query1->search('test');

        $this->assertNotSame($query1, $query2);
    }

    // =========================================================================
    // Method Chaining
    // =========================================================================

    public function testMethodChainingWorks(): void
    {
        $query = $this->createQuery()
            ->type('post')
            ->published()
            ->orderBy('date', 'desc')
            ->perPage(10)
            ->page(1);

        $this->assertInstanceOf(Query::class, $query);
    }

    public function testComplexQueryChaining(): void
    {
        $query = $this->createQuery()
            ->type('post')
            ->status('published')
            ->whereTax('category', 'tutorials')
            ->where('featured', true)
            ->orderBy('title', 'asc')
            ->perPage(5);

        $results = $query->get();
        $this->assertIsArray($results);
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    public function testPerPageCapsAt100(): void
    {
        $query = $this->createQuery()->perPage(999);
        $pagination = $query->pagination();

        $this->assertEquals(100, $pagination['per_page']);
    }

    public function testPerPageMinimumIs1(): void
    {
        $query = $this->createQuery()->perPage(0);
        $pagination = $query->pagination();

        $this->assertEquals(1, $pagination['per_page']);
    }

    public function testPageMinimumIs1(): void
    {
        $query = $this->createQuery()->page(0);

        $this->assertEquals(1, $query->currentPage());
    }

    public function testPaginationReturnsCorrectStructure(): void
    {
        $query = $this->createQuery()->type('post')->published();
        $pagination = $query->pagination();

        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertArrayHasKey('has_more', $pagination);
        $this->assertArrayHasKey('has_previous', $pagination);
    }

    public function testHasPreviousIsFalseOnFirstPage(): void
    {
        $query = $this->createQuery()->page(1);

        $this->assertFalse($query->hasPrevious());
    }

    public function testHasPreviousIsTrueOnLaterPages(): void
    {
        $query = $this->createQuery()->page(2);

        $this->assertTrue($query->hasPrevious());
    }

    // =========================================================================
    // Results
    // =========================================================================

    public function testGetReturnsArray(): void
    {
        $results = $this->createQuery()->type('post')->get();

        $this->assertIsArray($results);
    }

    public function testFirstReturnsItemOrNull(): void
    {
        $result = $this->createQuery()->type('post')->published()->first();

        // Can be Item or null depending on content
        $this->assertTrue($result === null || $result instanceof \Ava\Content\Item);
    }

    public function testCountReturnsInteger(): void
    {
        $count = $this->createQuery()->type('post')->count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testIsEmptyReturnsBoolean(): void
    {
        $isEmpty = $this->createQuery()->type('nonexistent')->isEmpty();

        $this->assertTrue($isEmpty);
    }

    // =========================================================================
    // FromParams (WP-style)
    // =========================================================================

    public function testFromParamsAcceptsType(): void
    {
        $query = $this->createQuery()->fromParams(['type' => 'post']);
        $results = $query->get();

        $this->assertIsArray($results);
    }

    public function testFromParamsAcceptsOrderby(): void
    {
        $query = $this->createQuery()
            ->type('post')
            ->fromParams(['orderby' => 'title', 'order' => 'asc']);

        $results = $query->get();
        $this->assertIsArray($results);
    }

    public function testFromParamsAcceptsPerPage(): void
    {
        $query = $this->createQuery()->fromParams(['per_page' => 5]);
        $pagination = $query->pagination();

        $this->assertEquals(5, $pagination['per_page']);
    }

    public function testFromParamsAcceptsPaged(): void
    {
        $query = $this->createQuery()->fromParams(['paged' => 3]);

        $this->assertEquals(3, $query->currentPage());
    }

    public function testFromParamsAcceptsTaxFilter(): void
    {
        $query = $this->createQuery()
            ->type('post')
            ->fromParams(['tax_category' => 'tutorials']);

        $results = $query->get();
        $this->assertIsArray($results);
    }

    public function testFromParamsAcceptsSearch(): void
    {
        $query = $this->createQuery()->fromParams(['q' => 'hello']);
        $results = $query->get();

        $this->assertIsArray($results);
    }

    public function testFromParamsAcceptsSearchAlternative(): void
    {
        $query = $this->createQuery()->fromParams(['search' => 'world']);
        $results = $query->get();

        $this->assertIsArray($results);
    }
}
