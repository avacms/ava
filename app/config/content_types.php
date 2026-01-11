<?php

declare(strict_types=1);

/**
 * ══════════════════════════════════════════════════════════════════════════════
 * AVA CMS — CONTENT TYPES
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Define the kinds of content your site has. Each content type specifies
 * where files live, how URLs are generated, and which templates to use.
 *
 * Docs: https://ava.addy.zone/docs/configuration
 */

return [

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PAGES
    |───────────────────────────────────────────────────────────────────────────
    | Static pages with hierarchical URLs that mirror the folder structure.
    |
    | content/pages/about.md       → /about
    | content/pages/about/team.md  → /about/team
    */

    'page' => [
        'label'       => 'Pages',
        'content_dir' => 'pages',

        'url' => [
            'type' => 'hierarchical',       // URL mirrors file path
            'base' => '/',
        ],

        'templates' => [
            'single' => 'page.php',
        ],

        'taxonomies' => [],                 // Pages typically don't use taxonomies
        'fields'     => [],                 // Custom fields (for validation/admin)
        'sorting'    => 'manual',           // manual, date_desc, date_asc, title

        'search' => [
            'enabled' => true,
            'fields'  => ['title', 'body'],
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | POSTS
    |───────────────────────────────────────────────────────────────────────────
    | Blog posts with pattern-based URLs and archive listing.
    |
    | content/posts/hello-world.md → /blog/hello-world
    | Archive listing              → /blog
    */

    'post' => [
        'label'       => 'Posts',
        'content_dir' => 'posts',

        'url' => [
            'type'    => 'pattern',
            'pattern' => '/blog/{slug}',    // {slug}, {yyyy}, {mm}, {dd}, {id}
            'archive' => '/blog',           // Archive listing URL
        ],

        'templates' => [
            'single'  => 'post.php',
            'archive' => 'archive.php',
        ],

        'taxonomies' => ['category', 'tag'],
        'fields'     => [],
        'sorting'    => 'date_desc',

        'search' => [
            'enabled' => true,
            'fields'  => ['title', 'excerpt', 'body'],

            // Optional: Customise search scoring (see docs for defaults)
            // 'weights' => [
            //     'title_phrase' => 80,
            //     'body_token'   => 2,
            // ],
        ],

        // Optional: Extra fields to include in archive cache
        // (id, title, date are always included)
        // 'cache_fields' => ['author', 'featured_image'],
    ],

    /*
    |═══════════════════════════════════════════════════════════════════════════
    | ADD YOUR CONTENT TYPES BELOW
    |═══════════════════════════════════════════════════════════════════════════
    | Copy the structure above to create new content types.
    |
    | Example — Documentation:
    |
    |   'doc' => [
    |       'label'       => 'Documentation',
    |       'content_dir' => 'docs',
    |       'url' => [
    |           'type'    => 'pattern',
    |           'pattern' => '/docs/{slug}',
    |           'archive' => '/docs',
    |       ],
    |       'templates' => [
    |           'single'  => 'doc.php',
    |           'archive' => 'docs-archive.php',
    |       ],
    |       'taxonomies' => [],
    |       'sorting'    => 'manual',
    |   ],
    |
    | URL Pattern Placeholders:
    |   {slug} — Item slug         → /blog/my-post
    |   {id}   — Unique ID         → /posts/01HXYZ
    |   {yyyy} — 4-digit year      → /blog/2024/my-post
    |   {mm}   — 2-digit month     → /blog/2024/03/my-post
    |   {dd}   — 2-digit day       → /blog/2024/03/15/my-post
    */

];
