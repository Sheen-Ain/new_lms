<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;

/**
 * SubmissionApi — grading workflow for teachers/admins.
 */
class SubmissionApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            $this->error('Unauthorized', [], 401);
        }
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            $this->error('Access denied.', [], 403);
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $search = $this->string('search');
        $batchId = $this->int('batch_id');
        $assignmentId = $this->int('assignment_id');
        $status = $this->string('status');
        $role = $this->role();

        $where = [];
        $params = [];

        if ($role === 'teacher') {
            $where[] = 'a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR a.title LIKE ?)';
            $params[] = Database::like($search);
            $params[] = Database::like($search);
        }
        if ($batchId > 0) {
            $where[] = 'a.batch_id = ?';
            $params[] = $batchId;
        }
        if ($assignmentId > 0) {
            $where[] = 's.assignment_id = ?';
            $params[] = $assignmentId;
        }
        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value(
            "SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN users u ON u.id = s.student_id $whereSql",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT s.*, u.full_name AS student_name, u.user_id_number, u.profile_picture,
                    a.title AS assignment_title, a.total_marks, b.name AS batch_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN users u ON u.id = s.student_id
             LEFT JOIN batches b ON b.id = a.batch_id
             $whereSql
             ORDER BY s.submitted_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('submission_id');
        $row = Database::first(
            "SELECT s.*, u.full_name AS student_name, u.user_id_number, u.email, a.title AS assignment_title,
                    a.total_marks, b.name AS batch_name, gb.full_name AS graded_by_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN users u ON u.id = s.student_id
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN users gb ON gb.id = s.graded_by
             WHERE s.id = ?",
            [$id]
        );

        if (!$row) {
            return $this->error('Submission not found.');
        }
        return $this->success('OK', ['submission' => $row]);
    }

    public function grade()
    {
        $id = $this->int('submission_id');
        $marks = $this->input('marks');
        $feedback = $this->string('feedback');
        $action = $this->string('action', 'grade');   // grade | return

        $submission = Database::first(
            'SELECT s.*, a.total_marks FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ?',
            [$id]
        );
        if (!$submission) {
            return $this->error('Submission not found.');
        }

        if ($action === 'return') {
            $status = 'returned';
            $marksValue = 0;
        } else {
            $marksValue = (int) $marks;
            if ($marksValue < 0 || $marksValue > (int) $submission['total_marks']) {
                return $this->error('Marks must be between 0 and ' . $submission['total_marks'] . '.');
            }
            $status = 'graded';
        }

        Database::write(
            "UPDATE submissions SET marks = ?, feedback = ?, status = ?, graded_at = NOW(), graded_by = ? WHERE id = ?",
            [$marksValue, $feedback, $status, $this->userId(), $id]
        );

        Activity::log(
            'Graded submission #' . $id . ' (' . $marksValue . '/' . $submission['total_marks'] . ')',
            'submissions'
        );

        return $this->success($status === 'returned' ? 'Submission returned to student.' : 'Marks saved.');
    }

    /** Remove submissions (and their stored files) — used for corrections. */
    public function delete()
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('submission_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No submissions selected.');
        }

        $placeholders = Database::placeholders($ids);
        $rows = Database::all(
            "SELECT s.id, s.file_path, s.student_id FROM submissions s WHERE s.id IN ($placeholders)",
            $ids
        );

        // Teachers may only touch submissions inside their own batches.
        if ($this->role() === 'teacher') {
            $allowed = Database::column(
                "SELECT s.id FROM submissions s
                 JOIN assignments a ON a.id = s.assignment_id
                 WHERE s.id IN ($placeholders)
                   AND a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)",
                array_merge($ids, [$this->userId()])
            );
            $allowed = array_map('intval', $allowed);
            $rows = array_values(array_filter($rows, function ($row) use ($allowed) {
                return in_array((int) $row['id'], $allowed, true);
            }));
        }

        if (!$rows) {
            return $this->error('You cannot delete those submissions.', [], 403);
        }

        foreach ($rows as $row) {
            if (!empty($row['file_path'])) {
                $absolute = \App\Support\Uploader::resolve($row['file_path']);
                if ($absolute && is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        $removeIds = array_map(function ($row) { return (int) $row['id']; }, $rows);
        $deleted = Database::write(
            'DELETE FROM submissions WHERE id IN (' . Database::placeholders($removeIds) . ')',
            $removeIds
        );

        Activity::log('Deleted ' . $deleted . ' submission(s)', 'submissions');
        return $this->success($deleted . ' submission(s) deleted.');
    }
}
