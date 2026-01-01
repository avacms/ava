<?php

declare(strict_types=1);

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
 * Docs: https://ava.addy.zone/#/configuration
 */

return [

    /*
    |───────────────────────────────────────────────────────────────────────────
    | SITE IDENTITY
    |───────────────────────────────────────────────────────────────────────────
    | Basic information about your site. These values are available in
    | templates via $site->name, $site->url, and the $ava->date() helper.
    */

    'site' => [
        'name'        => 'My Ava Site',
        'base_url'    => 'http://localhost:8000',   // Full URL, no trailing slash
        'timezone'    => 'UTC',                     // php.net/timezones
        'locale'      => 'en_GB',                   // php.net/setlocale
        'date_format' => 'F j, Y',                  // php.net/datetime.format
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | THEME
    |───────────────────────────────────────────────────────────────────────────
    | The active theme folder name inside themes/
    */

    'theme' => 'default',

    /*
    |───────────────────────────────────────────────────────────────────────────
    | ADMIN DASHBOARD
    |───────────────────────────────────────────────────────────────────────────
    | Web-based dashboard for site overview and content browsing.
    | Create users first with: ./ava user:add
    */

    'admin' => [
        'enabled' => true,
        'path'    => '/admin',              // URL path (e.g., /admin, /dashboard)
    ],

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
    */

    'content_index' => [
        'mode'         => 'auto',
        'backend'      => 'array',
        'use_igbinary' => true,             // ~5x faster serialization if installed
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PERFORMANCE — WEBPAGE CACHE
    |───────────────────────────────────────────────────────────────────────────
    | Stores fully-rendered HTML for instant serving (~0.1ms vs ~30ms).
    | Cache is cleared automatically on ./ava rebuild.
    */

    'webpage_cache' => [
        'enabled' => true,
        'ttl'     => null,                  // Seconds, or null = until rebuild
        'exclude' => [                      // URL patterns to never cache
            '/api/*',
            '/preview/*',
        ],
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
            'allow_html' => true,           // Allow raw HTML in Markdown
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
            'allow_php_snippets' => true,   // Enable [snippet name="file"] shortcode
        ],
        'preview_token' => 'your-preview-token-here',   // Set a secret for ?preview=1&token=xxx on drafts
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PATHS
    |───────────────────────────────────────────────────────────────────────────
    | Directory locations (rarely need to change these).
    | Aliases let you use @media:photo.jpg in content instead of /media/photo.jpg
    */

    'paths' => [
        'content'  => 'content',
        'themes'   => 'themes',
        'plugins'  => 'plugins',
        'snippets' => 'snippets',
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
    |
    | ⚠️  NEVER enable display_errors in production, it can expose sensitive info
    |
    | level: all (everything), errors (fatal only), none (suppress all)
    */

    'debug' => [
        'enabled'        => true,
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
    | Available: sitemap, feed, redirects (bundled), or add your own.
    */

    'plugins' => [
        'sitemap',
        'feed',
        'redirects',
    ],

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
