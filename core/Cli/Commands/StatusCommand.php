<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Show site status.
 */
final class StatusCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function execute(array $args): int
    {
        $this->output->showBanner(showVersion: true);

        // Site info
        $this->output->sectionHeader('Site');
        $this->output->keyValue('Name', $this->output->color($this->app->config('site.name'), Output::BOLD));
        $this->output->keyValue('URL', $this->output->color($this->app->config('site.base_url'), Output::PRIMARY));

        // PHP environment
        $this->output->sectionHeader('Environment');
        $this->output->keyValue('PHP', PHP_VERSION);
        $extensions = [];
        if (extension_loaded('igbinary')) {
            $extensions[] = $this->output->color('igbinary', Output::GREEN);
        }
        if (extension_loaded('opcache') && ini_get('opcache.enable')) {
            $extensions[] = $this->output->color('opcache', Output::GREEN);
        }
        if (!empty($extensions)) {
            $this->output->keyValue('Extensions', implode(', ', $extensions));
        }

        // Content Index status
        $this->output->sectionHeader('Content Index');
        $store = $this->app->indexStore();
        $state = $store->reloadState();

        if ($state !== null) {
            $fresh = $this->app->indexer()->isCacheFresh();
            $status = $fresh
                ? $this->output->color('● Fresh', Output::GREEN, Output::BOLD)
                : $this->output->color('○ Stale', Output::YELLOW, Output::BOLD);
            $this->output->keyValue('Status', $status);
            $this->output->keyValue('Mode', $this->app->indexMode());

            $backend = ucfirst($state['backend']);
            $configured = (string) $this->app->config('content_index.backend', 'array');
            if ($configured !== $state['backend']) {
                $backend .= $this->output->color(" (config says {$configured}; takes effect at the next rebuild)", Output::YELLOW);
            }
            $this->output->keyValue('Backend', $this->output->color($backend, Output::PRIMARY));

            $path = (string) $store->currentPath();
            $size = 0;
            $htmlFiles = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $size += $file->getSize();
                $htmlFiles += str_contains($file->getPathname(), '/html/') ? 1 : 0;
            }
            $sizeInfo = $this->output->formatBytes($size);
            if ($htmlFiles > 0) {
                $sizeInfo .= $this->output->color(" ({$htmlFiles} pre-rendered pages)", Output::DIM);
            }
            $this->output->keyValue('Size', $sizeInfo);
            $this->output->keyValue('Built', $this->output->color(date('Y-m-d H:i:s', strtotime($state['built_at']) ?: 0), Output::DIM));

            if (!$store->keyIsReadable()) {
                $this->output->writeln('');
                $this->output->error(\Ava\Support\SignedCache::describeUnreadableKey($store->keyDirectory()));
            }
        } else {
            $this->output->keyValue('Status', $this->output->color('○ Not built', Output::YELLOW));
            $this->output->tip('Run ./ava rebuild to build the index');
        }

        // Content counts
        $this->output->sectionHeader('Content');
        $repository = $this->app->repository();

        foreach ($repository->types() as $type) {
            $total = $repository->count($type);
            $published = $repository->count($type, 'published');
            $drafts = $repository->count($type, 'draft');

            $draftBadge = $drafts > 0 ? $this->output->color(" ({$drafts} drafts)", Output::YELLOW) : '';
            $this->output->labeledItem(
                ucfirst($type),
                $this->output->color((string) $published, Output::GREEN, Output::BOLD) .
                    $this->output->color(' published', Output::DIM) . $draftBadge,
                $total > 0 ? '◆' : '◇',
                $total > 0 ? Output::GREEN : Output::DIM
            );
        }

        // Taxonomies
        $this->output->sectionHeader('Taxonomies');
        foreach ($repository->taxonomies() as $taxonomy) {
            $terms = $repository->terms($taxonomy);
            $count = count($terms);
            $this->output->labeledItem(
                ucfirst($taxonomy),
                $this->output->color((string) $count, Output::PRIMARY, Output::BOLD) .
                    $this->output->color(' terms', Output::DIM),
                '◆',
                Output::PRIMARY
            );
        }

        // Webpage cache stats
        $webpageCache = $this->app->webpageCache();
        $stats = $webpageCache->stats();
        $this->output->sectionHeader('Webpage Cache');
        $status = $stats['enabled']
            ? $this->output->color('● Enabled', Output::GREEN, Output::BOLD)
            : $this->output->color('○ Disabled', Output::DIM);
        $this->output->keyValue('Status', $status);

        if ($stats['enabled']) {
            $ttl = $stats['ttl'] ?? null;
            $this->output->keyValue('TTL', $ttl ? "{$ttl}s" : 'Forever');
            $this->output->keyValue('Cached', $this->output->color((string) $stats['count'], Output::PRIMARY, Output::BOLD) . ' webpages');
            if ($stats['count'] > 0) {
                $this->output->keyValue('Size', $this->output->formatBytes($stats['size']));
            }
        }

        // Check for stale files
        $updater = new \Ava\Updater($this->app);
        if ($updater->checkPathSafety()['safe']) {
            $staleResult = $updater->detectStaleFiles();
            if ($staleResult['success'] && !empty($staleResult['stale_files'])) {
                $count = count($staleResult['stale_files']);
                $version = $staleResult['compared_to'];
                $this->output->sectionHeader('Maintenance');
                $this->output->writeln('  ' . $this->output->color('⚠', Output::YELLOW) . ' ' . $this->output->color("{$count} file(s) not in v{$version}", Output::YELLOW));
                $this->output->nextStep('./ava update:stale --clean', 'Review and remove');
            }
        }

        $this->output->writeln('');
        return 0;
    }
}
