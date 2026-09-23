<?php

declare(strict_types=1);

namespace Ava\Http;

/**
 * Serves files from the active theme's assets/ directory at /theme/...
 *
 * Runs before plugins load or the index is checked: an asset depends on
 * nothing but the file. Only allowlisted extensions are served, dotfiles never
 * are, and the resolved path must stay inside assets/. Bodies stream from
 * disk, conditional requests get 304s, and single byte ranges are honoured
 * (Safari will not play video from a server without them).
 */
final class ThemeAssets
{
    public const PREFIX = '/theme/';

    /** Extension => MIME type. Anything else is never served. */
    public const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'avif' => 'image/avif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'm4a' => 'audio/mp4',
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
    ];

    public function __construct(private string $assetsDirectory)
    {
    }

    /**
     * A response for the requested asset, or null when there is none to
     * serve (the caller answers with its normal 404).
     */
    public function serve(Request $request): ?Response
    {
        $path = $request->path();
        if (!str_starts_with($path, self::PREFIX) || !in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $file = $this->resolve(rawurldecode(substr($path, strlen(self::PREFIX))));
        if ($file === null) {
            return null;
        }

        $stat = @stat($file);
        if ($stat === false) {
            return null;
        }

        $size = (int) $stat['size'];
        $mtime = (int) $stat['mtime'];
        $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';
        $headers = [
            'Content-Type' => self::MIME_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))],
            // $ava->asset() appends ?v=<mtime>, so versioned URLs can be
            // cached forever; a bare URL must revalidate or edits never show.
            'Cache-Control' => $request->queryString('v') !== null
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=0, must-revalidate',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'ETag' => $etag,
            'Accept-Ranges' => 'bytes',
        ];

        if ($this->notModified($request, $etag, $mtime)) {
            return new Response('', 304, $headers);
        }

        $range = $this->range($request, $size, $etag, $mtime);
        if ($range === false) {
            return new Response('', 416, ['Content-Range' => "bytes */{$size}"] + $headers);
        }
        if ($range !== null) {
            [$start, $end] = $range;
            return Response::file($file, 206, ['Content-Range' => "bytes {$start}-{$end}/{$size}"] + $headers, $start, $end - $start + 1);
        }

        return Response::file($file, 200, $headers);
    }

    /**
     * The absolute path of an allowed file inside the assets directory.
     */
    public function resolve(string $assetPath): ?string
    {
        if ($assetPath === '' || str_contains($assetPath, "\0") || str_starts_with(basename($assetPath), '.')) {
            return null;
        }

        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$extension])) {
            return null;
        }

        // realpath() rather than string checks: "....//" tricks and symlinks
        // are both judged by where the path really leads.
        $root = realpath($this->assetsDirectory);
        $real = realpath($this->assetsDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath));
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        // The final path must also pass the checks the requested one did.
        if (str_starts_with(basename($real), '.') || !isset(self::MIME_TYPES[strtolower(pathinfo($real, PATHINFO_EXTENSION))])) {
            return null;
        }

        return $real;
    }

    private function notModified(Request $request, string $etag, int $mtime): bool
    {
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null) {
            // If-None-Match wins over If-Modified-Since when both are sent.
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '*' || preg_replace('/^W\//', '', $candidate) === $etag) {
                    return true;
                }
            }
            return false;
        }

        $ifModifiedSince = $request->header('if-modified-since');
        $since = $ifModifiedSince === null ? false : strtotime($ifModifiedSince);

        return $since !== false && $since >= $mtime;
    }

    /**
     * @return array{0: int, 1: int}|false|null [start, end] inclusive, false
     *         for an unsatisfiable range, null to send the whole file.
     */
    private function range(Request $request, int $size, string $etag, int $mtime): array|false|null
    {
        $header = $request->header('range');
        if ($header === null || preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) !== 1) {
            return null; // Absent, or several ranges: a full response is allowed.
        }

        // A changed file must not be patched together from mismatched parts.
        $ifRange = $request->header('if-range');
        if ($ifRange !== null && $ifRange !== $etag && strtotime($ifRange) !== $mtime) {
            return null;
        }

        [, $first, $last] = $matches;
        if ($first === '' && $last === '') {
            return null;
        }

        if ($first === '') {
            $length = min((int) $last, $size);
            if ($length === 0) {
                return false;
            }
            return [$size - $length, $size - 1];
        }

        $start = (int) $first;
        $end = $last === '' ? $size - 1 : min((int) $last, $size - 1);

        return $start > $end || $start >= $size ? false : [$start, $end];
    }
}
