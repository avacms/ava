<?php

declare(strict_types=1);

namespace Ava\Tests\Http;

use Ava\Http\Request;
use Ava\Testing\TestCase;

/**
 * Tests for the HTTP Request class.
 */
final class RequestTest extends TestCase
{
    // =========================================================================
    // Constructor & Basic Properties
    // =========================================================================

    public function testConstructorSetsMethod(): void
    {
        $request = new Request('GET', '/path');
        $this->assertEquals('GET', $request->method());
    }

    public function testConstructorUppercasesMethod(): void
    {
        $request = new Request('post', '/path');
        $this->assertEquals('POST', $request->method());
    }

    public function testConstructorSetsUri(): void
    {
        $request = new Request('GET', '/path?query=value');
        $this->assertEquals('/path?query=value', $request->uri());
    }

    public function testConstructorExtractsPathFromUri(): void
    {
        $request = new Request('GET', '/path?query=value');
        $this->assertEquals('/path', $request->path());
    }

    public function testConstructorDefaultsToRootPath(): void
    {
        $request = new Request('GET', '');
        $this->assertEquals('/', $request->path());
    }

    // =========================================================================
    // Method Checks
    // =========================================================================

    public function testIsMethodReturnsTrueForMatch(): void
    {
        $request = new Request('POST', '/path');
        $this->assertTrue($request->isMethod('POST'));
    }

    public function testIsMethodIsCaseInsensitive(): void
    {
        $request = new Request('POST', '/path');
        $this->assertTrue($request->isMethod('post'));
    }

    public function testIsMethodReturnsFalseForMismatch(): void
    {
        $request = new Request('POST', '/path');
        $this->assertFalse($request->isMethod('GET'));
    }

    // =========================================================================
    // Normalized Path
    // =========================================================================

    public function testNormalizedPathWithoutTrailingSlash(): void
    {
        $request = new Request('GET', '/path/');
        $this->assertEquals('/path', $request->normalizedPath(false));
    }

    public function testNormalizedPathWithTrailingSlash(): void
    {
        $request = new Request('GET', '/path');
        $this->assertEquals('/path/', $request->normalizedPath(true));
    }

    public function testNormalizedPathPreservesRoot(): void
    {
        $request = new Request('GET', '/');
        $this->assertEquals('/', $request->normalizedPath(false));
        $this->assertEquals('/', $request->normalizedPath(true));
    }

    // =========================================================================
    // Query Parameters
    // =========================================================================

    public function testQueryReturnsAllParameters(): void
    {
        $request = new Request('GET', '/path', ['a' => '1', 'b' => '2']);
        $query = $request->query();

        $this->assertEquals('1', $query['a']);
        $this->assertEquals('2', $query['b']);
    }

    public function testQueryReturnsSpecificParameter(): void
    {
        $request = new Request('GET', '/path', ['name' => 'value']);
        $this->assertEquals('value', $request->query('name'));
    }

    public function testQueryReturnsDefaultForMissing(): void
    {
        $request = new Request('GET', '/path', []);
        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    public function testQueryReturnsNullForMissingWithNoDefault(): void
    {
        $request = new Request('GET', '/path', []);
        $this->assertNull($request->query('missing'));
    }

    // =========================================================================
    // Headers
    // =========================================================================

    public function testHeaderReturnsValue(): void
    {
        $request = new Request('GET', '/path', [], ['Content-Type' => 'application/json']);
        $this->assertEquals('application/json', $request->header('content-type'));
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $request = new Request('GET', '/path', [], ['X-Custom-Header' => 'value']);
        $this->assertEquals('value', $request->header('x-custom-header'));
    }

    public function testHeaderReturnsDefaultForMissing(): void
    {
        $request = new Request('GET', '/path', [], []);
        $this->assertEquals('default', $request->header('missing', 'default'));
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $request = new Request('GET', '/path', [], [
            'Accept' => 'text/html',
            'Host' => 'example.com',
        ]);

        $headers = $request->headers();
        $this->assertArrayHasKey('accept', $headers);
        $this->assertArrayHasKey('host', $headers);
    }

    // =========================================================================
    // Body
    // =========================================================================

    public function testBodyReturnsRequestBody(): void
    {
        $request = new Request('POST', '/path', [], [], '{"data": "value"}');
        $this->assertEquals('{"data": "value"}', $request->body());
    }

    public function testBodyDefaultsToEmptyString(): void
    {
        $request = new Request('GET', '/path');
        $this->assertEquals('', $request->body());
    }

    // =========================================================================
    // Content Type Checks
    // =========================================================================

    public function testExpectsJsonReturnsTrueForJsonAccept(): void
    {
        $request = new Request('GET', '/path', [], ['Accept' => 'application/json']);
        $this->assertTrue($request->expectsJson());
    }

    public function testExpectsJsonReturnsFalseForHtmlAccept(): void
    {
        $request = new Request('GET', '/path', [], ['Accept' => 'text/html']);
        $this->assertFalse($request->expectsJson());
    }

    public function testExpectsJsonReturnsFalseForNoAccept(): void
    {
        $request = new Request('GET', '/path', [], []);
        $this->assertFalse($request->expectsJson());
    }
}
