<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Http\Request;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

/**
 * Content problems that used to take a whole site down.
 */
final class IndexingRegressionTest extends TestCase
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

    public function testUnquotedNumericTitleIsIndexedAndWarnedAbout(): void
    {
        // `title: 1984` is an int to YAML; it used to throw a TypeError in
        // the indexer and 500 every page on the site.
        $this->site->page('posts/film.md', ['title' => 1984, 'slug' => 'film', 'status' => 'published', 'date' => '2026-01-01']);
        $this->site->page('pages/about.md', ['title' => 'About', 'status' => 'published']);
        $app = $this->site->app();

        $this->assertEquals(200, $app->handle(new Request('GET', '/about'))->status());
        $film = $this->site->app()->handle(new Request('GET', '/blog/film'));
        $this->assertEquals(200, $film->status());
        $this->assertStringContains('1984', $film->content());

        $warnings = implode("\n", $this->site->app()->indexer()->lint()['warnings']);
        $this->assertStringContains('film.md', $warnings);
        $this->assertStringContains("'title'", $warnings);
    }

    public function testBrokenFilesAreReportedAndSkipped(): void
    {
        $this->site->write('posts/broken.md', "---\ntitle: [unclosed\n---\nBody\n");
        $this->site->write('posts/scalar.md', "---\njust a sentence\n---\nBody\n");
        $this->site->page('posts/fine.md', ['title' => 'Fine', 'slug' => 'fine', 'status' => 'published']);
        $app = $this->site->app();

        $this->assertEquals(200, $app->handle(new Request('GET', '/blog/fine'))->status());

        $log = (string) file_get_contents($this->site->root . '/storage/logs/indexer.log');
        $this->assertStringContains('broken.md', $log);
        $this->assertStringContains('scalar.md', $log);
        $this->assertStringContains('key: value', $log);
    }

    public function testNonTextFieldsAreLintErrors(): void
    {
        $this->site->write('posts/listy.md', "---\ntitle: [a, b]\nslug: listy\nstatus: published\n---\n");

        $errors = implode("\n", $this->site->app()->indexer()->lint()['errors']);

        $this->assertStringContains("Field 'title' must be text", $errors);
    }

    public function testNumericAndSpacedTermsAreReachable(): void
    {
        $this->site->page('posts/tagged.md', [
            'title' => 'Tagged', 'slug' => 'tagged', 'status' => 'published',
            'category' => 'Tutorials', 'tag' => ['2024', 'Web Dev'],
        ]);
        $app = $this->site->app();

        $this->assertEquals(200, $app->handle(new Request('GET', '/category/tutorials'))->status());
        $this->assertEquals(200, $app->handle(new Request('GET', '/tag/2024'))->status());
        $this->assertEquals(200, $app->handle(new Request('GET', '/tag/web-dev'))->status());
        $this->assertStringContains('Tagged', $app->handle(new Request('GET', '/tag/web-dev'))->content());

        $redirect = $app->handle(new Request('GET', '/category/Tutorials'));
        $this->assertEquals(301, $redirect->status());
        $this->assertEquals('/category/tutorials', $redirect->header('Location'));

        $post = $app->handle(new Request('GET', '/blog/tagged'))->content();
        $this->assertStringContains('href="/tag/web-dev"', $post);
        $this->assertStringContains('href="/category/tutorials"', $post);
    }

    public function testDuplicateKeysAreDeterministic(): void
    {
        $this->site->page('posts/a.md', ['title' => 'First', 'slug' => 'same', 'status' => 'published']);
        $this->site->page('posts/b.md', ['title' => 'Second', 'slug' => 'same', 'status' => 'published']);

        $content = $this->site->app()->handle(new Request('GET', '/blog/same'))->content();

        $this->assertStringContains('First', $content);
        $errors = implode("\n", $this->site->app()->indexer()->lint()['errors']);
        $this->assertStringContains("Duplicate content key 'same'", $errors);
    }
}
