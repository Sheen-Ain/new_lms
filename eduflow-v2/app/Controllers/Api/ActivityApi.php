<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;

/**
 * ActivityApi — the audit trail (activity_logs).
 *
 * Admins see every entry; teachers see the actions of accounts that share a
 * batch with them, which keeps the log useful without leaking institute-wide
 * data to non-admin roles.
 */
class ActivityApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            $this->error('Unauthorized', [], 401);
        }
        if (!in_array($this->role(), ['admin', 'teacher'], true)) {
            $this->error('Access denied.', [], 403);
        }
    }

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 20)));
        $search = $this->string('search');
        $section = $this->string('section');
        $userId = $this->int('user_id');
        $from = $this->string('from');
        $to = $this->string('to');

        $where = [];
        $params = [];

        if ($this->role() === 'teacher') {
            $where[] = '(al.user_id = ?
                          OR al.user_id IN (
                               SELECT bs.student_id FROM batch_students bs
                               WHERE bs.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)
                          ))';
            $params[] = $this->userId();
            $params[] = $this->userId();
        }

        if ($userId > 0) {
            $where[] = 'al.user_id = ?';
            $params[] = $userId;
        }
        if ($section !== '') {
            $where[] = 'al.section = ?';
            $params[] = $section;
        }
        if ($search !== '') {
            $where[] = '(al.action LIKE ? OR u.full_name LIKE ?)';
            $params[] = Database::like($search);
            $params[] = Database::like($search);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'al.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'al.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value(
            "SELECT COUNT(*) FROM activity_logs al JOIN users u ON u.id = al.user_id $whereSql",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT al.*, u.full_name, u.profile_picture, u.user_id_number, u.`current_role`
             FROM activity_logs al
             JOIN users u ON u.id = al.user_id
             $whereSql
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return $this->success('OK', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'sections' => Activity::sections(),
        ]);
    }

    /** Wipe the audit trail (admin only — used before a fresh term). */
    public function clear()
    {
        if ($this->role() !== 'admin') {
            return $this->error('Access denied.', [], 403);
        }

        $count = (int) Database::value('SELECT COUNT(*) FROM activity_logs');
        Activity::clear();
        Activity::log('Cleared ' . $count . ' activity log entries', 'system');

        return $this->success($count . ' log entr' . ($count === 1 ? 'y' : 'ies') . ' cleared.');
    }

    /** Distinct users appearing in the log (filter dropdown). */
    public function users()
    {
        $rows = Database::all(
            'SELECT DISTINCT u.id, u.full_name, u.user_id_number
             FROM activity_logs al JOIN users u ON u.id = al.user_id
             ORDER BY u.full_name'
        );
        return $this->success('OK', ['users' => $rows]);
    }
}
