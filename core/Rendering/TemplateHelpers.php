<?php

declare(strict_types=1);

namespace Ava\Rendering;

use Ava\Application;
use Ava\Content\Item;
use Ava\Content\Query;

/**
 * Template Helpers
 *
 * Helper functions available in templates via $ava variable.
 */
final class TemplateHelpers
{
    private Application $app;
    private Engine $engine;

    public function __construct(Application $app, Engine $engine)
    {
        $this->app = $app;
        $this->engine = $engine;
    }

    // -------------------------------------------------------------------------
    // Content Rendering
    // -------------------------------------------------------------------------

    /**
     * Render a content item's body to HTML.
     */
    public function content(Item $item): string
    {
        return $this->engine->renderItem($item);
    }

    /**
     * Render Markdown to HTML.
     */
    public function markdown(string $markdown): string
    {
        return $this->engine->renderMarkdown($markdown);
    }

    /**
     * Render a partial template.
     */
    public function partial(string $name, array $data = []): string
    {
        return $this->engine->partial($name, $data);
    }

    /**
     * Expand path aliases in a string.
     */
    public function expand(string $content): string
    {
        return $this->engine->expandAliases($content);
    }

    // -------------------------------------------------------------------------
    // URL Helpers
    // -------------------------------------------------------------------------

    /**
     * Get URL for a content item.
     */
    public function url(string $type, string $slug): ?string
    {
        return $this->app->router()->urlFor($type, $slug);
    }

    /**
     * Get URL for a taxonomy term.
     */
    public function termUrl(string $taxonomy, string $term): ?string
    {
        return $this->app->router()->urlForTerm($taxonomy, $term);
    }

    /**
     * Get the site base URL.
     */
    public function baseUrl(): string
    {
        return $this->app->config('site.base_url', '');
    }

    /**
     * Build a full URL from a path.
     */
    public function fullUrl(string $path): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Get asset URL (with cache busting if file exists).
     * 
     * For theme assets, use: $ava->asset('style.css') or $ava->asset('js/app.js')
     * For public assets, use: $ava->asset('/assets/file.css') (leading slash)
     */
    public function asset(string $path): string
    {
        // If path starts with /, it's a public asset
        if (str_starts_with($path, '/')) {
            $fullPath = $this->app->path('public/' . ltrim($path, '/'));

            if (file_exists($fullPath)) {
                $mtime = filemtime($fullPath);
                return $path . '?v=' . $mtime;
            }

            return $path;
        }

        // Otherwise, it's a theme asset
        $theme = $this->app->config('theme', 'default');
        $themePath = $this->app->configPath('themes') . '/' . $theme . '/assets/' . $path;

        if (file_exists($themePath)) {
            $mtime = filemtime($themePath);
            return '/theme/' . $path . '?v=' . $mtime;
        }

        return '/theme/' . $path;
    }

    /**
     * Get theme asset URL explicitly.
     */
    public function themeAsset(string $path): string
    {
        $theme = $this->app->config('theme', 'default');
        $themePath = $this->app->configPath('themes') . '/' . $theme . '/assets/' . ltrim($path, '/');

        if (file_exists($themePath)) {
            $mtime = filemtime($themePath);
            return '/theme/' . ltrim($path, '/') . '?v=' . $mtime;
        }

        return '/theme/' . ltrim($path, '/');
    }

    // -------------------------------------------------------------------------
    // Query Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a new query.
     */
    public function query(): Query
    {
        return new Query($this->app);
    }

    /**
     * Get recent items of a type.
     */
    public function recent(string $type, int $count = 5): array
    {
        return $this->query()
            ->type($type)
            ->published()
            ->orderBy('date', 'desc')
            ->perPage($count)
            ->get();
    }

    /**
     * Get a specific content item.
     */
    public function get(string $type, string $slug): ?Item
    {
        return $this->app->repository()->get($type, $slug);
    }

    /**
     * Get taxonomy terms.
     */
    public function terms(string $taxonomy): array
    {
        return $this->app->repository()->terms($taxonomy);
    }

    // -------------------------------------------------------------------------
    // SEO Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate meta tags for a content item.
     */
    public function metaTags(Item $item): string
    {
        $tags = [];

        // Title
        $title = $item->metaTitle() ?? $item->title();
        $tags[] = '<title>' . $this->escape($title) . '</title>';

        // Description
        $description = $item->metaDescription() ?? $item->excerpt();
        if ($description) {
            $tags[] = '<meta name="description" content="' . $this->escape($description) . '">';
        }

        // Canonical
        $canonical = $item->canonical();
        if ($canonical) {
            $tags[] = '<link rel="canonical" href="' . $this->escape($canonical) . '">';
        }

        // noindex
        if ($item->noindex()) {
            $tags[] = '<meta name="robots" content="noindex">';
        }

        // Open Graph
        $tags[] = '<meta property="og:title" content="' . $this->escape($title) . '">';
        $tags[] = '<meta property="og:type" content="article">';

        if ($description) {
            $tags[] = '<meta property="og:description" content="' . $this->escape($description) . '">';
        }

        if ($item->ogImage()) {
            $image = $this->engine->expandAliases($item->ogImage());
            $tags[] = '<meta property="og:image" content="' . $this->escape($this->fullUrl($image)) . '">';
        }

        return implode("\n    ", $tags);
    }

    /**
     * Generate per-item asset tags.
     */
    public function itemAssets(Item $item): string
    {
        $tags = [];

        foreach ($item->css() as $css) {
            $url = $this->engine->expandAliases($css);
            $tags[] = '<link rel="stylesheet" href="' . $this->escape($url) . '">';
        }

        foreach ($item->js() as $js) {
            $url = $this->engine->expandAliases($js);
            $tags[] = '<script src="' . $this->escape($url) . '" defer></script>';
        }

        return implode("\n    ", $tags);
    }

    // -------------------------------------------------------------------------
    // Pagination Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate pagination HTML.
     */
    public function pagination(Query $query, string $baseUrl = ''): string
    {
        $pagination = $query->pagination();

        if ($pagination['total_pages'] <= 1) {
            return '';
        }

        $html = '<nav class="pagination" aria-label="Pagination">';

        // Previous link
        if ($pagination['has_previous']) {
            $prevUrl = $this->pageUrl($baseUrl, $pagination['current_page'] - 1);
            $html .= '<a href="' . $prevUrl . '" class="pagination-prev">Previous</a> ';
        }

        // Page numbers
        $html .= '<span class="pagination-info">';
        $html .= 'Page ' . $pagination['current_page'] . ' of ' . $pagination['total_pages'];
        $html .= '</span>';

        // Next link
        if ($pagination['has_more']) {
            $nextUrl = $this->pageUrl($baseUrl, $pagination['current_page'] + 1);
            $html .= ' <a href="' . $nextUrl . '" class="pagination-next">Next</a>';
        }

        $html .= '</nav>';

        return $html;
    }

    /**
     * Build a page URL.
     */
    private function pageUrl(string $baseUrl, int $page): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . 'paged=' . $page;
    }

    // -------------------------------------------------------------------------
    // Date Helpers
    // -------------------------------------------------------------------------

    /**
     * Format a date.
     */
    public function date(\DateTimeInterface $date, string $format = 'F j, Y'): string
    {
        return $date->format($format);
    }

    /**
     * Get relative time (e.g., "2 days ago").
     */
    public function ago(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }

        return 'just now';
    }

    // -------------------------------------------------------------------------
    // Utility Helpers
    // -------------------------------------------------------------------------

    /**
     * Escape HTML.
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Alias for escape.
     */
    public function e(string $value): string
    {
        return $this->escape($value);
    }

    /**
     * Truncate text to word limit.
     */
    public function excerpt(string $text, int $words = 55): string
    {
        $text = strip_tags($text);
        $parts = preg_split('/\s+/', $text, $words + 1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) > $words) {
            array_pop($parts);
            return implode(' ', $parts) . '…';
        }

        return implode(' ', $parts);
    }

    /**
     * Get site configuration value.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->app->config($key, $default);
    }

    /**
     * Check if current path matches.
     */
    public function isActive(string $path): bool
    {
        // This would need request context - simplified version
        return false;
    }
}
