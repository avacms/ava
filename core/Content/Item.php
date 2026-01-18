<?php

declare(strict_types=1);

namespace Ava\Content;

/**
 * Content Item
 *
 * Represents a single piece of content (page, post, etc.)
 * Immutable value object created from parsed Markdown + frontmatter.
 */
final class Item
{
    private array $frontmatter;
    private string $rawContent;
    private ?string $htmlContent = null;
    private string $filePath;
    private string $type;

    /** @var array<string, \DateTimeImmutable|null|false> Cached parsed dates (false = not yet computed) */
    private array $dateCache = [];

    public function __construct(
        array $frontmatter,
        string $rawContent,
        string $filePath,
        string $type
    ) {
        $this->frontmatter = $frontmatter;
        $this->rawContent = $rawContent;
        $this->filePath = $filePath;
        $this->type = $type;
    }

    // === Core Fields ===

    public function id(): ?string
    {
        return $this->frontmatter['id'] ?? null;
    }

    public function title(): string
    {
        return $this->frontmatter['title'] ?? '';
    }

    public function slug(): string
    {
        return $this->frontmatter['slug'] ?? '';
    }

    public function status(): string
    {
        return $this->frontmatter['status'] ?? 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status() === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status() === 'draft';
    }

    public function isUnlisted(): bool
    {
        return $this->status() === 'unlisted';
    }

    // === Dates ===

    public function date(): ?\DateTimeImmutable
    {
        if (!array_key_exists('date', $this->dateCache)) {
            $this->dateCache['date'] = $this->parseDate($this->frontmatter['date'] ?? null);
        }
        return $this->dateCache['date'];
    }

    /**
     * Parse a date value into DateTimeImmutable.
     */
    private function parseDate(mixed $date): ?\DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        if ($date instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($date);
        }

        if (is_int($date)) {
            return (new \DateTimeImmutable())->setTimestamp($date);
        }

        try {
            return new \DateTimeImmutable((string) $date);
        } catch (\Exception) {
            return null;
        }
    }

    public function updated(): ?\DateTimeImmutable
    {
        if (!array_key_exists('updated', $this->dateCache)) {
            $updated = $this->frontmatter['updated'] ?? null;
            if ($updated === null) {
                $this->dateCache['updated'] = $this->date();
            } else {
                $parsed = $this->parseDate($updated);
                $this->dateCache['updated'] = $parsed ?? $this->date();
            }
        }
        return $this->dateCache['updated'];
    }

    // === Content ===

    public function rawContent(): string
    {
        return $this->rawContent;
    }

    public function excerpt(): ?string
    {
        return $this->frontmatter['excerpt'] ?? null;
    }

    /**
     * Whether this item should skip Markdown parsing and render raw HTML.
     * 
     * When true, the body content is treated as HTML and passed through
     * without Markdown processing. Shortcodes and path aliases are still
     * processed.
     * 
     * Security note: This is safe for file-based content since content
     * authors have filesystem access anyway. The admin editor blocks
     * high-risk HTML regardless of this setting.
     */
    public function rawHtml(): bool
    {
        return (bool) ($this->frontmatter['raw_html'] ?? false);
    }

    /**
     * Get the HTML content.
     */
    public function html(): ?string
    {
        return $this->htmlContent;
    }

    /**
     * Return a new Item with the HTML content set.
     * 
     * This maintains immutability - the original item is unchanged.
     */
    public function withHtml(string $html): self
    {
        $clone = clone $this;
        $clone->htmlContent = $html;
        return $clone;
    }

    // === Metadata ===

    public function type(): string
    {
        return $this->type;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function template(): ?string
    {
        return $this->frontmatter['template'] ?? null;
    }

    // === Taxonomies ===

    /**
     * Get taxonomy terms.
     *
     * @param string|null $taxonomy Specific taxonomy or null for all
     * @return array<string>|array<string, array<string>>
     */
    public function terms(?string $taxonomy = null): array
    {
        // Check for explicit 'tax' format
        if (isset($this->frontmatter['tax'])) {
            $allTerms = $this->frontmatter['tax'];
            if ($taxonomy !== null) {
                return $allTerms[$taxonomy] ?? [];
            }
            return $allTerms;
        }

        // Simple format: terms stored directly as keys
        if ($taxonomy !== null) {
            $value = $this->frontmatter[$taxonomy] ?? [];
            return is_array($value) ? $value : [$value];
        }

        // Simple format can't return all terms without knowing which keys are taxonomies
        return [];
    }

    // === SEO ===

    public function metaTitle(): ?string
    {
        return $this->frontmatter['meta_title'] ?? null;
    }

    public function metaDescription(): ?string
    {
        return $this->frontmatter['meta_description'] ?? null;
    }

    public function noindex(): bool
    {
        return (bool) ($this->frontmatter['noindex'] ?? false);
    }

    public function canonical(): ?string
    {
        return $this->frontmatter['canonical'] ?? null;
    }

    public function ogImage(): ?string
    {
        return $this->frontmatter['og_image'] ?? null;
    }

    // === Redirects ===

    /**
     * Get redirect_from URLs for this item.
     *
     * @return array<string>
     */
    public function redirectFrom(): array
    {
        $redirects = $this->frontmatter['redirect_from'] ?? [];
        return is_array($redirects) ? $redirects : [$redirects];
    }

    // === Assets ===

    /**
     * Get per-item CSS assets.
     *
     * @return array<string>
     */
    public function css(): array
    {
        return $this->frontmatter['assets']['css'] ?? [];
    }

    /**
     * Get per-item JS assets.
     *
     * @return array<string>
     */
    public function js(): array
    {
        return $this->frontmatter['assets']['js'] ?? [];
    }

    // === Hierarchy ===

    public function parent(): ?string
    {
        return $this->frontmatter['parent'] ?? null;
    }

    public function order(): int
    {
        return (int) ($this->frontmatter['order'] ?? 0);
    }

    // === Generic Access ===

    /**
     * Get a frontmatter field.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->frontmatter[$key] ?? $default;
    }

    /**
     * Check if a frontmatter field exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->frontmatter);
    }

    /**
     * Get all frontmatter.
     */
    public function frontmatter(): array
    {
        return $this->frontmatter;
    }

    // === Serialization ===

    /**
     * Convert to array for indexing/caching.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'title' => $this->title(),
            'slug' => $this->slug(),
            'status' => $this->status(),
            'type' => $this->type,
            'file_path' => $this->filePath,
            'date' => $this->date()?->format('c'),
            'updated' => $this->updated()?->format('c'),
            'excerpt' => $this->excerpt(),
            'body' => $this->rawContent,
            'template' => $this->template(),
            'parent' => $this->parent(),
            'order' => $this->order(),
            'redirect_from' => $this->redirectFrom(),
            'frontmatter' => $this->frontmatter,
        ];
    }

    /**
     * Create from cached array.
     */
    public static function fromArray(array $data, string $rawContent = ''): self
    {
        return new self(
            $data['frontmatter'] ?? $data,
            $rawContent,
            $data['file_path'] ?? '',
            $data['type'] ?? ''
        );
    }
}
