<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;
use Ava\Plugins\Hooks;

/**
 * Index management commands: rebuild, lint.
 */
final class IndexCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function rebuild(array $args): int
    {
        $keepWebpageCache = in_array('--keep-webpage-cache', $args, true) || in_array('--keep-webcache', $args, true);

        // Clean up stale directories from previous versions
        // This runs on every rebuild to ensure upgrades are clean
        $this->cleanupStaleDirectories();

        // Load plugins so they can hook into the rebuild process
        $this->app->loadPlugins();

        $this->output->writeln('');
        $this->output->withSpinner('Rebuilding content index', function () use ($keepWebpageCache) {
            $this->app->indexer()->rebuild(clearWebpageCache: !$keepWebpageCache);
            return true;
        });

        // Reset OPcache if available (clears cached PHP bytecode)
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        Hooks::doAction('cli.rebuild', $this->app);

        if ($keepWebpageCache) {
            $this->output->success('Content index rebuilt (webpage cache kept)!');
        } else {
            $this->output->success('Content index rebuilt!');
        }
        $this->output->writeln('');
        return 0;
    }

    /**
     * Remove directories that were removed in newer versions.
     * 
     * Silent operation - only cleans if directories exist.
     * Critical for security when upgrading from versions that had admin panel.
     */
    private function cleanupStaleDirectories(): void
    {
        $rootDir = $this->app->path('');
        
        // Directories removed in v26.3 that should be cleaned up
        $staleDirectories = [
            'core/Admin',      // Admin panel removed
            'core/Fields',     // Field types removed (admin dependency)
        ];

        foreach ($staleDirectories as $dir) {
            $fullPath = $rootDir . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->removeDirectoryRecursive($fullPath);
            }
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectoryRecursive(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    public function lint(array $args): int
    {
        $this->output->writeln('');
        echo $this->output->color('  🔍 Validating content files...', Output::DIM) . "\n";
        $this->output->writeln('');

        $errors = $this->app->indexer()->lint();

        if (empty($errors)) {
            $this->output->box("All content files are valid!\nNo issues found.", 'success');
            $this->output->writeln('');
            return 0;
        }

        $this->output->error("Found " . count($errors) . " issue(s):");
        $this->output->writeln('');
        foreach ($errors as $error) {
            echo "    " . $this->output->color('•', Output::RED) . " {$error}\n";
        }

        $this->output->writeln('');
        $this->output->tip('Fix the issues above and run lint again');
        $this->output->writeln('');

        return 1;
    }
}
