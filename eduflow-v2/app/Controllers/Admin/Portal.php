<?php

/**
 * Admin\Portal — every administrator-facing screen.
 */
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;

class Portal extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            redirect('/login');
        }
    }

    public function index()
    {
        $stats = [
            'students' => (int) Database::value(
                "SELECT COUNT(*) FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN roles r ON r.id = ur.role_id WHERE r.name = 'student'"
            ),
            'teachers' => (int) Database::value(
                "SELECT COUNT(*) FROM users u
                 JOIN user_roles ur ON ur.user_id = u.id
                 JOIN roles r ON r.id = ur.role_id WHERE r.name = 'teacher'"
            ),
            'courses' => (int) Database::value("SELECT COUNT(*) FROM courses WHERE status <> 'archived'"),
            'batches' => (int) Database::value("SELECT COUNT(*) FROM batches WHERE status = 'active'"),
            'pending_submissions' => (int) Database::value("SELECT COUNT(*) FROM submissions WHERE status = 'submitted'"),
            'pending_applications' => (int) Database::value(
                "SELECT COUNT(*) FROM course_applications WHERE status IN ('pending','test_submitted')"
            ),
            'tests' => (int) Database::value('SELECT COUNT(*) FROM tests'),
            'active_sessions' => (int) Database::value("SELECT COUNT(*) FROM live_sessions WHERE status = 'active'"),
        ];

        $recentActivity = Database::all(
            'SELECT al.*, u.full_name, u.profile_picture
             FROM activity_logs al
             JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 8'
        );

        $enrollment = Database::all(
            "SELECT c.title, COUNT(DISTINCT bs.student_id) AS learners
             FROM courses c
             LEFT JOIN batches b ON b.course_id = c.id
             LEFT JOIN batch_students bs ON bs.batch_id = b.id
             GROUP BY c.id, c.title
             ORDER BY learners DESC
             LIMIT 5"
        );

        $this->view('admin/dashboard', [
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'enrollment' => $enrollment,
        ]);
    }

    private function resourcePage($id, $title, $endpoint, array $columns, array $filters = [], $rowsLabel = null, $moduleType = null, array $actions = [], array $bulkActions = [], $createLabel = null, array $toolbarActions = [])
    {
        array_unshift($filters, ['type' => 'search', 'placeholder' => 'Search ' . strtolower($title) . '...']);
        array_unshift($filters, [
            'type' => 'select',
            'name' => 'per_page',
            'label' => 'Rows',
            'any' => '10 / page',
            'default' => '10',
            'options' => [10 => '10 / page', 25 => '25 / page', 50 => '50 / page'],
        ]);
        if ($actions) {
            $columns[] = ['label' => 'Actions', 'class' => 'col-actions', 'format' => 'actions'];
        }
        $toolbar = $createLabel
            ? '<button type="button" class="btn btn-sm btn-primary" data-admin-create>' . icon('plus', 15) . ' ' . e($createLabel) . '</button>'
            : '';
        $toolbarMeta = [
            'manual-enroll' => ['label' => 'Enroll student', 'icon' => 'user'],
            'export' => ['label' => 'Export CSV', 'icon' => 'download'],
            'clear' => ['label' => 'Clear log', 'icon' => 'trash'],
        ];
        foreach ($toolbarActions as $action) {
            if (isset($toolbarMeta[$action])) {
                $toolbar .= '<button type="button" class="btn btn-sm' . ($action === 'clear' ? ' is-danger' : '') . '" data-admin-toolbar="' . e($action) . '">' . icon($toolbarMeta[$action]['icon'], 15) . ' ' . e($toolbarMeta[$action]['label']) . '</button>';
            }
        }

        $this->view('admin/module', [
            'pageTitle' => $title,
            'resource' => [
                'id' => $id,
                'endpoint' => $endpoint,
                'columns' => $columns,
                'filters' => $filters,
                'renderFn' => 'renderAdminModuleRow',
                'perPage' => 10,
                'rowsLabel' => $rowsLabel ?: strtolower(rtrim($title, 's')),
                'emptyTitle' => 'No ' . strtolower($title) . ' found',
                'emptyText' => 'Records will appear here when they are available.',
                'toolbar' => $toolbar,
                'moduleType' => $moduleType,
                'actions' => $actions,
                'bulkActions' => $bulkActions,
                'createLabel' => $createLabel,
                'toolbarActions' => $toolbarActions,
                'bulk' => !empty($bulkActions),
            ],
        ]);
    }

    public function users()
    {
        $this->resourcePage('admin-users', 'Users', '/api/users/list', [
            ['label' => 'User', 'field' => 'full_name', 'format' => 'user'],
            ['label' => 'Email', 'field' => 'email'],
            ['label' => 'Roles', 'field' => 'all_roles'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
            ['label' => 'Verified', 'field' => 'is_verified', 'format' => 'verified'],
            ['label' => 'Last seen', 'field' => 'last_seen', 'format' => 'datetime'],
        ], [
            ['type' => 'select', 'name' => 'role', 'label' => 'Role', 'options' => ['student' => 'Student', 'teacher' => 'Teacher', 'admin' => 'Admin']],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ['type' => 'select', 'name' => 'verified', 'label' => 'Verification', 'options' => ['1' => 'Verified', '0' => 'Unverified']],
        ], 'user', 'users', ['view', 'edit', 'status', 'bypass', 'delete'], ['activate', 'deactivate', 'delete'], 'Add user');
    }

    public function courses()
    {
        $this->resourcePage('admin-courses', 'Courses', '/api/courses/list', [
            ['label' => 'Course', 'field' => 'title', 'format' => 'course'],
            ['label' => 'Description', 'field' => 'description'],
            ['label' => 'Batches', 'field' => 'batch_count'],
            ['label' => 'Created by', 'field' => 'creator_name'],
            ['label' => 'Date', 'field' => 'created_at', 'format' => 'date'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
        ], [['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived']]], 'course', 'courses', ['edit', 'status', 'delete'], ['delete'], 'New course');
    }

    public function batches()
    {
        $courseOptions = [];
        foreach (Database::all("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title") as $course) {
            $courseOptions[$course['id']] = $course['title'];
        }
        $this->resourcePage('admin-batches', 'Batches', '/api/batches/list', [
            ['label' => 'Batch', 'field' => 'name'],
            ['label' => 'Course', 'field' => 'course_title'],
            ['label' => 'Students', 'field' => 'student_count', 'format' => 'capacity'],
            ['label' => 'Teachers', 'field' => 'teacher_names'],
            ['label' => 'Dates', 'field' => 'start_date', 'format' => 'dateRange'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
        ], [
            ['type' => 'select', 'name' => 'course_id', 'label' => 'Course', 'options' => $courseOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'completed' => 'Completed']],
        ], 'batch', 'batches', ['edit', 'teachers', 'students', 'status', 'delete'], ['delete'], 'New batch');
    }

    public function topics()
    {
        $batchOptions = ['-1' => 'Global'];
        foreach (Database::all("SELECT b.id, b.name, c.title AS course_title FROM batches b JOIN courses c ON c.id = b.course_id WHERE b.status = 'active' ORDER BY c.title, b.name") as $batch) {
            $batchOptions[$batch['id']] = $batch['course_title'] . ' · ' . $batch['name'];
        }
        $this->resourcePage('admin-topics', 'Topics', '/api/topics/list', [
            ['label' => 'Topic', 'field' => 'title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Sort', 'field' => 'sort_order'],
            ['label' => 'Files', 'field' => 'file_count'],
            ['label' => 'Assignments', 'field' => 'assignment_count'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
        ], [
            ['type' => 'select', 'name' => 'batch_id', 'label' => 'Batch', 'options' => $batchOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ], 'topic', 'topics', ['edit', 'files', 'status', 'delete'], ['delete'], 'New topic');
    }

    public function assignments()
    {
        $batchOptions = [];
        foreach (Database::all("SELECT id, name FROM batches WHERE status = 'active' ORDER BY name") as $batch) {
            $batchOptions[$batch['id']] = $batch['name'];
        }
        $this->resourcePage('admin-assignments', 'Assignments', '/api/assignments/list', [
            ['label' => 'Assignment', 'field' => 'title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Topic', 'field' => 'topic_title'],
            ['label' => 'Submissions', 'field' => 'submission_count'],
            ['label' => 'Files', 'field' => 'file_count'],
            ['label' => 'Due', 'field' => 'due_date', 'format' => 'datetime'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
        ], [
            ['type' => 'select', 'name' => 'batch_id', 'label' => 'Batch', 'options' => $batchOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft']],
        ], 'assignment', 'assignments', ['edit', 'files', 'status', 'delete'], ['delete'], 'New assignment');
    }

    public function submissions()
    {
        $batchOptions = [];
        foreach (Database::all('SELECT id, name FROM batches ORDER BY name') as $batch) {
            $batchOptions[$batch['id']] = $batch['name'];
        }
        $this->resourcePage('admin-submissions', 'Submissions', '/api/submissions/list', [
            ['label' => 'Student', 'field' => 'student_name'],
            ['label' => 'Student ID', 'field' => 'user_id_number'],
            ['label' => 'Assignment', 'field' => 'assignment_title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
            ['label' => 'Submitted', 'field' => 'submitted_at', 'format' => 'datetime'],
        ], [
            ['type' => 'select', 'name' => 'batch_id', 'label' => 'Batch', 'options' => $batchOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['submitted' => 'Submitted', 'graded' => 'Graded', 'returned' => 'Returned']],
        ], 'submission', 'submissions', ['view', 'grade', 'download', 'delete'], ['delete', 'download']);
    }

    public function tests()
    {
        $this->resourcePage('admin-tests', 'Tests', '/api/tests/list', [
            ['label' => 'Test', 'field' => 'title'],
            ['label' => 'Course', 'field' => 'course_title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Questions', 'field' => 'question_count'],
            ['label' => 'Attempts', 'field' => 'attempt_count'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
        ], [
            ['type' => 'select', 'name' => 'type', 'label' => 'Type', 'options' => ['entry' => 'Entry', 'weekly' => 'Weekly', 'monthly' => 'Monthly']],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft']],
        ], 'test', 'tests', ['view', 'edit', 'copy', 'status', 'delete'], ['activate', 'deactivate', 'delete'], 'New test');
    }

    public function applications()
    {
        $batchOptions = [];
        foreach (Database::all('SELECT id, name FROM batches ORDER BY name') as $batch) {
            $batchOptions[$batch['id']] = $batch['name'];
        }
        $this->resourcePage('admin-applications', 'Applications', '/api/applications/list', [
            ['label' => 'Applicant', 'field' => 'full_name'],
            ['label' => 'Student ID', 'field' => 'user_id_number'],
            ['label' => 'Course', 'field' => 'course_title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Attempts', 'field' => 'attempt_count'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
            ['label' => 'Applied', 'field' => 'applied_at', 'format' => 'datetime'],
        ], [
            ['type' => 'select', 'name' => 'batch_id', 'label' => 'Batch', 'options' => $batchOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['pending' => 'Pending', 'test_submitted' => 'Test submitted', 'approved' => 'Approved', 'rejected' => 'Rejected']],
        ], 'application', 'applications', ['view', 'approve', 'reject'], ['approve', 'reject'], null, ['manual-enroll', 'export']);
    }

    public function announcements()
    {
        $batchOptions = ['-1' => 'Global'];
        foreach (Database::all('SELECT id, name FROM batches ORDER BY name') as $batch) {
            $batchOptions[$batch['id']] = $batch['name'];
        }
        $this->resourcePage('admin-announcements', 'Announcements', '/api/announcements/list', [
            ['label' => 'Announcement', 'field' => 'title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Priority', 'field' => 'priority', 'format' => 'status'],
            ['label' => 'Created by', 'field' => 'creator_name'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
            ['label' => 'Created', 'field' => 'created_at', 'format' => 'date'],
        ], [
            ['type' => 'select', 'name' => 'batch_id', 'label' => 'Audience', 'options' => $batchOptions],
            ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
        ], 'announcement', 'announcements', ['edit', 'pin', 'delete'], ['delete'], 'New announcement');
    }

    public function feedback()
    {
        $this->resourcePage('admin-feedback', 'Feedback', '/api/feedback/list', [
            ['label' => 'Session', 'field' => 'session_title'],
            ['label' => 'Course', 'field' => 'course_title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Categories', 'field' => 'category_labels'],
            ['label' => 'Feedback', 'field' => 'content'],
            ['label' => 'Review', 'field' => 'is_reviewed', 'format' => 'review'],
            ['label' => 'Submitted', 'field' => 'submitted_at', 'format' => 'datetime'],
        ], [['type' => 'select', 'name' => 'status', 'label' => 'Review', 'options' => ['new' => 'Needs review', 'reviewed' => 'Reviewed']]], 'feedback entry', 'feedback', ['view', 'review', 'delete'], ['review', 'delete'], 'Manage feedback rounds');
    }

    public function live()
    {
        $this->resourcePage('admin-live', 'Live sessions', '/api/live/list', [
            ['label' => 'Session', 'field' => 'title'],
            ['label' => 'Course', 'field' => 'course_title'],
            ['label' => 'Batch', 'field' => 'batch_name'],
            ['label' => 'Host', 'field' => 'host_name'],
            ['label' => 'Live now', 'field' => 'live_count'],
            ['label' => 'Status', 'field' => 'status', 'format' => 'status'],
            ['label' => 'Started', 'field' => 'started_at', 'format' => 'datetime'],
        ], [['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'waiting' => 'Waiting', 'ended' => 'Ended']]], 'session', 'live', ['view', 'open', 'end', 'delete'], [], 'Start session');
    }

    public function activityLogs()
    {
        $this->resourcePage('admin-activity', 'Activity logs', '/api/activity/list', [
            ['label' => 'User', 'field' => 'full_name'],
            ['label' => 'User ID', 'field' => 'user_id_number'],
            ['label' => 'Role', 'field' => 'current_role'],
            ['label' => 'Section', 'field' => 'section'],
            ['label' => 'Activity', 'field' => 'action'],
            ['label' => 'Date', 'field' => 'created_at', 'format' => 'datetime'],
        ], [
            ['type' => 'select', 'name' => 'section', 'label' => 'Section', 'options' => [
                'auth' => 'Authentication', 'users' => 'Users', 'courses' => 'Courses', 'batches' => 'Batches',
                'topics' => 'Topics', 'assignments' => 'Assignments', 'tests' => 'Tests',
            ]],
            ['type' => 'date', 'name' => 'from', 'label' => 'From'],
            ['type' => 'date', 'name' => 'to', 'label' => 'To'],
        ], 'activity record', 'activity', [], [], null, ['export', 'clear']);
    }

    public function system()
    {
        $routes = [];
        try {
            $router = \App\Core\App::router();
            $routes = $router->table();
        } catch (\Throwable $e) {
            Logger::warning('Could not build route table: ' . $e->getMessage());
        }

        $schema = [];
        try {
            $tables = Database::column('SHOW TABLES');
            foreach ($tables as $table) {
                $schema[$table] = (int) Database::value('SELECT COUNT(*) FROM `' . $table . '`');
            }
        } catch (\Throwable $e) {
            $schema = [];
        }

        $this->view('admin/system', [
            'pageTitle' => 'System health',
            'routes' => $routes,
            'schema' => $schema,
            'phpVersion' => PHP_VERSION,
            'logs' => Logger::recentErrors(25),
        ]);
    }
}
