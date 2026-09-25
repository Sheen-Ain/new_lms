<?php
/**
 * EduFlow V2 — single web entry point.
 *
 * Everything is routed through this file, so the application works on
 * shared/free hosting with no .htaccess rewrite rules:
 *
 *   /eduflow-v2/public/index.php?r=/student/tests
 *
 * PATH_INFO (/index.php/student/tests) and plain REQUEST_URI paths are
 * also accepted when the host provides them.
 */

// PHP version guard (the app targets 7.2+ but runs best on 8.x).
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('EduFlow requires PHP 7.2 or newer. Current version: ' . PHP_VERSION);
}

$root = dirname(__DIR__);

/**
 * Constants must exist before the framework boots (config.php is
 * idempotent, so loading it here is safe).
 */
define('EDUFLOW_ROOT', $root);
require_once $root . '/config/config.php';
require_once $root . '/app/Core/App.php';

\App\Core\App::run();
