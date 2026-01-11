<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

use Ava\Application;

/**
 * SQLite Backend
 *
 * SQLite-based content index backend using a single database file.
 * Designed for large sites (10,000+ posts) where the array backend
 * would consume too much memory.
 *
 * Features:
 * - Constant memory usage regardless of content size
 * - Fast indexed queries on type, slug, status, date
 * - FTS5 full-text search (future enhancement)
 * - Single file storage: storage/cache/content_index.sqlite
 *
 * Best for: Large sites (10,000+ posts)
 * Memory: ~5MB constant (database connection overhead)
 */
final class SqliteBackend implements BackendInterface
{
    private Application $app;
    private ?\PDO $pdo = null;
    private ?string $dbPath = null;

    // Prepared statements cache
    private array $statements = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->dbPath = $this->app->configPath('storage') . '/cache/content_index.sqlite';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'sqlite';
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        // Check if PDO SQLite extension is available
        if (!extension_loaded('pdo_sqlite')) {
            return false;
        }

        // Check if database file exists
        return file_exists($this->dbPath);
    }

    /**
     * Get the PDO connection, creating it if needed.
     */
    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new \PDO(
                'sqlite:' . $this->dbPath,
                null,
                null,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Performance optimizations
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
            $this->pdo->exec('PRAGMA cache_size = -16000'); // 16MB cache
            $this->pdo->exec('PRAGMA mmap_size = 268435456'); // 256MB mmap
        }

        return $this->pdo;
    }

    /**
     * Get or create a prepared statement.
     */
    private function stmt(string $key, string $sql): \PDOStatement
    {
        if (!isset($this->statements[$key])) {
            $this->statements[$key] = $this->pdo()->prepare($sql);
        }
        return $this->statements[$key];
    }

    // -------------------------------------------------------------------------
    // Single Item Retrieval
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function getBySlug(string $type, string $slug): ?array
    {
        $stmt = $this->stmt('get_by_slug', '
            SELECT * FROM content WHERE type = :type AND slug = :slug LIMIT 1
        ');
        $stmt->execute(['type' => $type, 'slug' => $slug]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->rowToItem($row);
    }

    /**
     * {@inheritdoc}
     */
    public function getById(string $id): ?array
    {
        $stmt = $this->stmt('get_by_id', '
            SELECT * FROM content WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->rowToItem($row);
    }

    /**
     * {@inheritdoc}
     */
    public function getByPath(string $relativePath): ?array
    {
        $stmt = $this->stmt('get_by_path', '
            SELECT * FROM content WHERE file_path = :path LIMIT 1
        ');
        $stmt->execute(['path' => $relativePath]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->rowToItem($row);
    }

    // -------------------------------------------------------------------------
    // Bulk Retrieval
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function allRaw(string $type): array
    {
        $stmt = $this->stmt('all_raw', '
            SELECT * FROM content WHERE type = :type
        ');
        $stmt->execute(['type' => $type]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->rowToItem($row), $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function types(): array
    {
        $stmt = $this->stmt('types', '
            SELECT DISTINCT type FROM content ORDER BY type
        ');
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'type');
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $type, ?string $status = null): int
    {
        if ($status === null) {
            $stmt = $this->stmt('count_type', '
                SELECT COUNT(*) as cnt FROM content WHERE type = :type
            ');
            $stmt->execute(['type' => $type]);
        } else {
            $stmt = $this->stmt('count_type_status', '
                SELECT COUNT(*) as cnt FROM content WHERE type = :type AND status = :status
            ');
            $stmt->execute(['type' => $type, 'status' => $status]);
        }

        return (int) $stmt->fetch()['cnt'];
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $type, string $slug): bool
    {
        $stmt = $this->stmt('exists', '
            SELECT 1 FROM content WHERE type = :type AND slug = :slug LIMIT 1
        ');
        $stmt->execute(['type' => $type, 'slug' => $slug]);
        return $stmt->fetch() !== false;
    }

    // -------------------------------------------------------------------------
    // Query Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function query(array $params): array
    {
        $type = $params['type'] ?? null;
        $status = $params['status'] ?? null;
        $taxonomies = $params['taxonomies'] ?? [];
        $fields = $params['fields'] ?? [];
        $search = $params['search'] ?? null;
        $orderBy = $params['orderBy'] ?? 'date';
        $order = strtoupper($params['order'] ?? 'desc');
        $page = $params['page'] ?? 1;
        $perPage = $params['perPage'] ?? 10;

        // Build the query
        $conditions = [];
        $bindings = [];

        if ($type !== null) {
            $conditions[] = 'type = :type';
            $bindings['type'] = $type;
        }

        if ($status !== null) {
            $conditions[] = 'status = :status';
            $bindings['status'] = $status;
        }

        // Taxonomy filters use JSON search
        $taxIndex = 0;
        foreach ($taxonomies as $taxonomy => $term) {
            $paramName = 'tax_' . $taxIndex++;
            // Search for term in JSON array using JSON functions
            $conditions[] = "json_extract(taxonomies, :tax_path_{$paramName}) LIKE :tax_val_{$paramName}";
            $bindings["tax_path_{$paramName}"] = '$.' . $taxonomy;
            $bindings["tax_val_{$paramName}"] = '%' . json_encode($term) . '%';
        }

        // Field filters
        $fieldIndex = 0;
        foreach ($fields as $filter) {
            $paramName = 'field_' . $fieldIndex++;
            $field = $filter['field'];
            $value = $filter['value'];
            $operator = $filter['operator'];

            // Try to match against both direct column and meta JSON
            $sqlOp = match ($operator) {
                '=' => '=',
                '!=' => '!=',
                '>' => '>',
                '>=' => '>=',
                '<' => '<',
                '<=' => '<=',
                'like' => 'LIKE',
                default => '=',
            };

            if (in_array($field, ['title', 'slug', 'status', 'date', 'type'], true)) {
                // Direct column
                if ($operator === 'like') {
                    $conditions[] = "{$field} LIKE :{$paramName}";
                    $bindings[$paramName] = '%' . $value . '%';
                } else {
                    $conditions[] = "{$field} {$sqlOp} :{$paramName}";
                    $bindings[$paramName] = $value;
                }
            } else {
                // JSON meta field
                if ($operator === 'like') {
                    $conditions[] = "json_extract(meta, '\$.{$field}') LIKE :{$paramName}";
                    $bindings[$paramName] = '%' . $value . '%';
                } elseif ($operator === 'in' && is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $i => $v) {
                        $placeholders[] = ":{$paramName}_{$i}";
                        $bindings["{$paramName}_{$i}"] = $v;
                    }
                    $conditions[] = "json_extract(meta, '\$.{$field}') IN (" . implode(',', $placeholders) . ")";
                } else {
                    $conditions[] = "json_extract(meta, '\$.{$field}') {$sqlOp} :{$paramName}";
                    $bindings[$paramName] = $value;
                }
            }
        }

        // Build WHERE clause
        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Handle search (simple LIKE for now, FTS5 can be added later)
        if ($search !== null && $search !== '') {
            $searchCondition = '(title LIKE :search OR json_extract(meta, \'$.excerpt\') LIKE :search)';
            if ($where === '') {
                $where = 'WHERE ' . $searchCondition;
            } else {
                $where .= ' AND ' . $searchCondition;
            }
            $bindings['search'] = '%' . $search . '%';
        }

        // Map orderBy to column
        $orderColumn = match ($orderBy) {
            'date' => 'date',
            'updated' => 'updated_at',
            'title' => 'title',
            'order', 'menu_order' => "json_extract(meta, '\$.order')",
            default => 'date',
        };

        // Validate order direction
        $order = $order === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        $countSql = "SELECT COUNT(*) as cnt FROM content {$where}";
        $countStmt = $this->pdo()->prepare($countSql);
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetch()['cnt'];

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM content {$where} ORDER BY {$orderColumn} {$order}, title ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo()->prepare($sql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = array_map(fn($row) => $this->rowToItem($row), $rows);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Recent Cache Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function canUseFastCache(string $type, int $page, int $perPage): bool
    {
        // SQLite is always fast, no need for a separate cache
        // But we limit to first 200 items like the array backend
        $offset = ($page - 1) * $perPage;
        return $offset + $perPage <= 200;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecentItems(string $type, int $page, int $perPage): array
    {
        // Fast path: direct query with index
        $offset = ($page - 1) * $perPage;

        $stmt = $this->stmt('recent_items', '
            SELECT * FROM content 
            WHERE type = :type AND status = :status
            ORDER BY date DESC, title ASC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':status', 'published');
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = array_map(fn($row) => $this->rowToItem($row), $rows);

        // Get total count
        $countStmt = $this->stmt('recent_count', '
            SELECT COUNT(*) as cnt FROM content WHERE type = :type AND status = :status
        ');
        $countStmt->execute(['type' => $type, 'status' => 'published']);
        $total = (int) $countStmt->fetch()['cnt'];

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Taxonomy Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function terms(string $taxonomy): array
    {
        $stmt = $this->stmt('terms', '
            SELECT * FROM taxonomy_terms WHERE taxonomy = :taxonomy
        ');
        $stmt->execute(['taxonomy' => $taxonomy]);
        $rows = $stmt->fetchAll();

        $terms = [];
        foreach ($rows as $row) {
            $terms[$row['slug']] = [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'count' => (int) $row['count'],
                'items' => json_decode($row['items'] ?? '[]', true),
                'meta' => json_decode($row['meta'] ?? '{}', true),
            ];
        }

        return $terms;
    }

    /**
     * {@inheritdoc}
     */
    public function term(string $taxonomy, string $slug): ?array
    {
        $stmt = $this->stmt('term', '
            SELECT * FROM taxonomy_terms WHERE taxonomy = :taxonomy AND slug = :slug LIMIT 1
        ');
        $stmt->execute(['taxonomy' => $taxonomy, 'slug' => $slug]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'slug' => $row['slug'],
            'name' => $row['name'],
            'count' => (int) $row['count'],
            'items' => json_decode($row['items'] ?? '[]', true),
            'meta' => json_decode($row['meta'] ?? '{}', true),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function taxonomies(): array
    {
        $stmt = $this->stmt('taxonomies', '
            SELECT DISTINCT taxonomy FROM taxonomy_terms ORDER BY taxonomy
        ');
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'taxonomy');
    }

    // -------------------------------------------------------------------------
    // Route Operations
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function routes(): array
    {
        $stmt = $this->stmt('routes', '
            SELECT * FROM routes
        ');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $routes = [
            'redirects' => [],
            'exact' => [],
            'taxonomy' => [],
        ];

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);
            $path = $row['path'];

            switch ($row['type']) {
                case 'redirect':
                    $routes['redirects'][$path] = $data;
                    break;
                case 'exact':
                    $routes['exact'][$path] = $data;
                    break;
                case 'taxonomy':
                    $routes['taxonomy'][$row['name']] = $data;
                    break;
            }
        }

        return $routes;
    }

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function clearMemoryCache(): void
    {
        $this->statements = [];
        $this->pdo = null;
    }

    // -------------------------------------------------------------------------
    // Schema Management (for Indexer)
    // -------------------------------------------------------------------------

    /**
     * Initialize the database schema.
     * Called by Indexer when rebuilding.
     */
    public function initializeSchema(): void
    {
        $pdo = $this->pdo();

        // Content table - main index
        // Primary key is (type, slug) since id is optional
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS content (
                type TEXT NOT NULL,
                slug TEXT NOT NULL,
                id TEXT,
                title TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "published",
                date TEXT,
                updated_at TEXT,
                file_path TEXT NOT NULL,
                template TEXT,
                excerpt TEXT,
                taxonomies TEXT DEFAULT "{}",
                meta TEXT DEFAULT "{}",
                frontmatter TEXT DEFAULT "{}",
                PRIMARY KEY(type, slug)
            )
        ');

        // Indexes for common queries
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_type ON content(type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_status ON content(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_date ON content(date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_type_status ON content(type, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_id ON content(id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_content_file_path ON content(file_path)');

        // Taxonomy terms table
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

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_taxonomy ON taxonomy_terms(taxonomy)');

        // Routes table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS routes (
                path TEXT NOT NULL,
                type TEXT NOT NULL,
                name TEXT,
                data TEXT DEFAULT "{}",
                PRIMARY KEY(path, type)
            )
        ');

        // Metadata table for cache info
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS metadata (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ');
    }

    /**
     * Begin a transaction for batch inserts.
     */
    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public function commit(): void
    {
        $this->pdo()->commit();
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    /**
     * Clear all data from the database.
     */
    public function truncate(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM content');
        $pdo->exec('DELETE FROM taxonomy_terms');
        $pdo->exec('DELETE FROM routes');
        $pdo->exec('DELETE FROM metadata');
    }

    /**
     * Insert a content item.
     */
    public function insertContent(array $item): void
    {
        $stmt = $this->stmt('insert_content', '
            INSERT OR REPLACE INTO content 
            (type, slug, id, title, status, date, updated_at, file_path, template, excerpt, taxonomies, meta, frontmatter)
            VALUES (:type, :slug, :id, :title, :status, :date, :updated_at, :file_path, :template, :excerpt, :taxonomies, :meta, :frontmatter)
        ');

        $id = $item['id'] ?? null;

        $stmt->execute([
            'type' => $item['type'] ?? '',
            'slug' => $item['slug'] ?? '',
            'id' => $id ?: null,  // Store NULL if empty, not empty string
            'title' => $item['title'] ?? '',
            'status' => $item['status'] ?? 'published',
            'date' => $item['date'] ?? null,
            'updated_at' => $item['updated'] ?? $item['updated_at'] ?? null,
            'file_path' => $item['file_path'] ?? $item['relative_path'] ?? '',
            'template' => $item['template'] ?? null,
            'excerpt' => $item['excerpt'] ?? $item['meta']['excerpt'] ?? null,
            'taxonomies' => json_encode($item['taxonomies'] ?? []),
            'meta' => json_encode($item['meta'] ?? []),
            'frontmatter' => json_encode($item['frontmatter'] ?? []),
        ]);
    }

    /**
     * Insert a taxonomy term.
     */
    public function insertTerm(string $taxonomy, array $term): void
    {
        $stmt = $this->stmt('insert_term', '
            INSERT OR REPLACE INTO taxonomy_terms 
            (taxonomy, slug, name, count, items, meta)
            VALUES (:taxonomy, :slug, :name, :count, :items, :meta)
        ');

        $meta = $term;
        unset($meta['slug'], $meta['name'], $meta['count'], $meta['items']);

        $stmt->execute([
            'taxonomy' => $taxonomy,
            'slug' => $term['slug'] ?? '',
            'name' => $term['name'] ?? '',
            'count' => $term['count'] ?? 0,
            'items' => json_encode($term['items'] ?? []),
            'meta' => json_encode($meta),
        ]);
    }

    /**
     * Insert a route.
     */
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
            'data' => json_encode($data),
        ]);
    }

    /**
     * Set a metadata value.
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $stmt = $this->stmt('set_metadata', '
            INSERT OR REPLACE INTO metadata (key, value)
            VALUES (:key, :value)
        ');

        $stmt->execute([
            'key' => $key,
            'value' => json_encode($value),
        ]);
    }

    /**
     * Get a metadata value.
     */
    public function getMetadata(string $key): mixed
    {
        $stmt = $this->stmt('get_metadata', '
            SELECT value FROM metadata WHERE key = :key LIMIT 1
        ');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return json_decode($row['value'], true);
    }

    /**
     * Get the database path.
     */
    public function getDatabasePath(): string
    {
        return $this->dbPath;
    }

    /**
     * Create a fresh database file.
     */
    public function createDatabase(): void
    {
        // Ensure directory exists
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Remove existing database
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }

        // Clear cached connection
        $this->clearMemoryCache();

        // Initialize schema
        $this->initializeSchema();
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Convert a database row to an item array.
     */
    private function rowToItem(array $row): array
    {
        return [
            'id' => $row['id'],
            'type' => $row['type'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'status' => $row['status'],
            'date' => $row['date'],
            'updated' => $row['updated_at'],
            'file_path' => $this->app->configPath('content') . '/' . $row['file_path'],
            'relative_path' => $row['file_path'],
            'template' => $row['template'],
            'excerpt' => $row['excerpt'],
            'taxonomies' => json_decode($row['taxonomies'] ?? '{}', true),
            'meta' => json_decode($row['meta'] ?? '{}', true),
            'frontmatter' => json_decode($row['frontmatter'] ?? '{}', true),
        ];
    }
}
