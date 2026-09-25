<?php

namespace App\Core;

/**
 * Request — normalised read-only access to the current HTTP request.
 */
class Request
{
    /** Path used by the router, without the application base path. */
    public static function path()
    {
        if (isset($_GET['r']) && $_GET['r'] !== '') {
            $path = '/' . trim((string) $_GET['r'], '/');
            return $path === '' ? '/' : $path;
        }

        if (!empty($_SERVER['PATH_INFO'])) {
            $path = '/' . trim($_SERVER['PATH_INFO'], '/');
            return $path === '/' ? '/' : $path;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        $scriptDir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
        if ($scriptDir !== '/' && $scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $path = '/' . trim($uri, '/');
        return $path === '/' ? '/' : $path;
    }

    public static function method()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public static function isPost()
    {
        return self::method() === 'POST';
    }

    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /** Accept header or XHR detected. */
    public static function wantsJson()
    {
        if (self::isAjax()) {
            return true;
        }
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        return strpos($accept, 'application/json') !== false;
    }

    /** Input from any source (POST wins over GET over JSON body). */
    public static function input($key = null, $default = null)
    {
        static $json = null;
        if ($json === null) {
            $json = [];
            if (self::method() !== 'GET'
                && (self::isAjax() || stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== false)) {
                $raw = file_get_contents('php://input');
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $json = $decoded;
                    }
                }
            }
        }

        if ($key === null) {
            return array_merge($_GET, $_POST, $json);
        }
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }
        return $default;
    }

    public static function string($key, $default = '')
    {
        $value = self::input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function int($key, $default = 0)
    {
        $value = self::input($key, $default);
        return is_scalar($value) ? (int) $value : $default;
    }

    public static function float($key, $default = 0.0)
    {
        $value = self::input($key, $default);
        return is_scalar($value) ? (float) $value : $default;
    }

    public static function bool($key, $default = false)
    {
        $value = self::input($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function array($key, $default = [])
    {
        $value = self::input($key, $default);
        return is_array($value) ? $value : $default;
    }

    public static function file($key)
    {
        return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null;
    }

    public static function ip()
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $value = explode(',', $_SERVER[$key]);
                $ip = trim($value[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function userAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    }

    /** Current URL including query string (used for redirect=after-login). */
    public static function fullUrl()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        return $uri;
    }
}
