<?php

declare(strict_types=1);

namespace Ava\Tests\Routing;

use Ava\Content\Item;
use Ava\Http\Response;
use Ava\Routing\RouteMatch;
use Ava\Testing\TestCase;

/**
 * Tests for the RouteMatch value object.
 */
final class RouteMatchTest extends TestCase
{
    // =========================================================================
    // Basic properties
    // =========================================================================

    public function testGetTypeReturnsType(): void
    {
        $match = new RouteMatch('single');
        $this->assertEquals('single', $match->getType());
    }

    public function testGetTemplateReturnsTemplate(): void
    {
        $match = new RouteMatch('single', null, null, null, 'post.php');
        $this->assertEquals('post.php', $match->getTemplate());
    }

    public function testGetTemplateDefaultsToIndex(): void
    {
        $match = new RouteMatch('single');
        $this->assertEquals('index.php', $match->getTemplate());
    }

    // =========================================================================
    // Content Item
    // =========================================================================

    public function testGetContentItemReturnsItem(): void
    {
        $item = new Item(['title' => 'Test'], 'content', '/test.md', 'post');
        $match = new RouteMatch('single', $item);

        $this->assertInstanceOf(Item::class, $match->getContentItem());
        $this->assertEquals('Test', $match->getContentItem()->title());
    }

    public function testGetContentItemReturnsNullWhenNotSet(): void
    {
        $match = new RouteMatch('archive');
        $this->assertNull($match->getContentItem());
    }

    // =========================================================================
    // Taxonomy
    // =========================================================================

    public function testGetTaxonomyReturnsTaxonomyData(): void
    {
        $tax = ['name' => 'categories', 'term' => 'tutorials', 'label' => 'Tutorials'];
        $match = new RouteMatch('taxonomy', null, null, $tax);

        $this->assertEquals($tax, $match->getTaxonomy());
    }

    public function testGetTaxonomyReturnsNullWhenNotSet(): void
    {
        $match = new RouteMatch('single');
        $this->assertNull($match->getTaxonomy());
    }

    // =========================================================================
    // Redirects
    // =========================================================================

    public function testIsRedirectReturnsTrueWhenRedirectSet(): void
    {
        $match = new RouteMatch('redirect', null, null, null, 'index.php', '/new-url', 301);
        $this->assertTrue($match->isRedirect());
    }

    public function testIsRedirectReturnsFalseWhenNotSet(): void
    {
        $match = new RouteMatch('single');
        $this->assertFalse($match->isRedirect());
    }

    public function testGetRedirectUrlReturnsUrl(): void
    {
        $match = new RouteMatch('redirect', null, null, null, 'index.php', '/new-url', 301);
        $this->assertEquals('/new-url', $match->getRedirectUrl());
    }

    public function testGetRedirectCodeReturnsCode(): void
    {
        $match = new RouteMatch('redirect', null, null, null, 'index.php', '/new-url', 301);
        $this->assertEquals(301, $match->getRedirectCode());
    }

    public function testGetRedirectCodeDefaultsTo302(): void
    {
        $match = new RouteMatch('redirect');
        $this->assertEquals(302, $match->getRedirectCode());
    }

    // =========================================================================
    // Params
    // =========================================================================

    public function testGetParamsReturnsAllParams(): void
    {
        $match = new RouteMatch('single', null, null, null, 'index.php', null, 302, [
            'slug' => 'hello-world',
            'page' => 1,
        ]);

        $params = $match->getParams();
        $this->assertEquals('hello-world', $params['slug']);
        $this->assertEquals(1, $params['page']);
    }

    public function testGetParamReturnsSingleParam(): void
    {
        $match = new RouteMatch('single', null, null, null, 'index.php', null, 302, [
            'slug' => 'hello-world',
        ]);

        $this->assertEquals('hello-world', $match->getParam('slug'));
    }

    public function testGetParamReturnsDefaultForMissing(): void
    {
        $match = new RouteMatch('single');
        $this->assertEquals('default', $match->getParam('missing', 'default'));
    }

    public function testGetParamReturnsNullForMissingWithNoDefault(): void
    {
        $match = new RouteMatch('single');
        $this->assertNull($match->getParam('missing'));
    }

    // =========================================================================
    // Response
    // =========================================================================

    public function testHasResponseReturnsTrueWhenSet(): void
    {
        $response = new Response('content', 200);
        $match = new RouteMatch('plugin', null, null, null, 'index.php', null, 302, [], $response);

        $this->assertTrue($match->hasResponse());
    }

    public function testHasResponseReturnsFalseWhenNotSet(): void
    {
        $match = new RouteMatch('single');
        $this->assertFalse($match->hasResponse());
    }

    public function testGetResponseReturnsResponse(): void
    {
        $response = new Response('plugin content', 200);
        $match = new RouteMatch('plugin', null, null, null, 'index.php', null, 302, [], $response);

        $this->assertInstanceOf(Response::class, $match->getResponse());
        $this->assertEquals('plugin content', $match->getResponse()->content());
    }

    public function testGetResponseReturnsNullWhenNotSet(): void
    {
        $match = new RouteMatch('single');
        $this->assertNull($match->getResponse());
    }
}
