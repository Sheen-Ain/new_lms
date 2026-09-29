<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Support\Uploader;

/**
 * AssignmentApi — AJAX CRUD + student submission.
 */
class AssignmentApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
    }

    protected function requireLogin()
    {
        if (!$this->userId()) {
            $this->error('Unauthorized', [], 401);
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $search = $this->string('search');
        $batchId = $this->int('batch_id');
        $status = $this->string('status');
        $role = $this->role();
        $userId = $this->userId();

        $where = [];
        $params = [];

        if ($role === 'student') {
            $where[] = "a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?) AND a.status = 'active'";
            $params[] = $userId;
        } elseif ($role === 'teacher') {
            $where[] = 'a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $userId;
        }

        if ($search !== '') {
            $where[] = 'a.title LIKE ?';
            $params[] = Database::like($search);
        }
        if ($batchId > 0) {
            $where[] = 'a.batch_id = ?';
            $params[] = $batchId;
        }
        if ($status !== '') {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM assignments a $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT a.*, b.name AS batch_name, t.title AS topic_title,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submission_count,
                    (SELECT COUNT(*) FROM assignment_files f WHERE f.assignment_id = a.id AND f.status = 'active') AS file_count
             FROM assignments a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN topics t ON t.id = a.topic_id
             $whereSql
             ORDER BY a.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        foreach ($rows as &$row) {
            if ($role === 'student') {
                $sub = Database::first(
                    'SELECT id, status, marks, file_name, submitted_at FROM submissions WHERE assignment_id = ? AND student_id = ?',
                    [(int) $row['id'], $userId]
                );
                $row['my_submission'] = $sub;
            }
        }

        return $this->success('OK', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function getOne()
    {
        $id = $this->int('assignment_id');
        $row = Database::first(
            "SELECT a.*, b.name AS batch_name, t.title AS topic_title
             FROM assignments a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN topics t ON t.id = a.topic_id
             WHERE a.id = ?",
            [$id]
        );

        if (!$row) {
            return $this->error('Assignment not found.');
        }

        $files = Database::all(
            'SELECT id, file_name, file_path, file_size, file_type, status FROM assignment_files WHERE assignment_id = ? ORDER BY id DESC',
            [$id]
        );

        if ($this->role() === 'student') {
            $row['my_submission'] = Database::first(
                'SELECT id, status, marks, file_name, file_path, feedback, submitted_at FROM submissions WHERE assignment_id = ? AND student_id = ?',
                [$id, $this->userId()]
            );
        }

        return $this->success('OK', ['assignment' => $row, 'files' => $files]);
    }

    public function create()
    {
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            return $this->error('Access denied.');
        }

        $data = [
            'title' => $this->string('title'),
            'batch_id' => $this->int('batch_id'),
            'description' => $this->string('description'),
            'total_marks' => $this->int('total_marks', 100),
            'due_date' => $this->string('due_date'),
            'allow_late' => $this->bool('allow_late') ? 1 : 0,
            'status' => $this->string('status', 'active'),
        ];

        $validator = Validator::make($data, [
            'title' => 'required|min:3|max:200',
            'batch_id' => 'required|exists:batches,id',
            'total_marks' => 'required|integer|min_value:1|max_value:1000',
            'due_date' => 'datetime',
            'status' => 'in:active,inactive,draft',
        ], ['title' => 'Title', 'batch_id' => 'Batch', 'total_marks' => 'Total marks']);

        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        $id = Database::insert(
            "INSERT INTO assignments (batch_id, topic_id, title, description, total_marks, due_date, allow_late, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $data['batch_id'],
                $this->int('topic_id') ?: null,
                $data['title'],
                $data['description'],
                $data['total_marks'],
                $data['due_date'] !== '' ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : null,
                $data['allow_late'],
                $data['status'],
                $this->userId(),
            ]
        );

        Activity::log('Created assignment: ' . $data['title'], 'assignments');
        return $this->success('Assignment created.', ['assignment_id' => $id]);
    }

    public function update()
    {
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            return $this->error('Access denied.');
        }

        $id = $this->int('assignment_id');
        if (!$id) {
            return $this->error('Invalid assignment.');
        }

        $existing = Database::first('SELECT id, batch_id FROM assignments WHERE id = ?', [$id]);
        if (!$existing) {
            return $this->error('Assignment not found.');
        }

        $title = $this->string('title');
        $batchId = $this->int('batch_id', (int) $existing['batch_id']);
        $topicId = $this->int('topic_id');
        $status = $this->string('status', 'active');
        $dueDate = $this->string('due_date');
        $marks = $this->int('total_marks', 100);

        $validator = Validator::make(
            ['title' => $title, 'status' => $status, 'total_marks' => $marks, 'batch_id' => $batchId],
            ['title' => 'required|min:3|max:200', 'status' => 'in:active,inactive,draft', 'total_marks' => 'integer|min_value:1|max_value:1000', 'batch_id' => 'required|exists:batches,id'],
            ['title' => 'Title']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        if ($this->role() === 'teacher') {
            $assigned = (bool) Database::value(
                'SELECT COUNT(*) FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?',
                [$batchId, $this->userId()]
            );
            if (!$assigned) {
                return $this->error('You are not assigned to that batch.', [], 403);
            }
        }

        Database::write(
            "UPDATE assignments SET batch_id = ?, topic_id = ?, title = ?, description = ?, total_marks = ?, due_date = ?, allow_late = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [
                $batchId,
                $topicId ?: null,
                $title,
                $this->string('description'),
                $marks,
                $dueDate !== '' ? date('Y-m-d H:i:s', strtotime($dueDate)) : null,
                $this->bool('allow_late') ? 1 : 0,
                $status,
                $id,
            ]
        );

        Activity::log('Updated assignment: ' . $title, 'assignments');
        return $this->success('Assignment updated.');
    }

    public function delete()
    {
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            return $this->error('Access denied.');
        }

        $ids = array_filter(array_map('intval', explode(',', $this->string('ids'))));
        if (!$ids && ($single = $this->int('assignment_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No assignments selected.');
        }

        $placeholders = Database::placeholders($ids);
        $files = Database::all("SELECT file_path FROM assignment_files WHERE assignment_id IN ($placeholders)", $ids);
        $subs = Database::all("SELECT file_path FROM submissions WHERE assignment_id IN ($placeholders)", $ids);

        foreach (array_merge($files, $subs) as $file) {
            if (!empty($file['file_path'])) {
                Uploader::deleteFile(UPLOAD_PATH . '/assignments/' . $file['file_path']);
                Uploader::deleteFile(UPLOAD_PATH . '/submissions/' . $file['file_path']);
            }
        }

        Database::write("DELETE FROM assignment_files WHERE assignment_id IN ($placeholders)", $ids);
        Database::write("DELETE FROM submissions WHERE assignment_id IN ($placeholders)", $ids);
        $deleted = Database::write("DELETE FROM assignments WHERE id IN ($placeholders)", $ids);

        Activity::log('Deleted ' . $deleted . ' assignment(s)', 'assignments');
        return $this->success($deleted . ' assignment(s) deleted.');
    }

    /** Student submits work (multipart/form-data with optional file). */
    public function submit()
    {
        if ($this->role() !== 'student') {
            return $this->error('Access denied.');
        }

        $assignmentId = $this->int('assignment_id');
        $notes = $this->string('notes');

        $assignment = Database::first('SELECT a.*, b.name AS batch_name FROM assignments a JOIN batches b ON b.id = a.batch_id WHERE a.id = ?', [$assignmentId]);
        if (!$assignment) {
            return $this->error('Assignment not found.');
        }
        if ($assignment['status'] !== 'active') {
            return $this->error('This assignment is not accepting submissions.');
        }

        $enrolled = Database::value(
            'SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [(int) $assignment['batch_id'], $this->userId()]
        );
        if (!$enrolled) {
            return $this->error('You are not enrolled in this batch.');
        }

        $isLate = 0;
        if (!empty($assignment['due_date']) && strtotime($assignment['due_date']) < time()) {
            if (!(int) $assignment['allow_late']) {
                return $this->error('The deadline has passed and late submissions are not allowed.');
            }
            $isLate = 1;
        }

        $fileName = null;
        $filePath = null;
        $fileSize = 0;

        $file = $this->file('file');
        if ($file && !empty($file['name'])) {
            $saved = Uploader::store($file, 'submissions', [
                'assignment_title' => $assignment['title'],
                'student_id' => $this->userId(),
                'student_name' => $this->user()['full_name'],
                'student_id_number' => $this->user()['user_id_number'],
            ]);
            if (!$saved) {
                return $this->error('File could not be saved. Check the type and size.');
            }
            $fileName = $file['name'];
            $filePath = $saved;
            $fileSize = (int) $file['size'];
        }

        $existing = Database::first('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?', [$assignmentId, $this->userId()]);

        if ($existing) {
            Database::write(
                "UPDATE submissions SET file_name = ?, file_path = ?, file_size = ?, notes = ?, status = 'submitted', is_late = ?, submitted_at = NOW() WHERE id = ?",
                [$fileName, $filePath, $fileSize, $notes, $isLate, $existing['id']]
            );
        } else {
            Database::write(
                "INSERT INTO submissions (assignment_id, student_id, file_name, file_path, file_size, notes, status, is_late, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())",
                [$assignmentId, $this->userId(), $fileName, $filePath, $fileSize, $notes, $isLate]
            );
        }

        Activity::log('Submitted assignment: ' . $assignment['title'] . ($isLate ? ' (late)' : ''), 'submissions');
        return $this->success('Assignment submitted successfully.');
    }

    /** Upload a reference file to an assignment (teacher/admin). */
    public function upload()
    {
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            return $this->error('Access denied.');
        }

        $assignmentId = $this->int('assignment_id');
        $file = $this->file('file');

        if (!$assignmentId || !$file) {
            return $this->error('No file provided.');
        }

        $assignment = Database::first('SELECT id, title FROM assignments WHERE id = ?', [$assignmentId]);
        if (!$assignment) {
            return $this->error('Assignment not found.');
        }

        $saved = Uploader::store($file, 'assignments', ['assignment_title' => $assignment['title']]);
        if (!$saved) {
            return $this->error('File could not be saved.');
        }

        Database::write(
            "INSERT INTO assignment_files (assignment_id, file_name, file_path, file_size, file_type, status, uploaded_by)
             VALUES (?, ?, ?, ?, ?, 'active', ?)",
            [
                $assignmentId,
                $file['name'],
                $saved,
                (int) $file['size'],
                Uploader::mime($file['tmp_name'], file_ext($file['name'])),
                $this->userId(),
            ]
        );

        Activity::log('Uploaded file to assignment ' . $assignmentId, 'assignments');
        return $this->success('File uploaded.', ['file_name' => $file['name'], 'file_path' => $saved]);
    }

    /** Reference material attached to an assignment. */
    public function files()
    {
        $assignmentId = $this->int('assignment_id');
        if (!$assignmentId) {
            return $this->error('Assignment required.');
        }

        $assignment = Database::first(
            'SELECT id, title, batch_id, total_marks, due_date FROM assignments WHERE id = ?',
            [$assignmentId]
        );
        if (!$assignment) {
            return $this->error('Assignment not found.');
        }

        $status = $this->string('status', $this->role() === 'student' ? 'active' : '');
        $where = ['f.assignment_id = ?'];
        $params = [$assignmentId];
        if ($status !== '') {
            $where[] = 'f.status = ?';
            $params[] = $status;
        }

        $rows = Database::all(
            'SELECT f.*, u.full_name AS uploader_name
             FROM assignment_files f
             LEFT JOIN users u ON u.id = f.uploaded_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY f.uploaded_at DESC',
            $params
        );

        return $this->success('OK', ['assignment' => $assignment, 'files' => $rows, 'total' => count($rows)]);
    }

    public function deleteFile()
    {
        if (!in_array($this->role(), ['teacher', 'admin'], true)) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('file_ids')))));
        if (!$ids && ($single = $this->int('file_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No files selected.');
        }

        $placeholders = Database::placeholders($ids);
        $files = Database::all("SELECT file_path FROM assignment_files WHERE id IN ($placeholders)", $ids);
        foreach ($files as $file) {
            if (!empty($file['file_path'])) {
                Uploader::deleteFile(UPLOAD_PATH . '/assignments/' . ltrim($file['file_path'], '/'));
            }
        }

        $deleted = Database::write("DELETE FROM assignment_files WHERE id IN ($placeholders)", $ids);
        Activity::log('Deleted ' . $deleted . ' assignment file(s)', 'assignments');
        return $this->success($deleted . ' file(s) deleted.');
    }
}



