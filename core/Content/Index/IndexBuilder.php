<?php

declare(strict_types=1);

namespace Ava\Content\Index;

use Ava\Content\Item;
use Ava\Content\QueryProcessor;
use Ava\Content\Terms;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns scanned items into the index structures a generation stores.
 *
 * Every method is a pure function of its arguments (plus the taxonomy and
 * search YAML under the content directory), so each can be tested alone.
 */
final class IndexBuilder
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(private ItemPaths $paths, private string $contentRoot)
    {
    }

    /**
     * Problems found while building (URL collisions, bad YAML registries).
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Metadata for every item, each stored once, with lookup maps by ID and
     * path. Bodies are deliberately absent: listings, feeds and sitemaps
     * never need them, and they made this file several times larger.
     *
     * @param array<string, list<Item>> $allItems
     * @return array{by_type: array<string, array<string, array>>, by_id: array<string, array{0: string, 1: string}>, by_path: array<string, array{0: string, 1: string}>}
     */
    public function contentIndex(array $allItems, array $contentTypes): array
    {
        $index = ['by_type' => [], 'by_id' => [], 'by_path' => []];

        foreach ($allItems as $type => $items) {
            $typeConfig = $contentTypes[$type] ?? [];
            $index['by_type'][$type] = [];

            foreach ($items as $item) {
                $key = $this->paths->contentKey($item, $typeConfig);
                $data = $this->metadata($item, (string) $type, $key, $typeConfig);

                $index['by_type'][$type][$key] = $data;
                if ($item->id() !== null) {
                    $index['by_id'][$item->id()] = [(string) $type, $key];
                }
                $index['by_path'][$data['relative_path']] = [(string) $type, $key];
            }
        }

        return $index;
    }

    /**
     * Raw bodies keyed "type:contentKey" in shards of about $shardBytes, so
     * search can read them one at a time.
     *
     * @param array<string, list<Item>> $allItems
     * @return list<array<string, string>>
     */
    public function bodies(array $allItems, array $contentTypes, int $shardBytes = 1 << 20): array
    {
        $shards = [[]];
        $size = 0;
        foreach ($allItems as $type => $items) {
            foreach ($items as $item) {
                if ($size >= $shardBytes) {
                    $shards[] = [];
                    $size = 0;
                }
                $key = $type . ':' . $this->paths->contentKey($item, $contentTypes[$type] ?? []);
                $shards[array_key_last($shards)][$key] = $item->rawContent();
                $size += strlen($item->rawContent());
            }
        }

        return $shards;
    }

    /**
     * One item's indexed metadata (the shape backends hand to queries).
     */
    public function metadata(Item $item, string $type, string $contentKey, array $typeConfig): array
    {
        $data = $item->toArray();
        unset($data['body']);

        $data['type'] = $type;
        $data['content_key'] = $contentKey;
        $data['relative_path'] = $this->paths->relativePath($item);
        $data['taxonomies'] = $this->itemTaxonomies($item, $typeConfig);

        return $data;
    }

    /**
     * Pre-sorted metadata for the first pages of each type's archive.
     *
     * @param array<string, list<Item>> $allItems
     */
    public function recentCache(array $allItems, array $contentTypes, int $maxItems = 200): array
    {
        $cache = [];

        foreach ($allItems as $type => $items) {
            $typeConfig = $contentTypes[$type] ?? [];
            $cacheFields = $typeConfig['cache_fields'] ?? [];
            $published = [];

            foreach ($items as $item) {
                if (!$item->isPublished()) {
                    continue;
                }

                $frontmatter = $item->frontmatter();
                $taxonomies = $this->itemTaxonomies($item, $typeConfig);
                $entry = [
                    'id' => $item->id(),
                    'slug' => $item->slug(),
                    'content_key' => $this->paths->contentKey($item, $typeConfig),
                    'title' => $item->title(),
                    'type' => (string) $type,
                    'date' => $item->date()?->format('c'),
                    'updated' => $item->updated()?->format('c'),
                    'status' => $item->status(),
                    'excerpt' => $item->excerpt(),
                    'noindex' => $item->noindex(),
                    'taxonomies' => $taxonomies,
                ];

                // Raw term values too, so $entry->terms('tag') works in
                // listings exactly as it does on a single page.
                foreach (array_keys($taxonomies) as $taxonomy) {
                    if (array_key_exists($taxonomy, $frontmatter) && !isset($entry[$taxonomy])) {
                        $entry[$taxonomy] = $frontmatter[$taxonomy];
                    }
                }
                foreach ($cacheFields as $field) {
                    if (is_string($field) && array_key_exists($field, $frontmatter) && !isset($entry[$field])) {
                        $entry[$field] = $frontmatter[$field];
                    }
                }

                $published[] = $entry;
            }

            // The same ordering the full query path uses, so page 20 and
            // page 21 of an archive agree about what comes next.
            $published = QueryProcessor::applySort($published, 'date', 'desc');

            $cache[$type] = [
                'total' => count($published),
                'items' => array_slice($published, 0, $maxItems),
            ];
        }

        return $cache;
    }

    /**
     * type => contentKey => {file, id, status, slug} for single-item lookups.
     *
     * @param array<string, list<Item>> $allItems
     */
    public function slugLookup(array $allItems, array $contentTypes): array
    {
        $lookup = [];

        foreach ($allItems as $type => $items) {
            $typeConfig = $contentTypes[$type] ?? [];
            $lookup[$type] = [];

            foreach ($items as $item) {
                $lookup[$type][$this->paths->contentKey($item, $typeConfig)] = [
                    'file' => $this->paths->relativePath($item),
                    'id' => $item->id(),
                    'status' => $item->status(),
                    'slug' => $item->slug(),
                ];
            }
        }

        return $lookup;
    }

    /**
     * Terms per taxonomy, keyed by normalised slug, with published counts.
     *
     * @param array<string, list<Item>> $allItems
     */
    public function taxonomyIndex(array $allItems, array $taxonomies, array $contentTypes): array
    {
        $index = [];
        $seen = [];
        foreach ($taxonomies as $taxonomy => $config) {
            $index[$taxonomy] = ['config' => $config, 'terms' => []];
        }

        foreach ($allItems as $type => $items) {
            $typeConfig = $contentTypes[$type] ?? [];
            $declared = array_intersect($typeConfig['taxonomies'] ?? [], array_keys($taxonomies));

            foreach ($items as $item) {
                if (!$item->isPublished()) {
                    continue;
                }

                $itemKey = $type . ':' . $this->paths->contentKey($item, $typeConfig);

                foreach ($declared as $taxonomy) {
                    foreach ($item->terms($taxonomy) as $original) {
                        $slug = Terms::slug($original);
                        if ($slug === '') {
                            continue;
                        }

                        $index[$taxonomy]['terms'][$slug] ??= [
                            'slug' => $slug,
                            'name' => Terms::displayName($original, $slug),
                            'count' => 0,
                            'items' => [],
                        ];
                        if (!isset($seen[$taxonomy][$slug][$itemKey])) {
                            $seen[$taxonomy][$slug][$itemKey] = true;
                            $index[$taxonomy]['terms'][$slug]['count']++;
                            $index[$taxonomy]['terms'][$slug]['items'][] = $itemKey;
                        }
                    }
                }
            }
        }

        // Registries (content/_taxonomies/<taxonomy>.yml) add names,
        // descriptions and terms not yet used by any content.
        foreach (array_keys($taxonomies) as $taxonomy) {
            $registryPath = $this->contentRoot . '/_taxonomies/' . $taxonomy . '.yml';
            if (!is_file($registryPath)) {
                continue;
            }

            try {
                $registry = Yaml::parseFile($registryPath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (\Throwable $e) {
                $this->errors[] = "{$registryPath}: " . $e->getMessage();
                continue;
            }

            foreach (is_array($registry) ? $registry : [] as $termData) {
                if (!is_array($termData) || !is_scalar($termData['slug'] ?? null)) {
                    continue;
                }
                $slug = Terms::slug((string) $termData['slug']);
                if ($slug === '') {
                    continue;
                }

                $existing = $index[$taxonomy]['terms'][$slug] ?? [
                    'name' => Terms::displayName('', $slug),
                    'count' => 0,
                    'items' => [],
                ];
                $index[$taxonomy]['terms'][$slug] = array_merge($existing, $termData, [
                    'slug' => $slug,
                    'count' => $existing['count'],
                    'items' => $existing['items'],
                ]);
            }
        }

        return $index;
    }

    /**
     * Route tables. Drafts get their URLs under 'preview' so a valid preview
     * link can reach them at the address they will be published at, while
     * 'exact' stays public-only (plugins read it to enumerate the site).
     *
     * @param array<string, list<Item>> $allItems
     */
    public function routes(array $allItems, array $contentTypes, array $taxonomies): array
    {
        $routes = [
            'redirects' => [],
            'exact' => [],
            'preview' => [],
            'patterns' => [],
            'taxonomy' => [],
            'reverse' => [],
        ];

        foreach ($allItems as $type => $items) {
            $typeConfig = $contentTypes[$type] ?? [];

            foreach ($items as $item) {
                $url = $this->paths->url($item, $typeConfig);
                $key = $this->paths->contentKey($item, $typeConfig);
                $route = [
                    'type' => 'single',
                    'content_type' => (string) $type,
                    'content_key' => $key,
                    'slug' => $item->slug(),
                    'file' => $this->paths->relativePath($item),
                    'template' => $item->template() ?? $typeConfig['templates']['single'] ?? 'single.php',
                ];

                if (preg_match('#\{\w+\}|//#', $url) === 1) {
                    $this->errors[] = "{$route['file']}: URL {$url} has an empty or unfilled part; check url.pattern and the item's date and id";
                }

                if ($item->isDraft()) {
                    $routes['preview'][$url] = $route;
                    continue;
                }

                if (isset($routes['exact'][$url])) {
                    $this->errors[] = "{$route['file']}: URL {$url} is already used by {$routes['exact'][$url]['file']}";
                }
                $routes['exact'][$url] = $route;
                $routes['reverse'][$type . ':' . $key] = $url;

                foreach ($item->redirectFrom() as $from) {
                    $from = self::normalizeRedirectPath($from);
                    if ($from !== null && $from !== $url) {
                        $routes['redirects'][$from] = ['to' => $url, 'code' => 301];
                    }
                }
            }

            $archive = $typeConfig['url']['archive'] ?? null;
            if (is_string($archive) && $archive !== '') {
                $routes['exact'][$archive] = [
                    'type' => 'archive',
                    'content_type' => (string) $type,
                    'template' => $typeConfig['templates']['archive'] ?? 'archive.php',
                ];
            }
        }

        foreach ($taxonomies as $taxonomy => $config) {
            if (!($config['public'] ?? true)) {
                continue;
            }

            $routes['taxonomy'][$taxonomy] = [
                'base' => $config['rewrite']['base'] ?? '/' . $taxonomy,
                'hierarchical' => $config['hierarchical'] ?? false,
            ];
        }

        return $routes;
    }

    /**
     * Search synonyms (content/_search/synonyms.yml) as word => other words.
     *
     * @return array<string, list<string>>
     */
    public function synonyms(): array
    {
        $groups = $this->readSearchYaml('synonyms.yml');
        $map = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $words = array_values(array_unique(array_filter(
                array_map(static fn($word) => is_string($word) ? strtolower(trim($word)) : '', $group)
            )));
            if (count($words) < 2) {
                continue;
            }

            foreach ($words as $word) {
                $others = array_values(array_filter($words, static fn($other) => $other !== $word));
                $map[$word] = array_values(array_unique(array_merge($map[$word] ?? [], $others)));
            }
        }

        return $map;
    }

    /**
     * Stop words (content/_search/stopwords.yml) as word => true.
     *
     * @return array<string, true>
     */
    public function stopWords(): array
    {
        $words = [];
        foreach ($this->readSearchYaml('stopwords.yml') as $word) {
            if (is_string($word) && trim($word) !== '') {
                $words[strtolower(trim($word))] = true;
            }
        }

        return $words;
    }

    /**
     * Normalised terms per declared taxonomy.
     *
     * @return array<string, list<string>>
     */
    private function itemTaxonomies(Item $item, array $typeConfig): array
    {
        $taxonomies = [];
        foreach ($typeConfig['taxonomies'] ?? [] as $taxonomy) {
            if (is_string($taxonomy) && $taxonomy !== '') {
                $taxonomies[$taxonomy] = Terms::slugs($item->terms($taxonomy));
            }
        }

        return $taxonomies;
    }

    /**
     * Match the form the router looks paths up in: leading slash, no
     * trailing slash (except the root).
     */
    private static function normalizeRedirectPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            return null;
        }

        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    private function readSearchYaml(string $file): array
    {
        $path = $this->contentRoot . '/_search/' . $file;
        if (!is_file($path)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (\Throwable $e) {
            $this->errors[] = "Failed to parse {$path}: " . $e->getMessage();
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
