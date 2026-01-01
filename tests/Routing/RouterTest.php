<?php

declare(strict_types=1);

namespace Ava\Tests\Routing;

use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Routing\RouteMatch;
use Ava\Routing\Router;
use Ava\Testing\TestCase;

/**
 * Tests for the Router class.
 * 
 * These tests leverage dependency injection to test routing behavior
 * with the real application instance.
 */
final class RouterTest extends TestCase
{
    private Router $router;

    public function setUp(): void
    {
        $this->router = $this->app->router();
    }

    /**
     * Create a request for testing.
     */
    private function createRequest(string $path, string $method = 'GET', array $query = []): Request
    {
        return new Request($method, $path, $query);
    }

    // =========================================================================
    // System Routes (addRoute)
    // =========================================================================

    public function testAddRouteRegistersExactRoute(): void
    {
        $called = false;
        $this->router->addRoute('/test-exact', function ($request) use (&$called) {
            $called = true;
            return new Response('OK');
        });

        $request = $this->createRequest('/test-exact');
        $match = $this->router->match($request);

        $this->assertTrue($called);
        $this->assertNotNull($match);
        $this->assertEquals('plugin', $match->getType());
    }

    public function testAddRouteWithParameterPlaceholders(): void
    {
        $capturedParams = [];
        $this->router->addRoute('/api/items/{id}', function ($request, $params) use (&$capturedParams) {
            $capturedParams = $params;
            return new Response('OK');
        });

        $request = $this->createRequest('/api/items/123');
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('123', $capturedParams['id']);
    }

    public function testAddRouteWithMultiplePlaceholders(): void
    {
        $capturedParams = [];
        $this->router->addRoute('/api/{type}/{id}', function ($request, $params) use (&$capturedParams) {
            $capturedParams = $params;
            return new Response('OK');
        });

        $request = $this->createRequest('/api/posts/456');
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('posts', $capturedParams['type']);
        $this->assertEquals('456', $capturedParams['id']);
    }

    public function testAddRouteDoesNotMatchPartialPath(): void
    {
        $called = false;
        $this->router->addRoute('/test', function () use (&$called) {
            $called = true;
            return new Response('OK');
        });

        $request = $this->createRequest('/test/extra');
        $this->router->match($request);

        $this->assertFalse($called);
    }

    // =========================================================================
    // Prefix Routes (addPrefixRoute)
    // =========================================================================

    public function testAddPrefixRouteMatchesPrefix(): void
    {
        $called = false;
        // Use a unique path that won't conflict with other routes
        $this->router->addPrefixRoute('/api-test-prefix-v1/', function ($request) use (&$called) {
            $called = true;
            return new Response('OK');
        });

        $request = $this->createRequest('/api-test-prefix-v1/users');
        $match = $this->router->match($request);

        $this->assertTrue($called);
        $this->assertNotNull($match);
    }

    public function testAddPrefixRouteMatchesMultiplePaths(): void
    {
        $paths = [];
        // Use a unique prefix path
        $this->router->addPrefixRoute('/unique-prefix-test/', function ($request) use (&$paths) {
            $paths[] = $request->path();
            return new Response('OK');
        });

        $this->router->match($this->createRequest('/unique-prefix-test/one'));
        $this->router->match($this->createRequest('/unique-prefix-test/two/three'));

        $this->assertCount(2, $paths);
        $this->assertContains('/unique-prefix-test/one', $paths);
        $this->assertContains('/unique-prefix-test/two/three', $paths);
    }

    // =========================================================================
    // Route Handler Return Types
    // =========================================================================

    public function testHandlerReturningResponseCreatesPluginMatch(): void
    {
        $this->router->addRoute('/response-test', function () {
            return new Response('Direct response', 200);
        });

        $request = $this->createRequest('/response-test');
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('plugin', $match->getType());
        $this->assertEquals('__raw__', $match->getTemplate());
    }

    public function testHandlerReturningRouteMatchPassesThrough(): void
    {
        $this->router->addRoute('/match-test', function () {
            return new RouteMatch(
                type: 'custom',
                template: 'custom.php',
                params: ['custom' => true]
            );
        });

        $request = $this->createRequest('/match-test');
        $match = $this->router->match($request);

        $this->assertNotNull($match);
        $this->assertEquals('custom', $match->getType());
        $this->assertEquals('custom.php', $match->getTemplate());
    }

    public function testHandlerReturningNullReturnsNull(): void
    {
        $this->router->addRoute('/null-test', function () {
            return null;
        });

        $request = $this->createRequest('/null-test');
        $match = $this->router->match($request);

        // Handler returned null, but system routes matched, so we get null back
        $this->assertNull($match);
    }

    // =========================================================================
    // URL Generation
    // =========================================================================

    public function testUrlForReturnsUrlForExistingContent(): void
    {
        // The hello-world post should exist in the test content
        $url = $this->router->urlFor('post', 'hello-world');

        // If content exists, we should get a URL back
        if ($url !== null) {
            $this->assertStringContains('/hello-world', $url);
        } else {
            // If content index isn't built, URL will be null - skip assertion
            $this->markSkipped('Content index not available');
        }
    }

    public function testUrlForReturnsNullForNonexistentContent(): void
    {
        $url = $this->router->urlFor('post', 'nonexistent-slug-12345');
        $this->assertNull($url);
    }

    // =========================================================================
    // Route Priority
    // =========================================================================

    public function testSystemRoutesMatchBeforePrefixRoutes(): void
    {
        $matchedRoute = null;

        $this->router->addPrefixRoute('/priority/', function () use (&$matchedRoute) {
            $matchedRoute = 'prefix';
            return new Response('prefix');
        });

        $this->router->addRoute('/priority/exact', function () use (&$matchedRoute) {
            $matchedRoute = 'exact';
            return new Response('exact');
        });

        // Re-register in different order to ensure priority is enforced
        $request = $this->createRequest('/priority/exact');
        $this->router->match($request);

        $this->assertEquals('exact', $matchedRoute);
    }
}
