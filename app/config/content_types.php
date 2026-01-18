<?php

declare(strict_types=1);

// Prevent direct access
defined('AVA_ROOT') || exit;

/**
 * ══════════════════════════════════════════════════════════════════════════════
 * AVA CMS — CONTENT TYPES
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Define the kinds of content your site has. Each content type specifies:
 *   - Where Markdown files live (content_dir)
 *   - How URLs are generated (url.type: 'hierarchical' or 'pattern')
 *   - Which templates render the content (templates)
 *   - Custom fields for validation and admin UI (fields)
 *
 * Docs: https://ava.addy.zone/docs/configuration
 * Fields: https://ava.addy.zone/docs/fields
 */

return [

    /*
    |───────────────────────────────────────────────────────────────────────────
    | PAGES — Hierarchical URL Structure
    |───────────────────────────────────────────────────────────────────────────
    | Best for: About pages, contact, legal pages, landing pages.
    | URLs mirror the folder structure in content/pages/.
    |
    | Examples:
    |   content/pages/about.md         → /about
    |   content/pages/about/team.md    → /about/team
    |   content/pages/services/web.md  → /services/web
    */

    'page' => [
        'label'       => 'Pages',           // Display name in admin UI
        'icon'        => 'description',     // Material icon (see fonts.google.com/icons)
        'content_dir' => 'pages',           // Folder inside content/

        'url' => [
            'type' => 'hierarchical',       // URL = folder path + filename
            'base' => '/',                  // URL prefix (usually just '/')
        ],

        'templates' => [
            'single' => 'page.php',         // Theme template for individual pages
        ],

        'taxonomies' => [],                 // Pages typically don't need categories/tags
        'fields'     => [],                 // Add custom fields here (see 'fields' docs)
        'sorting'    => 'manual',           // manual | date_desc | date_asc | title

        'search' => [
            'enabled' => true,              // Include in site search
            'fields'  => ['title', 'body'], // Fields to index for search
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | POSTS — Pattern-Based URLs with Archive
    |───────────────────────────────────────────────────────────────────────────
    | Best for: Blog posts, news, articles—any dated content with listings.
    | Uses URL patterns with placeholders for flexible permalink structures.
    |
    | Examples:
    |   content/posts/hello-world.md → /blog/hello-world
    |   Archive listing page         → /blog
    */

    'post' => [
        'label'       => 'Posts',
        'icon'        => 'article',
        'content_dir' => 'posts',

        'url' => [
            'type'    => 'pattern',         // URL built from pattern below
            'pattern' => '/blog/{slug}',    // Placeholders: {slug}, {yyyy}, {mm}, {dd}, {id}
            'archive' => '/blog',           // Archive page URL (omit for no archive)
        ],

        'templates' => [
            'single'  => 'post.php',        // Individual post template
            'archive' => 'archive.php',     // Archive listing template
        ],

        'taxonomies' => ['category', 'tag'], // Enable categories and tags
        'fields'     => [],                  // Define custom fields below
        'sorting'    => 'date_desc',         // Newest first

        'search' => [
            'enabled' => true,
            'fields'  => ['title', 'excerpt', 'body'],

            // Optional: Tune search result ranking
            // 'weights' => ['title_phrase' => 80, 'body_token' => 2],
        ],

        // Optional: Cache extra frontmatter fields for fast archive queries
        // 'cache_fields' => ['author', 'featured_image'],
    ],

    /*
    |═══════════════════════════════════════════════════════════════════════════
    | EXAMPLE: RECIPES 
    |═══════════════════════════════════════════════════════════════════════════
    | This example shows a content type with custom fields for validation
    | and admin UI. Uncomment and adapt for your own content types.
    |
    | To use: Create content/recipes/ folder and recipe.php template.
    |
    | Field types available:
    |   text, textarea, number, checkbox, select, date, color,
    |   image, file, gallery, array, content, taxonomy
    |
    | See: https://ava.addy.zone/docs/fields
    */

    // 'recipe' => [
    //     'label'       => 'Recipes',
    //     'icon'        => 'restaurant',
    //     'content_dir' => 'recipes',
    //
    //     'url' => [
    //         'type'    => 'pattern',
    //         'pattern' => '/recipes/{slug}',
    //         'archive' => '/recipes',
    //     ],
    //
    //     'templates' => [
    //         'single'  => 'recipe.php',
    //         'archive' => 'recipes-archive.php',
    //     ],
    //
    //     'taxonomies' => ['category'],   // e.g., "Breakfast", "Desserts"
    //     'sorting'    => 'date_desc',
    //
    //     'fields' => [
    //         // Text field with validation
    //         'author' => [
    //             'type'      => 'text',
    //             'label'     => 'Recipe Author',
    //             'required'  => true,
    //             'maxLength' => 100,
    //         ],
    //
    //         // Number fields for cooking info
    //         'prep_time' => [
    //             'type'  => 'number',
    //             'label' => 'Prep Time (minutes)',
    //             'min'   => 0,
    //             'max'   => 480,
    //         ],
    //         'cook_time' => [
    //             'type'  => 'number',
    //             'label' => 'Cook Time (minutes)',
    //             'min'   => 0,
    //             'max'   => 480,
    //         ],
    //         'servings' => [
    //             'type'    => 'number',
    //             'label'   => 'Servings',
    //             'min'     => 1,
    //             'max'     => 100,
    //             'default' => 4,
    //         ],
    //
    //         // Dropdown selection
    //         'difficulty' => [
    //             'type'    => 'select',
    //             'label'   => 'Difficulty',
    //             'options' => [
    //                 'easy'         => 'Easy',
    //                 'intermediate' => 'Intermediate',
    //                 'advanced'     => 'Advanced',
    //             ],
    //             'default' => 'easy',
    //         ],
    //
    //         // Image with preview in admin
    //         'featured_image' => [
    //             'type'        => 'image',
    //             'label'       => 'Recipe Photo',
    //             'description' => 'Recommended: 1200×800px',
    //         ],
    //
    //         // Dynamic list (ingredients, steps, etc.)
    //         'ingredients' => [
    //             'type'     => 'array',
    //             'label'    => 'Ingredients',
    //             'minItems' => 1,
    //             'maxItems' => 50,
    //         ],
    //
    //         // Boolean toggle
    //         'featured' => [
    //             'type'    => 'checkbox',
    //             'label'   => 'Featured Recipe',
    //             'default' => false,
    //         ],
    //     ],
    //
    //     // Include these fields in archive cache for fast listing access
    //     'cache_fields' => ['author', 'prep_time', 'cook_time', 'difficulty', 'featured_image'],
    //
    //     'search' => [
    //         'enabled' => true,
    //         'fields'  => ['title', 'body', 'ingredients'],
    //     ],
    // ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | URL PATTERN PLACEHOLDERS
    |───────────────────────────────────────────────────────────────────────────
    |   {slug} — Item slug         → /blog/my-post
    |   {id}   — Unique ID         → /posts/01HXYZ
    |   {yyyy} — 4-digit year      → /blog/2024/my-post
    |   {mm}   — 2-digit month     → /blog/2024/03/my-post
    |   {dd}   — 2-digit day       → /blog/2024/03/15/my-post
    |
    | SORTING OPTIONS
    |   manual    — Order by 'order' frontmatter field
    |   date_desc — Newest first (default for blogs)
    |   date_asc  — Oldest first
    |   title     — Alphabetical by title
    */

];
