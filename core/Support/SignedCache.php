<?php

declare(strict_types=1);

namespace Ava\Support;

/**
 * One format and validation boundary for binary array caches.
 *
 * A missing file is an ordinary cache miss (null). Anything else that stops a
 * file being read (an unreadable signing key, a bad signature, a payload that
 * will not decode) throws, because treating it as "empty" turns a permissions
 * mistake into a site that silently 404s every page.
 *
 * Readers never create or rotate signing keys.
 */
final class SignedCache
{
    public const KEY_FILE = '.cache_key';

    /** @var array<string, string> Signing keys already loaded, by directory */
    private static array $writerKeys = [];

    /**
     * @param string|null $keyDirectory Directory holding the signing key;
     *                                  defaults to the cache file's directory.
     * @return array|null Null when the file does not exist.
     * @throws SignedCacheException
     */
    public static function read(string $path, ?string $keyDirectory = null): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new SignedCacheException('Cannot read cache file ' . $path . self::ownershipHint($path));
        }

        try {
            $key = self::readKey($keyDirectory ?? dirname($path));
            $mac = fread($handle, 32);
            if (!is_string($mac) || strlen($mac) < 32) {
                throw new SignedCacheException('Cache file is truncated: ' . $path);
            }

            // Verify by streaming, then read the payload once: substr() copies
            // would hold a large index in memory three times over.
            $context = hash_init('sha256', HASH_HMAC, $key);
            hash_update_stream($context, $handle);
            if (!hash_equals(hash_final($context, true), $mac)) {
                throw new SignedCacheException('Cache file signature does not match: ' . $path);
            }

            fseek($handle, 32);
            $format = fread($handle, 3);
            $payload = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        try {
            $data = match ($format) {
                'SZ:' => @unserialize((string) $payload, ['allowed_classes' => false]),
                'IG:' => function_exists('igbinary_unserialize') ? @igbinary_unserialize((string) $payload) : null,
                default => null,
            };
        } catch (\Throwable) {
            $data = null;
        }
        unset($payload);

        if (!is_array($data)) {
            throw new SignedCacheException(
                'Cache file could not be decoded (was it written with igbinary on another server?): ' . $path
            );
        }

        return $data;
    }

    /**
     * @param string|null $keyDirectory Directory holding the signing key;
     *                                  defaults to the cache file's directory.
     * @param bool $atomic Write via a temporary file and rename. Only files
     *                     nobody can be reading yet (inside an unpublished
     *                     index generation) may skip this.
     */
    public static function write(
        string $path,
        array $data,
        bool $useIgbinary = true,
        ?string $keyDirectory = null,
        bool $atomic = true
    ): void {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create binary cache directory.');
        }

        $payload = $useIgbinary && function_exists('igbinary_serialize')
            ? 'IG:' . igbinary_serialize($data)
            : 'SZ:' . serialize($data);
        $keyDirectory ??= $directory;
        $key = self::$writerKeys[$keyDirectory] ??= self::signingKey($keyDirectory);
        $contents = hash_hmac('sha256', $payload, $key, true) . $payload;

        $written = $atomic
            ? AtomicFile::write($path, $contents)
            : @file_put_contents($path, $contents) === strlen($contents);
        if (!$written) {
            throw new \RuntimeException('Unable to publish binary cache: ' . basename($path));
        }
    }

    /**
     * Forget loaded keys (after a key file is deleted, e.g. in tests).
     */
    public static function forgetKeys(): void
    {
        self::$writerKeys = [];
    }

    /**
     * Can the web server read the signing key? Cheap enough to ask per request.
     */
    public static function keyIsReadable(string $keyDirectory): bool
    {
        $path = $keyDirectory . '/' . self::KEY_FILE;

        return !is_file($path) || is_readable($path);
    }

    /**
     * Explain an unreadable key in terms an operator can act on.
     */
    public static function describeUnreadableKey(string $keyDirectory): string
    {
        $path = $keyDirectory . '/' . self::KEY_FILE;

        return 'Cannot read the cache signing key ' . $path . self::ownershipHint($path)
            . '. It was probably created by a different user (for example `sudo ./ava rebuild`).'
            . ' Rebuild as the web server user, or delete storage/cache and rebuild.';
    }

    private static function readKey(string $keyDirectory): string
    {
        $path = $keyDirectory . '/' . self::KEY_FILE;
        $key = @file_get_contents($path);
        if ($key === false) {
            throw new SignedCacheException(
                is_file($path) ? self::describeUnreadableKey($keyDirectory) : 'Cache signing key is missing: ' . $path
            );
        }
        if (strlen($key) !== 32) {
            throw new SignedCacheException('Cache signing key is invalid; delete storage/cache and rebuild: ' . $path);
        }

        return $key;
    }

    private static function signingKey(string $directory): string
    {
        $path = $directory . '/' . self::KEY_FILE;
        $file = @fopen($path, 'c+b');
        if ($file === false) {
            throw new \RuntimeException(
                is_file($path) ? self::describeUnreadableKey($directory) : 'Unable to open cache signing key: ' . $path
            );
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock cache signing key.');
            }
            $key = stream_get_contents($file);
            if (is_string($key) && strlen($key) === 32) {
                return $key;
            }
            if ($key !== '') {
                throw new \RuntimeException('Invalid cache signing key; remove the cache and rebuild.');
            }

            // Group-readable, so a CLI user and the PHP-FPM user that share a
            // group can both use it. Other cache files are world-readable.
            $key = random_bytes(32);
            if (!chmod($path, 0640) || fwrite($file, $key) !== 32 || !fflush($file)) {
                throw new \RuntimeException('Unable to create cache signing key.');
            }
            return $key;
        } finally {
            fclose($file);
        }
    }

    private static function ownershipHint(string $path): string
    {
        $owner = @fileowner($path);
        $perms = @fileperms($path);
        if ($owner === false || $perms === false) {
            return '';
        }

        $hint = sprintf(' (owner uid %d, mode %04o', $owner, $perms & 0777);
        if (function_exists('posix_geteuid')) {
            $hint .= sprintf('; this process runs as uid %d', posix_geteuid());
        }

        return $hint . ')';
    }
}
