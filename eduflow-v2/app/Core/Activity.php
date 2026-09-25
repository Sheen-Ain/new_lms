<?php

namespace App\Core;

/**
 * Activity — the audit trail used across every module (activity_logs).
 */
class Activity
{
    /** @var int|null explicit actor override */
    private static $actor = null;

    public static function setActor($userId)
    {
        self::$actor = $userId === null ? null : (int) $userId;
    }

    /**
     * @param string $action  human readable description
     * @param string $section module name (auth, courses, tests, ...)
     */
    public static function log($action, $section = null)
    {
        $userId = self::$actor !== null
            ? self::$actor
            : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);

        if (!$userId) {
            return false;
        }

        try {
            Database::insert(
                'INSERT INTO activity_logs (user_id, action, section, ip_address, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [$userId, mb_substr((string) $action, 0, 500), $section ? mb_substr($section, 0, 100) : null, Request::ip()]
            );
            return true;
        } catch (\Throwable $e) {
            Logger::warning('Activity log failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Paginated trail with optional filters (admin screen). */
    public static function paginate($page = 1, $perPage = 20, array $filters = [])
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['section'])) {
            $where[] = 'al.section = ?';
            $params[] = $filters['section'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'al.action LIKE ?';
            $params[] = Database::like($filters['search']);
        }
        if (!empty($filters['from'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM activity_logs al $whereSql", $params);

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::all(
            "SELECT al.*, u.full_name, u.profile_picture, u.user_id_number
             FROM activity_logs al
             JOIN users u ON al.user_id = u.id
             $whereSql
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function sections()
    {
        return Database::column('SELECT DISTINCT section FROM activity_logs WHERE section IS NOT NULL ORDER BY section');
    }

    public static function clear()
    {
        Database::write('DELETE FROM activity_logs');
    }

    /** Feed for the top-bar notification panel. */
    public static function feed($excludeUserId, $limit = 8)
    {
        return Database::all(
            'SELECT al.*, u.full_name, u.profile_picture
             FROM activity_logs al
             JOIN users u ON al.user_id = u.id
             WHERE al.user_id <> ?
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT ' . (int) $limit,
            [(int) $excludeUserId]
        );
    }
}
