<?php

declare(strict_types=1);

namespace Ava\Admin;

/**
 * Content Security Linter
 * 
 * Validates user-submitted content for security risks.
 * Used to prevent XSS and other HTML injection attacks from the admin interface.
 * 
 * This is intentionally strict - the file system should be used for advanced 
 * HTML that requires these blocked elements.
 */
final class ContentSecurity
{
    /**
     * Dangerous HTML tags that are blocked from admin editing.
     * These can still be added via file system for legitimate use cases.
     */
    private const BLOCKED_TAGS = [
        'script',
        'iframe',
        'frame',
        'frameset',
        'object',
        'embed',
        'applet',
        'base',      // Can change base URL for all relative links
        'form',      // Could be used for phishing
        'input',     // Part of form-based attacks
        'button',    // Part of form-based attacks
        'textarea',  // Part of form-based attacks
        'select',    // Part of form-based attacks
    ];

    /**
     * Dangerous attributes that are blocked.
     */
    private const BLOCKED_ATTRIBUTES = [
        'onabort', 'onafterprint', 'onbeforeprint', 'onbeforeunload',
        'onblur', 'oncanplay', 'oncanplaythrough', 'onchange', 'onclick',
        'oncontextmenu', 'oncopy', 'oncuechange', 'oncut', 'ondblclick',
        'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover',
        'ondragstart', 'ondrop', 'ondurationchange', 'onemptied', 'onended',
        'onerror', 'onfocus', 'onhashchange', 'oninput', 'oninvalid',
        'onkeydown', 'onkeypress', 'onkeyup', 'onload', 'onloadeddata',
        'onloadedmetadata', 'onloadstart', 'onmessage', 'onmousedown',
        'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel',
        'onoffline', 'ononline', 'onpagehide', 'onpageshow', 'onpaste',
        'onpause', 'onplay', 'onplaying', 'onpopstate', 'onprogress',
        'onratechange', 'onreset', 'onresize', 'onscroll', 'onsearch',
        'onseeked', 'onseeking', 'onselect', 'onstalled', 'onstorage',
        'onsubmit', 'onsuspend', 'ontimeupdate', 'ontoggle', 'onunload',
        'onvolumechange', 'onwaiting', 'onwheel',
        'formaction', // Form hijacking
        'xlink:href', // SVG-based XSS
    ];

    /**
     * Dangerous URL protocols.
     */
    private const BLOCKED_PROTOCOLS = [
        'javascript:',
        'vbscript:',
        'data:text/html',
        'data:application',
    ];

    /**
     * Patterns that indicate dangerous content.
     */
    private const DANGEROUS_PATTERNS = [
        // Meta refresh redirect
        '/<meta[^>]*http-equiv\s*=\s*["\']?refresh["\']?/i',
        // Expression() in CSS (IE)
        '/expression\s*\(/i',
        // JavaScript URLs in various contexts
        '/javascript\s*:/i',
        // VBScript
        '/vbscript\s*:/i',
        // Data URIs with executable content
        '/data\s*:\s*(text\/html|application)/i',
        // Encoded script tags (various encodings)
        '/&#0*60;?\s*&#0*115;?\s*&#0*99;?\s*&#0*114;?\s*&#0*105;?\s*&#0*112;?\s*&#0*116;?/i',
        // Base64 encoded dangerous content in data URIs
        '/data:[^;]*;base64,/i',
    ];

    /**
     * Validate content for security issues.
     * 
     * @param string $content The raw markdown/HTML content to validate
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validate(string $content): array
    {
        $errors = [];
        $warnings = [];

        // Check for blocked tags
        foreach (self::BLOCKED_TAGS as $tag) {
            if (preg_match('/<' . preg_quote($tag, '/') . '[\s>]/i', $content)) {
                $errors[] = "Blocked tag <{$tag}> detected. For security, this tag cannot be added via the admin interface. Use the file system to add this content.";
            }
        }

        // Check for blocked event handler attributes
        foreach (self::BLOCKED_ATTRIBUTES as $attr) {
            if (preg_match('/\s' . preg_quote($attr, '/') . '\s*=/i', $content)) {
                $errors[] = "Blocked attribute '{$attr}' detected. JavaScript event handlers are not allowed via the admin interface.";
            }
        }

        // Check for dangerous protocols in hrefs and srcs
        foreach (self::BLOCKED_PROTOCOLS as $protocol) {
            if (stripos($content, $protocol) !== false) {
                $errors[] = "Blocked protocol '{$protocol}' detected. This could be used for XSS attacks.";
            }
        }

        // Check for dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Potentially dangerous content pattern detected. Please review the content or use the file system for advanced HTML.";
                break; // Only report once for pattern matches
            }
        }

        // Check for HTML comments that might hide malicious content
        if (preg_match('/<!--.*?(script|iframe|object|embed|on\w+\s*=)/is', $content)) {
            $warnings[] = "HTML comment contains suspicious content. This will be preserved but may indicate hidden malicious code.";
        }

        // Check for style attributes with suspicious content
        if (preg_match('/style\s*=\s*["\'][^"\']*(?:expression|url\s*\(|behavior\s*:)/i', $content)) {
            $errors[] = "Potentially dangerous CSS detected in style attribute.";
        }

        // Check for SVG with embedded scripts
        if (preg_match('/<svg[^>]*>.*?<script/is', $content)) {
            $errors[] = "SVG with embedded script detected. This is not allowed via the admin interface.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get a human-readable explanation of the security restrictions.
     */
    public static function getSecurityExplanation(): string
    {
        return <<<'HTML'
<div class="security-notice">
    <h4>Content Security</h4>
    <p>For security, certain HTML elements cannot be added via the admin interface:</p>
    <ul>
        <li><strong>Script tags</strong> - JavaScript can steal credentials and session data</li>
        <li><strong>Iframes/frames</strong> - Can load malicious external content</li>
        <li><strong>Objects/embeds</strong> - Can execute arbitrary plugins</li>
        <li><strong>Forms/inputs</strong> - Can be used for phishing attacks</li>
        <li><strong>Event handlers</strong> - onclick, onload, etc. can execute JavaScript</li>
        <li><strong>Meta refresh</strong> - Can redirect users to malicious sites</li>
    </ul>
    <p><strong>Need these elements?</strong> Edit the content file directly on the server. 
    Ava is a files-first CMS, so advanced content should be managed via the file system 
    where you have full control and responsibility.</p>
</div>
HTML;
    }

    /**
     * Get list of blocked tags for display.
     */
    public static function getBlockedTags(): array
    {
        return self::BLOCKED_TAGS;
    }

    /**
     * Validate and sanitize a slug.
     * 
     * Slugs must be lowercase alphanumeric with hyphens only.
     * No path traversal, dots, slashes, or other special characters.
     * 
     * @param string $slug The slug to validate
     * @return array{valid: bool, slug: string, error: ?string}
     */
    public static function validateSlug(string $slug): array
    {
        // Trim whitespace
        $slug = trim($slug);
        
        // Block empty slugs
        if ($slug === '') {
            return ['valid' => false, 'slug' => '', 'error' => 'Slug cannot be empty.'];
        }
        
        // Block any path traversal attempts
        if (str_contains($slug, '..') || str_contains($slug, '/') || str_contains($slug, '\\')) {
            return ['valid' => false, 'slug' => $slug, 'error' => 'Slug cannot contain path separators or ".." sequences.'];
        }
        
        // Block dots (no file extension trickery)
        if (str_contains($slug, '.')) {
            return ['valid' => false, 'slug' => $slug, 'error' => 'Slug cannot contain dots.'];
        }
        
        // Normalize to lowercase
        $slug = strtolower($slug);
        
        // Must match: only a-z, 0-9, and hyphens; cannot start/end with hyphen
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return ['valid' => false, 'slug' => $slug, 'error' => 'Slug must be lowercase alphanumeric with hyphens only, and cannot start or end with a hyphen.'];
        }
        
        // Block consecutive hyphens
        if (str_contains($slug, '--')) {
            return ['valid' => false, 'slug' => $slug, 'error' => 'Slug cannot contain consecutive hyphens.'];
        }
        
        return ['valid' => true, 'slug' => $slug, 'error' => null];
    }

    /**
     * Validate and sanitize a filename (without extension).
     * 
     * Filenames must be lowercase alphanumeric with hyphens only.
     * Date prefixes (YYYY-MM-DD-) are allowed for dated content.
     * 
     * @param string $filename The filename to validate (without .md extension)
     * @return array{valid: bool, filename: string, error: ?string}
     */
    public static function validateFilename(string $filename): array
    {
        // Trim whitespace
        $filename = trim($filename);
        
        // Block empty filenames
        if ($filename === '') {
            return ['valid' => false, 'filename' => '', 'error' => 'Filename cannot be empty.'];
        }
        
        // Block any path traversal attempts
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return ['valid' => false, 'filename' => $filename, 'error' => 'Filename cannot contain path separators or ".." sequences.'];
        }
        
        // Block dots (we add .md extension ourselves)
        if (str_contains($filename, '.')) {
            return ['valid' => false, 'filename' => $filename, 'error' => 'Filename cannot contain dots. The .md extension is added automatically.'];
        }
        
        // Normalize to lowercase
        $filename = strtolower($filename);
        
        // Must match: only a-z, 0-9, and hyphens; cannot start/end with hyphen
        // Pattern allows date prefix like 2025-01-10-my-post
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $filename)) {
            return ['valid' => false, 'filename' => $filename, 'error' => 'Filename must be lowercase alphanumeric with hyphens only, and cannot start or end with a hyphen.'];
        }
        
        // Block consecutive hyphens (except in date patterns like 2025-01-10-slug)
        // Actually, allow consecutive hyphens since date-slug creates them: 2025-01-10-my-post
        // The key security concern is path traversal which we've already blocked
        
        return ['valid' => true, 'filename' => $filename, 'error' => null];
    }

    /**
     * Generate a safe filename from date and slug.
     * 
     * For dated content types, generates: YYYY-MM-DD-slug
     * For non-dated content types, just uses the slug.
     * 
     * @param string $slug The content slug
     * @param ?string $date The content date (Y-m-d format) or null for non-dated content
     * @return string The safe filename (without .md extension)
     */
    public static function generateFilename(string $slug, ?string $date = null): string
    {
        // Ensure slug is valid first
        $slugResult = self::validateSlug($slug);
        $safeSlug = $slugResult['valid'] ? $slugResult['slug'] : 'untitled';
        
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date . '-' . $safeSlug;
        }
        
        return $safeSlug;
    }
}
