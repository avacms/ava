<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Index\Fingerprint;
use Ava\Testing\TestCase;

final class FingerprintTest extends TestCase
{
    private string $directory;

    public function setUp(): void
    {
        $this->directory = AVA_ROOT . '/storage/tmp/test-fingerprint-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/content', 0755, true);
        mkdir($this->directory . '/theme', 0755, true);
    }

    public function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testUnchangedSourcesReportNoChanges(): void
    {
        file_put_contents($this->directory . '/content/b.md', 'B');
        file_put_contents($this->directory . '/content/a.md', 'A');
        $fingerprint = $this->fingerprint();

        $this->assertEquals([], $fingerprint->changes($fingerprint->capture()));
        $this->assertEquals(2, $fingerprint->capture()['sources']['content']['count']);
    }

    public function testSameSizeEditWithinTheSameSecondIsDetected(): void
    {
        // Metadata alone cannot see this edit (same size, same mtime, same
        // second), so the snapshot must have hashed the recently written file.
        $path = $this->directory . '/content/page.md';
        file_put_contents($path, 'draft');
        $fingerprint = $this->fingerprint();
        $snapshot = $fingerprint->capture();
        $mtime = filemtime($path);

        file_put_contents($path, 'final');
        touch($path, $mtime);
        clearstatcache();

        $this->assertEquals([Fingerprint::SCOPE_INDEX], $fingerprint->changes($snapshot));
    }

    public function testBackdatedEditIsDetectedThroughCtime(): void
    {
        $path = $this->directory . '/content/page.md';
        file_put_contents($path, 'draft');
        touch($path, 1_700_000_000);
        $fingerprint = $this->fingerprint();
        $snapshot = $fingerprint->capture();
        $snapshot['racy'] = []; // Prove metadata alone catches it.

        sleep(1);
        file_put_contents($path, 'final');
        touch($path, 1_700_000_000); // rsync -t style: old mtime, same size

        $this->assertEquals([Fingerprint::SCOPE_INDEX], $fingerprint->changes($snapshot));
    }

    public function testPresentationChangesAreReportedSeparately(): void
    {
        file_put_contents($this->directory . '/content/page.md', 'content');
        file_put_contents($this->directory . '/theme/style.css', 'a {}');
        $fingerprint = $this->fingerprint();
        $snapshot = $fingerprint->capture();

        file_put_contents($this->directory . '/theme/style.css', 'a { color: red }');

        $this->assertEquals([Fingerprint::SCOPE_PRESENTATION], $fingerprint->changes($snapshot));
    }

    public function testRemovedSourceChangesItsScope(): void
    {
        $snapshot = $this->fingerprint()->capture();
        $withoutTheme = new Fingerprint([
            'content' => ['path' => $this->directory . '/content', 'scope' => Fingerprint::SCOPE_INDEX],
        ]);

        $this->assertEquals([Fingerprint::SCOPE_PRESENTATION], $withoutTheme->changes($snapshot));
    }

    public function testDotDirectoriesAreIgnored(): void
    {
        file_put_contents($this->directory . '/content/page.md', 'content');
        $fingerprint = $this->fingerprint();
        $snapshot = $fingerprint->capture();

        // A content directory kept in git must not rebuild on every commit.
        mkdir($this->directory . '/content/.git/objects', 0755, true);
        file_put_contents($this->directory . '/content/.git/objects/abc', 'object');
        file_put_contents($this->directory . '/content/.DS_Store', 'finder');

        $this->assertEquals([], $fingerprint->changes($snapshot));
    }

    public function testOlderSnapshotVersionsRequireARebuild(): void
    {
        $snapshot = $this->fingerprint()->capture();
        $snapshot['version'] = Fingerprint::VERSION - 1;

        $this->assertEquals([Fingerprint::SCOPE_INDEX], $this->fingerprint()->changes($snapshot));
    }

    public function testApplicationFingerprintScopesItsSources(): void
    {
        $capture = $this->app->indexer()->fingerprint()->capture();
        $sources = $capture['sources'];

        foreach (['content', 'config', 'theme-bootstrap'] as $name) {
            $this->assertEquals(Fingerprint::SCOPE_INDEX, $sources[$name]['scope'] ?? null, $name);
        }
        foreach (['theme', 'snippets', 'redirects'] as $name) {
            $this->assertEquals(Fingerprint::SCOPE_PRESENTATION, $sources[$name]['scope'] ?? null, $name);
        }
        foreach ($this->app->pluginNames() as $plugin) {
            $this->assertEquals(Fingerprint::SCOPE_INDEX, $sources['plugin:' . $plugin]['scope'] ?? null, $plugin);
        }
    }

    private function fingerprint(): Fingerprint
    {
        return new Fingerprint([
            'content' => ['path' => $this->directory . '/content', 'scope' => Fingerprint::SCOPE_INDEX],
            'theme' => ['path' => $this->directory . '/theme', 'scope' => Fingerprint::SCOPE_PRESENTATION],
        ]);
    }
}
