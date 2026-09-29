<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Support\Uploader;

/**
 * TopicApi — course material management.
 *
 * Topics are the study units inside a batch; each topic owns a set of files
 * (slide decks, code samples, outlines) that students can preview inline.
 * Assignments can be attached to a topic so material and work stay together.
 */
class TopicApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            $this->error('Unauthorized', [], 401);
        }
    }

    private function isStaff()
    {
        return in_array($this->role(), ['teacher', 'admin'], true);
    }

    /** Visibility scope for the active role. */
    private function scope(&$where, &$params)
    {
        if ($this->role() === 'student') {
            $where[] = '(t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?) OR t.batch_id IS NULL)';
            $params[] = $this->userId();
            $where[] = "t.status = 'active'";
        } elseif ($this->role() === 'teacher') {
            $where[] = '(t.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?) OR t.batch_id IS NULL)';
            $params[] = $this->userId();
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 12)));
        $search = $this->string('search');
        $batchId = (string) $this->input('batch_id', '');
        $status = $this->string('status');

        $where = [];
        $params = [];
        $this->scope($where, $params);

        if ($batchId === '-1') {
            $where[] = 't.batch_id IS NULL';
        } elseif ((int) $batchId > 0) {
            $where[] = 't.batch_id = ?';
            $params[] = (int) $batchId;
        }
        if ($status !== '' && $this->role() !== 'student') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = Database::like($search);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM topics t $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT t.*, b.name AS batch_name,
                    (SELECT COUNT(*) FROM topic_files f WHERE f.topic_id = t.id AND f.status = 'active') AS file_count,
                    (SELECT COUNT(*) FROM assignments a WHERE a.topic_id = t.id AND a.status = 'active') AS assignment_count
             FROM topics t
             LEFT JOIN batches b ON b.id = t.batch_id
             $whereSql
             ORDER BY COALESCE(t.batch_id, 0), t.sort_order, t.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    /** Compact list used to populate topic dropdowns. */
    public function listSimple()
    {
        $batchId = $this->int('batch_id');
        $where = ["t.status = 'active'"];
        $params = [];
        $this->scope($where, $params);

        if ($batchId > 0) {
            $where[] = '(t.batch_id = ? OR t.batch_id IS NULL)';
            $params[] = $batchId;
        }

        $rows = Database::all(
            'SELECT t.id, t.title, t.batch_id FROM topics t WHERE ' . implode(' AND ', $where) . ' ORDER BY t.sort_order, t.title',
            $params
        );

        return $this->success('OK', ['topics' => $rows]);
    }

    public function getOne()
    {
        $id = $this->int('topic_id');
        $topic = Database::first(
            'SELECT t.*, b.name AS batch_name FROM topics t LEFT JOIN batches b ON b.id = t.batch_id WHERE t.id = ?',
            [$id]
        );
        if (!$topic) {
            return $this->error('Topic not found.');
        }

        $files = Database::all(
            'SELECT f.*, u.full_name AS uploader_name
             FROM topic_files f
             LEFT JOIN users u ON u.id = f.uploaded_by
             WHERE f.topic_id = ?
             ORDER BY f.uploaded_at DESC',
            [$id]
        );

        return $this->success('OK', ['topic' => $topic, 'files' => $files]);
    }

    /** Files inside one topic (student material panel). */
    public function files()
    {
        $topicId = $this->int('topic_id');
        $topic = Database::first('SELECT id, title, description, batch_id FROM topics WHERE id = ?', [$topicId]);
        if (!$topic) {
            return $this->error('Topic not found.');
        }

        $status = $this->string('status', $this->role() === 'student' ? 'active' : '');
        $where = ['f.topic_id = ?'];
        $params = [$topicId];
        if ($status !== '') {
            $where[] = 'f.status = ?';
            $params[] = $status;
        }

        $rows = Database::all(
            'SELECT f.*, u.full_name AS uploader_name
             FROM topic_files f
             LEFT JOIN users u ON u.id = f.uploaded_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY f.uploaded_at DESC',
            $params
        );

        return $this->success('OK', ['topic' => $topic, 'files' => $rows, 'total' => count($rows)]);
    }

    /** Active assignments attached to a topic. */
    public function assignments()
    {
        $topicId = $this->int('topic_id');
        $rows = Database::all(
            "SELECT a.id, a.title, a.description, a.total_marks, a.due_date, a.allow_late, a.status,
                    b.name AS batch_name,
                    (SELECT COUNT(*) FROM assignment_files f WHERE f.assignment_id = a.id AND f.status = 'active') AS file_count,
                    s.id AS submission_id, s.status AS submission_status, s.marks AS submission_marks, s.submitted_at
             FROM assignments a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
             WHERE a.topic_id = ? AND a.status = 'active'
             ORDER BY a.due_date IS NULL, a.due_date, a.created_at DESC",
            [$this->userId(), $topicId]
        );

        return $this->success('OK', ['rows' => $rows, 'total' => count($rows)]);
    }

    public function create()
    {
        return $this->save(false);
    }

    public function update()
    {
        return $this->save(true);
    }

    private function save($isUpdate)
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $title = $this->string('title');
        $description = $this->string('description');
        $batchId = $this->int('batch_id');
        $status = $this->string('status', 'active');
        $sortOrder = $this->int('sort_order', 0);

        $validator = Validator::make(
            ['title' => $title, 'status' => $status],
            ['title' => 'required|min:3|max:200', 'status' => 'in:active,inactive'],
            ['title' => 'Topic title']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        if ($batchId > 0 && !$this->isAdmin()) {
            $allowed = (bool) Database::value(
                'SELECT COUNT(*) FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?',
                [$batchId, $this->userId()]
            );
            if (!$allowed) {
                return $this->error('You are not assigned to that batch.', [], 403);
            }
        }

        if ($isUpdate) {
            $id = $this->int('topic_id');
            if (!$id || !Database::first('SELECT id FROM topics WHERE id = ?', [$id])) {
                return $this->error('Topic not found.');
            }

            Database::write(
                'UPDATE topics SET title = ?, description = ?, batch_id = ?, status = ?, sort_order = ?, updated_at = NOW() WHERE id = ?',
                [$title, $description !== '' ? $description : null, $batchId > 0 ? $batchId : null, $status, $sortOrder, $id]
            );

            Activity::log('Updated topic: ' . $title, 'topics');
            return $this->success('Topic updated.', ['topic_id' => $id]);
        }

        if ($sortOrder === 0) {
            $sortOrder = (int) Database::value(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM topics WHERE ' . ($batchId > 0 ? 'batch_id = ?' : 'batch_id IS NULL'),
                $batchId > 0 ? [$batchId] : []
            );
        }

        $id = Database::insert(
            "INSERT INTO topics (batch_id, title, description, sort_order, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$batchId > 0 ? $batchId : null, $title, $description !== '' ? $description : null, $sortOrder, $status, $this->userId()]
        );

        Activity::log('Created topic: ' . $title, 'topics');
        return $this->success('Topic created.', ['topic_id' => $id]);
    }

    /** Persist a new ordering for topics. */
    public function reorder()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $order = json_decode((string) $this->input('order', '[]'), true);
        if (!is_array($order) || !$order) {
            return $this->error('Nothing to reorder.');
        }

        foreach (array_values($order) as $index => $topicId) {
            Database::write('UPDATE topics SET sort_order = ? WHERE id = ?', [$index + 1, (int) $topicId]);
        }

        Activity::log('Reordered ' . count($order) . ' topics', 'topics');
        return $this->success('Order saved.');
    }

    public function delete()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('topic_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No topics selected.');
        }

        $placeholders = Database::placeholders($ids);
        $files = Database::all("SELECT file_path FROM topic_files WHERE topic_id IN ($placeholders)", $ids);
        foreach ($files as $file) {
            $this->removeStoredFile($file['file_path']);
        }

        Database::write("DELETE FROM topic_files WHERE topic_id IN ($placeholders)", $ids);
        Database::write("UPDATE assignments SET topic_id = NULL WHERE topic_id IN ($placeholders)", $ids);
        $deleted = Database::write("DELETE FROM topics WHERE id IN ($placeholders)", $ids);

        Activity::log('Deleted ' . $deleted . ' topic(s)', 'topics');
        return $this->success($deleted . ' topic(s) deleted.');
    }

    /** Upload study material into a topic. */
    public function upload()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $topicId = $this->int('topic_id');
        $file = $this->file('file');
        if (!$topicId || !$file || empty($file['name'])) {
            return $this->error('Select a topic and a file.');
        }

        $topic = Database::first('SELECT id, title FROM topics WHERE id = ?', [$topicId]);
        if (!$topic) {
            return $this->error('Topic not found.');
        }

        $saved = Uploader::store($file, 'topics', ['topic_title' => $topic['title']]);
        if (!$saved) {
            return $this->error('File could not be saved. Check the type and size limit.');
        }

        $id = Database::insert(
            "INSERT INTO topic_files (topic_id, file_name, file_path, file_size, file_type, status, uploaded_by, uploaded_at)
             VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())",
            [
                $topicId,
                $file['name'],
                $saved,
                (int) $file['size'],
                Uploader::mime($file['tmp_name'], file_ext($file['name'])),
                $this->userId(),
            ]
        );

        Activity::log('Uploaded "' . $file['name'] . '" to topic: ' . $topic['title'], 'topics');
        return $this->success('File uploaded.', [
            'file_id' => $id,
            'file_name' => $file['name'],
            'file_path' => $saved,
            'file_size' => (int) $file['size'],
        ]);
    }

    public function deleteFile()
    {
        if (!$this->isStaff()) {
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
        $files = Database::all("SELECT file_name, file_path FROM topic_files WHERE id IN ($placeholders)", $ids);
        foreach ($files as $file) {
            $this->removeStoredFile($file['file_path']);
        }

        $deleted = Database::write("DELETE FROM topic_files WHERE id IN ($placeholders)", $ids);
        Activity::log('Deleted ' . $deleted . ' topic file(s)', 'topics');
        return $this->success($deleted . ' file(s) deleted.');
    }

    /** Hide or restore a file without deleting it. */
    public function toggleFile()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $id = $this->int('file_id');
        $status = $this->string('status', 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->error('Invalid status.');
        }

        Database::write('UPDATE topic_files SET status = ? WHERE id = ?', [$status, $id]);
        $this->flash('success', 'File visibility updated.');
        return $this->success('File visibility updated.', ['status' => $status]);
    }

    /** Remove the stored copy of an upload (best effort). */
    private function removeStoredFile($relative)
    {
        $absolute = Uploader::resolve($relative);
        if ($absolute && is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
