<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Index\IndexBuilder;
use Ava\Content\Index\ItemPaths;
use Ava\Content\Item;
use Ava\Testing\TestCase;

final class IndexBuilderTest extends TestCase
{
    private const POST_TYPE = [
        'url' => ['type' => 'pattern', 'pattern' => '/posts/{slug}', 'archive' => '/posts'],
        'templates' => ['single' => 'single.php', 'archive' => 'archive.php'],
        'taxonomies' => ['category', 'tag'],
    ];

    private const TAXONOMIES = [
        'category' => ['hierarchical' => true, 'rewrite' => ['base' => '/category']],
        'tag' => ['rewrite' => ['base' => '/tag']],
    ];

    private string $contentRoot;

    public function setUp(): void
    {
        $this->contentRoot = rtrim($this->app->configPath('content'), '/');
    }

    public function testUnlistedItemsArePublicDraftsArePreviewOnly(): void
    {
        $routes = $this->builder()->routes(['post' => [
            $this->post('unlisted-test', ['status' => 'unlisted']),
            $this->post('draft-test', ['status' => 'draft']),
        ]], ['post' => self::POST_TYPE], []);

        $this->assertArrayHasKey('/posts/unlisted-test', $routes['exact']);
        $this->assertEquals('/posts/unlisted-test', $routes['reverse']['post:unlisted-test'] ?? null);

        // Drafts must stay out of 'exact' (plugins enumerate it to publish
        // the site) but be reachable at their real URL with a preview link.
        $this->assertFalse(isset($routes['exact']['/posts/draft-test']));
        $this->assertFalse(isset($routes['reverse']['post:draft-test']));
        $this->assertEquals('draft-test', $routes['preview']['/posts/draft-test']['content_key'] ?? null);
    }

    public function testHierarchicalRoutesUsePathBasedContentKeys(): void
    {
        $items = [
            new Item(['title' => 'About Team', 'slug' => 'team', 'status' => 'published'], '', $this->contentRoot . '/pages/about/team.md', 'page'),
            new Item(['title' => 'Company Team', 'slug' => 'team', 'status' => 'published'], '', $this->contentRoot . '/pages/company/team.md', 'page'),
        ];
        $routes = $this->builder()->routes(
            ['page' => $items],
            ['page' => ['url' => ['type' => 'hierarchical', 'base' => '/'], 'templates' => ['single' => 'page.php']]],
            []
        );

        $this->assertEquals('/about/team', $routes['reverse']['page:about/team'] ?? null);
        $this->assertEquals('/company/team', $routes['reverse']['page:company/team'] ?? null);
        $this->assertEquals('about/team', $routes['exact']['/about/team']['content_key'] ?? null);
        $this->assertFalse(isset($routes['reverse']['page:team']));
    }

    public function testRedirectSourcesAreStoredTheWayTheRouterLooksThemUp(): void
    {
        $routes = $this->builder()->routes(['post' => [
            $this->post('moved', ['redirect_from' => ['/old-path/', 'older', 'https://example.com/elsewhere']]),
        ]], ['post' => self::POST_TYPE], []);

        $this->assertEquals('/posts/moved', $routes['redirects']['/old-path']['to'] ?? null);
        $this->assertEquals('/posts/moved', $routes['redirects']['/older']['to'] ?? null);
        $this->assertCount(2, $routes['redirects']);
    }

    public function testUrlCollisionsAreReported(): void
    {
        $builder = $this->builder();
        $builder->routes([
            'post' => [$this->post('same')],
            'news' => [new Item(['title' => 'Same', 'slug' => 'same', 'status' => 'published'], '', $this->contentRoot . '/news/same.md', 'news')],
        ], ['post' => self::POST_TYPE, 'news' => self::POST_TYPE], []);

        $this->assertCount(1, $builder->errors());
        $this->assertStringContains('/posts/same', $builder->errors()[0]);
    }

    public function testTaxonomyIndexOnlyIncludesDeclaringContentTypes(): void
    {
        $post = $this->post('post', ['category' => 'design']);
        $page = new Item(['slug' => 'page', 'status' => 'published', 'category' => 'design'], '', $this->contentRoot . '/pages/page.md', 'page');

        $index = $this->builder()->taxonomyIndex(
            ['post' => [$post], 'page' => [$page]],
            ['category' => []],
            ['post' => self::POST_TYPE, 'page' => ['taxonomies' => [], 'url' => ['type' => 'hierarchical']]]
        );

        $this->assertEquals(1, $index['category']['terms']['design']['count']);
        $this->assertEquals(['post:post'], $index['category']['terms']['design']['items']);
    }

    public function testTermsAreNormalisedToSlugsAndKeepTheirSpelling(): void
    {
        $index = $this->builder()->taxonomyIndex(['post' => [
            $this->post('one', ['category' => 'Tutorials', 'tag' => ['Web Dev', 'PHP', 'Café']]),
            $this->post('two', ['category' => 'tutorials', 'tag' => ['web-dev']]),
            $this->post('three', ['category' => 'Guides/PHP Tips']),
        ]], self::TAXONOMIES, ['post' => self::POST_TYPE]);

        $terms = $index['tag']['terms'];
        $this->assertEquals(2, $index['category']['terms']['tutorials']['count']);
        $this->assertEquals('Tutorials', $index['category']['terms']['tutorials']['name']);
        $this->assertEquals(2, $terms['web-dev']['count']);
        $this->assertEquals('Web Dev', $terms['web-dev']['name']);
        $this->assertArrayHasKey('php', $terms);
        $this->assertArrayHasKey('café', $terms);
        $this->assertArrayHasKey('guides/php-tips', $index['category']['terms']);
    }

    public function testTwoSpellingsOfATermCountAnItemOnce(): void
    {
        $index = $this->builder()->taxonomyIndex(['post' => [
            $this->post('one', ['tag' => ['Web Dev', 'web-dev']]),
        ]], self::TAXONOMIES, ['post' => self::POST_TYPE]);

        $this->assertEquals(1, $index['tag']['terms']['web-dev']['count']);
        $this->assertEquals(['post:one'], $index['tag']['terms']['web-dev']['items']);
    }

    public function testMetadataIsStoredOnceWithoutBodies(): void
    {
        $post = $this->post('with-id', ['id' => '01HX', 'tag' => ['Web Dev']], 'A long body');
        $index = $this->builder()->contentIndex(['post' => [$post]], ['post' => self::POST_TYPE]);

        $data = $index['by_type']['post']['with-id'];
        $this->assertFalse(array_key_exists('body', $data));
        $this->assertEquals(['category' => [], 'tag' => ['web-dev']], $data['taxonomies']);
        $this->assertEquals(['post', 'with-id'], $index['by_id']['01HX']);
        $this->assertEquals(['post', 'with-id'], $index['by_path']['posts/with-id.md']);
        $this->assertEquals(['post:with-id' => 'A long body'], $this->builder()->bodies(['post' => [$post]], ['post' => self::POST_TYPE]));
    }

    public function testRecentCacheSortsLikeTheFullQueryPath(): void
    {
        $cache = $this->builder()->recentCache(['post' => [
            $this->post('b', ['title' => 'B', 'date' => '2026-01-02']),
            $this->post('a', ['title' => 'A', 'date' => '2026-01-02']),
            $this->post('new', ['title' => 'New', 'date' => '2026-03-01']),
            $this->post('draft', ['status' => 'draft', 'date' => '2026-04-01']),
        ]], ['post' => self::POST_TYPE]);

        $this->assertEquals(3, $cache['post']['total']);
        $this->assertEquals(['new', 'a', 'b'], array_column($cache['post']['items'], 'slug'));
    }

    private function builder(): IndexBuilder
    {
        return new IndexBuilder(new ItemPaths($this->contentRoot), $this->contentRoot);
    }

    private function post(string $slug, array $frontmatter = [], string $body = ''): Item
    {
        return new Item(
            $frontmatter + ['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published'],
            $body,
            $this->contentRoot . '/posts/' . $slug . '.md',
            'post'
        );
    }
}
