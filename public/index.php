<?php

declare(strict_types=1);

/**
 * Ava CMS Front Controller
 *
 * All requests route through here. The bootstrap handles loading,
 * the router handles matching, the renderer handles output.
 * 
 * Performance: Cached pages are served before full boot for minimal TTFB.
 */

define('AVA_START', microtime(true));
define('AVA_ROOT', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────────────
// ULTRA-FAST PATH: Serve cached HTML with minimal PHP overhead
// ─────────────────────────────────────────────────────────────────────────────
// This runs BEFORE composer autoload for maximum speed (~0.5ms).
// Only applies to: GET requests, no query params, webpage_cache enabled.

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    // Quick config load (avoid full bootstrap)
    $configPath = AVA_ROOT . '/app/config/ava.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        
        if (!empty($config['webpage_cache']['enabled'])) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
            
            // Skip admin paths
            $adminPath = $config['admin']['path'] ?? '/admin';
            if (!str_starts_with($path, $adminPath)) {
                // Skip if query params present (except UTM)
                $query = $_GET;
                unset($query['utm_source'], $query['utm_medium'], $query['utm_campaign'], $query['utm_term'], $query['utm_content']);
                
                if (empty($query)) {
                    // Check exclusion patterns
                    $excluded = false;
                    foreach ($config['webpage_cache']['exclude'] ?? [] as $pattern) {
                        $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';
                        if (preg_match($regex, $path)) {
                            $excluded = true;
                            break;
                        }
                    }
                    
                    if (!$excluded) {
                        // Build cache file path
                        $storagePath = AVA_ROOT . '/' . ($config['paths']['storage'] ?? 'storage');
                        $hash = md5($path);
                        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', trim($path, '/'));
                        $safeName = substr($safeName ?: 'index', 0, 50);
                        $cacheFile = $storagePath . '/cache/pages/' . $safeName . '_' . substr($hash, 0, 8) . '.html';
                        
                        // Check cache file exists and TTL (single stat call)
                        $mtime = @filemtime($cacheFile);
                        if ($mtime !== false) {
                            $ttl = $config['webpage_cache']['ttl'] ?? null;
                            $age = time() - $mtime;
                            
                            if ($ttl === null || $age <= $ttl) {
                                // Check conditional GET (If-Modified-Since)
                                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                                    $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                                    if ($ifModifiedSince >= $mtime) {
                                        header('HTTP/1.1 304 Not Modified');
                                        header('X-Page-Cache: HIT');
                                        header('X-Fast-Path: ultra');
                                        header('X-Content-Type-Options: nosniff');
                                        header('X-Frame-Options: SAMEORIGIN');
                                        header('Referrer-Policy: strict-origin-when-cross-origin');

                                        $securityHeaders = $config['security']['headers'] ?? [];
                                        if (!empty($securityHeaders['content_security_policy'])) {
                                            header('Content-Security-Policy: ' . $securityHeaders['content_security_policy']);
                                        }
                                        if (!empty($securityHeaders['permissions_policy'])) {
                                            header('Permissions-Policy: ' . $securityHeaders['permissions_policy']);
                                        }
                                        if (!empty($securityHeaders['cross_origin_opener_policy'])) {
                                            header('Cross-Origin-Opener-Policy: ' . $securityHeaders['cross_origin_opener_policy']);
                                        }
                                        if (!empty($securityHeaders['cross_origin_resource_policy'])) {
                                            header('Cross-Origin-Resource-Policy: ' . $securityHeaders['cross_origin_resource_policy']);
                                        }
                                        if (!empty($securityHeaders['strict_transport_security'])) {
                                            $isSecure = (($_SERVER['HTTPS'] ?? 'off') !== 'off') || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
                                            if ($isSecure) {
                                                header('Strict-Transport-Security: ' . $securityHeaders['strict_transport_security']);
                                            }
                                        }
                                        exit;
                                    }
                                }

                                // Serve cached file directly!
                                header('Content-Type: text/html; charset=utf-8');
                                header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $mtime));
                                header('X-Page-Cache: HIT');
                                header('X-Fast-Path: ultra');
                                header('X-Cache-Age: ' . $age);

                                // Apply baseline security headers (match Response::send defaults)
                                header('X-Content-Type-Options: nosniff');
                                header('X-Frame-Options: SAMEORIGIN');
                                header('Referrer-Policy: strict-origin-when-cross-origin');

                                // Apply configured public security headers
                                $securityHeaders = $config['security']['headers'] ?? [];
                                if (!empty($securityHeaders['content_security_policy'])) {
                                    header('Content-Security-Policy: ' . $securityHeaders['content_security_policy']);
                                }
                                if (!empty($securityHeaders['permissions_policy'])) {
                                    header('Permissions-Policy: ' . $securityHeaders['permissions_policy']);
                                }
                                if (!empty($securityHeaders['cross_origin_opener_policy'])) {
                                    header('Cross-Origin-Opener-Policy: ' . $securityHeaders['cross_origin_opener_policy']);
                                }
                                if (!empty($securityHeaders['cross_origin_resource_policy'])) {
                                    header('Cross-Origin-Resource-Policy: ' . $securityHeaders['cross_origin_resource_policy']);
                                }
                                if (!empty($securityHeaders['strict_transport_security'])) {
                                    $isSecure = (($_SERVER['HTTPS'] ?? 'off') !== 'off') || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
                                    if ($isSecure) {
                                        header('Strict-Transport-Security: ' . $securityHeaders['strict_transport_security']);
                                    }
                                }
                                readfile($cacheFile);
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// STANDARD PATH: Full application boot
// ─────────────────────────────────────────────────────────────────────────────

$app = require AVA_ROOT . '/bootstrap.php';

// Fast path: Try to serve a cached page without full boot
// This is a fallback if ultra-fast path didn't match (shouldn't happen often)
$request = Ava\Http\Request::capture();
$cached = $app->tryCachedResponse($request);
if ($cached !== null) {
    $cached->withHeader('X-Fast-Path', 'standard')->send();
    exit;
}

// Full path: Boot the application and handle the request
$app->boot();
$response = $app->handle($request);
$response->send();
