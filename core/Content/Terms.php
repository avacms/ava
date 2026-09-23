<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Support\Str;

/**
 * Taxonomy term normalisation.
 *
 * Frontmatter says `category: Tutorials` or `tag: [Web Dev]`; URLs, the index
 * and filters all use the slug form ("tutorials", "web-dev"). Hierarchical
 * terms keep their separators: "Guides/PHP Tips" -> "guides/php-tips".
 */
final class Terms
{
    public static function slug(string $term): string
    {
        $segments = array_filter(
            array_map(static fn(string $segment): string => Str::slug($segment), explode('/', $term)),
            static fn(string $segment): bool => $segment !== ''
        );

        return implode('/', $segments);
    }

    /**
     * Slugs for a list of frontmatter terms, de-duplicated, empties removed.
     *
     * @param array<string> $terms
     * @return list<string>
     */
    public static function slugs(array $terms): array
    {
        // A list rather than array_keys(): PHP would turn a slug like "2024"
        // into an int key, and strict comparisons against URL strings fail.
        $slugs = [];
        foreach ($terms as $term) {
            $slug = self::slug((string) $term);
            if ($slug !== '' && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * A display name for a term: the author's own spelling unless it was
     * already written as a slug, which gets title-cased as before.
     */
    public static function displayName(string $original, string $slug): string
    {
        $original = trim($original);
        if ($original !== '' && $original !== $slug) {
            return $original;
        }

        return ucwords(str_replace(['-', '_', '/'], ' ', $slug));
    }
}
