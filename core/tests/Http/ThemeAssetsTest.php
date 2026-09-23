<?php

declare(strict_types=1);

namespace Ava\Tests\Http;

use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Http\ThemeAssets;
use Ava\Testing\TestCase;

final class ThemeAssetsTest extends TestCase
{
    private string $directory;
    private ThemeAssets $assets;

    public function setUp(): void
    {
        $this->directory = AVA_ROOT . '/storage/tmp/test-assets-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0755, true);
        file_put_contents($this->directory . '/clip.mp4', '0123456789');
        file_put_contents($this->directory . '/style.css', 'a {}');
        $this->assets = new ThemeAssets($this->directory);
    }

    public function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testBodiesStreamFromDisk(): void
    {
        $response = $this->serve('/theme/clip.mp4');

        $this->assertTrue($response->isFile());
        $this->assertEquals('10', $response->header('Content-Length'));
        $this->assertEquals('0123456789', $response->content());
        $this->assertEquals('bytes', $response->header('Accept-Ranges'));
    }

    public function testByteRanges(): void
    {
        // Safari won't play video from a server that ignores Range.
        $partial = $this->serve('/theme/clip.mp4', ['Range' => 'bytes=2-5']);
        $this->assertEquals(206, $partial->status());
        $this->assertEquals('2345', $partial->content());
        $this->assertEquals('bytes 2-5/10', $partial->header('Content-Range'));

        $this->assertEquals('789', $this->serve('/theme/clip.mp4', ['Range' => 'bytes=-3'])->content());
        $this->assertEquals('89', $this->serve('/theme/clip.mp4', ['Range' => 'bytes=8-'])->content());
        $this->assertEquals(416, $this->serve('/theme/clip.mp4', ['Range' => 'bytes=20-30'])->status());
        // Several ranges may be answered with the whole file.
        $this->assertEquals(200, $this->serve('/theme/clip.mp4', ['Range' => 'bytes=0-1,4-5'])->status());
        // A stale If-Range gets the whole (new) file.
        $this->assertEquals(200, $this->serve('/theme/clip.mp4', ['Range' => 'bytes=0-1', 'If-Range' => '"old"'])->status());
    }

    public function testConditionalRequests(): void
    {
        $etag = $this->serve('/theme/style.css')->header('ETag');

        $this->assertEquals(304, $this->serve('/theme/style.css', ['If-None-Match' => $etag])->status());
        $this->assertEquals(304, $this->serve('/theme/style.css', ['If-None-Match' => '"x", W/' . $etag])->status());
        // If-None-Match takes precedence over If-Modified-Since.
        $this->assertEquals(200, $this->serve('/theme/style.css', [
            'If-None-Match' => '"other"',
            'If-Modified-Since' => gmdate('D, d M Y H:i:s', time() + 60) . ' GMT',
        ])->status());
    }

    public function testOnlyVersionedUrlsAreCachedForever(): void
    {
        $this->assertStringContains('immutable', (string) $this->serve('/theme/style.css', [], ['v' => '123'])->header('Cache-Control'));
        $this->assertStringNotContains('immutable', (string) $this->serve('/theme/style.css')->header('Cache-Control'));
    }

    public function testEncodedNamesAndTraversal(): void
    {
        file_put_contents($this->directory . '/my font.woff2', 'font');

        $this->assertEquals('font', $this->serve('/theme/my%20font.woff2')->content());
        $this->assertNull($this->assets->serve(new Request('GET', '/theme/..%2F..%2Fbootstrap.php')));
        $this->assertNull($this->assets->serve(new Request('GET', '/theme/%2e%2e/%2e%2e/composer.json')));
        $this->assertNull($this->assets->serve(new Request('POST', '/theme/style.css')));
    }

    public function testResponsesCarrySeveralValuesPerHeader(): void
    {
        $response = (new Response('ok'))
            ->withAddedHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        $this->assertEquals(['a=1', 'b=2'], $response->headerValues('set-cookie'));
        $this->assertEquals('a=1, b=2', $response->header('Set-Cookie'));
        $this->assertEquals(['c=3'], $response->withHeader('Set-Cookie', 'c=3')->headerValues('Set-Cookie'));
        $this->assertEquals(['x', 'y'], (new Response('', 200, ['Link' => ['x', 'y']]))->headerValues('Link'));
    }

    private function serve(string $path, array $headers = [], array $query = []): Response
    {
        $response = $this->assets->serve(new Request('GET', $path, $query, $headers));
        $this->assertNotNull($response, $path);

        return $response;
    }
}
