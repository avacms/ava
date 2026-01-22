<?php

declare(strict_types=1);

namespace Ava\Rendering;

use Ava\Application;
use Ava\Content\Item;
use Ava\Plugins\Hooks;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Rendering Engine
 *
 * Handles template resolution and rendering.
 */
final class Engine
{
    private Application $app;
    private ?MarkdownConverter $markdown = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Render a template with context.
     */
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
    public function renderMarkdown(string $markdown): string
    {
        $converter = $this->getMarkdownConverter();
        $html = $converter->convert($markdown)->getContent();

        // Apply shortcodes after markdown
        $html = $this->app->shortcodes()->process($html);

        // Expand path aliases
        $html = $this->expandAliases($html);

        return $html;
    }

    /**
     * Render a content item (process its Markdown body).
     * 
     * Returns the rendered HTML. Uses pre-rendered cache if available,
     * otherwise renders markdown on demand.
     * 
     * If the item has `raw_html: true` in frontmatter, Markdown parsing
     * is skipped and the body is treated as raw HTML. Shortcodes and
     * path aliases are still processed.
     * 
     * @param Item $item Content item to render
     * @param string|null $contentKey Optional content key for pre-render cache lookup
     */
    public function renderItem(Item $item, ?string $contentKey = null): string
    {
        // Check if already rendered (in-memory)
        if ($item->html() !== null) {
            return $item->html();
        }

        // If raw_html is enabled AND allowed by config, skip Markdown parsing
        // but still process shortcodes and expand path aliases.
        // When allow_raw_html is false, we fall back to Markdown rendering.
        if ($item->rawHtml() && $this->app->config('security.allow_raw_html', false)) {
            $html = $this->app->shortcodes()->process($item->rawContent());
            return $this->expandAliases($html);
        }

        // Try pre-rendered HTML cache (if enabled during rebuild)
        if ($contentKey !== null) {
            $prerendered = $this->app->repository()->getPrerenderedHtml($item->type(), $contentKey);
            if ($prerendered !== null) {
                // Still need to process shortcodes (they weren't processed during pre-render)
                return $this->app->shortcodes()->process($prerendered);
            }
        }

        return $this->renderMarkdown($item->rawContent());
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
        $theme = $this->app->config('theme', 'default');
        $context['theme'] = [
            'name' => $theme,
            'path' => $this->app->configPath('themes') . '/' . $theme,
            'url' => '/themes/' . $theme,
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

        $theme = $this->app->config('theme', 'default');
        $themePath = $this->app->configPath('themes') . '/' . $theme;

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
        extract($context);

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
     * Get or create the Markdown converter.
     */
    private function getMarkdownConverter(): MarkdownConverter
    {
        if ($this->markdown === null) {
            $enableHeadingIds = $this->app->config('content.markdown.heading_ids', true);

            $config = [
                'html_input' => $this->app->config('content.markdown.allow_html', true)
                    ? 'allow'
                    : 'strip',
                'allow_unsafe_links' => false,
                // GFM registers the DisallowedRawHtmlExtension; configure it so filesystem content
                // can opt into allowing tags like iframe while still letting users block others.
                'disallowed_raw_html' => [
                    'disallowed_tags' => $this->app->config('content.markdown.disallowed_tags', []),
                ],
            ];

            if ($enableHeadingIds) {
                // Add ids to headings for in-page anchors without visible permalink markup
                $config['heading_permalink'] = [
                    'apply_id_to_heading' => true,
                    'insert' => 'none', // Do not inject extra anchor markup
                    'min_heading_level' => 1,
                    'max_heading_level' => 6,
                ];
            }

            $environment = new Environment($config);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            if ($enableHeadingIds) {
                $environment->addExtension(new HeadingPermalinkExtension());
            }

            // Allow plugins to add extensions
            Hooks::doAction('markdown.configure', $environment);

            $this->markdown = new MarkdownConverter($environment);
        }

        return $this->markdown;
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
        $theme = $this->app->config('theme', 'default');
        $themePath = $this->app->configPath('themes') . '/' . $theme;

        $partialPath = $themePath . '/partials/' . $name . '.php';

        if (!file_exists($partialPath)) {
            throw new \RuntimeException("Partial not found: {$name}");
        }

        // Merge with current context
        $context = $this->buildContext($data);

        return $this->renderTemplate($partialPath, $context);
    }
}
