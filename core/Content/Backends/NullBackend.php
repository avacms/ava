<?php

declare(strict_types=1);

namespace Ava\Content\Backends;

/**
 * The backend before any index has been built: everything is empty.
 *
 * Requests never see this (the application builds an index before routing),
 * but CLI commands and code running before the first build read an empty
 * site rather than an error. An index that exists but cannot be read is a
 * different matter and still fails loudly.
 */
final class NullBackend implements BackendInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getBySlug(string $type, string $slug): ?array
    {
        return null;
    }

    public function getById(string $id): ?array
    {
        return null;
    }

    public function getByPath(string $relativePath): ?array
    {
        return null;
    }

    public function allRaw(string $type, bool $withBody = false): array
    {
        return [];
    }

    public function types(): array
    {
        return [];
    }

    public function count(string $type, ?string $status = null): int
    {
        return 0;
    }

    public function exists(string $type, string $slug): bool
    {
        return false;
    }

    public function query(array $params): array
    {
        return ['items' => [], 'total' => 0];
    }

    public function canUseFastCache(string $type, int $page, int $perPage): bool
    {
        return false;
    }

    public function getRecentItems(string $type, int $page, int $perPage): array
    {
        return ['items' => [], 'total' => 0];
    }

    public function terms(string $taxonomy): array
    {
        return [];
    }

    public function term(string $taxonomy, string $slug): ?array
    {
        return null;
    }

    public function taxonomies(): array
    {
        return [];
    }

    public function routes(): array
    {
        return ['redirects' => [], 'exact' => [], 'preview' => [], 'patterns' => [], 'taxonomy' => [], 'reverse' => []];
    }

    public function exactRoute(string $path): ?array
    {
        return null;
    }

    public function previewRoute(string $path): ?array
    {
        return null;
    }

    public function redirectRoute(string $path): ?array
    {
        return null;
    }

    public function reverseUrl(string $type, string $contentKey): ?string
    {
        return null;
    }

    public function taxonomyRoutes(): array
    {
        return [];
    }

    public function clearMemoryCache(): void
    {
    }
}
