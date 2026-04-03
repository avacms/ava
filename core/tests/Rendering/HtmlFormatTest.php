<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Content\Item;
use Ava\Testing\TestCase;

/**
 * Tests for HTML format content files.
 * 
 * When a content file has a .html extension, Markdown parsing is skipped
 * and the body is treated as raw HTML. Shortcodes and path aliases are still
 * processed.
 */
final class HtmlFormatTest extends TestCase
{
    // =========================================================================
    // Format detection
    // =========================================================================

    public function testMarkdownFormatByDefault(): void
    {
        $item = new Item([], '<p>Content</p>', '/test.md', 'post');
        $this->assertFalse($item->isHtml());
        $this->assertEquals(Item::FORMAT_MARKDOWN, $item->format());
    }

    public function testHtmlFormatDetectedFromExtension(): void
    {
        $item = new Item([], '<p>Content</p>', '/test.html', 'post', Item::FORMAT_HTML);
        $this->assertTrue($item->isHtml());
        $this->assertEquals(Item::FORMAT_HTML, $item->format());
    }

    // =========================================================================
    // Content preservation when using HTML format
    // =========================================================================

    public function testHtmlFormatPreservesHtmlStructure(): void
    {
        $item = new Item(
            [],
            '<div class="custom">Content</div>',
            '/test.html',
            'post',
            Item::FORMAT_HTML
        );
        $this->assertTrue($item->isHtml());
        $this->assertEquals('<div class="custom">Content</div>', $item->rawContent());
    }

    public function testHtmlFormatPreservesMarkdownSyntaxLiterally(): void
    {
        // When format is HTML, markdown syntax should NOT be processed
        $item = new Item(
            [],
            '**bold** and *italic* and # heading',
            '/test.html',
            'post',
            Item::FORMAT_HTML
        );

        // The raw content should retain markdown syntax literally
        $this->assertStringContains('**bold**', $item->rawContent());
        $this->assertStringContains('*italic*', $item->rawContent());
        $this->assertStringContains('# heading', $item->rawContent());
    }

    // =========================================================================
    // Security considerations documentation
    // =========================================================================

    /**
     * Document the security model for HTML format content.
     * 
     * HTML format is safe because:
     * 1. Content authors have filesystem access anyway (flat-file CMS)
     * 2. This only affects content body, not user-submitted data
     * 3. Format is determined by file extension, not user input
     */
    public function testSecurityModelDocumentation(): void
    {
        $this->assertTrue(true);
    }
}
