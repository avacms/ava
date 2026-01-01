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
 * - Configurable changefreq and priority per content type
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
            'changefreq' => [
                'page' => 'monthly',
                'post' => 'weekly',
            ],
            'priority' => [
                'page' => '0.8',
                'post' => '0.6',
            ],
        ], $app->config('sitemap', []));

        if (!$config['enabled']) {
            return;
        }

        // Load content types
        $contentTypesFile = $app->path('app/config/content_types.php');
        $contentTypes = file_exists($contentTypesFile) ? require $contentTypesFile : [];

        // Sitemap index route
        $router->addRoute('/sitemap.xml', function (Request $request) use ($app, $baseUrl, $contentTypes) {
            $repository = $app->repository();
            $types = $repository->types();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($types as $type) {
                // Check if this type has any published, indexable content
                $items = $repository->published($type);
                $hasIndexable = false;
                foreach ($items as $item) {
                    if (!$item->noindex()) {
                        $hasIndexable = true;
                        break;
                    }
                }

                if ($hasIndexable) {
                    $xml .= "  <sitemap>\n";
                    $xml .= "    <loc>{$baseUrl}/sitemap-{$type}.xml</loc>\n";
                    
                    // Get most recent update from this type
                    $lastMod = null;
                    foreach ($items as $item) {
                        if ($item->noindex()) continue;
                        $updated = $item->updated();
                        if ($updated && ($lastMod === null || $updated > $lastMod)) {
                            $lastMod = $updated;
                        }
                    }
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
                $items = $repository->published($type);

                $changefreq = $config['changefreq'][$type] ?? 'weekly';
                $priority = $config['priority'][$type] ?? '0.5';

                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

                foreach ($items as $item) {
                    // Skip noindex items
                    if ($item->noindex()) {
                        continue;
                    }

                    // Find URL for this item from routes
                    $url = null;
                    foreach ($routes['exact'] ?? [] as $routeUrl => $routeData) {
                        if (($routeData['content_type'] ?? '') === $type && ($routeData['slug'] ?? '') === $item->slug()) {
                            $url = $routeUrl;
                            break;
                        }
                    }

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
                    
                    $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
                    $xml .= "    <priority>{$priority}</priority>\n";
                    $xml .= "  </url>\n";
                }

                $xml .= '</urlset>';

                return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
            });
        }

        // Register admin page
        Hooks::addFilter('admin.register_pages', function (array $pages) use ($app, $baseUrl, $contentTypes) {
            $pages['sitemap'] = [
                'label' => 'Sitemap',
                'icon' => 'map',
                'section' => 'Plugins',
                'handler' => function (Request $request, Application $app, $controller) use ($baseUrl, $contentTypes) {
                    $repository = $app->repository();
                    $types = $repository->types();

                    // Gather stats
                    $stats = [];
                    $totalUrls = 0;
                    foreach ($types as $type) {
                        $items = $repository->published($type);
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
                        </a>',
                    ], $content);
                },
            ];
            return $pages;
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
                    $items = $repository->published($type);
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
