<?php

declare(strict_types=1);

/**
 * Ava Sitemap Plugin
 *
 * Generates XML sitemaps for search engines.
 * 
 * Features:
 * - Sitemap index at /sitemap.xml
 * - Per-content-type sitemaps (/sitemap-posts.xml, /sitemap-pages.xml)
 * - Respects noindex frontmatter field
 * - Supports lastmod from updated/date fields
 *
 * @package Ava\Plugins\Sitemap
 */

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

return [
    'name' => 'Sitemap',
    'version' => '1.0.0',
    'description' => 'Generates XML sitemaps for search engines',
    'author' => 'Ava CMS',

    'boot' => function (Application $app) {
        $router = $app->router();
        $baseUrl = rtrim($app->config('site.base_url', ''), '/');

        // Default configuration (can be overridden in ava.php under 'sitemap')
        $config = array_merge([
            'enabled' => true,
        ], $app->config('sitemap', []));

        if (!$config['enabled']) {
            return;
        }

        // Load content types
        $contentTypesFile = $app->path('app/config/content_types.php');
        $contentTypes = file_exists($contentTypesFile) ? require $contentTypesFile : [];

        // Sitemap index route
        $router->addRoute('/sitemap.xml', function (Request $request) use ($app, $baseUrl) {
            $repository = $app->repository();
            $types = $repository->types();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($types as $type) {
                // Check if this type has any published, indexable content
                // Use publishedMeta() - we only need metadata, not full content
                $items = $repository->publishedMeta($type);
                $hasIndexable = false;
                $lastMod = null;
                foreach ($items as $item) {
                    if ($item->noindex()) {
                        continue;
                    }
                    $hasIndexable = true;
                    $updated = $item->updated();
                    if ($updated && ($lastMod === null || $updated > $lastMod)) {
                        $lastMod = $updated;
                    }
                }

                if ($hasIndexable) {
                    $xml .= "  <sitemap>\n";
                    $xml .= "    <loc>{$baseUrl}/sitemap-{$type}.xml</loc>\n";
                    if ($lastMod) {
                        $xml .= "    <lastmod>" . $lastMod->format('Y-m-d') . "</lastmod>\n";
                    }
                    
                    $xml .= "  </sitemap>\n";
                }
            }

            $xml .= '</sitemapindex>';

            return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        });

        // Per-type sitemap routes
        foreach (array_keys($contentTypes) as $type) {
            $router->addRoute("/sitemap-{$type}.xml", function (Request $request) use ($app, $baseUrl, $type, $config, $contentTypes) {
                $repository = $app->repository();
                $routes = $repository->routes();
                $reverseRoutes = $routes['reverse'] ?? [];
                // Use publishedMeta() - sitemaps only need URL and lastmod, not body content
                $items = $repository->publishedMeta($type);

                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

                foreach ($items as $item) {
                    // Skip noindex items
                    if ($item->noindex()) {
                        continue;
                    }

                    // Find URL for this item from reverse routes (O(1))
                    $key = $type . ':' . $item->slug();
                    $url = $reverseRoutes[$key] ?? null;

                    // Fallback: generate from pattern
                    if ($url === null) {
                        $typeConfig = $contentTypes[$type] ?? [];
                        $urlConfig = $typeConfig['url'] ?? [];
                        $pattern = $urlConfig['pattern'] ?? '/' . $type . '/{slug}';
                        $url = str_replace('{slug}', $item->slug(), $pattern);
                    }

                    $xml .= "  <url>\n";
                    $xml .= "    <loc>{$baseUrl}{$url}</loc>\n";
                    
                    $updated = $item->updated();
                    if ($updated) {
                        $xml .= "    <lastmod>" . $updated->format('Y-m-d') . "</lastmod>\n";
                    }
                    
                    $xml .= "  </url>\n";
                }

                $xml .= '</urlset>';

                return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
            });
        }

        // Register admin page
        Hooks::addFilter('admin.register_pages', function (array $pages) use ($baseUrl) {
            $pages['sitemap'] = [
                'label' => 'Sitemap',
                'icon' => 'map',
                'section' => 'Plugins',
                'handler' => function (Request $request, Application $app, $controller) use ($baseUrl) {
                    $repository = $app->repository();
                    $types = $repository->types();

                    // Gather stats (metadata only, no file I/O needed)
                    $stats = [];
                    $totalUrls = 0;
                    foreach ($types as $type) {
                        $items = $repository->publishedMeta($type);
                        $indexable = 0;
                        $noindex = 0;
                        foreach ($items as $item) {
                            if ($item->noindex()) {
                                $noindex++;
                            } else {
                                $indexable++;
                            }
                        }
                        $stats[$type] = [
                            'indexable' => $indexable,
                            'noindex' => $noindex,
                            'total' => count($items),
                        ];
                        $totalUrls += $indexable;
                    }

                    // Render content-only view
                    ob_start();
                    include __DIR__ . '/views/content.php';
                    $content = ob_get_clean();

                    // Use the admin layout wrapper
                    return $controller->renderPluginPage([
                        'title' => 'Sitemap',
                        'icon' => 'map',
                        'activePage' => 'sitemap',
                        'headerActions' => '<a href="' . htmlspecialchars($baseUrl) . '/sitemap.xml" target="_blank" class="btn btn-primary btn-sm">
                            <span class="material-symbols-rounded">open_in_new</span>
                            View Sitemap
                        </a>' .
                        '<a href="https://ava.addy.zone/docs/bundled-plugins" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                            <span class="material-symbols-rounded">menu_book</span>
                            <span class="hide-mobile">Docs</span>
                        </a>',
                    ], $content);
                },
            ];
            return $pages;
        });

        // Add sitemap to robots.txt on content rebuild (CLI, auto, or admin)
        Hooks::addAction('indexer.rebuild', function (Application $app) use ($baseUrl) {
            $robotsFile = $app->path('public/robots.txt');
            $sitemapUrl = $baseUrl . '/sitemap.xml';
            $sitemapLine = "Sitemap: {$sitemapUrl}";

            if (file_exists($robotsFile)) {
                $content = file_get_contents($robotsFile);
                $lines = explode("\n", $content);
                $newLines = [];
                $found = false;
                $updated = false;

                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), 'Sitemap:')) {
                        // If sitemap line exists, check if it matches current URL
                        if (trim($line) === $sitemapLine) {
                            $found = true;
                            $newLines[] = $line;
                        } else {
                            // Update old sitemap URL
                            $newLines[] = $sitemapLine;
                            $found = true;
                            $updated = true;
                        }
                    } else {
                        $newLines[] = $line;
                    }
                }

                if ($updated) {
                    file_put_contents($robotsFile, implode("\n", $newLines));
                    if (php_sapi_name() === 'cli') {
                        echo "  \033[32m✔\033[0m Updated Sitemap URL in robots.txt\n";
                    }
                } elseif (!$found) {
                    // Append if not present
                    $separator = (substr($content, -1) !== "\n") ? "\n" : "";
                    file_put_contents($robotsFile, $content . $separator . $sitemapLine . "\n");
                    if (php_sapi_name() === 'cli') {
                        echo "  \033[32m✔\033[0m Added Sitemap to robots.txt\n";
                    }
                }
            } else {
                // Create if it doesn't exist
                file_put_contents($robotsFile, "User-agent: *\nAllow: /\n\n" . $sitemapLine . "\n");
                if (php_sapi_name() === 'cli') {
                    echo "  \033[32m✔\033[0m Created robots.txt with Sitemap link\n";
                }
            }
        });
    },

    'commands' => [
        [
            'name' => 'sitemap:stats',
            'description' => 'Show sitemap statistics',
            'handler' => function (array $args, $cli, \Ava\Application $app) {
                $repository = $app->repository();
                $types = $repository->types();
                $baseUrl = rtrim($app->config('site.base_url', ''), '/');

                $cli->header('Sitemap Statistics');
                
                $totalUrls = 0;
                $tableData = [];

                foreach ($types as $type) {
                    // Use publishedMeta() for CLI stats - no file I/O needed
                    $items = $repository->publishedMeta($type);
                    $indexable = 0;
                    $noindexed = 0;

                    foreach ($items as $item) {
                        if ($item->noindex()) {
                            $noindexed++;
                        } else {
                            $indexable++;
                        }
                    }

                    if ($indexable > 0 || $noindexed > 0) {
                        $tableData[] = [
                            'type' => $type,
                            'indexable' => $indexable,
                            'noindex' => $noindexed,
                            'file' => "/sitemap-{$type}.xml",
                        ];
                        $totalUrls += $indexable;
                    }
                }

                if (empty($tableData)) {
                    $cli->warning('No content types with published content found.');
                    return 0;
                }

                // Display table with colors
                $cli->writeln('');
                $headers = ['Content Type', 'Indexable', 'Noindex', 'Sitemap File'];
                $rows = array_map(fn($d) => [
                    $cli->primary($d['type']),
                    $cli->green((string)$d['indexable']),
                    $d['noindex'] > 0 ? $cli->yellow((string)$d['noindex']) : $cli->dim('0'),
                    $cli->cyan($d['file']),
                ], $tableData);
                $cli->table($headers, $rows);

                $cli->writeln('');
                $cli->info("Total URLs in sitemap: " . $cli->bold((string)$totalUrls));
                $cli->info("Main sitemap: " . $cli->primary("{$baseUrl}/sitemap.xml"));
                $cli->writeln('');

                return 0;
            },
        ],
    ],
];
