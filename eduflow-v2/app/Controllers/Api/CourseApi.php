<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Support\Uploader;

/**
 * CourseApi — admin course CRUD (with optional thumbnail).
 */
class CourseApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId() || !$this->isAdmin()) {
            $this->error('Access denied.', [], 403);
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 10)));
        $search = $this->string('search');
        $status = $this->string('status');

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = 'c.title LIKE ?';
            $params[] = Database::like($search);
        }
        if ($status !== '') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM courses c $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT c.*, u.full_name AS creator_name,
                    (SELECT COUNT(*) FROM batches WHERE course_id = c.id) AS batch_count,
                    (SELECT COUNT(*) FROM batches WHERE course_id = c.id AND status = 'active') AS active_batch_count
             FROM courses c
             LEFT JOIN users u ON u.id = c.created_by
             $whereSql
             ORDER BY c.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('course_id');
        $row = Database::first('SELECT * FROM courses WHERE id = ?', [$id]);
        if (!$row) {
            return $this->error('Course not found.');
        }
        return $this->success('OK', ['course' => $row]);
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
        $title = $this->string('title');
        $description = $this->string('description');
        $status = $this->string('status', 'active');

        $validator = Validator::make(
            ['title' => $title, 'status' => $status],
            ['title' => 'required|min:2|max:200', 'status' => 'in:active,inactive,archived'],
            ['title' => 'Title']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        $thumbnail = null;
        $file = $this->file('thumbnail');
        if ($file && !empty($file['name'])) {
            $thumbnail = Uploader::store($file, 'topics', [
                'max_mb' => THUMB_MAX_MB,
                'allowed' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ]);
            if (!$thumbnail) {
                return $this->error('Thumbnail upload failed. Use a JPG/PNG under ' . THUMB_MAX_MB . 'MB.');
            }
        }

        if ($isUpdate) {
            $id = $this->int('course_id');
            if (!$id) {
                return $this->error('Invalid course.');
            }

            if ($thumbnail) {
                Database::write(
                    'UPDATE courses SET title = ?, description = ?, thumbnail = ?, status = ? WHERE id = ?',
                    [$title, $description, $thumbnail, $status, $id]
                );
            } else {
                Database::write(
                    'UPDATE courses SET title = ?, description = ?, status = ? WHERE id = ?',
                    [$title, $description, $status, $id]
                );
            }

            Activity::log('Updated course: ' . $title, 'courses');
            return $this->success('Course updated.');
        }

        $id = Database::insert(
            'INSERT INTO courses (title, description, thumbnail, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [$title, $description, $thumbnail, $status, $this->userId()]
        );

        Activity::log('Created course: ' . $title, 'courses');
        return $this->success('Course created.', ['course_id' => $id]);
    }

    public function delete()
    {
        $ids = array_filter(array_map('intval', explode(',', $this->string('ids'))));
        if (!$ids && ($single = $this->int('course_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No courses selected.');
        }

        $placeholders = Database::placeholders($ids);
        $titles = Database::column("SELECT title FROM courses WHERE id IN ($placeholders)", $ids);

        $batchIds = Database::column("SELECT id FROM batches WHERE course_id IN ($placeholders)", $ids);
        if ($batchIds) {
            $bp = Database::placeholders($batchIds);
            Database::write("DELETE FROM batch_students WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM batch_teachers WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM assignments WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM announcements WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM live_sessions WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM topics WHERE batch_id IN ($bp)", $batchIds);
            Database::write("DELETE FROM batches WHERE id IN ($bp)", $batchIds);
        }

        $deleted = Database::write("DELETE FROM courses WHERE id IN ($placeholders)", $ids);
        Activity::log('Deleted ' . $deleted . ' course(s): ' . implode(', ', $titles), 'courses');

        return $this->success($deleted . ' course(s) deleted.');
    }
}

