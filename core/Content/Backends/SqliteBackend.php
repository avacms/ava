<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

use Ava\Content\QueryProcessor;
use Ava\Content\Terms;

/**
 * SQLite Backend
 *
 * One SQLite database per index generation. Filtering, sorting, paging and
 * route lookups happen in SQL, so memory stays flat as a site grows.
 *
 * Readers open the finished database with query_only: a generation is
 * immutable, and a reader never needs write access to the index directory.
 *
 * Best for: large sites (10,000+ items) and low memory limits.
 */
final class SqliteBackend implements BackendInterface
{
    /** Every column except the body, for reads that do not need it. */
    private const META_COLUMNS = 'type, content_key, slug, id, title, status, date, updated_at, '
        . 'file_path, template, excerpt, taxonomies, meta, frontmatter';

    private ?\PDO $pdo = null;

    /** @var array<string, \PDOStatement> */
    private array $statements = [];

    private ?array $routes = null;

    public function __construct(
        private string $databasePath,
        private string $contentPath,
        private bool $writable = false
    ) {
    }

    public function name(): string
    {
        return 'sqlite';
    }

    public function isAvailable(): bool
    {
        return extension_loaded('pdo_sqlite') && is_file($this->databasePath);
    }

    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            if (!$this->writable && !is_file($this->databasePath)) {
                throw new \RuntimeException(
                    'The SQLite content index is missing (' . $this->databasePath . '). Run: ./ava rebuild'
                );
            }

            $this->pdo = new \PDO('sqlite:' . $this->databasePath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            if ($this->writable) {
                // A build writes into a directory nobody reads until it is
                // published; if it dies, the directory is discarded.
                $this->pdo->exec('PRAGMA journal_mode = MEMORY');
                $this->pdo->exec('PRAGMA synchronous = OFF');
            } else {
                $this->pdo->exec('PRAGMA query_only = 1');
            }
            $this->pdo->exec('PRAGMA cache_size = -16000'); // 16MB
            $this->pdo->exec('PRAGMA mmap_size = 268435456'); // 256MB
        }

        return $this->pdo;
    }

    private function stmt(string $key, string $sql): \PDOStatement
    {
        return $this->statements[$key] ??= $this->pdo()->prepare($sql);
    }

    // -------------------------------------------------------------------------
    // Single Item Retrieval
    // -------------------------------------------------------------------------

    public function getBySlug(string $type, string $slug): ?array
    {
        return $this->fetchItem(
            'get_by_slug',
            'SELECT ' . self::META_COLUMNS . ' FROM content WHERE type = :type AND content_key = :slug LIMIT 1',
            ['type' => $type, 'slug' => $slug]
        );
    }

    public function getById(string $id): ?array
    {
        return $this->fetchItem(
            'get_by_id',
            'SELECT ' . self::META_COLUMNS . ' FROM content WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function getByPath(string $relativePath): ?array
    {
        return $this->fetchItem(
            'get_by_path',
            'SELECT ' . self::META_COLUMNS . ' FROM content WHERE file_path = :path LIMIT 1',
            ['path' => $relativePath]
        );
    }

    // -------------------------------------------------------------------------
    // Bulk Retrieval
    // -------------------------------------------------------------------------

    public function allRaw(string $type, bool $withBody = false): array
    {
        $columns = $withBody ? '*' : self::META_COLUMNS;
        $stmt = $this->stmt('all_raw_' . (int) $withBody, "SELECT {$columns} FROM content WHERE type = :type");
        $stmt->execute(['type' => $type]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[$row['content_key']] = $this->rowToItem($row);
        }

        return $items;
    }

    public function types(): array
    {
        $stmt = $this->stmt('types', 'SELECT DISTINCT type FROM content ORDER BY type');
        $stmt->execute();

        return array_column($stmt->fetchAll(), 'type');
    }

    public function count(string $type, ?string $status = null): int
    {
        if ($status === null) {
            $stmt = $this->stmt('count_type', 'SELECT COUNT(*) AS cnt FROM content WHERE type = :type');
            $stmt->execute(['type' => $type]);
        } else {
            $stmt = $this->stmt(
                'count_type_status',
                'SELECT COUNT(*) AS cnt FROM content WHERE type = :type AND status = :status'
            );
            $stmt->execute(['type' => $type, 'status' => $status]);
        }

        return (int) $stmt->fetch()['cnt'];
    }

    public function exists(string $type, string $slug): bool
    {
        $stmt = $this->stmt('exists', 'SELECT 1 FROM content WHERE type = :type AND content_key = :slug LIMIT 1');
        $stmt->execute(['type' => $type, 'slug' => $slug]);

        return $stmt->fetch() !== false;
    }

    // -------------------------------------------------------------------------
    // Query Operations
    // -------------------------------------------------------------------------

    public function query(array $params): array
    {
        $filter = $this->filterConditions($params);
        if ($filter === null) {
            return ['items' => [], 'total' => 0];
        }
        [$conditions, $bindings] = $filter;

        if (($params['search'] ?? '') !== '') {
            return $this->search($params, $conditions, $bindings);
        }

        $page = $params['page'] ?? 1;
        $perPage = $params['perPage'] ?? 10;
        $order = strtoupper($params['order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = $params['orderBy'] ?? 'date';
        $orderColumn = match ($orderBy) {
            'date' => 'date',
            'updated' => 'updated_at',
            'title' => 'title',
            'order', 'menu_order' => "json_extract(meta, '\$.order')",
            default => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $orderBy)
                ? "json_extract(meta, '\$.{$orderBy}')"
                : 'date',
        };

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->pdo()->prepare("SELECT COUNT(*) AS cnt FROM content {$where}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetch()['cnt'];

        $stmt = $this->pdo()->prepare(
            'SELECT ' . self::META_COLUMNS . " FROM content {$where} "
            . "ORDER BY {$orderColumn} {$order}, title ASC LIMIT :limit OFFSET :offset"
        );
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => array_map(fn(array $row) => $this->rowToItem($row), $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Relevance search: SQL narrows the rows to those containing at least one
     * search term, then the shared PHP scorer ranks them exactly as the array
     * backend does. Only candidates' bodies are loaded into memory.
     */
    private function search(array $params, array $conditions, array $bindings): array
    {
        $search = (string) $params['search'];
        $tokens = QueryProcessor::expandTokens(
            QueryProcessor::tokenize($search),
            $params['stopWords'] ?? [],
            $params['synonyms'] ?? []
        );
        if ($tokens === []) {
            return ['items' => [], 'total' => 0];
        }

        $prefilter = $this->searchPrefilter($tokens, $params['searchWeights'] ?? null, $bindings);
        if ($prefilter !== null) {
            $conditions[] = $prefilter;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->pdo()->prepare("SELECT * FROM content {$where}");
        $stmt->execute($bindings);
        $items = array_map(fn(array $row) => $this->rowToItem($row), $stmt->fetchAll());

        $items = QueryProcessor::applySearch($items, $search, $tokens, $params['searchWeights'] ?? null);
        $perPage = $params['perPage'] ?? 10;
        $offset = (($params['page'] ?? 1) - 1) * $perPage;

        return ['items' => array_slice($items, $offset, $perPage), 'total' => count($items)];
    }

    /**
     * A WHERE clause every item the scorer could match satisfies, or null
     * when one cannot be written safely (then every filtered row is scored).
     *
     * The scorer lowercases ASCII only and SQLite's LIKE ignores ASCII case
     * only, so both treat case identically.
     */
    private function searchPrefilter(array $tokenGroups, ?array $weights, array &$bindings): ?string
    {
        $columns = ['title', 'excerpt', 'body'];
        foreach ($weights['fields'] ?? [] as $field) {
            if (!is_string($field) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $field) !== 1) {
                return null;
            }
            $columns[] = "json_extract(meta, '\$.{$field}')";
        }

        $clauses = [];
        $index = 0;
        foreach ($tokenGroups as $variants) {
            foreach ($variants as $variant) {
                // JSON text escapes these in array fields; stay exact instead.
                if (str_contains($variant, '"') || str_contains($variant, '\\')) {
                    return null;
                }

                $name = 'search_' . $index++;
                $bindings[$name] = '%' . addcslashes($variant, '%_\\') . '%';
                foreach ($columns as $column) {
                    $clauses[] = "{$column} LIKE :{$name} ESCAPE '\\'";
                }
            }
        }

        return $clauses === [] ? null : '(' . implode(' OR ', $clauses) . ')';
    }

    /**
     * WHERE conditions for type, status, taxonomy and field filters.
     *
     * @return array{0: list<string>, 1: array<string, mixed>}|null Null when
     *         nothing can match (an empty list of eligible types).
     */
    private function filterConditions(array $params): ?array
    {
        $conditions = [];
        $bindings = [];

        if (isset($params['types'])) {
            if ($params['types'] === []) {
                return null;
            }
            $placeholders = [];
            foreach (array_values($params['types']) as $index => $queryType) {
                $placeholders[] = ':type_' . $index;
                $bindings['type_' . $index] = $queryType;
            }
            $conditions[] = 'type IN (' . implode(',', $placeholders) . ')';
        } elseif (($params['type'] ?? null) !== null) {
            $conditions[] = 'type = :type';
            $bindings['type'] = $params['type'];
        }

        if (($params['status'] ?? null) !== null) {
            $conditions[] = 'status = :status';
            $bindings['status'] = $params['status'];
        }

        $taxIndex = 0;
        foreach ($params['taxonomies'] ?? [] as $taxonomy => $term) {
            $name = 'tax_' . $taxIndex++;
            $conditions[] = "EXISTS (
                SELECT 1 FROM json_each(json_extract(content.taxonomies, :{$name}_path))
                WHERE value = :{$name}_value
            )";
            $bindings["{$name}_path"] = '$."' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $taxonomy) . '"';
            $bindings["{$name}_value"] = Terms::slug((string) $term);
        }

        $fieldIndex = 0;
        foreach ($params['fields'] ?? [] as $filter) {
            $name = 'field_' . $fieldIndex++;
            $field = $filter['field'];
            $value = $filter['value'];
            $operator = $filter['operator'];

            // Field names become part of a JSON path, so only plain identifiers.
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $field)
                || !in_array($operator, ['=', '!=', '>', '>=', '<', '<=', 'like', 'in', 'not_in'], true)
            ) {
                $conditions[] = '0 = 1';
                continue;
            }

            $expression = in_array($field, ['title', 'slug', 'status', 'date', 'type'], true)
                ? $field
                : "json_extract(meta, '\$.{$field}')";

            if ($operator === 'in' || $operator === 'not_in') {
                if (!is_array($value)) {
                    $conditions[] = '0 = 1';
                    continue;
                }
                if ($value === []) {
                    if ($operator === 'in') {
                        $conditions[] = '0 = 1';
                    }
                    continue;
                }

                $placeholders = [];
                foreach (array_values($value) as $i => $entry) {
                    $placeholders[] = ":{$name}_{$i}";
                    $bindings["{$name}_{$i}"] = $entry;
                }
                $list = implode(',', $placeholders);
                $conditions[] = $operator === 'not_in'
                    ? "({$expression} IS NULL OR {$expression} NOT IN ({$list}))"
                    : "{$expression} IN ({$list})";
                continue;
            }

            if ($operator === '!=') {
                $conditions[] = "({$expression} IS NULL OR {$expression} != :{$name})";
                $bindings[$name] = $value;
                continue;
            }

            $sqlOperator = $operator === 'like' ? 'LIKE' : $operator;
            $conditions[] = "{$expression} {$sqlOperator} :{$name}";
            $bindings[$name] = $operator === 'like' ? '%' . $value . '%' : $value;
        }

        return [$conditions, $bindings];
    }

    // -------------------------------------------------------------------------
    // Recent Cache Operations
    // -------------------------------------------------------------------------

    public function canUseFastCache(string $type, int $page, int $perPage): bool
    {
        // SQLite already filters and paginates in SQL; it has no recent cache.
        return false;
    }

    public function getRecentItems(string $type, int $page, int $perPage): array
    {
        return $this->query([
            'type' => $type,
            'status' => 'published',
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    // -------------------------------------------------------------------------
    // Taxonomy Operations
    // -------------------------------------------------------------------------

    public function terms(string $taxonomy): array
    {
        $stmt = $this->stmt('terms', 'SELECT * FROM taxonomy_terms WHERE taxonomy = :taxonomy');
        $stmt->execute(['taxonomy' => $taxonomy]);

        $terms = [];
        foreach ($stmt->fetchAll() as $row) {
            $terms[$row['slug']] = $this->rowToTerm($row);
        }

        return $terms;
    }

    public function term(string $taxonomy, string $slug): ?array
    {
        $stmt = $this->stmt(
            'term',
            'SELECT * FROM taxonomy_terms WHERE taxonomy = :taxonomy AND slug = :slug LIMIT 1'
        );
        $stmt->execute(['taxonomy' => $taxonomy, 'slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->rowToTerm($row);
    }

    public function taxonomies(): array
    {
        $stmt = $this->stmt('taxonomies', 'SELECT DISTINCT taxonomy FROM taxonomy_terms ORDER BY taxonomy');
        $stmt->execute();

        return array_column($stmt->fetchAll(), 'taxonomy');
    }

    // -------------------------------------------------------------------------
    // Route Operations
    // -------------------------------------------------------------------------

    public function routes(): array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $stmt = $this->stmt('routes', 'SELECT * FROM routes');
        $stmt->execute();

        $routes = [
            'redirects' => [], 'exact' => [], 'preview' => [], 'patterns' => [], 'taxonomy' => [], 'reverse' => [],
        ];
        foreach ($stmt->fetchAll() as $row) {
            $data = json_decode($row['data'], true);
            $path = $row['path'];

            match ($row['type']) {
                'redirect' => $routes['redirects'][$path] = $data,
                'exact' => $routes['exact'][$path] = $data,
                'preview' => $routes['preview'][$path] = $data,
                'taxonomy' => $routes['taxonomy'][$row['name']] = $data,
                'reverse' => $routes['reverse'][$path] = $data['url'] ?? null,
                default => null,
            };
        }

        return $this->routes = $routes;
    }

    public function exactRoute(string $path): ?array
    {
        return $this->route($path, 'exact');
    }

    public function previewRoute(string $path): ?array
    {
        return $this->route($path, 'preview');
    }

    public function redirectRoute(string $path): ?array
    {
        return $this->route($path, 'redirect');
    }

    public function reverseUrl(string $type, string $contentKey): ?string
    {
        $url = $this->route($type . ':' . $contentKey, 'reverse')['url'] ?? null;

        return is_string($url) ? $url : null;
    }

    public function taxonomyRoutes(): array
    {
        $stmt = $this->stmt('taxonomy_routes', "SELECT name, data FROM routes WHERE type = 'taxonomy'");
        $stmt->execute();

        $routes = [];
        foreach ($stmt->fetchAll() as $row) {
            $routes[$row['name']] = json_decode($row['data'], true);
        }

        return $routes;
    }

    private function route(string $path, string $type): ?array
    {
        $stmt = $this->stmt('route', 'SELECT data FROM routes WHERE path = :path AND type = :type LIMIT 1');
        $stmt->execute(['path' => $path, 'type' => $type]);
        $data = $stmt->fetchColumn();

        $decoded = is_string($data) ? json_decode($data, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    public function clearMemoryCache(): void
    {
        $this->statements = [];
        $this->routes = null;
        $this->pdo = null;
    }

    // -------------------------------------------------------------------------
    // Building (used by the Indexer)
    // -------------------------------------------------------------------------

    public function initializeSchema(): void
    {
        $pdo = $this->pdo();

        // content_key is a slug for pattern types and a path for hierarchical
        // types, matching the array backend's lookup semantics.
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS content (
                type TEXT NOT NULL,
                content_key TEXT NOT NULL,
                slug TEXT NOT NULL,
                id TEXT,
                title TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                date TEXT,
                updated_at TEXT,
                file_path TEXT NOT NULL,
                template TEXT,
                excerpt TEXT,
                body TEXT,
                taxonomies TEXT DEFAULT "{}",
                meta TEXT DEFAULT "{}",
                frontmatter TEXT DEFAULT "{}",
                PRIMARY KEY(type, content_key)
            )
        ');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_status ON content(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_date ON content(date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_type_status_date ON content(type, status, date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_id ON content(id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_file_path ON content(file_path)');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS taxonomy_terms (
                taxonomy TEXT NOT NULL,
                slug TEXT NOT NULL,
                name TEXT NOT NULL,
                count INTEGER DEFAULT 0,
                items TEXT DEFAULT "[]",
                meta TEXT DEFAULT "{}",
                PRIMARY KEY(taxonomy, slug)
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS routes (
                path TEXT NOT NULL,
                type TEXT NOT NULL,
                name TEXT,
                data TEXT DEFAULT "{}",
                PRIMARY KEY(path, type)
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_routes_type ON routes(type)');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS metadata (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ');
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function truncate(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM content');
        $pdo->exec('DELETE FROM taxonomy_terms');
        $pdo->exec('DELETE FROM routes');
        $pdo->exec('DELETE FROM metadata');
    }

    public function insertContent(array $item): void
    {
        $stmt = $this->stmt('insert_content', '
            INSERT OR REPLACE INTO content
            (type, content_key, slug, id, title, status, date, updated_at, file_path, template, excerpt, body, taxonomies, meta, frontmatter)
            VALUES (:type, :content_key, :slug, :id, :title, :status, :date, :updated_at, :file_path, :template, :excerpt, :body, :taxonomies, :meta, :frontmatter)
        ');

        $stmt->execute([
            'type' => $item['type'] ?? '',
            'content_key' => $item['content_key'] ?? $item['slug'] ?? '',
            'slug' => $item['slug'] ?? '',
            'id' => ($item['id'] ?? null) ?: null,
            'title' => $item['title'] ?? '',
            'status' => $item['status'] ?? 'draft',
            'date' => $item['date'] ?? null,
            'updated_at' => $item['updated'] ?? $item['updated_at'] ?? null,
            'file_path' => $item['relative_path'] ?? $item['file_path'] ?? '',
            'template' => $item['template'] ?? null,
            'excerpt' => $item['excerpt'] ?? $item['meta']['excerpt'] ?? null,
            'body' => $item['body'] ?? '',
            'taxonomies' => self::json($item['taxonomies'] ?? []),
            'meta' => self::json($item['meta'] ?? $item['frontmatter'] ?? []),
            'frontmatter' => self::json($item['frontmatter'] ?? []),
        ]);
    }

    public function insertTerm(string $taxonomy, array $term): void
    {
        $stmt = $this->stmt('insert_term', '
            INSERT OR REPLACE INTO taxonomy_terms (taxonomy, slug, name, count, items, meta)
            VALUES (:taxonomy, :slug, :name, :count, :items, :meta)
        ');

        $meta = $term;
        unset($meta['slug'], $meta['name'], $meta['count'], $meta['items']);

        $stmt->execute([
            'taxonomy' => $taxonomy,
            'slug' => (string) ($term['slug'] ?? ''),
            'name' => (string) ($term['name'] ?? ''),
            'count' => $term['count'] ?? 0,
            'items' => self::json($term['items'] ?? []),
            'meta' => self::json($meta),
        ]);
    }

    public function insertRoute(string $path, string $type, array $data, ?string $name = null): void
    {
        $stmt = $this->stmt('insert_route', '
            INSERT OR REPLACE INTO routes (path, type, name, data)
            VALUES (:path, :type, :name, :data)
        ');

        $stmt->execute([
            'path' => $path,
            'type' => $type,
            'name' => $name,
            'data' => self::json($data),
        ]);
    }

    public function setMetadata(string $key, mixed $value): void
    {
        $this->stmt('set_metadata', 'INSERT OR REPLACE INTO metadata (key, value) VALUES (:key, :value)')
            ->execute(['key' => $key, 'value' => self::json($value)]);
    }

    public function getMetadata(string $key): mixed
    {
        $stmt = $this->stmt('get_metadata', 'SELECT value FROM metadata WHERE key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        return $row === false ? null : json_decode($row['value'], true);
    }

    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }

    /**
     * Create a fresh database file.
     */
    public function createDatabase(): void
    {
        $dir = dirname($this->databasePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create directory for SQLite index: ' . $dir);
        }

        // Close any prior connection before replacing its files.
        $this->clearMemoryCache();
        foreach ([$this->databasePath, $this->databasePath . '-wal', $this->databasePath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->initializeSchema();
    }

    /**
     * Flush a completed build into one self-contained database file.
     */
    public function prepareForPublication(): void
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            throw new \LogicException('Cannot publish a SQLite database with an active transaction');
        }

        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $mode = strtolower((string) $pdo->query('PRAGMA journal_mode = DELETE')->fetchColumn());
        if ($mode !== 'delete') {
            throw new \RuntimeException('Failed to finalize SQLite journal before publication');
        }
        $pdo->exec('ANALYZE');

        $this->clearMemoryCache();
        foreach ([$this->databasePath . '-wal', $this->databasePath . '-shm'] as $sidecar) {
            if (is_file($sidecar) && !@unlink($sidecar)) {
                throw new \RuntimeException("Failed to remove temporary SQLite sidecar: {$sidecar}");
            }
        }
        @chmod($this->databasePath, 0644);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private function fetchItem(string $key, string $sql, array $bindings): ?array
    {
        $stmt = $this->stmt($key, $sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch();

        return $row === false ? null : $this->rowToItem($row);
    }

    private function rowToItem(array $row): array
    {
        $item = [
            'id' => $row['id'],
            'type' => $row['type'],
            'content_key' => $row['content_key'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'status' => $row['status'],
            'date' => $row['date'],
            'updated' => $row['updated_at'],
            'file_path' => $this->contentPath . '/' . $row['file_path'],
            'relative_path' => $row['file_path'],
            'template' => $row['template'],
            'excerpt' => $row['excerpt'],
            'taxonomies' => json_decode($row['taxonomies'] ?? '{}', true),
            'meta' => json_decode($row['meta'] ?? '{}', true),
            'frontmatter' => json_decode($row['frontmatter'] ?? '{}', true),
        ];

        if (array_key_exists('body', $row)) {
            $item['body'] = $row['body'];
        }

        return $item;
    }

    private function rowToTerm(array $row): array
    {
        $meta = json_decode($row['meta'] ?? '{}', true);

        return array_merge(is_array($meta) ? $meta : [], [
            'slug' => $row['slug'],
            'name' => $row['name'],
            'count' => (int) $row['count'],
            'items' => json_decode($row['items'] ?? '[]', true),
        ]);
    }

    /**
     * Unescaped Unicode, so SQL LIKE sees the same characters PHP does.
     */
    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }
}
