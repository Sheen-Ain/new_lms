<?php

namespace App\Core;

/**
 * Controller — base class for every HTTP controller.
 *
 * Provides view rendering, JSON envelopes, validation, flash messaging,
 * pagination input parsing and the current user.
 */
abstract class Controller
{
    /** @var array<string,mixed> data shared with the view */
    protected $viewData = [];

    public function __construct()
    {
        $this->viewData['currentUser'] = Auth::user();
        $this->viewData['activeRole'] = Auth::role();
        $this->viewData['csrfToken'] = Csrf::token();
        $this->viewData['flashMessages'] = Session::pullFlash();
        $this->viewData['errors'] = Session::errors();
        $this->viewData['oldInput'] = Session::oldInput();
        $this->viewData['appName'] = APP_NAME;
        $this->viewData['appVersion'] = APP_VERSION;
    }

    /* ── Identity shortcuts ────────────────────────────────────── */

    protected function user()
    {
        return Auth::user();
    }

    protected function userId()
    {
        return Auth::id();
    }

    protected function role()
    {
        return Auth::role();
    }

    protected function isAdmin()
    {
        return Auth::isAdmin();
    }

    protected function isTeacher()
    {
        return Auth::isTeacher();
    }

    protected function isStudent()
    {
        return Auth::isStudent();
    }

    /* ── Output ────────────────────────────────────────────────── */

    protected function view($view, array $data = [], $layout = 'layouts/portal')
    {
        View::render($view, array_merge($this->viewData, $data), $layout);
    }

    protected function publicView($view, array $data = [], $layout = 'layouts/public')
    {
        View::render($view, array_merge($this->viewData, $data), $layout);
    }

    protected function partial($view, array $data = [])
    {
        return View::partial($view, array_merge($this->viewData, $data));
    }

    protected function json($data, $statusCode = 200)
    {
        Response::json($data, $statusCode);
    }

    protected function success($message = 'OK', $data = [])
    {
        Response::success($message, $data);
    }

    protected function error($message = 'Something went wrong.', $data = [], $statusCode = 200)
    {
        Response::error($message, $data, $statusCode);
    }

    protected function redirect($path, $query = [])
    {
        Response::redirect(is_string($path) && (strpos($path, 'http') === 0 || strpos($path, '/index.php') !== false)
            ? $path
            : url($path, $query));
    }

    protected function back()
    {
        Response::back();
    }

    /* ── Security ──────────────────────────────────────────────── */

    /** Abort unless the request carries a valid CSRF token. */
    protected function verifyCsrf()
    {
        Csrf::verify();
    }

    /* ── Request helpers ───────────────────────────────────────── */

    protected function input($key = null, $default = null)
    {
        return Request::input($key, $default);
    }

    protected function string($key, $default = '')
    {
        return Request::string($key, $default);
    }

    protected function int($key, $default = 0)
    {
        return Request::int($key, $default);
    }

    protected function float($key, $default = 0.0)
    {
        return Request::float($key, $default);
    }

    protected function bool($key, $default = false)
    {
        return Request::bool($key, $default);
    }

    protected function file($key)
    {
        return Request::file($key);
    }

    protected function isPost()
    {
        return Request::isPost();
    }

    /** Validated pagination state from the request. */
    protected function pagination($defaultPerPage = PER_PAGE)
    {
        $page = max(1, Request::int('page', 1));
        $perPage = Request::int('per_page', $defaultPerPage);
        $perPage = max(5, min(PER_PAGE_MAX, $perPage));
        return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    /* ── Validation ────────────────────────────────────────────── */

    /**
     * Validate the request body.
     * @return Validator
     */
    protected function validate(array $rules, array $labels = [])
    {
        return Validator::make(Request::input(), $rules, $labels);
    }

    /**
     * Fail an AJAX request when validation fails; returns the Validator
     * for the caller to continue when it passes.
     */
    protected function validateOrFail(array $rules, array $labels = [])
    {
        $validator = $this->validate($rules, $labels);
        if ($validator->fails()) {
            Response::invalid($validator->errors(), $validator->firstMessage());
        }
        return $validator;
    }

    /** Classic form path: flash input + errors and bounce back. */
    protected function validateOrReturn(array $rules, array $labels = [], $redirectTo = null)
    {
        $validator = $this->validate($rules, $labels);
        if ($validator->fails()) {
            Session::flashInput(Request::input());
            Session::flashErrors($validator->errors());
            Session::flash('error', $validator->firstMessage());
            $this->redirect($redirectTo !== null ? $redirectTo : Request::path());
        }
        return $validator;
    }

    /* ── Flash ─────────────────────────────────────────────────── */

    protected function flash($type, $message)
    {
        Session::flash($type, $message);
    }

    /** Guard used by screens that need a record to exist. */
    protected function notFound($message = 'The requested record could not be found.')
    {
        if (Request::wantsJson()) {
            Response::json(['status' => 'error', 'message' => $message], 404);
        }
        http_response_code(404);
        View::render('errors/404', array_merge($this->viewData, ['pageTitle' => 'Not found', 'message' => $message]));
    }
}
