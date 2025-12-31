<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\Arr;
use Ava\Testing\TestCase;

/**
 * Tests for the Arr utility class.
 */
final class ArrTest extends TestCase
{
    // =========================================================================
    // get() - Dot notation access
    // =========================================================================

    public function testGetReturnsTopLevelValue(): void
    {
        $arr = ['name' => 'Ava'];
        $this->assertEquals('Ava', Arr::get($arr, 'name'));
    }

    public function testGetReturnsNestedValue(): void
    {
        $arr = ['site' => ['name' => 'Ava CMS']];
        $this->assertEquals('Ava CMS', Arr::get($arr, 'site.name'));
    }

    public function testGetReturnsDeeplyNestedValue(): void
    {
        $arr = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertEquals('deep', Arr::get($arr, 'a.b.c'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $arr = ['name' => 'Ava'];
        $this->assertEquals('default', Arr::get($arr, 'missing', 'default'));
    }

    public function testGetReturnsDefaultForMissingNestedKey(): void
    {
        $arr = ['site' => []];
        $this->assertNull(Arr::get($arr, 'site.name'));
    }

    public function testGetReturnsNullAsDefault(): void
    {
        $arr = [];
        $this->assertNull(Arr::get($arr, 'missing'));
    }

    // =========================================================================
    // set() - Dot notation assignment
    // =========================================================================

    public function testSetTopLevelValue(): void
    {
        $arr = [];
        Arr::set($arr, 'name', 'Ava');
        $this->assertEquals('Ava', $arr['name']);
    }

    public function testSetNestedValue(): void
    {
        $arr = [];
        Arr::set($arr, 'site.name', 'Ava CMS');
        $this->assertEquals('Ava CMS', $arr['site']['name']);
    }

    public function testSetCreatesMissingIntermediateArrays(): void
    {
        $arr = [];
        Arr::set($arr, 'a.b.c', 'deep');
        $this->assertEquals('deep', $arr['a']['b']['c']);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $arr = ['name' => 'old'];
        Arr::set($arr, 'name', 'new');
        $this->assertEquals('new', $arr['name']);
    }

    // =========================================================================
    // has() - Key existence check
    // =========================================================================

    public function testHasReturnsTrueForExistingKey(): void
    {
        $arr = ['name' => 'Ava'];
        $this->assertTrue(Arr::has($arr, 'name'));
    }

    public function testHasReturnsTrueForNestedKey(): void
    {
        $arr = ['site' => ['name' => 'Ava']];
        $this->assertTrue(Arr::has($arr, 'site.name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $arr = ['name' => 'Ava'];
        $this->assertFalse(Arr::has($arr, 'missing'));
    }

    public function testHasReturnsFalseForEmptyArray(): void
    {
        $this->assertFalse(Arr::has([], 'any'));
    }

    public function testHasReturnsFalseForEmptyKey(): void
    {
        $arr = ['name' => 'Ava'];
        $this->assertFalse(Arr::has($arr, ''));
    }

    // =========================================================================
    // dot() - Flatten to dot notation
    // =========================================================================

    public function testDotFlattensNestedArray(): void
    {
        $arr = ['site' => ['name' => 'Ava', 'url' => 'https://ava.test']];
        $result = Arr::dot($arr);

        $this->assertEquals('Ava', $result['site.name']);
        $this->assertEquals('https://ava.test', $result['site.url']);
    }

    public function testDotHandlesDeeplyNested(): void
    {
        $arr = ['a' => ['b' => ['c' => 'value']]];
        $result = Arr::dot($arr);

        $this->assertEquals('value', $result['a.b.c']);
    }

    public function testDotPreservesScalarValues(): void
    {
        $arr = ['name' => 'Ava', 'version' => 1];
        $result = Arr::dot($arr);

        $this->assertEquals('Ava', $result['name']);
        $this->assertEquals(1, $result['version']);
    }

    // =========================================================================
    // only() / except()
    // =========================================================================

    public function testOnlyReturnsOnlySpecifiedKeys(): void
    {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Arr::only($arr, ['a', 'c']);

        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('c', $result);
        $this->assertCount(2, $result);
    }

    public function testExceptRemovesSpecifiedKeys(): void
    {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        $result = Arr::except($arr, ['b']);

        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('c', $result);
        $this->assertCount(2, $result);
    }

    // =========================================================================
    // pluck()
    // =========================================================================

    public function testPluckExtractsValuesFromArray(): void
    {
        $arr = [
            ['name' => 'Post 1', 'id' => 1],
            ['name' => 'Post 2', 'id' => 2],
        ];
        $result = Arr::pluck($arr, 'name');

        $this->assertEquals(['Post 1', 'Post 2'], $result);
    }

    public function testPluckWithKeyBy(): void
    {
        $arr = [
            ['name' => 'Post 1', 'id' => 1],
            ['name' => 'Post 2', 'id' => 2],
        ];
        $result = Arr::pluck($arr, 'name', 'id');

        $this->assertEquals('Post 1', $result[1]);
        $this->assertEquals('Post 2', $result[2]);
    }

    // =========================================================================
    // groupBy()
    // =========================================================================

    public function testGroupByGroupsItemsByKey(): void
    {
        $arr = [
            ['type' => 'post', 'title' => 'A'],
            ['type' => 'page', 'title' => 'B'],
            ['type' => 'post', 'title' => 'C'],
        ];
        $result = Arr::groupBy($arr, 'type');

        $this->assertCount(2, $result['post']);
        $this->assertCount(1, $result['page']);
    }

    // =========================================================================
    // sortBy()
    // =========================================================================

    public function testSortBySortsArrayByKey(): void
    {
        $arr = [
            ['name' => 'Charlie'],
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ];
        $result = Arr::sortBy($arr, 'name');

        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
        $this->assertEquals('Charlie', $result[2]['name']);
    }

    public function testSortByDescending(): void
    {
        $arr = [
            ['count' => 10],
            ['count' => 30],
            ['count' => 20],
        ];
        $result = Arr::sortBy($arr, 'count', 'desc');

        $this->assertEquals(30, $result[0]['count']);
        $this->assertEquals(20, $result[1]['count']);
        $this->assertEquals(10, $result[2]['count']);
    }
}
