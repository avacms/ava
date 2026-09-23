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
    public const FORMAT_MARKDOWN = 'markdown';
    public const FORMAT_HTML = 'html';

    private array $frontmatter;
    private string $rawContent;
    private ?string $htmlContent = null;
    private string $filePath;
    private string $type;
    private string $format;
    private bool $hasFrontmatter;
    private array $defaultedFields;

    /** @var array<string, \DateTimeImmutable|null|false> Cached parsed dates (false = not yet computed) */
    private array $dateCache = [];

    public function __construct(
        array $frontmatter,
        string $rawContent,
        string $filePath,
        string $type,
        string $format = self::FORMAT_MARKDOWN,
        bool $hasFrontmatter = true,
        array $defaultedFields = []
    ) {
        $this->frontmatter = $frontmatter;
        $this->rawContent = $rawContent;
        $this->filePath = $filePath;
        $this->type = $type;
        $this->format = $format;
        $this->hasFrontmatter = $hasFrontmatter;
        $this->defaultedFields = $defaultedFields;
    }

    /**
     * Frontmatter fields that must hold text. YAML turns `title: 1984` into an
     * int and `title: 2024-01-01` into a timestamp, so accessors coerce scalars
     * rather than letting one unquoted value throw a TypeError at render time.
     */
    public const STRING_FIELDS = [
        'id', 'title', 'slug', 'status', 'excerpt', 'template', 'meta_title',
        'meta_description', 'canonical', 'og_image', 'featured_image', 'parent',
    ];

    // === Core Fields ===

    public function id(): ?string
    {
        return $this->nullableString('id');
    }

    public function title(): string
    {
        return $this->nullableString('title') ?? '';
    }

    public function slug(): string
    {
        return $this->nullableString('slug') ?? '';
    }

    public function contentKey(): string
    {
        return $this->nullableString('content_key') ?? $this->slug();
    }

    public function status(): string
    {
        return $this->nullableString('status') ?? 'draft';
    }

    /**
     * Read a text field, accepting any scalar YAML produced for it.
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->frontmatter[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
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
        return $this->nullableString('excerpt');
    }

    /**
     * Get the content format.
     * 
     * Determined by file extension: .md = markdown, .html = html.
     * HTML format skips Markdown parsing — the body is treated as raw HTML.
     * Shortcodes and path aliases are still processed for both formats.
     */
    public function format(): string
    {
        return $this->format;
    }

    /**
     * Whether this item is HTML format (skip Markdown parsing).
     */
    public function isHtml(): bool
    {
        return $this->format === self::FORMAT_HTML;
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

    public function withContentKey(string $contentKey): self
    {
        $clone = clone $this;
        $clone->frontmatter['content_key'] = $contentKey;
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

    public function hasFrontmatter(): bool
    {
        return $this->hasFrontmatter;
    }

    public function wasDefaulted(string $field): bool
    {
        return in_array($field, $this->defaultedFields, true);
    }

    public function template(): ?string
    {
        return $this->nullableString('template');
    }

    /**
     * Options for Application::markdown(), from the item's frontmatter.
     */
    public function markdownOptions(): array
    {
        $extensions = $this->frontmatter['markdown_extensions'] ?? [];

        return is_array($extensions) && $extensions !== [] ? ['markdown_extensions' => $extensions] : [];
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
        if (is_array($this->frontmatter['tax'] ?? null)) {
            $allTerms = $this->frontmatter['tax'];
            if ($taxonomy !== null) {
                return self::stringList($allTerms[$taxonomy] ?? []);
            }
            return array_map(self::stringList(...), $allTerms);
        }

        // Simple format: terms stored directly as keys
        if ($taxonomy !== null) {
            return self::stringList($this->frontmatter[$taxonomy] ?? []);
        }

        // Simple format can't return all terms without knowing which keys are taxonomies
        return [];
    }

    // === SEO ===

    public function metaTitle(): ?string
    {
        return $this->nullableString('meta_title');
    }

    public function metaDescription(): ?string
    {
        return $this->nullableString('meta_description');
    }

    public function noindex(): bool
    {
        return (bool) ($this->frontmatter['noindex'] ?? false);
    }

    public function canonical(): ?string
    {
        return $this->nullableString('canonical');
    }

    public function ogImage(): ?string
    {
        return $this->nullableString('og_image') ?? $this->nullableString('featured_image');
    }

    // === Redirects ===

    /**
     * Get redirect_from URLs for this item.
     *
     * @return array<string>
     */
    public function redirectFrom(): array
    {
        return self::stringList($this->frontmatter['redirect_from'] ?? []);
    }

    // === Assets ===

    /**
     * Get per-item CSS assets.
     *
     * @return array<string>
     */
    public function css(): array
    {
        return self::stringList($this->frontmatter['assets']['css'] ?? []);
    }

    /**
     * Get per-item JS assets.
     *
     * @return array<string>
     */
    public function js(): array
    {
        return self::stringList($this->frontmatter['assets']['js'] ?? []);
    }

    // === Hierarchy ===

    public function parent(): ?string
    {
        return $this->nullableString('parent');
    }

    public function order(): int
    {
        $order = $this->frontmatter['order'] ?? 0;

        return is_numeric($order) ? (int) $order : 0;
    }

    /**
     * Normalise a scalar-or-list frontmatter value to a list of strings.
     *
     * @return array<string>
     */
    private static function stringList(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_map(
            'strval',
            array_filter($values, fn($entry) => is_string($entry) || is_int($entry) || is_float($entry))
        ));
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
            'format' => $this->format,
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
        $frontmatter = $data['frontmatter'] ?? $data;
        if (isset($data['content_key'])) {
            $frontmatter['content_key'] = $data['content_key'];
        }

        return new self(
            $frontmatter,
            $rawContent,
            $data['file_path'] ?? '',
            $data['type'] ?? '',
            $data['format'] ?? self::FORMAT_MARKDOWN
        );
    }
}
