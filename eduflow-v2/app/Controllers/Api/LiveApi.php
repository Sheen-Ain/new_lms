<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Services\JaasService;
use App\Services\PusherService;

/**
 * LiveApi — Jitsi (JaaS) live classroom sessions.
 *
 * Flow mirrors the original application:
 *   teacher starts  -> session 'waiting'  (students see "starting soon")
 *   teacher opens   -> session 'active'   (students can join)
 *   teacher ends    -> session 'ended', all attendees closed off
 * Attendance is tracked in session_attendees so reports stay accurate even
 * when the browser drops the connection.
 */
class LiveApi extends Controller
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

    /** Can the signed-in user host this batch? */
    private function canHost($batchId)
    {
        if ($this->isAdmin()) {
            return true;
        }
        return (bool) Database::value(
            'SELECT COUNT(*) FROM batch_teachers WHERE batch_id = ? AND teacher_id = ?',
            [(int) $batchId, $this->userId()]
        );
    }

    /** Can the signed-in user attend this session? */
    private function canAttend($batchId)
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->role() === 'teacher') {
            return $this->canHost($batchId);
        }
        return (bool) Database::value(
            'SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [(int) $batchId, $this->userId()]
        );
    }

    private function sessionOrFail($id)
    {
        $session = Database::first(
            'SELECT ls.*, b.name AS batch_name, b.course_id, c.title AS course_title, u.full_name AS host_name
             FROM live_sessions ls
             JOIN batches b ON b.id = ls.batch_id
             JOIN courses c ON c.id = b.course_id
             JOIN users u ON u.id = ls.started_by
             WHERE ls.id = ?',
            [$id]
        );
        if (!$session) {
            $this->error('Session not found.');
        }
        return $session;
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 12)));
        $search = $this->string('search');
        $status = $this->string('status');
        $batchId = $this->int('batch_id');

        $where = [];
        $params = [];

        if ($this->role() === 'teacher') {
            $where[] = 'ls.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)';
            $params[] = $this->userId();
        } elseif ($this->role() === 'student') {
            $where[] = 'ls.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)';
            $params[] = $this->userId();
        }

        if ($status !== '') {
            $where[] = 'ls.status = ?';
            $params[] = $status;
        }
        if ($batchId > 0) {
            $where[] = 'ls.batch_id = ?';
            $params[] = $batchId;
        }
        if ($search !== '') {
            $where[] = '(ls.title LIKE ? OR ls.room_name LIKE ? OR b.name LIKE ? OR c.title LIKE ?)';
            $like = Database::like($search);
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value(
            "SELECT COUNT(*) FROM live_sessions ls
             JOIN batches b ON b.id = ls.batch_id
             JOIN courses c ON c.id = b.course_id
             $whereSql",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT ls.id, ls.batch_id, ls.room_name, ls.title, ls.status, ls.started_at, ls.ended_at,
                    b.name AS batch_name, c.title AS course_title, u.full_name AS host_name,
                    (SELECT COUNT(*) FROM session_attendees sa WHERE sa.session_id = ls.id AND sa.left_at IS NULL) AS live_count,
                    (SELECT COUNT(*) FROM session_attendees sa WHERE sa.session_id = ls.id) AS total_joined,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = ls.batch_id) AS total_students
             FROM live_sessions ls
             JOIN batches b ON b.id = ls.batch_id
             JOIN courses c ON c.id = b.course_id
             JOIN users u ON u.id = ls.started_by
             $whereSql
             ORDER BY ls.status = 'active' DESC, ls.status = 'waiting' DESC, ls.started_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    /** Create a new session for a batch (status: waiting). */
    public function start()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $batchId = $this->int('batch_id');
        $title = $this->string('title', 'Live class');
        if (!$batchId) {
            return $this->error('Select a batch first.');
        }
        if (!$this->canHost($batchId)) {
            return $this->error('You are not assigned to that batch.', [], 403);
        }

        $existing = Database::first(
            "SELECT id, room_name, status FROM live_sessions WHERE batch_id = ? AND status IN ('waiting','active') ORDER BY id DESC LIMIT 1",
            [$batchId]
        );
        if ($existing) {
            return $this->success('A session is already open for this batch.', [
                'session_id' => (int) $existing['id'],
                'room_name' => $existing['room_name'],
                'status' => $existing['status'],
                'reused' => true,
            ]);
        }

        $room = 'eduflow-' . $batchId . '-' . strtolower(bin2hex(random_bytes(4)));
        $sessionId = Database::insert(
            "INSERT INTO live_sessions (batch_id, room_name, title, started_by, status, started_at)
             VALUES (?, ?, ?, ?, 'waiting', NOW())",
            [$batchId, $room, $title !== '' ? mb_substr($title, 0, 200) : 'Live class', $this->userId()]
        );

        Activity::log('Started live session "' . $title . '"', 'live_sessions');
        PusherService::trigger('batch-' . $batchId, 'live:started', [
            'session_id' => $sessionId,
            'title' => $title,
            'status' => 'waiting',
        ]);

        return $this->success('Session created. Open it when you are ready for students to join.', [
            'session_id' => $sessionId,
            'room_name' => $room,
            'status' => 'waiting',
            'reused' => false,
        ]);
    }

    /** Move a waiting session to active. */
    public function open()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $session = $this->sessionOrFail($this->int('session_id'));
        if (!$this->canHost($session['batch_id'])) {
            return $this->error('Access denied.', [], 403);
        }
        if ($session['status'] === 'ended') {
            return $this->error('This session has already ended.');
        }

        Database::write("UPDATE live_sessions SET status = 'active', started_at = NOW() WHERE id = ?", [(int) $session['id']]);
        Activity::log('Opened live session "' . $session['title'] . '"', 'live_sessions');
        PusherService::trigger('batch-' . $session['batch_id'], 'live:opened', [
            'session_id' => (int) $session['id'],
            'status' => 'active',
        ]);

        return $this->success('Session is live.', ['status' => 'active']);
    }

    /** End a session and close open attendance rows. */
    public function end()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $session = $this->sessionOrFail($this->int('session_id'));
        if (!$this->canHost($session['batch_id'])) {
            return $this->error('Access denied.', [], 403);
        }

        Database::write("UPDATE live_sessions SET status = 'ended', ended_at = NOW() WHERE id = ?", [(int) $session['id']]);
        Database::write('UPDATE session_attendees SET left_at = NOW() WHERE session_id = ? AND left_at IS NULL', [(int) $session['id']]);

        Activity::log('Ended live session "' . $session['title'] . '"', 'live_sessions');
        PusherService::trigger('batch-' . $session['batch_id'], 'live:ended', [
            'session_id' => (int) $session['id'],
            'status' => 'ended',
        ]);

        return $this->success('Session ended.', ['status' => 'ended']);
    }

    /** Register attendance and hand the client everything it needs to join. */
    public function join()
    {
        $sessionId = $this->int('session_id');
        $session = $this->sessionOrFail($sessionId);

        if (!$this->canAttend($session['batch_id'])) {
            return $this->error('You are not a member of this class.', [], 403);
        }
        if ($session['status'] === 'ended') {
            return $this->error('This class has already ended.');
        }
        if ($session['status'] === 'waiting' && !$this->isStaff()) {
            return $this->error('The teacher has not started the class yet. Please wait.');
        }

        Database::write(
            'INSERT INTO session_attendees (session_id, user_id, joined_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE joined_at = NOW(), left_at = NULL',
            [$sessionId, $this->userId()]
        );

        $user = $this->user();
        $token = JaasService::token(
            $session['room_name'],
            $user['full_name'],
            $user['email'],
            !empty($user['profile_picture']) ? upload_url('profiles/' . $user['profile_picture']) : '',
            $this->isStaff()
        );

        return $this->success('Joined the class.', [
            'session' => [
                'id' => (int) $session['id'],
                'room_name' => $session['room_name'],
                'title' => $session['title'],
                'status' => $session['status'],
                'batch_name' => $session['batch_name'],
                'course_title' => $session['course_title'],
                'host_name' => $session['host_name'],
            ],
            'moderator' => $this->isStaff(),
            'token' => $token,
            'app_id' => defined('JAAS_APP_ID') ? JAAS_APP_ID : '',
        ]);
    }

    /** Mark the current user as having left. */
    public function leave()
    {
        $sessionId = $this->int('session_id');
        Database::write(
            'UPDATE session_attendees SET left_at = NOW() WHERE session_id = ? AND user_id = ? AND left_at IS NULL',
            [$sessionId, $this->userId()]
        );
        return $this->success('Left the class.');
    }

    /** Lightweight polling endpoint for the session room. */
    public function status()
    {
        $sessionId = $this->int('session_id');
        $row = Database::first(
            "SELECT ls.id, ls.status, ls.ended_at,
                    (SELECT COUNT(*) FROM session_attendees sa WHERE sa.session_id = ls.id AND sa.left_at IS NULL) AS live_count,
                    (SELECT COUNT(*) FROM session_attendees sa WHERE sa.session_id = ls.id) AS total_joined
             FROM live_sessions ls WHERE ls.id = ?",
            [$sessionId]
        );
        if (!$row) {
            return $this->error('Session not found.');
        }

        return $this->success('OK', [
            'status' => $row['status'],
            'ended_at' => $row['ended_at'],
            'live_count' => (int) $row['live_count'],
            'total_joined' => (int) $row['total_joined'],
        ]);
    }

    /** Attendance list for a session. */
    public function participants()
    {
        $sessionId = $this->int('session_id');
        $session = $this->sessionOrFail($sessionId);
        if (!$this->canAttend($session['batch_id'])) {
            return $this->error('Access denied.', [], 403);
        }

        $rows = Database::all(
            'SELECT sa.user_id, sa.joined_at, sa.left_at, u.full_name, u.user_id_number, u.`current_role`
             FROM session_attendees sa
             JOIN users u ON u.id = sa.user_id
             WHERE sa.session_id = ?
             ORDER BY sa.joined_at ASC',
            [$sessionId]
        );

        return $this->success('OK', ['rows' => $rows, 'total' => count($rows)]);
    }

    /** Remove a session from the history (staff only). */
    public function delete()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $this->string('ids')))));
        if (!$ids && ($single = $this->int('session_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No sessions selected.');
        }

        $placeholders = Database::placeholders($ids);
        Database::write("DELETE FROM session_attendees WHERE session_id IN ($placeholders)", $ids);
        $deleted = Database::write("DELETE FROM live_sessions WHERE id IN ($placeholders)", $ids);

        Activity::log('Deleted ' . $deleted . ' live session(s)', 'live_sessions');
        return $this->success($deleted . ' session(s) deleted.');
    }
}
