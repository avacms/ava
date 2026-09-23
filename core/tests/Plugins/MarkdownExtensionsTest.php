<?php

declare(strict_types=1);

namespace Ava\Tests\Plugins;

use Ava\Application;
use Ava\Content\Item;
use Ava\Plugins\Hooks;
use Ava\Rendering\Engine;
use Ava\Testing\TestCase;

final class MarkdownExtensionsTest extends TestCase
{
    public function setUp(): void
    {
        Hooks::reset();
    }

    public function tearDown(): void
    {
        Hooks::reset();
    }

    public function testFeaturesAreDisabledByDefault(): void
    {
        $app = $this->createApp();
        $html = $app->markdown()->convert("Text.[^note]\n\n[^note]: Note.")->getContent();

        $this->assertStringNotContains('class="footnotes"', $html);
    }

    public function testFootnotesCanBeEnabled(): void
    {
        $app = $this->createApp(['footnotes' => 'always']);
        $html = $app->markdown()->convert("Text.[^note]\n\n[^note]: Note.")->getContent();

        $this->assertStringContains('class="footnote-ref"', $html);
        $this->assertStringContains('class="footnotes"', $html);
    }

    public function testAdditiveExtensionsCanBeEnabledTogether(): void
    {
        $app = $this->createApp([
            'description_lists' => 'always',
            'highlight' => 'always',
            'smart_punctuation' => 'always',
        ]);
        $html = $app->markdown()->convert("Term\n: Definition\n\n==Marked== \"quoted\"")->getContent();

        $this->assertStringContains('<dl>', $html);
        $this->assertStringContains('<mark>Marked</mark>', $html);
        $this->assertStringNotContains('"quoted"', $html);
    }

    public function testDocumentExtensionsCanBeEnabledTogether(): void
    {
        $app = $this->createApp([
            'external_links' => 'always',
            'attributes' => 'always',
            'table_of_contents' => 'always',
        ]);
        $html = $app->markdown()->convert("# Heading\n\nParagraph\n{.featured}\n\n[External](https://example.com)")->getContent();

        $this->assertStringContains('class="table-of-contents"', $html);
        $this->assertStringContains('class="featured"', $html);
        $this->assertStringContains('rel="noopener noreferrer"', $html);
    }

    public function testOptInCanBeEnabledPerDocument(): void
    {
        $app = $this->createApp(['footnotes' => 'opt_in']);
        $markdown = "Text.[^note]\n\n[^note]: Note.";

        $defaultHtml = $app->markdown()->convert($markdown)->getContent();
        $enabledHtml = $app->markdown(['markdown_extensions' => ['footnotes' => true]])->convert($markdown)->getContent();

        $this->assertStringNotContains('class="footnotes"', $defaultHtml);
        $this->assertStringContains('class="footnotes"', $enabledHtml);
    }

    public function testOptOutCanBeDisabledPerDocument(): void
    {
        $app = $this->createApp(['highlight' => 'opt_out']);

        $defaultHtml = $app->markdown()->convert('==Marked==')->getContent();
        $disabledHtml = $app->markdown(['markdown_extensions' => ['highlight' => false]])->convert('==Marked==')->getContent();

        $this->assertStringContains('<mark>Marked</mark>', $defaultHtml);
        $this->assertStringNotContains('<mark>', $disabledHtml);
    }

    public function testAlwaysAndNeverIgnoreFrontmatter(): void
    {
        $app = $this->createApp([
            'footnotes' => 'always',
            'highlight' => 'never',
        ]);
        $options = ['markdown_extensions' => [
            'footnotes' => false,
            'highlight' => true,
        ]];
        $html = $app->markdown($options)->convert("==Marked==[^note]\n\n[^note]: Note.")->getContent();

        $this->assertStringContains('class="footnotes"', $html);
        $this->assertStringNotContains('<mark>', $html);
    }

    public function testItemFrontmatterControlsOptInFeature(): void
    {
        $app = $this->createApp(['table_of_contents' => 'opt_in']);
        $engine = new Engine($app);
        $item = new Item(
            ['markdown_extensions' => ['table_of_contents' => true]],
            "# Heading\n\n## Child",
            '/test.md',
            'page'
        );

        $html = $engine->renderItem($item);

        $this->assertStringContains('class="table-of-contents"', $html);
    }

    public function testEquivalentFeatureSetsShareConverter(): void
    {
        $app = $this->createApp(['footnotes' => 'opt_in']);
        $first = $app->markdown(['markdown_extensions' => [
            'footnotes' => true,
            'unknown' => true,
        ]]);
        $second = $app->markdown(['markdown_extensions' => ['footnotes' => true]]);

        $this->assertSame($first, $second);
    }

    public function testAutomaticRebuildPreRendersEnabledExtensions(): void
    {
        $relativeDirectory = 'storage/tmp/markdown-extensions-' . bin2hex(random_bytes(6));
        $absoluteDirectory = AVA_ROOT . '/' . $relativeDirectory;
        mkdir($absoluteDirectory . '/content/pages', 0700, true);
        file_put_contents(
            $absoluteDirectory . '/content/pages/footnotes.md',
            "---\ntitle: Footnotes\nstatus: published\n---\nText.[^note]\n\n[^note]: Note.\n"
        );

        try {
            $app = new Application([
                'paths' => [
                    'content' => $relativeDirectory . '/content',
                    'storage' => $relativeDirectory . '/storage',
                    'plugins' => 'app/plugins',
                    'themes' => 'app/themes',
                    'snippets' => 'app/snippets',
                ],
                'plugins' => ['markdown-extensions'],
                'markdown_extensions' => ['footnotes' => 'always'],
                'content_index' => [
                    'mode' => 'auto',
                    'backend' => 'array',
                    'use_igbinary' => false,
                    'prerender_html' => true,
                ],
                'theme' => 'missing-test-theme',
            ]);

            $app->boot();

            $item = $app->repository()->get('page', 'footnotes');
            $this->assertNotNull($item);
            $html = $app->repository()->prerenderedHtml($item);
            $this->assertIsString($html);
            $this->assertStringContains('class="footnote-ref"', $html);
            $this->assertStringContains('class="footnotes"', $html);
        } finally {
            $this->removeDirectory($absoluteDirectory);
        }
    }

    private function createApp(array $config = []): Application
    {
        $app = new Application([
            'paths' => [
                'plugins' => 'app/plugins',
            ],
            'plugins' => ['markdown-extensions'],
            'markdown_extensions' => $config,
        ]);
        $app->loadPlugins();

        return $app;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}