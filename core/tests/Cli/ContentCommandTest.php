<?php

declare(strict_types=1);

namespace Ava\Tests\Cli;

use Ava\Cli\Commands\ContentCommand;
use Ava\Cli\Output;
use Ava\Content\Parser;
use Ava\Testing\TempSite;
use Ava\Testing\TestCase;

final class ContentCommandTest extends TestCase
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

    public function testMadeContentKeepsTitlesWithYamlSyntax(): void
    {
        foreach (["PHP 8.5: What's New" => 'php-85-whats-new', 'Why PHP? #1 reason' => 'why-php-1-reason'] as $title => $slug) {
            $this->assertSame(0, $this->run(['post', $title]), $title);

            $item = (new Parser())->parseFile($this->site->root . "/content/posts/{$slug}.md", 'post');
            $this->assertSame($title, $item->title());
            $this->assertSame('draft', $item->status());
        }
    }

    public function testTitlesWithoutLettersOrDigitsAreRejected(): void
    {
        $this->assertSame(1, $this->run(['post', '!!!']));
        $this->assertSame([], glob($this->site->root . '/content/posts/*') ?: []);
    }

    private function run(array $args): int
    {
        $app = $this->site->app(['cli' => ['colors' => false]]);

        ob_start();
        try {
            return (new ContentCommand(new Output($app), $app))->make($args);
        } finally {
            ob_end_clean();
        }
    }
}
