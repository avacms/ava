<?php

declare(strict_types=1);

define('AVA_START', microtime(true));
define('AVA_ROOT', dirname(__DIR__));

$app = require AVA_ROOT . '/bootstrap.php';
$app->enableDeferredTasks();
$request = Ava\Http\Request::capture();
$response = $app->handle($request);

// Render timing is diagnostic output, so it follows the same opt-in switch as
// the generator comment rather than being published to every visitor.
if ($app->config('generator_comment', false)) {
    $response = $response->withHeader(
        'X-Render-Time',
        round((microtime(true) - AVA_START) * 1000, 1) . 'ms'
    );
}

$response->send();
$app->terminate();
