<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Run the automated test suite.
 */
final class TestCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function execute(array $args): int
    {
        // Parse arguments
        $verbose = in_array('-v', $args, true) || in_array('--verbose', $args, true);
        $quiet = in_array('-q', $args, true) || in_array('--quiet', $args, true);
        $release = in_array('--release', $args, true);
        $filter = null;

        // Get filter (first non-flag argument)
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '-')) {
                $filter = $arg;
                break;
            }
        }

        $testsPath = $this->app->path('core/tests');

        if (!is_dir($testsPath)) {
            $this->output->error("Tests directory not found: {$testsPath}");
            $this->output->tip('Create the core/tests/ directory and add test files ending in Test.php');
            return 1;
        }

        // Load test framework classes
        require_once $this->app->path('core/Testing/AssertionFailedException.php');
        require_once $this->app->path('core/Testing/SkippedException.php');
        require_once $this->app->path('core/Testing/TestCase.php');
        require_once $this->app->path('core/Testing/TestRunner.php');

        $runner = new \Ava\Testing\TestRunner($this->app, $verbose, $filter, $quiet, $release);
        return $runner->run($testsPath);
    }
}
