<?php

declare(strict_types=1);

namespace Ava\Testing;

use Ava\Application;

/**
 * Lightweight Test Runner
 *
 * A simple test runner for Ava CMS that requires no external dependencies.
 * Inspired by PHPUnit but minimal and self-contained.
 *
 * Tests are discovered automatically from the tests/ directory.
 */
final class TestRunner
{
    // ANSI colors (matching CLI palette)
    private const RESET = "\033[0m";
    private const PRIMARY = "\033[38;2;55;235;243m";     // Electric Blue
    private const ACCENT = "\033[38;2;228;85;174m";      // Frostbite pink
    private const RED = "\033[38;2;248;113;113m";
    private const GREEN = "\033[38;2;52;211;153m";
    private const YELLOW = "\033[38;2;251;191;36m";
    private const DIM = "\033[90m";
    private const WHITE = "\033[37m";
    private const BOLD = "\033[1m";

    private Application $app;
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private array $failures = [];
    private bool $verbose = false;
    private bool $quiet = false;
    private ?string $filter = null;

    public function __construct(Application $app, bool $verbose = false, ?string $filter = null, bool $quiet = false)
    {
        $this->app = $app;
        $this->verbose = $verbose;
        $this->filter = $filter;
        $this->quiet = $quiet;
    }

    /**
     * Run all tests in the tests directory.
     */
    public function run(string $testsPath): int
    {
        $startTime = microtime(true);

        echo $this->color("\n  Ava CMS Test Suite\n", self::PRIMARY, self::BOLD);
        echo $this->color("  " . str_repeat('─', 50) . "\n", self::PRIMARY);
        
        if (!$this->quiet) {
            echo "\n";
        }

        // Discover test files
        $testFiles = $this->discoverTests($testsPath);

        if (empty($testFiles)) {
            echo $this->color("  No tests found in {$testsPath}\n\n", self::YELLOW);
            return 1;
        }

        // Collect test class names for sorting
        $testClasses = [];
        foreach ($testFiles as $file) {
            require_once $file;
            $className = $this->getClassNameFromFile($file);
            if ($className !== null) {
                $testClasses[$className] = $file;
            }
        }

        // Sort by class short name
        uasort($testClasses, function($a, $b) {
            $classA = $this->getClassNameFromFile($a);
            $classB = $this->getClassNameFromFile($b);
            $shortNameA = (new \ReflectionClass($classA))->getShortName();
            $shortNameB = (new \ReflectionClass($classB))->getShortName();
            return strcmp($shortNameA, $shortNameB);
        });

        // Load and run each test file in alphabetical order
        foreach ($testClasses as $file) {
            $this->runTestFile($file);
        }

        // Summary
        $elapsed = round((microtime(true) - $startTime) * 1000);

        if (!$this->quiet) {
            echo "\n";
            echo $this->color("  " . str_repeat('─', 50) . "\n", self::PRIMARY);
        }

        $summary = "  Tests: ";
        if ($this->passed > 0) {
            $summary .= $this->color("{$this->passed} passed", self::GREEN);
        }
        if ($this->failed > 0) {
            $summary .= ($this->passed > 0 ? ", " : "") . $this->color("{$this->failed} failed", self::RED);
        }
        if ($this->skipped > 0) {
            $summary .= ($this->passed > 0 || $this->failed > 0 ? ", " : "") .
                $this->color("{$this->skipped} skipped", self::YELLOW);
        }
        $summary .= $this->color(" ({$elapsed}ms)", self::DIM);

        echo $summary . "\n\n";

        // Show failures
        if (!empty($this->failures)) {
            echo $this->color("  Failures:\n\n", self::RED, self::BOLD);
            foreach ($this->failures as $i => $failure) {
                echo "  " . $this->color(($i + 1) . ") ", self::RED);
                echo $this->color($failure['test'], self::BOLD) . "\n";
                echo "     " . $this->color($failure['message'], self::RED) . "\n";
                if (isset($failure['file'])) {
                    echo "     " . $this->color("at {$failure['file']}:{$failure['line']}", self::DIM) . "\n";
                }
                echo "\n";
            }
        }

        return $this->failed > 0 ? 1 : 0;
    }

    /**
     * Discover test files in directory.
     */
    private function discoverTests(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Run tests from a single file.
     */
    private function runTestFile(string $file): void
    {
        // Load the file
        require_once $file;

        // Get the class name from the file
        $className = $this->getClassNameFromFile($file);
        if ($className === null) {
            return;
        }

        // Apply filter if set
        if ($this->filter !== null && !str_contains(strtolower($className), strtolower($this->filter))) {
            return;
        }

        // Create instance
        try {
            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) {
                return;
            }

            $instance = $reflection->newInstance();
            
            // Inject app if this is a TestCase
            if ($instance instanceof TestCase) {
                $instance->setApp($this->app);
            }
        } catch (\Throwable $e) {
            $this->failures[] = [
                'test' => $className,
                'message' => "Failed to instantiate: " . $e->getMessage(),
            ];
            $this->failed++;
            return;
        }

        // Find test methods
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $testMethods = array_filter($methods, fn($m) => str_starts_with($m->getName(), 'test'));

        if (empty($testMethods)) {
            return;
        }

        // Show class name (unless quiet mode)
        if (!$this->quiet) {
            $shortName = $reflection->getShortName();
            echo $this->color("  {$shortName}\n", self::WHITE, self::BOLD);
            echo "\n";
        }

        // Run setUp if exists
        $hasSetUp = $reflection->hasMethod('setUp');
        $hasTearDown = $reflection->hasMethod('tearDown');

        foreach ($testMethods as $method) {
            $methodName = $method->getName();

            // Apply filter to method if set
            if ($this->filter !== null && !str_contains(strtolower($methodName), strtolower($this->filter))) {
                continue;
            }

            // Run setUp
            if ($hasSetUp) {
                try {
                    $instance->setUp();
                } catch (\Throwable $e) {
                    $this->recordFailure($className, $methodName, $e);
                    continue;
                }
            }

            // Run test
            try {
                $instance->{$methodName}();
                $this->passed++;
                if (!$this->quiet) {
                    echo "    " . $this->color('✓', self::GREEN) . " ";
                    echo $this->color($this->humanize($methodName), self::DIM) . "\n";
                }
            } catch (SkippedException $e) {
                $this->skipped++;
                if (!$this->quiet) {
                    echo "    " . $this->color('○', self::YELLOW) . " ";
                    echo $this->color($this->humanize($methodName), self::YELLOW);
                    echo $this->color(" (skipped: {$e->getMessage()})", self::DIM) . "\n";
                }
            } catch (AssertionFailedException $e) {
                $this->recordFailure($className, $methodName, $e);
                if (!$this->quiet) {
                    echo "    " . $this->color('✗', self::RED) . " ";
                    echo $this->color($this->humanize($methodName), self::RED) . "\n";
                }
            } catch (\Throwable $e) {
                $this->recordFailure($className, $methodName, $e);
                if (!$this->quiet) {
                    echo "    " . $this->color('✗', self::RED) . " ";
                    echo $this->color($this->humanize($methodName), self::RED) . "\n";
                }
            }

            // Run tearDown
            if ($hasTearDown) {
                try {
                    $instance->tearDown();
                } catch (\Throwable $e) {
                    // Log but don't fail
                }
            }
        }

        // Add spacing after test class output (unless quiet mode)
        if (!$this->quiet) {
            echo "\n";
        }
    }

    /**
     * Record a test failure.
     */
    private function recordFailure(string $class, string $method, \Throwable $e): void
    {
        $this->failed++;
        $this->failures[] = [
            'test' => "{$class}::{$method}",
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }

    /**
     * Get class name from file.
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]) . '\\';
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $namespace . $matches[1];
        }

        return null;
    }

    /**
     * Convert method name to human readable.
     */
    private function humanize(string $methodName): string
    {
        // Remove 'test' prefix
        $name = preg_replace('/^test_?/', '', $methodName);

        // Convert camelCase to words
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);

        // Convert underscores to spaces
        $name = str_replace('_', ' ', $name);

        return strtolower($name);
    }

    /**
     * Apply ANSI color codes.
     */
    private function color(string $text, string ...$codes): string
    {
        if (!$this->supportsColors()) {
            return $text;
        }

        return implode('', $codes) . $text . self::RESET;
    }

    /**
     * Check if terminal supports colors.
     */
    private function supportsColors(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
