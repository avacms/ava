<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;
use Ava\Support\Path;

/**
 * Content Indexer
 *
 * Scans content files and generates cache files:
 * - content_index.php - All content items indexed by type and slug
 * - tax_index.php - Taxonomy terms with counts
 * - routes.php - Compiled route map
 * - fingerprint.json - Change detection data
 */
final class Indexer
{
    private Application $app;
    private Parser $parser;

    /** @var array<string, string> Track IDs during indexing */
    private array $seenIds = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->parser = new Parser();
    }

    /**
     * Check if cache is fresh.
     */
    public function isCacheFresh(): bool
    {
        $fingerprintPath = $this->getCachePath('fingerprint.json');

        if (!file_exists($fingerprintPath)) {
            return false;
        }

        $stored = json_decode(file_get_contents($fingerprintPath), true);
        if (!$stored) {
            return false;
        }

        $current = $this->computeFingerprint();

        return $stored === $current;
    }

    /**
     * Rebuild all cache files.
     */
    public function rebuild(): void
    {
        // Reset seen IDs for duplicate detection
        $this->seenIds = [];

        $contentTypes = $this->loadContentTypes();
        $taxonomies = $this->loadTaxonomies();

        // Parse all content
        $allItems = [];
        $errors = [];

        foreach ($contentTypes as $typeName => $typeConfig) {
            $items = $this->scanContentType($typeName, $typeConfig, $errors);
            $allItems[$typeName] = $items;
        }

        // Build indexes
        $contentIndex = $this->buildContentIndex($allItems);
        $taxIndex = $this->buildTaxonomyIndex($allItems, $taxonomies);
        $routes = $this->buildRoutes($allItems, $contentTypes, $taxonomies);
        $recentCache = $this->buildRecentCache($allItems);
        $slugLookup = $this->buildSlugLookup($allItems);
        $fingerprint = $this->computeFingerprint();

        // Write cache files atomically (binary format for speed)
        $this->writeBinaryCacheFile('content_index.bin', $contentIndex);
        $this->writeBinaryCacheFile('tax_index.bin', $taxIndex);
        $this->writeBinaryCacheFile('routes.bin', $routes);
        $this->writeBinaryCacheFile('recent_cache.bin', $recentCache);
        $this->writeBinaryCacheFile('slug_lookup.bin', $slugLookup);
        $this->writeJsonCacheFile('fingerprint.json', $fingerprint);

        // Clear page cache when content cache is rebuilt
        $this->clearPageCache();

        // Log any errors
        if (!empty($errors)) {
            $this->logErrors($errors);
        }
    }

    /**
     * Clear the page cache.
     */
    private function clearPageCache(): void
    {
        $pageCachePath = $this->app->configPath('storage') . '/cache/pages';
        if (!is_dir($pageCachePath)) {
            return;
        }

        $files = glob($pageCachePath . '/*.html');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Get validation errors from last rebuild.
     */
    public function lint(): array
    {
        $contentTypes = $this->loadContentTypes();
        $errors = [];

        foreach ($contentTypes as $typeName => $typeConfig) {
            $this->scanContentType($typeName, $typeConfig, $errors);
        }

        return $errors;
    }

    /**
     * Scan a content type directory.
     *
     * @return array<Item>
     */
    private function scanContentType(string $typeName, array $typeConfig, array &$errors): array
    {
        $contentDir = $typeConfig['content_dir'] ?? $typeName;
        $basePath = $this->app->configPath('content') . '/' . $contentDir;

        if (!is_dir($basePath)) {
            return [];
        }

        $items = [];
        $slugs = []; // Track slugs for uniqueness

        $files = $this->findMarkdownFiles($basePath);

        foreach ($files as $filePath) {
            try {
                $item = $this->parser->parseFile($filePath, $typeName);

                // Validate item
                $itemErrors = $this->parser->validate($item);
                foreach ($itemErrors as $error) {
                    $errors[] = "{$filePath}: {$error}";
                }

                // Check slug uniqueness
                $slug = $item->slug();
                if (isset($slugs[$slug])) {
                    $errors[] = "{$filePath}: Duplicate slug '{$slug}' (also in {$slugs[$slug]})";
                } else {
                    $slugs[$slug] = $filePath;
                }

                // Check ID uniqueness (if IDs are used)
                $id = $item->id();
                if ($id !== null) {
                    if (isset($this->seenIds[$id])) {
                        $errors[] = "{$filePath}: Duplicate ID '{$id}' (also in {$this->seenIds[$id]})";
                    } else {
                        $this->seenIds[$id] = $filePath;
                    }
                }

                $items[] = $item;
            } catch (\Exception $e) {
                $errors[] = "{$filePath}: " . $e->getMessage();
            }
        }

        return $items;
    }

    /**
     * Find all Markdown files recursively.
     *
     * @return array<string>
     */
    private function findMarkdownFiles(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Build the content index.
     */
    private function buildContentIndex(array $allItems): array
    {
        $index = [
            'by_type' => [],
            'by_slug' => [],
            'by_id' => [],
            'by_path' => [],
        ];

        foreach ($allItems as $typeName => $items) {
            $index['by_type'][$typeName] = [];

            foreach ($items as $item) {
                $data = $item->toArray();

                // Index by type
                $index['by_type'][$typeName][$item->slug()] = $data;

                // Global slug index (type:slug)
                $key = $typeName . ':' . $item->slug();
                $index['by_slug'][$key] = $data;

                // Index by ID if present
                if ($item->id()) {
                    $index['by_id'][$item->id()] = $data;
                }

                // Index by relative file path
                $relativePath = $this->getRelativePath($item->filePath());
                $index['by_path'][$relativePath] = $data;
            }
        }

        return $index;
    }

    /**
     * Build the recent cache.
     * 
     * A lightweight index containing pre-sorted, minimal data for each content type.
     * This enables fast archive queries without loading the full content index.
     * 
     * Structure: [
     *     'post' => [
     *         'total' => 1000,
     *         'items' => [ {id, slug, title, date, status, excerpt, taxonomies}, ... ]
     *     ],
     *     ...
     * ]
     */
    private function buildRecentCache(array $allItems): array
    {
        $cache = [];
        $maxItems = 200; // Cache top 200 items per type (covers ~20 pages at 10/page)
        $contentTypes = $this->loadContentTypes();

        foreach ($allItems as $typeName => $items) {
            // Get cache_fields config for this type
            $cacheFields = $contentTypes[$typeName]['cache_fields'] ?? [];
            
            // Filter to published only and collect minimal data
            $published = [];
            foreach ($items as $item) {
                if (!$item->isPublished()) {
                    continue;
                }
                
                // Extract taxonomy terms and custom cache fields from frontmatter
                $taxonomies = [];
                $extraFields = [];
                $frontmatter = $item->toArray()['frontmatter'] ?? [];
                
                foreach ($frontmatter as $key => $value) {
                    // Skip core fields
                    if (in_array($key, ['id', 'title', 'slug', 'status', 'date', 'excerpt', 'template', 'updated'], true)) {
                        continue;
                    }
                    // Capture taxonomy arrays
                    if (is_array($value)) {
                        $taxonomies[$key] = $value;
                    }
                    // Capture configured cache fields
                    if (in_array($key, $cacheFields, true)) {
                        $extraFields[$key] = $value;
                    }
                }
                
                $itemData = [
                    'id' => $item->id(),
                    'slug' => $item->slug(),
                    'title' => $item->title(),
                    'date' => $item->date()?->format('c'),
                    'status' => $item->status(),
                    'excerpt' => mb_substr($item->excerpt() ?? '', 0, 200),
                    'taxonomies' => $taxonomies,
                ];
                
                // Merge in any extra configured cache fields
                if (!empty($extraFields)) {
                    $itemData = array_merge($itemData, $extraFields);
                }
                
                $published[] = $itemData;
            }

            // Sort by date descending
            usort($published, function ($a, $b) {
                $aDate = $a['date'] ?? '';
                $bDate = $b['date'] ?? '';
                return strcmp($bDate, $aDate);
            });

            $cache[$typeName] = [
                'total' => count($published),
                'items' => array_slice($published, 0, $maxItems),
            ];
        }

        return $cache;
    }

    /**
     * Build the slug lookup table.
     * 
     * A lightweight index mapping type/slug to file path and minimal metadata.
     * Used for fast single-item lookups without loading the full content index.
     * 
     * Structure: [
     *     'post' => [
     *         'hello-world' => ['file' => 'content/posts/hello-world.md', 'id' => '...', 'status' => 'published'],
     *         ...
     *     ],
     *     ...
     * ]
     */
    private function buildSlugLookup(array $allItems): array
    {
        $lookup = [];

        foreach ($allItems as $typeName => $items) {
            $lookup[$typeName] = [];
            
            foreach ($items as $item) {
                $lookup[$typeName][$item->slug()] = [
                    'file' => $this->getRelativePath($item->filePath()),
                    'id' => $item->id(),
                    'status' => $item->status(),
                ];
            }
        }

        return $lookup;
    }

    /**
     * Build the taxonomy index.
     */
    private function buildTaxonomyIndex(array $allItems, array $taxonomies): array
    {
        $index = [];

        foreach ($taxonomies as $taxName => $taxConfig) {
            $index[$taxName] = [
                'config' => $taxConfig,
                'terms' => [],
            ];
        }

        // Collect terms from all content
        foreach ($allItems as $typeName => $items) {
            foreach ($items as $item) {
                if (!$item->isPublished()) {
                    continue;
                }

                foreach ($taxonomies as $taxName => $taxConfig) {
                    $terms = $item->terms($taxName);

                    foreach ($terms as $term) {
                        $termSlug = is_string($term) ? $term : ($term['slug'] ?? $term['name'] ?? '');
                        if (empty($termSlug)) {
                            continue;
                        }

                        if (!isset($index[$taxName]['terms'][$termSlug])) {
                            $index[$taxName]['terms'][$termSlug] = [
                                'slug' => $termSlug,
                                'name' => ucwords(str_replace(['-', '_', '/'], ' ', $termSlug)),
                                'count' => 0,
                                'items' => [],
                            ];
                        }

                        $index[$taxName]['terms'][$termSlug]['count']++;
                        $index[$taxName]['terms'][$termSlug]['items'][] = $typeName . ':' . $item->slug();
                    }
                }
            }
        }

        // Load term registries if they exist
        $taxonomiesPath = $this->app->configPath('content') . '/_taxonomies';
        foreach ($taxonomies as $taxName => $taxConfig) {
            $registryPath = $taxonomiesPath . '/' . $taxName . '.yml';
            if (file_exists($registryPath)) {
                $registry = \Symfony\Component\Yaml\Yaml::parseFile($registryPath);
                if (is_array($registry)) {
                    foreach ($registry as $termData) {
                        $slug = $termData['slug'] ?? '';
                        if ($slug && isset($index[$taxName]['terms'][$slug])) {
                            // Merge registry data with collected data
                            $index[$taxName]['terms'][$slug] = array_merge(
                                $index[$taxName]['terms'][$slug],
                                $termData
                            );
                        } elseif ($slug) {
                            // Term from registry not used in content
                            $index[$taxName]['terms'][$slug] = array_merge(
                                ['slug' => $slug, 'count' => 0, 'items' => []],
                                $termData
                            );
                        }
                    }
                }
            }
        }

        return $index;
    }

    /**
     * Build the routes index.
     */
    private function buildRoutes(array $allItems, array $contentTypes, array $taxonomies): array
    {
        $routes = [
            'redirects' => [],      // From redirect_from
            'exact' => [],          // Exact path => handler
            'patterns' => [],       // Pattern routes for CPTs
            'taxonomy' => [],       // Taxonomy archive routes
        ];

        foreach ($allItems as $typeName => $items) {
            $typeConfig = $contentTypes[$typeName] ?? [];
            $urlConfig = $typeConfig['url'] ?? [];

            foreach ($items as $item) {
                // Skip non-published for route generation
                // (they'll be accessible via preview token)
                if (!$item->isPublished()) {
                    continue;
                }

                // Generate URL for this item
                $url = $this->generateUrl($item, $typeConfig);

                // Add to exact routes
                $routes['exact'][$url] = [
                    'type' => 'single',
                    'content_type' => $typeName,
                    'slug' => $item->slug(),
                    'template' => $item->template() ?? $typeConfig['templates']['single'] ?? 'single.php',
                ];

                // Add redirect_from routes
                foreach ($item->redirectFrom() as $fromUrl) {
                    $routes['redirects'][$fromUrl] = [
                        'to' => $url,
                        'code' => 301,
                    ];
                }
            }

            // Add archive route if configured
            if (isset($urlConfig['archive'])) {
                $routes['exact'][$urlConfig['archive']] = [
                    'type' => 'archive',
                    'content_type' => $typeName,
                    'template' => $typeConfig['templates']['archive'] ?? 'archive.php',
                ];
            }
        }

        // Add taxonomy routes
        foreach ($taxonomies as $taxName => $taxConfig) {
            if (!($taxConfig['public'] ?? true)) {
                continue;
            }

            $base = $taxConfig['rewrite']['base'] ?? '/' . $taxName;

            $routes['taxonomy'][$taxName] = [
                'base' => $base,
                'hierarchical' => $taxConfig['hierarchical'] ?? false,
            ];
        }

        return $routes;
    }

    /**
     * Generate URL for a content item.
     */
    private function generateUrl(Item $item, array $typeConfig): string
    {
        $urlConfig = $typeConfig['url'] ?? [];
        $type = $urlConfig['type'] ?? 'pattern';

        if ($type === 'hierarchical') {
            // URL reflects file path structure
            return $this->generateHierarchicalUrl($item, $urlConfig);
        }

        // Pattern-based URL
        $pattern = $urlConfig['pattern'] ?? '/{slug}';

        $replacements = [
            '{slug}' => $item->slug(),
            '{id}' => $item->id() ?? '',
        ];

        // Date-based replacements
        $date = $item->date();
        if ($date) {
            $replacements['{yyyy}'] = $date->format('Y');
            $replacements['{mm}'] = $date->format('m');
            $replacements['{dd}'] = $date->format('d');
        }

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Generate hierarchical URL based on file path.
     */
    private function generateHierarchicalUrl(Item $item, array $urlConfig): string
    {
        $base = $urlConfig['base'] ?? '/';
        $contentPath = $this->app->configPath('content');
        $relativePath = $this->getRelativePath($item->filePath());

        // Remove type prefix (e.g., 'pages/')
        $parts = explode('/', $relativePath);
        array_shift($parts); // Remove type folder

        // Get path without .md extension
        $pathParts = [];
        foreach ($parts as $part) {
            if (str_ends_with($part, '.md')) {
                $part = substr($part, 0, -3);
            }
            // Handle index files
            if ($part !== 'index') {
                $pathParts[] = $part;
            }
        }

        $path = implode('/', $pathParts);

        if ($base === '/') {
            return '/' . ltrim($path, '/') ?: '/';
        }

        return rtrim($base, '/') . '/' . $path;
    }

    /**
     * Compute fingerprint for change detection.
     */
    private function computeFingerprint(): array
    {
        $fingerprint = [];

        $watchDirs = [
            'content' => $this->app->configPath('content'),
            'config' => $this->app->path('app/config'),
        ];

        foreach ($watchDirs as $name => $path) {
            if (!is_dir($path)) {
                continue;
            }

            $mtime = 0;
            $count = 0;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $mtime = max($mtime, $file->getMTime());
                    $count++;
                }
            }

            $fingerprint[$name] = [
                'mtime' => $mtime,
                'count' => $count,
            ];
        }

        // Hash config files
        $configFiles = ['ava.php', 'content_types.php', 'taxonomies.php'];
        $configHashes = [];
        foreach ($configFiles as $file) {
            $path = $this->app->path('app/config/' . $file);
            if (file_exists($path)) {
                $configHashes[$file] = md5_file($path);
            }
        }
        $fingerprint['config_hashes'] = $configHashes;

        return $fingerprint;
    }

    /**
     * Get the cache directory path.
     */
    private function getCachePath(string $filename = ''): string
    {
        $path = $this->app->configPath('storage') . '/cache';
        return $filename ? $path . '/' . $filename : $path;
    }

    /**
     * Write a binary cache file atomically.
     * Uses igbinary if available (faster, smaller), otherwise PHP serialize.
     * Adds a format marker prefix for reliable deserialization.
     */
    private function writeBinaryCacheFile(string $filename, array $data): void
    {
        $cachePath = $this->getCachePath();
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $targetPath = $cachePath . '/' . $filename;
        $tmpPath = $cachePath . '/.' . $filename . '.tmp';

        // Use igbinary if available (faster and smaller), otherwise serialize
        // Prefix with format marker: "IG:" for igbinary, "SZ:" for serialize
        if (extension_loaded('igbinary')) {
            /** @var callable $serialize */
            $serialize = 'igbinary_serialize';
            $content = "IG:" . $serialize($data);
        } else {
            $content = "SZ:" . serialize($data);
        }

        file_put_contents($tmpPath, $content, LOCK_EX);
        rename($tmpPath, $targetPath);
        chmod($targetPath, 0644);
    }

    /**
     * Write a JSON cache file atomically.
     */
    private function writeJsonCacheFile(string $filename, array $data): void
    {
        $cachePath = $this->getCachePath();
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $targetPath = $cachePath . '/' . $filename;
        $tmpPath = $cachePath . '/.' . $filename . '.tmp';

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($tmpPath, $content, LOCK_EX);
        rename($tmpPath, $targetPath);
        chmod($targetPath, 0644);
    }

    /**
     * Get relative path from content directory.
     */
    private function getRelativePath(string $absolutePath): string
    {
        $contentPath = $this->app->configPath('content');
        if (str_starts_with($absolutePath, $contentPath)) {
            return ltrim(substr($absolutePath, strlen($contentPath)), '/');
        }
        return $absolutePath;
    }

    /**
     * Load content type definitions.
     */
    private function loadContentTypes(): array
    {
        $path = $this->app->path('app/config/content_types.php');
        if (!file_exists($path)) {
            return [];
        }
        return require $path;
    }

    /**
     * Load taxonomy definitions.
     */
    private function loadTaxonomies(): array
    {
        $path = $this->app->path('app/config/taxonomies.php');
        if (!file_exists($path)) {
            return [];
        }
        return require $path;
    }

    /**
     * Log errors to storage.
     */
    private function logErrors(array $errors): void
    {
        $logPath = $this->app->configPath('storage') . '/logs';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        $logFile = $logPath . '/indexer.log';
        $content = "[" . date('c') . "] Indexer errors:\n";
        foreach ($errors as $error) {
            $content .= "  - {$error}\n";
        }
        $content .= "\n";

        file_put_contents($logFile, $content, FILE_APPEND | LOCK_EX);
    }
}
