<?php

declare(strict_types=1);

/**
 * Ava CMS Front Controller
 *
 * All requests route through here. The bootstrap handles loading,
 * the router handles matching, the renderer handles output.
 */

define('AVA_START', microtime(true));
define('AVA_ROOT', dirname(__DIR__));

$app = require AVA_ROOT . '/bootstrap.php';

// Boot the application
$app->boot();

// Handle the request
$request = Ava\Http\Request::capture();
$response = $app->handle($request);
$response->send();
