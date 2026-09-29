<?php

namespace App\Controllers\Api;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * TestApi — test authoring (teacher/admin) and attempt engine (student).
 *
 * Scoring matches V1 exactly: +1.00 correct, -0.25 incorrect, 0 unanswered.
 */
class TestApi extends Controller
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

    /* ── Authoring ───────────────────────────────────────────── */

    public function list()
    {
        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 15)));
        $search = $this->string('search');
        $type = $this->string('type');
        $status = $this->string('status');

        $where = [];
        $params = [];

        if ($this->role() === 'teacher') {
            $where[] = '(t.created_by = ? OR t.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id = ?))';
            $params[] = $this->userId();
            $params[] = $this->userId();
        } elseif ($this->role() === 'student') {
            $where[] = "t.type IN ('weekly','monthly') AND t.status = 'active'";
            $where[] = 't.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)';
            $params[] = $this->userId();
        }

        if ($search !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = Database::like($search);
        }
        if ($type !== '') {
            $where[] = 't.type = ?';
            $params[] = $type;
        }
        if ($status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM tests t $whereSql", $params);

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT t.*, b.name AS batch_name, c.title AS course_title,
                    (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count,
                    (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id) AS attempt_count,
                    (SELECT ta.status FROM test_attempts ta WHERE ta.test_id = t.id AND ta.student_id = ? ORDER BY ta.id DESC LIMIT 1) AS my_attempt_status,
                    (SELECT ta.id FROM test_attempts ta WHERE ta.test_id = t.id AND ta.student_id = ? ORDER BY ta.id DESC LIMIT 1) AS my_attempt_id,
                    (SELECT ta.percentage FROM test_attempts ta WHERE ta.test_id = t.id AND ta.student_id = ? ORDER BY ta.id DESC LIMIT 1) AS my_percentage,
                    (SELECT ta.score FROM test_attempts ta WHERE ta.test_id = t.id AND ta.student_id = ? ORDER BY ta.id DESC LIMIT 1) AS my_score
             FROM tests t
             LEFT JOIN batches b ON b.id = t.batch_id
             LEFT JOIN courses c ON c.id = b.course_id
             $whereSql
             ORDER BY t.created_at DESC
             LIMIT $perPage OFFSET $offset",
            array_merge([$this->userId(), $this->userId(), $this->userId(), $this->userId()], $params)
        );

        return $this->success('OK', ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    public function getOne()
    {
        $id = $this->int('test_id');
        $test = Database::first(
            'SELECT t.*, b.name AS batch_name FROM tests t LEFT JOIN batches b ON b.id = t.batch_id WHERE t.id = ?',
            [$id]
        );
        if (!$test) {
            return $this->error('Test not found.');
        }

        $questions = Database::all(
            'SELECT q.id, q.question_text, q.is_code, o.id AS option_id, o.option_text, o.is_correct, o.sort_order
             FROM test_question_map m
             JOIN test_questions q ON q.id = m.question_id
             LEFT JOIN test_options o ON o.question_id = q.id
             WHERE m.test_id = ?
             ORDER BY m.sort_order, o.sort_order, o.id',
            [$id]
        );

        $grouped = [];
        foreach ($questions as $row) {
            $qid = (int) $row['id'];
            if (!isset($grouped[$qid])) {
                $grouped[$qid] = [
                    'id' => $qid,
                    'text' => $row['question_text'],
                    'is_code' => (int) $row['is_code'] === 1,
                    'options' => [],
                ];
            }
            if ($row['option_id'] !== null) {
                $grouped[$qid]['options'][] = [
                    'id' => (int) $row['option_id'],
                    'text' => $row['option_text'],
                    'is_correct' => (int) $row['is_correct'] === 1,
                ];
            }
        }

        return $this->success('OK', ['test' => $test, 'questions' => array_values($grouped)]);
    }

    public function create()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.');
        }

        $title = $this->string('title');
        $type = $this->string('type', 'weekly');
        $batchId = $this->int('batch_id');
        $minutes = $this->int('time_minutes', DEFAULT_TEST_MINUTES);
        $status = $this->string('status', 'active');
        $description = $this->string('description');
        $questions = json_decode((string) $this->input('questions', '[]'), true);

        $validator = Validator::make(
            ['title' => $title, 'type' => $type, 'time_minutes' => $minutes, 'status' => $status],
            [
                'title' => 'required|min:3|max:200',
                'type' => 'in:entry,weekly,monthly',
                'time_minutes' => 'required|integer|min_value:1|max_value:480',
                'status' => 'in:active,inactive,draft',
            ],
            ['title' => 'Title', 'time_minutes' => 'Duration']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }

        if (!is_array($questions) || !$questions) {
            return $this->error('Add at least one question.');
        }

        $questionErrors = $this->validateQuestions($questions);
        if ($questionErrors) {
            return $this->error($questionErrors, [], 422);
        }

        Database::begin();
        try {
            $testId = Database::insert(
                'INSERT INTO tests (title, description, type, batch_id, time_minutes, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$title, $description, $type, $batchId ?: null, $minutes, $status, $this->userId()]
            );
            $this->insertQuestions($testId, $questions, $this->userId());
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \App\Core\Logger::error('Test creation failed: ' . $e->getMessage());
            return $this->error('Could not save the test.');
        }

        Activity::log('Created test: ' . $title, 'tests');
        return $this->success('Test created.', ['test_id' => $testId]);
    }

    public function update()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.');
        }

        $testId = $this->int('test_id');
        $test = Database::first('SELECT id FROM tests WHERE id = ?', [$testId]);
        if (!$test) {
            return $this->error('Test not found.');
        }
        if ((int) Database::value('SELECT COUNT(*) FROM test_attempts WHERE test_id = ?', [$testId]) > 0) {
            return $this->error('Tests with attempts cannot be edited. Copy the test to make a new version.');
        }

        $title = $this->string('title');
        $type = $this->string('type', 'weekly');
        $batchId = $this->int('batch_id');
        $minutes = $this->int('time_minutes', DEFAULT_TEST_MINUTES);
        $status = $this->string('status', 'active');
        $description = $this->string('description');
        $questions = json_decode((string) $this->input('questions', '[]'), true);

        $validator = Validator::make(
            ['title' => $title, 'type' => $type, 'time_minutes' => $minutes, 'status' => $status],
            [
                'title' => 'required|min:3|max:200',
                'type' => 'in:entry,weekly,monthly',
                'time_minutes' => 'required|integer|min_value:1|max_value:480',
                'status' => 'in:active,inactive,draft',
            ],
            ['title' => 'Title', 'time_minutes' => 'Duration']
        );
        if ($validator->fails()) {
            return $this->error($validator->firstMessage(), ['errors' => $validator->errors()], 422);
        }
        if (!is_array($questions) || !$questions) {
            return $this->error('Add at least one question.');
        }
        if ($questionError = $this->validateQuestions($questions)) {
            return $this->error($questionError, [], 422);
        }

        Database::begin();
        try {
            Database::write(
                'UPDATE tests SET title = ?, description = ?, type = ?, batch_id = ?, time_minutes = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$title, $description, $type, $batchId ?: null, $minutes, $status, $testId]
            );
            $questionIds = Database::column('SELECT question_id FROM test_question_map WHERE test_id = ?', [$testId]);
            Database::write('DELETE FROM test_question_map WHERE test_id = ?', [$testId]);
            if ($questionIds) {
                $placeholders = Database::placeholders($questionIds);
                Database::write("DELETE FROM test_answers WHERE question_id IN ($placeholders)", $questionIds);
                Database::write("DELETE FROM test_options WHERE question_id IN ($placeholders)", $questionIds);
                Database::write("DELETE FROM test_questions WHERE id IN ($placeholders)", $questionIds);
            }
            $this->insertQuestions($testId, $questions, $this->userId());
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \App\Core\Logger::error('Test update failed: ' . $e->getMessage());
            return $this->error('Could not update the test.');
        }

        Activity::log('Updated test: ' . $title, 'tests');
        return $this->success('Test updated.', ['test_id' => $testId]);
    }

    public function status()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.');
        }
        $id = $this->int('test_id');
        $status = $this->string('status');
        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->error('Invalid test status.');
        }
        $updated = Database::write('UPDATE tests SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
        if (!$updated) {
            return $this->error('Test not found.');
        }
        Activity::log('Changed test #' . $id . ' status to ' . $status, 'tests');
        return $this->success('Test status updated.', ['status' => $status]);
    }

    public function delete()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.');
        }

        $ids = array_filter(array_map('intval', explode(',', $this->string('ids'))));
        if (!$ids && ($single = $this->int('test_id'))) {
            $ids = [$single];
        }
        if (!$ids) {
            return $this->error('No tests selected.');
        }

        $placeholders = Database::placeholders($ids);
        $mapRows = Database::all("SELECT question_id FROM test_question_map WHERE test_id IN ($placeholders)", $ids);
        $questionIds = array_map(function ($r) { return (int) $r['question_id']; }, $mapRows);

        Database::write("DELETE FROM test_question_map WHERE test_id IN ($placeholders)", $ids);

        if ($questionIds) {
            $qp = Database::placeholders($questionIds);
            Database::write("DELETE FROM test_options WHERE question_id IN ($qp)", $questionIds);
            Database::write("DELETE FROM test_answers WHERE question_id IN ($qp)", $questionIds);
            Database::write("DELETE FROM test_questions WHERE id IN ($qp)", $questionIds);
        }

        Database::write("DELETE FROM test_attempts WHERE test_id IN ($placeholders)", $ids);
        $deleted = Database::write("DELETE FROM tests WHERE id IN ($placeholders)", $ids);

        Activity::log('Deleted ' . $deleted . ' test(s)', 'tests');
        return $this->success($deleted . ' test(s) deleted.');
    }

    /* ── Attempt engine (students) ───────────────────────────── */

    public function start()
    {
        if ($this->role() !== 'student') {
            return $this->error('Access denied.');
        }

        $testId = $this->int('test_id');
        $userId = $this->userId();

        $test = Database::first(
            "SELECT t.*, b.name AS batch_name FROM tests t LEFT JOIN batches b ON b.id = t.batch_id
             WHERE t.id = ? AND t.status = 'active'",
            [$testId]
        );
        if (!$test) {
            return $this->error('Test not found or no longer active.');
        }

        $enrolled = Database::value(
            'SELECT COUNT(*) FROM batch_students WHERE batch_id = ? AND student_id = ?',
            [(int) $test['batch_id'], $userId]
        );
        if (!$enrolled) {
            return $this->error('You are not enrolled in this batch.');
        }

        $attempt = Database::first(
            'SELECT * FROM test_attempts WHERE test_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1',
            [$testId, $userId]
        );

        if ($attempt && $attempt['status'] === 'in_progress') {
            $remaining = max(0, ((int) $test['time_minutes'] * 60) - (time() - strtotime($attempt['started_at'])));
            return $this->success('Resuming attempt.', [
                'attempt_id' => (int) $attempt['id'],
                'remaining' => $remaining,
                'url' => url('/student/tests/take', ['attempt' => $attempt['id']]),
            ]);
        }

        if ($attempt && $attempt['status'] !== 'in_progress' && !(int) $attempt['allow_reattempt']) {
            return $this->error('You have already completed this test.');
        }

        $attemptId = Database::insert(
            "INSERT INTO test_attempts (test_id, student_id, status, started_at) VALUES (?, ?, 'in_progress', NOW())",
            [$testId, $userId]
        );

        Activity::log('Started test: ' . $test['title'], 'tests');

        return $this->success('Test started.', [
            'attempt_id' => $attemptId,
            'remaining' => (int) $test['time_minutes'] * 60,
            'url' => url('/student/tests/take', ['attempt' => $attemptId]),
        ]);
    }

    public function saveAnswer()
    {
        if ($this->role() !== 'student') {
            return $this->error('Access denied.');
        }

        $attemptId = $this->int('attempt_id');
        $questionId = $this->int('question_id');
        $optionId = $this->int('option_id');

        $attempt = Database::first(
            'SELECT * FROM test_attempts WHERE id = ? AND student_id = ?',
            [$attemptId, $this->userId()]
        );
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return $this->error('Attempt is not active.');
        }
        if (!$questionId || !$optionId) {
            return $this->error('Invalid question or option.');
        }

        Database::write('DELETE FROM test_answers WHERE attempt_id = ? AND question_id = ?', [$attemptId, $questionId]);

        $isCorrect = (int) Database::value(
            'SELECT is_correct FROM test_options WHERE id = ? AND question_id = ? LIMIT 1',
            [$optionId, $questionId]
        );
        $marks = $isCorrect ? 1.00 : -NEGATIVE_MARKING;

        Database::write(
            'INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded) VALUES (?, ?, ?, ?, ?)',
            [$attemptId, $questionId, $optionId, $isCorrect, $marks]
        );

        return $this->success('Answer saved.');
    }

    public function submit()
    {
        if ($this->role() !== 'student') {
            return $this->error('Access denied.');
        }

        $attemptId = $this->int('attempt_id');
        $attempt = Database::first(
            'SELECT * FROM test_attempts WHERE id = ? AND student_id = ?',
            [$attemptId, $this->userId()]
        );

        if (!$attempt) {
            return $this->error('Attempt not found.');
        }
        if ($attempt['status'] !== 'in_progress') {
            return $this->success('Attempt was already submitted.');
        }

        // Merge pending answers sent by the runner.
        $pending = json_decode((string) $this->input('answers', '{}'), true);
        if (is_array($pending)) {
            foreach ($pending as $qId => $optId) {
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

        $summary = $this->finaliseAttempt($attemptId, (int) $attempt['test_id']);

        Activity::log('Submitted test attempt #' . $attemptId . ' (' . $summary['percentage'] . '%)', 'tests');

        return $this->success('Your assessment has been submitted.', [
            'summary' => $summary,
            'redirect' => url('/student/results'),
        ]);
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function validateQuestions(array $questions)
    {
        foreach ($questions as $index => $question) {
            $label = 'Question ' . ($index + 1);
            if (empty($question['text'])) {
                return $label . ': text is required.';
            }
            if (!isset($question['options']) || count($question['options']) !== 4) {
                return $label . ': needs exactly 4 options.';
            }
            if (!isset($question['correct']) || !in_array((int) $question['correct'], [0, 1, 2, 3], true)) {
                return $label . ': mark the correct answer.';
            }
            foreach ($question['options'] as $option) {
                if (trim((string) $option) === '') {
                    return $label . ': all options must be filled.';
                }
            }
        }
        return null;
    }

    private function insertQuestions($testId, array $questions, $createdBy)
    {
        foreach ($questions as $sortOrder => $question) {
            $questionId = Database::insert(
                'INSERT INTO test_questions (question_text, is_code, created_by) VALUES (?, ?, ?)',
                [$question['text'], !empty($question['is_code']) ? 1 : 0, $createdBy]
            );

            foreach (array_values($question['options']) as $index => $optionText) {
                Database::write(
                    'INSERT INTO test_options (question_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$questionId, $optionText, $index === (int) $question['correct'] ? 1 : 0, $index]
                );
            }

            Database::write(
                'INSERT INTO test_question_map (test_id, question_id, sort_order) VALUES (?, ?, ?)',
                [$testId, $questionId, $sortOrder]
            );
        }
    }

    /** Shared scorer: +1.00 correct, -0.25 incorrect, 0 unanswered. */
    private function finaliseAttempt($attemptId, $testId)
    {
        $totalQuestions = (int) Database::value('SELECT COUNT(*) FROM test_question_map WHERE test_id = ?', [$testId]);
        $answers = Database::all(
            'SELECT ta.question_id, opt.is_correct
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
            "UPDATE test_attempts SET status = 'submitted', score = ?, percentage = ?, total_questions = ?,
             correct_count = ?, wrong_count = ?, unanswered_count = ?, submitted_at = NOW() WHERE id = ?",
            [$score, $percentage, $totalQuestions, $correct, $wrong, $unanswered, $attemptId]
        );
        Database::write('UPDATE tests SET questions_locked = 1 WHERE id = ? AND questions_locked = 0', [$testId]);

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

    /* ── Reporting ───────────────────────────────────────────── */

    /** Every attempt of a test (teacher/admin review board). */
    public function attempts()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $testId = $this->int('test_id');
        if (!$testId) {
            return $this->error('Test required.');
        }

        $test = Database::first('SELECT id, title, type, time_minutes FROM tests WHERE id = ?', [$testId]);
        if (!$test) {
            return $this->error('Test not found.');
        }

        $page = max(1, $this->int('page', 1));
        $perPage = max(5, min(100, $this->int('per_page', 20)));
        $status = $this->string('status');
        $search = $this->string('search');

        $where = ['ta.test_id = ?'];
        $params = [$testId];
        if ($status !== '') {
            $where[] = 'ta.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.user_id_number LIKE ?)';
            $params[] = Database::like($search);
            $params[] = Database::like($search);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value(
            "SELECT COUNT(*) FROM test_attempts ta JOIN users u ON u.id = ta.student_id $whereSql",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT ta.*, u.full_name, u.user_id_number, u.profile_picture,
                    (SELECT COUNT(*) FROM test_questions q) AS placeholder
             FROM test_attempts ta
             JOIN users u ON u.id = ta.student_id
             $whereSql
             ORDER BY ta.percentage DESC, ta.submitted_at ASC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        foreach ($rows as &$row) {
            $row['band'] = band_for($row['percentage']);
        }

        $summary = Database::first(
            "SELECT COUNT(*) AS attempts,
                    AVG(percentage) AS average,
                    MAX(percentage) AS best,
                    MIN(percentage) AS worst,
                    SUM(status = 'submitted') AS submitted,
                    SUM(status = 'in_progress') AS in_progress
             FROM test_attempts WHERE test_id = ?",
            [$testId]
        );

        return $this->success('OK', [
            'test' => $test,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'summary' => $summary,
        ]);
    }

    /** Grant (or revoke) a reattempt for one student's attempt. */
    public function reattempt()
    {
        if (!$this->isStaff()) {
            return $this->error('Access denied.', [], 403);
        }

        $attemptId = $this->int('attempt_id');
        $allow = $this->bool('allow', true) ? 1 : 0;

        $attempt = Database::first(
            'SELECT ta.id, ta.test_id, ta.student_id, t.title FROM test_attempts ta
             JOIN tests t ON t.id = ta.test_id WHERE ta.id = ?',
            [$attemptId]
        );
        if (!$attempt) {
            return $this->error('Attempt not found.');
        }

        Database::write('UPDATE test_attempts SET allow_reattempt = ? WHERE id = ?', [$allow, $attemptId]);
        Activity::log(
            ($allow ? 'Granted' : 'Revoked') . ' a reattempt for "' . $attempt['title'] . '"',
            'tests'
        );

        return $this->success(
            $allow
                ? 'The student can now start a fresh attempt.'
                : 'Reattempt permission removed.',
            ['allow_reattempt' => $allow]
        );
    }
}



