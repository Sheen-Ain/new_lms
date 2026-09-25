<?php

namespace App\Core;

/**
 * Csrf — per-session token protection for every state-changing request.
 */
class Csrf
{
    public static function token()
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Hidden input helper for classic forms. */
    public static function field()
    {
        return '<input type="hidden" name="' . CSRF_FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function fromRequest()
    {
        if (isset($_POST[CSRF_FIELD])) {
            return (string) $_POST[CSRF_FIELD];
        }
        if (isset($_SERVER[CSRF_HEADER])) {
            return (string) $_SERVER[CSRF_HEADER];
        }
        if (isset($_GET[CSRF_FIELD])) {
            return (string) $_GET[CSRF_FIELD];
        }
        return '';
    }

    public static function check()
    {
        $sent = self::fromRequest();
        $known = isset($_SESSION['_csrf']) ? (string) $_SESSION['_csrf'] : '';
        if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
            Logger::warning('CSRF validation failed for ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-'));
            return false;
        }
        return true;
    }

    /** Guard used by controllers: aborts with 419 when the token is wrong. */
    public static function verify()
    {
        if (self::check()) {
            return true;
        }
        if (Request::wantsJson()) {
            Response::json(['status' => 'error', 'message' => 'Your session expired. Please refresh the page.'], 419);
        }
        Session::flash('error', 'Security token mismatch. Please try again.');
        Response::back();
        exit;
    }
}
