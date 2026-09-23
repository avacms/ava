<?php

declare(strict_types=1);

namespace Ava\Content\Index;

use Ava\Support\AtomicFile;
use Ava\Support\SignedCache;

/**
 * On-disk layout of the content index.
 *
 *   storage/cache/
 *     state.json          which generation is live, its backend and fingerprint
 *     .cache_key          HMAC key shared by every generation
 *     .rebuild.lock       held by whichever process is building
 *     index/<generation>/ one complete, immutable build
 *     pages/              webpage cache (owned by WebpageCache)
 *
 * A rebuild writes a fresh generation directory and then atomically replaces
 * state.json, so readers never wait for a rebuild and never see a half-written
 * index: a request reads state.json once and uses that generation throughout.
 * Replaced generations are kept for a grace period so requests already reading
 * them can finish.
 */
final class IndexStore
{
    public const STATE_VERSION = 1;

    /** How long a replaced generation stays readable. */
    private const RETIREMENT_GRACE = 120;

    /** How long a failed automatic rebuild of the same sources is not retried. */
    private const FAILURE_BACKOFF = 300;

    /** Files written by versions before generation directories existed. */
    private const LEGACY_FILES = [
        'content_index.bin', 'content_index.sqlite', 'content_index.sqlite-wal',
        'content_index.sqlite-shm', 'tax_index.bin', 'routes.bin', 'recent_cache.bin',
        'slug_lookup.bin', 'html_cache.bin', 'synonyms.bin', 'stopwords.bin',
        'fingerprint.json',
    ];

    private string $cacheRoot;
    private ?array $state = null;
    private bool $stateLoaded = false;

    public function __construct(string $storagePath)
    {
        $this->cacheRoot = rtrim($storagePath, '/\\') . '/cache';
    }

    public function cacheRoot(): string
    {
        return $this->cacheRoot;
    }

    // -------------------------------------------------------------------------
    // Live generation
    // -------------------------------------------------------------------------

    /**
     * The live generation's state, read once per request.
     *
     * @return array{version: int, generation: string, backend: string, built_at: string, fingerprint: array}|null
     */
    public function state(): ?array
    {
        if (!$this->stateLoaded) {
            $this->state = $this->readState();
            $this->stateLoaded = true;
        }

        return $this->state;
    }

    /**
     * Forget the memoised state, e.g. after another process may have published.
     */
    public function reloadState(): ?array
    {
        $this->stateLoaded = false;

        return $this->state();
    }

    public function hasGeneration(): bool
    {
        return $this->state() !== null;
    }

    /**
     * Absolute path of the live generation, or null if nothing is built yet.
     */
    public function currentPath(): ?string
    {
        $state = $this->state();

        return $state === null ? null : $this->generationPath($state['generation']);
    }

    public function backend(): ?string
    {
        return $this->state()['backend'] ?? null;
    }

    public function keyDirectory(): string
    {
        return $this->cacheRoot;
    }

    // -------------------------------------------------------------------------
    // Building and publishing
    // -------------------------------------------------------------------------

    /**
     * Create an empty directory for a generation that is about to be built.
     *
     * @return array{0: string, 1: string} [generation id, absolute path]
     */
    public function createGeneration(): array
    {
        $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $path = $this->generationPath($id);
        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create index directory: ' . $path);
        }

        return [$id, $path];
    }

    /**
     * Make a fully written generation live.
     */
    public function publish(string $generation, string $backend, array $fingerprint): void
    {
        $previous = $this->reloadState();

        $this->writeState([
            'version' => self::STATE_VERSION,
            'generation' => $generation,
            'backend' => $backend,
            'built_at' => date('c'),
            'fingerprint' => $fingerprint,
        ]);

        if ($previous !== null && $previous['generation'] !== $generation) {
            @touch($this->generationPath($previous['generation']) . '/.retired');
        }
    }

    /**
     * Record a new fingerprint for the live generation (presentation-only
     * changes need no rebuild, just a new baseline).
     */
    public function updateFingerprint(array $fingerprint): void
    {
        $state = $this->reloadState();
        if ($state === null) {
            return;
        }

        $state['fingerprint'] = $fingerprint;
        $this->writeState($state);
    }

    public function discardGeneration(string $path): void
    {
        self::removeDirectory($path);
    }

    /**
     * Delete generations that are neither live nor still in their grace
     * period. Callers hold the rebuild lock, so no other build is running and
     * any unretired, non-live directory is debris from a crashed build.
     */
    public function collectGarbage(): void
    {
        $live = $this->reloadState()['generation'] ?? null;
        $now = time();

        foreach (glob($this->cacheRoot . '/index/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (basename($directory) === $live) {
                continue;
            }

            $retired = @filemtime($directory . '/.retired');
            if ($retired === false || $retired < $now - self::RETIREMENT_GRACE) {
                self::removeDirectory($directory);
            }
        }
    }

    /**
     * Remove cache files written by versions before generation directories.
     */
    public function removeLegacyArtifacts(): void
    {
        foreach (self::LEGACY_FILES as $file) {
            $path = $this->cacheRoot . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Reading and writing generation files
    // -------------------------------------------------------------------------

    public function readBinary(string $generationPath, string $name): ?array
    {
        return SignedCache::read($generationPath . '/' . $name, $this->keyDirectory());
    }

    public function writeBinary(string $generationPath, string $name, array $data, bool $useIgbinary): void
    {
        SignedCache::write($generationPath . '/' . $name, $data, $useIgbinary, $this->keyDirectory());
    }

    public function keyIsReadable(): bool
    {
        return SignedCache::keyIsReadable($this->keyDirectory());
    }

    // -------------------------------------------------------------------------
    // Coordination between processes
    // -------------------------------------------------------------------------

    /**
     * Run a callback while holding the rebuild lock.
     *
     * @param bool $wait False to give up immediately when another process
     *                   holds the lock (the caller keeps serving the live
     *                   generation instead of queueing behind the build).
     * @return bool Whether the callback ran.
     */
    public function withRebuildLock(callable $callback, bool $wait = true): bool
    {
        $this->ensureCacheRoot();
        $lock = @fopen($this->cacheRoot . '/.rebuild.lock', 'c+b');
        if ($lock === false) {
            throw new \RuntimeException('Unable to open content index rebuild lock.');
        }

        try {
            if (!flock($lock, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
                if ($wait) {
                    throw new \RuntimeException('Unable to acquire content index rebuild lock.');
                }
                return false;
            }

            try {
                $callback();
            } finally {
                flock($lock, LOCK_UN);
            }

            return true;
        } finally {
            fclose($lock);
        }
    }

    /**
     * Has a freshness check passed within the last $seconds?
     */
    public function checkedWithin(int $seconds): bool
    {
        if ($seconds <= 0) {
            return false;
        }

        $checked = @filemtime($this->cacheRoot . '/.checked');

        return $checked !== false && $checked > time() - $seconds;
    }

    public function markChecked(): void
    {
        @touch($this->cacheRoot . '/.checked');
    }

    /**
     * Note that an automatic rebuild of these sources is starting. If the
     * process dies (a fatal out-of-memory error cannot be caught), the marker
     * survives and stops every following request retrying the same doomed
     * build.
     */
    public function recordAttempt(string $identity): void
    {
        $this->ensureCacheRoot();
        AtomicFile::write(
            $this->cacheRoot . '/.rebuild-attempt',
            json_encode(['identity' => $identity, 'started_at' => time()]) ?: '{}'
        );
    }

    public function clearAttempt(): void
    {
        @unlink($this->cacheRoot . '/.rebuild-attempt');
    }

    /**
     * Did an automatic rebuild of exactly these sources fail recently?
     */
    public function recentlyFailed(string $identity): bool
    {
        $contents = @file_get_contents($this->cacheRoot . '/.rebuild-attempt');
        $attempt = $contents === false ? null : json_decode($contents, true);

        return is_array($attempt)
            && ($attempt['identity'] ?? null) === $identity
            && (int) ($attempt['started_at'] ?? 0) > time() - self::FAILURE_BACKOFF;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function generationPath(string $generation): string
    {
        return $this->cacheRoot . '/index/' . $generation;
    }

    private function readState(): ?array
    {
        $contents = @file_get_contents($this->cacheRoot . '/state.json');
        if ($contents === false) {
            return null;
        }

        $state = json_decode($contents, true);
        if (
            !is_array($state)
            || ($state['version'] ?? null) !== self::STATE_VERSION
            || !is_string($state['generation'] ?? null)
            || preg_match('/^[A-Za-z0-9-]+$/', $state['generation']) !== 1
            || !in_array($state['backend'] ?? null, ['array', 'sqlite'], true)
            || !is_array($state['fingerprint'] ?? null)
            || !is_dir($this->generationPath($state['generation']))
        ) {
            return null;
        }

        return $state;
    }

    private function writeState(array $state): void
    {
        $this->ensureCacheRoot();
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!AtomicFile::write($this->cacheRoot . '/state.json', $json)) {
            throw new \RuntimeException('Unable to publish content index state.');
        }

        $this->state = $state;
        $this->stateLoaded = true;
    }

    private function ensureCacheRoot(): void
    {
        if (!is_dir($this->cacheRoot) && !@mkdir($this->cacheRoot, 0755, true) && !is_dir($this->cacheRoot)) {
            throw new \RuntimeException('Unable to create cache directory: ' . $this->cacheRoot);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($directory);
    }
}
