<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Http\Request;
use Ava\Rendering\TemplateHelpers;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

final class RenderingRegressionTest extends TestCase
{
    private TempSite $site;

    public function setUp(): void
    {
        $this->site = new TempSite($this->app->allConfig());
    }

    public function tearDown(): void
    {
        $this->site->remove();
    }

    public function testPrerenderedAndOnDemandPagesAreIdentical(): void
    {
        // Pre-rendered HTML used to skip block-shortcode unwrapping and to
        // expand aliases before shortcodes ran, so the default configuration
        // rendered differently from the documented one.
        $body = "Intro\n\n[snippet name=\"cta\"]\n\n[media_link]\n\n![Logo](@media:logo.png)";
        $this->site->page('pages/parity.md', ['title' => 'Parity', 'status' => 'published'], $body);

        $renders = [];
        foreach ([true, false] as $prerender) {
            $app = $this->site->app(['content_index' => ['prerender_html' => $prerender]]);
            $app->shortcodes()->register('media_link', fn() => '<a href="@media:guide.pdf">Guide</a>');
            $app->indexer()->rebuild();
            $item = $app->repository()->get('page', 'parity');
            $renders[] = $app->renderer()->renderItem($item);

            $this->assertEquals($prerender, $app->repository()->prerenderedHtml($item) !== null);
        }

        $this->assertEquals($renders[1], $renders[0]);
        $this->assertStringNotContains('<p><div class="cta-box"', $renders[0]);
        $this->assertStringContains('href="/media/guide.pdf"', $renders[0]);
    }

    public function testStalePrerenderedHtmlIsNeverServed(): void
    {
        $path = $this->site->page('pages/stale.md', ['title' => 'Stale', 'status' => 'published'], 'First version');
        $manual = ['content_index' => ['mode' => 'never']];
        $this->site->app($manual)->indexer()->rebuild();

        // Edited without a rebuild (manual mode).
        file_put_contents($path, str_replace('First version', 'Second version', (string) file_get_contents($path)));

        $content = $this->site->app($manual)->handle(new Request('GET', '/stale'))->content();
        $this->assertStringContains('Second version', $content);
    }

    public function testShortcodesInsideCodeAreShownNotRun(): void
    {
        $html = $this->app->renderer()->renderMarkdown(
            "Inline `[year]` example.\n\n```\n[snippet name=\"cta\" heading=\"Nope\"]\n```\n\nReal: [year]"
        );

        $this->assertStringContains('<code>[year]</code>', $html);
        $this->assertStringContains('[snippet name=&quot;cta&quot; heading=&quot;Nope&quot;]', $html);
        $this->assertStringNotContains('cta-box', $html);
        $this->assertStringContains('Real: ' . date('Y'), $html);
    }

    public function testMetaTagsKeepAbsoluteImageUrlsAndResolveRelativeCanonicals(): void
    {
        $this->site->page('pages/social.md', [
            'title' => 'Social',
            'status' => 'published',
            'og_image' => 'https://cdn.example.com/team.jpg',
            'canonical' => '/elsewhere',
        ]);
        $app = $this->site->app();
        $app->indexer()->rebuild();
        $helpers = new TemplateHelpers($app, $app->renderer());

        $tags = $helpers->metaTags($app->repository()->get('page', 'social'));

        $this->assertStringContains('<meta property="og:image" content="https://cdn.example.com/team.jpg">', $tags);
        $this->assertStringContains('<link rel="canonical" href="https://example.test/elsewhere">', $tags);
        $this->assertEquals('https://cdn.example.com/x', $helpers->fullUrl('https://cdn.example.com/x'));
        $this->assertEquals('//cdn.example.com/x', $helpers->fullUrl('//cdn.example.com/x'));
        $this->assertEquals('https://example.test/x', $helpers->fullUrl('/x'));
    }

    public function testSearchResultsPaginateAndLinkNestedPages(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->site->page(sprintf('posts/post-%02d.md', $i), [
                'title' => "Needle {$i}", 'slug' => "post-{$i}", 'status' => 'published', 'date' => sprintf('2026-01-%02d', $i),
            ]);
        }
        $this->site->page('pages/about/team.md', ['title' => 'Needle team', 'status' => 'published']);
        $app = $this->site->app();

        $first = $app->handle(new Request('GET', '/search', ['q' => 'needle']))->content();
        $this->assertStringContains('Page 1 of 2', $first);
        $this->assertStringContains('/search?q=needle&amp;paged=2', $first);

        $second = $this->site->app()->handle(new Request('GET', '/search', ['q' => 'needle', 'paged' => '2']))->content();
        $this->assertStringContains('Page 2 of 2', $second);

        // Nested pages link by path, not by their last segment.
        $all = $first . $second;
        $this->assertStringContains('href="/about/team"', $all);
        $this->assertStringNotContains('href=""', $all);
    }

    public function testTaxonomyIndexLinksFollowTheConfiguredBase(): void
    {
        $this->site->page('posts/tagged.md', ['title' => 'Tagged', 'slug' => 'tagged', 'status' => 'published', 'category' => 'Tutorials']);

        $content = $this->site->app()->handle(new Request('GET', '/category'))->content();

        $this->assertStringContains('href="/category/tutorials"', $content);
        $this->assertStringContains('Tutorials', $content);
    }

    public function testPagesAndFeedsFollowTheSiteLocale(): void
    {
        $this->site->page('posts/hello.md', ['title' => 'Hello', 'slug' => 'hello', 'status' => 'published', 'date' => '2026-01-01']);
        $app = $this->site->app(['site' => ['locale' => 'fr_CA.UTF-8']]);

        $page = $app->handle(new Request('GET', '/blog/hello'))->content();
        $this->assertStringContains('<html lang="fr-CA">', $page);
        $this->assertStringContains('<meta property="og:locale" content="fr_CA">', $page);

        $feed = $this->site->app(['site' => ['locale' => 'fr_CA.UTF-8']])->handle(new Request('GET', '/feed.xml'))->content();
        $this->assertStringContains('<language>fr-ca</language>', $feed);
    }
}
