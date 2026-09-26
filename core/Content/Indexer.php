<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;
use Ava\Content\Backends\SqliteBackend;
use Ava\Content\Index\ContentScanner;
use Ava\Content\Index\Fingerprint;
use Ava\Content\Index\IndexBuilder;
use Ava\Content\Index\IndexStore;
use Ava\Content\Index\ItemPaths;
use Ava\Content\Index\PrerenderedHtml;
use Ava\Plugins\Hooks;
use Ava\Support\LogRotator;
use Ava\Support\SignedCache;

/**
 * Builds content index generations and decides, per request, whether the live
 * one is still current.
 *
 * A build writes a complete new generation beside the live one and then
 * publishes it atomically (see IndexStore), so requests never wait on a
 * rebuild and never read a half-written index.
 */
final class Indexer
{
    private const MAX_BUILD_ATTEMPTS = 3;

    /** Builds that took longer than this last time run after the response. */
    private const float INLINE_BUILD_SECONDS = 1.0;

    private Application $app;
    private IndexStore $store;
    private ItemPaths $paths;
    private ?Fingerprint $fingerprint = null;

    public function __construct(
        Application $app,
        private ?string $backendOverride = null,
        private ?bool $igbinaryOverride = null
    ) {
        $this->app = $app;
        $this->store = $app->indexStore();
        $this->paths = new ItemPaths($app->configPath('content'));
    }

    public function store(): IndexStore
    {
        return $this->store;
    }

    /**
     * The sources an index depends on, and what a change to each requires.
     */
    public function fingerprint(): Fingerprint
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $theme = $this->app->themeName();
        $themePath = $this->app->configPath('themes') . '/' . $theme;

        $sources = [
            'content' => ['path' => $this->app->configPath('content'), 'scope' => Fingerprint::SCOPE_INDEX],
            'config' => ['path' => $this->app->path('app/config'), 'scope' => Fingerprint::SCOPE_INDEX],
            // theme.php can register hooks that change how content renders.
            'theme-bootstrap' => ['path' => $themePath . '/theme.php', 'scope' => Fingerprint::SCOPE_INDEX],
            // Templates, partials and assets only affect cached pages.
            'theme' => ['path' => $themePath, 'scope' => Fingerprint::SCOPE_PRESENTATION],
            'snippets' => ['path' => $this->app->configPath('snippets'), 'scope' => Fingerprint::SCOPE_PRESENTATION],
            'redirects' => [
                'path' => $this->app->configPath('storage') . '/redirects.json',
                'scope' => Fingerprint::SCOPE_PRESENTATION,
            ],
        ];

        foreach ($this->app->pluginNames() as $plugin) {
            $sources['plugin:' . $plugin] = [
                'path' => $this->app->configPath('plugins') . '/' . $plugin,
                'scope' => Fingerprint::SCOPE_INDEX,
            ];
        }

        return $this->fingerprint = new Fingerprint($sources);
    }

    /**
     * Is the live generation built from the current sources?
     */
    public function isCacheFresh(): bool
    {
        $state = $this->store->reloadState();

        return $state !== null && $this->fingerprint()->changes($state['fingerprint']) === [];
    }

    /**
     * Rebuild now, waiting for any other rebuild to finish first.
     */
    public function rebuild(bool $clearWebpageCache = true): void
    {
        $this->store->withRebuildLock(fn() => $this->build($clearWebpageCache));
    }

    /**
     * Rebuild only if sources changed; presentation-only changes just clear
     * cached pages. Waits for any other rebuild to finish first.
     */
    public function rebuildIfStale(bool $clearWebpageCache = true): void
    {
        $this->store->withRebuildLock(function () use ($clearWebpageCache): void {
            $state = $this->store->reloadState();
            $changes = $state === null
                ? [Fingerprint::SCOPE_INDEX]
                : $this->fingerprint()->changes($state['fingerprint']);

            if (in_array(Fingerprint::SCOPE_INDEX, $changes, true)) {
                $this->build($clearWebpageCache);
            } elseif ($changes !== []) {
                $this->refreshPresentation();
            }
        });
    }

    /**
     * Bring the index up to date for the current request.
     *
     * - Nothing built yet: build, waiting if another process is building.
     * - never:  use the live generation as is (./ava rebuild updates it).
     * - always: rebuild on every request (debugging only).
     * - auto:   at most once per check_interval seconds, compare file
     *           metadata with the live generation. Presentation-only changes
     *           clear cached pages. Content changes are rebuilt by one
     *           request while every other request keeps using the live
     *           generation, after that request's response is sent if the
     *           last build took over a second. A failed automatic rebuild is
     *           logged and not retried for the same sources for a few
     *           minutes, so a build that runs out of memory cannot take
     *           every request down with it.
     */
    public function refresh(string $mode): void
    {
        if ($this->store->state() === null) {
            $this->store->withRebuildLock(function (): void {
                if ($this->store->reloadState() === null) {
                    $this->build(true);
                }
            });
            return;
        }

        if ($mode === 'never') {
            return;
        }

        if ($mode === 'always') {
            $this->rebuild();
            return;
        }

        $interval = max(0, (int) $this->app->config('content_index.check_interval', 1));
        if ($this->store->checkedWithin($interval)) {
            return;
        }

        // One request scans at a time; the rest keep serving the live index.
        $this->store->withCheckLock(function () use ($interval): void {
            if (!$this->store->checkedWithin($interval)) {
                $this->checkSources();
            }
        });
    }

    private function checkSources(): void
    {
        if (!$this->store->keyIsReadable()) {
            error_log('Ava: ' . SignedCache::describeUnreadableKey($this->store->keyDirectory()));
        }

        $current = null;
        $changes = $this->fingerprint()->changes($this->store->state()['fingerprint'], $current);
        $this->store->markChecked();

        if ($changes === []) {
            return;
        }

        if (!in_array(Fingerprint::SCOPE_INDEX, $changes, true)) {
            $this->runOrDefer(fn() => $this->store->withRebuildLock(function (): void {
                $state = $this->store->reloadState();
                $changes = $state === null ? [] : $this->fingerprint()->changes($state['fingerprint']);
                if ($changes !== [] && !in_array(Fingerprint::SCOPE_INDEX, $changes, true)) {
                    $this->refreshPresentation();
                }
            }, wait: false));
            return;
        }

        $identity = Fingerprint::identity(['sources' => $current]);
        if ($this->store->recentlyFailed($identity)) {
            return;
        }

        $this->runOrDefer(fn() => $this->store->withRebuildLock(function () use ($identity): void {
            $state = $this->store->reloadState();
            if ($state !== null && $this->fingerprint()->changes($state['fingerprint']) === []) {
                return; // Another process rebuilt while we waited for the lock.
            }

            $this->store->recordAttempt($identity);
            try {
                $this->build(true);
            } catch (\Throwable $e) {
                // The attempt marker stays, so this is not retried on every
                // request; visitors keep getting the previous generation.
                error_log(
                    'Ava: automatic content index rebuild failed, still serving the previous index: '
                    . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                    . '. It is retried when files change again or in 5 minutes; run ./ava rebuild for details.'
                );
            }
        }, wait: false));
    }

    /**
     * Quick rebuilds run now, so an edit shows on the next refresh; slow ones
     * run after the response so no visitor waits for them.
     */
    private function runOrDefer(callable $task): void
    {
        if ($this->store->lastBuildSeconds() < self::INLINE_BUILD_SECONDS) {
            $task();
        } else {
            $this->app->defer($task);
        }
    }

    /**
     * Validate content files without building anything.
     *
     * @return array{errors: array<string>, warnings: array<string>}
     */
    public function lint(): array
    {
        // Plugins report their own problems through the lint.errors filter.
        $this->app->loadExtensions();
        $contentTypes = $this->app->contentTypes();
        $scan = $this->scanner()->scan($contentTypes, collectWarnings: true);

        $builder = $this->builder();
        $builder->routes($scan['items'], $contentTypes, $this->app->taxonomies());
        $builder->taxonomyIndex($scan['items'], $this->app->taxonomies(), $contentTypes);
        $builder->synonyms();
        $builder->stopWords();

        $errors = Hooks::apply('lint.errors', array_merge($scan['errors'], $builder->errors()), $this->app);

        return [
            'errors' => array_values(is_array($errors) ? $errors : []),
            'warnings' => $scan['warnings'],
        ];
    }

    // -------------------------------------------------------------------------
    // Building
    // -------------------------------------------------------------------------

    /**
     * Build and publish a generation. Callers hold the rebuild lock.
     */
    private function build(bool $clearWebpageCache): void
    {
        // Pre-rendering must see the same plugin and theme hooks as a page.
        $this->app->loadExtensions();

        $backend = $this->backendOverride ?? (string) $this->app->config('content_index.backend', 'array');
        if (!in_array($backend, ['array', 'sqlite'], true)) {
            throw new \RuntimeException("Unknown content_index.backend '{$backend}'; use 'array' or 'sqlite'.");
        }
        if ($backend === 'sqlite' && !extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException(
                "SQLite backend requires the pdo_sqlite extension. Install it or set backend to 'array' in config."
            );
        }

        $started = microtime(true);
        for ($attempt = 1; ; $attempt++) {
            $snapshot = $this->fingerprint()->capture();
            [$generation, $path] = $this->store->createGeneration();

            try {
                $errors = $this->writeGeneration($path, $backend);

                // Publish a consistent snapshot only: if content changed while
                // it was being read, build again from the new state.
                $stable = !in_array(Fingerprint::SCOPE_INDEX, $this->fingerprint()->changes($snapshot), true);
                if ($stable) {
                    $this->store->publish($generation, $backend, $snapshot, microtime(true) - $started);
                }
            } catch (\Throwable $e) {
                $this->store->discardGeneration($path);
                throw $e;
            }

            if ($stable) {
                break;
            }

            $this->store->discardGeneration($path);
            if ($attempt >= self::MAX_BUILD_ATTEMPTS) {
                throw new \RuntimeException('Source files kept changing during the content index rebuild.');
            }
        }

        $this->app->repository()->clearCache();
        if ($clearWebpageCache) {
            $this->app->webpageCache()->clear();
        }
        $this->store->clearAttempt();
        $this->store->markChecked();
        $this->store->collectGarbage();
        $this->store->removeLegacyArtifacts();

        if ($errors !== []) {
            $this->logErrors($errors);
        }

        Hooks::doAction('indexer.rebuild', $this->app);
    }

    /**
     * Write every artifact of one generation.
     *
     * @return list<string> Problems worth logging.
     */
    private function writeGeneration(string $path, string $backend): array
    {
        $contentTypes = $this->app->contentTypes();
        $taxonomies = $this->app->taxonomies();
        $igbinary = $this->igbinaryOverride ?? (bool) $this->app->config('content_index.use_igbinary', true);

        $scan = $this->scanner()->scan($contentTypes);
        $items = $scan['items'];
        $builder = $this->builder();

        $routes = $builder->routes($items, $contentTypes, $taxonomies);
        $taxIndex = $builder->taxonomyIndex($items, $taxonomies, $contentTypes);

        $this->store->writeBinary($path, 'routes.bin', $routes, $igbinary);
        $this->store->writeBinary($path, 'tax_index.bin', $taxIndex, $igbinary);
        $this->store->writeBinary($path, 'slug_lookup.bin', $builder->slugLookup($items, $contentTypes), $igbinary);
        $this->store->writeBinary($path, 'recent_cache.bin', $builder->recentCache($items, $contentTypes), $igbinary);
        $this->store->writeBinary($path, 'synonyms.bin', $builder->synonyms(), $igbinary);
        $this->store->writeBinary($path, 'stopwords.bin', $builder->stopWords(), $igbinary);

        if ($backend === 'sqlite') {
            $this->writeSqlite($path . '/content_index.sqlite', $items, $contentTypes, $taxIndex, $routes, $builder);
        } else {
            $this->store->writeBinary($path, 'content_index.bin', $builder->contentIndex($items, $contentTypes), $igbinary);
            foreach ($builder->bodies($items, $contentTypes) as $number => $shard) {
                $this->store->writeBinary($path, "bodies/{$number}.bin", $shard, $igbinary);
            }
        }
        unset($routes, $taxIndex);

        $errors = array_merge($scan['errors'], $builder->errors());

        if ($this->app->config('content_index.prerender_html', true)) {
            $errors = array_merge($errors, $this->prerender($path, $items, $contentTypes, $igbinary));
        }

        return $errors;
    }

    /**
     * @param array<string, list<Item>> $items
     */
    private function writeSqlite(
        string $database,
        array $items,
        array $contentTypes,
        array $taxIndex,
        array $routes,
        IndexBuilder $builder
    ): void {
        $sqlite = new SqliteBackend($database, $this->app->configPath('content'), writable: true);

        try {
            $sqlite->createDatabase();
            $sqlite->beginTransaction();

            foreach ($items as $type => $typeItems) {
                $typeConfig = $contentTypes[$type] ?? [];
                foreach ($typeItems as $item) {
                    $key = $this->paths->contentKey($item, $typeConfig);
                    $data = $builder->metadata($item, (string) $type, $key, $typeConfig);
                    $data['file_path'] = $data['relative_path'];
                    $data['body'] = $item->rawContent();
                    $data['meta'] = $data['frontmatter'];
                    $sqlite->insertContent($data);
                }
            }

            foreach ($taxIndex as $taxonomy => $taxData) {
                foreach ($taxData['terms'] ?? [] as $term) {
                    $sqlite->insertTerm((string) $taxonomy, $term);
                }
            }

            foreach (['redirects' => 'redirect', 'exact' => 'exact', 'preview' => 'preview'] as $group => $routeType) {
                foreach ($routes[$group] ?? [] as $routePath => $data) {
                    $sqlite->insertRoute((string) $routePath, $routeType, $data);
                }
            }
            foreach ($routes['taxonomy'] ?? [] as $name => $data) {
                $sqlite->insertRoute($data['base'] ?? '/' . $name, 'taxonomy', $data, (string) $name);
            }
            foreach ($routes['reverse'] ?? [] as $key => $url) {
                $sqlite->insertRoute((string) $key, 'reverse', ['url' => $url]);
            }

            $sqlite->commit();
            $sqlite->prepareForPublication();
        } catch (\Throwable $e) {
            $sqlite->rollback();
            $sqlite->clearMemoryCache();
            throw new \RuntimeException('SQLite index build failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Render each public Markdown item into its own file.
     *
     * @param array<string, list<Item>> $items
     * @return list<string>
     */
    private function prerender(string $path, array $items, array $contentTypes, bool $igbinary): array
    {
        $errors = [];

        foreach ($items as $type => $typeItems) {
            $typeConfig = $contentTypes[$type] ?? [];

            foreach ($typeItems as $item) {
                if ($item->isHtml() || (!$item->isPublished() && !$item->isUnlisted())) {
                    continue;
                }

                $key = $this->paths->contentKey($item, $typeConfig);
                try {
                    $html = $this->app->markdown($item->markdownOptions())
                        ->convert($item->rawContent())
                        ->getContent();
                    $this->store->writeBinary(
                        $path,
                        PrerenderedHtml::relativePath((string) $type, $key),
                        PrerenderedHtml::entry($item, $html),
                        $igbinary
                    );
                } catch (\Throwable $e) {
                    $errors[] = "{$item->filePath()}: pre-rendering failed: " . $e->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * Templates, snippets or redirects changed: cached pages are stale, the
     * index is not. Callers hold the rebuild lock.
     */
    private function refreshPresentation(): void
    {
        $this->app->webpageCache()->clear();
        $this->store->updateFingerprint($this->fingerprint()->capture());
        $this->store->markChecked();
    }

    private function scanner(): ContentScanner
    {
        return new ContentScanner($this->app->configPath('content'), $this->paths);
    }

    private function builder(): IndexBuilder
    {
        return new IndexBuilder($this->paths, $this->app->configPath('content'));
    }

    /**
     * @param list<string> $errors
     */
    private function logErrors(array $errors): void
    {
        $logPath = $this->app->configPath('storage') . '/logs';
        if (!is_dir($logPath) && !@mkdir($logPath, 0755, true) && !is_dir($logPath)) {
            return;
        }

        $logFile = $logPath . '/indexer.log';
        LogRotator::rotateIfNeeded(
            $logFile,
            (int) $this->app->config('logs.max_size', 10 * 1024 * 1024),
            (int) $this->app->config('logs.max_files', 3)
        );

        $content = '[' . date('c') . "] Indexer errors:\n";
        foreach ($errors as $error) {
            $content .= "  - {$error}\n";
        }

        @file_put_contents($logFile, $content . "\n", FILE_APPEND | LOCK_EX);
    }
}
