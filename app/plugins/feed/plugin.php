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

        $router->addRoute('/feed.xsl', function (Request $request) {
            $xsl = <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="html" encoding="UTF-8"/>
<xsl:template match="/rss/channel">
<html><head><meta name="viewport" content="width=device-width, initial-scale=1"/><title><xsl:value-of select="title"/></title>
<style>body{margin:0;background:#f5f4ef;color:#20211f;font:16px/1.6 Georgia,serif}main{max-width:760px;margin:auto;padding:48px 24px}header{border-bottom:3px solid #20211f;margin-bottom:28px}h1{font-size:2.4rem;line-height:1.1;margin:0 0 8px}header p{color:#62645f}.item{padding:22px 0;border-bottom:1px solid #cbc9c0}h2{font-size:1.35rem;margin:0 0 6px}a{color:#08756a}.date{font:13px sans-serif;color:#73756f}.description{margin-top:10px}@media(max-width:600px){main{padding:28px 18px}}</style>
</head><body><main><header><h1><xsl:value-of select="title"/></h1><p><xsl:value-of select="description"/> This RSS feed is formatted for people and feed readers.</p></header>
<xsl:for-each select="item"><article class="item"><h2><a href="{link}"><xsl:value-of select="title"/></a></h2><div class="date"><xsl:value-of select="pubDate"/></div><div class="description"><xsl:value-of select="description"/></div></article></xsl:for-each>
</main></body></html>
</xsl:template></xsl:stylesheet>
XSL;
            return new Response($xsl, 200, ['Content-Type' => 'text/xsl; charset=utf-8']);
        });

        // Load content types
        $contentTypesFile = $app->path('app/config/content_types.php');
        $contentTypes = file_exists($contentTypesFile) ? require $contentTypesFile : [];

        // Helper to generate RSS XML
        $generateFeed = function (array $items, string $title, string $description, string $feedUrl) use ($baseUrl, $config, $contentTypes, $app) {
            $repository = $app->repository();
            $routes = $repository->routes();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<?xml-stylesheet type="text/xsl" href="/feed.xsl"?>' . "\n";
            $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
            $xml .= "<channel>\n";
            $safeBaseUrl = htmlspecialchars($baseUrl, ENT_XML1, 'UTF-8');
            $safeFeedUrl = htmlspecialchars($feedUrl, ENT_XML1, 'UTF-8');
            $xml .= "  <title>" . htmlspecialchars($title) . "</title>\n";
            $xml .= "  <link>{$safeBaseUrl}</link>\n";
            $xml .= "  <description>" . htmlspecialchars($description) . "</description>\n";
            $xml .= "  <language>en</language>\n";
            $xml .= "  <atom:link href=\"{$safeBaseUrl}{$safeFeedUrl}\" rel=\"self\" type=\"application/rss+xml\"/>\n";
            
            // Build date from most recent item
            if (!empty($items)) {
                $mostRecent = $items[0]->updated() ?? $items[0]->date();
                if ($mostRecent) {
                    $xml .= "  <lastBuildDate>" . $mostRecent->format('r') . "</lastBuildDate>\n";
                }
            }

            // Build reverse route index for O(1) lookups
            $reverseRoutes = $routes['reverse'] ?? [];

            foreach ($items as $item) {
                // Skip noindex items
                if ($item->noindex()) {
                    continue;
                }

                // Find URL for this item using reverse routes (O(1) lookup)
                $type = $item->type();
                $key = $type . ':' . ($item->get('content_key') ?? $item->slug());
                $url = $reverseRoutes[$key] ?? $reverseRoutes[$type . ':' . $item->slug()] ?? null;

                if ($url === null) {
                    $typeConfig = $contentTypes[$type] ?? [];
                    $urlConfig = $typeConfig['url'] ?? [];
                    $pattern = $urlConfig['pattern'] ?? '/' . $type . '/{slug}';
                    $url = str_replace('{slug}', $item->slug(), $pattern);
                }

                $safeUrl = htmlspecialchars($url, ENT_XML1, 'UTF-8');
                $xml .= "  <item>\n";
                $xml .= "    <title>" . htmlspecialchars($item->title()) . "</title>\n";
                $xml .= "    <link>{$safeBaseUrl}{$safeUrl}</link>\n";
                $xml .= "    <guid isPermaLink=\"true\">{$safeBaseUrl}{$safeUrl}</guid>\n";

                $date = $item->date();
                if ($date) {
                    $xml .= "    <pubDate>" . $date->format('r') . "</pubDate>\n";
                }

                // Content - either full or excerpt
                $content = $config['full_content'] 
                    ? $app->renderer()->renderItem($item) 
                    : $item->excerpt();
                if ($content) {
                    // Escape ]]> inside CDATA to prevent premature closure
                    $safeCdata = str_replace(']]>', ']]]]><![CDATA[>', $content);
                    $xml .= "    <description><![CDATA[" . $safeCdata . "]]></description>\n";
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
                // Excerpt feeds only need indexed metadata. Avoid parsing every
                // content file just to discard all but the newest few items.
                $items = $config['full_content']
                    ? $repository->published($type)
                    : $repository->publishedMeta($type);

                foreach ($items as $item) {
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

            $items = $config['full_content']
                ? $repository->published($type)
                : $repository->publishedMeta($type);

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

    },

    'commands' => [
        [
            'name' => 'feed:stats',
            'description' => 'Show RSS feed statistics',
            'handler' => function (array $args, $output, \Ava\Application $app) {
                $repository = $app->repository();
                $types = $repository->types();
                $baseUrl = rtrim($app->config('site.base_url', ''), '/');

                // Get feed config
                $config = array_merge([
                    'items_per_feed' => 20,
                    'full_content' => false,
                    'types' => null,
                ], $app->config('feed', []));

                $output->header('RSS Feed Statistics');
                
                $tableData = [];
                $totalItems = 0;
                $combinedCount = 0;

                foreach ($types as $type) {
                    // Skip if types filter is set and this type isn't included
                    if ($config['types'] !== null && !in_array($type, $config['types'])) {
                        continue;
                    }

                    // Use publishedMeta() for CLI stats - no file I/O needed
                    $items = $repository->publishedMeta($type);
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
                    $output->warning('No content available for feeds.');
                    return 0;
                }

                // Display table with colors
                $output->writeln('');
                $headers = ['Content Type', 'Total Items', 'In Feed', 'Feed URL'];
                $rows = array_map(fn($d) => [
                    $output->primary($d['type']),
                    (string)$d['total'],
                    $output->green((string)$d['in_feed']),
                    $output->cyan($d['file']),
                ], $tableData);
                $output->table($headers, $rows);

                $output->writeln('');
                $output->info("Items per feed: " . $output->bold((string)$config['items_per_feed']));
                $output->info("Content mode: " . ($config['full_content'] ? $output->green('Full HTML') : $output->yellow('Excerpt only')));
                $output->info("Main feed: " . $output->primary("{$baseUrl}/feed.xml"));
                $output->writeln('');

                return 0;
            },
        ],
    ],
];
