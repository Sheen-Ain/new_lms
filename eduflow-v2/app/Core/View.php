<?php

namespace App\Core;

/**
 * View — server-side rendering with layouts and partials.
 *
 * Views are plain PHP files under resources/views. A view is first
 * rendered to a string, then injected into its layout as $content.
 */
class View
{
    /** @var array variables available to every view */
    private static $shared = [];

    public static function share($key, $value)
    {
        self::$shared[$key] = $value;
    }

    public static function shared($key = null)
    {
        if ($key === null) {
            return self::$shared;
        }
        return isset(self::$shared[$key]) ? self::$shared[$key] : null;
    }

    /** Render a view file without a layout. */
    public static function partial($view, array $data = [])
    {
        $file = self::resolve($view);
        if (!is_file($file)) {
            Logger::error('View not found: ' . $view);
            return APP_ENV === 'production' ? '' : '<pre>View not found: ' . htmlspecialchars($view) . '</pre>';
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Render a view inside a layout.
     *
     * @param string $view   e.g. 'student/dashboard'
     * @param string $layout e.g. 'layouts/portal'
     */
    public static function render($view, array $data = [], $layout = 'layouts/portal')
    {
        $data['content'] = self::partial($view, $data);
        if ($layout === null) {
            echo $data['content'];
            return;
        }
        $data['layout'] = $layout;
        echo self::partial($layout, $data);
    }

    private static function resolve($view)
    {
        $relative = str_replace(['.', '\\'], '/', trim($view, '/'));
        return VIEW_PATH . '/' . $relative . '.php';
    }

    /** Convenience for controllers that want the HTML as a string. */
    public static function capture($view, array $data = [], $layout = null)
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }
}
