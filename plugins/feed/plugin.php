<?php

declare(strict_types=1);

/**
 * Ava RSS Feed Plugin
 *
 * Generates RSS 2.0 feeds for content.
 * 
 * Features:
 * - Per-content-type feeds (/feed/posts.xml, /feed/pages.xml)
 * - Combined feed at /feed.xml
 * - Configurable item count
 * - Respects noindex frontmatter field
 * - Full content or excerpt in feed
 *
 * @package Ava\Plugins\Feed
 */

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

return [
    'name' => 'RSS Feed',
    'version' => '1.0.0',
    'description' => 'Generates RSS 2.0 feeds for content',
    'author' => 'Ava CMS',

    'boot' => function (Application $app) {
        $router = $app->router();
        $baseUrl = rtrim($app->config('site.base_url', ''), '/');
        $siteName = $app->config('site.name', 'Ava Site');

        // Default configuration
        $config = array_merge([
            'enabled' => true,
            'items_per_feed' => 20,
            'full_content' => false,  // true = full HTML, false = excerpt only
            'types' => null,  // null = all types, or array of type names
        ], $app->config('feed', []));

        if (!$config['enabled']) {
            return;
        }

        // Load content types
        $contentTypesFile = $app->path('app/config/content_types.php');
        $contentTypes = file_exists($contentTypesFile) ? require $contentTypesFile : [];

        // Helper to generate RSS XML
        $generateFeed = function (array $items, string $title, string $description, string $feedUrl) use ($baseUrl, $config, $contentTypes, $app) {
            $repository = $app->repository();
            $routes = $repository->routes();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
            $xml .= "<channel>\n";
            $xml .= "  <title>" . htmlspecialchars($title) . "</title>\n";
            $xml .= "  <link>{$baseUrl}</link>\n";
            $xml .= "  <description>" . htmlspecialchars($description) . "</description>\n";
            $xml .= "  <language>en</language>\n";
            $xml .= "  <atom:link href=\"{$baseUrl}{$feedUrl}\" rel=\"self\" type=\"application/rss+xml\"/>\n";
            
            // Build date from most recent item
            if (!empty($items)) {
                $mostRecent = $items[0]->updated() ?? $items[0]->date();
                if ($mostRecent) {
                    $xml .= "  <lastBuildDate>" . $mostRecent->format('r') . "</lastBuildDate>\n";
                }
            }

            foreach ($items as $item) {
                // Skip noindex items
                if ($item->noindex()) {
                    continue;
                }

                // Find URL for this item
                $url = null;
                $type = $item->type();
                foreach ($routes['exact'] ?? [] as $routeUrl => $routeData) {
                    if (($routeData['content_type'] ?? '') === $type && ($routeData['slug'] ?? '') === $item->slug()) {
                        $url = $routeUrl;
                        break;
                    }
                }

                if ($url === null) {
                    $typeConfig = $contentTypes[$type] ?? [];
                    $urlConfig = $typeConfig['url'] ?? [];
                    $pattern = $urlConfig['pattern'] ?? '/' . $type . '/{slug}';
                    $url = str_replace('{slug}', $item->slug(), $pattern);
                }

                $xml .= "  <item>\n";
                $xml .= "    <title>" . htmlspecialchars($item->title()) . "</title>\n";
                $xml .= "    <link>{$baseUrl}{$url}</link>\n";
                $xml .= "    <guid isPermaLink=\"true\">{$baseUrl}{$url}</guid>\n";

                $date = $item->date();
                if ($date) {
                    $xml .= "    <pubDate>" . $date->format('r') . "</pubDate>\n";
                }

                // Content - either full or excerpt
                $content = $config['full_content'] 
                    ? $app->renderer()->renderItem($item) 
                    : $item->excerpt();
                if ($content) {
                    $xml .= "    <description><![CDATA[" . $content . "]]></description>\n";
                }

                $xml .= "  </item>\n";
            }

            $xml .= "</channel>\n";
            $xml .= '</rss>';

            return $xml;
        };

        // Combined feed at /feed.xml
        $router->addRoute('/feed.xml', function (Request $request) use ($app, $siteName, $config, $generateFeed) {
            $repository = $app->repository();
            $allItems = [];

            // Determine which types to include
            $types = $config['types'] ?? $repository->types();
            if (!is_array($types)) {
                $types = [$types];
            }

            foreach ($types as $type) {
                foreach ($repository->published($type) as $item) {
                    if (!$item->noindex()) {
                        $allItems[] = $item;
                    }
                }
            }

            // Sort by date descending
            usort($allItems, function ($a, $b) {
                $aDate = $a->date();
                $bDate = $b->date();
                if (!$aDate && !$bDate) return 0;
                if (!$aDate) return 1;
                if (!$bDate) return -1;
                return $bDate->getTimestamp() - $aDate->getTimestamp();
            });

            // Limit items
            $allItems = array_slice($allItems, 0, $config['items_per_feed']);

            $xml = $generateFeed(
                $allItems,
                $siteName,
                "Latest content from {$siteName}",
                '/feed.xml'
            );

            return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
        });

        // Per-type feeds at /feed/{type}.xml
        $router->addRoute('/feed/{type}.xml', function (Request $request, array $params) use ($app, $siteName, $config, $generateFeed, $contentTypes) {
            $type = $params['type'] ?? '';
            $repository = $app->repository();

            // Check if type exists
            if (!isset($contentTypes[$type])) {
                return null;
            }

            $items = $repository->published($type);

            // Filter out noindex
            $items = array_filter($items, fn($item) => !$item->noindex());

            // Sort by date descending
            usort($items, function ($a, $b) {
                $aDate = $a->date();
                $bDate = $b->date();
                if (!$aDate && !$bDate) return 0;
                if (!$aDate) return 1;
                if (!$bDate) return -1;
                return $bDate->getTimestamp() - $aDate->getTimestamp();
            });

            // Limit items
            $items = array_slice($items, 0, $config['items_per_feed']);

            $typeLabel = $contentTypes[$type]['label'] ?? ucfirst($type) . 's';
            $xml = $generateFeed(
                $items,
                "{$siteName} - {$typeLabel}",
                "{$typeLabel} from {$siteName}",
                "/feed/{$type}.xml"
            );

            return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
        });

        // Register admin page
        Hooks::addFilter('admin.register_pages', function (array $pages) use ($baseUrl, $config) {
            $pages['feeds'] = [
                'label' => 'RSS Feeds',
                'icon' => 'rss_feed',
                'section' => 'Plugins',
                'handler' => function (Request $request, Application $app, $controller) use ($baseUrl, $config) {
                    $repository = $app->repository();
                    $types = $repository->types();

                    // Gather stats
                    $feeds = [];
                    $totalItems = 0;
                    foreach ($types as $type) {
                        $items = $repository->published($type);
                        $indexable = count(array_filter($items, fn($i) => !$i->noindex()));
                        $feeds[$type] = [
                            'count' => min($indexable, $config['items_per_feed']),
                            'total' => $indexable,
                        ];
                        $totalItems += $indexable;
                    }

                    // Render content-only view
                    ob_start();
                    include __DIR__ . '/views/content.php';
                    $content = ob_get_clean();

                    // Use the admin layout wrapper
                    return $controller->renderPluginPage([
                        'title' => 'RSS Feeds',
                        'icon' => 'rss_feed',
                        'activePage' => 'feeds',
                        'headerActions' => '<a href="' . htmlspecialchars($baseUrl) . '/feed.xml" target="_blank" class="btn btn-primary btn-sm">
                            <span class="material-symbols-rounded">open_in_new</span>
                            View Main Feed
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
    },

    'commands' => [
        [
            'name' => 'feed:stats',
            'description' => 'Show RSS feed statistics',
            'handler' => function (array $args, $cli, \Ava\Application $app) {
                $repository = $app->repository();
                $types = $repository->types();
                $baseUrl = rtrim($app->config('site.base_url', ''), '/');

                // Get feed config
                $config = array_merge([
                    'items_per_feed' => 20,
                    'full_content' => false,
                    'types' => null,
                ], $app->config('feed', []));

                $cli->header('RSS Feed Statistics');
                
                $tableData = [];
                $totalItems = 0;
                $combinedCount = 0;

                foreach ($types as $type) {
                    // Skip if types filter is set and this type isn't included
                    if ($config['types'] !== null && !in_array($type, $config['types'])) {
                        continue;
                    }

                    $items = $repository->published($type);
                    $count = 0;
                    
                    foreach ($items as $item) {
                        if (!$item->noindex()) {
                            $count++;
                        }
                    }

                    if ($count > 0) {
                        $inFeed = min($count, $config['items_per_feed']);
                        $tableData[] = [
                            'type' => $type,
                            'total' => $count,
                            'in_feed' => $inFeed,
                            'file' => "/feed/{$type}.xml",
                        ];
                        $totalItems += $count;
                        $combinedCount += $inFeed;
                    }
                }

                if (empty($tableData)) {
                    $cli->warning('No content available for feeds.');
                    return 0;
                }

                // Display table with colors
                $cli->writeln('');
                $headers = ['Content Type', 'Total Items', 'In Feed', 'Feed URL'];
                $rows = array_map(fn($d) => [
                    $cli->primary($d['type']),
                    (string)$d['total'],
                    $cli->green((string)$d['in_feed']),
                    $cli->cyan($d['file']),
                ], $tableData);
                $cli->table($headers, $rows);

                $cli->writeln('');
                $cli->info("Items per feed: " . $cli->bold((string)$config['items_per_feed']));
                $cli->info("Content mode: " . ($config['full_content'] ? $cli->green('Full HTML') : $cli->yellow('Excerpt only')));
                $cli->info("Main feed: " . $cli->primary("{$baseUrl}/feed.xml"));
                $cli->writeln('');

                return 0;
            },
        ],
    ],
];
