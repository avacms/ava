<?php

declare(strict_types=1);

namespace Ava\Rendering;

use Ava\Application;
use Ava\Content\Item;
use Ava\Http\ThemeAssets;
use Ava\Plugins\Hooks;

/**
 * Rendering Engine
 *
 * Handles template resolution and rendering.
 */
final class Engine
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function render(string $template, array $context = []): string
    {
        // Build full context
        $context = $this->buildContext($context);

        // Resolve template path
        $templatePath = $this->resolveTemplate($template);

        // Special handling for 404 - use built-in fallback if theme doesn't have one
        if ($templatePath === null && $template === '404') {
            $requestedPath = isset($context['request']) ? $context['request']->path() : null;
            return ErrorPages::render404($requestedPath);
        }

        if ($templatePath === null) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        // Render the template
        return $this->renderTemplate($templatePath, $context);
    }

    /**
     * Render Markdown to HTML.
     */
    public function renderMarkdown(string $markdown, array $options = []): string
    {
        return $this->finishMarkdownHtml(
            $this->getMarkdownConverter($options)->convert($markdown)->getContent()
        );
    }

    /**
     * Render a content item (process its body).
     *
     * Uses HTML rendered at build time when it was made from this exact
     * source, otherwise renders the Markdown now. Both paths finish through
     * finishMarkdownHtml(), so they produce the same page.
     *
     * HTML format items (.html files) skip Markdown parsing — the body
     * is treated as raw HTML. Shortcodes and path aliases are still processed.
     *
     * @param string|null $contentKey Deprecated; the item's own content key is used.
     */
    public function renderItem(Item $item, ?string $contentKey = null): string
    {
        // Check if already rendered (in-memory)
        if ($item->html() !== null) {
            return $item->html();
        }

        // HTML format: skip Markdown parsing, still process shortcodes and aliases
        if ($item->isHtml()) {
            $html = $this->app->shortcodes()->process($item->rawContent());
            return $this->expandAliases($html);
        }

        if ($contentKey !== null && $contentKey !== $item->contentKey()) {
            $item = $item->withContentKey($contentKey);
        }

        $prerendered = $this->app->repository()->prerenderedHtml($item);
        if ($prerendered !== null) {
            return $this->finishMarkdownHtml($prerendered);
        }

        return $this->renderMarkdown($item->rawContent(), $item->markdownOptions());
    }

    /**
     * Steps applied to Markdown output at render time: shortcodes (which may
     * depend on the request) with standalone block output unwrapped from its
     * paragraph, then path aliases, so shortcode output gets them too.
     */
    private function finishMarkdownHtml(string $html): string
    {
        return $this->expandAliases($this->app->shortcodes()->process($html, true));
    }

    /**
     * Build the full context for templates.
     */
    private function buildContext(array $context): array
    {
        // Add site context
        $context['site'] = [
            'name' => $this->app->config('site.name'),
            'url' => $this->app->config('site.base_url'),
            'timezone' => $this->app->config('site.timezone'),
        ];

        // Add theme context
        $theme = $this->app->themeName();
        $context['theme'] = [
            'name' => $theme,
            'path' => $this->app->configPath('themes') . '/' . $theme,
            'url' => rtrim(ThemeAssets::PREFIX, '/'),
        ];

        // Add rendering helpers
        $context['ava'] = new TemplateHelpers($this->app, $this);

        // Allow hooks to modify context
        $context = Hooks::apply('render.context', $context);

        return $context;
    }

    /**
     * Resolve a template to an absolute path.
     * 
     * Security: Template names from content frontmatter are validated
     * to prevent path traversal attacks.
     */
    private function resolveTemplate(string $template): ?string
    {
        // Security: Validate template name to prevent path traversal
        // Even though filesystem content is trusted, this is defense-in-depth
        if (
            str_contains($template, '..') ||
            str_contains($template, '/') ||
            str_contains($template, '\\') ||
            str_contains($template, "\0")
        ) {
            return null;
        }

        $themePath = $this->app->configPath('themes') . '/' . $this->app->themeName();

        // Templates to try (in order of preference)
        $candidates = [];

        // Exact template name
        if (str_ends_with($template, '.php')) {
            $candidates[] = $themePath . '/templates/' . $template;
        } else {
            $candidates[] = $themePath . '/templates/' . $template . '.php';
        }

        // For error templates (404, 500, etc.), don't fall back to other templates
        // Let the render() method handle the fallback to built-in error pages
        if (!in_array($template, ['404', '500', '503'])) {
            // Fallback templates for content
            $candidates[] = $themePath . '/templates/single.php';
            $candidates[] = $themePath . '/templates/index.php';

            // Check partials directory too
            $candidates[] = $themePath . '/partials/' . $template . '.php';
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Render a template file.
     */
    private function renderTemplate(string $templatePath, array $context): string
    {
        // Extract context variables for the template
        // EXTR_SKIP prevents variable collisions in nested template contexts
        extract($context, EXTR_SKIP);

        // Capture output
        ob_start();

        try {
            include $templatePath;
            $output = ob_get_clean();
            
            // Allow hooks to modify final output
            return Hooks::apply('render.output', $output, $templatePath, $context);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Get the shared Markdown converter.
     */
    private function getMarkdownConverter(array $options = []): \League\CommonMark\MarkdownConverter
    {
        return $this->app->markdown($options);
    }

    /**
     * Expand path aliases in content.
     */
    public function expandAliases(string $content): string
    {
        $aliases = $this->app->config('paths.aliases', []);

        if (empty($aliases)) {
            return $content;
        }

        // Single-pass replacement is more efficient than multiple str_replace() calls
        return strtr($content, $aliases);
    }

    /**
     * Render a partial template.
     */
    public function partial(string $name, array $data = []): string
    {
        $partialPath = $this->app->configPath('themes') . '/' . $this->app->themeName() . '/partials/' . $name . '.php';

        if (!file_exists($partialPath)) {
            throw new \RuntimeException("Partial not found: {$name}");
        }

        // Merge with current context
        $context = $this->buildContext($data);

        return $this->renderTemplate($partialPath, $context);
    }
}
