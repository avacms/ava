<?php

declare(strict_types=1);

namespace Ava\Tests\Plugins;

use Ava\Plugins\Hooks;
use Ava\Testing\TestCase;

/**
 * Tests for the Hooks system.
 */
final class HooksTest extends TestCase
{
    public function setUp(): void
    {
        // Reset hooks before each test
        Hooks::reset();
    }

    public function tearDown(): void
    {
        // Clean up after each test
        Hooks::reset();
    }

    // =========================================================================
    // Filters
    // =========================================================================

    public function testAddFilterRegistersCallback(): void
    {
        Hooks::addFilter('test_hook', fn($v) => $v);
        $this->assertTrue(Hooks::hasFilter('test_hook'));
    }

    public function testApplyFilterModifiesValue(): void
    {
        Hooks::addFilter('uppercase', fn(string $v) => strtoupper($v));

        $result = Hooks::apply('uppercase', 'hello');
        $this->assertEquals('HELLO', $result);
    }

    public function testApplyFilterWithMultipleCallbacks(): void
    {
        Hooks::addFilter('transform', fn(string $v) => $v . ' world');
        Hooks::addFilter('transform', fn(string $v) => strtoupper($v));

        $result = Hooks::apply('transform', 'hello');
        $this->assertEquals('HELLO WORLD', $result);
    }

    public function testApplyFilterRespectsOrder(): void
    {
        Hooks::addFilter('order', fn(string $v) => $v . '1', 20);
        Hooks::addFilter('order', fn(string $v) => $v . '2', 10);
        Hooks::addFilter('order', fn(string $v) => $v . '3', 30);

        $result = Hooks::apply('order', '');
        $this->assertEquals('213', $result);
    }

    public function testApplyFilterPassesAdditionalArguments(): void
    {
        Hooks::addFilter('with_args', function (string $value, int $num, string $str) {
            return $value . "-{$num}-{$str}";
        });

        $result = Hooks::apply('with_args', 'start', 42, 'end');
        $this->assertEquals('start-42-end', $result);
    }

    public function testApplyFilterReturnsOriginalForUnregistered(): void
    {
        $result = Hooks::apply('nonexistent', 'original');
        $this->assertEquals('original', $result);
    }

    public function testHasFilterReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(Hooks::hasFilter('nonexistent'));
    }

    // =========================================================================
    // Actions
    // =========================================================================

    public function testAddActionRegistersCallback(): void
    {
        Hooks::addAction('test_action', fn() => null);
        $this->assertTrue(Hooks::hasAction('test_action'));
    }

    public function testDoActionExecutesCallback(): void
    {
        $executed = false;
        Hooks::addAction('run', function () use (&$executed) {
            $executed = true;
        });

        Hooks::doAction('run');
        $this->assertTrue($executed);
    }

    public function testDoActionExecutesMultipleCallbacks(): void
    {
        $log = [];
        Hooks::addAction('multi', function () use (&$log) {
            $log[] = 'first';
        });
        Hooks::addAction('multi', function () use (&$log) {
            $log[] = 'second';
        });

        Hooks::doAction('multi');
        $this->assertEquals(['first', 'second'], $log);
    }

    public function testDoActionRespectsOrder(): void
    {
        $log = [];
        Hooks::addAction('ordered', function () use (&$log) {
            $log[] = 'c';
        }, 30);
        Hooks::addAction('ordered', function () use (&$log) {
            $log[] = 'a';
        }, 10);
        Hooks::addAction('ordered', function () use (&$log) {
            $log[] = 'b';
        }, 20);

        Hooks::doAction('ordered');
        $this->assertEquals(['a', 'b', 'c'], $log);
    }

    public function testDoActionPassesArguments(): void
    {
        $received = [];
        Hooks::addAction('with_args', function (...$args) use (&$received) {
            $received = $args;
        });

        Hooks::doAction('with_args', 'one', 'two', 'three');
        $this->assertEquals(['one', 'two', 'three'], $received);
    }

    public function testHasActionReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(Hooks::hasAction('nonexistent'));
    }

    // =========================================================================
    // Management
    // =========================================================================

    public function testRemoveAllRemovesHook(): void
    {
        Hooks::addFilter('removable', fn($v) => $v);
        Hooks::addAction('removable', fn() => null);

        Hooks::removeAll('removable');

        $this->assertFalse(Hooks::hasFilter('removable'));
        $this->assertFalse(Hooks::hasAction('removable'));
    }

    public function testResetClearsAllHooks(): void
    {
        Hooks::addFilter('filter1', fn($v) => $v);
        Hooks::addFilter('filter2', fn($v) => $v);
        Hooks::addAction('action1', fn() => null);

        Hooks::reset();

        $this->assertFalse(Hooks::hasFilter('filter1'));
        $this->assertFalse(Hooks::hasFilter('filter2'));
        $this->assertFalse(Hooks::hasAction('action1'));
    }

    public function testGetRegisteredFiltersReturnsNames(): void
    {
        Hooks::addFilter('alpha', fn($v) => $v);
        Hooks::addFilter('beta', fn($v) => $v);

        $filters = Hooks::getRegisteredFilters();

        $this->assertContains('alpha', $filters);
        $this->assertContains('beta', $filters);
    }

    public function testGetRegisteredActionsReturnsNames(): void
    {
        Hooks::addAction('gamma', fn() => null);
        Hooks::addAction('delta', fn() => null);

        $actions = Hooks::getRegisteredActions();

        $this->assertContains('gamma', $actions);
        $this->assertContains('delta', $actions);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testSamePriorityCallbacksRunInRegistrationOrder(): void
    {
        $log = [];
        Hooks::addAction('same_priority', function () use (&$log) {
            $log[] = 'first';
        }, 10);
        Hooks::addAction('same_priority', function () use (&$log) {
            $log[] = 'second';
        }, 10);
        Hooks::addAction('same_priority', function () use (&$log) {
            $log[] = 'third';
        }, 10);

        Hooks::doAction('same_priority');
        $this->assertEquals(['first', 'second', 'third'], $log);
    }

    public function testFilterCanReturnNull(): void
    {
        Hooks::addFilter('nullable', fn($v) => null);
        $result = Hooks::apply('nullable', 'value');
        $this->assertNull($result);
    }

    public function testFilterCanReturnDifferentType(): void
    {
        Hooks::addFilter('type_change', fn($v) => strlen($v));
        $result = Hooks::apply('type_change', 'hello');
        $this->assertEquals(5, $result);
    }
}
