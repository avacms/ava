<?php

declare(strict_types=1);

namespace Ava;

/**
 * Ava Updater
 *
 * Handles checking for and applying updates from GitHub.
 *
 * Version Format: SemVer MAJOR.MINOR.PATCH (e.g., 1.0.0)
 *
 * What gets updated:
 * - core/, docs/, ava (CLI), bootstrap.php, composer.json
 * - public/index.php
 * - Bundled plugins in app/plugins/ (sitemap, feed, redirects)
 *
 * What is preserved (never touched):
 * - content/, app/config/, app/themes/, app/snippets/, storage/, vendor/
 * - Custom plugins, public/robots.txt, .git, .env
 *
 * Updates sync individual files, not entire directories.
 * This may leave stale files from old versions. Use update:stale to detect them.
 */
final class Updater
{
    private Application $app;
    private string $githubRepo = 'avacms/ava';
    private string $cacheFile;

    /** @var string[] Directories/files that should be updated */
    private array $updateDirs = [
        'core',
        'docs',
        'ava',
        'public/index.php',
        'bootstrap.php',
        'composer.json',
    ];

    /** @var string[] Bundled plugins (shipped with Ava) */
    private array $bundledPlugins = [
        'sitemap',
        'feed',
        'redirects',
    ];

    /** @var string[] Default paths that the updater expects */
    private array $defaultPaths = [
        'themes'   => 'app/themes',
        'plugins'  => 'app/plugins',
        'snippets' => 'app/snippets',
    ];

    /**
     * Directories/files that should NEVER be touched.
     * Reserved for future use in update safety checks.
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
                $shortSha = substr($commit['sha'], 0, 7);
                $newVersion = $currentVersion . '-dev.' . $shortSha;
                $zipUrl = $this->getBranchZipUrl();
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

            // Download zip
            $tempDir = $this->app->path('storage/tmp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFile = $tempDir . '/ava-update-' . $newVersion . '.zip';
            $extractDir = $tempDir . '/ava-update-' . $newVersion;

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

            // Apply updates
            $this->applyUpdates($sourceDir);

            // Check for new bundled plugins
            $newPlugins = $this->detectNewPlugins($sourceDir, $currentPlugins);

            // Clean up
            @unlink($zipFile);
            $this->removeDirectory($extractDir);

            // Clear update cache
            @unlink($this->cacheFile);

            return [
                'success' => true,
                'message' => "Updated from {$currentVersion} to {$newVersion}",
                'updated_from' => $currentVersion,
                'updated_to' => $newVersion,
                'new_plugins' => $newPlugins,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
                'updated_from' => $currentVersion,
                'updated_to' => $currentVersion,
                'new_plugins' => [],
            ];
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
     * Get zipball URL for a branch.
     */
    private function getBranchZipUrl(string $branch = 'main'): string
    {
        return "https://api.github.com/repos/{$this->githubRepo}/zipball/{$branch}";
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
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Ava-CMS-Updater/' . AVA_VERSION,
                    'Accept: application/vnd.github.v3+json',
                ],
                'timeout' => 120,
                'follow_location' => 1,
            ],
        ]);

        if (@copy($url, $destination, $context)) {
            return;
        }

        $error = error_get_last()['message'] ?? 'Unknown error';
        throw new \RuntimeException('Failed to download update file: ' . $error);
    }

    /**
     * Extract a zip file.
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

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract update files');
        }

        $zip->close();
    }

    /**
     * Apply updates from extracted source.
     */
    private function applyUpdates(string $sourceDir): void
    {
        $rootDir = $this->app->path('');

        // Update core directories/files
        foreach ($this->updateDirs as $path) {
            $sourcePath = $sourceDir . '/' . $path;
            $destPath = $rootDir . '/' . $path;

            if (!file_exists($sourcePath)) {
                continue;
            }

            if (is_dir($sourcePath)) {
                $this->syncDirectory($sourcePath, $destPath);
            } else {
                $this->syncFile($sourcePath, $destPath);
            }
        }

        // Update bundled plugins to app/plugins/
        $pluginsSource = $sourceDir . '/app/plugins';
        // Fall back to old location for releases that still use plugins/
        if (!is_dir($pluginsSource)) {
            $pluginsSource = $sourceDir . '/plugins';
        }
        $pluginsDest = $rootDir . '/app/plugins';

        if (is_dir($pluginsSource)) {
            foreach ($this->bundledPlugins as $plugin) {
                $pluginSource = $pluginsSource . '/' . $plugin;
                if (is_dir($pluginSource)) {
                    $this->syncDirectory($pluginSource, $pluginsDest . '/' . $plugin);
                }
            }

            // Also copy any NEW bundled plugins from the release
            $releaseBundled = glob($pluginsSource . '/*', GLOB_ONLYDIR);
            foreach ($releaseBundled as $pluginDir) {
                $pluginName = basename($pluginDir);
                $destDir = $pluginsDest . '/' . $pluginName;

                // Only sync bundled plugins, don't overwrite custom plugins
                if (!in_array($pluginName, $this->bundledPlugins) && !is_dir($destDir)) {
                    // This is a new bundled plugin - copy it
                    $this->syncDirectory($pluginDir, $destDir);
                    $this->bundledPlugins[] = $pluginName;
                }
            }
        }

    }

    /**
     * Detect new bundled plugins that weren't in the config.
     *
     * @return string[] Names of new plugins
     */
    private function detectNewPlugins(string $sourceDir, array $currentActivePlugins): array
    {
        $newPlugins = [];
        $pluginsDir = $sourceDir . '/plugins';

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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
