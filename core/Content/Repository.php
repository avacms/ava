<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Application;

/**
 * Content Repository
 *
 * Provides read access to indexed content.
 * Metadata comes from cache, raw content is loaded on demand from files.
 */
final class Repository
{
    private Application $app;
    private Parser $parser;
    private ?array $contentIndex = null;
    private ?array $taxIndex = null;
    private ?array $routes = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->parser = new Parser();
    }

    /**
     * Load raw content from a file and return the Item with content.
     */
    private function hydrateItem(array $data): Item
    {
        $filePath = $data['file_path'] ?? '';
        $rawContent = '';

        // Load raw content from the file if the path exists
        if ($filePath !== '' && file_exists($filePath)) {
            $item = $this->parser->parseFile($filePath, $data['type'] ?? '');
            $rawContent = $item->rawContent();
        }

        return Item::fromArray($data, $rawContent);
    }

    // -------------------------------------------------------------------------
    // Content retrieval
    // -------------------------------------------------------------------------

    /**
     * Get a content item by type and slug.
     */
    public function get(string $type, string $slug): ?Item
    {
        $index = $this->loadContentIndex();
        $data = $index['by_type'][$type][$slug] ?? null;

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get a content item by ID.
     */
    public function getById(string $id): ?Item
    {
        $index = $this->loadContentIndex();
        $data = $index['by_id'][$id] ?? null;

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get a content item by file path.
     */
    public function getByPath(string $relativePath): ?Item
    {
        $index = $this->loadContentIndex();
        $data = $index['by_path'][$relativePath] ?? null;

        if ($data === null) {
            return null;
        }

        return $this->hydrateItem($data);
    }

    /**
     * Get all items of a type.
     *
     * @return array<Item>
     */
    public function all(string $type): array
    {
        $index = $this->loadContentIndex();
        $items = $index['by_type'][$type] ?? [];

        return array_map(fn($data) => $this->hydrateItem($data), $items);
    }

    /**
     * Get published items of a type.
     *
     * @return array<Item>
     */
    public function published(string $type): array
    {
        return array_filter(
            $this->all($type),
            fn(Item $item) => $item->isPublished()
        );
    }

    /**
     * Check if a content item exists.
     */
    public function exists(string $type, string $slug): bool
    {
        $index = $this->loadContentIndex();
        return isset($index['by_type'][$type][$slug]);
    }

    /**
     * Get content types that have items.
     *
     * @return array<string>
     */
    public function types(): array
    {
        $index = $this->loadContentIndex();
        return array_keys($index['by_type'] ?? []);
    }

    /**
     * Get count of items by type.
     */
    public function count(string $type, ?string $status = null): int
    {
        $items = $this->all($type);

        if ($status !== null) {
            $items = array_filter($items, fn(Item $item) => $item->status() === $status);
        }

        return count($items);
    }

    // -------------------------------------------------------------------------
    // Taxonomy retrieval
    // -------------------------------------------------------------------------

    /**
     * Get all terms for a taxonomy.
     */
    public function terms(string $taxonomy): array
    {
        $index = $this->loadTaxIndex();
        return $index[$taxonomy]['terms'] ?? [];
    }

    /**
     * Get a specific term.
     */
    public function term(string $taxonomy, string $slug): ?array
    {
        $terms = $this->terms($taxonomy);
        return $terms[$slug] ?? null;
    }

    /**
     * Get content items with a specific term.
     *
     * @return array<Item>
     */
    public function itemsWithTerm(string $taxonomy, string $termSlug): array
    {
        $term = $this->term($taxonomy, $termSlug);
        if ($term === null) {
            return [];
        }

        $items = [];
        foreach ($term['items'] ?? [] as $key) {
            [$type, $slug] = explode(':', $key, 2);
            $item = $this->get($type, $slug);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get taxonomy configuration.
     */
    public function taxonomyConfig(string $taxonomy): ?array
    {
        $index = $this->loadTaxIndex();
        return $index[$taxonomy]['config'] ?? null;
    }

    /**
     * Get all taxonomy names.
     *
     * @return array<string>
     */
    public function taxonomies(): array
    {
        $index = $this->loadTaxIndex();
        return array_keys($index);
    }

    // -------------------------------------------------------------------------
    // Routes
    // -------------------------------------------------------------------------

    /**
     * Get the routes index.
     */
    public function routes(): array
    {
        return $this->loadRoutes();
    }

    /**
     * Find route data for a path.
     */
    public function routeFor(string $path): ?array
    {
        $routes = $this->loadRoutes();

        // Check redirects first
        if (isset($routes['redirects'][$path])) {
            return [
                'type' => 'redirect',
                'to' => $routes['redirects'][$path]['to'],
                'code' => $routes['redirects'][$path]['code'] ?? 301,
            ];
        }

        // Check exact routes
        if (isset($routes['exact'][$path])) {
            return $routes['exact'][$path];
        }

        // Check taxonomy routes
        foreach ($routes['taxonomy'] ?? [] as $taxName => $taxRoute) {
            $base = rtrim($taxRoute['base'], '/');
            if (str_starts_with($path, $base . '/')) {
                $termPath = substr($path, strlen($base) + 1);
                return [
                    'type' => 'taxonomy',
                    'taxonomy' => $taxName,
                    'term' => $termPath,
                    'template' => 'taxonomy.php',
                ];
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Cache loading
    // -------------------------------------------------------------------------

    private function loadContentIndex(): array
    {
        if ($this->contentIndex === null) {
            $path = $this->getCachePath('content_index.php');
            $this->contentIndex = file_exists($path) ? require $path : [];
        }
        return $this->contentIndex;
    }

    private function loadTaxIndex(): array
    {
        if ($this->taxIndex === null) {
            $path = $this->getCachePath('tax_index.php');
            $this->taxIndex = file_exists($path) ? require $path : [];
        }
        return $this->taxIndex;
    }

    private function loadRoutes(): array
    {
        if ($this->routes === null) {
            $path = $this->getCachePath('routes.php');
            $this->routes = file_exists($path) ? require $path : [];
        }
        return $this->routes;
    }

    private function getCachePath(string $filename): string
    {
        return $this->app->configPath('storage') . '/cache/' . $filename;
    }

    /**
     * Clear cached data (for testing or forced reload).
     */
    public function clearCache(): void
    {
        $this->contentIndex = null;
        $this->taxIndex = null;
        $this->routes = null;
    }
}
