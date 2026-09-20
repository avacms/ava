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

    public function testMetaTagsFormatsDocumentTitleWithoutChangingSocialTitles(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello World',
        ]));

        $siteName = $this->app->config('site.name');
        $this->assertStringContains('<title>Hello World · ' . $siteName . '</title>', $output);
        $this->assertStringContains('<meta property="og:title" content="Hello World">', $output);
        $this->assertStringContains('<meta name="twitter:title" content="Hello World">', $output);
    }

    public function testMetaTagsDoesNotFormatExplicitMetaTitle(): void
    {
        $ref = new \ReflectionProperty($this->app, 'config');
        $config = $ref->getValue($this->app);
        $original = $config['site']['title_format'] ?? null;

        try {
            $config['site']['title_format'] = '{site} | {title}';
            $ref->setValue($this->app, $config);

            $output = $this->helpers->metaTags($this->makeItem('post', [
                'slug' => 'hello-world',
                'title' => 'Hello World',
                'meta_title' => 'Custom SEO Title',
            ]));

            $this->assertStringContains('<title>Custom SEO Title</title>', $output);
            $this->assertFalse(str_contains($output, '<title>My Ava Site | Custom SEO Title</title>'));
        } finally {
            $config['site']['title_format'] = $original;
            $ref->setValue($this->app, $config);
        }
    }

    // =========================================================================
    // Canonical and og:url
    // =========================================================================

    public function testFrontmatterCanonicalOverridesBothCanonicalAndOgUrl(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello',
            'canonical' => 'https://original.example.com/hello',
        ]));

        $this->assertStringContains('<link rel="canonical" href="https://original.example.com/hello">', $output);
        $this->assertStringContains('<meta property="og:url" content="https://original.example.com/hello">', $output);
        $this->assertEquals(1, substr_count($output, '<link rel="canonical"'), 'Expected exactly one canonical tag');
    }

    public function testAutoCanonicalAndOgUrlAgreeWhenTheRouterResolvesTheItem(): void
    {
        $url = $this->app->router()->urlFor('post', 'hello-world');
        if ($url === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $output = $this->helpers->metaTags($this->makeItem('post', [
            'slug' => 'hello-world',
            'title' => 'Hello World',
        ]));

        $expected = rtrim($this->app->config('site.base_url', ''), '/') . $url;

        $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
        $this->assertStringContains('<meta property="og:url" content="' . $expected . '">', $output);
    }

    public function testAutoCanonicalUsesHierarchicalContentKeyWhenSlugDiffers(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('page', [
            'slug' => 'different-about-slug',
            'content_key' => 'about',
            'title' => 'About',
        ]));

        $expected = rtrim($this->app->config('site.base_url', ''), '/') . '/about';

        $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
        $this->assertStringContains('<meta property="og:url" content="' . $expected . '">', $output);
        $this->assertEquals(1, substr_count($output, '<link rel="canonical"'), 'Expected exactly one canonical tag');
    }

    public function testAutoCanonicalUsesEmptyContentKeyForHomepageIndex(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('page', [
            'slug' => 'index',
            'content_key' => '',
            'title' => 'Home',
        ]));

        $expected = rtrim($this->app->config('site.base_url', ''), '/') . '/';

        $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
        $this->assertStringContains('<meta property="og:url" content="' . $expected . '">', $output);
        $this->assertEquals(1, substr_count($output, '<link rel="canonical"'), 'Expected exactly one canonical tag');
    }

    public function testNoCanonicalOrOgUrlEmittedWhenTheUrlCannotBeResolved(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('post', [
            'slug' => 'nonexistent-slug-xyz-99999',
            'title' => 'Nonexistent',
        ]));

        $this->assertStringNotContains('<link rel="canonical"', $output);
        $this->assertStringNotContains('<meta property="og:url"', $output);
    }

    public function testCanonicalRespectsTrailingSlashSetting(): void
    {
        if ($this->app->router()->urlFor('post', 'hello-world') === null) {
            $this->markSkipped('Content index not available');
            return;
        }

        $ref = new \ReflectionProperty($this->app, 'config');
        $ref->setAccessible(true);
        $config = $ref->getValue($this->app);
        $original = $config['routing']['trailing_slash'] ?? false;

        try {
            foreach ([true, false] as $trailingSlash) {
                $config['routing']['trailing_slash'] = $trailingSlash;
                $ref->setValue($this->app, $config);

                $output = $this->helpers->metaTags($this->makeItem('post', [
                    'slug' => 'hello-world',
                    'title' => 'Hello World',
                ]));

                $baseUrl = rtrim($this->app->config('site.base_url', ''), '/');
                $expected = $baseUrl . $this->app->router()->urlFor('post', 'hello-world');

                $this->assertStringContains('<link rel="canonical" href="' . $expected . '">', $output);
                $this->assertEquals(
                    $trailingSlash,
                    str_ends_with($expected, '/'),
                    'Canonical trailing slash should follow routing.trailing_slash'
                );
            }
        } finally {
            $config['routing']['trailing_slash'] = $original;
            $ref->setValue($this->app, $config);
        }
    }

    // =========================================================================
    // og:type by content type
    // =========================================================================

    public function testOgTypeIsArticleOnlyForPosts(): void
    {
        foreach (['post' => 'article', 'page' => 'website', 'project' => 'website'] as $type => $expected) {
            $output = $this->helpers->metaTags($this->makeItem($type, ['slug' => 'test', 'title' => 'Test']));

            $this->assertStringContains('<meta property="og:type" content="' . $expected . '">', $output, $type);
            if ($expected !== 'article') {
                $this->assertStringNotContains('content="article"', $output, $type);
            }
        }
    }

    // =========================================================================
    // Twitter card
    // =========================================================================

    public function testTwitterMinimumSetIsAlwaysEmitted(): void
    {
        $output = $this->helpers->metaTags($this->makeItem('post', [
            'slug' => 'test',
            'title' => 'My Post Title',
            'excerpt' => 'A short summary.',
        ]));

        $this->assertStringContains('<meta name="twitter:card" content="summary">', $output);
        $this->assertStringContains('<meta name="twitter:title" content="My Post Title">', $output);
        $this->assertStringContains('<meta name="twitter:description" content="A short summary.">', $output);
        // No image source in frontmatter, so no image tag.
        $this->assertStringNotContains('<meta name="twitter:image"', $output);
    }

    public function testSiteOgImageIsUsedWhenItemHasNoImage(): void
    {
        $this->withSiteConfig(['og_image' => '@media:site-social.jpg'], function (): void {
            $output = $this->helpers->metaTags($this->makeItem('page', [
                'slug' => 'test',
                'title' => 'Test',
            ]));

            $expected = rtrim($this->app->config('site.base_url', ''), '/') . '/media/site-social.jpg';

            $this->assertStringContains('<meta property="og:image" content="' . $expected . '">', $output);
            $this->assertStringContains('<meta name="twitter:image" content="' . $expected . '">', $output);
            $this->assertStringContains('<meta name="twitter:card" content="summary_large_image">', $output);
        });
    }

    public function testItemOgImageTakesPrecedenceOverSiteOgImage(): void
    {
        $this->withSiteConfig(['og_image' => '/media/site-social.jpg'], function (): void {
            $output = $this->helpers->metaTags($this->makeItem('post', [
                'slug' => 'test',
                'title' => 'Test',
                'og_image' => '@media:item-social.jpg',
            ]));

            $this->assertStringContains('/media/item-social.jpg', $output);
            $this->assertStringNotContains('/media/site-social.jpg', $output);
            $this->assertStringContains('<meta name="twitter:card" content="summary_large_image">', $output);
        });
    }

    public function testTwitterImageFollowsOgImageWithFeaturedImageFallback(): void
    {
        foreach ([
            [['og_image' => '/media/social.jpg'], '/media/social.jpg'],
            [['featured_image' => '/media/featured.jpg'], '/media/featured.jpg'],
        ] as [$frontmatter, $expected]) {
            $output = $this->helpers->metaTags(
                $this->makeItem('post', $frontmatter + ['slug' => 'test', 'title' => 'Test'])
            );

            $this->assertStringContains('<meta name="twitter:image"', $output, $expected);
            $this->assertStringContains($expected, $output);
        }
    }

    private function makeItem(string $type, array $frontmatter): Item
    {
        return new Item(
            $frontmatter,
            '',
            '/content/' . $type . 's/' . ($frontmatter['slug'] ?? 'test') . '.md',
            $type
        );
    }

    private function withSiteConfig(array $values, callable $callback): void
    {
        $ref = new \ReflectionProperty($this->app, 'config');
        $config = $ref->getValue($this->app);
        $originalSite = $config['site'];

        try {
            $config['site'] = array_replace($originalSite, $values);
            $ref->setValue($this->app, $config);
            $callback();
        } finally {
            $config['site'] = $originalSite;
            $ref->setValue($this->app, $config);
        }
    }
}
