<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;

/**
 * ApplicationApi — administration of course applications.
 *
 * Applicants are created by the public application flow as inactive users.
 * Approval activates the account and enrols the student in the chosen batch;
 * rejection stores a reason the applicant can read back from the public site.
 */
class ApplicationApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId() || !$this->isAdmin()) {
            $this->error('Access denied.', [], 403);
        }
    }

    /** Normalise the incoming id list (single id or comma separated ids). */
    private function idList($field)
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int($field))) {
            $ids = [$single];
        }
        return $ids;
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $search = $this->string('search');
        $status = $this->string('status');
        $batchId = $this->int('batch_id');

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'ca.status = ?';
            $params[] = $status;
        }
        if ($batchId > 0) {
            $where[] = 'ca.batch_id = ?';
            $params[] = $batchId;
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.user_id_number LIKE ? OR b.name LIKE ?)';
            $like = Database::like($search);
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value(
            "SELECT COUNT(*) FROM course_applications ca
             JOIN users u ON u.id = ca.user_id
             LEFT JOIN batches b ON b.id = ca.batch_id
             $whereSql",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT ca.*, u.full_name, u.email, u.user_id_number, u.cnic, u.gender,
                    u.status AS user_status, u.is_verified, u.profile_picture, u.created_at AS registered_at,
                    b.name AS batch_name, b.course_id, c.title AS course_title,
                    ta.id AS attempt_id, ta.percentage, ta.status AS attempt_status,
                    (SELECT COUNT(*) FROM test_attempts t2 WHERE t2.application_id = ca.id) AS attempt_count
             FROM course_applications ca
             JOIN users u ON u.id = ca.user_id
             LEFT JOIN batches b ON b.id = ca.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             LEFT JOIN test_attempts ta ON ta.application_id = ca.id
             $whereSql
             ORDER BY FIELD(ca.status, 'test_submitted', 'pending', 'approved', 'rejected'), ca.applied_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('application_id');
        $row = Database::first(
            'SELECT ca.*, u.full_name, u.email, u.user_id_number, u.cnic, u.gender, u.phone, u.bio,
                    u.status AS user_status, u.is_verified, u.created_at AS registered_at,
                    b.name AS batch_name, c.title AS course_title
             FROM course_applications ca
             JOIN users u ON u.id = ca.user_id
             LEFT JOIN batches b ON b.id = ca.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             WHERE ca.id = ?',
            [$id]
        );
        if (!$row) {
            return $this->error('Application not found.');
        }

        $attempts = Database::all(
            'SELECT id, score, percentage, total_questions, correct_count, wrong_count, unanswered_count,
                    status, started_at, submitted_at
             FROM test_attempts WHERE application_id = ? ORDER BY id DESC',
            [$id]
        );

        return $this->success('OK', ['application' => $row, 'attempts' => $attempts]);
    }

    /** Approve one or many applications and enrol the students. */
    public function approve()
    {
        $ids = $this->idList('application_id');
        if (!$ids) {
            return $this->error('No applications selected.');
        }

        $batchOverride = $this->int('batch_id');
        $placeholders = Database::placeholders($ids);
        $rows = Database::all(
            "SELECT ca.*, u.full_name FROM course_applications ca
             JOIN users u ON u.id = ca.user_id
             WHERE ca.id IN ($placeholders)",
            $ids
        );
        if (!$rows) {
            return $this->error('Applications not found.');
        }

        $approved = 0;
        foreach ($rows as $row) {
            $batchId = $batchOverride > 0 ? $batchOverride : (int) $row['batch_id'];
            if (!$batchId) {
                continue;
            }

            Database::write(
                "UPDATE course_applications SET status = 'approved', batch_id = ?, rejection_reason = NULL,
                 reviewed_at = NOW(), reviewed_by = ? WHERE id = ?",
                [$batchId, $this->userId(), (int) $row['id']]
            );

            Database::write(
                'INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by, enrolled_at) VALUES (?, ?, ?, NOW())',
                [$batchId, (int) $row['user_id'], $this->userId()]
            );

            // The account only becomes usable once the application is approved.
            Database::write(
                "UPDATE users SET status = 'active', is_verified = 1
                 WHERE id = ? AND (status <> 'active' OR is_verified = 0)",
                [(int) $row['user_id']]
            );

            $approved++;
        }

        Activity::log('Approved ' . $approved . ' application(s)', 'applications');
        return $this->success($approved . ' application(s) approved and enrolled.');
    }

    /** Reject one or many applications with an optional reason. */
    public function reject()
    {
        $ids = $this->idList('application_id');
        if (!$ids) {
            return $this->error('No applications selected.');
        }

        $reason = $this->string('reason');
        $placeholders = Database::placeholders($ids);

        $updated = Database::write(
            "UPDATE course_applications SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
             WHERE id IN ($placeholders)",
            array_merge([$reason !== '' ? $reason : null, $this->userId()], $ids)
        );

        Activity::log('Rejected ' . $updated . ' application(s)', 'applications');
        return $this->success($updated . ' application(s) rejected.');
    }

    /* ── Manual enrolment helpers ─────────────────────────────── */

    /** Candidates for manual enrolment (registered students with no seat yet). */
    public function eligible()
    {
        $batchId = $this->int('batch_id');
        $search = $this->string('search');

        $where = ["u.status = 'active'"];
        $params = [];

        if ($batchId > 0) {
            $where[] = 'u.id NOT IN (SELECT student_id FROM batch_students WHERE batch_id = ?)';
            $params[] = $batchId;
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.user_id_number LIKE ?)';
            $like = Database::like($search);
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $rows = Database::all(
            'SELECT u.id, u.full_name, u.email, u.user_id_number, u.cnic, u.profile_picture,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.student_id = u.id) AS batch_count
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.name = \'student\' AND ' . implode(' AND ', $where) . '
             ORDER BY u.full_name
             LIMIT 50',
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => count($rows)]);
    }

    /** Enrol an existing student into a batch directly. */
    public function enroll()
    {
        $batchId = $this->int('batch_id');
        $studentId = $this->int('student_id');
        if (!$batchId || !$studentId) {
            return $this->error('Select a batch and a student.');
        }

        if (!Database::first('SELECT id FROM batches WHERE id = ?', [$batchId])) {
            return $this->error('Batch not found.');
        }

        $student = Database::first(
            "SELECT u.id, u.full_name, u.status FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? AND r.name = 'student'",
            [$studentId]
        );
        if (!$student) {
            return $this->error('Student account not found.');
        }

        $exists = Database::value(
            'SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [$batchId, $studentId]
        );
        if ($exists) {
            return $this->error($student['full_name'] . ' is already enrolled in this batch.');
        }

        Database::write(
            'INSERT INTO batch_students (batch_id, student_id, enrolled_by, enrolled_at) VALUES (?, ?, ?, NOW())',
            [$batchId, $studentId, $this->userId()]
        );
        Database::write("UPDATE users SET status = 'active' WHERE id = ? AND status <> 'active'", [$studentId]);

        Activity::log('Enrolled ' . $student['full_name'] . ' in batch #' . $batchId, 'batches');
        return $this->success($student['full_name'] . ' enrolled.');
    }
}
