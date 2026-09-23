<?php

declare(strict_types=1);

namespace Ava\Routing;

use Ava\Http\Request;

/**
 * Access to unpublished content.
 *
 * Preferred: signed links for one path that expire,
 * `/blog/draft?preview=<expires>-<hmac>`, made with `./ava preview`. Sharing
 * one exposes one draft for a limited time, never the site's secret.
 *
 * Still accepted: `?preview=1&token=<security.preview_token>`, which works
 * for every draft and never expires. It puts the secret itself in URLs (and
 * so in access logs and browser history), so prefer signed links.
 */
final class PreviewLinks
{
    private const CONTEXT = "ava-preview\n";

    public function __construct(private mixed $secret)
    {
    }

    public function enabled(): bool
    {
        return is_string($this->secret) && $this->secret !== '';
    }

    /**
     * A signed preview URL for $path, valid until $expiresAt.
     */
    public function url(string $path, int $expiresAt): string
    {
        if (!$this->enabled()) {
            throw new \LogicException('Set security.preview_token to enable previews.');
        }

        return $path . '?preview=' . $expiresAt . '-' . $this->signature($path, $expiresAt);
    }

    /**
     * May this request see unpublished content at $path?
     */
    public function allows(Request $request, string $path): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $preview = $request->queryString('preview', null, 128);
        if ($preview === null || $preview === '') {
            return false;
        }

        if (preg_match('/^(\d{1,12})-([0-9a-f]{64})$/D', $preview, $matches) === 1) {
            $expiresAt = (int) $matches[1];

            return $expiresAt >= time() && hash_equals($this->signature($path, $expiresAt), $matches[2]);
        }

        $token = $request->queryString('token', null, 512);

        return $token !== null && $token !== '' && hash_equals((string) $this->secret, $token);
    }

    private function signature(string $path, int $expiresAt): string
    {
        return hash_hmac('sha256', self::CONTEXT . $path . "\n" . $expiresAt, (string) $this->secret);
    }
}
