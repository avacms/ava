<?php

declare(strict_types=1);

/**
 * ══════════════════════════════════════════════════════════════════════════════
 * AVA CMS — TAXONOMIES
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Taxonomies organise content into groups (Categories, Tags, Authors, etc.).
 * Assign taxonomies to content types in content_types.php.
 *
 * Docs: https://ava.addy.zone/#/configuration?id=taxonomies-taxonomiesphp
 */

return [

    /*
    |───────────────────────────────────────────────────────────────────────────
    | CATEGORIES
    |───────────────────────────────────────────────────────────────────────────
    | Hierarchical taxonomy with parent/child relationships.
    |
    | In frontmatter: category: Tutorials
    | URL:            /category/tutorials
    */

    'category' => [
        'label'        => 'Categories',
        'hierarchical' => true,             // Supports parent/child terms
        'public'       => true,             // Has public archive pages

        'rewrite' => [
            'base'      => '/category',     // URL prefix: /category/tutorials
            'separator' => '/',             // Hierarchy: /category/tutorials/php
        ],

        'behaviour' => [
            'allow_unknown_terms' => true,  // Auto-create terms from content
            'hierarchy_rollup'    => true,  // Include children when filtering parent
        ],

        'ui' => [
            'show_counts' => true,
            'sort_terms'  => 'name_asc',    // name_asc, name_desc, count_asc, count_desc
        ],
    ],

    /*
    |───────────────────────────────────────────────────────────────────────────
    | TAGS
    |───────────────────────────────────────────────────────────────────────────
    | Flat taxonomy (no hierarchy) for flexible content labelling.
    |
    | In frontmatter: tag: [php, cms, beginner]
    | URL:            /tag/php
    */

    'tag' => [
        'label'        => 'Tags',
        'hierarchical' => false,
        'public'       => true,

        'rewrite' => [
            'base' => '/tag',
        ],

        'behaviour' => [
            'allow_unknown_terms' => true,
        ],

        'ui' => [
            'show_counts' => true,
            'sort_terms'  => 'count_desc',  // Most used first
        ],
    ],

    /*
    |═══════════════════════════════════════════════════════════════════════════
    | ADD YOUR TAXONOMIES BELOW
    |═══════════════════════════════════════════════════════════════════════════
    | Copy the structure above to create new taxonomies.
    |
    | Example — Authors:
    |
    |   'author' => [
    |       'label'        => 'Authors',
    |       'hierarchical' => false,
    |       'public'       => true,
    |       'rewrite'      => ['base' => '/author'],
    |       'behaviour'    => ['allow_unknown_terms' => true],
    |       'ui'           => ['show_counts' => true, 'sort_terms' => 'name_asc'],
    |   ],
    |
    | Remember to add new taxonomies to content types in content_types.php:
    |   'taxonomies' => ['category', 'tag', 'author'],
    */

];
