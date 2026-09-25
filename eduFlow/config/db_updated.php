<?php
// ============================================================
// DATABASE CONFIGURATION
// ============================================================

// Disable mysqli strict exception mode (PHP 8.1+ enables it by default)
// so query failures return false instead of throwing uncaught exceptions
mysqli_report(MYSQLI_REPORT_OFF);

date_default_timezone_set('Asia/Karachi');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lms_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');

// ============================================================
// DATABASE SETUP — Run once to create all tables
// ============================================================
function setupDatabase($conn)
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL UNIQUE,
            `label` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(191) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `gender` ENUM('male','female','other') DEFAULT NULL,
            `profile_picture` VARCHAR(255) DEFAULT NULL,
            `bio` TEXT DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `is_verified` TINYINT(1) DEFAULT 0,
            `status` ENUM('active','inactive') DEFAULT 'inactive',
            `is_online` TINYINT(1) DEFAULT 0,
            `last_seen` DATETIME DEFAULT NULL,
            `theme_preference` ENUM('light','dark','system') DEFAULT 'system',
            `current_role` VARCHAR(50) DEFAULT 'student',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `user_roles` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `role_id` INT UNSIGNED NOT NULL,
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `assigned_by` INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `courses` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `thumbnail` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('active','inactive','archived') DEFAULT 'active',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `batches` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `max_students` INT UNSIGNED DEFAULT 50,
            `status` ENUM('active','inactive','completed') DEFAULT 'active',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `batch_teachers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `batch_id` INT UNSIGNED NOT NULL,
            `teacher_id` INT UNSIGNED NOT NULL,
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `assigned_by` INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY `unique_batch_teacher` (`batch_id`, `teacher_id`),
            FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `batch_students` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `batch_id` INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `enrolled_by` INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY `unique_batch_student` (`batch_id`, `student_id`),
            FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `topics` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `batch_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `sort_order` INT UNSIGNED DEFAULT 0,
            `status` ENUM('active','inactive') DEFAULT 'active',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `topic_files` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `topic_id` INT UNSIGNED NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` BIGINT UNSIGNED DEFAULT 0,
            `file_type` VARCHAR(100) DEFAULT NULL,
            `uploaded_by` INT UNSIGNED NOT NULL,
            `status` ENUM('active','inactive') DEFAULT 'active',
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `batch_id` INT UNSIGNED NOT NULL,
            `topic_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `total_marks` INT UNSIGNED DEFAULT 100,
            `due_date` DATETIME DEFAULT NULL,
            `allow_late` TINYINT(1) DEFAULT 0,
            `status` ENUM('active','inactive','draft') DEFAULT 'active',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `assignment_files` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT UNSIGNED NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` BIGINT UNSIGNED DEFAULT 0,
            `file_type` VARCHAR(100) DEFAULT NULL,
            `status` ENUM('active','inactive') DEFAULT 'active',
            `uploaded_by` INT UNSIGNED NOT NULL,
            `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `submissions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `file_name` VARCHAR(255) DEFAULT NULL,
            `file_path` VARCHAR(500) DEFAULT NULL,
            `file_size` BIGINT UNSIGNED DEFAULT 0,
            `notes` TEXT DEFAULT NULL,
            `marks` INT DEFAULT NULL,
            `feedback` TEXT DEFAULT NULL,
            `status` ENUM('submitted','graded','returned') DEFAULT 'submitted',
            `is_late` TINYINT(1) DEFAULT 0,
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `graded_at` DATETIME DEFAULT NULL,
            `graded_by` INT UNSIGNED DEFAULT NULL,
            UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
            FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `announcements` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `batch_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(200) NOT NULL,
            `content` TEXT NOT NULL,
            `priority` ENUM('normal','important','urgent') DEFAULT 'normal',
            `is_pinned` TINYINT(1) DEFAULT 0,
            `status` ENUM('published','draft') DEFAULT 'published',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `email_verifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `is_used` TINYINT(1) DEFAULT 0,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `action` VARCHAR(500) NOT NULL,
            `section` VARCHAR(100) DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // ── Chat System Tables ────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS `conversations` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user1_id`   INT UNSIGNED NOT NULL,
            `user2_id`   INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_conversation` (`user1_id`, `user2_id`),
            FOREIGN KEY (`user1_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user2_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `messages` (
            `id`                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `conversation_id`      INT UNSIGNED NOT NULL,
            `sender_id`            INT UNSIGNED NOT NULL,
            `content`              TEXT NOT NULL,
            `type`                 ENUM('text','deleted') DEFAULT 'text',
            `status`               ENUM('sent','delivered','seen') DEFAULT 'sent',
            `reactions`            JSON DEFAULT NULL,
            `deleted_for_sender`   TINYINT(1) DEFAULT 0,
            `deleted_for_receiver` TINYINT(1) DEFAULT 0,
            `deleted_at`           DATETIME DEFAULT NULL,
            `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_conv_created` (`conversation_id`, `created_at`),
            INDEX `idx_sender`       (`sender_id`),
            FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`sender_id`)       REFERENCES `users`(`id`)         ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Seed roles
        "INSERT IGNORE INTO `roles` (`id`, `name`, `label`) VALUES
            (1, 'student', 'Student'),
            (2, 'teacher', 'Teacher'),
            (3, 'admin', 'Administrator');",

        // Seed admin user (password: Admin@1234)
        "INSERT IGNORE INTO `users` (`id`, `full_name`, `email`, `password`, `status`, `is_verified`, `current_role`)
         VALUES (1, 'System Admin', 'admin@lms.com', '" . password_hash('Admin@1234', PASSWORD_BCRYPT) . "', 'active', 1, 'admin');",

        "INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `assigned_by`) VALUES (1, 3, 1);"
    ];

    foreach ($queries as $sql) {
        try {
            if (!$conn->query($sql)) {
                error_log("DB Setup Error: " . $conn->error . " | Query: " . substr($sql, 0, 100));
            }
        } catch (Exception $e) {
            error_log("DB Setup Exception: " . $e->getMessage() . " | Query: " . substr($sql, 0, 100));
        }
    }
}

// Safe column migrations (run every time, IF NOT EXISTS guards them)
function runMigrations($conn)
{
    $migrations = [
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `cnic` VARCHAR(15) DEFAULT NULL",
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `user_id_number` VARCHAR(5) DEFAULT NULL",
        "ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_cnic` (`cnic`)",
        "ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_user_id_number` (`user_id_number`)",
        "ALTER TABLE `topic_files` ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') DEFAULT 'active'",
        "ALTER TABLE `assignment_files` ADD COLUMN IF NOT EXISTS `file_type` VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE `assignment_files` ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') DEFAULT 'active'",
        "ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `is_late` TINYINT(1) DEFAULT 0",

        // ── Chat system tables (no FK constraints to avoid collation issues) ──
        "SET FOREIGN_KEY_CHECKS=0",

        "CREATE TABLE IF NOT EXISTS `conversations` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user1_id`   INT UNSIGNED NOT NULL,
            `user2_id`   INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_conversation` (`user1_id`, `user2_id`),
            INDEX `idx_user1` (`user1_id`),
            INDEX `idx_user2` (`user2_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `messages` (
            `id`                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `conversation_id`      INT UNSIGNED NOT NULL,
            `sender_id`            INT UNSIGNED NOT NULL,
            `content`              TEXT NOT NULL,
            `type`                 ENUM('text','deleted') DEFAULT 'text',
            `status`               ENUM('sent','delivered','seen') DEFAULT 'sent',
            `reactions`            JSON DEFAULT NULL,
            `deleted_for_sender`   TINYINT(1) DEFAULT 0,
            `deleted_for_receiver` TINYINT(1) DEFAULT 0,
            `deleted_at`           DATETIME DEFAULT NULL,
            `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_conv_created` (`conversation_id`, `created_at`),
            INDEX `idx_sender`       (`sender_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "SET FOREIGN_KEY_CHECKS=1",

        // ── Block & conversation-hide columns ─────────────────────
        "ALTER TABLE `conversations` ADD COLUMN IF NOT EXISTS `hidden_user1` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `conversations` ADD COLUMN IF NOT EXISTS `hidden_user2` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `conversations` ADD COLUMN IF NOT EXISTS `hidden_user1_at` DATETIME DEFAULT NULL",
        "ALTER TABLE `conversations` ADD COLUMN IF NOT EXISTS `hidden_user2_at` DATETIME DEFAULT NULL",

        "SET FOREIGN_KEY_CHECKS=0",

        "CREATE TABLE IF NOT EXISTS `blocked_users` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `blocker_id` INT UNSIGNED NOT NULL,
            `blocked_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_block` (`blocker_id`, `blocked_id`),
            INDEX `idx_blocker` (`blocker_id`),
            INDEX `idx_blocked` (`blocked_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "SET FOREIGN_KEY_CHECKS=1",
    ];
    foreach ($migrations as $sql) {
        try { $conn->query($sql); } catch (Exception $e) { /* silently ignore */ }
    }

    // Ensure upload directories exist
    $dirs = ['profiles', 'topics', 'assignments', 'submissions'];
    $base = __DIR__ . '/../uploads/';
    foreach ($dirs as $dir) {
        $path = $base . $dir;
        if (!is_dir($path)) mkdir($path, 0755, true);
    }
}

// Check if database is set up by checking if roles table exists
$check = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = 'roles'");
if ($check) {
    $row = $check->fetch_assoc();
    if ($row['cnt'] == 0) {
        setupDatabase($conn);
    }
} else {
    setupDatabase($conn);
}

// Run migrations after setup is complete
runMigrations($conn);