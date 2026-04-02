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
        $cachePath = $this->app->configPath('storage') . '/cache';
        $fingerprintPath = $cachePath . '/fingerprint.json';

        if (file_exists($fingerprintPath)) {
            $fresh = $this->app->indexer()->isCacheFresh();
            $status = $fresh
                ? $this->output->color('● Fresh', Output::GREEN, Output::BOLD)
                : $this->output->color('○ Stale', Output::YELLOW, Output::BOLD);
            $this->output->keyValue('Status', $status);
            $this->output->keyValue('Mode', $this->app->config('content_index.mode', 'auto'));

            // Show backend info
            $repository = $this->app->repository();
            $backendName = ucfirst($repository->backendName());
            $configBackend = $this->app->config('content_index.backend', 'auto');
            $backendInfo = $this->output->color($backendName, Output::PRIMARY);
            if ($configBackend === 'auto') {
                $backendInfo .= $this->output->color(' (auto-detected)', Output::DIM);
            }
            $this->output->keyValue('Backend', $backendInfo);

            // Show cache file sizes
            $cacheFiles = [
                'content_index.bin' => 'Full index',
                'slug_lookup.bin' => 'Slug lookup',
                'recent_cache.bin' => 'Recent cache',
                'routes.bin' => 'Routes',
                'tax_index.bin' => 'Taxonomies',
            ];

            $sizes = [];
            foreach ($cacheFiles as $file => $label) {
                $path = $cachePath . '/' . $file;
                if (file_exists($path)) {
                    $sizes[] = $this->output->color($label, Output::DIM) . ' ' . $this->output->formatBytes(filesize($path));
                }
            }

            // Add SQLite size if available
            $sqlitePath = $cachePath . '/content_index.sqlite';
            if (file_exists($sqlitePath)) {
                $sizes[] = $this->output->color('SQLite', Output::DIM) . ' ' . $this->output->formatBytes(filesize($sqlitePath));
            }

            if (!empty($sizes)) {
                $this->output->keyValue('Cache', implode(', ', $sizes));
            }

            // Show build time
            $indexPath = $cachePath . '/content_index.bin';
            if (file_exists($indexPath)) {
                $mtime = filemtime($indexPath);
                $this->output->keyValue('Built', $this->output->color(date('Y-m-d H:i:s', $mtime), Output::DIM));
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
                $this->output->sectionHeader('Maintenance');
                $this->output->writeln('  ' . $this->output->color('⚠', Output::YELLOW) . ' ' . $this->output->color("{$count} stale file(s) from previous version", Output::YELLOW));
                $this->output->nextStep('./ava update:stale --clean', 'Review and remove');
            }
        }

        $this->output->writeln('');
        return 0;
    }
}
