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

        // Flush webpage cache action (protected)
        $router->addRoute($basePath . '/flush-pages', function (Request $request) {
            return $this->handle('flushWebpageCache', $request);
        });

        // System info (protected)
        $router->addRoute($basePath . '/system', function (Request $request) {
            return $this->handle('system', $request);
        });

        // Clear error log (protected)
        $router->addRoute($basePath . '/clear-errors', function (Request $request) {
            return $this->handle('clearErrorLog', $request);
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

        // Content lint page (protected)
        $router->addRoute($basePath . '/lint', function (Request $request) {
            return $this->handle('lint', $request);
        });

        // Media uploader (protected)
        $router->addRoute($basePath . '/media', function (Request $request) {
            return $this->handleMedia($request);
        });

        // Content list (protected) - pattern: /admin/content/{type}
        $router->addRoute($basePath . '/content/{type}', function (Request $request, array $params) {
            return $this->handleContent($request, $params['type']);
        });

        // Content create (protected) - pattern: /admin/content/{type}/create
        $router->addRoute($basePath . '/content/{type}/create', function (Request $request, array $params) {
            return $this->handleContentCreate($request, $params['type']);
        });

        // Content edit (protected) - pattern: /admin/content/{type}/{slug}/edit
        $router->addRoute($basePath . '/content/{type}/{slug}/edit', function (Request $request, array $params) {
            return $this->handleContentEdit($request, $params['type'], $params['slug']);
        });

        // Content delete (protected) - pattern: /admin/content/{type}/{slug}/delete
        $router->addRoute($basePath . '/content/{type}/{slug}/delete', function (Request $request, array $params) {
            return $this->handleContentDelete($request, $params['type'], $params['slug']);
        });

        // Taxonomy detail (protected) - pattern: /admin/taxonomy/{taxonomy}
        $router->addRoute($basePath . '/taxonomy/{taxonomy}', function (Request $request, array $params) {
            return $this->handleTaxonomy($request, $params['taxonomy']);
        });

        // Taxonomy term create (protected) - pattern: /admin/taxonomy/{taxonomy}/create
        $router->addRoute($basePath . '/taxonomy/{taxonomy}/create', function (Request $request, array $params) {
            return $this->handleTaxonomyTermCreate($request, $params['taxonomy']);
        });

        // Taxonomy term delete (protected) - pattern: /admin/taxonomy/{taxonomy}/{term}/delete
        $router->addRoute($basePath . '/taxonomy/{taxonomy}/{term}/delete', function (Request $request, array $params) {
            return $this->handleTaxonomyTermDelete($request, $params['taxonomy'], $params['term']);
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
     * Check HTTPS and authentication requirements.
     * Returns a RouteMatch with error/redirect if checks fail, null if OK.
     */
    private function checkAccess(Request $request, bool $requireAuth = true): ?RouteMatch
    {
        // Enforce HTTPS for admin access (except on localhost)
        if (!$request->isSecure() && !$request->isLocalhost()) {
            $response = $this->applyAdminSecurityHeaders(new Response(
                '<h1>HTTPS Required</h1>' .
                '<p>The admin dashboard requires HTTPS for security. Your password and session cookies ' .
                'would be transmitted in plain text over HTTP.</p>' .
                '<p>Please access the admin via <strong>https://' . htmlspecialchars($request->host()) . 
                htmlspecialchars($request->path()) . '</strong></p>',
                403,
                ['Content-Type' => 'text/html; charset=utf-8']
            ));
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        // Check authentication for protected routes
        if ($requireAuth && !$this->controller->auth()->check()) {
            $loginUrl = $this->app->config('admin.path', '/admin') . '/login';
            $response = $this->applyAdminSecurityHeaders(Response::redirect($loginUrl));
            return new RouteMatch(
                type: 'admin',
                template: '__raw__',
                params: ['response' => $response]
            );
        }

        return null;
    }

    /**
     * Handle an admin request.
     */
    private function handle(string $action, Request $request, bool $requireAuth = true): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request, $requireAuth);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = match ($action) {
            'login' => $this->controller->login($request),
            'logout' => $this->controller->logout($request),
            'dashboard' => $this->controller->dashboard($request),
            'rebuild' => $this->controller->rebuild($request),
            'flushWebpageCache' => $this->controller->flushWebpageCache($request),
            'clearErrorLog' => $this->controller->clearErrorLog($request),
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

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle media upload request.
     */
    private function handleMedia(Request $request): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->media($request);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

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
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->contentList($request, $type);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

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
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->taxonomyDetail($request, $taxonomy);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle taxonomy term create request.
     */
    private function handleTaxonomyTermCreate(Request $request, string $taxonomy): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->taxonomyTermCreate($request, $taxonomy);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle taxonomy term delete request.
     */
    private function handleTaxonomyTermDelete(Request $request, string $taxonomy, string $term): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->taxonomyTermDelete($request, $taxonomy, $term);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle content create request.
     */
    private function handleContentCreate(Request $request, string $type): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->contentCreate($request, $type);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle content edit request.
     */
    private function handleContentEdit(Request $request, string $type, string $slug): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->contentEdit($request, $type, $slug);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Handle content delete request.
     */
    private function handleContentDelete(Request $request, string $type, string $slug): ?RouteMatch
    {
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $response = $this->controller->contentDelete($request, $type, $slug);

        if ($response === null) {
            return null;
        }

        $response = $this->applyAdminSecurityHeaders($response);

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
        $accessCheck = $this->checkAccess($request);
        if ($accessCheck !== null) {
            return $accessCheck;
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

        $response = $this->applyAdminSecurityHeaders($response);

        return new RouteMatch(
            type: 'admin',
            template: '__raw__',
            params: ['response' => $response]
        );
    }

    /**
     * Apply strict security headers for all admin responses.
     * This intentionally does not affect public site pages.
     */
    private function applyAdminSecurityHeaders(Response $response): Response
    {
        // Admin should never be cached by browsers/proxies.
        // (Avoids back-button showing sensitive pages post-logout, shared proxy caches, etc.)
        $headers = [
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',

            // Stronger anti-framing for admin.
            'X-Frame-Options' => 'DENY',

            // Cross-origin isolation hardening.
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',

            // Limit powerful APIs.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',

            // CSP tuned for the current admin templates (inline theme CSS + inline JS + Google font stylesheet).
            // If you later remove inline handlers/scripts, you can tighten script-src/style-src.
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "base-uri 'none'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "img-src 'self' data:",
                "font-src 'self' https://fonts.gstatic.com data:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self'",
            ]),
        ];

        return $response->withHeaders($headers);
    }

}
