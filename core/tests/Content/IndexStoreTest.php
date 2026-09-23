<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Index\IndexStore;
use Ava\Testing\TestCase;

final class IndexStoreTest extends TestCase
{
    private string $storage;

    public function setUp(): void
    {
        $this->storage = AVA_ROOT . '/storage/tmp/test-index-store-' . bin2hex(random_bytes(6));
        mkdir($this->storage, 0755, true);
    }

    public function tearDown(): void
    {
        if (!is_dir($this->storage)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->storage, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->storage);
    }

    public function testNothingIsLiveUntilPublished(): void
    {
        $store = new IndexStore($this->storage);
        [$generation, $path] = $store->createGeneration();
        $store->writeBinary($path, 'routes.bin', ['exact' => []], false);

        $this->assertNull($store->reloadState());

        $store->publish($generation, 'array', ['version' => 5, 'sources' => []]);

        $this->assertEquals($path, (new IndexStore($this->storage))->currentPath());
        $this->assertEquals(['exact' => []], $store->readBinary($path, 'routes.bin'));
    }

    public function testReadersAreNotBlockedWhileARebuildHoldsTheLock(): void
    {
        $store = new IndexStore($this->storage);
        [$generation] = $store->createGeneration();
        $store->publish($generation, 'array', []);

        $ranInside = false;
        $store->withRebuildLock(function () use (&$ranInside, $generation): void {
            // Another process during the build: it reads the live
            // generation, and its own attempt to rebuild is turned away
            // immediately instead of queueing.
            $other = new IndexStore($this->storage);
            $this->assertEquals($generation, $other->state()['generation'] ?? null);
            $this->assertFalse($other->withRebuildLock(fn() => null, wait: false));
            $ranInside = true;
        });

        $this->assertTrue($ranInside);
        $this->assertTrue((new IndexStore($this->storage))->withRebuildLock(fn() => null, wait: false));
    }

    public function testReplacedGenerationsSurviveTheirGracePeriodThenGo(): void
    {
        $store = new IndexStore($this->storage);
        [$first, $firstPath] = $store->createGeneration();
        $store->publish($first, 'array', []);
        [$second, $secondPath] = $store->createGeneration();
        $store->publish($second, 'array', []);

        // Requests that started on the first generation can still read it.
        $store->collectGarbage();
        $this->assertTrue(is_dir($firstPath));

        touch($firstPath . '/.retired', time() - 3600);
        $store->collectGarbage();
        $this->assertFalse(is_dir($firstPath));
        $this->assertTrue(is_dir($secondPath));
    }

    public function testDebrisFromACrashedBuildIsRemoved(): void
    {
        $store = new IndexStore($this->storage);
        [$live] = $store->createGeneration();
        $store->publish($live, 'array', []);
        [, $crashed] = $store->createGeneration();

        $store->collectGarbage();

        $this->assertFalse(is_dir($crashed));
    }

    public function testStateThatPointsNowhereIsIgnored(): void
    {
        mkdir($this->storage . '/cache', 0755, true);
        file_put_contents($this->storage . '/cache/state.json', json_encode([
            'version' => IndexStore::STATE_VERSION,
            'generation' => '../../escape',
            'backend' => 'array',
            'fingerprint' => [],
        ]));

        $this->assertNull((new IndexStore($this->storage))->state());
    }

    public function testFailedAttemptsBackOffOnlyForTheSameSources(): void
    {
        $store = new IndexStore($this->storage);
        $store->recordAttempt('sources-a');

        $this->assertTrue($store->recentlyFailed('sources-a'));
        $this->assertFalse($store->recentlyFailed('sources-b'));

        $store->clearAttempt();
        $this->assertFalse($store->recentlyFailed('sources-a'));
    }

    public function testCheckThrottle(): void
    {
        $store = new IndexStore($this->storage);
        mkdir($this->storage . '/cache', 0755, true);

        $this->assertFalse($store->checkedWithin(60));
        $store->markChecked();
        $this->assertTrue($store->checkedWithin(60));
        $this->assertFalse($store->checkedWithin(0));
    }
}
