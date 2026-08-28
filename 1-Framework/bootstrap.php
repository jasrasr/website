<?php

declare(strict_types=1);

/**
 * Primary framework entry point.
 *
 * Future projects should load this file once near the beginning of each
 * request. As framework services are implemented, this file will initialize
 * configuration, error handling, sessions, shared helpers, and enabled modules.
 */

if (defined('JASR_FRAMEWORK_LOADED')) {
    return;
}

define('JASR_FRAMEWORK_LOADED', true);
define('JASR_FRAMEWORK_ROOT', __DIR__);

$configFile = JASR_FRAMEWORK_ROOT . '/config/config.php';

if (is_file($configFile)) {
    $frameworkConfig = require $configFile;
} else {
    $frameworkConfig = require JASR_FRAMEWORK_ROOT . '/config/config.example.php';
}

if (!is_array($frameworkConfig)) {
    throw new RuntimeException('Framework configuration must return an array.');
}

// Services and optional modules will be initialized here as they are added.
