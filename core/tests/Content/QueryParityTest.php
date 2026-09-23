<?php

declare(strict_types=1);

namespace Ava\Tests\Content;

use Ava\Application;
use Ava\Content\Backends\ArrayBackend;
use Ava\Content\Backends\SqliteBackend;
use Ava\Content\Index\IndexStore;
use Ava\Content\Query;
use Ava\Testing\TestCase;

final class QueryParityTest extends TestCase
{
    private string $relative;
    private string $directory;
    private ArrayBackend $array;
    private ?SqliteBackend $sqlite = null;

    /** @var array<string, string> backend => generation id */
    private array $generations = [];
    private IndexStore $store;

    public function setUp(): void
    {
        $this->relative = 'storage/tmp/query-parity-' . bin2hex(random_bytes(6));
        $this->directory = AVA_ROOT . '/' . $this->relative;
        mkdir($this->directory, 0700, true);
        $items = [
            $this->item('post', 'shared', 'Bicycle guide', 'published', '2026-09-03', 'Acme'),
            $this->item('post', 'draft', 'Draft guide', 'draft', '2026-09-04', 'Other'),
            $this->item('post', 'other', 'Other guide', 'published', '2026-09-02', 'Other'),
            $this->item('page', 'shared', 'Page guide', 'published', '2026-09-01', 'Acme'),
        ];
        $items[2]['body'] = 'A unique bodyneedle appears here.';

        // One real generation per backend, built the way the indexer lays
        // them out, so Applications pointed at this storage can read them.
        $this->store = new IndexStore($this->directory);
        [$this->generations['array'], $arrayPath] = $this->store->createGeneration();
        $index = ['by_type' => []];
        $bodies = [];
        foreach ($items as $item) {
            $bodies[$item['type'] . ':' . $item['slug']] = $item['body'] ?? '';
            unset($item['body']);
            $index['by_type'][$item['type']][$item['slug']] = $item;
        }
        $this->store->writeBinary($arrayPath, 'content_index.bin', $index, false);
        $this->store->writeBinary($arrayPath, 'bodies.bin', $bodies, false);
        $this->store->writeBinary($arrayPath, 'recent_cache.bin', [
            'post' => ['items' => [$index['by_type']['post']['shared'], $index['by_type']['post']['other']], 'total' => 2],
        ], false);
        $this->store->writeBinary($arrayPath, 'synonyms.bin', ['bike' => ['bicycle']], false);
        $this->store->writeBinary($arrayPath, 'stopwords.bin', ['the' => true], false);
        $this->array = new ArrayBackend($arrayPath, $this->directory, $this->store->keyDirectory());

        if (extension_loaded('pdo_sqlite')) {
            [$this->generations['sqlite'], $sqlitePath] = $this->store->createGeneration();
            $this->store->writeBinary($sqlitePath, 'synonyms.bin', ['bike' => ['bicycle']], false);
            $this->store->writeBinary($sqlitePath, 'stopwords.bin', ['the' => true], false);
            $this->sqlite = new SqliteBackend($sqlitePath . '/content_index.sqlite', $this->directory, writable: true);
            $this->sqlite->createDatabase();
            foreach ($items as $item) {
                $this->sqlite->insertContent($item);
            }
        }
    }

    public function tearDown(): void
    {
        $this->sqlite?->clearMemoryCache();
        $this->sqlite = null;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testQueriesPreserveFiltersPaginationAndSearchAcrossBackends(): void
    {
        if ($this->sqlite === null) {
            $this->markSkipped('pdo_sqlite is unavailable');
        }
        $cases = [
            [['type' => 'post', 'status' => 'published', 'perPage' => 1, 'page' => 2], 2, ['other']],
            [['types' => ['post'], 'taxonomies' => ['category' => 'guides'], 'fields' => [
                ['field' => 'client', 'operator' => '=', 'value' => 'Acme'],
            ]], 1, ['shared']],
            [['types' => []], 0, []],
            [['search' => 'bike', 'synonyms' => ['bike' => ['bicycle']]], 1, ['shared']],
            [['search' => 'the', 'stopWords' => ['the' => true]], 0, []],
            [['search' => 'bodyneedle'], 1, ['other']],
            [['search' => 'Acme', 'searchWeights' => ['fields' => ['client']]], 2, ['shared', 'shared']],
        ];
        foreach ($cases as [$params, $total, $slugs]) {
            foreach ([$this->array, $this->sqlite] as $backend) {
                $result = $backend->query($params);
                $this->assertEquals($total, $result['total'], $backend->name());
                $this->assertEquals($slugs, array_column($result['items'], 'slug'), $backend->name());
            }
        }
    }

    public function testCrossTypeQueriesDoNotOverwriteMatchingContentKeys(): void
    {
        $result = $this->array->query(['status' => 'published']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(['post', 'post', 'page'], array_column($result['items'], 'type'));
    }

    public function testFluentQueriesDefaultToPublishedAndShareSearchOptions(): void
    {
        foreach (['array', 'sqlite'] as $backend) {
            if ($backend === 'sqlite' && $this->sqlite === null) {
                continue;
            }
            $this->store->publish($this->generations[$backend], $backend, []);
            $config = $this->app->allConfig();
            $config['paths']['storage'] = $this->relative;
            $app = new Application($config);
            $query = new Query($app);
            // SECURITY: the fixture holds three posts, one of them a draft. A
            // query that never mentions status must not return it, because
            // that is the query a theme author writes by accident.
            $this->assertEquals(2, $query->type('post')->perPage(1)->count());
            // A recent-cache hit must not drop drafts when status is unfiltered.
            $this->assertEquals(3, $query->type('post')->anyStatus()->perPage(1)->count());
            $this->assertEquals(2, $query->type('post')->published()->perPage(1)->count());
            $this->assertEquals(1, $query->type('post')->search('bike')->count());
            $this->assertEquals(0, $query->search('the')->count());
            // Page has matching raw frontmatter but does not declare category.
            $this->assertEquals(0, $query->type('page')->whereTax('category', 'guides')->count());
            // Exercise public parameters against known content, rather than
            // merely checking that each parameter returns an array.
            $filtered = $query->published()->fromParams([
                'type' => 'post', 'tax_category' => 'guides',
                'orderby' => 'title', 'order' => 'asc', 'per_page' => 1, 'paged' => 2,
            ]);
            $this->assertSame('Other guide', $filtered->first()?->title(), $backend);
            $this->assertSame([
                'current_page' => 2, 'per_page' => 1, 'total' => 2,
                'total_pages' => 2, 'has_more' => false, 'has_previous' => true,
            ], $filtered->pagination(), $backend);
            foreach (['q', 'search'] as $parameter) {
                $searched = $query->fromParams([$parameter => 'bike']);
                $this->assertSame(1, $searched->count(), "$backend $parameter");
                $this->assertSame('Bicycle guide', $searched->first()?->title(), "$backend $parameter");
            }
            $app->repository()->clearCache();
        }
    }

    private function item(string $type, string $slug, string $title, string $status, string $date, string $client): array
    {
        $meta = compact('slug', 'title', 'status', 'date', 'client');
        $meta['category'] = ['guides'];
        return [
            'type' => $type,
            'content_key' => $slug,
            'slug' => $slug,
            'title' => $title,
            'status' => $status,
            'date' => $date,
            'file_path' => $type . '/' . $slug . '.md',
            'taxonomies' => ['category' => ['guides']],
            'frontmatter' => $meta,
        ];
    }
}
