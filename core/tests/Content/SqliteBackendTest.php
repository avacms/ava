<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Application;
use Ava\Content\Backends\SqliteBackend;
use Ava\Testing\TestCase;

final class SqliteBackendTest extends TestCase
{
    public function testSwitchingToSqliteBuildsAndServesASqliteIndex(): void
    {
        // Regression: v26.9.0 could not build a first SQLite index at all,
        // because clearing the repository tried to open the database that was
        // still being built ("index is unavailable. Run: php ava rebuild").
        if (!extension_loaded('pdo_sqlite')) {
            $this->markSkipped('pdo_sqlite extension is not available');
        }

        $storage = 'storage/tmp/sqlite-switch-' . bin2hex(random_bytes(6));
        $config = $this->app->allConfig();
        $config['paths']['storage'] = $storage;
        $config['content_index']['backend'] = 'sqlite';
        $config['content_index']['mode'] = 'never';

        try {
            $app = new Application($config);
            $app->indexer()->rebuild();

            $this->assertEquals('sqlite', $app->repository()->backendName());
            $this->assertEquals('sqlite', $app->indexStore()->backend());
            $this->assertNotNull($app->router()->urlFor('post', 'hello-world'));
            $this->assertTrue(is_file($app->indexStore()->currentPath() . '/content_index.sqlite'));
        } finally {
            $this->removeDirectory(AVA_ROOT . '/' . $storage);
        }
    }

    public function testTheLiveGenerationsBackendWinsOverConfiguration(): void
    {
        // Changing content_index.backend takes effect at the next rebuild; until
        // then readers must use the index that actually exists.
        $storage = 'storage/tmp/sqlite-config-' . bin2hex(random_bytes(6));
        $config = $this->app->allConfig();
        $config['paths']['storage'] = $storage;
        $config['content_index']['backend'] = 'array';

        try {
            $builder = new Application($config);
            $builder->indexer()->rebuild();

            $config['content_index']['backend'] = 'sqlite';
            $reader = new Application($config);
            $this->assertEquals('array', $reader->repository()->backendName());
        } finally {
            $this->removeDirectory(AVA_ROOT . '/' . $storage);
        }
    }

    public function testHierarchicalItemsWithTheSameSlugRemainDistinct(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markSkipped('pdo_sqlite extension is not available');
        }

        $directory = $this->app->configPath('storage') . '/tmp/test-sqlite-backend-'
            . bin2hex(random_bytes(6));
        $content = $directory . '/content';
        mkdir($content, 0700, true);
        $backend = new SqliteBackend($directory . '/index.sqlite', $content, writable: true);

        try {
            $backend->createDatabase();
            $backend->beginTransaction();
            $backend->insertContent($this->item('company/team', 'Company team'));
            $backend->insertContent($this->item('support/team', 'Support team'));
            $backend->commit();

            $this->assertEquals(2, $backend->count('page'));
            $this->assertEquals('Company team', $backend->getBySlug('page', 'company/team')['title'] ?? null);
            $this->assertEquals('Support team', $backend->getBySlug('page', 'support/team')['title'] ?? null);
            $this->assertTrue($backend->exists('page', 'company/team'));
            $this->assertTrue($backend->exists('page', 'support/team'));
        } finally {
            $backend->clearMemoryCache();
            $this->removeDirectory($directory);
        }
    }

    public function testRoutesIncludeReverseUrlLookups(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markSkipped('pdo_sqlite extension is not available');
        }

        $directory = $this->app->configPath('storage') . '/tmp/test-sqlite-routes-'
            . bin2hex(random_bytes(6));
        $content = $directory . '/content';
        mkdir($content, 0700, true);
        $backend = new SqliteBackend($directory . '/index.sqlite', $content, writable: true);

        try {
            $backend->createDatabase();
            $backend->insertRoute(
                'post:release-notes',
                'reverse',
                ['url' => '/2026/09/release-notes']
            );

            $routes = $backend->routes();
            $this->assertEquals(
                '/2026/09/release-notes',
                $routes['reverse']['post:release-notes'] ?? null
            );
        } finally {
            $backend->clearMemoryCache();
            $this->removeDirectory($directory);
        }
    }

    public function testQueriesFilterIndexedTaxonomiesAndCustomFields(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markSkipped('pdo_sqlite extension is not available');
        }

        $directory = $this->app->configPath('storage') . '/tmp/test-sqlite-filters-'
            . bin2hex(random_bytes(6));
        $content = $directory . '/content';
        mkdir($content, 0700, true);
        $backend = new SqliteBackend($directory . '/index.sqlite', $content, writable: true);

        try {
            $backend->createDatabase();
            $item = $this->item('case-study', 'Case study');
            $item['taxonomies'] = ['category' => ['design', 'accessibility']];
            $item['frontmatter'] = ['featured' => true, 'client' => 'Acme', 'score' => 10];
            $backend->insertContent($item);
            $other = $this->item('other', 'Other page');
            $other['taxonomies'] = ['category' => ['design-systems']];
            $other['frontmatter'] = ['client' => 'Other', 'score' => 5];
            $backend->insertContent($other);

            $result = $backend->query([
                'type' => 'page',
                'taxonomies' => ['category' => 'design'],
                'fields' => [[
                    'field' => 'client',
                    'value' => 'Acme',
                    'operator' => '=',
                ]],
                'page' => 1,
                'perPage' => 10,
            ]);

            $this->assertEquals(1, $result['total']);
            $this->assertEquals('Case study', $result['items'][0]['title'] ?? null);
            $this->assertEquals(['design', 'accessibility'], $result['items'][0]['taxonomies']['category'] ?? null);
            $this->assertEquals('Acme', $result['items'][0]['meta']['client'] ?? null);

            $notIn = $backend->query([
                'type' => 'page',
                'fields' => [[
                    'field' => 'client',
                    'value' => ['Other'],
                    'operator' => 'not_in',
                ]],
                'orderBy' => 'score',
                'order' => 'desc',
                'page' => 1,
                'perPage' => 1,
            ]);
            $this->assertEquals(1, $notIn['total']);
            $this->assertEquals('Case study', $notIn['items'][0]['title'] ?? null);
        } finally {
            $backend->clearMemoryCache();
            $this->removeDirectory($directory);
        }
    }

    public function testCompletedDatabaseCanBePublishedWithoutWalFiles(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markSkipped('pdo_sqlite extension is not available');
        }

        $directory = $this->app->configPath('storage') . '/tmp/test-sqlite-publish-'
            . bin2hex(random_bytes(6));
        $content = $directory . '/content';
        $database = $directory . '/new-index.sqlite';
        mkdir($content, 0700, true);
        $backend = new SqliteBackend($database, $content, writable: true);

        try {
            $backend->createDatabase();
            $backend->beginTransaction();
            $backend->insertContent($this->item('company/team', 'Company team'));
            $backend->commit();
            $backend->prepareForPublication();

            $this->assertTrue(is_file($database));
            $this->assertFalse(is_file($database . '-wal'));
            $this->assertFalse(is_file($database . '-shm'));
        } finally {
            $backend->clearMemoryCache();
            $this->removeDirectory($directory);
        }
    }

    private function item(string $contentKey, string $title): array
    {
        return [
            'type' => 'page',
            'content_key' => $contentKey,
            'slug' => 'team',
            'title' => $title,
            'status' => 'published',
            'file_path' => 'pages/' . $contentKey . '.md',
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
