<?php

/**
 * Student\Portal — every student-facing screen.
 *
 * Each action renders a view; list data is fetched over AJAX so pages
 * stay fast and behave consistently across modules.
 */
namespace App\Controllers\Student;

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

    public function index()
    {
        $userId = $this->userId();

        $batches = Database::all(
            "SELECT b.id, b.name, b.status, c.title AS course_title,
                    (SELECT COUNT(*) FROM topics t WHERE t.batch_id = b.id) AS topic_count,
                    (SELECT COUNT(*) FROM assignments a WHERE a.batch_id = b.id AND a.status = 'active') AS assignment_count
             FROM batch_students bs
             JOIN batches b ON b.id = bs.batch_id
             JOIN courses c ON c.id = b.course_id
             WHERE bs.student_id = ?
             ORDER BY b.status = 'active' DESC, b.id DESC",
            [$userId]
        );

        $pendingAssignments = (int) Database::value(
            "SELECT COUNT(*) FROM assignments a
             WHERE a.status = 'active'
               AND a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
               AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = ?)",
            [$userId, $userId]
        );

        $availableTests = (int) Database::value(
            "SELECT COUNT(*) FROM tests t
             WHERE t.status = 'active' AND t.type IN ('weekly','monthly')
               AND t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)",
            [$userId]
        );

        $liveNow = (int) Database::value(
            "SELECT COUNT(*) FROM live_sessions
             WHERE status = 'active' AND batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)",
            [$userId]
        );

        $recentAnnouncements = Database::all(
            "SELECT a.*, b.name AS batch_name, u.full_name AS creator_name
             FROM announcements a
             LEFT JOIN batches b ON b.id = a.batch_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.status = 'published'
               AND (a.batch_id IS NULL OR a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?))
             ORDER BY a.is_pinned DESC, a.created_at DESC
             LIMIT 5",
            [$userId]
        );

        $recentResults = Database::all(
            "SELECT ta.percentage, ta.score, ta.total_questions, ta.submitted_at, t.title, t.type
             FROM test_attempts ta
             JOIN tests t ON t.id = ta.test_id
             WHERE ta.student_id = ? AND ta.status = 'submitted'
             ORDER BY ta.submitted_at DESC
             LIMIT 5",
            [$userId]
        );

        $recentSubmissions = Database::all(
            "SELECT s.status, s.marks, s.submitted_at, a.title AS assignment_title, a.total_marks
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             WHERE s.student_id = ?
             ORDER BY s.submitted_at DESC
             LIMIT 5",
            [$userId]
        );

        $this->view('student/dashboard', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'batches' => count($batches),
                'pending_assignments' => $pendingAssignments,
                'available_tests' => $availableTests,
                'live_now' => $liveNow,
            ],
            'batches' => $batches,
            'recentAnnouncements' => $recentAnnouncements,
            'recentResults' => $recentResults,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    public function courses()
    {
        $batches = Database::all(
            "SELECT b.*, c.title AS course_title, c.description AS course_description, c.thumbnail,
                    (SELECT COUNT(*) FROM topics t WHERE t.batch_id = b.id) AS topic_count,
                    (SELECT COUNT(*) FROM assignments a WHERE a.batch_id = b.id AND a.status = 'active') AS assignment_count,
                    (SELECT COUNT(*) FROM batch_students bs WHERE bs.batch_id = b.id) AS student_count,
                    (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                     FROM batch_teachers bt JOIN users u ON u.id = bt.teacher_id
                     WHERE bt.batch_id = b.id) AS teacher_names
             FROM batch_students bs
             JOIN batches b ON b.id = bs.batch_id
             JOIN courses c ON c.id = b.course_id
             WHERE bs.student_id = ?
             ORDER BY b.status = 'active' DESC, b.id DESC",
            [$this->userId()]
        );

        $this->view('student/courses', [
            'pageTitle' => 'My courses',
            'batches' => $batches,
        ]);
    }

    public function topics()
    {
        $this->view('student/topics', ['pageTitle' => 'Topics & material']);
    }

    public function assignments()
    {
        $this->view('student/assignments', ['pageTitle' => 'Assignments']);
    }

    public function tests()
    {
        $this->view('student/tests', ['pageTitle' => 'Tests']);
    }

    public function results()
    {
        $results = Database::all(
            "SELECT ta.*, t.title, t.type, t.time_minutes, b.name AS batch_name,
                    (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count
             FROM test_attempts ta
             JOIN tests t ON t.id = ta.test_id
             LEFT JOIN batches b ON b.id = t.batch_id
             WHERE ta.student_id = ? AND ta.status IN ('submitted','reviewed')
             ORDER BY ta.submitted_at DESC",
            [$this->userId()]
        );

        $this->view('student/results', [
            'pageTitle' => 'Results',
            'results' => $results,
        ]);
    }

    public function announcements()
    {
        $this->view('student/announcements', ['pageTitle' => 'Announcements']);
    }

    public function takeTest()
    {
        $attemptId = $this->int('attempt');
        $userId = $this->userId();

        $attempt = Database::first(
            "SELECT ta.*, t.title, t.description, t.time_minutes, t.type, t.status AS test_status
             FROM test_attempts ta
             JOIN tests t ON t.id = ta.test_id
             WHERE ta.id = ? AND ta.student_id = ?",
            [$attemptId, $userId]
        );

        if (!$attempt) {
            $this->flash('error', 'Assessment not found.');
            return $this->redirect('/student/tests');
        }

        if (in_array($attempt['status'], ['submitted', 'reviewed'], true)) {
            return $this->redirect('/student/results');
        }

        $questions = $this->loadQuestions((int) $attempt['test_id']);
        $saved = Database::all(
            'SELECT question_id, selected_option_id FROM test_answers WHERE attempt_id = ?',
            [$attemptId]
        );

        $answers = [];
        foreach ($saved as $row) {
            $answers[(int) $row['question_id']] = (int) $row['selected_option_id'];
        }

        $duration = (int) $attempt['time_minutes'] * 60;
        $elapsed = max(0, time() - strtotime($attempt['started_at']));

        $this->view('student/take-test', [
            'pageTitle' => $attempt['title'],
            'attempt' => $attempt,
            'questions' => $questions,
            'savedAnswers' => $answers,
            'remaining' => max(0, $duration - $elapsed),
        ]);
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
             WHERE ls.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
             ORDER BY ls.started_at DESC
             LIMIT 30",
            [$this->userId()]
        );

        $this->view('student/live', [
            'pageTitle' => 'Live classes',
            'sessions' => $sessions,
        ]);
    }

    public function feedback()
    {
        $this->view('student/feedback', ['pageTitle' => 'Give feedback']);
    }

    /** Live classroom (Jitsi room embedded in the portal shell). */
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
             WHERE ls.id = ? AND ls.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)",
            [$sessionId, $this->userId()]
        );

        if (!$session) {
            $this->flash('error', 'That live class is not available to you.');
            return $this->redirect('/student/live');
        }

        $this->view('live/room', [
            'pageTitle' => $session['title'],
            'session' => $session,
            'isModerator' => false,
            'bodyClass' => 'is-room',
            'flushContent' => true,
        ]);
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function loadQuestions($testId)
    {
        $rows = Database::all(
            "SELECT q.id, q.question_text AS text, q.is_code, o.id AS option_id, o.option_text
             FROM test_question_map m
             JOIN test_questions q ON q.id = m.question_id
             JOIN test_options o ON o.question_id = q.id
             WHERE m.test_id = ?
             ORDER BY m.sort_order, o.sort_order, o.id",
            [$testId]
        );

        $questions = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($questions[$id])) {
                $questions[$id] = [
                    'id' => $id,
                    'text' => $row['text'],
                    'is_code' => (int) $row['is_code'] === 1,
                    'options' => [],
                ];
            }
            $questions[$id]['options'][] = ['id' => (int) $row['option_id'], 'text' => $row['option_text']];
        }
        return array_values($questions);
    }
}


