<?php

namespace App\Core;

/**
 * Middleware — named guards applied to routes.
 *
 *   guest    : only for signed-out visitors
 *   auth     : requires a signed-in user
 *   role:x   : requires the active role, e.g. role:admin
 *   roles:a,b: requires one of the listed roles
 *   ajax     : requests must look like AJAX calls
 *   csrf     : POST requests must carry a valid token
 */
class Middleware
{
    public static function run($name)
    {
        if (strpos($name, ':') !== false) {
            list($name, $arguments) = explode(':', $name, 2);
            $arguments = explode(',', $arguments);
        } else {
            $arguments = [];
        }

        switch ($name) {
            case 'auth':
                self::auth();
                break;

            case 'guest':
                self::guest();
                break;

            case 'role':
                self::role($arguments);
                break;

            case 'roles':
                self::role($arguments);
                break;

            case 'ajax':
                self::ajax();
                break;

            case 'csrf':
                self::csrf();
                break;

            case 'web':
            default:
                break;
        }
    }

    private static function auth()
    {
        if (Auth::check()) {
            return;
        }

        if (Request::wantsJson()) {
            Response::json(['status' => 'error', 'message' => 'Your session has expired. Please sign in again.'], 401);
        }

        $target = Request::fullUrl();
        Session::flash('warning', 'Please sign in to continue.');
        Response::redirect(url('/login', $target ? ['next' => $target] : []));
    }

    private static function guest()
    {
        if (!Auth::check()) {
            return;
        }
        Response::redirect(url(Auth::homeFor()));
    }

    private static function role(array $roles)
    {
        self::auth();

        $roles = array_filter(array_map('trim', $roles));
        if (!$roles) {
            return;
        }

        $user = Auth::user();
        if ($user && array_intersect($roles, $user['roles'])) {
            return;
        }

        Logger::warning('Role check failed. required=' . implode(',', $roles) . ' current=' . Auth::role());

        if (Request::wantsJson()) {
            Response::json(['status' => 'error', 'message' => 'You do not have access to this resource.'], 403);
        }

        Session::flash('error', 'You do not have permission to open that page.');
        Response::redirect(url(Auth::homeFor($user ? $user['current_role'] : null)));
    }

    private static function ajax()
    {
        if (Request::isAjax() || Request::wantsJson()) {
            return;
        }
        Response::json(['status' => 'error', 'message' => 'This endpoint only accepts AJAX requests.'], 400);
    }

    private static function csrf()
    {
        if (!Request::isPost()) {
            return;
        }
        Csrf::verify();
    }
}
