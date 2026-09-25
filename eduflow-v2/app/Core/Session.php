<?php

namespace App\Core;

/**
 * Session — hardened session handling plus flash messaging.
 */
class Session
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        session_start();

        self::enforceIdleTimeout();
        self::harden();
    }

    /** Kill sessions that have been idle for too long. */
    private static function enforceIdleTimeout()
    {
        $now = time();
        $last = isset($_SESSION['_last_seen']) ? (int) $_SESSION['_last_seen'] : 0;

        if ($last && ($now - $last) > SESSION_LIFETIME) {
            self::destroy();
            self::start();
            self::flash('warning', 'Your session expired. Please sign in again.');
            return;
        }
        $_SESSION['_last_seen'] = $now;
    }

    /** Fingerprint the session so a stolen cookie is far less useful. */
    private static function harden()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $fingerprint = hash('sha256', $agent . '|' . substr(sha1(APP_NAME . SESSION_NAME), 0, 16));

        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = $fingerprint;
            return;
        }
        if (!hash_equals($_SESSION['_fp'], $fingerprint)) {
            self::destroy();
            self::start();
        }
    }

    public static function get($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function forget($key)
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /* ── Flash messages ────────────────────────────────────────── */

    public static function flash($type, $message)
    {
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Pull and clear all queued flash messages. */
    public static function pullFlash()
    {
        $messages = isset($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /** Remember form input so it can be re-filled after validation errors. */
    public static function flashInput(array $input)
    {
        unset($input[CSRF_FIELD], $input['password'], $input['new_password'],
            $input['confirm_password'], $input['current_password']);
        $_SESSION['_old_input'] = $input;
    }

    public static function oldInput()
    {
        $input = isset($_SESSION['_old_input']) ? $_SESSION['_old_input'] : [];
        unset($_SESSION['_old_input']);
        return $input;
    }

    public static function flashErrors(array $errors)
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function errors()
    {
        $errors = isset($_SESSION['_errors']) ? $_SESSION['_errors'] : [];
        unset($_SESSION['_errors']);
        return $errors;
    }
}
