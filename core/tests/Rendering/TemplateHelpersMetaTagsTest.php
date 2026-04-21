<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Content\Item;
use Ava\Rendering\TemplateHelpers;
use Ava\Testing\TestCase;

/**
 * Tests for TemplateHelpers::metaTags() SEO output.
 *
 * Covers:
 *   - Canonical: frontmatter override, auto-generation, trailing-slash awareness
 *   - og:type: mapped by content type (post → article, others → website)
 *   - og:url: emitted and matches canonical
 *   - Twitter: minimum set always present; image conditional
 */
final class TemplateHelpersMetaTagsTest extends TestCase
{
    private TemplateHelpers $helpers;

    public function setUp(): void
    {
        $this->helpers = new TemplateHelpers($this->app, $this->app->renderer());
    }

    // =========================================================================
    // Canonical — frontmatter override
    // =========================================================================

    public function testFrontmatterCanonicalIsUsedWhenPresent(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello',
            'canonical' => 'https://original.example.com/hello',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains(
            '<link rel="canonical" href="https://original.example.com/hello">',
            $output
        );
    }

    public function testFrontmatterCanonicalProducesExactlyOneCanonicalTag(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello',
            'canonical' => 'https://original.example.com/hello',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertEquals(1, substr_count($output, '<link rel="canonical"'), 'Expected exactly one canonical tag');
    }

    // =========================================================================
    // Canonical — auto-generation from router
    // =========================================================================

    public function testAutoCanonicalGeneratedWhenNoFrontmatterCanonical(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello World',
        ]);

        $output = $this->helpers->metaTags($item);

        $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
        $expected = $baseUrl . $url;

        $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
    }

    public function testNoCanonicalEmittedWhenUrlCannotBeResolved(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'nonexistent-slug-xyz-99999',
            'title' => 'Nonexistent',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringNotContains('<link rel="canonical"', $output);
    }

    // =========================================================================
    // Canonical — trailing slash config
    // =========================================================================

    public function testCanonicalRespectsTrailingSlashEnabled(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $ref = new \ReflectionProperty($this->app, 'config');
        $ref->setAccessible(true);
        $config = $ref->getValue($this->app);
        $original = $config['routing']['trailing_slash'] ?? false;
        $config['routing']['trailing_slash'] = true;
        $ref->setValue($this->app, $config);

        try {
            $item = $this->makeItem('post', [
                'slug' => 'hello-world',
                'title' => 'Hello World',
            ]);

            $output = $this->helpers->metaTags($item);

            $resolvedUrl = $this->app->router()->urlFor('post', 'hello-world');
            $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
            $expected = $baseUrl . $resolvedUrl;

            $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
            $this->assertTrue(str_ends_with($expected, '/'), 'Canonical should end with / when trailing_slash is enabled');
        } finally {
            $config['routing']['trailing_slash'] = $original;
            $ref->setValue($this->app, $config);
        }
    }

    public function testCanonicalRespectsTrailingSlashDisabled(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $ref = new \ReflectionProperty($this->app, 'config');
        $ref->setAccessible(true);
        $config = $ref->getValue($this->app);
        $original = $config['routing']['trailing_slash'] ?? false;
        $config['routing']['trailing_slash'] = false;
        $ref->setValue($this->app, $config);

        try {
            $item = $this->makeItem('post', [
                'slug' => 'hello-world',
                'title' => 'Hello World',
            ]);

            $output = $this->helpers->metaTags($item);

            $resolvedUrl = $this->app->router()->urlFor('post', 'hello-world');
            $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
            $expected = $baseUrl . $resolvedUrl;

            $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
            $this->assertTrue(!str_ends_with($expected, '/'), 'Canonical should not end with / when trailing_slash is disabled');
        } finally {
            $config['routing']['trailing_slash'] = $original;
            $ref->setValue($this->app, $config);
        }
    }

    // =========================================================================
    // og:type by content type
    // =========================================================================

    public function testOgTypeIsArticleForPost(): void
    {
        $item = $this->makeItem('post', ['slug' => 'test', 'title' => 'Test']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta property="og:type" content="article">', $output);
    }

    public function testOgTypeIsWebsiteForPage(): void
    {
        $item = $this->makeItem('page', ['slug' => 'about', 'title' => 'About']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta property="og:type" content="website">', $output);
    }

    public function testOgTypeIsWebsiteForUnknownType(): void
    {
        $item = $this->makeItem('project', ['slug' => 'my-project', 'title' => 'My Project']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta property="og:type" content="website">', $output);
    }

    public function testOgTypeNotHardcodedToArticleForPage(): void
    {
        $item = $this->makeItem('page', ['slug' => 'about', 'title' => 'About']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringNotContains('content="article"', $output);
    }

    // =========================================================================
    // og:url
    // =========================================================================

    public function testOgUrlIsEmittedForItemWithKnownUrl(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello World',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta property="og:url"', $output);
    }

    public function testOgUrlMatchesCanonical(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello World',
        ]);

        $output = $this->helpers->metaTags($item);

        $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
        $expectedUrl = $baseUrl . $url;

        $this->assertStringContains('<link rel="canonical" href="' . $expectedUrl . '">', $output);
        $this->assertStringContains('<meta property="og:url" content="' . $expectedUrl . '">', $output);
    }

    public function testOgUrlUsesFrontmatterCanonicalWhenSet(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello',
            'canonical' => 'https://original.example.com/hello',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains(
            '<meta property="og:url" content="https://original.example.com/hello">',
            $output
        );
    }

    public function testOgUrlNotEmittedWhenUrlCannotBeResolved(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'nonexistent-xyz-99999',
            'title' => 'Nonexistent',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringNotContains('<meta property="og:url"', $output);
    }

    // =========================================================================
    // Twitter — minimum set always emitted
    // =========================================================================

    public function testTwitterCardAlwaysEmitted(): void
    {
        $item = $this->makeItem('post', ['slug' => 'test', 'title' => 'Test']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:card"', $output);
    }

    public function testTwitterCardIsSummary(): void
    {
        $item = $this->makeItem('post', ['slug' => 'test', 'title' => 'Test']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:card" content="summary">', $output);
    }

    public function testTwitterTitleAlwaysEmitted(): void
    {
        $item = $this->makeItem('post', ['slug' => 'test', 'title' => 'My Post Title']);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:title" content="My Post Title">', $output);
    }

    public function testTwitterDescriptionEmittedWhenExcerptPresent(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'test',
            'title' => 'Test',
            'excerpt' => 'A short summary.',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:description" content="A short summary.">', $output);
    }

    public function testTwitterMinimumSetPresentWithoutOgImage(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'test',
            'title' => 'My Post',
            'excerpt' => 'Some excerpt',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:card"', $output);
        $this->assertStringContains('<meta name="twitter:title"', $output);
        $this->assertStringContains('<meta name="twitter:description"', $output);
        $this->assertStringNotContains('<meta name="twitter:image"', $output);
    }

    public function testTwitterImageEmittedWhenOgImagePresent(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'test',
            'title' => 'Test',
            'og_image' => '/media/social.jpg',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:image"', $output);
        $this->assertStringContains('/media/social.jpg', $output);
    }

    public function testTwitterImageEmittedWhenFeaturedImagePresent(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'test',
            'title' => 'Test',
            'featured_image' => '/media/featured.jpg',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringContains('<meta name="twitter:image"', $output);
        $this->assertStringContains('/media/featured.jpg', $output);
    }

    public function testTwitterImageNotEmittedWithoutOgImage(): void
    {
        $item = $this->makeItem('post', [
            'slug' => 'test',
            'title' => 'Test',
        ]);

        $output = $this->helpers->metaTags($item);

        $this->assertStringNotContains('<meta name="twitter:image"', $output);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makeItem(string $type, array $frontmatter): Item
    {
        return new Item(
            $frontmatter,
            '',
            '/content/' . $type . 's/' . ($frontmatter['slug'] ?? 'test') . '.md',
            $type
        );
    }
}
