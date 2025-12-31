<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Parser;
use Ava\Content\Item;
use Ava\Testing\TestCase;

/**
 * Tests for the Content Parser.
 */
final class ParserTest extends TestCase
{
    private Parser $parser;

    public function setUp(): void
    {
        $this->parser = new Parser();
    }

    // =========================================================================
    // Basic parsing
    // =========================================================================

    public function testParseExtractsFrontmatterAndContent(): void
    {
        $content = <<<MD
---
title: Hello World
slug: hello-world
---

This is the content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertEquals('Hello World', $item->title());
        $this->assertEquals('hello-world', $item->slug());
        $this->assertStringContains('This is the content.', $item->rawContent());
    }

    public function testParseHandlesMultipleFrontmatterFields(): void
    {
        $content = <<<MD
---
id: 01ARYZ6S41ABCDEFGHIJKLMNOP
title: Test Post
slug: test-post
status: published
date: 2024-01-15
excerpt: A test excerpt
---

Content here.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertEquals('01ARYZ6S41ABCDEFGHIJKLMNOP', $item->id());
        $this->assertEquals('Test Post', $item->title());
        $this->assertEquals('test-post', $item->slug());
        $this->assertEquals('published', $item->status());
        $this->assertEquals('A test excerpt', $item->excerpt());
    }

    public function testParseDefaultsSlugFromFilename(): void
    {
        $content = <<<MD
---
title: My Post
---

Content.
MD;

        $item = $this->parser->parse($content, '/content/posts/my-post.md', 'post');

        $this->assertEquals('my-post', $item->slug());
    }

    public function testParseDefaultsTitleFromSlug(): void
    {
        $content = <<<MD
---
slug: hello-world
---

Content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertEquals('Hello World', $item->title());
    }

    public function testParseDefaultsStatusToDraft(): void
    {
        $content = <<<MD
---
title: Test
slug: test
---

Content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertEquals('draft', $item->status());
    }

    // =========================================================================
    // Frontmatter edge cases
    // =========================================================================

    public function testParseThrowsForMissingClosingDelimiter(): void
    {
        $content = <<<MD
---
title: Broken
slug: broken
MD;

        $this->assertThrows(\RuntimeException::class, function () use ($content) {
            $this->parser->parse($content, '/test.md', 'post');
        });
    }

    public function testParseHandlesContentWithoutFrontmatter(): void
    {
        $content = "Just plain markdown content.";

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertStringContains('Just plain markdown content.', $item->rawContent());
        $this->assertEquals('test', $item->slug()); // From filename
    }

    public function testParseHandlesEmptyFrontmatter(): void
    {
        $content = <<<MD
---
---

Content only.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');

        $this->assertStringContains('Content only.', $item->rawContent());
    }

    public function testParseThrowsForInvalidYaml(): void
    {
        $content = <<<MD
---
title: [invalid yaml
  this is broken
---

Content.
MD;

        $this->assertThrows(\RuntimeException::class, function () use ($content) {
            $this->parser->parse($content, '/test.md', 'post');
        });
    }

    // =========================================================================
    // File parsing
    // =========================================================================

    public function testParseFileThrowsForMissingFile(): void
    {
        $this->assertThrows(\RuntimeException::class, function () {
            $this->parser->parseFile('/nonexistent/file.md', 'post');
        });
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function testValidateReturnsEmptyForValidItem(): void
    {
        $content = <<<MD
---
title: Valid Post
slug: valid-post
status: published
---

Content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');
        $errors = $this->parser->validate($item);

        $this->assertEmpty($errors);
    }

    public function testValidateReturnsErrorForInvalidStatus(): void
    {
        $content = <<<MD
---
title: Test
slug: test
status: invalid-status
---

Content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');
        $errors = $this->parser->validate($item);

        $this->assertNotEmpty($errors);
        $this->assertStringContains('Invalid status', $errors[0]);
    }

    public function testValidateReturnsErrorForInvalidSlugCharacters(): void
    {
        $content = <<<MD
---
title: Test
slug: Invalid_Slug!
---

Content.
MD;

        $item = $this->parser->parse($content, '/test.md', 'post');
        $errors = $this->parser->validate($item);

        $this->assertNotEmpty($errors);
        $this->assertStringContains('lowercase alphanumeric', $errors[0]);
    }
}
