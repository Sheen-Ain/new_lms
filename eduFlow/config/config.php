<?php
// ============================================================
//  EDUFLOW LMS — MASTER CONFIGURATION
// ============================================================

define('APP_ENV', 'development');
define('APP_NAME',    'EduFlow');

// define('APP_URL',     'https://eduflow.my-board.org');   // NO trailing slash
// define('BASE_PATH',   '');

define('APP_URL',     'https://localhost/edu_flow');   // NO trailing slash
define('BASE_PATH',   '/edu_flow/eduFlow');
define('APP_TIMEZONE','Asia/Karachi');

// ── Database ──────────────────────────────────────────────────
// define('DB_HOST',    'sql103.byethost7.com');
// define('DB_USER',    'b7_41397792');
// define('DB_PASS',    'bhutto786');
// define('DB_NAME',    'b7_41397792_lms_db');
// define('DB_CHARSET', 'utf8mb4');

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'edu_flow');
define('DB_CHARSET', 'utf8mb4');

// ── Pusher (used for chat only) ───────────────────────────────
define('PUSHER_APP_ID',  '2127654');
define('PUSHER_KEY',     '76171b10f0e4e942efd3');
define('PUSHER_SECRET',  '4857f1beb776a23dcf1c');
define('PUSHER_CLUSTER', 'ap2');

// ── Mail (SMTP) ───────────────────────────────────────────────
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'shariqbhutto5@gmail.com');
define('MAIL_PASS',      'tgzk xbbn fmad nsfq');
define('MAIL_FROM',      'shariqbhutto5@gmail.com');
define('MAIL_FROM_NAME', 'EduFlow LMS');

// ── Uploads ───────────────────────────────────────────────────
define('UPLOAD_MAX_MB', 20);
define('OTP_EXPIRY_MINUTES', 15);
define('TOKEN_EXPIRY_HOURS', 24);

// ── Allowed File Extensions (edit this list to add/remove types globally) ─────
// Documents
// Code / Web
// Data / Archives
// Media
define('ALLOWED_FILE_EXTENSIONS', [
    // ── Documents ────────────────────────────────────
    'pdf', 'doc', 'docx', 'odt',
    'ppt', 'pptx', 'odp',
    'xls', 'xlsx', 'ods', 'csv',
    'txt', 'rtf', 'md',

    // ── Archives ─────────────────────────────────────
    'zip', 'rar', '7z', 'tar', 'gz',

    // ── Images ───────────────────────────────────────
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',

    // ── Audio / Video ─────────────────────────────────
    'mp4', 'mkv', 'avi', 'mov', 'webm',
    'mp3', 'wav', 'ogg', 'flac',

    // ── Web / Frontend ────────────────────────────────
    'html', 'htm', 'css', 'js', 'ts', 'jsx', 'tsx', 'vue',
    'json', 'xml', 'yaml', 'yml',

    // ── General / Scripting ───────────────────────────
    'php', 'sql',

    // ── Python ───────────────────────────────────────
    'py', 'pyc', 'pyw', 'ipynb',

    // ── Laravel / PHP Ecosystem ───────────────────────
    'env', 'blade',                  // .env, .blade.php handled as 'blade'

    // ── Other Popular Languages ───────────────────────
    'java', 'class', 'jar',          // Java
    'c', 'cpp', 'h', 'hpp',          // C / C++
    'cs',                            // C#
    'rb',                            // Ruby
    'go',                            // Go
    'rs',                            // Rust
    'kt',                            // Kotlin
    'swift',                         // Swift
    'sh', 'bash', 'bat', 'ps1',      // Shell / Scripts
    'r',                             // R
    'dart',                          // Dart / Flutter
    'lua',                           // Lua
    'pl',                            // Perl
    'scala',                         // Scala
]);

// ── Admin seed ────────────────────────────────────────────────
// define('ADMIN_EMAIL',    'admin@lms.com');
// define('ADMIN_PASSWORD', 'Admin@1234');

// ════════════════════════════════════════════════════════════════
//  JITSI AS A SERVICE (JaaS) — 8x8.vc
//  Free tier: 10,000 participant-minutes/month. No time limit.
//  Dashboard: https://jaas.8x8.vc
// ════════════════════════════════════════════════════════════════
define('JAAS_APP_ID',     'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7');
define('JAAS_API_KEY_ID', 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/63d24b');
define('JAAS_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCrmFRo38DPhrh0
SU5ht1v8kT8Twb+lYaE/0pJtFOHcjHFiI4s9Azo47ClgBp/sYuKt46/UU/qmZg0p
WaSoOOoBzNobKJtFLxYcaCbbMcdiqyLMCEHyC8qjTB9QrIebDqP3NSQhPZghkk1s
ybwq/UvuIuR2mYL4EIdfGM+2mlJxBfRv0/vozWfhxV6Or51Ez3b2jWlzLx1i466i
w072rwmxCr4mnl1Z0jgEDjsfp6F0Fw9QzAbxlvTSaZ4mWNkeeoap0lDZC8VTC98D
ISERCzIuAN41ElT+bJzgl3RTCub43sqorj+x4xh82GNSNN+sTDs9dHWGL1qkp25V
STXO8lvZAgMBAAECggEALmQSff/wKqrrd1TSQgzGa7QA76Bz5YxNgem1+JOqtGur
w7KEVExpEzaVwQZJeikJy6VAxhCmNRJmAIXhxDEO4sm1NZ52y+989NUbnCsLEpvd
3ndlDMEvWZKc7LyYNM1yesT9LZdvZ7QcBotLufuc6Za5WW8LP3GIh6c3kNCL9U4L
R8qCdrLPy18uWYmxA9Ywd+Hcin+XONShp93KtS/mzyMFGgKLQuiNkEJ2Ol34WZln
nXlfGurS5ad5UD7r0Bao/2R4vvAH4qI5xeKB7HvSUQZOxBlj0ftAh+wKh3ZBJGy+
BtMZ17wc992rgRpRHeUPYv9Q2z6qyg0vubb8u9mX+QKBgQDfQhL1yfVR1GhVenTH
5N6Kp1pcgHlxAlcgNs/fagcaD6KEvR1VO/AgxM5JpmtIWxBziFlCsvDRwQ0d2y1M
6+HDuOAgUwle6/j4cifN+IVopys+K4UwB5KyrNupS/mb/FbDXg8FGu108Ye8mHV/
D9ZhCtzgYc7rQ5sYKOxc30rs5wKBgQDEwqKe0xYQPcCITYhFGRjEkyHDkq62kl6w
g12Ye4pCO4Sn15t7rzWu+W8RRwJ05y6G4K3EJ2NFiQTmXMtC7cHAOV65rY7xpgKg
K9Rs+SxAkh/OpH7mNW1P2GkGayCKuUoRAde2k0pMIW019Smockv9n+3I8lVsvTOc
cCyNwzSZPwKBgHL3P3Q6b42X57I8wO4+uSqFS07fCapcHimEkD7oBogxDOt1xykh
GGKHdgMPI6e63RnhWLW0F7arxulc+FLoFPYIucFrgSPUN/0YK88w7uIZU3dMSeWV
wMEpqmPfr8XXh4ZLZUinuSfDSLahe7/Wk/qc8WjKdRdJVRB34l9gzOB5AoGAANmC
UENDFiDeIviKvRmlpLup6qlIfdtV81ct4UmvSCfvo7XnovoXtkC3fRCcbxrMdaKk
vXMaF6PG1KPT8N8L9iOJSC36rwpzenOWAD53NXQsFP1a2u2iIjUiBvgRdOfl7Prg
DpbGPFvsl84ONv7/WwIEydhaDBUpEuTdHGOaZ4sCgYAVY6Ugv4os41s34GOdAAab
wCUcMTU1274GvViGjiZJkviziPjZVrwjTxIDU73GHxVrY1scsuFrIqWavPwZIS+k
AV29uV31ocYcZCNyf6ZCVd0x7hDPPZr4py8QTo6jUILp++sK2WmcM/TQ677FTB1c
OZC+lQYAMD3VnHeZM1Slnw==
-----END PRIVATE KEY-----');

// ── Environment ───────────────────────────────────────────────
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

date_default_timezone_set(APP_TIMEZONE);