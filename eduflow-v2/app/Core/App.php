<?php

namespace App\Core;

/**
 * App — bootstrap: autoloading, error handling, session, shared data,
 * route loading and dispatch. One entry point (public/index.php) calls run().
 */
class App
{
    /** @var Router|null */
    private static $router = null;

    public static function boot()
    {
        require_once ROOT_PATH . '/config/config.php';
        require_once APP_PATH . '/Support/Helpers.php';

        self::registerAutoloader();
        self::registerErrorHandling();
        Session::start();

        View::share('currentUser', Auth::user());
        View::share('activeRole', Auth::role());
    }

    public static function run()
    {
        self::boot();

        $router = self::router();
        $router->dispatch();
    }

    public static function router()
    {
        if (self::$router instanceof Router) {
            return self::$router;
        }

        $router = new Router(base_path());
        $routeFile = ROOT_PATH . '/routes/web.php';
        if (is_file($routeFile)) {
            $definition = require $routeFile;
            if (is_array($definition)) {
                $router->group($definition);
                if (isset($definition['__fallback'])) {
                    $router->fallback($definition['__fallback']);
                }
            }
        }

        self::$router = $router;
        return $router;
    }

    /* ── Autoloading ───────────────────────────────────────────── */

    private static function registerAutoloader()
    {
        spl_autoload_register(function ($class) {
            $prefixes = [
                'App\\' => APP_PATH . '/',
            ];

            foreach ($prefixes as $prefix => $baseDir) {
                if (strpos($class, $prefix) !== 0) {
                    continue;
                }
                $relative = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        });
    }

    /* ── Error handling ────────────────────────────────────────── */

    private static function registerErrorHandling()
    {
        set_exception_handler(function ($exception) {
            Logger::exception($exception);
            if (!headers_sent()) {
                http_response_code(500);
            }

            if (Request::wantsJson()) {
                echo json_encode([
                    'status' => 'error',
                    'message' => APP_ENV === 'production'
                        ? 'An unexpected server error occurred.'
                        : $exception->getMessage(),
                ]);
                return;
            }

            View::render('errors/500', [
                'pageTitle' => 'Server error',
                'detail' => APP_ENV === 'production' ? null : $exception->getMessage(),
            ], 'layouts/public');
        });

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::critical($error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
            }
        });
    }
}
