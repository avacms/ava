<?php

declare(strict_types=1);

namespace Ava\Http;

/**
 * HTTP Response.
 *
 * Immutable response builder. A header name can carry several values
 * (withAddedHeader), which are sent as separate header lines, as Set-Cookie
 * requires. A file response streams its body from disk when sent instead of
 * holding it in memory.
 */
final class Response
{
    private string $content;
    private int $status;

    /** @var array<string, list<string>> */
    private array $headers;

    /** @var array{path: string, offset: int, length: int}|null */
    private ?array $file = null;

    public function __construct(
        string $content = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = self::normalizeHeaders($headers);
    }

    /**
     * Create a redirect response.
     *
     * Non-ASCII characters in the target are percent-encoded, as HTTP
     * header values must be ASCII.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => UrlPath::encodeForHeader($url)]);
    }

    /**
     * Create a JSON response.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * Create a plain text response.
     */
    public static function text(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Create an HTML response.
     */
    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Stream (part of) a file as the body.
     */
    public static function file(
        string $path,
        int $status = 200,
        array $headers = [],
        int $offset = 0,
        ?int $length = null
    ): self {
        $size = @filesize($path);
        if ($size === false) {
            throw new \InvalidArgumentException('File is not readable: ' . $path);
        }

        $offset = max(0, min($offset, $size));
        $length = $length === null ? $size - $offset : max(0, min($length, $size - $offset));

        $response = new self('', $status, $headers);
        $response->file = ['path' => $path, 'offset' => $offset, 'length' => $length];

        return $response->withHeader('Content-Length', (string) $length);
    }

    /**
     * Create a 404 Not Found response.
     */
    public static function notFound(string $content = 'Not Found'): self
    {
        return new self($content, 404);
    }

    /**
     * The body. File responses read it from disk on demand.
     */
    public function content(): string
    {
        if ($this->file === null) {
            return $this->content;
        }

        if ($this->file['length'] === 0) {
            return '';
        }

        $content = @file_get_contents($this->file['path'], false, null, $this->file['offset'], $this->file['length']);

        return $content === false ? '' : $content;
    }

    public function isFile(): bool
    {
        return $this->file !== null;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * A header's value; several values are joined with ", ".
     */
    public function header(string $name): ?string
    {
        $values = $this->headerValues($name);

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * Every value of a header, in the order added.
     *
     * @return list<string>
     */
    public function headerValues(string $name): array
    {
        foreach ($this->headers as $headerName => $values) {
            if (strcasecmp($headerName, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    /**
     * Headers as name => value (several values joined with ", ").
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return array_map(static fn(array $values): string => implode(', ', $values), $this->headers);
    }

    /**
     * Replace a header's values.
     */
    public function withHeader(string $name, string $value): self
    {
        self::assertValidHeader($name, $value);
        $response = clone $this;
        self::setHeader($response->headers, $name, [$value]);
        return $response;
    }

    /**
     * Add a value to a header, keeping existing values (e.g. Set-Cookie).
     */
    public function withAddedHeader(string $name, string $value): self
    {
        self::assertValidHeader($name, $value);
        $response = clone $this;
        self::setHeader($response->headers, $name, [...$response->headerValues($name), $value]);
        return $response;
    }

    public function withoutHeader(string $name): self
    {
        $response = clone $this;
        foreach (array_keys($response->headers) as $headerName) {
            if (strcasecmp($headerName, $name) === 0) {
                unset($response->headers[$headerName]);
            }
        }
        return $response;
    }

    /**
     * Set multiple headers, replacing existing values.
     */
    public function withHeaders(array $headers): self
    {
        $response = clone $this;
        foreach (self::normalizeHeaders($headers) as $name => $values) {
            self::setHeader($response->headers, $name, $values);
        }
        return $response;
    }

    /**
     * Validate headers and collapse names that differ only by case.
     *
     * @return array<string, list<string>>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $values = is_array($value) ? array_values($value) : [$value];
            if (!is_string($name) || $values === [] || array_filter($values, 'is_string') !== $values) {
                throw new \InvalidArgumentException('Header names and values must be strings');
            }

            foreach ($values as $single) {
                self::assertValidHeader($name, $single);
            }
            self::setHeader($normalized, $name, $values);
        }

        return $normalized;
    }

    /**
     * Replace a header using HTTP's case-insensitive name semantics.
     *
     * @param array<string, list<string>> $headers
     * @param list<string> $values
     */
    private static function setHeader(array &$headers, string $name, array $values): void
    {
        foreach (array_keys($headers) as $headerName) {
            if (strcasecmp($headerName, $name) === 0) {
                $headers[$headerName] = $values;
                return;
            }
        }

        $headers[$name] = $values;
    }

    /**
     * Set the status code.
     */
    public function withStatus(int $status): self
    {
        $response = clone $this;
        $response->status = $status;
        return $response;
    }

    public function withContent(string $content): self
    {
        $response = clone $this;
        $response->content = $content;
        $response->file = null;
        return $response->withoutHeader('Content-Length');
    }

    /**
     * Default security headers applied to all responses.
     */
    private const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->status);

        $headers = $this->headers;
        if ($this->header('Content-Type') === null) {
            $headers['Content-Type'] = ['text/html; charset=utf-8'];
        }
        foreach (self::SECURITY_HEADERS as $name => $value) {
            if ($this->header($name) === null) {
                $headers[$name] = [$value];
            }
        }

        foreach ($headers as $name => $values) {
            foreach ($values as $index => $value) {
                self::assertValidHeader($name, $value);
                header("{$name}: {$value}", $index === 0);
            }
        }

        if ($this->status === 204 || $this->status === 304) {
            return;
        }

        if ($this->file === null) {
            echo $this->content;
            return;
        }

        $this->streamFile();
    }

    private function streamFile(): void
    {
        $handle = @fopen($this->file['path'], 'rb');
        if ($handle === false) {
            return;
        }

        try {
            if ($this->file['offset'] > 0) {
                fseek($handle, $this->file['offset']);
            }

            $remaining = $this->file['length'];
            while ($remaining > 0 && !feof($handle)) {
                $chunk = fread($handle, min(1 << 16, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Guard against header injection and invalid header names.
     */
    private static function assertValidHeader(string $name, string $value): void
    {
        // Prevent response splitting / header injection.
        if (str_contains($name, "\r") || str_contains($name, "\n") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('Invalid header: CRLF not allowed');
        }

        // Conservative header-name validation (RFC 7230 token).
        if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid header name');
        }
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500;
    }
}
