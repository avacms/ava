<?php

declare(strict_types=1);

// Prevent direct access
defined('AVA_ROOT') || exit;

/**
 * ══════════════════════════════════════════════════════════════════════════════
 * AVA CMS — TAXONOMIES
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Taxonomies organise content into groups (Categories, Tags, Authors, etc.).
 * Assign taxonomies to content types in content_types.php.
 *
 * Docs: https://ava.addy.zone/docs/configuration
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
    |   ],
    |
    | Remember to add new taxonomies to content types in content_types.php:
    |   'taxonomies' => ['category', 'tag', 'author'],
    */

];
