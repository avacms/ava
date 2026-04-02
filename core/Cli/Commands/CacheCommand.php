<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Webpage cache commands: stats, clear.
 */
final class CacheCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function stats(array $args): int
    {
        $webpageCache = $this->app->webpageCache();
        $stats = $webpageCache->stats();

        $this->output->writeln('');
        echo $this->output->color('  ─── Webpage Cache ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(str_repeat('─', 38), Output::PRIMARY, Output::BOLD) . "\n";
        $this->output->writeln('');

        $status = $stats['enabled']
            ? $this->output->color('● Enabled', Output::GREEN, Output::BOLD)
            : $this->output->color('○ Disabled', Output::DIM);
        $this->output->keyValue('Status', $status);

        if (!$stats['enabled']) {
            $this->output->writeln('');
            $this->output->tip("Enable webpage caching in app/config/ava.php: 'webpage_cache' => ['enabled' => true]");
            $this->output->writeln('');
            return 0;
        }

        $this->output->keyValue('TTL', $stats['ttl'] ? $stats['ttl'] . ' seconds' : 'Forever (until cleared)');
        $this->output->writeln('');
        $this->output->keyValue('Cached', $this->output->color((string) $stats['count'], Output::PRIMARY, Output::BOLD) . ' webpages');
        $this->output->keyValue('Size', $this->output->formatBytes($stats['size']));

        if ($stats['oldest']) {
            $this->output->keyValue('Oldest', $stats['oldest']);
            $this->output->keyValue('Newest', $stats['newest']);
        }

        $this->output->writeln('');

        return 0;
    }

    public function clear(array $args): int
    {
        $webpageCache = $this->app->webpageCache();

        $this->output->writeln('');

        if (!$webpageCache->isEnabled()) {
            $this->output->box("Webpage cache is not enabled", 'warning');
            $this->output->writeln('');
            $this->output->tip("Enable it in app/config/ava.php with 'webpage_cache' => ['enabled' => true]");
            $this->output->writeln('');
            return 0;
        }

        $stats = $webpageCache->stats();
        if ($stats['count'] === 0) {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Webpage cache is empty.');
            $this->output->writeln('');
            return 0;
        }

        $this->output->writeln('  Found ' . $this->output->color((string) $stats['count'], Output::PRIMARY, Output::BOLD) . ' cached webpage(s).');
        $this->output->writeln('');

        // Check for pattern argument
        if (isset($args[0])) {
            $pattern = $args[0];
            $count = $webpageCache->clearPattern($pattern);
            $this->output->success("Cleared {$count} webpage(s) matching: {$pattern}");
        } else {
            echo '  Clear all cached webpages? [' . $this->output->color('y', Output::RED) . '/N]: ';
            $answer = trim(fgets(STDIN));

            if (strtolower($answer) !== 'y') {
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Cancelled.');
                $this->output->writeln('');
                return 0;
            }

            $count = $webpageCache->clear();
            $this->output->success("Cleared {$count} cached webpage(s)");
        }

        $this->output->writeln('');
        return 0;
    }
}
