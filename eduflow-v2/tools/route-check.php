<?php
/**
 * Developer utility: verifies that every route resolves to a real
 * controller method. Run with `php tools/route-check.php`.
 */

define('EDUFLOW_ROOT', dirname(__DIR__));
require EDUFLOW_ROOT . '/config/config.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$routes = require EDUFLOW_ROOT . '/routes/web.php';

$missing = [];
$resolved = 0;
$methods = [];
$paths = [];

foreach ($routes as $route) {
    $method = $route[0];
    $path = $route[1];
    $handler = $route[2];

    $key = $method . ' ' . $path;
    if (isset($paths[$key])) {
        $missing[] = "DUPLICATE route: $key";
    }
    $paths[$key] = true;

    list($controller, $action) = array_pad(explode('@', $handler, 2), 2, '');
    $class = 'App\\Controllers\\' . $controller;

    if (!class_exists($class)) {
        $missing[] = "MISSING CLASS  $class  ($key)";
        continue;
    }
    if (!method_exists($class, $action)) {
        $missing[] = "MISSING METHOD $class::$action  ($key)";
        continue;
    }
    $resolved++;
}

echo 'Routes defined : ' . count($routes) . PHP_EOL;
echo 'Resolved       : ' . $resolved . PHP_EOL;
echo 'Problems       : ' . count($missing) . PHP_EOL;

foreach ($missing as $line) {
    echo '  - ' . $line . PHP_EOL;
}

exit($missing ? 1 : 0);
