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
        [$frontmatter, $body] = $this->splitFrontmatter($content);

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
        }

        // Ensure required fields have defaults
        $meta = $this->applyDefaults($meta, $filePath);

        // Detect format from file extension
        $format = $this->detectFormat($filePath);

        return new Item($meta, $body, $filePath, $type, $format);
    }

    /**
     * Split frontmatter from content.
     *
     * @return array{0: string, 1: string} [frontmatter, content]
     * @throws \RuntimeException If frontmatter delimiters are incomplete
     */
    private function splitFrontmatter(string $content): array
    {
        $content = ltrim($content);

        // Check for frontmatter delimiter at start
        if (!str_starts_with($content, self::FRONTMATTER_DELIMITER)) {
            return ['', $content];
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

        return [trim($frontmatter), $body];
    }

    /**
     * Apply default values to frontmatter.
     */
    private function applyDefaults(array $meta, string $filePath): array
    {
        // Default slug from filename
        if (!isset($meta['slug'])) {
            $meta['slug'] = Path::filename($filePath);
        }

        // Default title from slug
        if (!isset($meta['title'])) {
            $meta['title'] = ucwords(str_replace(['-', '_'], ' ', $meta['slug']));
        }

        // Default status
        if (!isset($meta['status'])) {
            $meta['status'] = 'draft';
        }

        return $meta;
    }

    /**
     * Validate that required frontmatter fields exist.
     *
     * @return array<string> List of validation errors
     */
    public function validate(Item $item): array
    {
        $errors = [];

        if (empty($item->title())) {
            $errors[] = "Missing required field: title — see https://ava.addy.zone/docs/content";
        }

        if (empty($item->slug())) {
            $errors[] = "Missing required field: slug — see https://ava.addy.zone/docs/content";
        }

        if (!in_array($item->status(), ['draft', 'published', 'unlisted'], true)) {
            $errors[] = "Invalid status: {$item->status()} (must be draft, published, or unlisted) — see https://ava.addy.zone/docs/content";
        }

        // Validate slug is URL-safe
        if (!preg_match('/^[a-z0-9-]+$/', $item->slug())) {
            $errors[] = "Slug must be lowercase alphanumeric with hyphens: {$item->slug()} — see https://ava.addy.zone/docs/content";
        }

        return $errors;
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
