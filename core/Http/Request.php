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
    private ?string $body;
    private bool $bodyLoaded;

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $headers = [],
        ?string $body = null,
        bool $bodyLoaded = true
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        // parse_url() reads "//about" as a host with no path; a request
        // target is always a path, so take everything before the query.
        $this->path = str_starts_with($uri, '//')
            ? explode('#', explode('?', $uri, 2)[0], 2)[0]
            : (parse_url($uri, PHP_URL_PATH) ?: '/');
        $this->query = $query;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->bodyLoaded = $bodyLoaded;
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

        // Some SAPIs expose Basic/Digest authentication only through PHP_AUTH_*.
        if (!isset($headers['AUTHORIZATION']) && (isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['PHP_AUTH_DIGEST']))) {
            $headers['AUTHORIZATION'] = 'authenticated';
        }

        // Add content type and length if present
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        // Defer body reading until actually needed (lazy loading for performance)
        return new self($method, $uri, $_GET, $headers, null, false);
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
     * Get a string query parameter, rejecting PHP's nested array syntax.
     */
    public function queryString(string $key, ?string $default = null, ?int $maxLength = null): ?string
    {
        $value = $this->query[$key] ?? null;
        if (!is_string($value)) {
            return $default;
        }
        if ($maxLength !== null) {
            if ($maxLength < 0) {
                throw new \InvalidArgumentException('Maximum query length cannot be negative.');
            }
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Get a bounded integer query parameter.
     */
    public function queryInt(
        string $key,
        int $default = 0,
        int $min = PHP_INT_MIN,
        int $max = PHP_INT_MAX
    ): int {
        if ($min > $max) {
            throw new \InvalidArgumentException('Minimum query value cannot exceed maximum.');
        }
        $default = max($min, min($max, $default));

        $value = $this->query[$key] ?? null;
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
            $negative = str_starts_with($value, '-');
            $digits = ltrim($value, '-0');
            $normalized = ($negative ? '-' : '') . ($digits === '' ? '0' : $digits);
            $integer = filter_var($normalized, FILTER_VALIDATE_INT);

            if ($integer === false) {
                return $default;
            }
        } else {
            return $default;
        }

        return max($min, min($max, $integer));
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get the request body.
     * Body is lazy-loaded on first access to avoid reading php://input for GET requests.
     */
    public function body(): string
    {
        if (!$this->bodyLoaded) {
            $this->body = file_get_contents('php://input') ?: '';
            $this->bodyLoaded = true;
        }
        return $this->body ?? '';
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

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->host() . $this->uri;
    }

    /**
     * Check if the request comes from the loopback interface.
     *
     * Only the connection's address counts. The Host header is chosen by the
     * client, so "Host: localhost" from anywhere must not pass this check.
     */
    public function isLocalhost(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }
}
