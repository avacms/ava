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

        $router->addRoute('/sitemap.xsl', function (Request $request) {
            $xsl = <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
<xsl:output method="html" encoding="UTF-8"/>
<xsl:template match="/">
<html><head><meta name="viewport" content="width=device-width, initial-scale=1"/><title>Sitemap</title>
<style>body{margin:0;background:#f5f4ef;color:#20211f;font:16px/1.5 Georgia,serif}main{max-width:960px;margin:auto;padding:48px 24px}h1{font-size:2.4rem;margin:0 0 8px}p{color:#62645f;margin:0 0 8px}p:last-of-type{margin-bottom:24px}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #deded8}th{background:#20211f;color:#fff;font:600 13px sans-serif;text-transform:uppercase}a{color:#08756a;overflow-wrap:anywhere}td:last-child{white-space:nowrap}@media(max-width:600px){main{padding:28px 14px}th,td{padding:10px 8px}td:last-child{white-space:normal}}</style>
</head><body><main><h1>Sitemap</h1><p>This is an optimised XML sitemap meant to be processed quickly by search engines like <a href="https://www.google.com/">Google</a> or <a href="https://www.bing.com/">Bing</a>.</p><p>You can find more information on XML sitemaps at <a href="https://www.sitemaps.org/">sitemaps.org</a>.</p><table><thead><tr><th>URL</th><th>Last modified</th></tr></thead><tbody>
<xsl:for-each select="s:urlset/s:url | s:sitemapindex/s:sitemap"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="s:lastmod"/></td></tr></xsl:for-each>
</tbody></table></main></body></html>
</xsl:template></xsl:stylesheet>
XSL;
            return new Response($xsl, 200, ['Content-Type' => 'text/xsl; charset=utf-8']);
        });

        // Load content types
        $contentTypesFile = $app->path('app/config/content_types.php');
        $contentTypes = file_exists($contentTypesFile) ? require $contentTypesFile : [];

        // Sitemap index route
        $router->addRoute('/sitemap.xml', function (Request $request) use ($app, $baseUrl) {
            $repository = $app->repository();
            $types = $repository->types();

            $safeBaseUrl = htmlspecialchars($baseUrl, ENT_XML1, 'UTF-8');
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>' . "\n";
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
                    $safeType = htmlspecialchars($type, ENT_XML1, 'UTF-8');
                    $xml .= "  <sitemap>\n";
                    $xml .= "    <loc>{$safeBaseUrl}/sitemap-{$safeType}.xml</loc>\n";
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
                $trailingSlash = $app->config('routing.trailing_slash', false);

                $safeBaseUrl = htmlspecialchars($baseUrl, ENT_XML1, 'UTF-8');
                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $xml .= '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>' . "\n";
                $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

                foreach ($items as $item) {
                    // Skip noindex items
                    if ($item->noindex()) {
                        continue;
                    }

                    // Find URL for this item from reverse routes (O(1))
                    $key = $type . ':' . ($item->get('content_key') ?? $item->slug());
                    $url = $reverseRoutes[$key] ?? $reverseRoutes[$type . ':' . $item->slug()] ?? null;

                    // Fallback: generate from pattern
                    if ($url === null) {
                        $typeConfig = $contentTypes[$type] ?? [];
                        $urlConfig = $typeConfig['url'] ?? [];
                        $pattern = $urlConfig['pattern'] ?? '/' . $type . '/{slug}';
                        $url = str_replace('{slug}', $item->slug(), $pattern);
                    }

                    if ($url !== '/') {
                        if ($trailingSlash && !str_ends_with($url, '/')) {
                            $url .= '/';
                        } elseif (!$trailingSlash && str_ends_with($url, '/')) {
                            $url = rtrim($url, '/');
                        }
                    }

                    $safeUrl = htmlspecialchars($url, ENT_XML1, 'UTF-8');
                    $xml .= "  <url>\n";
                    $xml .= "    <loc>{$safeBaseUrl}{$safeUrl}</loc>\n";
                    
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

        // Add sitemap to robots.txt on content rebuild
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
            'handler' => function (array $args, $output, \Ava\Application $app) {
                $repository = $app->repository();
                $types = $repository->types();
                $baseUrl = rtrim($app->config('site.base_url', ''), '/');

                $output->header('Sitemap Statistics');
                
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
                    $output->warning('No content types with published content found.');
                    return 0;
                }

                // Display table with colors
                $output->writeln('');
                $headers = ['Content Type', 'Indexable', 'Noindex', 'Sitemap File'];
                $rows = array_map(fn($d) => [
                    $output->primary($d['type']),
                    $output->green((string)$d['indexable']),
                    $d['noindex'] > 0 ? $output->yellow((string)$d['noindex']) : $output->dim('0'),
                    $output->cyan($d['file']),
                ], $tableData);
                $output->table($headers, $rows);

                $output->writeln('');
                $output->info("Total URLs in sitemap: " . $output->bold((string)$totalUrls));
                $output->info("Main sitemap: " . $output->primary("{$baseUrl}/sitemap.xml"));
                $output->writeln('');

                return 0;
            },
        ],
    ],
];
