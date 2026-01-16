<?php

declare(strict_types=1);

// Prevent direct access
defined('AVA_ROOT') || exit;

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
        'icon'        => 'description',
        'content_dir' => 'pages',

        'url' => [
            'type' => 'hierarchical',       // URL mirrors file path
            'base' => '/',
        ],

        'templates' => [
            'single' => 'page.php',
        ],

        'taxonomies' => [],                 // Pages typically don't use taxonomies
        
        // Example fields - demonstrates all available field types
        'fields' => [
            // Text input with validation
            'subtitle' => [
                'type'        => 'text',
                'label'       => 'Subtitle',
                'description' => 'A secondary heading for the page',
                'required'    => false,
                'maxlength'   => 100,
                'group'       => 'content',
            ],
            
            'arraytest' => [
                'type'        => 'array',
                'label'       => 'Array Test',
                'description' => 'An array field for testing',
                'group'       => 'content',
                'keyValue'    => true,
            ],

            // Textarea for longer content
            'summary' => [
                'type'        => 'textarea',
                'label'       => 'Summary',
                'description' => 'Brief overview for card displays',
                'rows'        => 3,
                'group'       => 'content',
            ],
            
            // Select dropdown
            'layout' => [
                'type'        => 'select',
                'label'       => 'Layout Style',
                'options'     => [
                    ''          => '— Default —',
                    'sidebar'   => 'With Sidebar',
                    'full'      => 'Full Width',
                    'centered'  => 'Centered Content',
                ],
                'default'     => '',
                'group'       => 'display',
            ],
            
            // Checkbox
            'featured' => [
                'type'        => 'checkbox',
                'label'       => 'Featured Page',
                'description' => 'Show this page in featured sections',
                'group'       => 'display',
            ],
            
            // Number with min/max
            'display_order' => [
                'type'        => 'number',
                'label'       => 'Display Order',
                'description' => 'For sorting in navigation (lower = first)',
                'min'         => 0,
                'max'         => 999,
                'step'        => 1,
                'group'       => 'display',
            ],
            
            // Image with media picker
            'hero_image' => [
                'type'        => 'image',
                'label'       => 'Hero Image',
                'description' => 'Large banner image for the page header',
                'group'       => 'media',
            ],
            
            // URL field
            'external_link' => [
                'type'        => 'url',
                'label'       => 'External Link',
                'description' => 'Link to external resource',
                'group'       => 'advanced',
            ],
            
            // Email field
            'contact_email' => [
                'type'        => 'email',
                'label'       => 'Contact Email',
                'description' => 'Email address for this page',
                'group'       => 'advanced',
            ],
            
            // Date field
            'event_date' => [
                'type'        => 'date',
                'label'       => 'Event Date',
                'description' => 'For event pages',
                'group'       => 'advanced',
            ],
            
            // Hidden field (for system values)
            'page_version' => [
                'type'        => 'hidden',
                'default'     => '1.0',
            ],
        ],
        
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
        'icon'        => 'article',
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
