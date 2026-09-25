<?php
/**
 * EduFlow V2 — Master Configuration
 * ---------------------------------------------------------------
 * Single source of truth for environment, database, mail, realtime
 * and upload settings. Values may be overridden per-environment by
 * creating config/config.local.php which is loaded last.
 *
 * PHP 7.2 compatible on purpose (free/shared hosting targets).
 */

if (defined('EDUFLOW_CONFIG_LOADED')) {
    return;
}
define('EDUFLOW_CONFIG_LOADED', true);

/* ───────────────────────────────────────────────────────────────
   ENVIRONMENT
   ─────────────────────────────────────────────────────────────── */
define('APP_ENV', 'development');          // development | production
define('APP_NAME', 'EduFlow');
define('APP_TAGLINE', 'Learning & Assessment Platform');
define('APP_VERSION', '2.0.0');
define('APP_TIMEZONE', 'Asia/Karachi');

/* ───────────────────────────────────────────────────────────────
   DATABASE
   ─────────────────────────────────────────────────────────────── */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edu_flow');
define('DB_CHARSET', 'utf8mb4');
define('DB_TIMEZONE_OFFSET', '+05:00');

/* ───────────────────────────────────────────────────────────────
   PATHS
   ─────────────────────────────────────────────────────────────── */
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/resources/views');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/storage/logs');
define('CACHE_PATH', ROOT_PATH . '/storage/cache');

/* ───────────────────────────────────────────────────────────────
   URL / ROUTING  (no .htaccess required)
   ─────────────────────────────────────────────────────────────── */
define('SESSION_NAME', 'EDUFLOW_V2_SESSION');

/* ───────────────────────────────────────────────────────────────
   SECURITY
   ─────────────────────────────────────────────────────────────── */
define('BCRYPT_COST', 10);
define('SESSION_LIFETIME', 259200);        // idle timeout: 3 days
define('CSRF_FIELD', '_token');
define('CSRF_HEADER', 'HTTP_X_CSRF_TOKEN');
define('LOGIN_MAX_ATTEMPTS', 8);
define('LOGIN_WINDOW', 600);

/* ───────────────────────────────────────────────────────────────
   PAGINATION
   ─────────────────────────────────────────────────────────────── */
define('PER_PAGE', 10);
define('PER_PAGE_MAX', 100);

/* ───────────────────────────────────────────────────────────────
   UPLOADS
   ─────────────────────────────────────────────────────────────── */
define('UPLOAD_MAX_MB', 20);
define('AVATAR_MAX_MB', 5);
define('THUMB_MAX_MB', 5);
define('ALLOWED_FILE_EXTENSIONS', [
    // Documents
    'pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods',
    'csv', 'txt', 'rtf', 'md',
    // Archives
    'zip', 'rar', '7z', 'tar', 'gz',
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    // Audio / video
    'mp4', 'mkv', 'avi', 'mov', 'webm', 'mp3', 'wav', 'ogg', 'flac',
    // Web / frontend
    'html', 'htm', 'css', 'js', 'ts', 'jsx', 'tsx', 'vue', 'json', 'xml', 'yaml', 'yml',
    // Scripting / general
    'php', 'sql', 'py', 'pyw', 'ipynb', 'java', 'class', 'jar',
    'c', 'cpp', 'h', 'hpp', 'cs', 'rb', 'go', 'rs', 'kt', 'swift',
    'sh', 'bash', 'bat', 'ps1', 'r', 'dart', 'lua', 'pl', 'scala', 'env', 'blade',
]);

/* ───────────────────────────────────────────────────────────────
   EMAIL (SMTP). Leave MAIL_USER empty to disable sending — the app
   then surfaces dev fallbacks instead of failing hard.
   ─────────────────────────────────────────────────────────────── */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'shariqbhutto5@gmail.com');
define('MAIL_PASS', 'tgzk xbbn fmad nsfq');
define('MAIL_FROM', 'shariqbhutto5@gmail.com');
define('MAIL_FROM_NAME', 'EduFlow LMS');
define('MAIL_ENCRYPTION', 'tls');

/* ───────────────────────────────────────────────────────────────
   PASSWORD RESET / VERIFICATION
   ─────────────────────────────────────────────────────────────── */
define('OTP_EXPIRY_MINUTES', 15);
define('TOKEN_EXPIRY_HOURS', 24);

/* ───────────────────────────────────────────────────────────────
   BUSINESS RULES (preserved from V1)
   ─────────────────────────────────────────────────────────────── */
define('NEGATIVE_MARKING', 0.25);
define('DEFAULT_TEST_MINUTES', 30);
define('DEFAULT_TEST_QUESTIONS', 4);
define('CODE_PREVIEW_EXTENSIONS', ['php', 'py', 'js', 'ts', 'css', 'html', 'htm', 'sql',
    'json', 'xml', 'txt', 'md', 'c', 'cpp', 'h', 'hpp', 'cs', 'java', 'rb', 'go',
    'rs', 'kt', 'swift', 'sh', 'bash', 'bat', 'ps1', 'yml', 'yaml', 'env', 'r', 'lua', 'pl']);

/* ───────────────────────────────────────────────────────────────
   REALTIME — Pusher (chat) & Jitsi as a Service (live classes)
   ─────────────────────────────────────────────────────────────── */
define('PUSHER_APP_ID', '2127654');
define('PUSHER_KEY', '76171b10f0e4e942efd3');
define('PUSHER_SECRET', '4857f1beb776a23dcf1c');
define('PUSHER_CLUSTER', 'ap2');

define('JAAS_APP_ID', 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7');
define('JAAS_API_KEY_ID', 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/63d24b');
define('JAAS_PRIVATE_KEY_PATH', ROOT_PATH . '/storage/keys/jaas.pem');

/* ───────────────────────────────────────────────────────────────
   ERROR HANDLING
   ─────────────────────────────────────────────────────────────── */
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
ini_set('log_errors', '1');
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0755, true);
}
ini_set('error_log', LOG_PATH . '/php-error.log');

date_default_timezone_set(APP_TIMEZONE);

/* ───────────────────────────────────────────────────────────────
   PER-ENVIRONMENT OVERRIDES (optional, not committed)
   ─────────────────────────────────────────────────────────────── */
$eduflowLocalConfig = __DIR__ . '/config.local.php';
if (is_file($eduflowLocalConfig)) {
    require $eduflowLocalConfig;
}


