<?php

declare(strict_types=1);

namespace Ava\Plugins;

/**
 * Hooks System
 *
 * Simple WordPress-style hooks (filters and actions).
 */
final class Hooks
{
    /** @var array<string, array<int, array<callable>>> */
    private static array $filters = [];

    /** @var array<string, array<int, array<callable>>> */
    private static array $actions = [];

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
        ksort(self::$filters[$hook]);
    }

    /**
     * Apply filters to a value.
     */
    public static function apply(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        foreach (self::$filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
        ksort(self::$actions[$hook]);
    }

    /**
     * Run action hooks.
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        foreach (self::$actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Check if a filter hook has callbacks.
     */
    public static function hasFilter(string $hook): bool
    {
        return !empty(self::$filters[$hook]);
    }

    /**
     * Check if an action hook has callbacks.
     */
    public static function hasAction(string $hook): bool
    {
        return !empty(self::$actions[$hook]);
    }

    /**
     * Remove all callbacks for a hook.
     */
    public static function removeAll(string $hook): void
    {
        unset(self::$filters[$hook], self::$actions[$hook]);
    }

    /**
     * Capture every registered hook (for testing).
     *
     * @return array{0: array, 1: array}
     */
    public static function snapshot(): array
    {
        return [self::$filters, self::$actions];
    }

    /**
     * Put back hooks captured by snapshot() (for testing).
     *
     * @param array{0: array, 1: array} $snapshot
     */
    public static function restore(array $snapshot): void
    {
        [self::$filters, self::$actions] = $snapshot;
    }

    /**
     * Clear all hooks (for testing).
     */
    public static function reset(): void
    {
        self::$filters = [];
        self::$actions = [];
    }

    /**
     * Get all registered filter hook names.
     * 
     * @return array<string>
     */
    public static function getRegisteredFilters(): array
    {
        return array_keys(self::$filters);
    }

    /**
     * Get all registered action hook names.
     * 
     * @return array<string>
     */
    public static function getRegisteredActions(): array
    {
        return array_keys(self::$actions);
    }
}
