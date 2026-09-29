<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * AnnouncementApi — batch and institute-wide notices.
 *
 * Notices without a batch_id are visible to everyone; batch notices are
 * scoped to the enrolled students and assigned teachers of that batch.
 */
class AnnouncementApi extends Controller
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

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 10)));
        $search = $this->string('search');
        $batchId = (string) $this->input('batch_id', '');
        $priority = $this->string('priority');
        $status = $this->string('status');

        $where = [];
        $params = [];

        if ($this->role() === 'student') {
            $where[] = "a.status = 'published'";
            $where[] = '(a.batch_id IS NULL OR a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?))';
            $params[] = $this->userId();
        } elseif ($this->role() === 'teacher') {
            $where[] = '(a.batch_id IS NULL
                          OR a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)
                          OR a.created_by = ?)';
            $params[] = $this->userId();
            $params[] = $this->userId();
        }

        if ($batchId === '-1') {
            $where[] = 'a.batch_id IS NULL';
        } elseif ((int) $batchId > 0) {
            $where[] = 'a.batch_id = ?';
            $params[] = (int) $batchId;
        }
        if ($priority !== '') {
            $where[] = 'a.priority = ?';
            $params[] = $priority;
        }
        if ($status !== '' && $this->role() !== 'student') {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
            $params[] = Database::like($search);
            $params[] = Database::like($search);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM announcements a $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT a.*, b.name AS batch_name, u.full_name AS creator_name
             FROM announcements a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN users u ON u.id = a.created_by
             $whereSql
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('announcement_id');
        $row = Database::first(
            'SELECT a.*, b.name AS batch_name, u.full_name AS creator_name
             FROM announcements a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.id = ?',
            [$id]
        );
        if (!$row) {
            return $this->error('Announcement not found.');
        }
        return $this->success('OK', ['announcement' => $row]);
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
        $content = $this->string('content');
        $priority = $this->string('priority', 'normal');
        $status = $this->string('status', 'published');
        $batchId = $this->int('batch_id');
        $isPinned = $this->bool('is_pinned') ? 1 : 0;

        $validator = Validator::make(
            ['title' => $title, 'content' => $content, 'priority' => $priority, 'status' => $status],
            [
                'title' => 'required|min:3|max:200',
                'content' => 'required|min:5',
                'priority' => 'in:normal,important,urgent',
                'status' => 'in:published,draft',
            ],
            ['title' => 'Title', 'content' => 'Message']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        if ($this->role() === 'teacher' && $batchId > 0) {
            $owned = (bool) Database::value(
                'SELECT COUNT(*) FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?',
                [$batchId, $this->userId()]
            );
            if (!$owned) {
                return $this->error('You can only publish to your own batches.', [], 403);
            }
        }

        if ($isUpdate) {
            $id = $this->int('announcement_id');
            $existing = Database::first('SELECT id, created_by FROM announcements WHERE id = ?', [$id]);
            if (!$existing) {
                return $this->error('Announcement not found.');
            }
            if ($this->role() === 'teacher' && (int) $existing['created_by'] !== $this->userId()) {
                return $this->error('You can only edit announcements you created.', [], 403);
            }

            Database::write(
                'UPDATE announcements
                 SET batch_id = ?, title = ?, content = ?, priority = ?, is_pinned = ?, status = ?, updated_at = NOW()
                 WHERE id = ?',
                [$batchId > 0 ? $batchId : null, $title, $content, $priority, $isPinned, $status, $id]
            );

            Activity::log('Updated announcement: ' . $title, 'announcements');
            return $this->success('Announcement updated.', ['announcement_id' => $id]);
        }

        $id = Database::insert(
            "INSERT INTO announcements (batch_id, title, content, priority, is_pinned, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$batchId > 0 ? $batchId : null, $title, $content, $priority, $isPinned, $status, $this->userId()]
        );

        Activity::log('Published announcement: ' . $title, 'announcements');
        return $this->success('Announcement published.', ['announcement_id' => $id]);
    }

    /** Pin or unpin a notice so it stays at the top of every feed. */
    public function pin()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $id = $this->int('announcement_id');
        $row = Database::first('SELECT id, is_pinned, title FROM announcements WHERE id = ?', [$id]);
        if (!$row) {
            return $this->error('Announcement not found.');
        }

        $next = (int) $row['is_pinned'] === 1 ? 0 : 1;
        Database::write('UPDATE announcements SET is_pinned = ? WHERE id = ?', [$next, $id]);
        Activity::log(($next ? 'Pinned' : 'Unpinned') . ' announcement: ' . $row['title'], 'announcements');

        return $this->success($next ? 'Announcement pinned.' : 'Announcement unpinned.', ['is_pinned' => $next]);
    }

    public function delete()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('announcement_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No announcements selected.');
        }

        if ($this->role() === 'teacher') {
            $owned = Database::column(
                'SELECT id FROM announcements WHERE id IN (' . Database::placeholders($ids) . ') AND created_by = ?',
                array_merge($ids, [$this->userId()])
            );
            $ids = array_map('intval', $owned);
            if (!$ids) {
                return $this->error('You can only delete announcements you created.', [], 403);
            }
        }

        $deleted = Database::write(
            'DELETE FROM announcements WHERE id IN (' . Database::placeholders($ids) . ')',
            $ids
        );

        Activity::log('Deleted ' . $deleted . ' announcement(s)', 'announcements');
        return $this->success($deleted . ' announcement(s) deleted.');
    }
}
