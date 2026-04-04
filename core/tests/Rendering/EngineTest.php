<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Rendering\Engine;
use Ava\Rendering\TemplateHelpers;
use Ava\Testing\TestCase;

/**
 * Tests for the Rendering Engine.
 *
 * Covers template context building, template resolution,
 * alias expansion, and partial rendering.
 */
final class EngineTest extends TestCase
{
    private ?Engine $engine = null;
    private string $themePath;
    private array $createdFiles = [];

    public function setUp(): void
    {
        $this->engine = $this->app->renderer();
        $theme = $this->app->config('theme', 'default');
        $this->themePath = $this->app->configPath('themes') . '/' . $theme;
    }

    public function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }
        $this->createdFiles = [];
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = $this->themePath . '/templates/' . $name;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;
    }

    private function createPartial(string $name, string $content): void
    {
        $path = $this->themePath . '/partials/' . $name;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;
    }

    // =========================================================================
    // Template context - $ava type
    // =========================================================================

    public function testTemplateContextAvaIsTemplateHelpers(): void
    {
        $this->createTemplate('_test_ava_ctx.php', '<?php echo get_class($ava);');

        $result = $this->engine->render('_test_ava_ctx');

        $this->assertEquals(TemplateHelpers::class, $result);
    }

    public function testTemplateContextAvaHasHelperMethods(): void
    {
        $this->createTemplate('_test_ava_helpers.php', '<?php
            $methods = ["url", "asset", "recent", "e", "date", "query", "body", "partial"];
            $missing = [];
            foreach ($methods as $m) {
                if (!method_exists($ava, $m)) {
                    $missing[] = $m;
                }
            }
            echo empty($missing) ? "ok" : "missing:" . implode(",", $missing);
        ');

        $result = $this->engine->render('_test_ava_helpers');

        $this->assertEquals('ok', $result);
    }

    // =========================================================================
    // Template context - site and theme
    // =========================================================================

    public function testTemplateContextHasSiteInfo(): void
    {
        $this->createTemplate('_test_site_ctx.php', '<?php echo $site["name"];');

        $result = $this->engine->render('_test_site_ctx');

        $this->assertEquals($this->app->config('site.name'), $result);
    }

    public function testTemplateContextHasThemeInfo(): void
    {
        $this->createTemplate('_test_theme_ctx.php', '<?php echo $theme["name"];');

        $result = $this->engine->render('_test_theme_ctx');

        $this->assertEquals($this->app->config('theme', 'default'), $result);
    }

    public function testTemplateContextPassesThroughCustomVars(): void
    {
        $this->createTemplate('_test_custom_ctx.php', '<?php echo $custom_var ?? "missing";');

        $result = $this->engine->render('_test_custom_ctx', ['custom_var' => 'custom_value']);

        $this->assertEquals('custom_value', $result);
    }

    // =========================================================================
    // Template resolution
    // =========================================================================

    public function testRenderResolvesTemplateByName(): void
    {
        $this->createTemplate('_test_resolve.php', 'resolved');

        $result = $this->engine->render('_test_resolve');

        $this->assertEquals('resolved', $result);
    }

    public function testRenderResolvesTemplateWithPhpExtension(): void
    {
        $this->createTemplate('_test_ext.php', 'with_ext');

        $result = $this->engine->render('_test_ext.php');

        $this->assertEquals('with_ext', $result);
    }

    public function testRenderBlocksPathTraversal(): void
    {
        // Template name with .. should be blocked
        $this->assertThrows(\RuntimeException::class, function () {
            $this->engine->render('../../../etc/passwd');
        });
    }

    public function testRenderBlocksSlashInName(): void
    {
        $this->assertThrows(\RuntimeException::class, function () {
            $this->engine->render('sub/template');
        });
    }

    public function testRender404FallsBackToBuiltIn(): void
    {
        // Rendering a custom '404' that doesn't exist should use built-in
        // The default theme has a 404.php, but let's verify the render works
        $result = $this->engine->render('404');
        $this->assertStringContains('404', $result);
    }

    // =========================================================================
    // Partials
    // =========================================================================

    public function testPartialRendersWithContext(): void
    {
        $this->createPartial('_test_partial.php', '<?php echo get_class($ava);');

        $result = $this->engine->partial('_test_partial');

        // Partials should also get TemplateHelpers as $ava
        $this->assertEquals(TemplateHelpers::class, $result);
    }

    public function testPartialReceivesPassedData(): void
    {
        $this->createPartial('_test_partial_data.php', '<?php echo $items ?? "missing";');

        $result = $this->engine->partial('_test_partial_data', ['items' => 'test_value']);

        $this->assertEquals('test_value', $result);
    }

    public function testPartialThrowsForNonExistent(): void
    {
        $this->assertThrows(\RuntimeException::class, function () {
            $this->engine->partial('nonexistent_partial_xyz');
        });
    }

    // =========================================================================
    // Alias expansion
    // =========================================================================

    public function testExpandAliasesReplacesMediaAlias(): void
    {
        $result = $this->engine->expandAliases('src="@media:image.jpg"');
        $this->assertStringContains('/media/image.jpg', $result);
    }

    public function testExpandAliasesNoAliasesReturnsUnchanged(): void
    {
        $result = $this->engine->expandAliases('plain text without aliases');
        $this->assertEquals('plain text without aliases', $result);
    }

    // =========================================================================
    // Markdown rendering
    // =========================================================================

    public function testRenderMarkdownBasic(): void
    {
        $result = $this->engine->renderMarkdown('**bold**');
        $this->assertStringContains('<strong>bold</strong>', $result);
    }

    public function testRenderMarkdownProcessesShortcodes(): void
    {
        $result = $this->engine->renderMarkdown('Year is [year]');
        $this->assertStringContains(date('Y'), $result);
    }

    // =========================================================================
    // Content rendering
    // =========================================================================

    public function testRenderItemHtmlFormat(): void
    {
        $item = new \Ava\Content\Item(
            ['slug' => 'test', 'title' => 'Test'],
            '<div>Raw HTML [year]</div>',
            '/test.html',
            'page',
            \Ava\Content\Item::FORMAT_HTML
        );

        $result = $this->engine->renderItem($item);

        // Should process shortcodes but not markdown
        $this->assertStringContains('<div>Raw HTML', $result);
        $this->assertStringContains(date('Y'), $result);
    }

    public function testRenderItemCachedHtml(): void
    {
        $item = new \Ava\Content\Item(
            ['slug' => 'cached', 'title' => 'Cached'],
            '**markdown**',
            '/test.md',
            'page'
        );
        $item = $item->withHtml('<p>Already rendered</p>');

        $result = $this->engine->renderItem($item);

        // Should use pre-cached HTML
        $this->assertEquals('<p>Already rendered</p>', $result);
    }
}
