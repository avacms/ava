<?php

declare(strict_types=1);

/**
 * Ava Default Theme
 * 
 * A clean, educational starter theme for Ava CMS. This file serves as the
 * theme's entry point and bootstrap file.
 * 
 * This theme demonstrates core Ava concepts and is designed to be a learning
 * resource for building your own themes. Comments throughout explain how
 * each feature works.
 * 
 * Theme files:
 *   templates/   - Main page templates (page.php, post.php, etc.)
 *   partials/    - Reusable template fragments (header.php, footer.php)
 *   assets/      - CSS, JavaScript, and images
 * 
 * @see https://ava.addy.zone/#/themes
 */

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

/**
 * Theme bootstrap function.
 * 
 * Every theme must return a function that receives the Application instance.
 * This is where you can register routes, hooks, filters, and perform any
 * theme-specific setup.
 * 
 * The function is called once when Ava loads your theme, before any
 * content is rendered.
 * 
 * @see https://ava.addy.zone/#/themes?id=theme-bootstrap
 */
return function (Application $app): void {
    
    /**
     * Custom Search Route
     * 
     * This demonstrates how themes can add custom routes using the hook system.
     * The 'router.before_match' filter runs before the router tries to match
     * a URL to content, allowing you to intercept requests and handle them
     * yourself.
     * 
     * Returning a Response object bypasses normal routing. Returning the
     * original $match value continues normal processing.
     * 
     * @see https://ava.addy.zone/#/creating-plugins?id=available-hooks
     */
    Hooks::addFilter('router.before_match', function ($match, Request $request) use ($app) {
        
        // Only handle the /search URL
        if ($request->path() !== '/search') {
            return $match; // Let Ava handle this URL normally
        }

        // Get the search query from ?q= parameter
        // $request->query() safely retrieves GET parameters with a default value
        $searchQuery = trim($request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        /**
         * Build a content query using Ava's fluent query API.
         * 
         * The query builder provides a chainable interface for filtering,
         * sorting, and paginating content. Queries are lazy - they don't
         * execute until you call get() or iterate over results.
         * 
         * Common query methods:
         *   ->type('post')          Filter by content type
         *   ->published()           Only published items
         *   ->orderBy('date')       Sort results
         *   ->perPage(10)           Limit results per page
         *   ->search('term')        Full-text search
         * 
         * @see https://ava.addy.zone/#/themes?id=content-queries
         */
        $query = $app->query()
            ->published()
            ->orderBy('date', 'desc')
            ->perPage(10)
            ->page($page);

        // Apply search filter if a query was provided
        if ($searchQuery !== '') {
            $query = $query->search($searchQuery);
        }

        /**
         * Render a template with custom variables.
         * 
         * The renderer loads templates from themes/<theme>/templates/ and
         * provides them with the $ava helper and $site configuration
         * automatically.
         * 
         * Any additional variables passed in the array become available
         * as local variables in the template.
         */
        $renderer = $app->renderer();
        $content = $renderer->render('search', [
            'query' => $query,
            'searchQuery' => $searchQuery,
            'request' => $request,
        ]);

        // Return a Response object to bypass normal routing
        return new Response($content, 200);
    });
};
