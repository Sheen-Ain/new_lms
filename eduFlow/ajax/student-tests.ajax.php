<?php
// ============================================================
// STUDENT TESTS AJAX — Authenticated LMS test engine
// Actions: list_tests, get_test, start_test, get_questions,
//          save_answer, submit_test, my_results
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');
if (($_SESSION['role'] ?? '') !== 'student') jsonResponse('error', 'Access denied');

$me     = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list_tests':
        listTests();
        break;
    case 'get_test':
        getTest();
        break;
    case 'start_test':
        startTest();
        break;
    case 'get_questions':
        getQuestions();
        break;
    case 'save_answer':
        saveAnswer();
        break;
    case 'submit_test':
        submitTest();
        break;
    case 'my_results':
        myResults();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}

// ── LIST TESTS available to this student ─────────────────────
function listTests()
{
    global $conn, $me;

    $batchId = (int)($_POST['batch_id'] ?? 0);
    $type    = trim($_POST['type'] ?? '');

    // Base: all weekly/monthly tests for enrolled batches
    $where  = [
        "t.type IN ('weekly','monthly')",
        "t.status = 'active'",
        "t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = $me)"
    ];
    if ($batchId) $where[] = "t.batch_id = $batchId";
    if ($type)    $where[] = "t.type = '" . $conn->real_escape_string($type) . "'";
    $wSQL = 'WHERE ' . implode(' AND ', $where);

    $tests = $conn->query("
        SELECT t.id, t.title, t.description, t.type, t.time_minutes, t.questions_locked,
               b.name AS batch_name, c.title AS course_title,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count,
               ta.id          AS attempt_id,
               ta.status      AS attempt_status,
               ta.score,
               ta.percentage,
               ta.correct_count,
               ta.wrong_count,
               ta.unanswered_count,
               ta.submitted_at,
               ta.allow_reattempt,
               ta.started_at
        FROM tests t
        JOIN batches b ON t.batch_id  = b.id
        JOIN courses c ON b.course_id = c.id
        LEFT JOIN test_attempts ta ON ta.test_id = t.id AND ta.student_id = $me
        $wSQL
        ORDER BY t.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // Enrolled batches for filter dropdown
    $batches = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM batch_students bs
        JOIN batches b ON bs.batch_id  = b.id
        JOIN courses c ON b.course_id  = c.id
        WHERE bs.student_id = $me AND b.status = 'active'
        ORDER BY c.title, b.name
    ")->fetch_all(MYSQLI_ASSOC);

    // Compute time_remaining for in-progress attempts
    foreach ($tests as &$t) {
        if ($t['attempt_status'] === 'in_progress' && $t['started_at']) {
            $elapsed = time() - strtotime($t['started_at']);
            $t['time_remaining'] = max(0, $t['time_minutes'] * 60 - $elapsed);
        } else {
            $t['time_remaining'] = null;
        }
    }

    jsonResponse('success', 'OK', ['tests' => $tests, 'batches' => $batches]);
}

// ── GET SINGLE TEST (pre-start info) ─────────────────────────
function getTest()
{
    global $conn, $me;
    $testId = (int)($_POST['test_id'] ?? 0);
    if (!$testId) jsonResponse('error', 'Invalid test ID');

    // Must belong to student's enrolled batch
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.description, t.type, t.time_minutes,
               b.name AS batch_name, c.title AS course_title,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count,
               ta.id AS attempt_id, ta.status AS attempt_status,
               ta.score, ta.percentage, ta.correct_count, ta.wrong_count,
               ta.unanswered_count, ta.submitted_at, ta.allow_reattempt, ta.started_at
        FROM tests t
        JOIN batches b ON t.batch_id  = b.id
        JOIN courses c ON b.course_id = c.id
        LEFT JOIN test_attempts ta ON ta.test_id = t.id AND ta.student_id = ?
        WHERE t.id = ?
          AND t.status = 'active'
          AND t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
    ");
    $stmt->bind_param('iii', $me, $testId, $me);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$test) jsonResponse('error', 'Test not found or not available to you.');
    jsonResponse('success', 'OK', $test);
}

// ── START / RESUME TEST ───────────────────────────────────────
function startTest()
{
    global $conn, $me;
    $testId = (int)($_POST['test_id'] ?? 0);
    if (!$testId) jsonResponse('error', 'Invalid test ID');

    // Verify access
    $stmt = $conn->prepare("
        SELECT t.* FROM tests t
        WHERE t.id = ? AND t.status = 'active'
          AND t.type IN ('weekly','monthly')
          AND t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
    ");
    $stmt->bind_param('ii', $testId, $me);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$test) jsonResponse('error', 'Test not found or not available to you.');

    $qCount = (int)$conn->query("SELECT COUNT(*) AS cnt FROM test_question_map WHERE test_id=$testId")->fetch_assoc()['cnt'];
    if ($qCount === 0) jsonResponse('error', 'This test has no questions yet.');

    // Check existing attempt
    $existing = $conn->query("
        SELECT * FROM test_attempts WHERE test_id=$testId AND student_id=$me LIMIT 1
    ")->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'in_progress') {
            // Resume — check time
            $elapsed   = time() - strtotime($existing['started_at']);
            $remaining = max(0, ($test['time_minutes'] * 60) - $elapsed);
            if ($remaining <= 0) {
                // Auto-submit expired attempt
                _calculateAndSave($conn, (int)$existing['id'], $testId, $me);
                jsonResponse('error', 'Your previous attempt timed out and has been auto-submitted.');
            }
            jsonResponse('success', 'resumed', [
                'attempt_id'    => (int)$existing['id'],
                'time_remaining' => $remaining,
                'test_title'    => $test['title'],
                'time_minutes'  => $test['time_minutes'],
            ]);
        }
        // Already submitted
        if ((int)$existing['allow_reattempt'] !== 1) {
            jsonResponse('error', 'You have already submitted this test. Only one attempt is allowed unless your teacher grants a re-attempt.');
        }
        // Re-attempt allowed — delete old
        $conn->query("DELETE FROM test_attempts WHERE id={$existing['id']}");
    }

    // Create new attempt
    $stmt = $conn->prepare("
        INSERT INTO test_attempts (test_id, student_id, status, total_questions, started_at)
        VALUES (?, ?, 'in_progress', ?, NOW())
    ");
    $stmt->bind_param('iii', $testId, $me, $qCount);
    if (!$stmt->execute()) jsonResponse('error', 'Failed to start test. Please try again.');
    $attemptId = (int)$conn->insert_id;
    $stmt->close();

    logActivity($conn, $me, "Started test ID $testId", 'student_tests');
    jsonResponse('success', 'started', [
        'attempt_id'     => $attemptId,
        'time_remaining' => $test['time_minutes'] * 60,
        'test_title'     => $test['title'],
        'time_minutes'   => $test['time_minutes'],
    ]);
}

// ── GET QUESTIONS (never exposes is_correct) ─────────────────
function getQuestions()
{
    global $conn, $me;
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    if (!$attemptId) jsonResponse('error', 'Invalid attempt');

    // Verify attempt belongs to this student and is in_progress
    $attempt = $conn->query("
        SELECT ta.*, t.time_minutes, t.title AS test_title, t.batch_id
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.id = $attemptId AND ta.student_id = $me
    ")->fetch_assoc();
    if (!$attempt) jsonResponse('error', 'Attempt not found');
    if ($attempt['status'] !== 'in_progress') jsonResponse('error', 'This attempt has already been submitted.');

    $testId = (int)$attempt['test_id'];

    // Questions in order
    $stmt = $conn->prepare("
        SELECT tq.id AS question_id, tq.question_text, tq.is_code, tqm.sort_order
        FROM test_question_map tqm
        JOIN test_questions tq ON tqm.question_id = tq.id
        WHERE tqm.test_id = ?
        ORDER BY tqm.sort_order ASC
    ");
    $stmt->bind_param('i', $testId);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($questions as &$q) {
        $os = $conn->prepare("SELECT id AS option_id, option_text, sort_order FROM test_options WHERE question_id = ? ORDER BY sort_order ASC");
        $os->bind_param('i', $q['question_id']);
        $os->execute();
        $q['options'] = $os->get_result()->fetch_all(MYSQLI_ASSOC);
        $os->close();
    }

    // Already-saved answers
    $saved = [];
    $rows  = $conn->query("SELECT question_id, selected_option_id FROM test_answers WHERE attempt_id=$attemptId")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) $saved[(int)$r['question_id']] = (int)$r['selected_option_id'];
    foreach ($questions as &$q) {
        $qid = (int)$q['question_id'];
        $q['selected_option_id'] = $saved[$qid] ?? null;
    }

    $elapsed   = time() - strtotime($attempt['started_at']);
    $remaining = max(0, $attempt['time_minutes'] * 60 - $elapsed);

    jsonResponse('success', 'OK', [
        'questions'      => $questions,
        'time_remaining' => $remaining,
        'test_title'     => $attempt['test_title'],
    ]);
}

// ── SAVE ANSWER ───────────────────────────────────────────────
function saveAnswer()
{
    global $conn, $me;
    $attemptId  = (int)($_POST['attempt_id']  ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);
    $optionIdRaw = $_POST['option_id'] ?? '';
    $optionId    = ($optionIdRaw !== '' && $optionIdRaw !== null) ? (int)$optionIdRaw : null;

    if (!$attemptId || !$questionId) jsonResponse('error', 'Invalid data');

    // Verify attempt
    $attempt = $conn->query("
        SELECT ta.started_at, t.time_minutes, t.id AS test_id
        FROM test_attempts ta JOIN tests t ON ta.test_id = t.id
        WHERE ta.id = $attemptId AND ta.student_id = $me AND ta.status = 'in_progress'
    ")->fetch_assoc();
    if (!$attempt) jsonResponse('error', 'Invalid attempt');

    // Time check (30s grace)
    $elapsed = time() - strtotime($attempt['started_at']);
    if ($elapsed > ($attempt['time_minutes'] * 60 + 30)) jsonResponse('error', 'Time expired');

    // Validate question belongs to this test
    $qOk = $conn->query("SELECT id FROM test_question_map WHERE test_id={$attempt['test_id']} AND question_id=$questionId")->fetch_assoc();
    if (!$qOk) jsonResponse('error', 'Invalid question');

    // Delete existing answer first (clean upsert)
    $del = $conn->prepare("DELETE FROM test_answers WHERE attempt_id = ? AND question_id = ?");
    $del->bind_param('ii', $attemptId, $questionId);
    $del->execute();
    $del->close();

    if ($optionId !== null) {
        $optOk = $conn->query("SELECT is_correct FROM test_options WHERE id=$optionId AND question_id=$questionId")->fetch_assoc();
        if (!$optOk) jsonResponse('error', 'Invalid option');

        $isCorrect    = (int)$optOk['is_correct'];
        $marksAwarded = 0.00;
        $ins = $conn->prepare("INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded) VALUES (?,?,?,?,?)");
        $ins->bind_param('iiiid', $attemptId, $questionId, $optionId, $isCorrect, $marksAwarded);
        $ins->execute();
        $ins->close();
    }

    $answered = (int)$conn->query("SELECT COUNT(*) AS cnt FROM test_answers WHERE attempt_id=$attemptId")->fetch_assoc()['cnt'];
    jsonResponse('success', 'Saved', ['answered' => $answered]);
}

// ── SUBMIT TEST ───────────────────────────────────────────────
function submitTest()
{
    global $conn, $me;
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    if (!$attemptId) jsonResponse('error', 'Invalid attempt');

    $attempt = $conn->query("SELECT * FROM test_attempts WHERE id=$attemptId AND student_id=$me AND status='in_progress'")->fetch_assoc();
    if (!$attempt) jsonResponse('error', 'Attempt not found or already submitted.');

    try {
        $result = _calculateAndSave($conn, $attemptId, (int)$attempt['test_id'], $me);
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to submit: ' . $e->getMessage());
    }

    jsonResponse('success', 'Test submitted successfully!', $result);
}

// ── MY RESULTS — all test history for this student ───────────
function myResults()
{
    global $conn, $me;

    $attempts = $conn->query("
        SELECT ta.id, ta.score, ta.percentage, ta.correct_count, ta.wrong_count,
               ta.unanswered_count, ta.total_questions, ta.submitted_at, ta.status,
               t.title AS test_title, t.type, t.time_minutes,
               b.name AS batch_name, c.title AS course_title
        FROM test_attempts ta
        JOIN tests   t ON ta.test_id   = t.id
        JOIN batches b ON t.batch_id   = b.id
        JOIN courses c ON b.course_id  = c.id
        WHERE ta.student_id = $me AND ta.status = 'submitted'
        ORDER BY ta.submitted_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['attempts' => $attempts]);
}

// ── Shared: calculate score, update test_attempts ────────────
function _calculateAndSave($conn, $attemptId, $testId, $studentId)
{
    $totalQ = (int)$conn->query("SELECT COUNT(*) AS cnt FROM test_question_map WHERE test_id=$testId")->fetch_assoc()['cnt'];

    // Get latest answer per question
    $latestAnswers = $conn->query("
        SELECT ta.question_id, ta.selected_option_id, opt.is_correct
        FROM test_answers ta
        INNER JOIN (
            SELECT question_id, MAX(id) AS max_id FROM test_answers WHERE attempt_id=$attemptId GROUP BY question_id
        ) latest ON ta.question_id = latest.question_id AND ta.id = latest.max_id
        LEFT JOIN test_options opt ON ta.selected_option_id = opt.id
        WHERE ta.attempt_id = $attemptId
    ")->fetch_all(MYSQLI_ASSOC);

    $correctCount = 0;
    $wrongCount   = 0;
    $answeredQIds = [];

    foreach ($latestAnswers as $a) {
        $answeredQIds[] = (int)$a['question_id'];
        if ((int)$a['is_correct'] === 1) $correctCount++;
        else                              $wrongCount++;
    }

    $unanswered = $totalQ - count(array_unique($answeredQIds));
    $score      = max(0.0, (float)$correctCount - ($wrongCount * 0.25));
    $percentage = $totalQ > 0 ? ($score / $totalQ) * 100.0 : 0.0;

    // Clean duplicates, write final answer rows
    $conn->query("DELETE FROM test_answers WHERE attempt_id=$attemptId");
    foreach ($latestAnswers as $a) {
        $qid   = (int)$a['question_id'];
        $optId = (int)$a['selected_option_id'];
        $corr  = (int)$a['is_correct'];
        $marks = $corr ? 1.00 : -0.25;
        $conn->query("INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded) VALUES ($attemptId,$qid,$optId,$corr,$marks)");
    }

    $stmt = $conn->prepare("
        UPDATE test_attempts SET
            status           = 'submitted',
            score            = ?,
            percentage       = ?,
            total_questions  = ?,
            correct_count    = ?,
            wrong_count      = ?,
            unanswered_count = ?,
            submitted_at     = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param('ddiiiii', $score, $percentage, $totalQ, $correctCount, $wrongCount, $unanswered, $attemptId);
    if (!$stmt->execute()) throw new Exception('DB update failed: ' . $conn->error);
    $stmt->close();

    // Lock test questions on first submission
    $conn->query("UPDATE tests SET questions_locked=1 WHERE id=$testId AND questions_locked=0");

    logActivity($conn, $studentId, "Submitted test attempt ID $attemptId", 'student_tests');

    $band = _getBand($percentage);
    return [
        'score'            => round($score, 2),
        'percentage'       => round($percentage, 2),
        'total_questions'  => $totalQ,
        'correct_count'    => $correctCount,
        'wrong_count'      => $wrongCount,
        'unanswered_count' => $unanswered,
        'band'             => $band['label'],
        'band_color'       => $band['color'],
    ];
}

function _getBand($pct)
{
    if ($pct >= 90) return ['label' => 'Superb',    'color' => '#10b981'];
    if ($pct >= 75) return ['label' => 'Excellent', 'color' => '#6366f1'];
    if ($pct >= 60) return ['label' => 'Good',      'color' => '#06b6d4'];
    if ($pct >= 40) return ['label' => 'Average',   'color' => '#f59e0b'];
    return                  ['label' => 'Weak',      'color' => '#ef4444'];
}
