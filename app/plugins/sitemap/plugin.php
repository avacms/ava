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

        // Sitemaps may list at most 50,000 URLs, so large types are split
        // into numbered files: /sitemap-post.xml, /sitemap-post-2.xml, ...
        $maxUrls = max(1, (int) ($config['max_urls'] ?? 50_000));
        $contentTypes = $app->contentTypes();

        /**
         * Indexable URLs of one type, with their last-modified dates.
         *
         * @return list<array{url: string, lastmod: ?\DateTimeImmutable}>
         */
        $entries = function (string $type) use ($app): array {
            $router = $app->router();
            $entries = [];

            foreach ($app->repository()->eachMeta($type) as $item) {
                if (!$item->isPublished() || $item->noindex()) {
                    continue;
                }
                $url = $router->urlFor($type, $item->contentKey());
                if ($url !== null) {
                    $entries[] = ['url' => $url, 'lastmod' => $item->updated()];
                }
            }

            return $entries;
        };

        $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>' . "\n";
        $safeBaseUrl = htmlspecialchars($baseUrl, ENT_XML1, 'UTF-8');

        // Sitemap index route
        $router->addRoute('/sitemap.xml', function (Request $request) use ($app, $entries, $maxUrls, $xmlHeader, $safeBaseUrl) {
            $xml = $xmlHeader . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($app->repository()->types() as $type) {
                $typeEntries = $entries($type);
                if ($typeEntries === []) {
                    continue;
                }

                $safeType = htmlspecialchars($type, ENT_XML1, 'UTF-8');
                foreach (array_chunk($typeEntries, $maxUrls) as $index => $chunk) {
                    $lastMod = null;
                    foreach ($chunk as $entry) {
                        if ($entry['lastmod'] !== null && ($lastMod === null || $entry['lastmod'] > $lastMod)) {
                            $lastMod = $entry['lastmod'];
                        }
                    }

                    $suffix = $index === 0 ? '' : '-' . ($index + 1);
                    $xml .= "  <sitemap>\n";
                    $xml .= "    <loc>{$safeBaseUrl}/sitemap-{$safeType}{$suffix}.xml</loc>\n";
                    if ($lastMod !== null) {
                        $xml .= "    <lastmod>" . $lastMod->format('Y-m-d') . "</lastmod>\n";
                    }
                    $xml .= "  </sitemap>\n";
                }
            }

            $xml .= '</sitemapindex>';

            return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        });

        $typeSitemap = function (string $type, int $page) use ($entries, $maxUrls, $xmlHeader, $safeBaseUrl): ?Response {
            $chunk = array_slice($entries($type), ($page - 1) * $maxUrls, $maxUrls);
            if ($chunk === [] && $page > 1) {
                return null;
            }

            $xml = $xmlHeader . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($chunk as $entry) {
                $xml .= "  <url>\n";
                $xml .= "    <loc>{$safeBaseUrl}" . htmlspecialchars($entry['url'], ENT_XML1, 'UTF-8') . "</loc>\n";
                if ($entry['lastmod'] !== null) {
                    $xml .= "    <lastmod>" . $entry['lastmod']->format('Y-m-d') . "</lastmod>\n";
                }
                $xml .= "  </url>\n";
            }
            $xml .= '</urlset>';

            return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        };

        // Per-type sitemaps: /sitemap-{type}.xml, then /sitemap-{type}-{n}.xml
        foreach (array_keys($contentTypes) as $type) {
            $router->addRoute("/sitemap-{$type}.xml", fn(Request $request) => $typeSitemap((string) $type, 1));
        }
        $router->addRoute('/sitemap-{name}.xml', function (Request $request, array $params) use ($contentTypes, $typeSitemap) {
            if (preg_match('/^(.+)-([2-9]|[1-9]\d+)$/', $params['name'] ?? '', $matches) !== 1
                || !isset($contentTypes[$matches[1]])
            ) {
                return null;
            }

            return $typeSitemap($matches[1], (int) $matches[2]);
        });

        // Served only when public/robots.txt doesn't exist; the web server
        // answers with the file otherwise.
        $router->addRoute('/robots.txt', fn() => Response::text(
            "User-agent: *\nAllow: /\n\nSitemap: {$baseUrl}/sitemap.xml\n"
        ));

        // Keep a custom public/robots.txt pointing at the sitemap. This runs
        // after CLI rebuilds only, so web requests never write to public/.
        Hooks::addAction('cli.rebuild', function (Application $app) use ($baseUrl) {
            $robotsFile = $app->path('public/robots.txt');
            $sitemapLine = 'Sitemap: ' . $baseUrl . '/sitemap.xml';

            if (!file_exists($robotsFile)) {
                return;
            }

            $lines = explode("\n", (string) file_get_contents($robotsFile));
            if (in_array($sitemapLine, array_map('trim', $lines), true)) {
                return;
            }

            // Replace only a line for this plugin's sitemap (e.g. after base_url
            // changed); other Sitemap: lines, such as a news sitemap, are kept.
            foreach ($lines as $i => $line) {
                if (preg_match('#^\s*Sitemap:\s*\S+/sitemap\.xml\s*$#i', $line) === 1) {
                    $lines[$i] = $sitemapLine;
                    file_put_contents($robotsFile, implode("\n", $lines));
                    echo "  \033[32m✔\033[0m Updated Sitemap URL in robots.txt\n";
                    return;
                }
            }

            $content = implode("\n", $lines);
            $separator = str_ends_with($content, "\n") ? '' : "\n";
            file_put_contents($robotsFile, $content . $separator . $sitemapLine . "\n");
            echo "  \033[32m✔\033[0m Added Sitemap to robots.txt\n";
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
