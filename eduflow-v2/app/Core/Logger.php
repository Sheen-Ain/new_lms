<?php

namespace App\Core;

/**
 * Logger — application level logging (errors + activity trail).
 */
class Logger
{
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';

    public static function error($message)
    {
        self::write(self::LEVEL_ERROR, $message);
    }

    public static function warning($message)
    {
        self::write(self::LEVEL_WARNING, $message);
    }

    public static function info($message)
    {
        self::write(self::LEVEL_INFO, $message);
    }

    public static function critical($message)
    {
        self::write('critical', $message);
    }

    public static function exception(\Throwable $e)
    {
        self::write('exception', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }

    /** Raw writing — never throws, logging must not break the request. */
    private static function write($level, $message)
    {
        $line = sprintf(
            "[%s] %s: %s | ip=%s | uri=%s | uid=%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::scrub($message),
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-',
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-',
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '-',
            PHP_EOL
        );

        $file = LOG_PATH . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Never log credentials or tokens. */
    private static function scrub($message)
    {
        $message = (string) $message;
        foreach (['password', 'pass', 'token', 'secret'] as $needle) {
            $message = preg_replace('/' . $needle . '\s*[=:]\s*\S+/i', $needle . '=***', $message);
        }
        return $message;
    }

    public static function recentErrors($lines = 50)
    {
        $file = LOG_PATH . '/app-' . date('Y-m-d') . '.log';
        if (!is_file($file)) {
            return [];
        }
        $content = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$content) {
            return [];
        }
        return array_slice($content, -$lines);
    }
}
