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
        $date = $this->frontmatter['date'] ?? null;
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
        $updated = $this->frontmatter['updated'] ?? null;
        if ($updated === null) {
            return $this->date();
        }

        if ($updated instanceof \DateTimeImmutable) {
            return $updated;
        }

        if ($updated instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($updated);
        }

        if (is_int($updated)) {
            return (new \DateTimeImmutable())->setTimestamp($updated);
        }

        try {
            return new \DateTimeImmutable((string) $updated);
        } catch (\Exception) {
            return $this->date();
        }
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

        // Return empty for all - would need taxonomy config to know keys
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
