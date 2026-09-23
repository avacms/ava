<?php

declare(strict_types=1);

namespace Ava\Content\Index;

use Ava\Content\Item;

/**
 * Markdown rendered at build time, one small signed file per item.
 *
 * Rendering one page reads one file, instead of loading every page's HTML.
 * Each entry records a hash of the source it was rendered from, and a render
 * only uses it when the file on disk still matches, so an edit made without a
 * rebuild (manual index mode) can never show stale HTML.
 *
 * Stored HTML is the raw Markdown output. Shortcodes and path aliases are
 * applied at render time by the same code as on-demand rendering, so both
 * paths produce identical pages.
 */
final class PrerenderedHtml
{
    public const DIRECTORY = 'html';

    public static function relativePath(string $type, string $contentKey): string
    {
        return self::DIRECTORY . '/' . sha1($type . ':' . $contentKey) . '.bin';
    }

    public static function sourceHash(Item $item): string
    {
        return sha1(serialize([$item->rawContent(), $item->markdownOptions()]));
    }

    public static function entry(Item $item, string $html): array
    {
        return ['source' => self::sourceHash($item), 'html' => $html];
    }

    /**
     * The stored HTML if it was rendered from exactly this item's source.
     */
    public static function match(?array $entry, Item $item): ?string
    {
        if ($entry === null || !is_string($entry['html'] ?? null)) {
            return null;
        }

        return ($entry['source'] ?? null) === self::sourceHash($item) ? $entry['html'] : null;
    }
}
