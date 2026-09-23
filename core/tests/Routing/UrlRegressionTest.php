<?php

declare(strict_types=1);

namespace Ava\Tests\Routing;

use Ava\Http\Request;
use Ava\Http\UrlPath;
use Ava\Routing\PreviewLinks;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

final class UrlRegressionTest extends TestCase
{
    private TempSite $site;

    public function setUp(): void
    {
        $this->site = new TempSite($this->app->allConfig());
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published']);
        $this->site->page('pages/café.md', ['title' => 'Café', 'status' => 'published']);
        $this->site->page('pages/secret-plans.md', ['title' => 'Secret plans', 'status' => 'draft']);
        $this->site->page('posts/first.md', ['title' => 'First', 'slug' => 'first', 'status' => 'published', 'date' => '2026-01-01']);
        $this->site->page('posts/second.md', ['title' => 'Second', 'slug' => 'second', 'status' => 'published', 'date' => '2026-01-02']);
    }

    public function tearDown(): void
    {
        $this->site->remove();
    }

    public function testTrailingSlashRedirectKeepsTheQueryString(): void
    {
        $response = $this->get('/blog/', ['paged' => '2'], '/blog/?paged=2');

        $this->assertEquals(301, $response->status());
        $this->assertEquals('/blog?paged=2', $response->header('Location'));
    }

    public function testArchiveTypeCannotBeSwappedByVisitors(): void
    {
        $content = $this->get('/blog', ['type' => 'page'], '/blog?type=page')->content();

        $this->assertStringContains('First', $content);
        $this->assertStringNotContains('Café', $content);
    }

    public function testNonAsciiUrlsWork(): void
    {
        $response = $this->get('/caf%C3%A9');

        $this->assertEquals(200, $response->status());
        $this->assertStringContains('Café', $response->content());
    }

    public function testEveryPageHasOneCanonicalSpelling(): void
    {
        // Each spelling would otherwise be a separate page (and a separate
        // webpage-cache entry).
        foreach (['/%61bout' => '/about', '/caf%c3%a9' => '/caf%C3%A9', '//about' => '/about'] as $variant => $canonical) {
            $response = $this->get($variant);
            $this->assertEquals(301, $response->status(), $variant);
            $this->assertEquals($canonical, $response->header('Location'), $variant);
        }
    }

    public function testDraftsArePreviewedAtTheirRealUrlWithSignedLinks(): void
    {
        $app = $this->site->app(['security' => ['preview_token' => 'a-long-random-preview-secret']]);
        $links = $app->router()->previewLinks();

        $this->assertEquals(404, $app->handle(new Request('GET', '/secret-plans'))->status());

        $url = $links->url('/secret-plans', time() + 3600);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $preview = $app->handle(new Request('GET', '/secret-plans', $query));
        $this->assertEquals(200, $preview->status());
        $this->assertStringContains('Secret plans', $preview->content());
        $this->assertEquals('private, no-store, max-age=0', $preview->header('Cache-Control'));

        // A link is for one path, and expires.
        parse_str((string) parse_url($links->url('/about', time() + 3600), PHP_URL_QUERY), $other);
        $this->assertEquals(404, $app->handle(new Request('GET', '/secret-plans', $other))->status());
        parse_str((string) parse_url($links->url('/secret-plans', time() - 1), PHP_URL_QUERY), $expired);
        $this->assertEquals(404, $app->handle(new Request('GET', '/secret-plans', $expired))->status());

        // The original token form still works.
        $legacy = ['preview' => '1', 'token' => 'a-long-random-preview-secret'];
        $this->assertEquals(200, $app->handle(new Request('GET', '/secret-plans', $legacy))->status());
        $this->assertEquals(404, $app->handle(new Request('GET', '/secret-plans', ['preview' => '1', 'token' => 'wrong']))->status());
    }

    public function testPreviewsAreOffWithoutASecret(): void
    {
        $links = new PreviewLinks(null);

        $this->assertFalse($links->enabled());
        $this->assertFalse($links->allows(new Request('GET', '/x', ['preview' => '1', 'token' => '']), '/x'));
    }

    public function testUrlPathDecodingNeverCreatesSegments(): void
    {
        $this->assertEquals('/café', UrlPath::decode('/caf%C3%A9'));
        $this->assertEquals('/a%2Fb', UrlPath::decode('/a%2Fb'));
        $this->assertEquals('/a%00b', UrlPath::decode('/a%00b'));
        $this->assertEquals('/a%2Fb', UrlPath::canonical('/a%2fb'));
        $this->assertEquals('/a%20b', UrlPath::canonical('/a b'));
        $this->assertEquals('/caf%C3%A9', UrlPath::canonical('/café'));
        $this->assertEquals('/caf%C3%A9?x=%C3%A9', UrlPath::encodeForHeader('/café?x=%C3%A9'));
    }

    private function get(string $path, array $query = [], ?string $uri = null)
    {
        return $this->site->app()->handle(new Request('GET', $uri ?? $path, $query));
    }
}
