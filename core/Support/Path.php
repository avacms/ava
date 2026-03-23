<?php

declare(strict_types=1);

namespace Ava\Support;

/**
 * Path helper utilities.
 */
final class Path
{
    /**
     * Normalize a path (resolve . and .., normalize slashes).
     */
    public static function normalize(string $path): string
    {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Collapse multiple slashes
        $path = preg_replace('#/+#', '/', $path);

        // Resolve . and ..
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        $normalized = implode('/', $parts);

        // Preserve leading slash
        if (str_starts_with($path, '/')) {
            $normalized = '/' . $normalized;
        }

        return $normalized ?: '/';
    }

    /**
     * Join path segments.
     */
    public static function join(string ...$parts): string
    {
        $path = implode('/', array_filter($parts, fn($p) => $p !== ''));
        return self::normalize($path);
    }

    /**
     * Get the relative path from one path to another.
     */
    public static function relative(string $from, string $to): string
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        $fromParts = explode('/', trim($from, '/'));
        $toParts = explode('/', trim($to, '/'));

        // Find common prefix
        $common = 0;
        while (
            isset($fromParts[$common], $toParts[$common]) &&
            $fromParts[$common] === $toParts[$common]
        ) {
            $common++;
        }

        // Build relative path
        $ups = array_fill(0, count($fromParts) - $common, '..');
        $downs = array_slice($toParts, $common);

        return implode('/', array_merge($ups, $downs)) ?: '.';
    }

    /**
     * Get the directory name.
     */
    public static function dirname(string $path): string
    {
        $path = self::normalize($path);
        $dir = dirname($path);
        return $dir === '.' ? '' : $dir;
    }

    /**
     * Get the base name (filename with extension).
     */
    public static function basename(string $path, ?string $suffix = null): string
    {
        $path = self::normalize($path);
        return $suffix !== null ? basename($path, $suffix) : basename($path);
    }

    /**
     * Get the file extension.
     */
    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get the filename without extension.
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Check if a path is absolute.
     */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path);
    }

    /**
     * Make a path absolute.
     */
    public static function makeAbsolute(string $path, string $base): string
    {
        if (self::isAbsolute($path)) {
            return self::normalize($path);
        }

        return self::normalize(self::join($base, $path));
    }

    /**
     * Check if a path is inside another path.
     * 
     * Uses realpath() when both paths exist on disk (resolves symlinks),
     * falls back to string comparison for paths that don't exist yet.
     */
    public static function isInside(string $path, string $base): bool
    {
        // When both paths exist, use realpath() to resolve symlinks
        $realPath = realpath($path);
        $realBase = realpath($base);
        if ($realPath !== false && $realBase !== false) {
            $realBase = rtrim($realBase, '/') . '/';
            return str_starts_with($realPath, $realBase) || $realPath === rtrim($realBase, '/');
        }

        // Fallback to string comparison for paths that don't exist yet
        $path = self::normalize($path);
        $base = rtrim(self::normalize($base), '/') . '/';

        return str_starts_with($path, $base) || $path === rtrim($base, '/');
    }

    /**
     * Convert a file path to a URL path segment.
     */
    public static function toUrl(string $path, string $base = ''): string
    {
        $path = self::normalize($path);

        if ($base !== '') {
            $base = self::normalize($base);
            if (str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        // Remove .md extension
        if (str_ends_with($path, '.md')) {
            $path = substr($path, 0, -3);
        }

        // Handle index files
        if (str_ends_with($path, '/index')) {
            $path = rtrim($path, 'index');
        }

        return '/' . ltrim($path, '/');
    }
}
