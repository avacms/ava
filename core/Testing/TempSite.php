<?php

declare(strict_types=1);

namespace Ava\Testing;

use Ava\Application;

/**
 * A throwaway site for integration tests: its own content and storage
 * directories, sharing the real app's config files, theme and plugins.
 */
final class TempSite
{
    public readonly string $relative;
    public readonly string $root;

    public function __construct(private array $baseConfig)
    {
        $this->relative = 'storage/tmp/site-' . bin2hex(random_bytes(6));
        $this->root = AVA_ROOT . '/' . $this->relative;
        mkdir($this->root . '/content/pages', 0755, true);
        mkdir($this->root . '/content/posts', 0755, true);
        mkdir($this->root . '/storage', 0755, true);
    }

    /**
     * Write a content file (path relative to content/).
     */
    public function write(string $path, string $contents): string
    {
        $file = $this->root . '/content/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, $contents);

        return $file;
    }

    /**
     * A Markdown file with frontmatter.
     */
    public function page(string $path, array $frontmatter, string $body = ''): string
    {
        $yaml = '';
        foreach ($frontmatter as $key => $value) {
            $yaml .= $key . ': ' . (is_array($value) ? '[' . implode(', ', $value) . ']' : $value) . "\n";
        }

        return $this->write($path, "---\n{$yaml}---\n{$body}\n");
    }

    public function app(array $overrides = []): Application
    {
        $config = array_replace_recursive($this->baseConfig, [
            'paths' => [
                'content' => $this->relative . '/content',
                'storage' => $this->relative . '/storage',
            ],
            'site' => ['base_url' => 'https://example.test'],
            'webpage_cache' => ['enabled' => true, 'ttl' => null, 'exclude' => [], 'hosts' => []],
            'content_index' => ['mode' => 'auto', 'check_interval' => 0, 'backend' => 'array'],
        ], $overrides);

        return new Application($config);
    }

    public function remove(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @chmod($item->getPathname(), 0755);
                rmdir($item->getPathname());
            } else {
                @chmod($item->getPathname(), 0644);
                unlink($item->getPathname());
            }
        }
        rmdir($this->root);
    }
}
