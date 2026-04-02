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
            $this->output->writeln('    2. Extract and copy core/, docs/, bootstrap.php, composer.json');
            $this->output->writeln('    3. Copy bundled plugins to your custom plugins path');
            $this->output->writeln('    4. Run ./ava rebuild');
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
            $this->output->writeln($this->output->color('  Will be updated:', Output::BOLD));
            echo "    " . $this->output->color('▸', Output::GREEN) . " Core files (core/, bootstrap.php)\n";
            echo "    " . $this->output->color('▸', Output::GREEN) . " Bundled plugins in app/plugins/ (sitemap, feed, redirects)\n";
            $this->output->writeln('');
            $this->output->writeln($this->output->color('  Will NOT be modified:', Output::BOLD));
            echo "    " . $this->output->color('•', Output::DIM) . " Your content (content/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your config (app/config/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your themes (app/themes/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Your snippets (app/snippets/)\n";
            echo "    " . $this->output->color('•', Output::DIM) . " Custom plugins, storage, and cache\n";
            $this->output->writeln('');

            // Backup check
            $this->output->writeln($this->output->color('  ⚠️  Have you backed up your site and have a secure copy saved off-site?', Output::YELLOW, Output::BOLD));
            echo '  [' . $this->output->color('y', Output::GREEN) . '/N]: ';
            $backupAnswer = trim(fgets(STDIN));
            if (strtolower($backupAnswer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Please backup your site before updating.');
                $this->output->writeln('');
                return 0;
            }
            $this->output->writeln('');

            echo '  Continue with update? [' . $this->output->color('y', Output::GREEN) . '/N]: ';
            $answer = trim(fgets(STDIN));
            if (strtolower($answer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Update cancelled.');
                $this->output->writeln('');
                return 0;
            }
            $this->output->writeln('');
        }

        $result = $this->output->withSpinner('Downloading update', function () use ($updater, $devMode) {
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
        $this->output->success('Content index rebuilt.');

        // Check for stale files after update
        $staleResult = $updater->detectStaleFiles();
        if ($staleResult['success'] && !empty($staleResult['stale_files'])) {
            $count = count($staleResult['stale_files']);
            $version = $staleResult['compared_to'];
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' ' . $this->output->color("{$count} file(s) not in v{$version}", Output::YELLOW));
            $this->output->nextStep('./ava update:stale --clean', 'Review and remove them');
        }

        $this->output->writeln('');
        return 0;
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
