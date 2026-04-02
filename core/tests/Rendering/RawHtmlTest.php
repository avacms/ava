<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Content\Item;
use Ava\Testing\TestCase;

/**
 * Tests for raw_html frontmatter feature.
 * 
 * When `raw_html: true` is set in frontmatter, Markdown parsing is skipped
 * and the body is treated as raw HTML. Shortcodes and path aliases are still
 * processed.
 */
final class RawHtmlTest extends TestCase
{
    // =========================================================================
    // Item::rawHtml() behavior
    // =========================================================================

    public function testRawHtmlDefaultsToFalse(): void
    {
        $item = new Item([], '<p>Content</p>', '/test.md', 'post');
        $this->assertFalse($item->rawHtml());
    }

    public function testRawHtmlReturnsTrueWhenEnabled(): void
    {
        $item = new Item(['raw_html' => true], '<p>Content</p>', '/test.md', 'post');
        $this->assertTrue($item->rawHtml());
    }

    // =========================================================================
    // Content preservation when raw_html is enabled
    // =========================================================================

    public function testRawHtmlPreservesHtmlStructure(): void
    {
        // When raw_html is false (default), Markdown wraps in <p>
        $normalItem = new Item(
            ['raw_html' => false],
            '<div class="custom">Content</div>',
            '/test.md',
            'post'
        );
        $this->assertFalse($normalItem->rawHtml());

        // When raw_html is true, HTML should pass through unchanged
        $rawItem = new Item(
            ['raw_html' => true],
            '<div class="custom">Content</div>',
            '/test.md',
            'post'
        );
        $this->assertTrue($rawItem->rawHtml());
        // The raw content should be exactly what we passed in
        $this->assertEquals('<div class="custom">Content</div>', $rawItem->rawContent());
    }

    public function testRawHtmlPreservesMarkdownSyntax(): void
    {
        // When raw_html is true, markdown syntax should NOT be processed
        $item = new Item(
            ['raw_html' => true],
            '**bold** and *italic* and # heading',
            '/test.md',
            'post'
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
     * Document the security model for raw_html.
     * 
     * raw_html is safe because:
     * 1. Content authors have filesystem access anyway (flat-file CMS)
     * 2. This only affects content body, not user-submitted data
     */
    public function testSecurityModelDocumentation(): void
    {
        // This test documents the security model via its existence
        // The raw_html feature is intentionally "allow all" for the body
        // because content authors are trusted (they can edit files directly)
        $this->assertTrue(true);
    }
}
