<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * BatchApi — admin/teacher batch CRUD, enrollment and teacher assignment.
 */
class BatchApi extends Controller
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
        $perPage = max(5, min(100, $this->int('per_page', 10)));
        $search = $this->string('search');
        $courseId = $this->int('course_id');
        $status = $this->string('status');

        $where = [];
        $params = [];

        if ($this->role() === 'teacher') {
            $where[] = 'b.id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        }
        if ($search !== '') {
            $where[] = 'b.name LIKE ?';
            $params[] = Database::like($search);
        }
        if ($courseId > 0) {
            $where[] = 'b.course_id = ?';
            $params[] = $courseId;
        }
        if ($status !== '') {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM batches b $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT b.*, c.title AS course_title,
                    (SELECT COUNT(*) FROM batch_students WHERE batch_id = b.id) AS student_count,
                    (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                     FROM batch_teachers bt JOIN users u ON u.id = bt.teacher_id
                     WHERE bt.batch_id = b.id) AS teacher_names
             FROM batches b
             LEFT JOIN courses c ON c.id = b.course_id
             $whereSql
             ORDER BY b.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('batch_id');
        $row = Database::first(
            'SELECT b.*, c.title AS course_title FROM batches b LEFT JOIN courses c ON c.id = b.course_id WHERE b.id = ?',
            [$id]
        );
        if (!$row) {
            return $this->error('Batch not found.');
        }
        return $this->success('OK', ['batch' => $row]);
    }

    public function create()
    {
        if (!$this->isAdmin()) {
            return $this->error('Only administrators can create batches.');
        }
        return $this->save(false);
    }

    public function update()
    {
        if (!$this->isAdmin()) {
            return $this->error('Only administrators can edit batches.');
        }
        return $this->save(true);
    }

    private function save($isUpdate)
    {
        $data = [
            'name' => $this->string('name'),
            'course_id' => $this->int('course_id'),
            'description' => $this->string('description'),
            'start_date' => $this->string('start_date'),
            'end_date' => $this->string('end_date'),
            'max_students' => $this->int('max_students', 50),
            'status' => $this->string('status', 'active'),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:3|max:150',
            'course_id' => 'required|exists:courses,id',
            'max_students' => 'integer|min_value:1|max_value:5000',
            'status' => 'in:active,inactive,completed',
        ], ['name' => 'Batch name', 'course_id' => 'Course', 'max_students' => 'Capacity']);

        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        if ($isUpdate) {
            $id = $this->int('batch_id');
            if (!$id) {
                return $this->error('Invalid batch.');
            }
            Database::write(
                'UPDATE batches SET course_id = ?, name = ?, description = ?, start_date = ?, end_date = ?, max_students = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [
                    $data['course_id'],
                    $data['name'],
                    $data['description'],
                    $data['start_date'] !== '' ? $data['start_date'] : null,
                    $data['end_date'] !== '' ? $data['end_date'] : null,
                    $data['max_students'],
                    $data['status'],
                    $id,
                ]
            );
            Activity::log('Updated batch: ' . $data['name'], 'batches');
            return $this->success('Batch updated.');
        }

        $id = Database::insert(
            'INSERT INTO batches (course_id, name, description, start_date, end_date, max_students, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $data['course_id'],
                $data['name'],
                $data['description'],
                $data['start_date'] !== '' ? $data['start_date'] : null,
                $data['end_date'] !== '' ? $data['end_date'] : null,
                $data['max_students'],
                $data['status'],
                $this->userId(),
            ]
        );

        Activity::log('Created batch: ' . $data['name'], 'batches');
        return $this->success('Batch created.', ['batch_id' => $id]);
    }

    public function delete()
    {
        if (!$this->isAdmin()) {
            return $this->error('Only administrators can delete batches.');
        }

        $ids = array_filter(array_map('intval', explode(',', $this->string('ids'))));
        if (!$ids && ($single = $this->int('batch_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No batches selected.');
        }

        $placeholders = Database::placeholders($ids);
        Database::write("DELETE FROM batch_students WHERE batch_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM batch_teachers WHERE batch_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM announcements WHERE batch_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM live_sessions WHERE batch_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM topics WHERE batch_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM assignments WHERE batch_id IN ($placeholders)", $ids);
        $deleted = Database::write("DELETE FROM batches WHERE id IN ($placeholders)", $ids);

        Activity::log('Deleted ' . $deleted . ' batch(es)', 'batches');
        return $this->success($deleted . ' batch(es) deleted.');
    }

    /** All + assigned members of a batch (for the enroll/assign modal). */
    public function members()
    {
        $batchId = $this->int('batch_id');
        if (!$batchId) {
            return $this->error('Invalid batch.');
        }

        $students = Database::all(
            "SELECT u.id, u.full_name, u.email, u.user_id_number, u.status
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id AND r.name = 'student'
             ORDER BY u.full_name ASC
             LIMIT 300"
        );
        $enrolled = Database::all(
            'SELECT student_id FROM batch_students WHERE batch_id = ?',
            [$batchId]
        );

        $teachers = Database::all(
            "SELECT u.id, u.full_name, u.email, u.status
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id AND r.name = 'teacher'
             ORDER BY u.full_name ASC"
        );
        $assigned = Database::all('SELECT teacher_id FROM batch_teachers WHERE batch_id = ?', [$batchId]);

        $enrolledIds = array_map(function ($r) { return (int) $r['student_id']; }, $enrolled);
        $assignedIds = array_map(function ($r) { return (int) $r['teacher_id']; }, $assigned);

        return $this->success('OK', [
            'students' => $students,
            'enrolled' => $enrolledIds,
            'teachers' => $teachers,
            'assigned' => $assignedIds,
        ]);
    }

    /** Replace the enrolled student roster (admin/teacher). */
    public function enroll()
    {
        $batchId = $this->int('batch_id');
        $ids = array_filter(array_map('intval', explode(',', $this->string('student_ids'))));

        if (!$batchId) {
            return $this->error('Invalid batch.');
        }

        $batch = Database::first('SELECT max_students, name FROM batches WHERE id = ?', [$batchId]);
        if (!$batch) {
            return $this->error('Batch not found.');
        }
        if ($ids && count($ids) > (int) $batch['max_students']) {
            return $this->error('Exceeds the batch capacity of ' . $batch['max_students'] . ' students.');
        }

        Database::write('DELETE FROM batch_students WHERE batch_id = ?', [$batchId]);
        foreach ($ids as $studentId) {
            Database::write(
                'INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES (?, ?, ?)',
                [$batchId, $studentId, $this->userId()]
            );
        }

        Activity::log('Enrolled ' . count($ids) . ' student(s) in batch #' . $batchId, 'batches');
        return $this->success(count($ids) . ' student(s) enrolled.');
    }

    /** Replace the assigned teachers for a batch. */
    public function assign()
    {
        $batchId = $this->int('batch_id');
        $ids = array_filter(array_map('intval', explode(',', $this->string('teacher_ids'))));

        if (!$batchId) {
            return $this->error('Invalid batch.');
        }

        Database::write('DELETE FROM batch_teachers WHERE batch_id = ?', [$batchId]);
        foreach ($ids as $teacherId) {
            Database::write(
                'INSERT IGNORE INTO batch_teachers (batch_id, teacher_id, assigned_by) VALUES (?, ?, ?)',
                [$batchId, $teacherId, $this->userId()]
            );
        }

        Activity::log('Assigned ' . count($ids) . ' teacher(s) to batch #' . $batchId, 'batches');
        return $this->success('Teachers assigned.');
    }

    /** Remove a single student from a batch. */
    public function unenroll()
    {
        $batchId = $this->int('batch_id');
        $studentId = $this->int('student_id');
        if (!$batchId || !$studentId) {
            return $this->error('Select a batch and a student.');
        }
        if (!$this->isAdmin() && !$this->isTeacher()) {
            return $this->error('Access denied.', [], 403);
        }

        $removed = Database::write(
            'DELETE FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [$batchId, $studentId]
        );

        Activity::log('Removed a student from batch #' . $batchId, 'batches');
        return $this->success($removed ? 'Student removed from the batch.' : 'That student was not enrolled.');
    }

    /** Remove a single teacher from a batch. */
    public function unassign()
    {
        if (!$this->isAdmin()) {
            return $this->error('Only administrators can change teacher assignments.', [], 403);
        }

        $batchId = $this->int('batch_id');
        $teacherId = $this->int('teacher_id');
        if (!$batchId || !$teacherId) {
            return $this->error('Select a batch and a teacher.');
        }

        $removed = Database::write(
            'DELETE FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?',
            [$batchId, $teacherId]
        );

        Activity::log('Removed a teacher from batch #' . $batchId, 'batches');
        return $this->success($removed ? 'Teacher unassigned.' : 'That teacher was not assigned.');
    }

    /** Actionable batches for the signed-in user (pickers, quick actions). */
    public function available()
    {
        $scope = $this->string('scope', 'all');
        $where = [];
        $params = [];

        if ($this->role() === 'teacher') {
            $where[] = 'b.id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        } elseif ($this->role() === 'student') {
            $where[] = 'b.id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)';
            $params[] = $this->userId();
        }

        if ($scope === 'active') {
            $where[] = "b.status = 'active'";
        } elseif ($scope === 'open') {
            $where[] = "b.status = 'active'";
            $where[] = '(SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) < b.max_students';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = Database::all(
            "SELECT b.id, b.name, b.status, b.max_students, c.title AS course_title,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS student_count
             FROM batches b
             JOIN courses c ON c.id = b.course_id
             $whereSql
             ORDER BY b.status = 'active' DESC, c.title, b.name
             LIMIT 200",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => count($rows)]);
    }
}

