<?php

namespace App\Core;

/**
 * Router — front-controller routing that works without .htaccess.
 *
 * Routes are declared as "METHOD /path" => "Controller@action" and the
 * request path arrives via ?r=/path, PATH_INFO or REQUEST_URI (whichever
 * the host provides). Middleware strings may be attached to any route.
 */
class Router
{
    /** @var array<string,array> */
    private $routes = [];

    private $notFound = null;

    /** Register a route: get('/path', 'Controller@action', ['auth']). */
    public function add($method, $path, $handler, array $middleware = [])
    {
        $key = strtoupper($method) . ' ' . $this->normalise($path);
        $this->routes[$key] = ['handler' => $handler, 'middleware' => $middleware];
        return $this;
    }

    public function get($path, $handler, array $middleware = [])
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, array $middleware = [])
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function any($path, $handler, array $middleware = [])
    {
        $this->get($path, $handler, $middleware);
        return $this->post($path, $handler, $middleware);
    }

    /** Add many routes at once: [[METHOD, path, handler, middleware], ...]. */
    public function group(array $routes)
    {
        foreach ($routes as $definition) {
            $this->add(
                $definition[0],
                $definition[1],
                $definition[2],
                isset($definition[3]) ? $definition[3] : []
            );
        }
        return $this;
    }

    /** Handler used when nothing matches. */
    public function fallback($handler)
    {
        $this->notFound = $handler;
        return $this;
    }

    private function normalise($path)
    {
        $path = '/' . trim((string) $path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** Resolve and run the current request. */
    public function dispatch()
    {
        $method = Request::method();
        $path = $this->normalise(Request::path());

        $match = $this->match($method, $path);

        if ($match === null && $method === 'HEAD') {
            $match = $this->match('GET', $path);
        }

        if ($match === null) {
            $this->handleNotFound();
            return;
        }

        foreach ($match['middleware'] as $middleware) {
            Middleware::run($middleware);
        }

        $this->invoke($match['handler'], $match['params']);
    }

    private function match($method, $path)
    {
        $exact = $method . ' ' . $path;
        if (isset($this->routes[$exact])) {
            return [
                'handler' => $this->routes[$exact]['handler'],
                'middleware' => $this->routes[$exact]['middleware'],
                'params' => [],
            ];
        }

        foreach ($this->routes as $key => $definition) {
            list($routeMethod, $routePath) = explode(' ', $key, 2);
            if ($routeMethod !== $method || strpos($routePath, '{') === false) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
            if (preg_match('#^' . $pattern . '$#', $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $name => $value) {
                    if (!is_int($name)) {
                        $params[$name] = $value;
                    }
                }
                return [
                    'handler' => $definition['handler'],
                    'middleware' => $definition['middleware'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function handleNotFound()
    {
        http_response_code(404);
        if ($this->notFound !== null) {
            $this->invoke($this->notFound, []);
            return;
        }
        View::render('errors/404', ['pageTitle' => 'Page not found'], 'layouts/public');
    }

    /** Call Controller@action with any route parameters. */
    private function invoke($handler, array $params = [])
    {
        if (strpos($handler, '@') === false) {
            $this->fail('Invalid route handler definition: ' . $handler);
            return;
        }

        list($controllerName, $action) = explode('@', $handler, 2);
        $class = strpos($controllerName, '\\') !== false
            ? $controllerName
            : 'App\\Controllers\\' . $controllerName;

        if (!class_exists($class)) {
            Logger::error('Controller not found: ' . $class);
            $this->fail('Controller not found: ' . $class);
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $action)) {
            Logger::error('Action not found: ' . $class . '::' . $action);
            $this->fail('Action not found: ' . $class . '::' . $action);
            return;
        }

        call_user_func_array([$controller, $action], array_values($params));
    }

    private function fail($message)
    {
        Logger::error($message);
        http_response_code(500);
        View::render('errors/500', [
            'pageTitle' => 'Server error',
            'detail' => APP_ENV === 'production' ? null : $message,
        ], 'layouts/public');
    }

    /** Route table (used by the admin system screen). */
    public function table()
    {
        $out = [];
        foreach ($this->routes as $key => $definition) {
            $out[] = [
                'route' => $key,
                'handler' => $definition['handler'],
                'middleware' => $definition['middleware'],
            ];
        }
        return $out;
    }
}

