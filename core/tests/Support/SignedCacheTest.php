<?php

declare(strict_types=1);

namespace Ava\Tests\Support;

use Ava\Support\SignedCache;
use Ava\Support\SignedCacheException;
use Ava\Testing\TestCase;

final class SignedCacheTest extends TestCase
{
    private string $directory;

    public function setUp(): void
    {
        $this->directory = AVA_ROOT . '/storage/tmp/test-signed-cache-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    public function tearDown(): void
    {
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testRoundTripPreservesNestedValuesAndSigningKey(): void
    {
        $data = ['items' => [['title' => 'Café', 'enabled' => true, 'count' => 2]], 'missing' => null];
        $first = $this->directory . '/first.bin';
        SignedCache::write($first, $data, false);
        $key = file_get_contents($this->directory . '/.cache_key');
        SignedCache::write($this->directory . '/second.bin', ['other'], false);
        $this->assertEquals($data, SignedCache::read($first));
        $this->assertEquals($key, file_get_contents($this->directory . '/.cache_key'));
    }

    public function testMissingFileIsACacheMissAndReadersNeverCreateKeys(): void
    {
        $this->assertNull(SignedCache::read($this->directory . '/data.bin'));
        $this->assertFalse(file_exists($this->directory . '/.cache_key'));
    }

    public function testTamperingAndMissingKeysFailLoudly(): void
    {
        // A cache that exists but can't be trusted must not read as "empty":
        // that turned a permissions mistake into a site that 404s silently.
        $file = $this->directory . '/data.bin';
        SignedCache::write($file, ['secret'], false);
        file_put_contents($file, file_get_contents($file) . 'tampered');
        $this->assertThrows(SignedCacheException::class, fn() => SignedCache::read($file));

        unlink($this->directory . '/.cache_key');
        $this->assertThrows(SignedCacheException::class, fn() => SignedCache::read($file));
        $this->assertFalse(file_exists($this->directory . '/.cache_key'));
    }

    public function testUnreadableKeyExplainsOwnership(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markSkipped('root can read any file');
        }

        $file = $this->directory . '/data.bin';
        SignedCache::write($file, ['value'], false);
        chmod($this->directory . '/.cache_key', 0000);

        try {
            $this->assertFalse(SignedCache::keyIsReadable($this->directory));
            SignedCache::read($file);
            $this->fail('Expected an unreadable key to throw');
        } catch (SignedCacheException $e) {
            $this->assertStringContains('signing key', $e->getMessage());
            $this->assertStringContains('different user', $e->getMessage());
        } finally {
            chmod($this->directory . '/.cache_key', 0600);
        }
    }

    public function testNewKeysAreGroupReadable(): void
    {
        SignedCache::write($this->directory . '/data.bin', [], false);

        $this->assertEquals(0640, fileperms($this->directory . '/.cache_key') & 0777);
    }

    public function testKeyCanLiveOutsideTheCacheFilesDirectory(): void
    {
        mkdir($this->directory . '/generation');
        $file = $this->directory . '/generation/data.bin';

        SignedCache::write($file, ['shared' => true], false, $this->directory);

        $this->assertFalse(file_exists($this->directory . '/generation/.cache_key'));
        $this->assertEquals(['shared' => true], SignedCache::read($file, $this->directory));
        unlink($file);
        rmdir($this->directory . '/generation');
    }

    public function testSignedNonArrayMalformedAndUnknownPayloadsAreRejected(): void
    {
        $file = $this->directory . '/data.bin';
        SignedCache::write($file, [], false);
        $key = file_get_contents($this->directory . '/.cache_key');
        foreach (['SZ:' . serialize('text'), 'SZ:' . serialize(new \stdClass()), 'SZ:broken', 'XX:' . serialize([])] as $payload) {
            file_put_contents($file, hash_hmac('sha256', $payload, $key, true) . $payload);
            $this->assertThrows(SignedCacheException::class, fn() => SignedCache::read($file));
        }
    }

    public function testIgbinaryUsesTheSameValidationBoundary(): void
    {
        if (!function_exists('igbinary_serialize')) {
            $this->markSkipped('igbinary is unavailable');
        }
        $file = $this->directory . '/data.bin';
        SignedCache::write($file, ['format' => 'igbinary']);
        $this->assertEquals(['format' => 'igbinary'], SignedCache::read($file));
        $key = file_get_contents($this->directory . '/.cache_key');
        $payload = 'IG:' . igbinary_serialize('not an array');
        file_put_contents($file, hash_hmac('sha256', $payload, $key, true) . $payload);
        $this->assertThrows(SignedCacheException::class, fn() => SignedCache::read($file));
    }
}
