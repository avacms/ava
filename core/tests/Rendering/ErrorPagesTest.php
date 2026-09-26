<?php

declare(strict_types=1);

namespace Ava\Tests\Rendering;

use Ava\Testing\TestCase;
use Ava\Rendering\ErrorPages;

/**
 * Tests for built-in error pages.
 */
final class ErrorPagesTest extends TestCase
{
    // =========================================================================
    // 404 Page
    // =========================================================================

    public function testRender404ReturnsHtml(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('<!DOCTYPE html>', $html);
        $this->assertStringContains('</html>', $html);
    }

    public function testRender404ContainsTitle(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('<title>Page Not Found', $html);
    }

    public function testRender404ContainsErrorCode(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('404', $html);
    }

    public function testRender404ContainsHomeLink(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('href="/"', $html);
    }

    public function testRender404ContainsDocsLink(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('ava.addy.zone', $html);
    }

    public function testRender404IncludesRequestedPath(): void
    {
        $html = ErrorPages::render404('/some/missing/page');
        $this->assertStringContains('/some/missing/page', $html);
    }

    public function testRender404EscapesRequestedPath(): void
    {
        $html = ErrorPages::render404('/<script>alert(1)</script>');
        $this->assertStringNotContains('<script>alert', $html);
        $this->assertStringContains('&lt;script&gt;', $html);
    }

    public function testRender404HasNoindexMeta(): void
    {
        $html = ErrorPages::render404();
        $this->assertStringContains('noindex', $html);
    }

    // =========================================================================
    // 500 Page
    // =========================================================================

    public function testRender500ReturnsHtml(): void
    {
        $html = ErrorPages::render500();
        $this->assertStringContains('<!DOCTYPE html>', $html);
        $this->assertStringContains('</html>', $html);
    }

    public function testRender500ContainsTitle(): void
    {
        $html = ErrorPages::render500();
        $this->assertStringContains('<title>Server Error', $html);
    }

    public function testRender500ContainsErrorCode(): void
    {
        $html = ErrorPages::render500();
        $this->assertStringContains('500', $html);
    }

    public function testRender500SaysTheErrorWasLoggedWithoutNamingPaths(): void
    {
        $html = ErrorPages::render500(null, null, true);
        $this->assertStringContains('has been logged', $html);
        $this->assertStringNotContains('storage/logs', $html);
    }

    public function testRender500ShowsNoSettingsToVisitorsWhenLoggingIsOff(): void
    {
        $html = ErrorPages::render500(null, null, false);
        $this->assertStringNotContains('has been logged', $html);
        $this->assertStringNotContains('debug.', $html);
    }

    public function testRender500IncludesErrorId(): void
    {
        $html = ErrorPages::render500('abc123');
        $this->assertStringContains('abc123', $html);
    }

    public function testRender500EscapesErrorId(): void
    {
        $html = ErrorPages::render500('<script>alert(1)</script>');
        $this->assertStringNotContains('<script>alert', $html);
    }

    public function testRender500HasNoindexMeta(): void
    {
        $html = ErrorPages::render500();
        $this->assertStringContains('noindex', $html);
    }

    public function testRender500HidesHomeLinkOnHomepage(): void
    {
        $html = ErrorPages::render500(null, '/');
        $this->assertStringNotContains('Go Home', $html);
    }

    public function testRender500ShowsHomeLinkOnOtherPages(): void
    {
        $html = ErrorPages::render500(null, '/some/page');
        $this->assertStringContains('Go Home', $html);
    }

    // =========================================================================
    // 503 Page
    // =========================================================================

    public function testRender503ReturnsHtml(): void
    {
        $html = ErrorPages::render503();
        $this->assertStringContains('<!DOCTYPE html>', $html);
        $this->assertStringContains('</html>', $html);
    }

    public function testRender503ContainsTitle(): void
    {
        $html = ErrorPages::render503();
        $this->assertStringContains('<title>Maintenance', $html);
    }

    public function testRender503ContainsErrorCode(): void
    {
        $html = ErrorPages::render503();
        $this->assertStringContains('503', $html);
    }

    public function testRender503ContainsAutoRefresh(): void
    {
        $html = ErrorPages::render503();
        $this->assertStringContains('http-equiv="refresh"', $html);
    }

    public function testRender503IncludesCustomMessage(): void
    {
        $html = ErrorPages::render503('Upgrading database');
        $this->assertStringContains('Upgrading database', $html);
    }

    public function testRender503EscapesCustomMessage(): void
    {
        $html = ErrorPages::render503('<script>alert(1)</script>');
        $this->assertStringNotContains('<script>alert', $html);
    }

    public function testRender503HasNoindexMeta(): void
    {
        $html = ErrorPages::render503();
        $this->assertStringContains('noindex', $html);
    }
}
