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
