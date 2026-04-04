<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Update commands: check, apply, stale.
 */
final class UpdateCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function check(array $args): int
    {
        // If --dev flag is present, redirect to apply (dev mode always applies)
        $devMode = in_array('--dev', $args) || in_array('-d', $args);
        if ($devMode) {
            return $this->apply($args);
        }

        $force = in_array('--force', $args) || in_array('-f', $args);

        $this->output->writeln('');
        echo $this->output->color('  🔍 Checking for updates...', Output::DIM) . "\n";
        $this->output->writeln('');

        $updater = new \Ava\Updater($this->app);
        $result = $updater->check($force);

        $this->output->keyValue('Current', $this->output->color($result['current'], Output::BOLD));
        $this->output->keyValue('Latest', $this->output->color($result['latest'], Output::BOLD));

        if ($result['error']) {
            $this->output->error($result['error']);
            return 1;
        }

        if ($result['available']) {
            $this->output->writeln('');
            $this->output->box("Update available!", 'success');
            $this->output->writeln('');

            if ($result['release']) {
                if ($result['release']['name']) {
                    $this->output->keyValue('Release', $result['release']['name']);
                }
                if ($result['release']['published_at']) {
                    $date = date('Y-m-d', strtotime($result['release']['published_at']));
                    $this->output->keyValue('Published', $date);
                }
                if ($result['release']['body']) {
                    $this->output->writeln('');
                    echo $this->output->color('  ─── Changelog ', Output::PRIMARY, Output::BOLD);
                    echo $this->output->color(str_repeat('─', 42), Output::PRIMARY, Output::BOLD) . "\n";
                    $this->output->writeln('');
                    // Show first 15 lines of changelog
                    $lines = explode("\n", $result['release']['body']);
                    foreach (array_slice($lines, 0, 15) as $line) {
                        $this->output->writeln('  ' . $line);
                    }
                    if (count($lines) > 15) {
                        $this->output->writeln('  ' . $this->output->color('... (truncated)', Output::DIM));
                    }
                }
            }

            $this->output->writeln('');
            $this->output->nextStep('./ava update:apply', 'Download and apply the update');

        } else {
            $this->output->writeln('');
            $this->output->box("You're up to date!", 'success');
        }

        if (isset($result['from_cache']) && $result['from_cache']) {
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' ' . $this->output->color('Cached result — use --force to refresh', Output::DIM));
        }

        $this->output->writeln('');
        return 0;
    }

    public function apply(array $args): int
    {
        $this->output->writeln('');

        $devMode = in_array('--dev', $args) || in_array('-d', $args);
        $updater = new \Ava\Updater($this->app);

        // Check for custom paths - auto-update is blocked entirely
        $pathCheck = $updater->checkPathSafety();
        if (!$pathCheck['safe']) {
            $this->output->box('Auto-update disabled', 'error');
            $this->output->writeln('');
            $this->output->writeln('  Your site uses custom paths that differ from Ava defaults:');
            $this->output->writeln('');
            foreach ($pathCheck['custom_paths'] as $key => $info) {
                $this->output->writeln('    ' . $this->output->color($key, Output::BOLD) . ': ' . $this->output->color($info['configured'], Output::YELLOW));
                $this->output->writeln('    ' . $this->output->color('Expected:', Output::DIM) . ' ' . $info['default']);
                $this->output->writeln('');
            }
            $this->output->writeln('  The auto-updater cannot safely update sites with custom paths');
            $this->output->writeln('  because files would be written to the wrong locations.');
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  To update manually:', Output::BOLD));
            $this->output->writeln('    1. Download the latest release from https://github.com/avacms/ava/releases');
            $this->output->writeln('    2. Extract and replace: core/, bootstrap.php, composer.json, composer.lock');
            $this->output->writeln('    3. Replace: ava, public/index.php, public/.htaccess, .htaccess, nginx.conf.example');
            $this->output->writeln('    4. Copy bundled plugins to your custom plugins path');
            $this->output->writeln('    5. Run: composer install --no-dev && ./ava rebuild');
            $this->output->writeln('');
            return 1;
        }

        if ($devMode) {
            echo $this->output->color('  ─── Dev Update ', Output::PRIMARY, Output::BOLD);
            echo $this->output->color(str_repeat('─', 42), Output::PRIMARY, Output::BOLD) . "\n";
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('⚠️  Forcing update from latest commit on main branch', Output::YELLOW));
            $this->output->writeln('  ' . $this->output->color('   This may include unstable or untested changes.', Output::DIM));
            $this->output->writeln('  ' . $this->output->color('   Version checks are bypassed in dev mode.', Output::DIM));
            $this->output->writeln('');
            $this->output->keyValue('From', $updater->currentVersion());
            $this->output->keyValue('To', $this->output->color('main (latest commit)', Output::YELLOW, Output::BOLD));
            $this->output->writeln('');
        } else {
            $check = $updater->check(true);

            if ($check['error']) {
                $this->output->error('Could not check for updates: ' . $check['error']);
                return 1;
            }

            if (!$check['available']) {
                $this->output->box("Already running the latest version ({$check['current']})", 'success');
                $this->output->writeln('');
                return 0;
            }

            echo $this->output->color('  ─── Update Available ', Output::PRIMARY, Output::BOLD);
            echo $this->output->color(str_repeat('─', 35), Output::PRIMARY, Output::BOLD) . "\n";
            $this->output->writeln('');
            $this->output->keyValue('From', $check['current']);
            $this->output->keyValue('To', $this->output->color($check['latest'], Output::GREEN, Output::BOLD));
            $this->output->writeln('');
        }

        // Confirm unless --yes flag
        if (!in_array('--yes', $args) && !in_array('-y', $args)) {
            $this->output->writeln($this->output->color('  Will be replaced:', Output::BOLD));
            echo "    " . $this->output->color('▸', Output::GREEN) . " core/ directory (fully replaced)\n";
            echo "    " . $this->output->color('▸', Output::GREEN) . " Bundled plugins (sitemap, feed, redirects)\n";
            echo "    " . $this->output->color('▸', Output::GREEN) . " bootstrap.php, composer.json, composer.lock\n";
            echo "    " . $this->output->color('▸', Output::GREEN) . " ava CLI, public/index.php, public/.htaccess\n";
            echo "    " . $this->output->color('▸', Output::GREEN) . " .htaccess, nginx.conf.example (root)\n";
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  Should be preserved:', Output::BOLD));
            echo "    " . $this->output->color('•', Output::DIM) . " Your content (content/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your config (app/config/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your themes (app/themes/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your snippets (app/snippets/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Custom plugins, vendor/, storage/\n";
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  After update, the following will run automatically:', Output::BOLD));
            echo "    " . $this->output->color('1.', Output::PRIMARY) . " composer install (update dependencies)\n";
            echo "    " . $this->output->color('2.', Output::PRIMARY) . " ./ava rebuild (rebuild content index)\n";
            $this->output->writeln('');

            // Backup check
            $this->output->writeln($this->output->color('  ⚠️  Have you backed up your entire site?', Output::YELLOW, Output::BOLD));
            echo '  [' . $this->output->color('y', Output::GREEN) . '/N]: ';
            $backupAnswer = trim(fgets(STDIN));
            if (strtolower($backupAnswer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Please backup your entire site before updating.');
                $this->output->writeln('');
                return 0;
            }
            $this->output->writeln('');

            // Proceed confirmation
            $this->output->writeln($this->output->color('  Proceed with the update?', Output::BOLD));
            echo '  [' . $this->output->color('y', Output::GREEN) . '/N]: ';
            $proceedAnswer = trim(fgets(STDIN));
            if (strtolower($proceedAnswer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Update cancelled.');
                $this->output->writeln('');
                return 0;
            }
            $this->output->writeln('');
        }

        $result = $this->output->withSpinner('Downloading and applying update', function () use ($updater, $devMode) {
            return $updater->apply(null, $devMode);
        });

        if (!$result['success']) {
            $this->output->error($result['message']);
            return 1;
        }

        $this->output->success($result['message']);

        if (!empty($result['new_plugins'])) {
            $this->output->writeln('');
            echo $this->output->color('  New bundled plugins available:', Output::BOLD) . "\n";
            foreach ($result['new_plugins'] as $plugin) {
                echo "    " . $this->output->color('•', Output::PRIMARY) . " {$plugin}\n";
            }
            $this->output->writeln('');
            $this->output->tip('Add them to your plugins array in app/config/ava.php to activate');
        }

        // Finalize update using NEW code (handles stale file cleanup for upgrades from older versions)
        $this->output->writeln('');
        $this->output->withSpinner('Cleaning up stale files', function () {
            $avaScript = $this->app->path('ava');
            $result = [];
            $exitCode = 0;
            exec('php ' . escapeshellarg($avaScript) . ' update:finalize 2>&1', $result, $exitCode);
            // Don't fail if finalize doesn't exist (old->new transition)
            return true;
        });

        // Install updated dependencies
        $this->output->writeln('');
        $composerFailed = false;
        $this->output->withSpinner('Installing dependencies (composer install)', function () use (&$composerFailed) {
            $rootDir = $this->app->path('');
            $result = [];
            $exitCode = 0;
            exec('cd ' . escapeshellarg($rootDir) . ' && composer install --no-dev --no-interaction 2>&1', $result, $exitCode);
            if ($exitCode !== 0) {
                $composerFailed = true;
            }
            return true;
        });

        if ($composerFailed) {
            $this->output->warning('composer install failed — run it manually: composer install --no-dev');
        }

        $this->output->writeln('');
        $this->output->withSpinner('Rebuilding content index', function () {
            // Spawn a new PHP process to ensure updated code is loaded
            $avaScript = $this->app->path('ava');
            $result = [];
            $exitCode = 0;
            exec('php ' . escapeshellarg($avaScript) . ' rebuild 2>&1', $result, $exitCode);
            if ($exitCode !== 0) {
                throw new \RuntimeException(implode("\n", $result));
            }
            return true;
        });

        $this->output->writeln('');
        $this->output->box('Update complete!', 'success');
        $this->output->writeln('');
        $this->output->keyValue('Updated', $this->output->color($result['updated_from'], Output::DIM) . ' → ' . $this->output->color($result['updated_to'], Output::GREEN, Output::BOLD));
        $this->output->writeln('');
        $this->output->tip('Review the changelog for any breaking changes or new features');

        $this->output->writeln('');
        return 0;
    }

    /**
     * Finalize an update by removing stale directories.
     * 
     * This is called as a separate PHP process AFTER files are copied,
     * ensuring the NEW code handles cleanup. Critical for upgrades that
     * remove entire directories (like core/Admin).
     */
    public function finalize(array $args): int
    {
        $rootDir = $this->app->path('');
        $removed = [];

        // Directories that were removed in v26.3 and should be cleaned up
        $staleDirectories = [
            'core/Admin',      // Admin panel removed
            'core/Fields',     // Field types removed (admin dependency)
        ];

        foreach ($staleDirectories as $dir) {
            $fullPath = $rootDir . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->removeDirectoryRecursive($fullPath);
                $removed[] = $dir;
            }
        }

        // Output for the spinner context
        if (!empty($removed)) {
            echo "Cleaned: " . implode(', ', $removed);
        }

        return 0;
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

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

    public function stale(array $args): int
    {
        $this->output->writeln('');

        $devMode = in_array('--dev', $args, true) || in_array('-d', $args, true);
        $cleanMode = in_array('--clean', $args, true) || in_array('-c', $args, true);
        $forceClean = in_array('--yes', $args, true) || in_array('-y', $args, true);
        $updater = new \Ava\Updater($this->app);

        // Check for custom paths
        $pathCheck = $updater->checkPathSafety();
        if (!$pathCheck['safe']) {
            $this->output->box('Stale file scan disabled', 'error');
            $this->output->writeln('');
            $this->output->writeln('  Your site uses custom paths that differ from Ava defaults:');
            $this->output->writeln('');
            foreach ($pathCheck['custom_paths'] as $key => $info) {
                $this->output->writeln('    ' . $this->output->color($key, Output::BOLD) . ': ' . $this->output->color($info['configured'], Output::YELLOW));
                $this->output->writeln('    ' . $this->output->color('Expected:', Output::DIM) . ' ' . $info['default']);
                $this->output->writeln('');
            }
            $this->output->writeln('  The stale file scan relies on default paths to compare files');
            $this->output->writeln('  safely. Please scan manually if you use custom paths.');
            $this->output->writeln('');
            return 1;
        }

        $result = $this->output->withSpinner('Scanning for stale files', function () use ($updater, $devMode) {
            return $updater->detectStaleFiles(null, $devMode);
        });

        if (!$result['success']) {
            $this->output->error($result['message']);
            return 1;
        }

        $this->output->keyValue('Compared to', $this->output->color($result['compared_to'], Output::BOLD));

        $staleFiles = $result['stale_files'] ?? [];
        $count = count($staleFiles);

        if ($count === 0) {
            $this->output->writeln('');
            $this->output->box('No stale files found', 'success');
            $this->output->writeln('');
            return 0;
        }

        $this->output->writeln('');
        $this->output->box("Found {$count} stale file(s)", 'warning');
        $this->output->writeln('');

        $max = 200;
        $shown = 0;
        foreach ($staleFiles as $file) {
            $this->output->writeln('  ' . $this->output->color('•', Output::PRIMARY) . ' ' . $file);
            $shown++;
            if ($shown >= $max) {
                $remaining = $count - $shown;
                if ($remaining > 0) {
                    $this->output->writeln('  ' . $this->output->color("... {$remaining} more", Output::DIM));
                }
                break;
            }
        }

        $this->output->writeln('');

        // Clean mode: remove the stale files
        if ($cleanMode) {
            if (!$forceClean) {
                $this->output->writeln('  ' . $this->output->color('⚠️  This will permanently delete these files.', Output::YELLOW, Output::BOLD));
                echo '  Continue? [' . $this->output->color('y', Output::GREEN) . '/N]: ';
                $answer = trim(fgets(STDIN));
                if (strtolower($answer) !== 'y') {
                    $this->output->writeln('');
                    $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Cancelled.');
                    $this->output->writeln('');
                    return 0;
                }
                $this->output->writeln('');
            }

            $removeResult = $this->output->withSpinner('Removing stale files', function () use ($updater, $staleFiles) {
                return $updater->removeStaleFiles($staleFiles);
            });

            if ($removeResult['success']) {
                $this->output->success('Removed ' . count($removeResult['removed']) . ' stale file(s)');
            } else {
                $this->output->warning($removeResult['message']);
                if (!empty($removeResult['failed'])) {
                    $this->output->writeln('');
                    $this->output->writeln('  ' . $this->output->color('Failed to remove:', Output::BOLD));
                    foreach (array_slice($removeResult['failed'], 0, 10) as $file) {
                        $this->output->writeln('    ' . $this->output->color('•', Output::RED) . ' ' . $file);
                    }
                    if (count($removeResult['failed']) > 10) {
                        $this->output->writeln('    ' . $this->output->color('... ' . (count($removeResult['failed']) - 10) . ' more', Output::DIM));
                    }
                }
            }
        } else {
            $this->output->tip('Run with --clean to remove these files');
        }

        $this->output->writeln('');
        return 0;
    }
}
