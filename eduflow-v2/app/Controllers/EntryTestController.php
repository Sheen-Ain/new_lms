<?php

/**
 * EntryTestController — pre-authentication assessment engine.
 *
 * Applicants identify themselves with their student ID/email + password,
 * pick their applied batch, and take the entry test before their account
 * is activated.
 */
namespace App\Controllers;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;

class EntryTestController extends Controller
{
    /** Launch screen: shows login/batch picker or the active test stage. */
    public function index()
    {
        $session = Session::get('entry_test');

        if ($session && !empty($session['attempt_id'])) {
            $attempt = Database::first(
                'SELECT ta.*, t.title AS test_title, t.description AS test_desc, t.time_minutes, b.name AS batch_name
                 FROM test_attempts ta
                 JOIN tests t ON t.id = ta.test_id
                 JOIN batches b ON b.id = ?
                 WHERE ta.id = ? LIMIT 1',
                [(int) $session['batch_id'], (int) $session['attempt_id']]
            );

            if ($attempt && $attempt['status'] === 'submitted') {
                return $this->publicView('auth/entry-result', [
                    'pageTitle' => 'Entry test result',
                    'attempt' => $attempt,
                    'band' => band_for($attempt['percentage']),
                    'hideChrome' => true,
                ], 'layouts/auth');
            }

            if ($attempt && $attempt['status'] === 'in_progress') {
                $questions = $this->loadQuestions((int) $attempt['test_id']);
                $savedAnswers = $this->loadSavedAnswers((int) $attempt['id']);

                $duration = (int) $attempt['time_minutes'] * 60;
                $elapsed = max(0, time() - strtotime($attempt['started_at']));
                $remaining = max(0, $duration - $elapsed);

                return $this->publicView('auth/entry-runner', [
                    'pageTitle' => $attempt['test_title'],
                    'attempt' => $attempt,
                    'questions' => $questions,
                    'savedAnswers' => $savedAnswers,
                    'remaining' => $remaining,
                    'hideChrome' => true,
                ], 'layouts/auth');
            }

            Session::forget('entry_test');
        }

        $batches = Database::all(
            "SELECT b.id, b.name, c.title AS course_title,
                    (SELECT COUNT(*) FROM tests t WHERE t.batch_id = b.id AND t.type = 'entry' AND t.status = 'active') AS has_test
             FROM batches b
             JOIN courses c ON b.course_id = c.id
             WHERE b.status = 'active'
             ORDER BY c.title ASC, b.name ASC"
        );

        $this->publicView('auth/entry-start', [
            'pageTitle' => 'Entry assessment',
            'batches' => $batches,
            'hideChrome' => true,
        ], 'layouts/auth');
    }

    /** Step 1: verify student credentials, find application & create/resume attempt. */
    public function authenticate()
    {
        $identifier = $this->string('identifier');
        $password = (string) $this->input('password', '');
        $batchId = $this->int('batch_id');

        if ($identifier === '' || $password === '' || !$batchId) {
            return $this->error('Please fill in all fields.');
        }

        $field = preg_match('/^\d{5}$/', $identifier) ? 'user_id_number' : 'email';
        $user = Database::first("SELECT * FROM users WHERE $field = ? LIMIT 1", [$identifier]);

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->error('Invalid student ID or password.');
        }
        if ((int) $user['is_verified'] !== 1) {
            return $this->error('Please verify your email address before taking the entry test.');
        }

        $batch = Database::first("SELECT id, name FROM batches WHERE id = ? AND status = 'active'", [$batchId]);
        if (!$batch) {
            return $this->error('Selected batch is not available.');
        }

        $application = Database::first(
            'SELECT id, status FROM course_applications WHERE user_id = ? AND batch_id = ? LIMIT 1',
            [$user['id'], $batchId]
        );

        if (!$application) {
            $appId = Database::insert(
                "INSERT INTO course_applications (user_id, batch_id, status, applied_at) VALUES (?, ?, 'pending', NOW())",
                [$user['id'], $batchId]
            );
            $application = ['id' => $appId, 'status' => 'pending'];
        }

        if ($application['status'] === 'approved') {
            return $this->error('You are already enrolled in this batch. Please use the main sign in screen.');
        }
        if ($application['status'] === 'rejected') {
            return $this->error('Your application for this batch was not approved.');
        }

        $test = Database::first(
            "SELECT * FROM tests WHERE (batch_id = ? OR batch_id IS NULL) AND type = 'entry' AND status = 'active'
             ORDER BY batch_id DESC, id DESC LIMIT 1",
            [$batchId]
        );

        if (!$test) {
            return $this->error('No entry test is currently active for this batch. Contact the academic office.');
        }

        $attempt = Database::first(
            'SELECT * FROM test_attempts WHERE test_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1',
            [$test['id'], $user['id']]
        );

        if ($attempt && $attempt['status'] === 'submitted' && !(int) $attempt['allow_reattempt']) {
            Session::set('entry_test', [
                'attempt_id' => (int) $attempt['id'],
                'batch_id' => $batchId,
                'user_id' => (int) $user['id'],
            ]);
            return $this->success('You have already submitted this assessment.', ['redirect' => url('/entry-test')]);
        }

        if (!$attempt || (int) $attempt['allow_reattempt'] || $attempt['status'] === 'submitted') {
            $attemptId = Database::insert(
                "INSERT INTO test_attempts (test_id, student_id, application_id, status, started_at)
                 VALUES (?, ?, ?, 'in_progress', NOW())",
                [$test['id'], $user['id'], $application['id']]
            );
            Activity::setActor((int) $user['id']);
            Activity::log('Started entry test: ' . $test['title'], 'tests');
        } else {
            $attemptId = (int) $attempt['id'];
        }

        Session::set('entry_test', [
            'attempt_id' => $attemptId,
            'batch_id' => $batchId,
            'user_id' => (int) $user['id'],
            'test_id' => (int) $test['id'],
        ]);

        return $this->success('Assessment ready.', ['redirect' => url('/entry-test')]);
    }

    /** AJAX: return question set for the active entry test attempt. */
    public function questions()
    {
        $session = Session::get('entry_test');
        if (!$session || empty($session['test_id'])) {
            return $this->error('Session expired. Please sign in again.');
        }

        $questions = $this->loadQuestions((int) $session['test_id']);
        return $this->success('Questions loaded.', ['questions' => $questions]);
    }

    /** AJAX: save a single answer as it is clicked. */
    public function saveAnswer()
    {
        $session = Session::get('entry_test');
        if (!$session || empty($session['attempt_id'])) {
            return $this->error('Session expired.');
        }

        $attemptId = (int) $session['attempt_id'];
        $questionId = $this->int('question_id');
        $optionId = $this->int('option_id');

        if (!$questionId || !$optionId) {
            return $this->error('Invalid question or option.');
        }

        Database::write(
            'DELETE FROM test_answers WHERE attempt_id = ? AND question_id = ?',
            [$attemptId, $questionId]
        );

        $isCorrect = (int) Database::value(
            'SELECT is_correct FROM test_options WHERE id = ? AND question_id = ? LIMIT 1',
            [$optionId, $questionId]
        );

        $marks = $isCorrect ? 1.00 : -NEGATIVE_MARKING;

        Database::write(
            'INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded)
             VALUES (?, ?, ?, ?, ?)',
            [$attemptId, $questionId, $optionId, $isCorrect, $marks]
        );

        return $this->success('Answer saved.');
    }

    /** Finalise the entry test and mark answers. */
    public function submit()
    {
        $session = Session::get('entry_test');
        if (!$session || empty($session['attempt_id'])) {
            return $this->error('Session expired.');
        }

        $attemptId = (int) $session['attempt_id'];
        $attempt = Database::first('SELECT * FROM test_attempts WHERE id = ? LIMIT 1', [$attemptId]);

        if (!$attempt || $attempt['status'] === 'submitted') {
            return $this->success('Attempt was already submitted.');
        }

        $answersJson = (string) $this->input('answers', '{}');
        $submittedAnswers = json_decode($answersJson, true);
        if (is_array($submittedAnswers)) {
            foreach ($submittedAnswers as $qId => $optId) {
                $qId = (int) $qId;
                $optId = (int) $optId;
                if ($qId > 0 && $optId > 0) {
                    Database::write('DELETE FROM test_answers WHERE attempt_id = ? AND question_id = ?', [$attemptId, $qId]);
                    $isCorrect = (int) Database::value('SELECT is_correct FROM test_options WHERE id = ? AND question_id = ?', [$optId, $qId]);
                    $marks = $isCorrect ? 1.00 : -NEGATIVE_MARKING;
                    Database::write(
                        'INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded) VALUES (?, ?, ?, ?, ?)',
                        [$attemptId, $qId, $optId, $isCorrect, $marks]
                    );
                }
            }
        }

        $summary = $this->calculateAndFinalise($attemptId, (int) $attempt['test_id'], (int) $attempt['student_id']);

        Database::write(
            "UPDATE course_applications ca
             JOIN tests t ON t.id = ?
             SET ca.status = 'test_submitted'
             WHERE ca.user_id = ? AND ca.batch_id = t.batch_id AND ca.status = 'pending'",
            [(int) $attempt['test_id'], (int) $attempt['student_id']]
        );

        Database::write('UPDATE tests SET questions_locked = 1 WHERE id = ?', [(int) $attempt['test_id']]);

        Activity::setActor((int) $attempt['student_id']);
        Activity::log('Submitted entry test (score: ' . $summary['percentage'] . '%)', 'tests');

        return $this->success('Your assessment has been submitted.', [
            'summary' => $summary,
            'redirect' => url('/entry-test'),
        ]);
    }

    public function exitTest()
    {
        Session::forget('entry_test');
        $this->flash('info', 'Assessment session closed.');
        return $this->redirect('/login');
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function loadQuestions($testId)
    {
        $rows = Database::all(
            "SELECT q.id, q.question_text AS text, q.is_code,
                    o.id AS option_id, o.option_text
             FROM test_question_map m
             JOIN test_questions q ON q.id = m.question_id
             JOIN test_options o ON o.question_id = q.id
             WHERE m.test_id = ?
             ORDER BY m.sort_order ASC, o.sort_order ASC, o.id ASC",
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
            $questions[$id]['options'][] = [
                'id' => (int) $row['option_id'],
                'text' => $row['option_text'],
            ];
        }

        return array_values($questions);
    }

    private function loadSavedAnswers($attemptId)
    {
        $rows = Database::all(
            'SELECT question_id, selected_option_id FROM test_answers WHERE attempt_id = ?',
            [$attemptId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['question_id']] = (int) $row['selected_option_id'];
        }
        return $map;
    }

    private function calculateAndFinalise($attemptId, $testId, $studentId)
    {
        $totalQuestions = (int) Database::value(
            'SELECT COUNT(*) FROM test_question_map WHERE test_id = ?',
            [$testId]
        );

        $answers = Database::all(
            'SELECT ta.question_id, ta.selected_option_id, opt.is_correct
             FROM test_answers ta
             LEFT JOIN test_options opt ON opt.id = ta.selected_option_id
             WHERE ta.attempt_id = ?',
            [$attemptId]
        );

        $correct = 0;
        $wrong = 0;
        $answered = [];

        foreach ($answers as $row) {
            $answered[] = (int) $row['question_id'];
            if ((int) $row['is_correct'] === 1) {
                $correct++;
            } else {
                $wrong++;
            }
        }

        $unanswered = max(0, $totalQuestions - count(array_unique($answered)));
        $score = max(0.0, (float) $correct - ($wrong * NEGATIVE_MARKING));
        $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100.0, 2) : 0.0;

        Database::write(
            "UPDATE test_attempts SET
                status = 'submitted',
                score = ?,
                percentage = ?,
                total_questions = ?,
                correct_count = ?,
                wrong_count = ?,
                unanswered_count = ?,
                submitted_at = NOW()
             WHERE id = ?",
            [$score, $percentage, $totalQuestions, $correct, $wrong, $unanswered, $attemptId]
        );

        $band = band_for($percentage);

        return [
            'score' => round($score, 2),
            'percentage' => $percentage,
            'total_questions' => $totalQuestions,
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'unanswered_count' => $unanswered,
            'band' => $band['label'],
            'tone' => $band['tone'],
        ];
    }

    /** Friendly alias: the entry test is always entered through index(). */
    public function start()
    {
        redirect('/entry-test');
    }
}




