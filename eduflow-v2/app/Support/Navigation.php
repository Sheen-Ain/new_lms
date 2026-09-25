<?php

namespace App\Support;

use App\Core\Auth;
use App\Core\Database;

/**
 * Navigation — the single definition of the portal sidebars, badge
 * counters and breadcrumbs. Views never hard-code menu entries.
 */
class Navigation
{
    /** Grouped sidebar definition for a role. */
    public static function forRole($role)
    {
        $menus = [
            'admin' => [
                ['title' => 'Overview', 'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'path' => '/admin'],
                ]],
                ['title' => 'Academics', 'items' => [
                    ['label' => 'Courses', 'icon' => 'book', 'path' => '/admin/courses'],
                    ['label' => 'Batches', 'icon' => 'layers', 'path' => '/admin/batches'],
                    ['label' => 'Topics', 'icon' => 'file-text', 'path' => '/admin/topics'],
                    ['label' => 'Assignments', 'icon' => 'clipboard', 'path' => '/admin/assignments'],
                    ['label' => 'Submissions', 'icon' => 'send', 'path' => '/admin/submissions', 'badge' => 'pending_submissions'],
                ]],
                ['title' => 'Assessment', 'items' => [
                    ['label' => 'Tests', 'icon' => 'checklist', 'path' => '/admin/tests'],
                    ['label' => 'Applications', 'icon' => 'inbox', 'path' => '/admin/applications', 'badge' => 'pending_applications'],
                ]],
                ['title' => 'Engagement', 'items' => [
                    ['label' => 'Announcements', 'icon' => 'megaphone', 'path' => '/admin/announcements'],
                    ['label' => 'Live sessions', 'icon' => 'video', 'path' => '/admin/live'],
                    ['label' => 'Feedback', 'icon' => 'message', 'path' => '/admin/feedback'],
                ]],
                ['title' => 'People & system', 'items' => [
                    ['label' => 'Users', 'icon' => 'users', 'path' => '/admin/users'],
                    ['label' => 'Activity logs', 'icon' => 'activity', 'path' => '/admin/activity-logs'],
                    ['label' => 'System health', 'icon' => 'server', 'path' => '/admin/system'],
                ]],
            ],
            'teacher' => [
                ['title' => 'Overview', 'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'path' => '/teacher'],
                ]],
                ['title' => 'My teaching', 'items' => [
                    ['label' => 'My batches', 'icon' => 'layers', 'path' => '/teacher/batches'],
                    ['label' => 'Students', 'icon' => 'users', 'path' => '/teacher/students'],
                    ['label' => 'Topics', 'icon' => 'file-text', 'path' => '/teacher/topics'],
                ]],
                ['title' => 'Assessment', 'items' => [
                    ['label' => 'Assignments', 'icon' => 'clipboard', 'path' => '/teacher/assignments'],
                    ['label' => 'Submissions', 'icon' => 'send', 'path' => '/teacher/submissions', 'badge' => 'pending_submissions'],
                    ['label' => 'Tests', 'icon' => 'checklist', 'path' => '/teacher/tests'],
                ]],
                ['title' => 'Engagement', 'items' => [
                    ['label' => 'Announcements', 'icon' => 'megaphone', 'path' => '/teacher/announcements'],
                    ['label' => 'Live sessions', 'icon' => 'video', 'path' => '/teacher/live'],
                    ['label' => 'Feedback', 'icon' => 'message', 'path' => '/teacher/feedback'],
                ]],
            ],
            'student' => [
                ['title' => 'Overview', 'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'path' => '/student'],
                ]],
                ['title' => 'Learning', 'items' => [
                    ['label' => 'My courses', 'icon' => 'book', 'path' => '/student/courses'],
                    ['label' => 'Topics & material', 'icon' => 'file-text', 'path' => '/student/topics'],
                    ['label' => 'Live classes', 'icon' => 'video', 'path' => '/student/live', 'badge' => 'live_now'],
                ]],
                ['title' => 'Assessment', 'items' => [
                    ['label' => 'Assignments', 'icon' => 'clipboard', 'path' => '/student/assignments', 'badge' => 'due_assignments'],
                    ['label' => 'Tests', 'icon' => 'checklist', 'path' => '/student/tests'],
                    ['label' => 'Results', 'icon' => 'chart', 'path' => '/student/results'],
                ]],
                ['title' => 'Engagement', 'items' => [
                    ['label' => 'Announcements', 'icon' => 'megaphone', 'path' => '/student/announcements'],
                    ['label' => 'Give feedback', 'icon' => 'message', 'path' => '/student/feedback'],
                ]],
            ],
        ];

        return isset($menus[$role]) ? $menus[$role] : $menus['student'];
    }

    /** Title used in the topbar when no page title is supplied. */
    public static function titleFromPath($path)
    {
        $segments = array_values(array_filter(explode('/', trim((string) $path, '/'))));
        if (count($segments) <= 1) {
            return 'Dashboard';
        }
        return ucwords(str_replace('-', ' ', end($segments)));
    }

    /** Badge counters resolved once per request. */
    public static function badges($role, $userId)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $badges = [];
        try {
            if ($role === 'admin') {
                $badges['pending_submissions'] = (int) Database::value(
                    "SELECT COUNT(*) FROM submissions WHERE status = 'submitted'"
                );
                $badges['pending_applications'] = (int) Database::value(
                    "SELECT COUNT(*) FROM course_applications WHERE status IN ('pending','test_submitted')"
                );
            } elseif ($role === 'teacher') {
                $badges['pending_submissions'] = (int) Database::value(
                    "SELECT COUNT(*) FROM submissions s
                     JOIN assignments a ON s.assignment_id = a.id
                     WHERE s.status = 'submitted'
                       AND a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)",
                    [$userId]
                );
            } elseif ($role === 'student') {
                $badges['live_now'] = (int) Database::value(
                    "SELECT COUNT(*) FROM live_sessions ls
                     WHERE ls.status = 'active'
                       AND ls.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)",
                    [$userId]
                );
                $badges['due_assignments'] = (int) Database::value(
                    "SELECT COUNT(*) FROM assignments a
                     WHERE a.status = 'active'
                       AND a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
                       AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = ?)
                       AND (a.due_date IS NULL OR a.due_date >= NOW())",
                    [$userId, $userId]
                );
            }
        } catch (\Throwable $e) {
            $badges = [];
        }

        $cache = $badges;
        return $cache;
    }

    /** Breadcrumb trail derived from the request path. */
    public static function breadcrumbs($path)
    {
        $role = Auth::role() ?: 'student';
        $trail = [['label' => 'Home', 'url' => url('/' . $role)]];

        $segments = array_values(array_filter(explode('/', trim((string) $path, '/'))));
        array_shift($segments);

        $built = '/' . $role;
        $total = count($segments);
        foreach ($segments as $index => $segment) {
            $built .= '/' . $segment;
            $trail[] = [
                'label' => ucwords(str_replace('-', ' ', $segment)),
                'url' => $index === $total - 1 ? null : url($built),
            ];
        }

        return $trail;
    }

    /** Route table for the system health screen. */
    public static function roleBadgeClass($role)
    {
        $map = ['admin' => 'badge-danger', 'teacher' => 'badge-purple', 'student' => 'badge-info'];
        return isset($map[$role]) ? $map[$role] : 'badge-muted';
    }
}

