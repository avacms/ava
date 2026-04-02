<?php

declare(strict_types=1);

/**
 * Ava Redirects Plugin
 *
 * Manage custom URL redirects and status responses.
 * 
 * Features:
 * - Add/remove custom redirects via admin UI
 * - Supports 301/302 redirects and status-only responses (410, 418, 451, etc.)
 * - Redirects stored in storage/redirects.json
 * - High priority routing (checked before content)
 *
 * @package Ava\Plugins\Redirects
 */

use Ava\Application;
use Ava\Http\Request;
use Ava\Http\Response;
use Ava\Plugins\Hooks;

/**
 * Status codes supported by this plugin.
 * Codes with 'redirect' => true require a destination URL.
 */
if (!defined('REDIRECT_STATUS_CODES'))
define('REDIRECT_STATUS_CODES', [
    301 => ['label' => 'Moved Permanently', 'redirect' => true, 'description' => 'SEO-friendly permanent redirect. Browsers cache this.'],
    302 => ['label' => 'Found (Temporary)', 'redirect' => true, 'description' => 'Temporary redirect. Not cached by browsers.'],
    307 => ['label' => 'Temporary Redirect', 'redirect' => true, 'description' => 'Like 302, but preserves request method (POST stays POST).'],
    308 => ['label' => 'Permanent Redirect', 'redirect' => true, 'description' => 'Like 301, but preserves request method.'],
    410 => ['label' => 'Gone', 'redirect' => false, 'description' => 'Resource permanently deleted. Search engines will de-index.'],
    418 => ['label' => "I'm a Teapot", 'redirect' => false, 'description' => 'The server refuses to brew coffee because it is a teapot. ☕'],
    451 => ['label' => 'Unavailable For Legal Reasons', 'redirect' => false, 'description' => 'Blocked due to legal demands (DMCA, court order, etc.).'],
    503 => ['label' => 'Service Unavailable', 'redirect' => false, 'description' => 'Temporarily down for maintenance.'],
]);

return [
    'name' => 'Redirects',
    'version' => '1.0.0',
    'description' => 'Manage custom URL redirects',
    'author' => 'Ava CMS',

    'boot' => function (Application $app) {
        $router = $app->router();
        $storagePath = $app->configPath('storage');
        $redirectsFile = $storagePath . '/redirects.json';

        // Load redirects with error handling
        $loadRedirects = function () use ($redirectsFile): array|string {
            if (!file_exists($redirectsFile)) {
                return [];
            }
            $contents = file_get_contents($redirectsFile);
            $data = json_decode($contents, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return 'JSON Error: ' . json_last_error_msg() . '. Check storage/redirects.json for syntax errors.';
            }
            
            return is_array($data) ? $data : [];
        };

        // Save redirects with file locking for concurrent request safety
        $saveRedirects = function (array $redirects) use ($redirectsFile): void {
            file_put_contents($redirectsFile, json_encode($redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        };

        // Validate/sanitize redirect targets to prevent unsafe schemes.
        $sanitizeRedirectTarget = function (string $to): ?string {
            $to = trim($to);

            if ($to === '') {
                return '';
            }

            // Block dangerous schemes
            if (preg_match('/^\s*(javascript|data|vbscript):/i', $to)) {
                return null;
            }

            // Allow internal absolute paths
            if (str_starts_with($to, '/')) {
                return $to;
            }

            // Allow http(s) only for external redirects
            $parts = parse_url($to);
            if ($parts === false || !isset($parts['scheme'])) {
                return null;
            }

            $scheme = strtolower($parts['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            return $to;
        };

        // Register redirects with router via hook (runs early in routing)
        Hooks::addFilter('router.before_match', function ($match, Request $request) use ($loadRedirects, $sanitizeRedirectTarget) {
            if ($match !== null) {
                return $match; // Already matched
            }

            $redirects = $loadRedirects();
            
            // Skip if there was a JSON error
            if (is_string($redirects)) {
                return null;
            }

            $path = '/' . trim($request->path(), '/');

            foreach ($redirects as $redirect) {
                $from = $redirect['from'] ?? '';
                if ($from === $path) {
                    $code = (int) ($redirect['code'] ?? 301);
                    $codeInfo = REDIRECT_STATUS_CODES[$code] ?? ['redirect' => true];
                    
                    // Check if this is a true redirect or a status-only response
                    if ($codeInfo['redirect']) {
                        $target = $sanitizeRedirectTarget((string) ($redirect['to'] ?? '/'));
                        if ($target === null) {
                            return null;
                        }
                        return new \Ava\Routing\RouteMatch(
                            type: 'redirect',
                            redirectUrl: $target === '' ? '/' : $target,
                            redirectCode: $code
                        );
                    } else {
                        // Return a status-only response
                        $label = $codeInfo['label'];
                        $body = $redirect['body'] ?? "<h1>{$code} {$label}</h1>";
                        return Response::html($body, $code);
                    }
                }
            }

            return null;
        }, 5); // Priority 5 = run early
    },

    'commands' => [
        [
            'name' => 'redirects:list',
            'description' => 'List all redirects',
            'handler' => function (array $args, $cli, \Ava\Application $app) {
                $storagePath = $app->configPath('storage');
                $redirectsFile = $storagePath . '/redirects.json';

                $cli->header('Configured Redirects');

                if (!file_exists($redirectsFile)) {
                    $cli->info('No redirects configured yet.');
                    $cli->writeln('');
                    return 0;
                }

                $contents = file_get_contents($redirectsFile);
                $redirects = json_decode($contents, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $cli->error('JSON parse error: ' . json_last_error_msg());
                    return 1;
                }

                if (empty($redirects)) {
                    $cli->info('No redirects configured.');
                    $cli->writeln('');
                    return 0;
                }

                // Build table
                $cli->writeln('');
                $headers = ['From', 'To', 'Code', 'Type'];
                $rows = [];

                foreach ($redirects as $redirect) {
                    $code = (int) ($redirect['code'] ?? 301);
                    $codeInfo = REDIRECT_STATUS_CODES[$code] ?? ['label' => 'Unknown', 'redirect' => true];
                    
                    $rows[] = [
                        $cli->primary($redirect['from'] ?? ''),
                        $redirect['to'] ?? ($codeInfo['redirect'] ? '' : $cli->dim('-')),
                        $cli->cyan((string) $code),
                        $codeInfo['label'],
                    ];
                }

                $cli->table($headers, $rows);
                $cli->writeln('');
                $cli->info('Total: ' . count($redirects) . ' redirect' . (count($redirects) !== 1 ? 's' : ''));
                $cli->writeln('');

                return 0;
            },
        ],
        [
            'name' => 'redirects:add',
            'description' => 'Add a redirect',
            'handler' => function (array $args, $cli, \Ava\Application $app) {
                $storagePath = $app->configPath('storage');
                $redirectsFile = $storagePath . '/redirects.json';

                // Parse arguments: redirects:add <from> <to> [code]
                $from = $args[0] ?? null;
                $to = $args[1] ?? null;
                $code = isset($args[2]) ? (int) $args[2] : 301;

                if (!$from) {
                    $cli->header('Add Redirect');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Usage:') . ' ./ava redirects:add <from> <to> [code]');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Arguments:'));
                    $cli->writeln('    ' . $cli->primary('from') . '    Source path (e.g., /old-page)');
                    $cli->writeln('    ' . $cli->primary('to') . '      Destination URL (e.g., /new-page or https://...)');
                    $cli->writeln('    ' . $cli->primary('code') . '    HTTP status code (default: 301)');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Supported codes:'));
                    foreach (REDIRECT_STATUS_CODES as $statusCode => $info) {
                        $codeStr = $cli->cyan((string) $statusCode);
                        $label = $info['label'];
                        $type = $info['redirect'] ? $cli->green('redirect') : $cli->yellow('status');
                        $cli->writeln("    {$codeStr}  {$label} [{$type}]");
                    }
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Examples:'));
                    $cli->writeln('    ' . $cli->dim('./ava redirects:add /old-page /new-page'));
                    $cli->writeln('    ' . $cli->dim('./ava redirects:add /legacy https://example.com 302'));
                    $cli->writeln('    ' . $cli->dim('./ava redirects:add /deleted "" 410'));
                    $cli->writeln('');
                    return 0;
                }

                // Validate code
                if (!isset(REDIRECT_STATUS_CODES[$code])) {
                    $cli->error("Invalid status code: {$code}");
                    $cli->info('Supported codes: ' . implode(', ', array_keys(REDIRECT_STATUS_CODES)));
                    return 1;
                }

                $codeInfo = REDIRECT_STATUS_CODES[$code];

                // For redirect codes, destination is required
                if ($codeInfo['redirect'] && empty($to)) {
                    $cli->error("Destination URL required for {$code} redirects.");
                    return 1;
                }

                // Normalize from path
                $from = '/' . ltrim($from, '/');

                // Load existing redirects
                $redirects = [];
                if (file_exists($redirectsFile)) {
                    $contents = file_get_contents($redirectsFile);
                    $redirects = json_decode($contents, true) ?? [];
                }

                // Check for duplicate
                foreach ($redirects as $r) {
                    if (($r['from'] ?? '') === $from) {
                        $cli->error("Redirect already exists for: {$from}");
                        $cli->info('Use ' . $cli->primary('redirects:remove') . ' first to replace it.');
                        return 1;
                    }
                }

                // Add new redirect
                $redirects[] = [
                    'from' => $from,
                    'to' => $to ?: '',
                    'code' => $code,
                    'created' => date('Y-m-d H:i:s'),
                ];

                // Save
                file_put_contents($redirectsFile, json_encode($redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $cli->success("Added redirect: {$from} → " . ($to ?: "[{$code} {$codeInfo['label']}]"));
                return 0;
            },
        ],
        [
            'name' => 'redirects:remove',
            'description' => 'Remove a redirect',
            'handler' => function (array $args, $cli, \Ava\Application $app) {
                $storagePath = $app->configPath('storage');
                $redirectsFile = $storagePath . '/redirects.json';

                $from = $args[0] ?? null;

                if (!$from) {
                    $cli->header('Remove Redirect');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Usage:') . ' ./ava redirects:remove <from>');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Arguments:'));
                    $cli->writeln('    ' . $cli->primary('from') . '    Source path to remove (e.g., /old-page)');
                    $cli->writeln('');
                    $cli->writeln('  ' . $cli->bold('Example:'));
                    $cli->writeln('    ' . $cli->dim('./ava redirects:remove /old-page'));
                    $cli->writeln('');
                    return 0;
                }

                if (!file_exists($redirectsFile)) {
                    $cli->error('No redirects configured.');
                    return 1;
                }

                $contents = file_get_contents($redirectsFile);
                $redirects = json_decode($contents, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $cli->error('JSON parse error: ' . json_last_error_msg());
                    return 1;
                }

                // Normalize from path
                $from = '/' . ltrim($from, '/');

                // Find and remove
                $found = false;
                $filtered = [];
                foreach ($redirects as $r) {
                    if (($r['from'] ?? '') === $from) {
                        $found = true;
                    } else {
                        $filtered[] = $r;
                    }
                }

                if (!$found) {
                    $cli->error("No redirect found for: {$from}");
                    return 1;
                }

                // Save
                file_put_contents($redirectsFile, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $cli->success("Removed redirect: {$from}");
                return 0;
            },
        ],
    ],
];
