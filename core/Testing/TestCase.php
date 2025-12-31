<?php

declare(strict_types=1);

namespace Ava\Testing;

/**
 * Base Test Case
 *
 * Provides assertion methods and test lifecycle hooks.
 * Extend this class to create test cases.
 */
abstract class TestCase
{
    /**
     * Set up before each test method.
     */
    public function setUp(): void
    {
        // Override in subclass
    }

    /**
     * Tear down after each test method.
     */
    public function tearDown(): void
    {
        // Override in subclass
    }

    // =========================================================================
    // Assertions
    // =========================================================================

    /**
     * Assert that a condition is true.
     */
    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        if ($condition !== true) {
            $this->fail($message ?: 'Expected true, got ' . $this->export($condition));
        }
    }

    /**
     * Assert that a condition is false.
     */
    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        if ($condition !== false) {
            $this->fail($message ?: 'Expected false, got ' . $this->export($condition));
        }
    }

    /**
     * Assert that two values are equal.
     */
    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $this->fail($message ?: "Expected {$this->export($expected)}, got {$this->export($actual)}");
        }
    }

    /**
     * Assert that two values are strictly equal (===).
     */
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail($message ?: "Expected {$this->export($expected)} (identical), got {$this->export($actual)}");
        }
    }

    /**
     * Assert that two values are not strictly equal (!==).
     */
    protected function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->fail($message ?: "Expected values to be different instances, but they are the same");
        }
    }

    /**
     * Assert that two values are not equal.
     */
    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected == $actual) {
            $this->fail($message ?: "Expected values to differ, both are {$this->export($actual)}");
        }
    }

    /**
     * Assert that a value is null.
     */
    protected function assertNull(mixed $actual, string $message = ''): void
    {
        if ($actual !== null) {
            $this->fail($message ?: "Expected null, got {$this->export($actual)}");
        }
    }

    /**
     * Assert that a value is not null.
     */
    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        if ($actual === null) {
            $this->fail($message ?: 'Expected non-null value');
        }
    }

    /**
     * Assert that an array has a specific key.
     */
    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $this->fail($message ?: "Array does not have key '{$key}'");
        }
    }

    /**
     * Assert that an array contains a specific value.
     */
    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            $this->fail($message ?: "{$this->export($needle)} not found in array");
        }
    }

    /**
     * Assert that a value is an array.
     */
    protected function assertIsArray(mixed $actual, string $message = ''): void
    {
        if (!is_array($actual)) {
            $this->fail($message ?: "Expected array, got " . gettype($actual));
        }
    }

    /**
     * Assert that a value is a string.
     */
    protected function assertIsString(mixed $actual, string $message = ''): void
    {
        if (!is_string($actual)) {
            $this->fail($message ?: "Expected string, got " . gettype($actual));
        }
    }

    /**
     * Assert that a string contains a substring.
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->fail($message ?: "String does not contain '{$needle}'");
        }
    }

    /**
     * Assert that a string does not contain a substring.
     */
    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            $this->fail($message ?: "String should not contain '{$needle}'");
        }
    }

    /**
     * Assert that a string starts with a prefix.
     */
    protected function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
    {
        if (!str_starts_with($string, $prefix)) {
            $this->fail($message ?: "String does not start with '{$prefix}'");
        }
    }

    /**
     * Assert that a string ends with a suffix.
     */
    protected function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
    {
        if (!str_ends_with($string, $suffix)) {
            $this->fail($message ?: "String does not end with '{$suffix}'");
        }
    }

    /**
     * Assert that a string matches a regex pattern.
     */
    protected function assertMatchesRegex(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            $this->fail($message ?: "String does not match pattern '{$pattern}'");
        }
    }

    /**
     * Assert that a value is an instance of a class.
     */
    protected function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $actualType = is_object($actual) ? get_class($actual) : gettype($actual);
            $this->fail($message ?: "Expected instance of {$expected}, got {$actualType}");
        }
    }

    /**
     * Assert that an array/countable has a specific count.
     */
    protected function assertCount(int $expected, array|\Countable $actual, string $message = ''): void
    {
        $actualCount = count($actual);
        if ($actualCount !== $expected) {
            $this->fail($message ?: "Expected count {$expected}, got {$actualCount}");
        }
    }

    /**
     * Assert that an array is empty.
     */
    protected function assertEmpty(mixed $actual, string $message = ''): void
    {
        if (!empty($actual)) {
            $this->fail($message ?: 'Expected empty value, got ' . $this->export($actual));
        }
    }

    /**
     * Assert that an array is not empty.
     */
    protected function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        if (empty($actual)) {
            $this->fail($message ?: 'Expected non-empty value');
        }
    }

    /**
     * Assert that a value is greater than another.
     */
    protected function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            $this->fail($message ?: "Expected value greater than {$expected}, got {$actual}");
        }
    }

    /**
     * Assert that a value is less than another.
     */
    protected function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            $this->fail($message ?: "Expected value less than {$expected}, got {$actual}");
        }
    }

    /**
     * Assert that a callable throws an exception.
     */
    protected function assertThrows(string $exceptionClass, callable $callback, string $message = ''): void
    {
        try {
            $callback();
            $this->fail($message ?: "Expected {$exceptionClass} to be thrown");
        } catch (\Throwable $e) {
            if (!($e instanceof $exceptionClass)) {
                $this->fail($message ?: "Expected {$exceptionClass}, got " . get_class($e));
            }
        }
    }

    /**
     * Assert that two arrays are equal (order-independent for associative arrays).
     */
    protected function assertArrayEquals(array $expected, array $actual, string $message = ''): void
    {
        // Sort both arrays by key for comparison
        ksort($expected);
        ksort($actual);

        if ($expected != $actual) {
            $this->fail($message ?: "Arrays are not equal.\nExpected: {$this->export($expected)}\nActual: {$this->export($actual)}");
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Skip the current test.
     */
    protected function skip(string $reason = ''): void
    {
        throw new SkippedException($reason ?: 'Test skipped');
    }

    /**
     * Fail the test with a message.
     */
    protected function fail(string $message): never
    {
        throw new AssertionFailedException($message);
    }

    /**
     * Export a value for display.
     */
    protected function export(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            if (strlen($value) > 50) {
                return '"' . substr($value, 0, 47) . '..."';
            }
            return '"' . $value . '"';
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }
            return 'array(' . count($value) . ')';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return (string) $value;
    }
}
