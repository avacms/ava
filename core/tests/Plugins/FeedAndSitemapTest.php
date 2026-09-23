<?php

declare(strict_types=1);

namespace Ava\Tests\Plugins;

use Ava\Http\Request;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

final class FeedAndSitemapTest extends TestCase
{
    private TempSite $site;

    public function setUp(): void
    {
        $this->site = new TempSite($this->app->allConfig());
        for ($i = 1; $i <= 5; $i++) {
            $this->site->page("posts/post-{$i}.md", [
                'title' => "Post {$i}", 'slug' => "post-{$i}", 'status' => 'published',
                'date' => "2026-01-0{$i}", 'excerpt' => "Excerpt {$i}",
            ], "See [the guide](/guide) and ![image](/media/pic.png).");
        }
        $this->site->page('posts/hidden.md', [
            'title' => 'Hidden', 'slug' => 'hidden', 'status' => 'published', 'date' => '2026-02-01', 'noindex' => 'true',
        ]);
        $this->site->page('posts/draft.md', ['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'date' => '2026-03-01']);
        $this->site->page('pages/about/team.md', ['title' => 'Team', 'status' => 'published']);
    }

    public function tearDown(): void
    {
        $this->site->remove();
    }

    public function testFeedsListTheNewestIndexableItems(): void
    {
        $feed = $this->site->app(['feed' => ['items_per_feed' => 3]])->handle(new Request('GET', '/feed/post.xml'))->content();

        $this->assertEquals(3, substr_count($feed, '<item>'));
        $this->assertStringContains('<title>Post 5</title>', $feed);
        $this->assertStringNotContains('Post 2', $feed);
        $this->assertStringNotContains('Hidden', $feed);
        $this->assertStringNotContains('Draft', $feed);
        $this->assertStringContains('<link>https://example.test/blog/post-5</link>', $feed);
    }

    public function testFullContentFeedsUseAbsoluteLinks(): void
    {
        $feed = $this->site->app(['feed' => ['full_content' => true, 'items_per_feed' => 1]])
            ->handle(new Request('GET', '/feed.xml'))->content();

        $this->assertStringContains('href="https://example.test/guide"', $feed);
        $this->assertStringContains('src="https://example.test/media/pic.png"', $feed);
    }

    public function testFeedsAreServedFromTheWebpageCache(): void
    {
        $request = new Request('GET', '/feed.xml', [], ['Host' => 'example.test']);

        $this->assertEquals('MISS', $this->site->app()->handle($request)->header('X-Page-Cache'));
        $this->assertEquals('HIT', $this->site->app()->handle($request)->header('X-Page-Cache'));
    }

    public function testSitemapsUseRealUrlsAndSplitAtTheLimit(): void
    {
        $app = $this->site->app(['sitemap' => ['max_urls' => 2]]);

        $index = $app->handle(new Request('GET', '/sitemap.xml'))->content();
        $this->assertStringContains('/sitemap-post.xml</loc>', $index);
        $this->assertStringContains('/sitemap-post-2.xml</loc>', $index);
        $this->assertStringContains('/sitemap-post-3.xml</loc>', $index);
        $this->assertStringNotContains('/sitemap-post-4.xml', $index);

        $second = $this->site->app(['sitemap' => ['max_urls' => 2]])->handle(new Request('GET', '/sitemap-post-2.xml'));
        $this->assertEquals(200, $second->status());
        $this->assertEquals(2, substr_count($second->content(), '<url>'));
        $this->assertEquals(404, $this->site->app(['sitemap' => ['max_urls' => 2]])->handle(new Request('GET', '/sitemap-post-9.xml'))->status());

        $pages = $this->site->app()->handle(new Request('GET', '/sitemap-page.xml'))->content();
        $this->assertStringContains('<loc>https://example.test/about/team</loc>', $pages);
    }
}
