<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Testing\TestCase;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Tests for Markdown rendering via CommonMark.
 * 
 * These tests verify CommonMark behavior independent of the Application.
 */
final class MarkdownTest extends TestCase
{
    private MarkdownConverter $converter;

    public function setUp(): void
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->converter = new MarkdownConverter($environment);
    }

    // =========================================================================
    // Basic Formatting
    // =========================================================================

    public function testParagraph(): void
    {
        $html = $this->render("Hello world");
        $this->assertStringContains('<p>Hello world</p>', $html);
    }

    public function testMultipleParagraphs(): void
    {
        $html = $this->render("First paragraph.\n\nSecond paragraph.");
        $this->assertStringContains('<p>First paragraph.</p>', $html);
        $this->assertStringContains('<p>Second paragraph.</p>', $html);
    }

    public function testBold(): void
    {
        $html = $this->render("This is **bold** text");
        $this->assertStringContains('<strong>bold</strong>', $html);
    }

    public function testItalic(): void
    {
        $html = $this->render("This is *italic* text");
        $this->assertStringContains('<em>italic</em>', $html);
    }

    public function testBoldItalic(): void
    {
        $html = $this->render("This is ***bold italic*** text");
        // CommonMark can render this either way - just check both tags exist
        $this->assertStringContains('<strong>', $html);
        $this->assertStringContains('<em>', $html);
        $this->assertStringContains('bold italic', $html);
    }

    public function testStrikethrough(): void
    {
        // Note: Strikethrough requires GFM extension, not in core
        $html = $this->render("This is ~~strikethrough~~ text");
        $this->assertStringContains('~~strikethrough~~', $html);
    }

    public function testInlineCode(): void
    {
        $html = $this->render("Use `code` here");
        $this->assertStringContains('<code>code</code>', $html);
    }

    // =========================================================================
    // Headings
    // =========================================================================

    public function testHeading1(): void
    {
        $html = $this->render("# Heading 1");
        $this->assertStringContains('<h1>Heading 1</h1>', $html);
    }

    public function testHeading2(): void
    {
        $html = $this->render("## Heading 2");
        $this->assertStringContains('<h2>Heading 2</h2>', $html);
    }

    public function testHeading3(): void
    {
        $html = $this->render("### Heading 3");
        $this->assertStringContains('<h3>Heading 3</h3>', $html);
    }

    public function testHeading6(): void
    {
        $html = $this->render("###### Heading 6");
        $this->assertStringContains('<h6>Heading 6</h6>', $html);
    }

    public function testAlternateHeading1(): void
    {
        $html = $this->render("Heading 1\n=========");
        $this->assertStringContains('<h1>Heading 1</h1>', $html);
    }

    public function testAlternateHeading2(): void
    {
        $html = $this->render("Heading 2\n---------");
        $this->assertStringContains('<h2>Heading 2</h2>', $html);
    }

    // =========================================================================
    // Lists
    // =========================================================================

    public function testUnorderedList(): void
    {
        $html = $this->render("- Item 1\n- Item 2\n- Item 3");
        $this->assertStringContains('<ul>', $html);
        $this->assertStringContains('<li>Item 1</li>', $html);
        $this->assertStringContains('<li>Item 2</li>', $html);
        $this->assertStringContains('<li>Item 3</li>', $html);
    }

    public function testOrderedList(): void
    {
        $html = $this->render("1. First\n2. Second\n3. Third");
        $this->assertStringContains('<ol>', $html);
        $this->assertStringContains('<li>First</li>', $html);
        $this->assertStringContains('<li>Second</li>', $html);
    }

    public function testNestedList(): void
    {
        $html = $this->render("- Item 1\n  - Nested 1\n  - Nested 2\n- Item 2");
        $this->assertStringContains('<ul>', $html);
        $this->assertStringContains('Nested 1', $html);
    }

    // =========================================================================
    // Links and Images
    // =========================================================================

    public function testLink(): void
    {
        $html = $this->render("[Link text](https://example.com)");
        $this->assertStringContains('<a href="https://example.com">Link text</a>', $html);
    }

    public function testLinkWithTitle(): void
    {
        $html = $this->render('[Link](https://example.com "Title")');
        $this->assertStringContains('title="Title"', $html);
    }

    public function testAutolink(): void
    {
        $html = $this->render("<https://example.com>");
        $this->assertStringContains('href="https://example.com"', $html);
    }

    public function testImage(): void
    {
        $html = $this->render("![Alt text](/image.jpg)");
        $this->assertStringContains('<img src="/image.jpg" alt="Alt text"', $html);
    }

    public function testImageWithTitle(): void
    {
        $html = $this->render('![Alt](/image.jpg "Title")');
        $this->assertStringContains('title="Title"', $html);
    }

    // =========================================================================
    // Code Blocks
    // =========================================================================

    public function testFencedCodeBlock(): void
    {
        $html = $this->render("```\ncode here\n```");
        $this->assertStringContains('<pre><code>', $html);
        $this->assertStringContains('code here', $html);
    }

    public function testFencedCodeBlockWithLanguage(): void
    {
        $html = $this->render("```php\n<?php echo 'hello';\n```");
        $this->assertStringContains('class="language-php"', $html);
    }

    public function testIndentedCodeBlock(): void
    {
        $html = $this->render("    code block");
        $this->assertStringContains('<pre><code>', $html);
        $this->assertStringContains('code block', $html);
    }

    // =========================================================================
    // Blockquotes
    // =========================================================================

    public function testBlockquote(): void
    {
        $html = $this->render("> This is a quote");
        $this->assertStringContains('<blockquote>', $html);
        $this->assertStringContains('This is a quote', $html);
    }

    public function testNestedBlockquote(): void
    {
        $html = $this->render("> Level 1\n>> Level 2");
        $this->assertEquals(2, substr_count($html, '<blockquote>'));
    }

    // =========================================================================
    // Horizontal Rules
    // =========================================================================

    public function testHorizontalRule(): void
    {
        $html = $this->render("---");
        $this->assertStringContains('<hr', $html);
    }

    public function testHorizontalRuleAsterisks(): void
    {
        $html = $this->render("***");
        $this->assertStringContains('<hr', $html);
    }

    // =========================================================================
    // HTML Handling
    // =========================================================================

    public function testAllowsHtmlByDefault(): void
    {
        $html = $this->render("<div class=\"custom\">Content</div>");
        $this->assertStringContains('<div class="custom">Content</div>', $html);
    }

    public function testAllowsInlineHtml(): void
    {
        $html = $this->render("Text with <span>inline</span> HTML");
        $this->assertStringContains('<span>inline</span>', $html);
    }

    public function testStripsHtmlWhenConfigured(): void
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $converter = new MarkdownConverter($environment);

        $html = $converter->convert("<script>alert('xss')</script>")->getContent();
        $this->assertStringNotContains('<script>', $html);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testEmptyInput(): void
    {
        $html = $this->render("");
        $this->assertEquals('', trim($html));
    }

    public function testWhitespaceOnly(): void
    {
        $html = $this->render("   \n   \n   ");
        $this->assertEquals('', trim($html));
    }

    public function testSpecialCharacters(): void
    {
        $html = $this->render("Less than < and greater than >");
        $this->assertStringContains('&lt;', $html);
        $this->assertStringContains('&gt;', $html);
    }

    public function testAmpersand(): void
    {
        $html = $this->render("Tom & Jerry");
        $this->assertStringContains('&amp;', $html);
    }

    public function testEscapedMarkdown(): void
    {
        $html = $this->render("\\*not italic\\*");
        $this->assertStringNotContains('<em>', $html);
        // Asterisks should appear literally (backslashes consumed)
        $this->assertStringContains('*not italic*', $html);
    }

    public function testLineBreak(): void
    {
        $html = $this->render("Line 1  \nLine 2");
        $this->assertStringContains('<br', $html);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
