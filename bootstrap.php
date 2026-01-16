<?php

declare(strict_types=1);

/**
 * Ava CMS Bootstrap
 *
 * Loads composer autoload, configuration, and initializes core services.
 * This file is shared by both the web front controller and CLI.
 */

// Prevent direct web access if accidentally exposed
if (php_sapi_name() !== 'cli' && !defined('AVA_START')) {
    http_response_code(403);
    exit('Direct access denied.');
}

// Ava version (SemVer: MAJOR.MINOR.PATCH)
define('AVA_VERSION', '1.1.0');

// Ensure we have a root constant
if (!defined('AVA_ROOT')) {
    define('AVA_ROOT', __DIR__);
}

// Composer autoload
$autoloadPath = AVA_ROOT . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found. Run: composer install\n");
}
require $autoloadPath;

// Load main configuration
$configPath = AVA_ROOT . '/app/config/ava.php';
if (!file_exists($configPath)) {
    die("Configuration file not found: app/config/ava.php\n");
}

$config = require $configPath;

// Configure error handling based on debug settings
$debug = $config['debug'] ?? [];
$debugEnabled = $debug['enabled'] ?? false;
$displayErrors = $debugEnabled && ($debug['display_errors'] ?? false);
$logErrors = $debugEnabled && ($debug['log_errors'] ?? true);
$errorLevel = $debug['level'] ?? 'errors';

// Set error reporting level
$errorReporting = match ($errorLevel) {
    'all' => E_ALL,
    'errors' => E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR,
    'none' => 0,
    default => E_ALL & ~E_NOTICE & ~E_DEPRECATED,
};
error_reporting($errorReporting);

// Display errors (only in debug mode with display_errors enabled)
ini_set('display_errors', ($debugEnabled && $displayErrors) ? '1' : '0');
ini_set('display_startup_errors', ($debugEnabled && $displayErrors) ? '1' : '0');

// Log errors to file
if ($logErrors) {
    ini_set('log_errors', '1');
    ini_set('error_log', AVA_ROOT . '/storage/logs/error.log');
}

// Custom error handler for enhanced logging (only if debugging or logging enabled)
if ($debugEnabled || $logErrors) {
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($logErrors) {
        // Skip errors suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $type = match ($errno) {
            E_ERROR, E_USER_ERROR => 'ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'UNKNOWN',
        };
        
        $message = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $type,
            $errstr,
            str_replace(AVA_ROOT . '/', '', $errfile),
            $errline
        );
        
        if ($logErrors) {
            $logFile = AVA_ROOT . '/storage/logs/error.log';
            @file_put_contents($logFile, $message . "\n", FILE_APPEND | LOCK_EX);
        }
        
        // Let PHP's default handler run if display_errors is on
        return false;
    });
}

// Exception handler - always registered to show custom error pages
set_exception_handler(function (\Throwable $e) use ($debugEnabled, $displayErrors, $logErrors) {
    $message = sprintf(
        "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        str_replace(AVA_ROOT . '/', '', $e->getFile()),
        $e->getLine(),
        $e->getTraceAsString()
    );
    
    if ($logErrors) {
        $logFile = AVA_ROOT . '/storage/logs/error.log';
        @file_put_contents($logFile, $message . "\n\n", FILE_APPEND | LOCK_EX);
    }
    
    if ($debugEnabled && $displayErrors) {
        echo "<pre style='background:#1a1a2e;color:#eee;padding:20px;font-family:monospace;'>";
        echo "<strong style='color:#ff6b6b;'>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "\n\n";
        echo "<strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "\n\n";
        echo "<strong>Stack Trace:</strong>\n" . htmlspecialchars($e->getTraceAsString());
        echo "</pre>";
    } else {
        // Show styled error page in production
        http_response_code(500);
        // Generate a short error reference ID from timestamp
        $errorId = $logErrors ? date('ymd-His') . '-' . substr(md5($e->getMessage() . $e->getFile()), 0, 6) : null;
        $requestedPath = $_SERVER['REQUEST_URI'] ?? null;
        if ($requestedPath) {
            $requestedPath = parse_url($requestedPath, PHP_URL_PATH) ?: $requestedPath;
        }
        echo \Ava\Rendering\ErrorPages::render500($errorId, $requestedPath, $logErrors);
    }
    exit(1);
});

// Initialize the application and return it
return new Ava\Application($config);
