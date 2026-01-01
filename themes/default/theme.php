<?php

declare(strict_types=1);

/**
 * Ava Default Theme
 *
 * A minimal, clean theme for Ava CMS.
 */

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

return function (Application $app): void {
    // Register search route
    Hooks::addFilter('router.before_match', function ($match, Request $request) use ($app) {
        if ($request->path() !== '/search') {
            return $match;
        }

        $searchQuery = trim($request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        // Build search query
        $query = $app->query()
            ->published()
            ->orderBy('date', 'desc')
            ->perPage(10)
            ->page($page);

        if ($searchQuery !== '') {
            $query = $query->search($searchQuery);
        }

        // Render search template
        $renderer = $app->renderer();
        $content = $renderer->render('search', [
            'query' => $query,
            'searchQuery' => $searchQuery,
            'request' => $request,
        ]);

        return new Response($content, 200);
    });
};
