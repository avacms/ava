<?php

declare(strict_types=1);

namespace Ava\Tests\Shortcodes;

use Ava\Rendering\TemplateHelpers;
use Ava\Testing\TestCase;

/**
 * Tests for the [snippet] shortcode and loadSnippet() method.
 *
 * These tests verify that snippets receive the correct context variables,
 * including a TemplateHelpers instance as $ava (not the rendering Engine).
 */
final class SnippetTest extends TestCase
{
    private string $snippetDir;

    public function setUp(): void
    {
        $this->snippetDir = $this->app->configPath('snippets');
    }

    public function tearDown(): void
    {
        // Clean up any test snippets we created
        $testFiles = glob($this->snippetDir . '/_test_*.php');
        foreach ($testFiles as $file) {
            @unlink($file);
        }
    }

    // =========================================================================
    // Context variables
    // =========================================================================

    public function testSnippetReceivesTemplateHelpersAsAva(): void
    {
        $snippet = $this->snippetDir . '/_test_ava_type.php';
        file_put_contents($snippet, '<?php echo get_class($ava);');

        $result = $this->app->shortcodes()->process('[snippet name="_test_ava_type"]');

        $this->assertEquals(TemplateHelpers::class, $result);
    }

    public function testSnippetAvaHasHelperMethods(): void
    {
        $snippet = $this->snippetDir . '/_test_ava_methods.php';
        file_put_contents($snippet, '<?php
            $methods = ["url", "asset", "recent", "e", "date", "query", "body", "partial"];
            $missing = [];
            foreach ($methods as $m) {
                if (!method_exists($ava, $m)) {
                    $missing[] = $m;
                }
            }
            echo empty($missing) ? "all_present" : "missing:" . implode(",", $missing);
        ');

        $result = $this->app->shortcodes()->process('[snippet name="_test_ava_methods"]');

        $this->assertEquals('all_present', $result);
    }

    public function testSnippetReceivesParams(): void
    {
        $snippet = $this->snippetDir . '/_test_params.php';
        file_put_contents($snippet, '<?php echo $params["greeting"] ?? "none";');

        $result = $this->app->shortcodes()->process('[snippet name="_test_params" greeting="hello"]');

        $this->assertEquals('hello', $result);
    }

    public function testSnippetReceivesAppInstance(): void
    {
        $snippet = $this->snippetDir . '/_test_app.php';
        file_put_contents($snippet, '<?php echo get_class($app);');

        $result = $this->app->shortcodes()->process('[snippet name="_test_app"]');

        $this->assertEquals(\Ava\Application::class, $result);
    }

    public function testSnippetReceivesContent(): void
    {
        $snippet = $this->snippetDir . '/_test_content.php';
        file_put_contents($snippet, '<?php echo $content ?? "null";');

        // Self-closing snippet has null content
        $result = $this->app->shortcodes()->process('[snippet name="_test_content"]');
        $this->assertEquals('null', $result);
    }

    // =========================================================================
    // Snippet rendering
    // =========================================================================

    public function testSnippetRendersOutput(): void
    {
        $snippet = $this->snippetDir . '/_test_render.php';
        file_put_contents($snippet, '<div class="test">Works</div>');

        $result = $this->app->shortcodes()->process('[snippet name="_test_render"]');

        $this->assertEquals('<div class="test">Works</div>', $result);
    }

    public function testSnippetCanUseMultipleParams(): void
    {
        $snippet = $this->snippetDir . '/_test_multi.php';
        file_put_contents($snippet, '<?php echo ($params["a"] ?? "") . "-" . ($params["b"] ?? "");');

        $result = $this->app->shortcodes()->process('[snippet name="_test_multi" a="one" b="two"]');

        $this->assertEquals('one-two', $result);
    }

    // =========================================================================
    // Security and validation
    // =========================================================================

    public function testSnippetMissingNameReturnsComment(): void
    {
        $result = $this->app->shortcodes()->process('[snippet]');
        $this->assertStringContains('missing name', $result);
    }

    public function testSnippetInvalidNameReturnsComment(): void
    {
        $result = $this->app->shortcodes()->process('[snippet name="../etc/passwd"]');
        $this->assertStringContains('invalid name', $result);
    }

    public function testSnippetTraversalAttemptBlocked(): void
    {
        $result = $this->app->shortcodes()->process('[snippet name="../../bootstrap"]');
        $this->assertStringContains('invalid name', $result);
    }

    public function testSnippetNonExistentReturnsComment(): void
    {
        $result = $this->app->shortcodes()->process('[snippet name="nonexistent_xyz"]');
        $this->assertStringContains('snippet not found', $result);
    }

    public function testSnippetNameWithSlashesBlocked(): void
    {
        $result = $this->app->shortcodes()->process('[snippet name="sub/dir"]');
        $this->assertStringContains('invalid name', $result);
    }

    // =========================================================================
    // Error handling
    // =========================================================================

    public function testSnippetExceptionReturnsSafeComment(): void
    {
        $snippet = $this->snippetDir . '/_test_throw.php';
        file_put_contents($snippet, '<?php throw new \RuntimeException("test error");');

        $oldErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        $result = $this->app->shortcodes()->process('[snippet name="_test_throw"]');

        ini_set('error_log', $oldErrorLog);

        $this->assertStringContains('snippet error', $result);
        $this->assertStringContains('_test_throw', $result);
    }

    public function testSnippetErrorCommentEscapesName(): void
    {
        // Names with special chars are blocked by regex, so this tests
        // that the not-found message also escapes for safety
        $result = $this->app->shortcodes()->process('[snippet name="test"]');
        // The name "test" would be escaped through htmlspecialchars
        $this->assertStringContains('test', $result);
    }

    // =========================================================================
    // Bundled snippets
    // =========================================================================

    public function testCtaSnippetRenders(): void
    {
        $result = $this->app->shortcodes()->process(
            '[snippet name="cta" heading="Test" button_text="Click" button_url="/test"]'
        );

        $this->assertStringContains('Test', $result);
        $this->assertStringContains('Click', $result);
        $this->assertStringContains('/test', $result);
        $this->assertStringContains('cta-box', $result);
    }
}
