<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * FeedbackApi — anonymous course feedback collection.
 *
 * Students submit feedback without their identity being stored: a
 * deterministic token (session + user, hashed) is the only link, so the
 * platform can prevent double submissions while keeping entries anonymous.
 */
class FeedbackApi extends Controller
{
    /** Category master list shared by the admin, teacher and student screens. */
    public static function categories()
    {
        return [
            'teaching' => ['label' => 'Teaching quality', 'icon' => 'graduation'],
            'curriculum' => ['label' => 'Curriculum & content', 'icon' => 'book'],
            'assignments' => ['label' => 'Assignments & tests', 'icon' => 'clipboard'],
            'communication' => ['label' => 'Teacher communication', 'icon' => 'message'],
            'scheduling' => ['label' => 'Schedule & timing', 'icon' => 'clock'],
            'resources' => ['label' => 'Learning resources', 'icon' => 'folder'],
            'organization' => ['label' => 'Organization & management', 'icon' => 'layers'],
            'facilities' => ['label' => 'Facilities & environment', 'icon' => 'map-pin'],
            'platform' => ['label' => 'LMS platform', 'icon' => 'settings'],
            'suggestions' => ['label' => 'Suggestions & ideas', 'icon' => 'star'],
            'general' => ['label' => 'General', 'icon' => 'info'],
        ];
    }

    /** Deterministic anonymous token for a (session, user) pair. */
    public static function tokenFor($sessionId, $userId)
    {
        return hash('sha256', 'eduflow-fb|' . (int) $sessionId . '|' . (int) $userId . '|' . APP_KEY);
    }

    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            $this->error('Unauthorized', [], 401);
        }
    }

    private function isStaffMember()
    {
        return $this->isAdmin() || $this->isTeacher();
    }

    /* ── Sessions ─────────────────────────────────────────────── */

    /**
     * Feedback rounds. Admin sees every batch, teachers see their own,
     * students receive the active round of each of their batches.
     */
    public function sessions()
    {
        if ($this->role() === 'student') {
            return $this->studentSessions();
        }
        if (!$this->isStaffMember()) {
            return $this->error('Access denied.', [], 403);
        }

        $where = ['1 = 1'];
        $params = [];
        if (!$this->isAdmin()) {
            $where[] = 'fs.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        }

        $rows = Database::all(
            'SELECT fs.*, b.name AS batch_name, c.title AS course_title,
                    (SELECT COUNT(*) FROM feedback_entries fe WHERE fe.session_id = fs.id) AS entry_count,
                    (SELECT COUNT(*) FROM feedback_entries fe WHERE fe.session_id = fs.id AND fe.is_reviewed = 0) AS unread_count,
                    (SELECT COUNT(*) FROM feedback_tokens ft WHERE ft.session_id = fs.id) AS submitter_count,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = fs.batch_id) AS enrolled_count
             FROM feedback_sessions fs
             JOIN batches b ON b.id = fs.batch_id
             JOIN courses c ON c.id = b.course_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY fs.is_active DESC, fs.created_at DESC',
            $params
        );

        return $this->success('OK', ['sessions' => $rows, 'categories' => self::categories()]);
    }

    private function studentSessions()
    {
        $rows = Database::all(
            'SELECT b.id AS batch_id, b.name AS batch_name, c.title AS course_title,
                    fs.id AS session_id, fs.title AS session_title, fs.description AS session_description,
                    fs.is_active AS feedback_active, fs.activated_at
             FROM batch_students bs
             JOIN batches b ON bs.batch_id = b.id
             JOIN courses c ON b.course_id = c.id
             LEFT JOIN feedback_sessions fs ON fs.batch_id = b.id AND fs.is_active = 1
             WHERE bs.student_id = ?
             ORDER BY fs.is_active DESC, c.title, b.name',
            [$this->userId()]
        );

        foreach ($rows as &$row) {
            $row['already_submitted'] = false;
            if (!empty($row['session_id']) && !empty($row['feedback_active'])) {
                $token = self::tokenFor((int) $row['session_id'], $this->userId());
                $row['already_submitted'] = (bool) Database::value(
                    'SELECT COUNT(*) FROM feedback_tokens WHERE session_id = ? AND token_hash = ?',
                    [(int) $row['session_id'], $token]
                );
            }
        }

        return $this->success('OK', ['batches' => $rows, 'categories' => self::categories()]);
    }

    /** Create (or reuse) the feedback round belonging to a batch. */
    public function createSession()
    {
        if (!$this->isAdmin()) {
            return $this->error('Access denied.', [], 403);
        }

        $batchId = $this->int('batch_id');
        $title = $this->string('title', 'Batch feedback');
        $description = $this->string('description');

        if (!$batchId) {
            return $this->error('Select a batch.');
        }
        if (!Database::first('SELECT id FROM batches WHERE id = ?', [$batchId])) {
            return $this->error('Batch not found.');
        }

        $existing = Database::first('SELECT id FROM feedback_sessions WHERE batch_id = ? ORDER BY id DESC LIMIT 1', [$batchId]);
        if ($existing) {
            Database::write(
                'UPDATE feedback_sessions SET title = ?, description = ? WHERE id = ?',
                [$title, $description !== '' ? $description : null, (int) $existing['id']]
            );
            Activity::log('Updated feedback round for batch ' . $batchId, 'feedback');
            return $this->success('Feedback round updated.', ['session_id' => (int) $existing['id']]);
        }

        $id = Database::insert(
            'INSERT INTO feedback_sessions (batch_id, title, description, is_active, activated_by, created_at)
             VALUES (?, ?, ?, 0, ?, NOW())',
            [$batchId, $title, $description !== '' ? $description : null, $this->userId()]
        );

        Activity::log('Created feedback round for batch ' . $batchId, 'feedback');
        return $this->success('Feedback round created. Activate it to start collecting responses.', ['session_id' => $id]);
    }

    /** Open or close a feedback round (one open round per batch). */
    public function toggleSession()
    {
        if (!$this->isAdmin()) {
            return $this->error('Access denied.', [], 403);
        }

        $id = $this->int('session_id');
        $session = Database::first('SELECT * FROM feedback_sessions WHERE id = ?', [$id]);
        if (!$session) {
            return $this->error('Feedback round not found.');
        }

        if ((int) $session['is_active'] !== 1) {
            Database::write(
                'UPDATE feedback_sessions SET is_active = 0, deactivated_at = NOW() WHERE batch_id = ? AND is_active = 1',
                [(int) $session['batch_id']]
            );
            Database::write(
                'UPDATE feedback_sessions SET is_active = 1, activated_by = ?, activated_at = NOW(), deactivated_at = NULL WHERE id = ?',
                [$this->userId(), $id]
            );
            Activity::log('Opened feedback round: ' . $session['title'], 'feedback');
            return $this->success('Feedback is now open for students.', ['is_active' => 1]);
        }

        Database::write('UPDATE feedback_sessions SET is_active = 0, deactivated_at = NOW() WHERE id = ?', [$id]);
        Activity::log('Closed feedback round: ' . $session['title'], 'feedback');
        return $this->success('Feedback collection closed.', ['is_active' => 0]);
    }

    public function deleteSession()
    {
        if (!$this->isAdmin()) {
            return $this->error('Access denied.', [], 403);
        }

        $id = $this->int('session_id');
        Database::write('DELETE FROM feedback_entries WHERE session_id = ?', [$id]);
        Database::write('DELETE FROM feedback_tokens WHERE session_id = ?', [$id]);
        $deleted = Database::write('DELETE FROM feedback_sessions WHERE id = ?', [$id]);

        Activity::log('Deleted a feedback round', 'feedback');
        return $this->success($deleted ? 'Feedback round deleted.' : 'Nothing to delete.');
    }

    /* ── Entries ──────────────────────────────────────────────── */

    /** Paginated anonymous entries (admin / teacher). */
    public function list()
    {
        if (!$this->isStaffMember()) {
            return $this->error('Access denied.', [], 403);
        }

        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $sessionId = $this->int('session_id');
        $batchId = $this->int('batch_id');
        $status = $this->string('status');
        $search = $this->string('search');

        $where = [];
        $params = [];
        if (!$this->isAdmin()) {
            $where[] = 'fe.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        }
        if ($sessionId > 0) {
            $where[] = 'fe.session_id = ?';
            $params[] = $sessionId;
        }
        if ($batchId > 0) {
            $where[] = 'fe.batch_id = ?';
            $params[] = $batchId;
        }
        if ($status === 'new') {
            $where[] = 'fe.is_reviewed = 0';
        } elseif ($status === 'reviewed') {
            $where[] = 'fe.is_reviewed = 1';
        }
        if ($search !== '') {
            $where[] = 'fe.content LIKE ?';
            $params[] = Database::like($search);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM feedback_entries fe $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT fe.*, fs.title AS session_title, b.name AS batch_name, c.title AS course_title
             FROM feedback_entries fe
             LEFT JOIN feedback_sessions fs ON fs.id = fe.session_id
             LEFT JOIN batches b ON b.id = fe.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             $whereSql
             ORDER BY fe.submitted_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $categories = self::categories();
        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['categories'], true);
            $row['category_keys'] = is_array($decoded) ? $decoded : ['general'];
            $row['category_labels'] = array_map(function ($key) use ($categories) {
                return isset($categories[$key]) ? $categories[$key]['label'] : ucfirst($key);
            }, $row['category_keys']);
        }

        return $this->success('OK', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'categories' => $categories,
        ]);
    }

    /** Student submission (anonymous — only the token is stored). */
    public function submit()
    {
        if ($this->role() !== 'student') {
            return $this->error('Access denied.', [], 403);
        }

        $sessionId = $this->int('session_id');
        $content = $this->string('content');
        $selected = $this->input('categories', []);
        if (is_string($selected)) {
            $decoded = json_decode($selected, true);
            $selected = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $selected)));
        }
        $selected = array_values(array_intersect(array_keys(self::categories()), (array) $selected));

        $validator = Validator::make(
            ['content' => $content],
            ['content' => 'required|min:10|max:5000'],
            ['content' => 'Feedback']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }
        if (!$selected) {
            return $this->error('Choose at least one category.');
        }

        $session = Database::first('SELECT * FROM feedback_sessions WHERE id = ? AND is_active = 1', [$sessionId]);
        if (!$session) {
            return $this->error('This feedback round is not open.');
        }

        $enrolled = (bool) Database::value(
            'SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [(int) $session['batch_id'], $this->userId()]
        );
        if (!$enrolled) {
            return $this->error('You are not enrolled in this batch.', [], 403);
        }

        $token = self::tokenFor($sessionId, $this->userId());
        $already = (bool) Database::value(
            'SELECT COUNT(*) FROM feedback_tokens WHERE session_id = ? AND token_hash = ?',
            [$sessionId, $token]
        );
        if ($already) {
            return $this->error('You have already submitted feedback for this batch.');
        }

        Database::write(
            'INSERT INTO feedback_entries (session_id, batch_id, content, categories, is_reviewed, submitted_at)
             VALUES (?, ?, ?, ?, 0, NOW())',
            [$sessionId, (int) $session['batch_id'], $content, json_encode($selected)]
        );
        Database::write(
            'INSERT INTO feedback_tokens (session_id, token_hash, submitted_at) VALUES (?, ?, NOW())',
            [$sessionId, $token]
        );

        return $this->success('Thank you — your feedback was submitted anonymously.');
    }

    /** Has the signed-in student already submitted this round? */
    public function myStatus()
    {
        $sessionId = $this->int('session_id');
        if (!$sessionId) {
            return $this->error('Session required.');
        }

        $token = self::tokenFor($sessionId, $this->userId());
        $submitted = (bool) Database::value(
            'SELECT COUNT(*) FROM feedback_tokens WHERE session_id = ? AND token_hash = ?',
            [$sessionId, $token]
        );

        return $this->success('OK', ['submitted' => $submitted]);
    }

    public function markReviewed()
    {
        if (!$this->isStaffMember()) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('entry_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No entries selected.');
        }

        $placeholders = Database::placeholders($ids);
        $reviewed = $this->bool('reviewed', true) ? 1 : 0;
        $updated = Database::write("UPDATE feedback_entries SET is_reviewed = ? WHERE id IN ($placeholders)", array_merge([$reviewed], $ids));
        return $this->success($updated . ' entry(ies) ' . ($reviewed ? 'marked as reviewed.' : 'reopened for review.'));
    }

    public function deleteEntry()
    {
        if (!$this->isAdmin()) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('entry_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No entries selected.');
        }

        $deleted = Database::write(
            'DELETE FROM feedback_entries WHERE id IN (' . Database::placeholders($ids) . ')',
            $ids
        );
        Activity::log('Deleted ' . $deleted . ' feedback entry(ies)', 'feedback');
        return $this->success($deleted . ' feedback entry(ies) deleted.');
    }

    /** Category frequency summary for the reporting panels. */
    public function stats()
    {
        if (!$this->isStaffMember()) {
            return $this->error('Access denied.', [], 403);
        }

        $sessionId = $this->int('session_id');
        $where = '';
        $params = [];
        if ($sessionId > 0) {
            $where = 'WHERE session_id = ?';
            $params[] = $sessionId;
        }

        $rows = Database::all("SELECT categories FROM feedback_entries $where", $params);
        $counts = array_fill_keys(array_keys(self::categories()), 0);
        foreach ($rows as $row) {
            $keys = json_decode((string) $row['categories'], true);
            foreach ((array) $keys as $key) {
                if (isset($counts[$key])) {
                    $counts[$key]++;
                }
            }
        }

        arsort($counts);
        return $this->success('OK', ['counts' => $counts, 'total' => count($rows)]);
    }
}
