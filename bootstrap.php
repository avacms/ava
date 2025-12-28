<?php

declare(strict_types=1);

/**
 * Ava CMS Bootstrap
 *
 * Loads composer autoload, configuration, and initializes core services.
 * This file is shared by both the web front controller and CLI.
 */

// Ava version (CalVer: YY.0M.MICRO - e.g., 25.12.1 = first release of Dec 2025)
define('AVA_VERSION', '25.12.1');

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

// Initialize the application
Ava\Application::init($config);
