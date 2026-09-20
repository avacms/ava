<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Content\Indexer;
use Ava\Content\Item;
use Ava\Testing\TestCase;

final class IndexerRoutesTest extends TestCase
{
    public function testBuildRoutesIncludesUnlistedInExactRoutes(): void
    {
        $indexer = new Indexer($this->app);

        $contentPath = $this->app->configPath('content');
        $item = new Item(
            [
                'title' => 'Unlisted Test',
                'slug' => 'unlisted-test',
                'status' => 'unlisted',
            ],
            '',
            rtrim($contentPath, '/') . '/posts/unlisted-test.md',
            'post'
        );

        $allItems = ['post' => [$item]];
        $contentTypes = [
            'post' => [
                'url' => [
                    'type' => 'pattern',
                    'pattern' => '/posts/{slug}',
                ],
                'templates' => [
                    'single' => 'single.php',
                    'archive' => 'archive.php',
                ],
            ],
        ];

        $method = new \ReflectionMethod($indexer, 'buildRoutes');
        $method->setAccessible(true);

        /** @var array $routes */
        $routes = $method->invoke($indexer, $allItems, $contentTypes, []);

        $this->assertArrayHasKey('exact', $routes);
        $this->assertArrayHasKey('/posts/unlisted-test', $routes['exact']);
        $this->assertEquals('/posts/unlisted-test', $routes['reverse']['post:unlisted-test'] ?? null);
    }

    public function testBuildRoutesExcludesDraftFromExactRoutes(): void
    {
        $indexer = new Indexer($this->app);

        $contentPath = $this->app->configPath('content');
        $item = new Item(
            [
                'title' => 'Draft Test',
                'slug' => 'draft-test',
                'status' => 'draft',
            ],
            '',
            rtrim($contentPath, '/') . '/posts/draft-test.md',
            'post'
        );

        $allItems = ['post' => [$item]];
        $contentTypes = [
            'post' => [
                'url' => [
                    'type' => 'pattern',
                    'pattern' => '/posts/{slug}',
                ],
                'templates' => [
                    'single' => 'single.php',
                    'archive' => 'archive.php',
                ],
            ],
        ];

        $method = new \ReflectionMethod($indexer, 'buildRoutes');
        $method->setAccessible(true);

        /** @var array $routes */
        $routes = $method->invoke($indexer, $allItems, $contentTypes, []);

        $this->assertArrayHasKey('exact', $routes);
        $this->assertFalse(isset($routes['exact']['/posts/draft-test']));
    }

    public function testHierarchicalReverseRoutesUsePathBasedContentKeys(): void
    {
        $indexer = new Indexer($this->app);
        $contentPath = rtrim($this->app->configPath('content'), '/');
        $items = [
            new Item(
                ['title' => 'About Team', 'slug' => 'team', 'status' => 'published'],
                '',
                $contentPath . '/pages/about/team.md',
                'page'
            ),
            new Item(
                ['title' => 'Company Team', 'slug' => 'team', 'status' => 'published'],
                '',
                $contentPath . '/pages/company/team.md',
                'page'
            ),
        ];
        $contentTypes = [
            'page' => [
                'url' => ['type' => 'hierarchical', 'base' => '/'],
                'templates' => ['single' => 'page.php'],
            ],
        ];

        $method = new \ReflectionMethod($indexer, 'buildRoutes');
        $method->setAccessible(true);
        $routes = $method->invoke($indexer, ['page' => $items], $contentTypes, []);

        $this->assertEquals('/about/team', $routes['reverse']['page:about/team'] ?? null);
        $this->assertEquals('/company/team', $routes['reverse']['page:company/team'] ?? null);
        $this->assertEquals('about/team', $routes['exact']['/about/team']['content_key'] ?? null);
        $this->assertEquals('company/team', $routes['exact']['/company/team']['content_key'] ?? null);
        $this->assertFalse(isset($routes['reverse']['page:team']));
    }
}
