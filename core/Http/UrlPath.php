<?php

declare(strict_types=1);

namespace Ava\Http;

use Ava\Support\Str;

/**
 * Percent-encoding rules for request paths.
 *
 * Browsers send "/café" as "/caf%C3%A9". Routes are stored decoded, so the
 * router matches against decode(). Because "/%61bout", "/about" and
 * "/caf%c3%a9" would otherwise each be a distinct URL for the same page (and
 * a distinct webpage-cache entry), the router redirects any path that is not
 * in canonical() form: unreserved characters plain, everything else encoded
 * with uppercase hex, Unicode in NFC.
 *
 * An encoded "/" (%2F) or NUL is never decoded, so it can't create path
 * segments or smuggle bytes past file checks.
 */
final class UrlPath
{
    /** RFC 3986 pchar, minus percent-encoded octets. */
    private const PLAIN = '/[^A-Za-z0-9\-._~!$&\'()*+,;=:@]/';

    public static function decode(string $raw): string
    {
        if (!str_contains($raw, '%')) {
            return $raw;
        }

        return implode('/', array_map(self::decodeSegment(...), explode('/', $raw)));
    }

    public static function canonical(string $raw): string
    {
        return implode('/', array_map(
            static function (string $segment): string {
                $decoded = self::decodeSegment($segment);
                if ($decoded === $segment && !str_contains($segment, '%') && preg_match(self::PLAIN, $segment) !== 1) {
                    return $segment;
                }

                // A segment kept encoded because it hides "/" or NUL is only
                // normalised to uppercase hex.
                if (str_contains(rawurldecode($segment), '/') || str_contains(rawurldecode($segment), "\0")) {
                    return preg_replace_callback('/%[0-9a-fA-F]{2}/', static fn($m) => strtoupper($m[0]), $segment) ?? $segment;
                }

                return self::encodeSegment($decoded);
            },
            explode('/', $raw)
        ));
    }

    /**
     * Make a URL safe for a header value (e.g. Location): bytes outside
     * printable ASCII are percent-encoded; existing escapes are kept.
     */
    public static function encodeForHeader(string $url): string
    {
        return preg_replace_callback(
            '/[^\x21-\x7E]/',
            static fn(array $match): string => sprintf('%%%02X', ord($match[0])),
            $url
        ) ?? $url;
    }

    private static function decodeSegment(string $segment): string
    {
        if (!str_contains($segment, '%')) {
            return $segment;
        }

        $decoded = rawurldecode($segment);
        if (str_contains($decoded, '/') || str_contains($decoded, "\0")) {
            return $segment;
        }

        return mb_check_encoding($decoded, 'UTF-8') ? Str::nfc($decoded) : $decoded;
    }

    private static function encodeSegment(string $decoded): string
    {
        return preg_replace_callback(
            self::PLAIN,
            static fn(array $match): string => sprintf('%%%02X', ord($match[0])),
            $decoded
        ) ?? $decoded;
    }
}
