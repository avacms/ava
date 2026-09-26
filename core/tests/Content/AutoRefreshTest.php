<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Http\Request;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

/**
 * How automatic index mode behaves across requests.
 */
final class AutoRefreshTest extends TestCase
{
    private TempSite $site;

    public function setUp(): void
    {
        $this->site = new TempSite($this->app->allConfig());
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Original text');
    }

    public function tearDown(): void
    {
        $this->site->remove();
    }

    public function testFirstRequestBuildsEvenInManualMode(): void
    {
        $response = $this->site->app(['content_index' => ['mode' => 'never']])->handle(new Request('GET', '/about'));

        $this->assertEquals(200, $response->status());
    }

    public function testContentEditsArePickedUpByTheNextRequest(): void
    {
        $this->assertStringContains('Original text', $this->request('/about')->content());
        $generation = $this->generation();

        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Edited text');

        $this->assertStringContains('Edited text', $this->request('/about')->content());
        $this->assertNotEquals($generation, $this->generation());
    }

    public function testPresentationChangesClearCachedPagesWithoutARebuild(): void
    {
        $this->assertEquals('MISS', $this->request('/about')->header('X-Page-Cache'));
        $this->assertEquals('HIT', $this->request('/about')->header('X-Page-Cache'));
        $generation = $this->generation();

        // redirects.json only affects routing, which cached pages bypass.
        file_put_contents($this->site->root . '/storage/redirects.json', '[]');

        $this->assertEquals('MISS', $this->request('/about')->header('X-Page-Cache'));
        $this->assertEquals($generation, $this->generation(), 'no rebuild for a presentation change');
    }

    public function testVisitorsKeepTheLiveIndexWhileAnotherProcessRebuilds(): void
    {
        $this->request('/about');
        $generation = $this->generation();
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Edited text');

        $store = $this->site->app()->indexStore();
        $store->withRebuildLock(function () use ($generation): void {
            $started = microtime(true);
            $response = $this->request('/about');

            $this->assertStringContains('Original text', $response->content());
            $this->assertEquals($generation, $this->generation());
            $this->assertLessThan(1.0, microtime(true) - $started, 'the request must not wait for the lock');
        });

        $this->assertStringContains('Edited text', $this->request('/about')->content());
    }

    public function testQuickRebuildsStillHappenInTheRequest(): void
    {
        $this->request('/about');
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Edited text');

        $app = $this->site->app();
        $app->enableDeferredTasks();

        $this->assertStringContains('Edited text', $app->handle($this->aboutRequest())->content());
    }

    public function testSlowRebuildsRunAfterTheResponse(): void
    {
        $this->request('/about');
        $state = $this->site->root . '/storage/cache/state.json';
        file_put_contents($state, json_encode(['build_seconds' => 5.0] + json_decode(file_get_contents($state), true)));
        $generation = $this->generation();
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Edited text');

        $app = $this->site->app();
        $app->enableDeferredTasks();
        $this->assertStringContains('Original text', $app->handle($this->aboutRequest())->content());
        $this->assertEquals($generation, $this->generation(), 'the rebuild waits for terminate()');

        $app->terminate();
        $this->assertNotEquals($generation, $this->generation());
        $this->assertStringContains('Edited text', $this->request('/about')->content());
    }

    public function testOnlyOneRequestChecksForChangesAtATime(): void
    {
        $this->request('/about');
        $this->site->page('pages/new.md', ['title' => 'New', 'status' => 'published']);

        $this->site->app()->indexStore()->withCheckLock(function (): void {
            $this->assertEquals(404, $this->request('/new')->status(), 'another request is already checking');
        });

        $this->assertEquals(200, $this->request('/new')->status());
    }

    public function testAFailedRebuildKeepsServingThePreviousIndexAndBacksOff(): void
    {
        $this->request('/about');
        $generation = $this->generation();
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Edited text');

        $broken = ['content_index' => ['backend' => 'not-a-backend']];
        $errorLog = ini_get('error_log');
        ini_set('error_log', $this->site->root . '/storage/logs/php-errors.log');

        try {
            $response = $this->site->app($broken)->handle(new Request('GET', '/about'));
            $this->assertEquals(200, $response->status());
            $this->assertEquals($generation, $this->generation());
            $this->assertTrue(is_file($this->site->root . '/storage/cache/.rebuild-attempt'));

            // The same sources are not retried on every request.
            $attempt = file_get_contents($this->site->root . '/storage/cache/.rebuild-attempt');
            sleep(1);
            $this->site->app($broken)->handle(new Request('GET', '/about'));
            $this->assertEquals($attempt, file_get_contents($this->site->root . '/storage/cache/.rebuild-attempt'));

            $log = (string) file_get_contents($this->site->root . '/storage/logs/php-errors.log');
            $this->assertStringContains('still serving the previous index', $log);
        } finally {
            ini_set('error_log', $errorLog === false ? '' : $errorLog);
        }

        // Fixing the problem (in real life by editing the config file, which
        // changes the sources) is picked up immediately and clears the marker.
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published'], 'Final text');
        $this->assertStringContains('Final text', $this->request('/about')->content());
        $this->assertFalse(is_file($this->site->root . '/storage/cache/.rebuild-attempt'));
    }

    public function testChecksAreThrottled(): void
    {
        // A single page is always read from its file, so edits to it show at
        // once. What the throttle delays is the index: a new page stays
        // unroutable until the next check.
        $throttled = ['content_index' => ['check_interval' => 60]];
        $this->site->app($throttled)->handle(new Request('GET', '/about'));
        $this->site->page('pages/new.md', ['title' => 'New', 'status' => 'published']);

        $this->assertEquals(404, $this->site->app($throttled)->handle(new Request('GET', '/new'))->status());
        $this->assertEquals(200, $this->site->app()->handle(new Request('GET', '/new'))->status());
    }

    private function request(string $path)
    {
        return $this->site->app()->handle(new Request('GET', $path, [], ['Host' => 'example.test']));
    }

    private function aboutRequest(): Request
    {
        return new Request('GET', '/about', [], ['Host' => 'example.test']);
    }

    private function generation(): ?string
    {
        return $this->site->app()->indexStore()->state()['generation'] ?? null;
    }
}
