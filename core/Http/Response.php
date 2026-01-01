<?php

declare(strict_types=1);

namespace Ava\Http;

/**
 * HTTP Response.
 *
 * Simple response builder for sending HTTP responses.
 */
final class Response
{
    private string $content;
    private int $status;
    private array $headers;

    public function __construct(
        string $content = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
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
     * Create a 404 Not Found response.
     */
    public static function notFound(string $content = 'Not Found'): self
    {
        return new self($content, 404);
    }

    /**
     * Get the content.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Get the status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Set a header.
     */
    public function withHeader(string $name, string $value): self
    {
        $response = clone $this;
        $response->headers[$name] = $value;
        return $response;
    }

    /**
     * Set multiple headers.
     */
    public function withHeaders(array $headers): self
    {
        $response = clone $this;
        $response->headers = array_merge($response->headers, $headers);
        return $response;
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

    /**
     * Set the content.
     */
    public function withContent(string $content): self
    {
        $response = clone $this;
        $response->content = $content;
        return $response;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->status);

        // Set default content type if not set
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        // Add security headers if not already set
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        foreach ($securityHeaders as $name => $value) {
            if (!isset($this->headers[$name])) {
                $this->headers[$name] = $value;
            }
        }

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send content
        echo $this->content;
    }

    /**
     * Check if response is a redirect.
     */
    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * Check if response is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Check if response is a client error.
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Check if response is a server error.
     */
    public function isServerError(): bool
    {
        return $this->status >= 500;
    }
}
