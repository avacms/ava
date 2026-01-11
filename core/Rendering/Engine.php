<?php

declare(strict_types=1);

namespace Ava\Rendering;

use Ava\Application;
use Ava\Content\Item;
use Ava\Plugins\Hooks;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
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
     * Returns the rendered HTML. The Item's html() is checked for cached content.
     * Use withHtml() on the item if you need to cache the result.
     */
    public function renderItem(Item $item): string
    {
        // Check if already rendered
        if ($item->html() !== null) {
            return $item->html();
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
     */
    private function resolveTemplate(string $template): ?string
    {
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

        // Fallback templates
        $candidates[] = $themePath . '/templates/single.php';
        $candidates[] = $themePath . '/templates/index.php';

        // Check partials directory too
        $candidates[] = $themePath . '/partials/' . $template . '.php';

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
            $config = [
                'html_input' => $this->app->config('content.markdown.allow_html', true)
                    ? 'allow'
                    : 'strip',
                'allow_unsafe_links' => false,
            ];

            $environment = new Environment($config);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());

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

        foreach ($aliases as $alias => $path) {
            $content = str_replace($alias, $path, $content);
        }

        return $content;
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
