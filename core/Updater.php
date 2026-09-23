<?php

declare(strict_types=1);

namespace Ava;

/**
 * Ava Updater
 *
 * Handles checking for and applying updates from GitHub.
 *
 * Version Format: CalVer YY.M.PATCH (e.g., 26.2.0 = first patch of Feb 2026)
 *
 * What gets updated:
 * - core/, ava (CLI), bootstrap.php, composer.json
 * - public/index.php, public/.htaccess
 * - index.php, .htaccess, nginx.conf.example (root files)
 * - Bundled plugins in app/plugins/ (sitemap, feed, redirects, markdown-extensions)
 *
 * What is preserved (never touched):
 * - content/, app/config/, app/themes/, app/snippets/, storage/, vendor/
 * - Custom plugins, public/robots.txt, .git, .env
 *
 * Updates are staged and installed with rollback backups to remove stale files
 * without leaving a partially updated installation after an ordinary failure.
 */
final class Updater
{
    private const int MAX_DOWNLOAD_BYTES = 64 * 1024 * 1024;
    private const int MAX_ARCHIVE_ENTRIES = 20_000;
    private const int MAX_EXTRACTED_BYTES = 256 * 1024 * 1024;
    private const int MAX_COMPRESSION_RATIO = 200;
    private const array ALLOWED_DOWNLOAD_HOSTS = [
        'api.github.com',
        'codeload.github.com',
        'github.com',
    ];

    private Application $app;
    private string $githubRepo = 'avacms/ava';
    private string $cacheFile;

    /** @var string[] Directories/files that should be updated */
    private array $updateDirs = [
        'core',
        'public/index.php',
        'public/.htaccess',
        'ava',
        'bootstrap.php',
        'composer.json',
        'composer.lock',
        '.htaccess',           // Root htaccess (blocks direct access)
        'nginx.conf.example',  // Nginx configuration example
    ];

    /** @var string[] Bundled plugins (shipped with Ava) */
    private array $bundledPlugins = [
        'sitemap',
        'feed',
        'redirects',
        'markdown-extensions',
    ];

    /** @var string[] Default paths that the updater expects */
    private array $defaultPaths = [
        'themes'   => 'app/themes',
        'plugins'  => 'app/plugins',
        'snippets' => 'app/snippets',
    ];

    /**
     * Directories/files that should NEVER be touched.
     *
     * @var string[]
     */
    private array $preserveDirs = [
        'content',
        'app/config',
        'app/themes',
        'app/snippets',
        'storage',
        'vendor',
        'public/robots.txt',
        '.git',
        '.env',
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->cacheFile = $app->path('storage/cache/update_check.json');
    }

    /**
     * Get current installed version.
     */
    public function currentVersion(): string
    {
        return AVA_VERSION;
    }

    /**
     * Check if any configured paths differ from defaults.
     *
     * The updater syncs bundled plugins to app/plugins/. If users have
     * customized paths, the auto-updater cannot safely proceed because
     * files would be written to the wrong locations.
     *
     * @return array{safe: bool, custom_paths: array<string, array{configured: string, default: string}>}
     */
    public function checkPathSafety(): array
    {
        $customPaths = [];

        foreach ($this->defaultPaths as $key => $default) {
            $configured = $this->app->config("paths.{$key}", $default);
            if ($configured !== $default) {
                $customPaths[$key] = [
                    'configured' => $configured,
                    'default' => $default,
                ];
            }
        }

        return [
            'safe' => empty($customPaths),
            'custom_paths' => $customPaths,
        ];
    }

    /**
     * Check for available updates.
     *
     * @param bool $force Force fresh check (bypass cache)
     * @return array{available: bool, current: string, latest: string, release: ?array, error: ?string, from_cache?: bool, checked_at?: int}
     */
    public function check(bool $force = false): array
    {
        $current = $this->currentVersion();

        // Check cache first (valid for 1 hour)
        if (!$force && file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if ($cached && ($cached['checked_at'] ?? 0) > time() - 3600) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        try {
            $release = $this->fetchLatestRelease();

            if ($release === null) {
                return [
                    'available' => false,
                    'current' => $current,
                    'latest' => $current,
                    'release' => null,
                    'error' => 'Could not fetch release info from GitHub',
                ];
            }

            $latest = ltrim($release['tag_name'], 'v');
            $available = version_compare($latest, $current, '>');

            $result = [
                'available' => $available,
                'current' => $current,
                'latest' => $latest,
                'release' => [
                    'name' => $release['name'] ?? $latest,
                    'body' => $release['body'] ?? '',
                    'published_at' => $release['published_at'] ?? null,
                    'html_url' => $release['html_url'] ?? null,
                    'zipball_url' => $release['zipball_url'] ?? null,
                ],
                'error' => null,
                'checked_at' => time(),
            ];

            // Cache the result with exclusive lock for concurrent safety
            $cacheDir = dirname($this->cacheFile);
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            @file_put_contents($this->cacheFile, json_encode($result, JSON_PRETTY_PRINT), LOCK_EX);

            return $result;

        } catch (\Exception $e) {
            return [
                'available' => false,
                'current' => $current,
                'latest' => $current,
                'release' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Apply an update.
     *
     * @param string|null $version Specific version to update to (null = latest)
     * @param bool $dev If true, update from the latest commit on main branch instead of a release
     * @return array{success: bool, message: string, updated_from: string, updated_to: string, new_plugins: string[]}
     */
    public function apply(?string $version = null, bool $dev = false): array
    {
        $currentVersion = $this->currentVersion();
        $workspace = null;
        $updateLock = null;

        // Check path safety before proceeding - block entirely if custom paths
        $pathCheck = $this->checkPathSafety();
        if (!$pathCheck['safe']) {
            return [
                'success' => false,
                'message' => 'Auto-update blocked due to custom paths. Please update manually from GitHub.',
                'updated_from' => $currentVersion,
                'updated_to' => $currentVersion,
                'new_plugins' => [],
            ];
        }

        try {
            $updateLock = $this->acquireUpdateLock();

            // Dev mode: get latest commit from main branch
            if ($dev) {
                $commit = $this->fetchLatestCommit();
                if ($commit === null) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch latest commit from GitHub',
                        'updated_from' => $currentVersion,
                        'updated_to' => $currentVersion,
                        'new_plugins' => [],
                    ];
                }
                $zipUrl = $this->getCommitZipUrl($commit);
                $newVersion = $currentVersion . '-dev.' . substr($commit['sha'], 0, 7);
            } else {
                // Get release info
                if ($version === null) {
                    $release = $this->fetchLatestRelease();
                } else {
                    $release = $this->fetchRelease($version);
                }

                if ($release === null) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch release from GitHub',
                        'updated_from' => $currentVersion,
                        'updated_to' => $currentVersion,
                        'new_plugins' => [],
                    ];
                }

                $newVersion = ltrim($release['tag_name'], 'v');
                $zipUrl = $release['zipball_url'] ?? null;
            }

            if (!$zipUrl) {
                return [
                    'success' => false,
                    'message' => 'No download URL available for this release',
                    'updated_from' => $currentVersion,
                    'updated_to' => $currentVersion,
                    'new_plugins' => [],
                ];
            }

            // Use an unpredictable, per-run workspace so concurrent updates
            // cannot collide with or reuse another run's partial files.
            $workspace = $this->createTemporaryWorkspace('update');
            $zipFile = $workspace . '/download.zip';
            $extractDir = $workspace . '/extract';

            // Download
            $this->download($zipUrl, $zipFile);

            // Extract
            $this->extract($zipFile, $extractDir);

            // Find the extracted directory (GitHub adds a prefix)
            $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            if (empty($dirs)) {
                throw new \RuntimeException('Could not find extracted files');
            }
            $sourceDir = $dirs[0];

            // Get current active plugins before update
            $currentPlugins = $this->app->config('plugins', []);

            // Apply updates (clean sync - deletes old directories first)
            $this->applyUpdates($sourceDir);

            // Check for new bundled plugins
            $newPlugins = $this->detectNewPlugins($sourceDir, $currentPlugins);

            // Clear update cache
            @unlink($this->cacheFile);

            return [
                'success' => true,
                'message' => "Updated from {$currentVersion} to {$newVersion}",
                'updated_from' => $currentVersion,
                'updated_to' => $newVersion,
                'new_plugins' => $newPlugins,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
                'updated_from' => $currentVersion,
                'updated_to' => $currentVersion,
                'new_plugins' => [],
            ];
        } finally {
            $this->cleanupTemporaryWorkspace($workspace);
            if (is_resource($updateLock)) {
                flock($updateLock, LOCK_UN);
                fclose($updateLock);
            }
        }
    }

    /**
     * Detect stale files left from older releases.
     *
     * @param string|null $version Specific version to compare against (null = latest)
     * @param bool $dev If true, compare against latest commit on main branch
     * @return array{success: bool, message: string, compared_to: string, stale_files: string[]}
     */
    public function detectStaleFiles(?string $version = null, bool $dev = false): array
    {
        $pathCheck = $this->checkPathSafety();
        if (!$pathCheck['safe']) {
            return [
                'success' => false,
                'message' => 'Stale file scan blocked due to custom paths. Please scan manually.',
                'compared_to' => $this->currentVersion(),
                'stale_files' => [],
            ];
        }

        $workspace = null;

        try {
            if ($dev) {
                $commit = $this->fetchLatestCommit();
                if ($commit === null) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch latest commit from GitHub',
                        'compared_to' => 'main (latest commit)',
                        'stale_files' => [],
                    ];
                }
                $compareLabel = 'main (latest commit)';
                $zipUrl = $this->getCommitZipUrl($commit);
            } else {
                if ($version === null) {
                    $release = $this->fetchLatestRelease();
                } else {
                    $release = $this->fetchRelease($version);
                }

                if ($release === null) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch release from GitHub',
                        'compared_to' => $this->currentVersion(),
                        'stale_files' => [],
                    ];
                }

                $newVersion = ltrim($release['tag_name'], 'v');
                $compareLabel = $newVersion;
                $zipUrl = $release['zipball_url'] ?? null;
            }

            if (!$zipUrl) {
                return [
                    'success' => false,
                    'message' => 'No download URL available for this release',
                    'compared_to' => $this->currentVersion(),
                    'stale_files' => [],
                ];
            }

            $workspace = $this->createTemporaryWorkspace('stale-scan');
            $zipFile = $workspace . '/download.zip';
            $extractDir = $workspace . '/extract';

            $this->download($zipUrl, $zipFile);
            $this->extract($zipFile, $extractDir);

            $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            if (empty($dirs)) {
                throw new \RuntimeException('Could not find extracted files');
            }
            $sourceDir = $dirs[0];

            $rootDir = $this->app->path('');
            $expected = [];
            $actual = [];

            foreach ($this->updateDirs as $path) {
                $expected = array_merge($expected, $this->collectFiles($sourceDir . '/' . $path, $path));
                $actual = array_merge($actual, $this->collectFiles($rootDir . '/' . $path, $path));
            }

            $pluginsSource = $sourceDir . '/app/plugins';
            if (!is_dir($pluginsSource)) {
                $pluginsSource = $sourceDir . '/plugins';
            }

            $releasePlugins = [];
            if (is_dir($pluginsSource)) {
                $releaseBundled = glob($pluginsSource . '/*', GLOB_ONLYDIR) ?: [];
                foreach ($releaseBundled as $pluginDir) {
                    $releasePlugins[] = basename($pluginDir);
                }
            }

            if (empty($releasePlugins)) {
                $releasePlugins = $this->bundledPlugins;
            }

            foreach ($releasePlugins as $plugin) {
                $expected = array_merge($expected, $this->collectFiles($pluginsSource . '/' . $plugin, 'app/plugins/' . $plugin));
            }

            $pluginsToCheck = array_unique(array_merge($releasePlugins, $this->bundledPlugins));
            foreach ($pluginsToCheck as $plugin) {
                $actual = array_merge($actual, $this->collectFiles($rootDir . '/app/plugins/' . $plugin, 'app/plugins/' . $plugin));
            }

            $expected = array_values(array_unique($expected));
            $actual = array_values(array_unique($actual));

            $stale = array_values(array_diff($actual, $expected));
            sort($stale, SORT_STRING);

            return [
                'success' => true,
                'message' => 'Stale file scan completed',
                'compared_to' => $compareLabel,
                'stale_files' => $stale,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Stale file scan failed: ' . $e->getMessage(),
                'compared_to' => $this->currentVersion(),
                'stale_files' => [],
            ];
        } finally {
            $this->cleanupTemporaryWorkspace($workspace);
        }
    }

    /**
     * Prevent concurrent update processes from changing the installation.
     *
     * @return resource
     */
    private function acquireUpdateLock(?string $lockFile = null)
    {
        $lockFile ??= dirname($this->cacheFile) . '/update_install.lock';
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0755, true)) {
            throw new \RuntimeException('Failed to create updater lock directory');
        }

        $lock = @fopen($lockFile, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Failed to open updater lock file');
        }
        @chmod($lockFile, 0600);

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new \RuntimeException('Another update is already in progress');
        }

        return $lock;
    }

    /**
     * Create a private workspace under the configured temporary directory.
     */
    private function createTemporaryWorkspace(string $purpose): string
    {
        $tempRoot = $this->app->configPath('storage') . '/tmp';
        if (!is_dir($tempRoot) && !mkdir($tempRoot, 0755, true) && !is_dir($tempRoot)) {
            throw new \RuntimeException('Failed to create updater temporary directory');
        }

        $realRoot = realpath($tempRoot);
        if ($realRoot === false) {
            throw new \RuntimeException('Updater temporary directory could not be resolved');
        }

        $safePurpose = preg_replace('/[^a-z0-9-]/i', '-', $purpose) ?: 'work';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $workspace = $realRoot . DIRECTORY_SEPARATOR . 'ava-' . $safePurpose . '-'
                . bin2hex(random_bytes(12));
            if (@mkdir($workspace, 0700)) {
                return $workspace;
            }
        }

        throw new \RuntimeException('Failed to create a unique updater workspace');
    }

    private function cleanupTemporaryWorkspace(?string $workspace): void
    {
        if ($workspace === null || !is_dir($workspace)) {
            return;
        }

        try {
            $this->removeDirectory($workspace);
        } catch (\Throwable $e) {
            error_log('Failed to clean updater workspace: ' . $e->getMessage());
        }
    }

    /**
     * Fetch latest release from GitHub API.
     */
    private function fetchLatestRelease(): ?array
    {
        $url = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
        return $this->githubApiRequest($url);
    }

    /**
     * Fetch a specific release by tag.
     */
    private function fetchRelease(string $version): ?array
    {
        $tag = str_starts_with($version, 'v') ? $version : 'v' . $version;
        $url = "https://api.github.com/repos/{$this->githubRepo}/releases/tags/{$tag}";
        return $this->githubApiRequest($url);
    }

    /**
     * Fetch the latest commit info from the main branch.
     */
    private function fetchLatestCommit(string $branch = 'main'): ?array
    {
        $url = "https://api.github.com/repos/{$this->githubRepo}/commits/{$branch}";
        return $this->githubApiRequest($url);
    }

    /**
     * Get the zipball URL for the exact commit that was reported.
     *
     * Downloading the branch name instead could fetch a newer commit than the
     * one whose SHA the update is labelled with.
     */
    private function getCommitZipUrl(array $commit): string
    {
        $sha = $commit['sha'] ?? null;
        if (!is_string($sha) || preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            throw new \RuntimeException('GitHub returned an invalid commit SHA');
        }

        return "https://api.github.com/repos/{$this->githubRepo}/zipball/{$sha}";
    }

    /**
     * Make a request to GitHub API.
     */
    private function githubApiRequest(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Ava-CMS-Updater/' . AVA_VERSION,
                    'Accept: application/vnd.github.v3+json',
                ],
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last()['message'] ?? 'Unknown error';
            throw new \RuntimeException('GitHub API request failed: ' . $error);
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Download a file.
     */
    private function download(string $url, string $destination): void
    {
        $this->validateDownloadUrl($url);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Ava-CMS-Updater/' . AVA_VERSION,
                    'Accept: application/vnd.github.v3+json',
                ],
                'timeout' => 120,
                'follow_location' => 1,
                'max_redirects' => 5,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $source = @fopen($url, 'rb', false, $context);
        if ($source === false) {
            $error = error_get_last()['message'] ?? 'Unknown error';
            throw new \RuntimeException('Failed to download update file: ' . $error);
        }

        $metadata = stream_get_meta_data($source);
        if (isset($metadata['uri']) && is_string($metadata['uri'])) {
            $this->validateDownloadUrl($metadata['uri']);
        }

        $target = @fopen($destination, 'xb');
        if ($target === false) {
            fclose($source);
            throw new \RuntimeException('Failed to create update download file');
        }

        $complete = false;
        try {
            $written = stream_copy_to_stream($source, $target, self::MAX_DOWNLOAD_BYTES + 1);
            if ($written === false) {
                throw new \RuntimeException('Failed while downloading update file');
            }
            if ($written > self::MAX_DOWNLOAD_BYTES) {
                throw new \RuntimeException('Update archive exceeds the 64 MiB download limit');
            }
            if (!fflush($target)) {
                throw new \RuntimeException('Failed to flush update download file');
            }
            $complete = true;
        } finally {
            fclose($source);
            fclose($target);
            if (!$complete && is_file($destination)) {
                @unlink($destination);
            }
        }
    }

    private function validateDownloadUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $path = $parts['path'] ?? '';
        $expectedPath = match ($host) {
            'api.github.com' => '/repos/' . $this->githubRepo . '/',
            'codeload.github.com', 'github.com' => '/' . $this->githubRepo . '/',
            default => '',
        };

        if (
            $scheme !== 'https'
            || !in_array($host, self::ALLOWED_DOWNLOAD_HOSTS, true)
            || $expectedPath === ''
            || !str_starts_with($path, $expectedPath)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException('Update download URL is not an approved HTTPS GitHub URL');
        }
    }

    /**
     * Extract a zip file.
     * 
     * Includes ZIP slip protection to prevent path traversal attacks.
     */
    private function extract(string $zipFile, string $destination): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive extension is required for updates');
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== true) {
            throw new \RuntimeException('Failed to open update zip file');
        }

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new \RuntimeException('Update archive has an invalid number of entries');
            }

            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $zip->getNameIndex($i);
                if (!is_array($stat) || $entryName === false) {
                    throw new \RuntimeException('Update archive contains an unreadable entry');
                }

                $this->validateArchiveEntryName($entryName);

                $size = $stat['size'] ?? null;
                $compressedSize = $stat['comp_size'] ?? null;
                if (!is_int($size) || !is_int($compressedSize) || $size < 0 || $compressedSize < 0) {
                    throw new \RuntimeException('Update archive contains invalid entry sizes');
                }
                if ($size > self::MAX_EXTRACTED_BYTES - $totalSize) {
                    throw new \RuntimeException('Update archive exceeds the 256 MiB extraction limit');
                }
                $totalSize += $size;

                if (
                    $size > 1024 * 1024
                    && $size / max(1, $compressedSize) > self::MAX_COMPRESSION_RATIO
                ) {
                    throw new \RuntimeException('Update archive contains a suspicious compression ratio');
                }

                if (($stat['encryption_method'] ?? 0) !== 0) {
                    throw new \RuntimeException('Encrypted update archives are not supported');
                }

                $operatingSystem = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $operatingSystem, $attributes)) {
                    $fileType = ($attributes >> 16) & 0170000;
                    if (!in_array($fileType, [0, 0040000, 0100000], true)) {
                        throw new \RuntimeException('Update archive contains a special file');
                    }
                }
            }

            if (file_exists($destination)) {
                throw new \RuntimeException('Update extraction directory already exists');
            }
            if (!@mkdir($destination, 0700, true)) {
                throw new \RuntimeException('Failed to create extraction directory');
            }

            if (!$zip->extractTo($destination)) {
                throw new \RuntimeException('Failed to extract update files');
            }
        } finally {
            $zip->close();
        }

        // Post-extraction verification: ensure no files escaped
        $realDestination = realpath($destination);
        if ($realDestination === false) {
            throw new \RuntimeException('Extraction directory not found after extraction');
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destination, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                $this->removeDirectory($destination);
                throw new \RuntimeException('Update archive extracted a symbolic link');
            }
            $realPath = realpath($file->getPathname());
            if (
                $realPath === false
                || (
                    $realPath !== $realDestination
                    && !str_starts_with($realPath, $realDestination . DIRECTORY_SEPARATOR)
                )
            ) {
                // A file escaped the destination directory - this shouldn't happen
                // but let's be paranoid
                $this->removeDirectory($destination);
                throw new \RuntimeException(
                    'ZIP slip attack detected: file extracted outside destination'
                );
            }
        }
    }

    private function validateArchiveEntryName(string $entryName): void
    {
        $normalized = str_replace('\\', '/', $entryName);
        if (
            $normalized === ''
            || strlen($normalized) > 4096
            || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/^[a-z]:/i', $normalized)
            || str_contains($normalized, ':')
        ) {
            throw new \RuntimeException('Update archive contains an unsafe path');
        }

        $segments = explode('/', rtrim($normalized, '/'));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException('Update archive contains a traversal path');
            }
        }
    }

    /**
     * Apply updates from extracted source.
     */
    private function applyUpdates(string $sourceDir, ?string $rootDir = null): void
    {
        $rootDir ??= $this->app->path('');
        $targets = $this->collectUpdateTargets($sourceDir, $rootDir);
        $transactionDir = $rootDir . '/.ava-update-' . bin2hex(random_bytes(12));
        $stagingDir = $transactionDir . '/staged';
        $backupDir = $transactionDir . '/backup';
        $activated = [];
        $backedUp = [];
        $preserveTransaction = false;

        if (!@mkdir($transactionDir, 0700)) {
            throw new \RuntimeException('Failed to create update transaction directory');
        }

        try {
            if (!@mkdir($stagingDir, 0700) || !@mkdir($backupDir, 0700)) {
                throw new \RuntimeException('Failed to prepare update transaction directory');
            }

            // Copy every replacement before changing live files.
            foreach ($targets as $path => $sourcePath) {
                $stagedPath = $stagingDir . '/' . $path;
                if (is_dir($sourcePath)) {
                    $this->syncDirectory($sourcePath, $stagedPath);
                } else {
                    $this->syncFile($sourcePath, $stagedPath);
                    $destination = $rootDir . '/' . $path;
                    if (is_file($destination)) {
                        @chmod($stagedPath, fileperms($destination) & 0777);
                    }
                }
            }

            foreach (array_keys($targets) as $path) {
                $stagedPath = $stagingDir . '/' . $path;
                $destination = $rootDir . '/' . $path;
                $backup = $backupDir . '/' . $path;

                if (file_exists($destination) || is_link($destination)) {
                    if (!is_dir(dirname($backup)) && !@mkdir(dirname($backup), 0700, true)) {
                        throw new \RuntimeException('Failed to create update backup directory: ' . dirname($backup));
                    }
                    if (!@rename($destination, $backup)) {
                        throw new \RuntimeException("Failed to back up update target: {$path}");
                    }
                    $backedUp[] = $path;
                }

                if (!is_dir(dirname($destination)) && !@mkdir(dirname($destination), 0755, true)) {
                    throw new \RuntimeException('Failed to create update destination: ' . dirname($destination));
                }
                if (!@rename($stagedPath, $destination)) {
                    throw new \RuntimeException("Failed to activate update target: {$path}");
                }
                $activated[] = $path;
            }
        } catch (\Throwable $updateError) {
            $rollbackErrors = $this->restoreUpdateTargets(
                $rootDir,
                $backupDir,
                $activated,
                $backedUp
            );

            $message = $updateError->getMessage();
            if ($rollbackErrors !== []) {
                $preserveTransaction = true;
                $message .= '; rollback errors: ' . implode('; ', $rollbackErrors)
                    . "; recovery files preserved at {$transactionDir}";
            }
            throw new \RuntimeException($message, 0, $updateError);
        } finally {
            if (!$preserveTransaction && is_dir($transactionDir)) {
                try {
                    $this->removeDirectory($transactionDir);
                } catch (\Throwable $e) {
                    error_log('Failed to clean update transaction: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Remove activated replacements and restore their original paths.
     *
     * @param string[] $activated
     * @param string[] $backedUp
     * @return string[] Rollback errors
     */
    private function restoreUpdateTargets(
        string $rootDir,
        string $backupDir,
        array $activated,
        array $backedUp
    ): array {
        $errors = [];

        foreach (array_reverse($activated) as $path) {
            try {
                $this->removePath($rootDir . '/' . $path);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        foreach (array_reverse($backedUp) as $path) {
            $backup = $backupDir . '/' . $path;
            $destination = $rootDir . '/' . $path;
            if ((file_exists($backup) || is_link($backup)) && !@rename($backup, $destination)) {
                $errors[] = "Failed to restore update target: {$path}";
            }
        }

        return $errors;
    }

    /**
     * Build the complete set of release files that may replace live files.
     * Missing required files abort before any live path is changed.
     *
     * @return array<string, string> Relative destination => source path
     */
    private function collectUpdateTargets(string $sourceDir, string $rootDir): array
    {
        $targets = [];
        foreach ($this->updateDirs as $path) {
            $sourcePath = $sourceDir . '/' . $path;
            if (!file_exists($sourcePath)) {
                throw new \RuntimeException("Update package is missing required path: {$path}");
            }
            $expectsDirectory = $path === 'core';
            if ($expectsDirectory !== is_dir($sourcePath)) {
                throw new \RuntimeException("Update package has invalid path type: {$path}");
            }
            $targets[$path] = $sourcePath;
        }

        $pluginsSource = $sourceDir . '/app/plugins';
        if (!is_dir($pluginsSource)) {
            $pluginsSource = $sourceDir . '/plugins';
        }
        if ($this->bundledPlugins !== [] && !is_dir($pluginsSource)) {
            throw new \RuntimeException('Update package is missing bundled plugins');
        }

        foreach ($this->bundledPlugins as $plugin) {
            $pluginSource = $pluginsSource . '/' . $plugin;
            if (!is_dir($pluginSource)) {
                throw new \RuntimeException("Update package is missing bundled plugin: {$plugin}");
            }
            $targets['app/plugins/' . $plugin] = $pluginSource;
        }

        foreach (glob($pluginsSource . '/*', GLOB_ONLYDIR) ?: [] as $pluginDir) {
            $pluginName = basename($pluginDir);
            $destination = $rootDir . '/app/plugins/' . $pluginName;
            if (!in_array($pluginName, $this->bundledPlugins, true) && !is_dir($destination)) {
                $targets['app/plugins/' . $pluginName] = $pluginDir;
            }
        }

        return $targets;
    }

    /**
     * Detect new bundled plugins that weren't in the config.
     *
     * @return string[] Names of new plugins
     */
    private function detectNewPlugins(string $sourceDir, array $currentActivePlugins): array
    {
        $newPlugins = [];
        $pluginsDir = $sourceDir . '/app/plugins';

        // Fall back to old location for releases that still use plugins/
        if (!is_dir($pluginsDir)) {
            $pluginsDir = $sourceDir . '/plugins';
        }

        if (!is_dir($pluginsDir)) {
            return [];
        }

        $releaseBundled = glob($pluginsDir . '/*', GLOB_ONLYDIR);
        foreach ($releaseBundled as $pluginDir) {
            $pluginName = basename($pluginDir);
            if (!in_array($pluginName, $currentActivePlugins)) {
                $newPlugins[] = $pluginName;
            }
        }

        return $newPlugins;
    }

    /**
     * Sync a directory (copy files, preserving structure).
     */
    private function syncDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $destPath = $dest . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    if (!@mkdir($destPath, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$destPath}");
                    }
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    if (!@mkdir($destDir, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$destDir}");
                    }
                }
                if (!@copy($item->getPathname(), $destPath)) {
                    throw new \RuntimeException("Failed to copy file: {$item->getPathname()} -> {$destPath}");
                }
            }
        }
    }

    /**
     * Sync a single file.
     */
    private function syncFile(string $source, string $dest): void
    {
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$destDir}");
            }
        }
        if (!@copy($source, $dest)) {
            throw new \RuntimeException("Failed to copy file: {$source} -> {$dest}");
        }
    }

    /**
     * Collect all files under a path and return normalized, prefixed paths.
     *
     * @return string[]
     */
    private function collectFiles(string $path, string $prefix): array
    {
        if (!file_exists($path)) {
            return [];
        }

        if (is_file($path)) {
            return [$this->normalizePath($prefix)];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($path) + 1);
            $files[] = $this->normalizePath($prefix . '/' . $relative);
        }

        return $files;
    }

    /**
     * Normalize paths to use forward slashes.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Remove stale files from the installation.
     *
     * @param string[] $files Array of relative file paths to remove
     * @return array{success: bool, removed: string[], failed: string[], message: string}
     */
    public function removeStaleFiles(array $files): array
    {
        $rootDir = $this->app->path('');
        $removed = [];
        $failed = [];

        foreach ($files as $file) {
            $fullPath = $rootDir . '/' . $file;
            
            // Security: ensure file is within root and in updateable directories
            $realPath = realpath($fullPath);
            if ($realPath === false) {
                // File doesn't exist, skip
                continue;
            }
            
            $realRoot = realpath($rootDir);
            if (!str_starts_with($realPath, $realRoot . '/')) {
                $failed[] = $file . ' (outside root)';
                continue;
            }

            // Only allow removal from updateable paths
            $allowed = false;
            foreach ($this->updateDirs as $dir) {
                if (str_starts_with($file, rtrim($dir, '/') . '/') || $file === $dir) {
                    $allowed = true;
                    break;
                }
            }
            // Also allow bundled plugin files
            foreach ($this->bundledPlugins as $plugin) {
                if (str_starts_with($file, 'app/plugins/' . $plugin . '/')) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                $failed[] = $file . ' (protected path)';
                continue;
            }

            if (@unlink($realPath)) {
                $removed[] = $file;
                
                // Remove empty parent directories up to updateDirs root
                $dir = dirname($realPath);
                while ($dir !== $realRoot && is_dir($dir) && count(scandir($dir)) === 2) {
                    @rmdir($dir);
                    $dir = dirname($dir);
                }
            } else {
                $failed[] = $file;
            }
        }

        $total = count($removed) + count($failed);
        return [
            'success' => empty($failed),
            'removed' => $removed,
            'failed' => $failed,
            'message' => empty($failed)
                ? "Removed {$total} stale file(s)"
                : "Removed " . count($removed) . " file(s), " . count($failed) . " failed",
        ];
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * Remove either a file, link, or directory without following links.
     */
    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new \RuntimeException("Failed to remove update target: {$path}");
            }
            return;
        }

        if (is_dir($path)) {
            $this->removeDirectory($path);
        }
    }
}
