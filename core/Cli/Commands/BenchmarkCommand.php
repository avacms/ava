<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;

/**
 * Performance benchmark command.
 */
final class BenchmarkCommand
{
    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function execute(array $args): int
    {
        $this->output->showBanner();
        $this->output->writeln('');
        echo $this->output->color('  v' . AVA_VERSION, Output::DIM) . "\n";
        $this->output->sectionHeader('Performance Benchmark');

        // Parse arguments
        $iterations = 5;
        $compareMode = false;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--iterations=')) {
                $iterations = max(1, (int) substr($arg, 13));
            }
            if ($arg === '--compare') {
                $compareMode = true;
            }
            if ($arg === '--help' || $arg === '-h') {
                $this->output->writeln('  ' . $this->output->color('Usage:', Output::BOLD) . ' ./ava benchmark [options]');
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('Options:', Output::BOLD));
                $this->output->writeln('    --compare         Compare all available backends');
                $this->output->writeln('    --iterations=N    Number of test iterations (default: 5)');
                $this->output->writeln('');
                $this->output->writeln('  ' . $this->output->color('Examples:', Output::BOLD));
                $this->output->writeln('    ./ava benchmark              Test current backend');
                $this->output->writeln('    ./ava benchmark --compare    Compare array vs sqlite');
                $this->output->writeln('');
                return 0;
            }
        }

        // Get current configuration
        $repository = $this->app->repository();
        $currentBackend = $this->app->config('content_index.backend', 'array');
        $useIgbinary = $this->app->config('content_index.use_igbinary', true);
        $igbinaryAvailable = extension_loaded('igbinary');
        $igbinaryActive = $useIgbinary && $igbinaryAvailable && $currentBackend === 'array';

        // Get content counts
        $totalItems = 0;
        $itemsByType = [];
        foreach ($repository->types() as $type) {
            $count = $repository->count($type);
            $totalItems += $count;
            $itemsByType[$type] = $count;
        }

        if ($totalItems === 0) {
            $this->output->error('No content found. Generate test content with:');
            $this->output->writeln('');
            $this->output->writeln('    ' . $this->output->color('./ava stress:generate post 1000', Output::PRIMARY));
            $this->output->writeln('');
            return 1;
        }

        // Display configuration
        $this->output->keyValue('Content', $this->output->color(number_format($totalItems), Output::BOLD) . ' items');
        foreach ($itemsByType as $type => $count) {
            $this->output->writeln('              ' . $type . ': ' . $count);
        }
        $this->output->writeln('');

        $backendDisplay = $currentBackend;
        if ($currentBackend === 'array') {
            if ($igbinaryActive) {
                $backendDisplay .= ' + igbinary';
            } else {
                $backendDisplay .= ' + serialize';
            }
        }
        $this->output->keyValue('Backend', $this->output->color($backendDisplay, Output::PRIMARY, Output::BOLD));

        if ($currentBackend === 'array') {
            $igStatus = $igbinaryAvailable
                ? ($useIgbinary ? $this->output->color('enabled', Output::GREEN) : $this->output->color('disabled in config', Output::YELLOW))
                : $this->output->color('not installed', Output::DIM);
            $this->output->keyValue('igbinary', $igStatus);
        }

        $this->output->keyValue('Iterations', (string) $iterations);
        $this->output->writeln('');

        // Determine backends to test
        $backends = [];
        if ($compareMode) {
            $backends[] = ['name' => 'array', 'igbinary' => true, 'label' => 'Array+igbinary'];
            $backends[] = ['name' => 'array', 'igbinary' => false, 'label' => 'Array+serialize'];
            if (extension_loaded('pdo_sqlite')) {
                $backends[] = ['name' => 'sqlite', 'igbinary' => null, 'label' => 'SQLite'];
            }
        } else {
            $backends[] = [
                'name' => $currentBackend,
                'igbinary' => $useIgbinary,
                'label' => $backendDisplay,
            ];
        }

        if ($compareMode) {
            $this->output->writeln('  ' . $this->output->color('ℹ', Output::PRIMARY) . ' Comparison mode requires rebuilding indexes for each backend.');
            $this->output->writeln('    This may take a moment for large sites.');
            $this->output->writeln('');
        }

        $results = [];

        foreach ($backends as $backendConfig) {
            $backendName = $backendConfig['name'];
            $igbinaryEnabled = $backendConfig['igbinary'];
            $label = $backendConfig['label'];

            $this->output->writeln('  Testing ' . $this->output->color($label, Output::BOLD) . '...');

            // Measure index build time
            $buildTime = 0;
            if ($compareMode) {
                $buildStart = hrtime(true);
                if ($backendName === 'array') {
                    $this->rebuildWithConfig($backendName, $igbinaryEnabled);
                } elseif ($backendName === 'sqlite') {
                    $this->rebuildWithConfig($backendName, null);
                }
                $buildEnd = hrtime(true);
                $buildTime = ($buildEnd - $buildStart) / 1_000_000;
            } else {
                $buildStart = hrtime(true);
                $this->rebuildWithConfig($currentBackend, $useIgbinary);
                $buildEnd = hrtime(true);
                $buildTime = ($buildEnd - $buildStart) / 1_000_000;
            }
            $results[$label]['_build_time'] = $buildTime;

            // Force backend selection
            $repository->setBackendOverride($backendName);
            $repository->clearCache();

            // Get a sample slug for testing
            $sampleSlug = 'test';
            try {
                $allItems = $repository->allRaw('post');
                if (!empty($allItems)) {
                    $firstItem = reset($allItems);
                    $sampleSlug = $firstItem['slug'] ?? 'test';
                }
            } catch (\Throwable $e) {
                // Ignore errors if content not found
            }

            $tests = [
                'Count' => fn() => $repository->count('post'),
                'Get by slug' => fn() => $repository->getFromIndex('post', $sampleSlug),
                'Recent (page 1)' => fn() => $repository->getRecentItems('post', 1, 10),
                'Archive (page 50)' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('date', 'desc')->perPage(10)->page(50)->get(),
                'Sort by date' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('date', 'asc')->perPage(10)->get(),
                'Sort by title' => fn() => (new \Ava\Content\Query($this->app))->type('post')->orderBy('title', 'asc')->perPage(10)->get(),
                'Search' => fn() => (new \Ava\Content\Query($this->app))->type('post')->search('lorem')->perPage(10)->get(),
            ];

            foreach ($tests as $testName => $testFn) {
                $times = [];

                try {
                    $testFn();
                } catch (\Throwable $e) {
                    continue;
                }
                $repository->clearCache();

                for ($i = 0; $i < $iterations; $i++) {
                    $start = hrtime(true);
                    $testFn();
                    $end = hrtime(true);
                    $times[] = ($end - $start) / 1_000_000;
                    $repository->clearCache();
                }

                $avg = array_sum($times) / count($times);
                $results[$label][$testName] = $avg;
            }

            // Memory test
            $repository->clearCache();
            $memBefore = memory_get_usage(false);
            $repository->allRaw('post');
            $memAfter = memory_get_usage(false);
            $results[$label]['_memory'] = max(0, $memAfter - $memBefore);

            // Cache size
            $cachePath = $this->app->configPath('storage') . '/cache';
            if ($backendName === 'sqlite') {
                $sqlitePath = $cachePath . '/content_index.sqlite';
                $results[$label]['_cache_size'] = file_exists($sqlitePath) ? filesize($sqlitePath) : 0;
            } else {
                $size = 0;
                foreach (['content_index.bin', 'slug_lookup.bin', 'recent_cache.bin'] as $file) {
                    $path = $cachePath . '/' . $file;
                    if (file_exists($path)) {
                        $size += filesize($path);
                    }
                }
                $results[$label]['_cache_size'] = $size;
            }
        }

        // Reset
        $repository->setBackendOverride(null);
        $repository->clearCache();

        if ($compareMode) {
            $this->rebuildWithConfig($currentBackend, $useIgbinary);
        }

        // Display results
        $this->output->writeln('');
        echo $this->output->color('  ─── Results ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(str_repeat('─', 45), Output::PRIMARY, Output::BOLD) . "\n";
        $this->output->writeln('');

        $backendLabels = array_keys($results);
        $testNames = ['Count', 'Get by slug', 'Recent (page 1)', 'Archive (page 50)', 'Sort by date', 'Sort by title', 'Search'];

        // Header
        $header = str_pad('Test', 20);
        foreach ($backendLabels as $label) {
            $header .= str_pad($label, 18);
        }
        $this->output->writeln('  ' . $this->output->color($header, Output::BOLD));
        $this->output->writeln('  ' . $this->output->color(str_repeat('─', 20 + 18 * count($backendLabels)), Output::DIM));

        // Rows
        foreach ($testNames as $testName) {
            $row = str_pad($testName, 20);

            foreach ($backendLabels as $label) {
                $avg = $results[$label][$testName] ?? 0;
                $formatted = $avg < 1 ? sprintf('%.2fms', $avg) : sprintf('%.1fms', $avg);
                $row .= str_pad($formatted, 18);
            }

            $this->output->writeln('  ' . $row);
        }

        // Memory and cache size
        $this->output->writeln('  ' . $this->output->color(str_repeat('─', 20 + 18 * count($backendLabels)), Output::DIM));

        $buildRow = str_pad('Build index', 20);
        foreach ($backendLabels as $label) {
            $buildTime = $results[$label]['_build_time'] ?? 0;
            $formatted = $buildTime >= 1000 ? sprintf('%.1fs', $buildTime / 1000) : sprintf('%.0fms', $buildTime);
            $buildRow .= str_pad($formatted, 18);
        }
        $this->output->writeln('  ' . $buildRow);

        $memRow = str_pad('Memory', 20);
        foreach ($backendLabels as $label) {
            $memRow .= str_pad($this->output->formatBytes($results[$label]['_memory']), 18);
        }
        $this->output->writeln('  ' . $memRow);

        $cacheRow = str_pad('Cache size', 20);
        foreach ($backendLabels as $label) {
            $cacheRow .= str_pad($this->output->formatBytes($results[$label]['_cache_size']), 18);
        }
        $this->output->writeln('  ' . $cacheRow);

        // Webpage rendering benchmarks
        $this->output->writeln('');
        echo $this->output->color('  ─── Webpage Rendering ', Output::PRIMARY, Output::BOLD);
        echo $this->output->color(str_repeat('─', 36), Output::PRIMARY, Output::BOLD) . "\n";
        $this->output->writeln('');

        $webpageResults = $this->benchmarkWebpageRendering($iterations);

        $this->output->writeln('  ' . $this->output->color(str_pad('Operation', 30) . str_pad('Time', 15), Output::BOLD));
        $this->output->writeln('  ' . $this->output->color(str_repeat('─', 45), Output::DIM));

        foreach ($webpageResults as $testName => $avgTime) {
            $formatted = $avgTime < 1 ? sprintf('%.2fms', $avgTime) : sprintf('%.1fms', $avgTime);
            $this->output->writeln('  ' . str_pad($testName, 30) . str_pad($formatted, 15));
        }

        $this->output->writeln('');

        if (!$compareMode) {
            $this->output->writeln('  ' . $this->output->color('💡 Tip:', Output::YELLOW) . ' Run with ' . $this->output->color('--compare', Output::PRIMARY) . ' to test all backends.');
        }

        $this->output->writeln('  ' . $this->output->color('📚 Docs:', Output::BLUE) . ' https://ava.addy.zone/docs/performance');
        $this->output->writeln('');

        return 0;
    }

    private function benchmarkWebpageRendering(int $iterations): array
    {
        $results = [];
        $repository = $this->app->repository();
        $webpageCache = $this->app->webpageCache();
        $renderer = $this->app->renderer();

        $sampleSlug = null;
        try {
            $recentResult = $repository->getRecentItems('post', 1, 1);
            if (!empty($recentResult['items'])) {
                $sampleSlug = $recentResult['items'][0]['slug'] ?? null;
            }
        } catch (\Throwable $e) {
            // No posts available
        }

        if ($sampleSlug === null) {
            return ['No posts available for test' => 0];
        }

        $webpageCache->clear();

        // Test 1: Render uncached
        $times = [];
        $output = '';
        for ($i = 0; $i < $iterations; $i++) {
            $item = $repository->get('post', $sampleSlug);
            if ($item === null) {
                continue;
            }

            $start = hrtime(true);
            $html = $renderer->renderMarkdown($item->rawContent());
            $output = $renderer->render('single', [
                'item' => $item->withHtml($html),
                'content_type' => 'post',
            ]);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;
        }
        if (!empty($times)) {
            $results['Render post (uncached)'] = array_sum($times) / count($times);
        }

        // Test 2: Write to webpage cache
        $cachePath = $this->app->configPath('storage') . '/cache/pages';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $cacheFile = $cachePath . '/benchmark_test_' . $i . '.html';
            $testHtml = $output;

            $start = hrtime(true);
            file_put_contents($cacheFile, $testHtml, LOCK_EX);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;

            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
        if (!empty($times)) {
            $results['Cache write'] = array_sum($times) / count($times);
        }

        // Test 3: Read from webpage cache
        $cacheFile = $cachePath . '/benchmark_test.html';
        $testHtml = $output;
        file_put_contents($cacheFile, $testHtml, LOCK_EX);

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            clearstatcache(true, $cacheFile);

            $start = hrtime(true);
            $content = file_get_contents($cacheFile);
            $end = hrtime(true);
            $times[] = ($end - $start) / 1_000_000;
        }
        if (!empty($times)) {
            $results['Cache read (HIT)'] = array_sum($times) / count($times);
        }

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        return $results;
    }

    private function rebuildWithConfig(string $backend, ?bool $useIgbinary): void
    {
        $indexer = new \Ava\Content\Indexer($this->app, $backend, $useIgbinary);
        $indexer->rebuild();
    }
}
