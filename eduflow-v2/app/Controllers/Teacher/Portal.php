<?php

/**
 * Teacher\Portal — every teacher-facing screen.
 */
namespace App\Controllers\Teacher;

use App\Core\Controller;
use App\Core\Database;

class Portal extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->userId()) {
            redirect('/login');
        }
    }

    /** Batch ids assigned to this teacher. */
    private function batchIds()
    {
        return Database::column(
            'SELECT batch_id FROM batch_teachers WHERE teacher_id = ?',
            [$this->userId()]
        );
    }

    public function index()
    {
        $userId = $this->userId();
        $batchIds = $this->batchIds();

        $stats = [
            'batches' => (int) Database::value('SELECT COUNT(*) FROM batch_teachers WHERE teacher_id = ?', [$userId]),
            'pending_submissions' => (int) Database::value(
                "SELECT COUNT(*) FROM submissions s
                 JOIN assignments a ON a.id = s.assignment_id
                 WHERE s.status = 'submitted' AND a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)",
                [$userId]
            ),
            'students' => (int) Database::value(
                'SELECT COUNT(*) FROM batch_students WHERE batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)',
                [$userId]
            ),
            'tests' => (int) Database::value(
                'SELECT COUNT(*) FROM tests WHERE created_by = ? OR batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)',
                [$userId, $userId]
            ),
        ];

        $batches = Database::all(
            "SELECT b.id, b.name, b.status, c.title AS course_title,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS student_count,
                    (SELECT COUNT(*) FROM assignments a WHERE a.batch_id = b.id AND a.status = 'active') AS assignment_count,
                    (SELECT COUNT(*) FROM live_sessions ls WHERE ls.batch_id = b.id AND ls.status = 'active') AS live_active
             FROM batch_teachers bt
             JOIN batches b ON b.id = bt.batch_id
             JOIN courses c ON c.id = b.course_id
             WHERE bt.teacher_id = ?
             ORDER BY b.status = 'active' DESC, b.id DESC",
            [$userId]
        );

        $recentSubmissions = Database::all(
            "SELECT s.id, s.status, s.marks, s.submitted_at, s.assignment_id,
                    a.title AS assignment_title, u.full_name AS student_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN users u ON u.id = s.student_id
             WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)
             ORDER BY s.submitted_at DESC
             LIMIT 6",
            [$userId]
        );

        $this->view('teacher/dashboard', [
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'batches' => $batches,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    public function batches()
    {
        $this->view('teacher/batches', ['pageTitle' => 'My batches']);
    }

    public function students()
    {
        $this->view('teacher/students', ['pageTitle' => 'Students']);
    }

    public function topics()
    {
        $this->view('teacher/topics', ['pageTitle' => 'Topics']);
    }

    public function assignments()
    {
        $this->view('teacher/assignments', ['pageTitle' => 'Assignments']);
    }

    public function submissions()
    {
        $this->view('teacher/submissions', ['pageTitle' => 'Submissions']);
    }

    public function tests()
    {
        $this->view('teacher/tests', ['pageTitle' => 'Tests']);
    }

    public function announcements()
    {
        $this->view('teacher/announcements', ['pageTitle' => 'Announcements']);
    }

    public function live()
    {
        $sessions = Database::all(
            "SELECT ls.*, b.name AS batch_name, c.title AS course_title, u.full_name AS host_name,
                    (SELECT COUNT(*) FROM session_attendees sa WHERE sa.session_id = ls.id) AS attendee_count
             FROM live_sessions ls
             JOIN batches b ON b.id = ls.batch_id
             JOIN courses c ON c.id = b.course_id
             JOIN users u ON u.id = ls.started_by
             WHERE ls.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)
             ORDER BY ls.started_at DESC
             LIMIT 30",
            [$this->userId()]
        );

        $this->view('teacher/live', [
            'pageTitle' => 'Live sessions',
            'sessions' => $sessions,
        ]);
    }

    public function feedback()
    {
        $this->view('teacher/feedback', ['pageTitle' => 'Feedback']);
    }

    /** Host a live class (moderator privileges in the Jitsi room). */
    public function room()
    {
        $sessionId = $this->int('session');
        $session = Database::first(
            "SELECT ls.id, ls.room_name, ls.title, ls.status, ls.started_at, ls.ended_at,
                    b.name AS batch_name, c.title AS course_title, u.full_name AS host_name
             FROM live_sessions ls
             JOIN batches b ON b.id = ls.batch_id
             JOIN courses c ON c.id = b.course_id
             JOIN users u ON u.id = ls.started_by
             WHERE ls.id = ? AND ls.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?)",
            [$sessionId, $this->userId()]
        );

        if (!$session) {
            $this->flash('error', 'That live class is not assigned to you.');
            return $this->redirect('/teacher/live');
        }

        $this->view('live/room', [
            'pageTitle' => $session['title'],
            'session' => $session,
            'isModerator' => true,
            'bodyClass' => 'is-room',
            'flushContent' => true,
        ]);
    }
}
