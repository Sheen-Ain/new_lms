<?php

namespace App\Core;

/**
 * Response — outbound helpers shared by web and AJAX controllers.
 */
class Response
{
    /**
     * Standard JSON envelope used by every AJAX endpoint:
     *   { "status": "success|error", "message": "...", "data": {...} }
     */
    public static function json($payload, $statusCode = 200)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            http_response_code($statusCode);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($message = 'OK', $data = [], $statusCode = 200)
    {
        self::json(['status' => 'success', 'message' => $message, 'data' => $data], $statusCode);
    }

    public static function error($message = 'Something went wrong.', $data = [], $statusCode = 200)
    {
        self::json(['status' => 'error', 'message' => $message, 'data' => $data], $statusCode);
    }

    /** Validation failures: { errors: { field: [messages] } }. */
    public static function invalid(array $errors, $message = 'Please correct the highlighted fields.')
    {
        self::json(['status' => 'error', 'message' => $message, 'data' => ['errors' => $errors]], 422);
    }

    public static function redirect($url, $statusCode = 302)
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $statusCode);
        } else {
            echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        }
        exit;
    }

    /** Send the user back where they came from. */
    public static function back($fallback = '/')
    {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if ($referer && strpos($referer, '//') !== false) {
            $host = parse_url($referer, PHP_URL_HOST);
            $self = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            if ($host && $self && strcasecmp($host, $self) === 0) {
                self::redirect($referer);
            }
        }
        self::redirect(url($fallback));
    }

    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    public static function csv($filename, array $rows, array $headings = [])
    {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store');
        }
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Excel-friendly BOM
        if ($headings) {
            fputcsv($out, $headings);
        }
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    /**
     * Stream a file for download with traversal protection handled by caller.
     */
    public static function download($absolutePath, $downloadName, $mime = 'application/octet-stream')
    {
        if (!is_file($absolutePath)) {
            http_response_code(404);
            exit('File not found.');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . filesize($absolutePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($absolutePath);
        exit;
    }
}
