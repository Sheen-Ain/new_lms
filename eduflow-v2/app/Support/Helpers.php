<?php
/**
 * Global helper functions — available to controllers, models and views.
 * Kept intentionally small and dependency-free (PHP 7.2 safe).
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Support\Icons;

if (!function_exists('e')) {
    /** HTML-escape any value for safe output. */
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path')) {
    /**
     * URL of the application directory (auto-detected so the project can be
     * dropped into any sub-folder of any host without .htaccess).
     */
    function base_path($fresh = false)
    {
        static $base = null;
        if ($base !== null && !$fresh) {
            return $base;
        }

        $detected = '';
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            $detected = rtrim(dirname($script), '/');
        }
        if ($detected === '/' || $detected === '.') {
            $detected = '';
        }
        $base = $detected;
        return $base;
    }
}

if (!function_exists('url')) {
    /**
     * Build an application URL. No rewrite rules needed:
     *   url('/student/tests')  =>  /edu_flow/eduflow-v2/public/index.php?r=/student/tests
     * Pass $query to append parameters.
     */
    function url($path = '/', array $query = [])
    {
        $path = '/' . ltrim((string) $path, '/');
        $url = base_path() . '/index.php?r=' . rawurlencode($path);
        if ($query) {
            $url .= '&' . http_build_query($query);
        }
        return $url;
    }
}

if (!function_exists('asset')) {
    /** URL of a file inside public/assets (with cache-busting version). */
    function asset($path)
    {
        $path = ltrim((string) $path, '/');
        $url = base_path() . '/assets/' . $path;
        $file = PUBLIC_PATH . '/assets/' . $path;
        if (is_file($file)) {
            $url .= '?v=' . APP_VERSION . '.' . filemtime($file);
        }
        return $url;
    }
}

if (!function_exists('upload_url')) {
    /** Public URL of an uploaded file relative to the uploads root. */
    function upload_url($relative, $fallback = '')
    {
        $relative = ltrim((string) $relative, '/');
        if ($relative === '') {
            return $fallback ? asset($fallback) : '';
        }
        return base_path() . '/uploads/' . str_replace('\\', '/', $relative);
    }
}

if (!function_exists('redirect')) {
    function redirect($path, $status = 302)
    {
        \App\Core\Response::redirect(url($path), $status);
    }
}

if (!function_exists('back')) {
    function back()
    {
        \App\Core\Response::back();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return Csrf::field();
    }
}

if (!function_exists('icon')) {
    /** Inline SVG icon from the EduFlow icon set. */
    function icon($name, $size = 18, $class = '')
    {
        return Icons::render($name, $size, $class);
    }
}

if (!function_exists('user')) {
    function user()
    {
        return Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id()
    {
        return Auth::id();
    }
}

if (!function_exists('current_role')) {
    function current_role()
    {
        return Auth::role();
    }
}

if (!function_exists('flash')) {
    function flash($type, $message)
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('themes_initials')) {
    /** Initials avatar fallback. */
    function initials($name, $length = 2)
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($out) >= $length) {
                break;
            }
        }
        return $out !== '' ? $out : 'U';
    }
}

if (!function_exists('user_avatar')) {
    /** Avatar markup for any user row (falls back to initials). */
    function user_avatar(array $user, $size = 34, $class = '')
    {
        $name = isset($user['full_name']) ? $user['full_name'] : 'User';
        $picture = isset($user['profile_picture']) ? $user['profile_picture'] : '';
        $classes = 'avatar' . ($class ? ' ' . $class : '');
        $style = 'width:' . (int) $size . 'px;height:' . (int) $size . 'px;font-size:' . max(10, (int) round($size * 0.38)) . 'px;';

        if ($picture) {
            return '<span class="' . $classes . '" style="' . $style . '">'
                . '<img src="' . e(upload_url('profiles/' . $picture)) . '" alt="' . e($name) . '" loading="lazy">'
                . '</span>';
        }
        return '<span class="' . $classes . ' avatar-initials" style="' . $style . '">' . e(initials($name)) . '</span>';
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime)
    {
        if (!$datetime || $datetime === '0000-00-00 00:00:00') {
            return 'Never';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '—';
        }
        $diff = time() - $timestamp;
        if ($diff < 0) {
            return 'Just now';
        }
        if ($diff < 60) {
            return 'Just now';
        }
        $units = [
            ['year', 31536000],
            ['month', 2592000],
            ['week', 604800],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
        ];
        foreach ($units as $unit) {
            if ($diff >= $unit[1]) {
                $count = (int) floor($diff / $unit[1]);
                return $count . ' ' . $unit[0] . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'Just now';
    }
}

if (!function_exists('format_date')) {
    function format_date($datetime, $format = 'd M Y')
    {
        if (!$datetime) {
            return '—';
        }
        $timestamp = strtotime($datetime);
        return $timestamp ? date($format, $timestamp) : '—';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'd M Y, h:i A')
    {
        return format_date($datetime, $format);
    }
}

if (!function_exists('due_label')) {
    /**
     * Human deadline state used by assignments/feedback screens.
     * @return array{label:string,state:string}
     */
    function due_label($dueDate)
    {
        if (!$dueDate) {
            return ['label' => 'No deadline', 'state' => 'neutral'];
        }
        $timestamp = strtotime($dueDate);
        if ($timestamp === false) {
            return ['label' => 'No deadline', 'state' => 'neutral'];
        }
        $diff = $timestamp - time();
        if ($diff < 0) {
            $late = time() - $timestamp;
            if ($late < 86400) {
                return ['label' => 'Closed ' . floor($late / 3600) . 'h ago', 'state' => 'danger'];
            }
            return ['label' => 'Closed ' . floor($late / 86400) . 'd ago', 'state' => 'danger'];
        }
        if ($diff < 86400) {
            return ['label' => 'Due in ' . max(1, floor($diff / 3600)) . 'h', 'state' => 'warning'];
        }
        if ($diff < 259200) {
            return ['label' => 'Due in ' . floor($diff / 86400) . 'd', 'state' => 'warning'];
        }
        return ['label' => 'Due ' . date('d M, h:i A', $timestamp), 'state' => 'neutral'];
    }
}

if (!function_exists('file_size_human')) {
    function file_size_human($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = (int) floor(log($bytes, 1024));
        $index = min($index, count($units) - 1);
        $size = $bytes / pow(1024, $index);
        return round($size, $size >= 100 ? 0 : 2) . ' ' . $units[$index];
    }
}

if (!function_exists('file_ext')) {
    function file_ext($filename)
    {
        $ext = pathinfo((string) $filename, PATHINFO_EXTENSION);
        return strtolower($ext);
    }
}

if (!function_exists('padded_id')) {
    /** 5-digit display id (00012) used in upload folder names. */
    function padded_id($id)
    {
        return str_pad((string) (int) $id, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('slugify')) {
    function slugify($text, $fallback = 'item')
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        $text = trim($text, '_');
        $text = substr($text, 0, 80);
        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('format_cnic')) {
    function format_cnic($cnic)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $cnic);
        if (strlen($digits) !== 13) {
            return $cnic ?: '—';
        }
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1);
    }
}

if (!function_exists('band_for')) {
    /** Grading bands shared by the entry test and LMS tests. */
    function band_for($percentage)
    {
        $percentage = (float) $percentage;
        if ($percentage >= 90) {
            return ['label' => 'Superb', 'tone' => 'success'];
        }
        if ($percentage >= 75) {
            return ['label' => 'Excellent', 'tone' => 'primary'];
        }
        if ($percentage >= 60) {
            return ['label' => 'Good', 'tone' => 'info'];
        }
        if ($percentage >= 40) {
            return ['label' => 'Average', 'tone' => 'warning'];
        }
        return ['label' => 'Weak', 'tone' => 'danger'];
    }
}

if (!function_exists('status_tone')) {
    /** Map a status string to a visual tone used by badges. */
    function status_tone($status)
    {
        $map = [
            'active' => 'success', 'inactive' => 'muted', 'archived' => 'muted',
            'completed' => 'info', 'draft' => 'muted', 'published' => 'success',
            'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger',
            'test_submitted' => 'info', 'submitted' => 'info', 'graded' => 'success',
            'returned' => 'warning', 'in_progress' => 'warning', 'reviewed' => 'success',
            'waiting' => 'muted', 'ended' => 'muted', 'normal' => 'muted',
            'important' => 'warning', 'urgent' => 'danger',
        ];
        return isset($map[$status]) ? $map[$status] : 'muted';
    }
}

