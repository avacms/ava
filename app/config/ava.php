<?php

declare(strict_types=1);

// Prevent direct access
defined('AVA_ROOT') || exit;

/**
 * ══════════════════════════════════════════════════════════════════════════════
 * AVA CMS — MAIN CONFIGURATION
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * This is the main configuration file for your Ava site.
 * All paths are relative to the project root unless otherwise noted.
 *
 * Tip: You can use PHP logic here for environment-specific settings:
 *      if (getenv('APP_ENV') === 'production') { ... }
 *
 * Docs: https://ava.addy.zone/docs/configuration
 */

return [

    /*
    |───────────────────────────────────────────────────────────────────────────
    | SITE IDENTITY
    |───────────────────────────────────────────────────────────────────────────
    | Basic information about your site. These values are available in
    | templates via $site['name'], $site['url'], and the $ava->date() helper.
    */

    'site' => [
        'name'         => 'My Ava Site',
        'base_url'     => 'http://localhost:8000',   // Full URL, no trailing slash
        'timezone'     => 'UTC',                     // php.net/timezones
        'locale'       => 'en_GB',                   // php.net/setlocale
        'date_format'  => 'F j, Y',                  // php.net/datetime.format
        'title_format' => '{title} · {site}',        // Use null for the content title alone
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | THEME
    |───────────────────────────────────────────────────────────────────────────
    | The active theme folder name inside app/themes/
    */

    'theme' => 'default',

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PERFORMANCE — CONTENT INDEX
    |───────────────────────────────────────────────────────────────────────────
    | Binary cache of content metadata for fast lookups.
    |
    | mode:
    |   • auto   — Rebuild when files change (best for development)
    |   • never  — Only rebuild via ./ava rebuild (best for production)
    |   • always — Rebuild every request (debugging only)
    |
    | backend:
    |   • array  — Binary PHP arrays, works everywhere (default)
    |   • sqlite — SQLite database, use for 10k+ items or memory limits
    |
    | prerender_html:
    |   Pre-render markdown to HTML during rebuild. Eliminates ~20ms markdown
    |   parsing on first page view. Trade-off: larger cache, slower rebuild after content updates.
    */

    'content_index' => [
        'mode'           => 'auto',
        'backend'        => 'array',
        'use_igbinary'   => true,           // ~5x faster serialization if installed
        'prerender_html' => true,           // Pre-render markdown during rebuild
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PERFORMANCE — WEBPAGE CACHE
    |───────────────────────────────────────────────────────────────────────────
    | Stores anonymous HTML responses, including their headers.
    | Cache is cleared automatically on ./ava rebuild.
    | Manual index mode can serve hits before application boot; automatic
    | modes check source freshness first. All paths use the same cache policy.
    |
    | A request is never served from or written to this cache when it carries
    | an Authorization header or a PHP session cookie, or when a session is
    | already running. A response is never stored when it sets a cookie, a
    | Vary, or its own Cache-Control. Client Cache-Control/Pragma request
    | headers are ignored: honouring them would let any visitor disable the
    | cache. Only the host in site.base_url (plus any listed in 'hosts') can
    | create entries, so stray Host headers cannot fill the cache directory.
    |
    | URLs are cached bare or with ?paged=N; any other query string is served
    | fresh.
    |
    | If a page varies per visitor — a theme reading a cookie, geolocation,
    | anything request-dependent — say so in one of two ways:
    |   • send 'Vary' or your own 'Cache-Control' on that response, or
    |   • add its URL to 'exclude' below.
    | Only 'exclude' is applied before the theme loads, so it is the one that
    | also stops an already-cached copy being served.
    */

    'webpage_cache' => [
        'enabled' => true,
        'ttl'     => null,                  // Seconds, or null = until rebuild
        'exclude' => [                      // URL patterns to never cache
            '/api/*',
            '/preview/*',
        ],
        'hosts'   => [],                    // Extra hostnames allowed to cache, e.g. 'www.example.com'
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | ROUTING
    |───────────────────────────────────────────────────────────────────────────
    */

    'routing' => [
        'trailing_slash' => false,          // true = /about/, false = /about
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | CONTENT PARSING
    |───────────────────────────────────────────────────────────────────────────
    | How Ava processes your Markdown content files.
    */

    'content' => [
        'frontmatter' => [
            'format' => 'yaml',             // Only YAML supported currently
        ],
        'markdown' => [
            // Allow raw HTML in Markdown content. Disallowed tags (below) are
            // always stripped, and unsafe links are neutralised regardless.
            // Set to false if your content authors are not fully trusted.
            'allow_html' => true,
            'heading_ids' => true,          // Add id attributes to headings for deep links
            'disallowed_tags' => [          // Tags stripped even when allow_html is true
                'script',                   // Prevents XSS attacks
                'noscript',                 // Can contain fallback attack vectors
            ],
        ],
        'id' => [
            'type' => 'ulid',               // ulid (sortable) or uuid7
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | SECURITY
    |───────────────────────────────────────────────────────────────────────────
    */

    'security' => [
        'shortcodes' => [
            'allow_php_snippets' => true,
        ],

        // Secret for previewing drafts via ?preview=1&token=xxx (null = disabled)
        // Use a long random string wrapped in single quotes - don't rely on previews for security, they are not rate limited
        'preview_token' => null,

        // Security headers for public responses. Defaults are permissive to support
        // common use cases; tighten for hardened sites. Docs: https://ava.addy.zone/docs/configuration#content-security
        'headers' => [
            'content_security_policy' => [
                "default-src 'self'",
                "base-uri 'none'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
                "form-action 'self'",
                "connect-src 'self' https:",        // External APIs, analytics
                "img-src 'self' data: https:",      // External images
                "font-src 'self' data: https:",     // Google Fonts, CDNs
                "style-src 'self' 'unsafe-inline' https:",
                "script-src 'self' 'unsafe-inline' https:",
            ],

            'permissions_policy' => [
                'camera=()',
                'microphone=()',
                'geolocation=()',
                'payment=()',
                'usb=()',
                'interest-cohort=()',
            ],
            
            // HSTS (Strict-Transport-Security) should be configured at your webserver,
            // not here. See: https://ava.addy.zone/docs/security
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PATHS
    |───────────────────────────────────────────────────────────────────────────
    | Directory locations relative to the project root.
    | Aliases let you use @media:photo.jpg in content instead of /media/photo.jpg
    |
    | All user-editable code lives in app/ by default, keeping it separate from
    | Ava's core files. The auto-updater expects these exact paths.
    */

    'paths' => [
        'content'  => 'content',
        'themes'   => 'app/themes',
        'plugins'  => 'app/plugins',
        'snippets' => 'app/snippets',
        'storage'  => 'storage',

        'aliases' => [
            '@media:' => '/media/',
            // '@cdn:' => 'https://cdn.example.com/',
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | LOGGING
    |───────────────────────────────────────────────────────────────────────────
    | Log files are stored in storage/logs/ and rotate automatically.
    | CLI: ./ava logs:stats, logs:tail, logs:clear
    */

    'logs' => [
        'max_size'  => 10 * 1024 * 1024,    // Rotate at 10 MB
        'max_files' => 3,                   // Keep 3 rotated copies
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | DEBUG MODE
    |───────────────────────────────────────────────────────────────────────────
    | Control error visibility for development and troubleshooting.
    | ⚠️  NEVER enable display_errors in production, it can expose sensitive info
    | level: all (everything), errors (fatal only), none (suppress all)
    */

    'debug' => [
        'enabled'        => false,
        'display_errors' => false,
        'log_errors'     => true,
        'level'          => 'errors',
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | CLI OPTIONS
    |───────────────────────────────────────────────────────────────────────────
    | theme: cyan, pink, purple, green, blue, amber, disabled
    */

    'cli' => [
        'theme' => 'cyan',
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | ACTIVE PLUGINS
    |───────────────────────────────────────────────────────────────────────────
    | Plugin folder names to activate. Plugins load in the order listed.
    | Available: sitemap, feed, redirects, markdown-extensions (bundled),
    | or add your own.
    */

    'plugins' => [
        'sitemap',
        'feed',
        'redirects',
        'markdown-extensions',
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | GENERATOR COMMENT
    |───────────────────────────────────────────────────────────────────────────
    | Appends an HTML comment to each rendered page identifying the CMS
    | version, render time, and timestamp, and adds an X-Render-Time header.
    |
    | Off by default: publishing your exact version on every page makes it
    | trivial to find unpatched installs at scale after a security release.
    | Turn it on while debugging, or if you want the attribution.
    */

    'generator_comment' => false,

    /*
    |═══════════════════════════════════════════════════════════════════════════
    | PLUGIN SETTINGS
    |═══════════════════════════════════════════════════════════════════════════
    | Add plugin-specific configuration below. Check each plugin's
    | documentation for available options.
    |
    | Example:
    |   'feed' => [
    |       'items' => 20,
    |       'full_content' => true,
    |   ],
    */

    // Modes: always, never, opt_in (frontmatter defaults off),
    // or opt_out (frontmatter defaults on).
    'markdown_extensions' => [
        'footnotes' => 'never',
        'description_lists' => 'never',
        'highlight' => 'never',
        'smart_punctuation' => 'never',
        'external_links' => 'never',
        'attributes' => 'never',
        'table_of_contents' => 'never',
    ],


    /*
    |═══════════════════════════════════════════════════════════════════════════
    | CUSTOM SETTINGS
    |═══════════════════════════════════════════════════════════════════════════
    | Add your own site-specific configuration below. Access in templates
    | with $ava->config('your_key').
    |
    | Example:
    |   'analytics' => [
    |       'tracking_id' => 'UA-XXXXX-Y',
    |   ],
    */

];
