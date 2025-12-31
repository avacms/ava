<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Item;
use Ava\Testing\TestCase;

/**
 * Tests for the Content Item class.
 */
final class ItemTest extends TestCase
{
    // =========================================================================
    // Core fields
    // =========================================================================

    public function testIdReturnsIdFromFrontmatter(): void
    {
        $item = $this->createItem(['id' => '01ARYZ6S41ABCDEFGHIJKLMNOP']);
        $this->assertEquals('01ARYZ6S41ABCDEFGHIJKLMNOP', $item->id());
    }

    public function testIdReturnsNullIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertNull($item->id());
    }

    public function testTitleReturnsTitle(): void
    {
        $item = $this->createItem(['title' => 'Hello World']);
        $this->assertEquals('Hello World', $item->title());
    }

    public function testTitleReturnsEmptyStringIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertEquals('', $item->title());
    }

    public function testSlugReturnsSlug(): void
    {
        $item = $this->createItem(['slug' => 'hello-world']);
        $this->assertEquals('hello-world', $item->slug());
    }

    public function testStatusReturnsStatus(): void
    {
        $item = $this->createItem(['status' => 'published']);
        $this->assertEquals('published', $item->status());
    }

    public function testStatusDefaultsToDraft(): void
    {
        $item = $this->createItem([]);
        $this->assertEquals('draft', $item->status());
    }

    // =========================================================================
    // Status checks
    // =========================================================================

    public function testIsPublishedReturnsTrueForPublished(): void
    {
        $item = $this->createItem(['status' => 'published']);
        $this->assertTrue($item->isPublished());
        $this->assertFalse($item->isDraft());
        $this->assertFalse($item->isPrivate());
    }

    public function testIsDraftReturnsTrueForDraft(): void
    {
        $item = $this->createItem(['status' => 'draft']);
        $this->assertTrue($item->isDraft());
        $this->assertFalse($item->isPublished());
        $this->assertFalse($item->isPrivate());
    }

    public function testIsPrivateReturnsTrueForPrivate(): void
    {
        $item = $this->createItem(['status' => 'private']);
        $this->assertTrue($item->isPrivate());
        $this->assertFalse($item->isPublished());
        $this->assertFalse($item->isDraft());
    }

    // =========================================================================
    // Dates
    // =========================================================================

    public function testDateReturnsDateTimeImmutable(): void
    {
        $item = $this->createItem(['date' => '2024-01-15']);
        $date = $item->date();

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertEquals('2024-01-15', $date->format('Y-m-d'));
    }

    public function testDateReturnsNullIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertNull($item->date());
    }

    public function testDateHandlesDateTimeObject(): void
    {
        $dt = new \DateTime('2024-06-20');
        $item = $this->createItem(['date' => $dt]);
        $date = $item->date();

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertEquals('2024-06-20', $date->format('Y-m-d'));
    }

    public function testDateHandlesTimestamp(): void
    {
        $timestamp = strtotime('2024-03-10');
        $item = $this->createItem(['date' => $timestamp]);
        $date = $item->date();

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertEquals('2024-03-10', $date->format('Y-m-d'));
    }

    public function testUpdatedReturnsUpdatedDate(): void
    {
        $item = $this->createItem([
            'date' => '2024-01-15',
            'updated' => '2024-06-20',
        ]);

        $this->assertEquals('2024-01-15', $item->date()->format('Y-m-d'));
        $this->assertEquals('2024-06-20', $item->updated()->format('Y-m-d'));
    }

    public function testUpdatedFallsBackToDate(): void
    {
        $item = $this->createItem(['date' => '2024-01-15']);

        $this->assertEquals('2024-01-15', $item->updated()->format('Y-m-d'));
    }

    // =========================================================================
    // Content
    // =========================================================================

    public function testRawContentReturnsMarkdown(): void
    {
        $item = new Item([], 'This is **markdown**', '/test.md', 'post');
        $this->assertEquals('This is **markdown**', $item->rawContent());
    }

    public function testExcerptReturnsExcerpt(): void
    {
        $item = $this->createItem(['excerpt' => 'A short summary']);
        $this->assertEquals('A short summary', $item->excerpt());
    }

    public function testExcerptReturnsNullIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertNull($item->excerpt());
    }

    public function testHtmlCanBeSetAndRetrieved(): void
    {
        $item = $this->createItem([]);

        $this->assertNull($item->html());

        $item->html('<p>Rendered HTML</p>');
        $this->assertEquals('<p>Rendered HTML</p>', $item->html());
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    public function testTypeReturnsContentType(): void
    {
        $item = new Item(['title' => 'Test'], '', '/test.md', 'page');
        $this->assertEquals('page', $item->type());
    }

    public function testFilePathReturnsPath(): void
    {
        $item = new Item([], '', '/content/posts/test.md', 'post');
        $this->assertEquals('/content/posts/test.md', $item->filePath());
    }

    public function testTemplateReturnsTemplate(): void
    {
        $item = $this->createItem(['template' => 'custom']);
        $this->assertEquals('custom', $item->template());
    }

    public function testTemplateReturnsNullIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertNull($item->template());
    }

    // =========================================================================
    // Taxonomies
    // =========================================================================

    public function testTermsReturnsTermsForTaxonomy(): void
    {
        $item = $this->createItem([
            'categories' => ['tutorials', 'php'],
        ]);

        $terms = $item->terms('categories');
        $this->assertEquals(['tutorials', 'php'], $terms);
    }

    public function testTermsReturnsSingleTermAsArray(): void
    {
        $item = $this->createItem([
            'categories' => 'tutorials',
        ]);

        $terms = $item->terms('categories');
        $this->assertEquals(['tutorials'], $terms);
    }

    public function testTermsReturnsEmptyArrayForMissingTaxonomy(): void
    {
        $item = $this->createItem([]);
        $terms = $item->terms('categories');
        $this->assertEquals([], $terms);
    }

    // =========================================================================
    // SEO
    // =========================================================================

    public function testMetaTitleReturnsMetaTitle(): void
    {
        $item = $this->createItem(['meta_title' => 'SEO Title']);
        $this->assertEquals('SEO Title', $item->metaTitle());
    }

    public function testMetaDescriptionReturnsMetaDescription(): void
    {
        $item = $this->createItem(['meta_description' => 'SEO description']);
        $this->assertEquals('SEO description', $item->metaDescription());
    }

    public function testNoindexReturnsFalseByDefault(): void
    {
        $item = $this->createItem([]);
        $this->assertFalse($item->noindex());
    }

    public function testNoindexReturnsTrueWhenSet(): void
    {
        $item = $this->createItem(['noindex' => true]);
        $this->assertTrue($item->noindex());
    }

    public function testCanonicalReturnsCanonicalUrl(): void
    {
        $item = $this->createItem(['canonical' => 'https://example.com/original']);
        $this->assertEquals('https://example.com/original', $item->canonical());
    }

    public function testOgImageReturnsOgImage(): void
    {
        $item = $this->createItem(['og_image' => '/images/og.jpg']);
        $this->assertEquals('/images/og.jpg', $item->ogImage());
    }

    // =========================================================================
    // Redirects
    // =========================================================================

    public function testRedirectFromReturnsArrayOfUrls(): void
    {
        $item = $this->createItem([
            'redirect_from' => ['/old-url', '/legacy/path'],
        ]);

        $redirects = $item->redirectFrom();
        $this->assertEquals(['/old-url', '/legacy/path'], $redirects);
    }

    public function testRedirectFromReturnsSingleUrlAsArray(): void
    {
        $item = $this->createItem([
            'redirect_from' => '/old-url',
        ]);

        $redirects = $item->redirectFrom();
        $this->assertEquals(['/old-url'], $redirects);
    }

    public function testRedirectFromReturnsEmptyArrayIfMissing(): void
    {
        $item = $this->createItem([]);
        $this->assertEquals([], $item->redirectFrom());
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function createItem(array $frontmatter, string $content = ''): Item
    {
        return new Item($frontmatter, $content, '/test.md', 'post');
    }
}
