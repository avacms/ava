<?php

declare(strict_types=1);

namespace Ava\Content;

use Ava\Support\Path;
use Symfony\Component\Yaml\Yaml;

/**
 * Content Parser
 *
 * Parses content files (.md and .html) with YAML frontmatter.
 */
final class Parser
{
    private const FRONTMATTER_DELIMITER = '---';

    /**
     * Parse a content file.
     */
    public function parseFile(string $filePath, string $type): Item
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Content file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);

        return $this->parse($content, $filePath, $type);
    }

    /**
     * Parse content string with frontmatter.
     */
    public function parse(string $content, string $filePath, string $type): Item
    {
        [$frontmatter, $body, $hasFrontmatter] = $this->splitFrontmatter($content);

        // Parse YAML frontmatter
        $meta = [];
        if ($frontmatter !== '') {
            try {
                // Use PARSE_EXCEPTION_ON_INVALID_TYPE for defense-in-depth against object injection
                $meta = Yaml::parse($frontmatter, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE) ?? [];
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    "Invalid YAML frontmatter in {$filePath}: " . $e->getMessage()
                );
            }

            if (!is_array($meta)) {
                throw new \RuntimeException(
                    "Invalid YAML frontmatter in {$filePath}: expected `key: value` lines, got " . get_debug_type($meta)
                );
            }
        }

        // Ensure required fields have defaults
        [$meta, $defaultedFields] = $this->applyDefaults($meta, $filePath);

        // Detect format from file extension
        $format = $this->detectFormat($filePath);

        return new Item($meta, $body, $filePath, $type, $format, $hasFrontmatter, $defaultedFields);
    }

    /**
     * Split frontmatter from content.
     *
    * @return array{0: string, 1: string, 2: bool} [frontmatter, content, has frontmatter]
     * @throws \RuntimeException If frontmatter delimiters are incomplete
     */
    private function splitFrontmatter(string $content): array
    {
        $content = ltrim($content);

        // Check for frontmatter delimiter at start
        if (!str_starts_with($content, self::FRONTMATTER_DELIMITER)) {
            return ['', $content, false];
        }

        // Find the closing delimiter
        $delimiterLength = strlen(self::FRONTMATTER_DELIMITER);
        $endPos = strpos($content, "\n" . self::FRONTMATTER_DELIMITER, $delimiterLength);

        if ($endPos === false) {
            // No closing delimiter - this is an error
            throw new \RuntimeException(
                "Missing closing frontmatter delimiter (---). Files must start with --- and have a closing --- on its own line."
            );
        }

        $frontmatter = substr($content, $delimiterLength, $endPos - $delimiterLength);
        $body = substr($content, $endPos + 1 + $delimiterLength);

        // Trim leading newlines from body
        $body = ltrim($body, "\r\n");

        return [trim($frontmatter), $body, true];
    }

    /**
     * Apply default values to frontmatter.
     */
    private function applyDefaults(array $meta, string $filePath): array
    {
        $defaultedFields = [];

        // Default slug from filename
        if (!isset($meta['slug'])) {
            $meta['slug'] = Path::filename($filePath);
            $defaultedFields[] = 'slug';
        }

        // Default title from slug
        if (!isset($meta['title'])) {
            $meta['title'] = ucwords(str_replace(['-', '_'], ' ', $meta['slug']));
            $defaultedFields[] = 'title';
        }

        // Default status
        if (!isset($meta['status'])) {
            $meta['status'] = 'draft';
            $defaultedFields[] = 'status';
        }

        return [$meta, $defaultedFields];
    }

    /**
     * Validate that required frontmatter fields exist.
     *
     * @return array<string> List of validation errors
     */
    public function validate(Item $item): array
    {
        $errors = [];
        $frontmatter = $item->frontmatter();

        foreach (Item::STRING_FIELDS as $field) {
            $value = $frontmatter[$field] ?? null;
            if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value)) {
                $errors[] = "Field '{$field}' must be text, got " . get_debug_type($value);
            }
        }

        if (empty($item->title())) {
            $errors[] = "Missing required field: title — see https://ava.addy.zone/docs/content";
        }

        if (empty($item->slug())) {
            $errors[] = "Missing required field: slug — see https://ava.addy.zone/docs/content";
        }

        if (!in_array($item->status(), ['draft', 'published', 'unlisted'], true)) {
            $errors[] = "Invalid status: {$item->status()} (must be draft, published, or unlisted) — see https://ava.addy.zone/docs/content";
        }

        // Lowercase letters (any script), digits and hyphens. Non-ASCII slugs
        // work because the router matches decoded paths ("/café").
        if (preg_match('/^[\p{Ll}\p{Lo}\p{Lm}\p{M}\p{Nd}-]+$/u', $item->slug()) !== 1) {
            $errors[] = "Slug must be lowercase letters, numbers and hyphens: {$item->slug()} — see https://ava.addy.zone/docs/content";
        }

        return $errors;
    }

    /**
     * Report authored metadata that was missing before defaults were applied.
     *
     * @return array<string> List of validation warnings
     */
    public function validateWarnings(Item $item): array
    {
        $warnings = [];

        if (!$item->hasFrontmatter()) {
            $warnings[] = 'No frontmatter delimiter found — add a YAML frontmatter block';
        }

        if ($item->wasDefaulted('title')) {
            $warnings[] = 'Title auto-generated — add an explicit title';
        }

        foreach (Item::STRING_FIELDS as $field) {
            $value = $item->frontmatter()[$field] ?? null;
            if (is_int($value) || is_float($value)) {
                $warnings[] = "Field '{$field}' was read by YAML as a number or date ({$value}) — wrap it in quotes to keep the text as written";
            }
        }

        return $warnings;
    }

    /**
     * Detect content format from file extension.
     */
    private function detectFormat(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return $ext === 'html' ? Item::FORMAT_HTML : Item::FORMAT_MARKDOWN;
    }
}
