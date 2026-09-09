<?php

declare(strict_types=1);

namespace Ava\Shortcodes;

use Ava\Application;
use Ava\Rendering\TemplateHelpers;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Util\RegexHelper;

/**
 * Shortcode Engine
 *
 * Processes shortcodes in content.
 *
 * Syntax:
 * - Inline: [year]
 * - Self-closing: [snippet name="cta" heading="Join"]
 *
 * Shortcodes are processed AFTER markdown rendering.
 * No nested shortcodes in v1.
 */
final class Engine
{
    private const SHORTCODE_EXPRESSION = '\[(?<tag>[a-zA-Z_][a-zA-Z0-9_-]*)(?<attributes>(?:\s+[^\]]+)?)\](?:(?<content>[^[]*)\[\/\k<tag>\])?';

    /** Regex pattern for matching shortcodes (self-closing and paired) */
    private const SHORTCODE_PATTERN = '/<!--[\s\S]*?-->(*SKIP)(*F)|' . self::SHORTCODE_EXPRESSION . '/';

    /** Also captures a paragraph containing only one shortcode. */
    private const MARKDOWN_SHORTCODE_PATTERN = '/<!--[\s\S]*?-->(*SKIP)(*F)|(?:(?<paragraph_open><p>[ \t]*))?'
        . self::SHORTCODE_EXPRESSION
        . '(?(paragraph_open)(?<paragraph_close>[ \t]*<\/p>))/';

    private Application $app;

    /** @var array<string, callable> */
    private array $shortcodes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerBuiltins();
    }

    /**
     * Register a shortcode handler.
     */
    public function register(string $tag, callable $handler): void
    {
        $this->shortcodes[strtolower($tag)] = $handler;
    }

    /**
     * Process shortcodes in content.
     *
     * @param bool $unwrapBlockParagraphs Remove Markdown-generated paragraphs
     *                                    around standalone block shortcodes
     */
    public function process(string $content, bool $unwrapBlockParagraphs = false): string
    {
        $pattern = $unwrapBlockParagraphs ? self::MARKDOWN_SHORTCODE_PATTERN : self::SHORTCODE_PATTERN;
        return $this->processMatches($content, $pattern, $unwrapBlockParagraphs);
    }

    private function processMatches(string $content, string $pattern, bool $unwrapBlockParagraphs): string
    {
        if ($content === '' || !str_contains($content, '[')) {
            return $content;
        }

        return preg_replace_callback($pattern, function ($matches) use ($unwrapBlockParagraphs) {
            $tag = strtolower($matches['tag']);
            $attrString = $matches['attributes'];
            $innerContent = $matches['content'] ?? null;

            if (!isset($this->shortcodes[$tag])) {
                // Unknown shortcode - return as-is
                return $matches[0];
            }

            $attrs = $this->parseAttributes($attrString);

            try {
                $result = ($this->shortcodes[$tag])($attrs, $innerContent, $tag);
                $result ??= '';
            } catch (\Throwable $e) {
                // Log error but don't break the page
                error_log("Shortcode error [{$tag}]: " . $e->getMessage());
                $result = '<!-- shortcode error: ' . htmlspecialchars($tag) . ' -->';
            }

            if ($unwrapBlockParagraphs && !empty($matches['paragraph_open'])) {
                if ($this->startsWithBlockHtml($result)) {
                    return $result;
                }

                return $matches['paragraph_open'] . $result . $matches['paragraph_close'];
            }

            return $result;
        }, $content);
    }

    private function startsWithBlockHtml(string $content): bool
    {
        $content = ltrim($content);

        for ($type = HtmlBlock::TYPE_1_CODE_CONTAINER; $type <= HtmlBlock::TYPE_6_BLOCK_ELEMENT; $type++) {
            if (preg_match(RegexHelper::getHtmlBlockOpenRegex($type), $content) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse shortcode attributes.
     */
    private function parseAttributes(string $attrString): array
    {
        $attrs = [];
        $attrString = trim($attrString);

        if ($attrString === '') {
            return $attrs;
        }
        // Decode HTML entities (markdown may have encoded quotes)
        $attrString = html_entity_decode($attrString, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Pattern for key="value" or key='value' or key=value or just key
        $pattern = '/([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))|([a-zA-Z_][a-zA-Z0-9_-]*)/';

        preg_match_all($pattern, $attrString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!empty($match[5])) {
                // Boolean attribute (just key, no value)
                $attrs[$match[5]] = true;
            } else {
                // Key-value attribute
                $key = $match[1];
                $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
                $attrs[$key] = $value;
            }
        }

        return $attrs;
    }

    /**
     * Register built-in shortcodes.
     */
    private function registerBuiltins(): void
    {
        // [year] - current year
        $this->register('year', fn() => date('Y'));

        // [date format="Y-m-d"] - current date
        $this->register('date', function (array $attrs) {
            $format = $attrs['format'] ?? 'Y-m-d';
            return date($format);
        });

        // [site_name] - site name from config
        $this->register('site_name', fn() => $this->app->config('site.name', ''));

        // [site_url] - site URL from config
        $this->register('site_url', fn() => $this->app->config('site.base_url', ''));

        // [email]address@example.com[/email] - obfuscated email
        $this->register('email', function (array $attrs, ?string $content) {
            if ($content === null) {
                return '';
            }
            $email = trim($content);
            // Simple obfuscation - encode characters
            $encoded = '';
            for ($i = 0; $i < strlen($email); $i++) {
                $encoded .= '&#' . ord($email[$i]) . ';';
            }
            $mailto = '&#109;&#97;&#105;&#108;&#116;&#111;&#58;';
            return '<a href="' . $mailto . $encoded . '">' . $encoded . '</a>';
        });

        // [snippet name="..."] - load PHP snippet
        $this->register('snippet', function (array $attrs, ?string $content) {
            $name = $attrs['name'] ?? null;
            if ($name === null) {
                return '<!-- snippet: missing name -->';
            }

            return $this->loadSnippet($name, $attrs, $content);
        });
    }

    /**
     * Load a PHP snippet.
     */
    private function loadSnippet(string $name, array $params, ?string $content): string
    {
        // Security: allowlist-only names (blocks traversal and odd encodings)
        $name = trim($name);
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $name)) {
            return '<!-- snippet: invalid name -->';
        }

        // Check if snippets are allowed
        if (!$this->app->config('security.shortcodes.allow_php_snippets', true)) {
            return '<!-- snippet: disabled -->';
        }

        $snippetDir = rtrim($this->app->configPath('snippets'), '/');
        $snippetPath = $snippetDir . '/' . $name . '.php';

        // Paranoid containment check (in case of unexpected path resolution)
        $realDir = realpath($snippetDir) ?: $snippetDir;
        $realFile = realpath($snippetPath);
        if ($realFile !== false && !str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR)) {
            return '<!-- snippet: invalid path -->';
        }

        if (!file_exists($snippetPath)) {
            return '<!-- snippet not found: ' . htmlspecialchars($name) . ' -->';
        }

        // Build context for snippet
        $context = [
            'params' => $params,
            'content' => $content,
            'ava' => new TemplateHelpers($this->app, $this->app->renderer()),
            'app' => $this->app,
        ];

        // Render snippet in isolation
        // EXTR_SKIP prevents variable collisions
        extract($context, EXTR_SKIP);

        ob_start();
        try {
            include $snippetPath;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log("Snippet error [{$name}]: " . $e->getMessage());
            return '<!-- snippet error: ' . htmlspecialchars($name) . ' -->';
        }
    }

    /**
     * Get all registered shortcode tags.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return array_keys($this->shortcodes);
    }

    /**
     * Check if a shortcode is registered.
     */
    public function has(string $tag): bool
    {
        return isset($this->shortcodes[strtolower($tag)]);
    }
}
