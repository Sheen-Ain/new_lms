<?php
// ============================================================
// ENTRY TEST AJAX — Pre-auth test engine
// Uses its own session key: $_SESSION['entry_test']
// Actions: auth, get_questions, save_answer, submit_test
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'auth':
        entryTestAuth();
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
    default:
        jsonResponse('error', 'Unknown action');
}

// ── AUTH — verify student, check application, create/resume attempt ─
function entryTestAuth()
{
    global $conn;

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $batchId    = (int)($_POST['batch_id'] ?? 0);

    if (!$identifier || !$password || !$batchId) {
        jsonResponse('error', 'All fields are required.');
    }

    // Find the student
    $isStudentId = preg_match('/^\d{5}$/', $identifier);
    $field       = $isStudentId ? 'u.user_id_number' : 'u.email';
    $stmt        = $conn->prepare("SELECT u.* FROM users u WHERE $field = ? LIMIT 1");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse('error', 'Invalid Student ID or password.');
    }
    if (!$user['is_verified']) {
        jsonResponse('error', 'Please verify your email address before taking the test.');
    }
    if ($user['status'] !== 'active') {
        jsonResponse('error', 'Your account is inactive. Please contact support.');
    }

    // Must have student role
    $roleCheck = $conn->query("
        SELECT ur.id FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = {$user['id']} AND r.name = 'student'
    ")->fetch_assoc();
    if (!$roleCheck) jsonResponse('error', 'This account does not have student access.');

    // Validate batch
    $batch = $conn->query("SELECT id, name FROM batches WHERE id=$batchId AND status='active'")->fetch_assoc();
    if (!$batch) jsonResponse('error', 'Selected batch is not available.');

    // Must have an application for this batch
    $app = $conn->query("
        SELECT id, status FROM course_applications
        WHERE user_id={$user['id']} AND batch_id=$batchId
    ")->fetch_assoc();

    if (!$app) {
        jsonResponse('error', 'You have not applied for this batch. Please apply first from the login page.');
    }

    // Approved students should use the LMS login, not the entry test
    if ($app['status'] === 'approved') {
        jsonResponse('error', 'You are already enrolled in this batch. Use the main login form.');
    }
    if ($app['status'] === 'rejected') {
        jsonResponse('error', 'Your application for this batch was not approved.');
    }

    // Get active entry test for this batch
    $test = $conn->query("
        SELECT * FROM tests
        WHERE batch_id=$batchId AND type='entry' AND entry_active=1 AND status='active'
        LIMIT 1
    ")->fetch_assoc();
    if (!$test) {
        jsonResponse('error', 'No active entry test found for this batch. Please check back later.');
    }

    // Check question count
    $qCount = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM test_question_map WHERE test_id={$test['id']}
    ")->fetch_assoc()['cnt'];
    if ($qCount === 0) {
        jsonResponse('error', 'This test has no questions yet. Please contact admin.');
    }

    // Check for existing attempt
    $existing = $conn->query("
        SELECT * FROM test_attempts
        WHERE test_id={$test['id']} AND student_id={$user['id']}
        ORDER BY started_at DESC LIMIT 1
    ")->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'in_progress') {
            // --- RESUME in-progress attempt ---
            $timeElapsed   = time() - strtotime($existing['started_at']);
            $timeRemaining = max(0, ($test['time_minutes'] * 60) - $timeElapsed);

            if ($timeRemaining <= 0) {
                // Timed out while abandoned — auto-submit it now
                _calculateAndSave($conn, (int)$existing['id'], (int)$test['id'], (int)$user['id']);
                jsonResponse('error', 'Your previous attempt timed out and has been automatically submitted.');
            }

            $_SESSION['entry_test'] = _buildSession($user, $test, (int)$existing['id'], $batchId, strtotime($existing['started_at']));
            jsonResponse('success', 'resumed', ['redirect' => BASE_PATH . '/auth/entry-test.php']);
        } else {
            // --- Attempt already submitted ---
            if ((int)$existing['allow_reattempt'] !== 1) {
                jsonResponse('error', 'You have already submitted this entry test. Only one attempt is allowed unless admin grants a re-attempt.');
            }
            // Re-attempt granted — delete the old attempt (answers cascade)
            $conn->query("DELETE FROM test_attempts WHERE id={$existing['id']}");
        }
    }

    // Create new attempt
    $stmt = $conn->prepare("
        INSERT INTO test_attempts
            (test_id, student_id, application_id, status, total_questions, started_at)
        VALUES (?, ?, ?, 'in_progress', ?, NOW())
    ");
    $stmt->bind_param('iiii', $test['id'], $user['id'], $app['id'], $qCount);
    if (!$stmt->execute()) jsonResponse('error', 'Failed to start test. Please try again.');
    $attemptId = (int)$conn->insert_id;
    $stmt->close();

    $_SESSION['entry_test'] = _buildSession($user, $test, $attemptId, $batchId, time());

    logActivity($conn, $user['id'], "Started entry test: {$test['title']}", 'entry_test');
    jsonResponse('success', 'ok', ['redirect' => BASE_PATH . '/auth/entry-test.php']);
}

// ── GET QUESTIONS ────────────────────────────────────────────
function getQuestions()
{
    global $conn;
    _requireSession();
    $s         = $_SESSION['entry_test'];
    $testId    = (int)$s['test_id'];
    $attemptId = (int)$s['attempt_id'];

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

    // Options (never expose is_correct to client)
    foreach ($questions as &$q) {
        $os = $conn->prepare("
            SELECT id AS option_id, option_text, sort_order
            FROM test_options
            WHERE question_id = ?
            ORDER BY sort_order ASC
        ");
        $os->bind_param('i', $q['question_id']);
        $os->execute();
        $q['options'] = $os->get_result()->fetch_all(MYSQLI_ASSOC);
        $os->close();
    }

    // Load already-saved answers (use MAX(id) to get last saved row per question
    // in case of any accidental duplicates from previous buggy version)
    $answerRows = $conn->query("
        SELECT question_id, selected_option_id
        FROM test_answers
        WHERE attempt_id=$attemptId
        GROUP BY question_id
        HAVING id = MAX(id)
    ");
    // Fallback if GROUP BY MAX trick doesn't work on all MySQL versions
    if (!$answerRows) {
        $answerRows = $conn->query("
            SELECT question_id, selected_option_id FROM test_answers
            WHERE attempt_id=$attemptId
        ");
    }
    $savedAnswers = [];
    if ($answerRows) {
        foreach ($answerRows->fetch_all(MYSQLI_ASSOC) as $row) {
            $savedAnswers[(int)$row['question_id']] = (int)$row['selected_option_id'];
        }
    }
    foreach ($questions as &$q) {
        $qid = (int)$q['question_id'];
        $q['selected_option_id'] = isset($savedAnswers[$qid]) ? $savedAnswers[$qid] : null;
    }

    $timeElapsed   = time() - $s['started_at'];
    $timeRemaining = max(0, $s['time_limit'] - $timeElapsed);

    jsonResponse('success', 'OK', [
        'questions'      => $questions,
        'time_remaining' => $timeRemaining,
        'attempt_id'     => $attemptId,
        'test_title'     => $s['test_title'],
        'student_name'   => $s['student_name'],
    ]);
}

// ── SAVE ANSWER ──────────────────────────────────────────────
function saveAnswer()
{
    global $conn;
    _requireSession();
    $s         = $_SESSION['entry_test'];
    $attemptId = (int)$s['attempt_id'];
    $testId    = (int)$s['test_id'];

    $questionId = (int)($_POST['question_id'] ?? 0);
    // Use explicit string check — option_id can be "0" which is falsy in PHP
    $optionIdRaw = $_POST['option_id'] ?? '';
    $optionId    = ($optionIdRaw !== '' && $optionIdRaw !== null) ? (int)$optionIdRaw : null;

    if (!$questionId) jsonResponse('error', 'Invalid question');

    // Validate question belongs to this test
    $qOk = $conn->query("
        SELECT id FROM test_question_map
        WHERE test_id=$testId AND question_id=$questionId
    ")->fetch_assoc();
    if (!$qOk) jsonResponse('error', 'Question not found in this test');

    // Check time hasn't expired (30s grace period)
    $timeElapsed = time() - $s['started_at'];
    if ($timeElapsed > ($s['time_limit'] + 30)) {
        jsonResponse('error', 'Time expired');
    }

    // Always DELETE existing answer first (prevents duplicate rows regardless of schema constraints)
    $del = $conn->prepare("DELETE FROM test_answers WHERE attempt_id=? AND question_id=?");
    $del->bind_param('ii', $attemptId, $questionId);
    $del->execute();
    $del->close();

    if ($optionId !== null) {
        // Validate option belongs to this question
        $optOk = $conn->query("
            SELECT is_correct FROM test_options
            WHERE id=$optionId AND question_id=$questionId
        ")->fetch_assoc();
        if (!$optOk) jsonResponse('error', 'Invalid option');

        $isCorrect    = (int)$optOk['is_correct'];
        $marksAwarded = 0.00; // Will be properly recalculated on final submit

        $stmt = $conn->prepare("
            INSERT INTO test_answers
                (attempt_id, question_id, selected_option_id, is_correct, marks_awarded)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiiid', $attemptId, $questionId, $optionId, $isCorrect, $marksAwarded);
        $stmt->execute();
        $stmt->close();
    }
    // If optionId is null: we already deleted the row above — answer is cleared

    $answered = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM test_answers WHERE attempt_id=$attemptId
    ")->fetch_assoc()['cnt'];

    jsonResponse('success', 'Saved', ['answered' => $answered]);
}

// ── SUBMIT TEST ──────────────────────────────────────────────
function submitTest()
{
    global $conn;
    _requireSession();
    $s = $_SESSION['entry_test'];

    try {
        $result = _calculateAndSave(
            $conn,
            (int)$s['attempt_id'],
            (int)$s['test_id'],
            (int)$s['student_id']
        );
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to submit test: ' . $e->getMessage());
    }

    // Store result for result-only page fallback, clear active session
    $_SESSION['entry_test_result'] = $result;
    unset($_SESSION['entry_test']);

    jsonResponse('success', 'Test submitted successfully!', $result);
}

// ── Calculate score, update DB ───────────────────────────────
function _calculateAndSave($conn, $attemptId, $testId, $studentId)
{
    // Total questions in test
    $totalQ = (int)$conn->query("
        SELECT COUNT(*) AS cnt FROM test_question_map WHERE test_id=$testId
    ")->fetch_assoc()['cnt'];

    // For each question, get the LATEST saved answer (handle any legacy duplicates)
    // We group by question_id and pick the one with the highest id (most recent)
    $latestAnswers = $conn->query("
        SELECT ta.question_id, ta.selected_option_id, opt.is_correct
        FROM test_answers ta
        INNER JOIN (
            SELECT question_id, MAX(id) AS max_id
            FROM test_answers
            WHERE attempt_id = $attemptId
            GROUP BY question_id
        ) latest ON ta.question_id = latest.question_id AND ta.id = latest.max_id
        LEFT JOIN test_options opt ON ta.selected_option_id = opt.id
        WHERE ta.attempt_id = $attemptId
    ")->fetch_all(MYSQLI_ASSOC);

    $correctCount  = 0;
    $wrongCount    = 0;
    $answeredQIds  = [];

    foreach ($latestAnswers as $a) {
        $answeredQIds[] = (int)$a['question_id'];
        if ((int)$a['is_correct'] === 1) $correctCount++;
        else                              $wrongCount++;
    }

    $unansweredCount = $totalQ - count(array_unique($answeredQIds));

    // Score: +1 correct, -0.25 wrong, minimum 0
    $score      = max(0.0, (float)$correctCount - ($wrongCount * 0.25));
    $percentage = $totalQ > 0 ? ($score / $totalQ) * 100.0 : 0.0;

    // Clean up any duplicate answer rows — keep only latest per question
    // Delete all, then re-insert the correct ones
    $conn->query("DELETE FROM test_answers WHERE attempt_id=$attemptId");

    foreach ($latestAnswers as $a) {
        $qid     = (int)$a['question_id'];
        $optId   = (int)$a['selected_option_id'];
        $correct = (int)$a['is_correct'];
        $marks   = $correct ? 1.00 : -0.25;
        $conn->query("
            INSERT INTO test_answers (attempt_id, question_id, selected_option_id, is_correct, marks_awarded)
            VALUES ($attemptId, $qid, $optId, $correct, $marks)
        ");
    }

    // FIX: correct bind_param type string — 7 vars, 7 type chars: d d i i i i i
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
    $stmt->bind_param('ddiiiii', $score, $percentage, $totalQ, $correctCount, $wrongCount, $unansweredCount, $attemptId);
    if (!$stmt->execute()) throw new Exception('Failed to save attempt: ' . $conn->error);
    $stmt->close();

    // Update application status to test_submitted (only if still pending)
    $conn->query("
        UPDATE course_applications
        SET status = 'test_submitted'
        WHERE user_id = $studentId
          AND status  = 'pending'
          AND batch_id IN (SELECT batch_id FROM tests WHERE id=$testId)
    ");

    // Lock test questions on first ever submission
    $conn->query("UPDATE tests SET questions_locked=1 WHERE id=$testId AND questions_locked=0");

    logActivity($conn, $studentId, "Submitted entry test attempt ID $attemptId", 'entry_test');

    $band = _getBand($percentage);
    return [
        'score'            => round($score, 2),
        'percentage'       => round($percentage, 2),
        'total_questions'  => $totalQ,
        'correct_count'    => $correctCount,
        'wrong_count'      => $wrongCount,
        'unanswered_count' => $unansweredCount,
        'band'             => $band['label'],
        'band_color'       => $band['color'],
    ];
}

function _getBand($percentage)
{
    if ($percentage >= 90) return ['label' => 'Superb',    'color' => '#10b981'];
    if ($percentage >= 75) return ['label' => 'Excellent', 'color' => '#6366f1'];
    if ($percentage >= 60) return ['label' => 'Good',      'color' => '#06b6d4'];
    if ($percentage >= 40) return ['label' => 'Average',   'color' => '#f59e0b'];
    return                        ['label' => 'Weak',      'color' => '#ef4444'];
}

function _requireSession()
{
    if (empty($_SESSION['entry_test'])) {
        jsonResponse('error', 'Session expired. Please sign in again.');
    }
}

function _buildSession($user, $test, $attemptId, $batchId, $startedAt)
{
    return [
        'student_id'   => (int)$user['id'],
        'student_name' => $user['full_name'],
        'student_uid'  => $user['user_id_number'],
        'test_id'      => (int)$test['id'],
        'test_title'   => $test['title'],
        'test_desc'    => $test['description'] ?? '',
        'attempt_id'   => $attemptId,
        'batch_id'     => $batchId,
        'time_limit'   => (int)$test['time_minutes'] * 60,
        'started_at'   => $startedAt,
    ];
}
