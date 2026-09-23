<?php

declare(strict_types=1);

namespace Ava\Content\Index;

use Ava\Content\Item;
use Ava\Content\Parser;

/**
 * Finds, parses and validates content files.
 *
 * A file that fails to parse or validate is reported and left out; it never
 * aborts the scan, because one bad file must not take the whole site down.
 */
final class ContentScanner
{
    private Parser $parser;

    public function __construct(private string $contentRoot, private ItemPaths $paths)
    {
        $this->parser = new Parser();
    }

    /**
     * @param array<string, mixed> $contentTypes As configured; non-array entries are ignored.
     * @return array{items: array<string, list<Item>>, errors: list<string>, warnings: list<string>}
     */
    public function scan(array $contentTypes, bool $collectWarnings = false): array
    {
        $items = [];
        $errors = [];
        $warnings = [];
        $seenIds = [];

        foreach ($contentTypes as $typeName => $typeConfig) {
            $items[$typeName] = $this->scanType(
                (string) $typeName,
                is_array($typeConfig) ? $typeConfig : [],
                $seenIds,
                $errors,
                $warnings,
                $collectWarnings
            );
        }

        return ['items' => $items, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param array<string, string> $seenIds id => file, shared across types
     * @return list<Item>
     */
    private function scanType(
        string $typeName,
        array $typeConfig,
        array &$seenIds,
        array &$errors,
        array &$warnings,
        bool $collectWarnings
    ): array {
        $basePath = $this->contentRoot . '/' . ($typeConfig['content_dir'] ?? $typeName);
        if (!is_dir($basePath)) {
            return [];
        }

        /** @var array<string, Item> $itemsByKey */
        $itemsByKey = [];

        foreach ($this->findContentFiles($basePath) as $filePath) {
            try {
                $item = $this->parser->parseFile($filePath, $typeName);

                foreach ($this->parser->validate($item) as $error) {
                    $errors[] = "{$filePath}: {$error}";
                }
                if ($collectWarnings) {
                    foreach ($this->parser->validateWarnings($item) as $warning) {
                        $warnings[] = "{$filePath}: {$warning}";
                    }
                }

                $key = $this->paths->contentKey($item, $typeConfig);
                $existing = $itemsByKey[$key] ?? null;

                if ($existing !== null) {
                    if ($existing->format() === $item->format()) {
                        $errors[] = "{$filePath}: Duplicate content key '{$key}' (also in {$existing->filePath()})";
                        continue;
                    }

                    // Cross-format collision: the .html file wins over .md.
                    [$winner, $loser] = $item->isHtml() ? [$item, $existing] : [$existing, $item];
                    $errors[] = "{$loser->filePath()}: Overridden by .html file with same content key '{$key}' ({$winner->filePath()})";
                    if ($winner === $existing) {
                        continue;
                    }
                    if ($existing->id() !== null) {
                        unset($seenIds[$existing->id()]);
                    }
                }

                $id = $item->id();
                if ($id !== null) {
                    if (isset($seenIds[$id])) {
                        $errors[] = "{$filePath}: Duplicate ID '{$id}' (also in {$seenIds[$id]})";
                    } else {
                        $seenIds[$id] = $filePath;
                    }
                }

                $itemsByKey[$key] = $item->withContentKey($key);
            } catch (\Throwable $e) {
                $errors[] = "{$filePath}: " . $e->getMessage();
            }
        }

        return array_values($itemsByKey);
    }

    /**
     * Content files (.md, .html), sorted so builds are deterministic.
     *
     * @return list<string>
     */
    private function findContentFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $file): bool => !str_starts_with($file->getFilename(), '.')
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['md', 'html'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }
}
