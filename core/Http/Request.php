<?php

declare(strict_types=1);

namespace Ava\Http;

/**
 * HTTP Request wrapper.
 *
 * Simple, immutable representation of an HTTP request.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $headers;
    private string $body;

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $headers = [],
        string $body = ''
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->query = $query;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
    }

    /**
     * Capture the current request from PHP globals.
     */
    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Parse headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }

        // Add content type and length if present
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        $body = file_get_contents('php://input') ?: '';

        return new self($method, $uri, $_GET, $headers, $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get normalized path (with or without trailing slash per config).
     */
    public function normalizedPath(bool $trailingSlash = false): string
    {
        $path = $this->path;

        // Always keep root as single slash
        if ($path === '/') {
            return '/';
        }

        if ($trailingSlash) {
            return rtrim($path, '/') . '/';
        }

        return rtrim($path, '/');
    }

    /**
     * Get a query parameter.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Get a header value.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Get all headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get the request body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Check if request expects JSON response.
     */
    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Get a POST parameter.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    /**
     * Get the host.
     */
    public function host(): string
    {
        return $this->header('host', $_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * Check if HTTPS.
     */
    public function isSecure(): bool
    {
        // Security note:
        // Do NOT trust proxy-provided headers (e.g. X-Forwarded-Proto) here.
        // They are user-controlled unless the app is explicitly behind a trusted proxy.
        // If you terminate TLS at a reverse proxy, configure your web server/PHP-FPM
        // to set HTTPS=on (or pass SERVER_PORT=443) for secure requests.
        return (
            ($_SERVER['HTTPS'] ?? 'off') !== 'off' ||
            (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        );
    }

    /**
     * Get the full URL.
     */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->host() . $this->uri;
    }

    /**
     * Check if request is from localhost.
     * 
     * Considers localhost to be:
     * - 127.0.0.1 (IPv4 loopback)
     * - ::1 (IPv6 loopback)
     * - localhost hostname
     */
    public function isLocalhost(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $host = $this->host();

        // Check if remote address is localhost
        if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        // Check if host is localhost (with or without port)
        if (preg_match('/^localhost(:\d+)?$/i', $host)) {
            return true;
        }

        return false;
    }
}
