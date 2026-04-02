<?php

declare(strict_types=1);

namespace Ava\Rendering;

/**
 * Error Pages
 *
 * Provides fallback error pages for when themes don't define them
 * or when errors occur before the theme is loaded.
 */
final class ErrorPages
{
    /**
     * Common styles used by all error pages.
     */
    private static function styles(): string
    {
        return <<<'CSS'
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            line-height: 1.6;
        }
        .error {
            text-align: center;
            max-width: 420px;
        }
        .error-code {
            font-size: 3.5rem;
            font-weight: 700;
            color: #8b5cf6;
            line-height: 1;
            margin-bottom: 0.75rem;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .error-message {
            color: #64748b;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }
        .error-message code {
            background: #f1f5f9;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            color: #334155;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: #1e293b;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .btn:hover { background: #334155; }
        .error-hint {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .error-hint a { color: #8b5cf6; text-decoration: none; }
        .error-hint a:hover { text-decoration: underline; }
CSS;
    }

    /**
     * Render a 404 Not Found page.
     */
    public static function render404(?string $requestedPath = null): string
    {
        $styles = self::styles();
        $pathInfo = $requestedPath 
            ? '<p class="error-message">The page <code>' . htmlspecialchars($requestedPath) . '</code> could not be found.</p>' 
            : '<p class="error-message">The page you requested could not be found.</p>';
        $showHomeLink = $requestedPath !== '/' && $requestedPath !== '';
        $homeLink = $showHomeLink ? '<a href="/" class="btn">Go Home</a>' : '';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Page Not Found</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="error">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        {$pathInfo}
        {$homeLink}
        <div class="error-hint">
            Powered by <a href="https://ava.addy.zone/">Ava CMS</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 500 Internal Server Error page.
     * 
     * @param string|null $errorId Optional error ID for support reference
     * @param string|null $requestedPath The path that was requested (to hide Go Home on homepage)
     * @param bool $loggingEnabled Whether error logging is enabled
     */
    public static function render500(?string $errorId = null, ?string $requestedPath = null, bool $loggingEnabled = true): string
    {
        $styles = self::styles();
        $errorRef = $errorId 
            ? '<br>Reference: <code>' . htmlspecialchars($errorId) . '</code>' 
            : '';
        $showHomeLink = $requestedPath !== '/' && $requestedPath !== '';
        $homeLink = $showHomeLink ? '<a href="/" class="btn">Go Home</a>' : '';
        $logHint = $loggingEnabled
            ? 'Check <code>storage/logs/error.log</code> for details.'
            : 'Enable <code>debug.enabled</code> and <code>debug.log_errors</code> in config to log errors.';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Server Error</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="error">
        <div class="error-code">500</div>
        <h1 class="error-title">Something Went Wrong</h1>
        <p class="error-message">
            An unexpected error occurred.{$errorRef}
        </p>
        {$homeLink}
        <div class="error-hint">
            {$logHint}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render a 503 Service Unavailable page.
     */
    public static function render503(?string $message = null): string
    {
        $styles = self::styles();
        $messageText = $message 
            ? htmlspecialchars($message)
            : 'This site is temporarily unavailable. Please try again later.';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Temporarily Unavailable</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="error">
        <div class="error-code">503</div>
        <h1 class="error-title">Temporarily Unavailable</h1>
        <p class="error-message">{$messageText}</p>
        <div class="error-hint">
            Powered by <a href="https://ava.addy.zone/">Ava CMS</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
