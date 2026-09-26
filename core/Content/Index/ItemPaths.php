<?php

declare(strict_types=1);

namespace Ava\Content\Index;

use Ava\Content\Item;

/**
 * Where an item lives: its content-relative file, content key and URL.
 */
final class ItemPaths
{
    private string $contentRoot;

    public function __construct(string $contentRoot)
    {
        $this->contentRoot = rtrim(str_replace('\\', '/', $contentRoot), '/');
    }

    /**
     * Path relative to the content directory, e.g. "pages/about/team.md".
     */
    public function relativePath(Item|string $item): string
    {
        $absolute = str_replace('\\', '/', $item instanceof Item ? $item->filePath() : $item);

        if (str_starts_with($absolute, $this->contentRoot . '/')) {
            return substr($absolute, strlen($this->contentRoot) + 1);
        }

        return $absolute;
    }

    /**
     * The item's unique key within its type: the path for hierarchical types
     * ("about/team"), the slug for pattern types.
     */
    public function contentKey(Item $item, array $typeConfig): string
    {
        if (($typeConfig['url']['type'] ?? 'pattern') !== 'hierarchical') {
            return $item->slug();
        }

        return $this->pathKey($item, $typeConfig);
    }

    public function url(Item $item, array $typeConfig): string
    {
        $urlConfig = $typeConfig['url'] ?? [];

        if (($urlConfig['type'] ?? 'pattern') === 'hierarchical') {
            $base = $urlConfig['base'] ?? '/';
            $path = $this->pathKey($item, $typeConfig);

            if ($base === '/') {
                return $path === '' ? '/' : '/' . ltrim($path, '/');
            }

            return $path === '' ? rtrim($base, '/') : rtrim($base, '/') . '/' . $path;
        }

        $replacements = [
            '{slug}' => $item->slug(),
            '{id}' => $item->id() ?? '',
        ];

        $date = $item->date();
        if ($date !== null) {
            $replacements['{yyyy}'] = $date->format('Y');
            $replacements['{mm}'] = $date->format('m');
            $replacements['{dd}'] = $date->format('d');
        }

        return strtr($urlConfig['pattern'] ?? '/{slug}', $replacements);
    }

    /**
     * "pages/about/team.md" -> "about/team"; index files map to their folder.
     */
    private function pathKey(Item $item, array $typeConfig): string
    {
        $directory = trim(str_replace('\\', '/', (string) ($typeConfig['content_dir'] ?? $item->type())), '/');
        $relative = $this->relativePath($item);
        $relative = str_starts_with($relative, $directory . '/')
            ? substr($relative, strlen($directory) + 1)
            : (explode('/', $relative, 2)[1] ?? '');

        $pathParts = [];
        foreach (explode('/', $relative) as $part) {
            if (str_ends_with($part, '.md')) {
                $part = substr($part, 0, -3);
            } elseif (str_ends_with($part, '.html')) {
                $part = substr($part, 0, -5);
            }
            if ($part !== 'index' && $part !== '_index') {
                $pathParts[] = $part;
            }
        }

        return implode('/', $pathParts);
    }
}
