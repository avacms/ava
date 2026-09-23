<?php

declare(strict_types=1);

namespace Ava\Content\Index;

/**
 * Cheap change detection for the files an index generation was built from.
 *
 * Each source directory is summarised from file metadata alone (path, size,
 * mtime, ctime, inode), which costs one stat per file instead of hashing every
 * byte. Metadata can only miss a change made within the same clock second as
 * the snapshot, so files touched that recently are content-hashed as well and
 * re-checked by hash later (git handles "racily clean" index entries the same
 * way). ctime and inode catch edits that preserve size and mtime, such as
 * `touch -m`, rsync -t and editors that save by renaming.
 *
 * Every source has a scope: an "index" change needs a rebuild, while a
 * "presentation" change (templates, assets, snippets) only needs cached pages
 * cleared.
 */
final class Fingerprint
{
    public const VERSION = 5;
    public const SCOPE_INDEX = 'index';
    public const SCOPE_PRESENTATION = 'presentation';

    /** Seconds within which a file's metadata cannot be trusted alone. */
    private const RACY_WINDOW = 2;

    private const IGNORED_EXTENSIONS = ['log', 'cache', 'tmp', 'lock'];

    /**
     * @param array<string, array{path: string, scope: string}> $sources
     */
    public function __construct(private array $sources)
    {
    }

    /**
     * Snapshot the current state of every source.
     *
     * @return array{version: int, sources: array<string, array>, racy: array<string, array<string, string>>}
     */
    public function capture(): array
    {
        clearstatcache();
        $now = time();
        $sources = [];
        $racy = [];

        foreach ($this->sources as $name => $source) {
            [$summary, $racyFiles] = $this->scan($source['path'], $now);
            $sources[$name] = ['scope' => $source['scope']] + $summary;
            if ($racyFiles !== []) {
                $racy[$name] = $racyFiles;
            }
        }

        return ['version' => self::VERSION, 'sources' => $sources, 'racy' => $racy];
    }

    /**
     * Which scopes changed since a stored snapshot?
     *
     * @param array|null $current Receives the current per-source summaries
     *                            (pass to identity() to name this state).
     * @return list<string> Empty when nothing changed.
     */
    public function changes(array $stored, ?array &$current = null): array
    {
        clearstatcache();
        $now = time();
        $changed = [];
        $current = [];

        foreach ($this->sources as $name => $source) {
            [$summary] = $this->scan($source['path'], $now, hashRecent: false);
            $current[$name] = ['scope' => $source['scope']] + $summary;
        }

        if (($stored['version'] ?? null) !== self::VERSION || !is_array($stored['sources'] ?? null)) {
            return [self::SCOPE_INDEX];
        }

        foreach ($this->sources as $name => $source) {
            $before = $stored['sources'][$name] ?? null;
            if (!is_array($before) || !self::sameSummary($before, $current[$name])) {
                $changed[$source['scope']] = true;
            }
        }

        // A source that disappeared from configuration (a disabled plugin)
        // changes whatever it used to contribute to.
        foreach ($stored['sources'] as $name => $before) {
            if (!isset($this->sources[$name])) {
                $changed[$before['scope'] ?? self::SCOPE_INDEX] = true;
            }
        }

        foreach ($stored['racy'] ?? [] as $name => $files) {
            $source = $this->sources[$name] ?? null;
            if ($source === null || isset($changed[$source['scope']]) || !is_array($files)) {
                continue;
            }
            foreach ($files as $relative => $hash) {
                $relative = (string) $relative;
                $path = $relative === '' ? $source['path'] : $source['path'] . '/' . $relative;
                if (self::hashFile($path) !== $hash) {
                    $changed[$source['scope']] = true;
                    break;
                }
            }
        }

        return array_keys($changed);
    }

    /**
     * Stable identity of a snapshot, used to recognise a retry of one change.
     */
    public static function identity(array $capture): string
    {
        return hash('sha256', json_encode($capture['sources'] ?? []) ?: '');
    }

    /**
     * @return array{0: array{exists: bool, count: int, digest: string|null}, 1: array<string, string>}
     */
    private function scan(string $path, int $now, bool $hashRecent = true): array
    {
        if (is_file($path)) {
            $stat = @stat($path);
            $racy = [];
            if ($hashRecent && $stat !== false && max($stat['mtime'], $stat['ctime']) >= $now - self::RACY_WINDOW) {
                $racy[''] = self::hashFile($path);
            }

            return [
                ['exists' => true, 'count' => 1, 'digest' => hash('sha256', self::statLine('', $stat))],
                $racy,
            ];
        }

        if (!is_dir($path)) {
            return [['exists' => false, 'count' => 0, 'digest' => null], []];
        }

        $root = rtrim(str_replace('\\', '/', $path), '/');
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    static function (\SplFileInfo $file): bool {
                        // Dot entries are never content: this also skips a
                        // .git directory, whose churn would force rebuilds.
                        if (str_starts_with($file->getFilename(), '.')) {
                            return false;
                        }

                        return $file->isDir()
                            || !in_array(strtolower($file->getExtension()), self::IGNORED_EXTENSIONS, true);
                    }
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $pathname = $file->getPathname();
                $relative = ltrim(substr(str_replace('\\', '/', $pathname), strlen($root)), '/');
                $files[$relative] = $pathname;
            }
        } catch (\UnexpectedValueException) {
            return [['exists' => true, 'count' => 0, 'digest' => 'unreadable'], []];
        }

        ksort($files, SORT_STRING);
        $context = hash_init('sha256');
        $racy = [];

        foreach ($files as $relative => $pathname) {
            $relative = (string) $relative; // "2024" would otherwise be an int key
            $stat = @stat($pathname);
            hash_update($context, self::statLine($relative, $stat));

            if ($hashRecent && $stat !== false && max($stat['mtime'], $stat['ctime']) >= $now - self::RACY_WINDOW) {
                $racy[$relative] = self::hashFile($pathname);
            }
        }

        return [
            ['exists' => true, 'count' => count($files), 'digest' => hash_final($context)],
            $racy,
        ];
    }

    private static function statLine(string $relative, array|false $stat): string
    {
        if ($stat === false) {
            return $relative . "\0unreadable\n";
        }

        return $relative . "\0" . $stat['size'] . "\0" . $stat['mtime'] . "\0" . $stat['ctime']
            . "\0" . $stat['ino'] . "\n";
    }

    private static function sameSummary(array $before, array $after): bool
    {
        return ($before['exists'] ?? null) === $after['exists']
            && ($before['count'] ?? null) === $after['count']
            && ($before['digest'] ?? null) === $after['digest'];
    }

    private static function hashFile(string $path): string
    {
        $hash = is_file($path) ? @hash_file('sha256', $path) : false;

        return $hash === false ? 'missing' : $hash;
    }
}
