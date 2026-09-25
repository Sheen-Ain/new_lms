<?php
// ============================================================
// DATABASE — loads master config then connects
// Schema is managed via lms_db.sql — import once via phpMyAdmin.
// ============================================================
require_once __DIR__ . '/config.php';

// Disable mysqli strict exception mode
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');

// Force MySQL session timezone to Pakistan (UTC+5)
// Fixes all NOW(), CURDATE(), last_seen comparisons on shared hosting
// where the MySQL server may be on a different timezone.
$conn->query("SET time_zone = '+05:00'");