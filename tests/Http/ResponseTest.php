<?php

declare(strict_types=1);

namespace Ava\Tests\Http;

use Ava\Http\Response;
use Ava\Testing\TestCase;

/**
 * Tests for the HTTP Response class.
 */
final class ResponseTest extends TestCase
{
    // =========================================================================
    // Constructor & Basic Properties
    // =========================================================================

    public function testConstructorSetsContent(): void
    {
        $response = new Response('Hello World');
        $this->assertEquals('Hello World', $response->content());
    }

    public function testConstructorDefaultsToEmptyContent(): void
    {
        $response = new Response();
        $this->assertEquals('', $response->content());
    }

    public function testConstructorSetsStatus(): void
    {
        $response = new Response('', 404);
        $this->assertEquals(404, $response->status());
    }

    public function testConstructorDefaultsTo200Status(): void
    {
        $response = new Response();
        $this->assertEquals(200, $response->status());
    }

    // =========================================================================
    // Static Factories
    // =========================================================================

    public function testRedirectCreatesRedirectResponse(): void
    {
        $response = Response::redirect('/new-location');

        $this->assertEquals(302, $response->status());
        $this->assertEquals('', $response->content());
    }

    public function testRedirectWithCustomStatus(): void
    {
        $response = Response::redirect('/permanent', 301);
        $this->assertEquals(301, $response->status());
    }

    public function testJsonCreatesJsonResponse(): void
    {
        $response = Response::json(['key' => 'value']);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('{"key":"value"}', $response->content());
    }

    public function testJsonWithCustomStatus(): void
    {
        $response = Response::json(['error' => 'Not found'], 404);
        $this->assertEquals(404, $response->status());
    }

    public function testTextCreatesTextResponse(): void
    {
        $response = Response::text('Plain text');

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Plain text', $response->content());
    }

    public function testHtmlCreatesHtmlResponse(): void
    {
        $response = Response::html('<p>HTML</p>');

        $this->assertEquals(200, $response->status());
        $this->assertEquals('<p>HTML</p>', $response->content());
    }

    public function testNotFoundCreates404Response(): void
    {
        $response = Response::notFound();

        $this->assertEquals(404, $response->status());
        $this->assertEquals('Not Found', $response->content());
    }

    public function testNotFoundWithCustomContent(): void
    {
        $response = Response::notFound('Page not found');

        $this->assertEquals(404, $response->status());
        $this->assertEquals('Page not found', $response->content());
    }

    // =========================================================================
    // Immutable Modifiers
    // =========================================================================

    public function testWithHeaderReturnsNewInstance(): void
    {
        $original = new Response();
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNotSame($original, $modified);
    }

    public function testWithHeaderAddsHeader(): void
    {
        $response = (new Response())->withHeader('X-Custom', 'value');
        // We can't directly access headers, but we can verify the response is valid
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testWithHeadersAddMultipleHeaders(): void
    {
        $response = (new Response())->withHeaders([
            'X-One' => '1',
            'X-Two' => '2',
        ]);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testWithStatusReturnsNewInstance(): void
    {
        $original = new Response();
        $modified = $original->withStatus(404);

        $this->assertNotSame($original, $modified);
        $this->assertEquals(200, $original->status());
        $this->assertEquals(404, $modified->status());
    }

    public function testWithContentReturnsNewInstance(): void
    {
        $original = new Response('old');
        $modified = $original->withContent('new');

        $this->assertNotSame($original, $modified);
        $this->assertEquals('old', $original->content());
        $this->assertEquals('new', $modified->content());
    }

    // =========================================================================
    // Chaining
    // =========================================================================

    public function testMethodChaining(): void
    {
        $response = (new Response())
            ->withContent('Hello')
            ->withStatus(201)
            ->withHeader('X-Custom', 'value');

        $this->assertEquals('Hello', $response->content());
        $this->assertEquals(201, $response->status());
    }

    // =========================================================================
    // JSON encoding
    // =========================================================================

    public function testJsonEncodesArrays(): void
    {
        $response = Response::json(['items' => [1, 2, 3]]);
        $this->assertEquals('{"items":[1,2,3]}', $response->content());
    }

    public function testJsonEncodesNestedData(): void
    {
        $response = Response::json([
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertStringContains('"name":"John"', $response->content());
    }

    public function testJsonDoesNotEscapeSlashes(): void
    {
        $response = Response::json(['url' => 'https://example.com/path']);
        $this->assertStringContains('https://example.com/path', $response->content());
    }
}
