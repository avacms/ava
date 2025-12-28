<?php

declare(strict_types=1);

namespace Ava\Admin;

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;
use Ava\Routing\RouteMatch;

/**
 * Admin Router
 *
 * Registers admin routes when admin is enabled.
 * 
 * Plugins can register custom admin pages using:
 * - 'admin.register_pages' filter: Add pages to the admin section
 * - 'admin.sidebar_items' filter: Add items to the admin sidebar
 * 
 * @example
 * Hooks::addFilter('admin.register_pages', function($pages) {
 *     $pages['analytics'] = [
 *         'label' => 'Analytics',
 *         'icon' => 'analytics',
 *         'handler' => fn($request, $app) => new Response(...),
 *     ];
 *     return $pages;
 * });
 */
final class AdminRouter
{
    private Application $app;
    private Controller $controller;

    /** @var array<string, array> Custom admin pages registered via hooks */
    private array $customPages = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->controller = new Controller($app);
    }

    /**
     * Register admin routes with the main router.
     */
    public function register(): void
    {
        if (!$this->app->config('admin.enabled', false)) {
            return;
        }

        $basePath = $this->app->config('admin.path', '/admin');
        $router = $this->app->router();

        // Allow plugins to register custom admin pages
        $this->customPages = Hooks::apply('admin.register_pages', [], $this->app);

        // Login (public)
        $router->addRoute($basePath . '/login', function (Request $request) {
            return $this->handle('login', $request, requireAuth: false);
        });

        // Logout (public, but needs session)
        $router->addRoute($basePath . '/logout', function (Request $request) {
            return $this->handle('logout', $request, requireAuth: false);
        });

        // Dashboard (protected)
        $router->addRoute($basePath, function (Request $request) {
            return $this->handle('dashboard', $request);
        });

        // Rebuild action (protected)
        $router->addRoute($basePath . '/rebuild', function (Request $request) {
            return $this->handle('rebuild', $request);
        });

        // Lint action (protected)
        $router->addRoute($basePath . '/lint', function (Request $request) {
            return $this->handle('lint', $request);
        });

        // System info (protected)
        $router->addRoute($basePath . '/system', function (Request $request) {
            return $this->handle('system', $request);
        });

        // Themes (protected)
        $router->addRoute($basePath . '/themes', function (Request $request) {
            return $this->handle('themes', $request);
        });

        // Shortcodes reference (protected)
        $router->addRoute($basePath . '/shortcodes', function (Request $request) {
            return $this->handle('shortcodes', $request);
        });

        // Admin logs (protected)
        $router->addRoute($basePath . '/logs', function (Request $request) {
            return $this->handle('logs', $request);
        });

        // Content list (protected) - pattern: /admin/content/{type}
        $router->addRoute($basePath . '/content/{type}', function (Request $request, array $params) {
            return $this->handleContent($request, $params['type']);
        });

        // Taxonomy detail (protected) - pattern: /admin/taxonomy/{taxonomy}
        $router->addRoute($basePath . '/taxonomy/{taxonomy}', function (Request $request, array $params) {
            return $this->handleTaxonomy($request, $params['taxonomy']);
        });

        // Register custom plugin pages
        foreach ($this->customPages as $slug => $pageConfig) {
            $router->addRoute($basePath . '/' . $slug, function (Request $request) use ($slug) {
                return $this->handleCustomPage($request, $slug);
            });
        }
    }

    /**
     * Get registered custom pages for use in views.
     */
    public function getCustomPages(): array
    {
        return $this->customPages;
    }

    /**
     * Handle an admin request.
     */
    private function handle(string $action, Request $request, bool $requireAuth = true): ?RouteMatch
    {
        $auth = $this->controller->auth();

        // Check authentication for protected routes
        if ($requireAuth && !$auth->check()) {
            $loginUrl = $this->app->config('admin.path', '/admin') . '/login';
            $response = Response::redirect($loginUrl);
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        $response = match ($action) {
            'login' => $this->controller->login($request),
            'logout' => $this->controller->logout($request),
            'dashboard' => $this->controller->dashboard($request),
            'rebuild' => $this->controller->rebuild($request),
            'lint' => $this->controller->lint($request),
            'system' => $this->controller->system($request),
            'themes' => $this->controller->themes($request),
            'shortcodes' => $this->controller->shortcodes($request),
            'logs' => $this->controller->logs($request),
            default => null,
        };

        if ($response === null) {
            return null;
        }

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle content list request.
     */
    private function handleContent(Request $request, string $type): ?RouteMatch
    {
        $auth = $this->controller->auth();

        if (!$auth->check()) {
            $loginUrl = $this->app->config('admin.path', '/admin') . '/login';
            $response = Response::redirect($loginUrl);
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        $response = $this->controller->contentList($request, $type);

        if ($response === null) {
            return null;
        }

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle taxonomy detail request.
     */
    private function handleTaxonomy(Request $request, string $taxonomy): ?RouteMatch
    {
        $auth = $this->controller->auth();

        if (!$auth->check()) {
            $loginUrl = $this->app->config('admin.path', '/admin') . '/login';
            $response = Response::redirect($loginUrl);
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        $response = $this->controller->taxonomyDetail($request, $taxonomy);

        if ($response === null) {
            return null;
        }

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle custom plugin page request.
     */
    private function handleCustomPage(Request $request, string $slug): ?RouteMatch
    {
        $auth = $this->controller->auth();

        if (!$auth->check()) {
            $loginUrl = $this->app->config('admin.path', '/admin') . '/login';
            $response = Response::redirect($loginUrl);
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        $pageConfig = $this->customPages[$slug] ?? null;
        if ($pageConfig === null || !isset($pageConfig['handler'])) {
            return null;
        }

        $handler = $pageConfig['handler'];
        $response = $handler($request, $this->app, $this->controller);

        if ($response === null) {
            return null;
        }

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }
}
