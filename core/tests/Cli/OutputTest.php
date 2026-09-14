<?php

declare(strict_types=1);

namespace Ava\Tests\Cli;

use Ava\Application;
use Ava\Cli\Output;
use Ava\Testing\TestCase;

final class OutputTest extends TestCase
{
    public function testConfirmUsesDefaultAtEofAndAcceptsYes(): void
    {
        $config = $this->app->allConfig();
        $config['cli']['colors'] = false;
        $output = new Output(new Application($config));

        $emptyInput = fopen('php://memory', 'r');
        $yesInput = fopen('php://memory', 'r+');
        fwrite($yesInput, "y\n");
        rewind($yesInput);

        ob_start();
        $this->assertFalse($output->confirm('Continue?', input: $emptyInput));
        $this->assertTrue($output->confirm('Continue?', input: $yesInput));
        ob_end_clean();

        fclose($emptyInput);
        fclose($yesInput);
    }

    public function testOutputCommandItemKeepsColumnAlignmentAndLongCommandGutter(): void
    {
        $config = $this->app->allConfig();
        $config['cli']['colors'] = false;
        $output = new Output(new Application($config));

        ob_start();
        $output->commandItem('status', 'Short description');
        $output->commandItem('rebuild [--keep-webpage-cache]', 'Long description');
        $rendered = ob_get_clean();

        $this->assertSame(
            "    status                        Short description\n"
            . "    rebuild [--keep-webpage-cache] Long description\n",
            $rendered
        );
    }
}