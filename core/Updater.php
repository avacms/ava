<?php

declare(strict_types=1);

namespace Ava;

/**
 * Ava Updater
 *
 * Handles checking for and applying updates from GitHub.
 *
 * Version Format: CalVer YY.0M.MICRO (e.g., 25.12.1)
 * - YY: Two-digit year
 * - 0M: Zero-padded month
 * - MICRO: Release number within that month (starts at 1)
 * 
 * Examples: 25.12.1, 25.12.2, 26.01.1
 *
 * Update Process:
 * 1. Check GitHub API for latest release
 * 2. Download release zip to temp
 * 3. Extract and apply updates (core/, docs/, themes/default/, bundled plugins)
 * 4. Preserve: content/, app/, storage/, custom themes, custom plugins
 * 5. New bundled plugins are added but NOT activated
 *
 * @package Ava
 */
final class Updater
{
    private Application $app;
    private string $githubRepo = 'addycodes/ava';
    private string $cacheFile;

    /** @var string[] Directories that should be updated */
    private array $updateDirs = [
        'core',
        'docs',
        'bin',
        'public/index.php',
        'public/robots.txt',
        'bootstrap.php',
        'composer.json',
    ];

    /** @var string[] Bundled plugins (shipped with Ava) */
    private array $bundledPlugins = [
        'sitemap',
        'feed',
        'redirects',
    ];

    /** @var string[] Directories/files that should NEVER be touched */
    private array $preserveDirs = [
        'content',
        'app',
        'storage',
        'vendor',
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
     * Check for available updates.
     *
     * @param bool $force Force fresh check (bypass cache)
     * @return array{available: bool, current: string, latest: string, release: ?array, error: ?string}
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

            // Cache the result
            $cacheDir = dirname($this->cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($this->cacheFile, json_encode($result, JSON_PRETTY_PRINT));

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
     * @return array{success: bool, message: string, updated_from: string, updated_to: string, new_plugins: string[]}
     */
    public function apply(?string $version = null): array
    {
        $currentVersion = $this->currentVersion();

        try {
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
            return null;
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
                    'Accept: application/octet-stream',
                ],
                'timeout' => 120,
                'follow_location' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \RuntimeException('Failed to download update file');
        }

        if (file_put_contents($destination, $content) === false) {
            throw new \RuntimeException('Failed to save update file');
        }
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

        // Update default theme (but preserve custom themes)
        $defaultThemeSource = $sourceDir . '/themes/default';
        if (is_dir($defaultThemeSource)) {
            $this->syncDirectory($defaultThemeSource, $rootDir . '/themes/default');
        }

        // Update bundled plugins (but don't activate new ones)
        $pluginsSource = $sourceDir . '/plugins';
        if (is_dir($pluginsSource)) {
            foreach ($this->bundledPlugins as $plugin) {
                $pluginSource = $pluginsSource . '/' . $plugin;
                if (is_dir($pluginSource)) {
                    $this->syncDirectory($pluginSource, $rootDir . '/plugins/' . $plugin);
                }
            }

            // Also copy any NEW bundled plugins from the release
            $releaseBundled = glob($pluginsSource . '/*', GLOB_ONLYDIR);
            foreach ($releaseBundled as $pluginDir) {
                $pluginName = basename($pluginDir);
                $destDir = $rootDir . '/plugins/' . $pluginName;

                // Only sync bundled plugins, don't overwrite custom plugins
                if (!in_array($pluginName, $this->bundledPlugins) && !is_dir($destDir)) {
                    // This is a new bundled plugin - copy it
                    $this->syncDirectory($pluginDir, $destDir);
                    $this->bundledPlugins[] = $pluginName;
                }
            }
        }

        // Update public assets (but not user assets)
        $publicAssetsSource = $sourceDir . '/public/assets/admin.css';
        if (file_exists($publicAssetsSource)) {
            $this->syncFile($publicAssetsSource, $rootDir . '/public/assets/admin.css');
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
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
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
            mkdir($destDir, 0755, true);
        }
        copy($source, $dest);
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
