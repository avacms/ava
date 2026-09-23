<?php

declare(strict_types=1);

namespace Ava\Cli\Commands;

use Ava\Application as AvaApp;
use Ava\Cli\Output;
use Ava\Content\Index\ItemPaths;
use Ava\Http\UrlPath;

/**
 * ./ava preview <url-path|content-file> [--hours=N]
 *
 * Prints a signed preview link for one unpublished item. The link works for
 * that URL only and expires, so it can be shared without handing out the
 * site's preview secret.
 */
final class PreviewCommand
{
    private const DEFAULT_HOURS = 72;

    public function __construct(
        private Output $output,
        private AvaApp $app,
    ) {}

    public function execute(array $args): int
    {
        $target = null;
        $hours = self::DEFAULT_HOURS;
        foreach ($args as $arg) {
            if (preg_match('/^--hours=(\d+)$/', $arg, $matches) === 1) {
                $hours = max(1, (int) $matches[1]);
            } elseif (!str_starts_with($arg, '-')) {
                $target ??= $arg;
            }
        }

        if ($target === null) {
            $this->output->writeln('');
            $this->output->writeln('  ' . $this->output->bold('Usage:') . ' ./ava preview <url-path|content-file> [--hours=N]');
            $this->output->writeln('');
            $this->output->writeln('    ' . $this->output->dim('./ava preview /blog/my-draft'));
            $this->output->writeln('    ' . $this->output->dim('./ava preview content/posts/my-draft.md --hours=24'));
            $this->output->writeln('');
            return 1;
        }

        $links = $this->app->router()->previewLinks();
        if (!$links->enabled()) {
            $this->output->error('Previews are disabled. Set security.preview_token in app/config/ava.php to a long random string.');
            return 1;
        }

        $this->app->indexer()->rebuildIfStale();
        $path = $this->resolvePath($target);
        if ($path === null) {
            $this->output->error("No content found at {$target}");
            $this->output->tip('Pass the URL path the item will be published at, or its file under content/.');
            return 1;
        }

        $expiresAt = time() + $hours * 3600;
        $url = rtrim((string) $this->app->config('site.base_url', ''), '/')
            . UrlPath::encodeForHeader($links->url($path, $expiresAt));

        $this->output->writeln('');
        $this->output->keyValue('Preview', $this->output->primary($url));
        $this->output->keyValue('Expires', date('Y-m-d H:i', $expiresAt) . $this->output->dim(" ({$hours}h)"));
        $this->output->writeln('');

        return 0;
    }

    /**
     * The URL path for a URL path or content file argument.
     */
    private function resolvePath(string $target): ?string
    {
        $repository = $this->app->repository();

        if (str_starts_with($target, '/')) {
            $path = UrlPath::decode(parse_url($target, PHP_URL_PATH) ?: $target);
            $path = $path === '/' ? $path : rtrim($path, '/');

            return $repository->previewRoute($path) !== null || $repository->exactRoute($path) !== null
                ? $path
                : null;
        }

        $contentRoot = $this->app->configPath('content');
        $file = realpath($target) ?: realpath($contentRoot . '/' . $target);
        if ($file === false) {
            return null;
        }
        $relative = (new ItemPaths($contentRoot))->relativePath($file);

        foreach (['preview', 'exact'] as $group) {
            foreach ($repository->routes()[$group] ?? [] as $path => $route) {
                if (($route['file'] ?? null) === $relative) {
                    return (string) $path;
                }
            }
        }

        return null;
    }
}
