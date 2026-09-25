<?php
// ============================================================
// TESTS AJAX — Full handler
// Actions: list, get_one, get_questions, get_details,
//          create, update, copy_test, update_details,
//          delete, bulk_delete, bulk_status,
//          toggle_entry_live, allow_reattempt, export_excel,
//          get_teacher_batches
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!in_array($myRole, ['admin', 'teacher'])) jsonResponse('error', 'Access denied');

switch ($action) {
    case 'list':
        listTests();
        break;
    case 'get_one':
        getOne();
        break;
    case 'get_questions':
        getQuestions();
        break;
    case 'get_details':
        getDetails();
        break;
    case 'create':
        saveTest();
        break;
    case 'update':
        updateTest();
        break;
    case 'copy_test':
        copyTest();
        break;
    case 'update_details':
        updateDetails();
        break;
    case 'delete':
        deleteTest();
        break;
    case 'bulk_delete':
        bulkDelete();
        break;
    case 'bulk_status':
        bulkStatus();
        break;
    case 'toggle_entry_live':
        toggleEntryLive();
        break;
    case 'allow_reattempt':
        allowReattempt();
        break;
    case 'export_excel':
        exportExcel();
        break;
    case 'get_teacher_batches':
        getTeacherBatches();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}

// ── List tests ──────────────────────────────────────────────
function listTests()
{
    global $conn, $me, $myRole;

    $page    = max(1, (int)($_POST['page'] ?? 1));
    $perPage = min(100, (int)($_POST['per_page'] ?? 10));
    $search  = trim($_POST['search'] ?? '');
    $type    = trim($_POST['type'] ?? '');
    $status  = trim($_POST['status'] ?? '');
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];
    $types  = '';

    if ($myRole === 'teacher') {
        $where[]  = "(t.created_by=? OR t.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?))";
        $params[] = $me;
        $params[] = $me;
        $types   .= 'ii';
    }
    if ($search) {
        $s        = "%$search%";
        $where[]  = "t.title LIKE ?";
        $params[] = $s;
        $types   .= 's';
    }
    if ($type) {
        $where[]  = "t.type=?";
        $params[] = $type;
        $types   .= 's';
    }
    if ($status) {
        $where[]  = "t.status=?";
        $params[] = $status;
        $types   .= 's';
    }

    $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cs = $conn->prepare("SELECT COUNT(*) AS cnt FROM tests t $wSQL");
    if ($types) $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
    $cs->close();

    // All tests are batch-scoped; course is derived via batch
    $sql = "
        SELECT t.*,
               b.name  AS batch_name,
               c.title AS course_title,
               u.full_name AS creator_name,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id=t.id) AS question_count,
               (SELECT COUNT(*) FROM test_attempts     WHERE test_id=t.id) AS attempt_count
        FROM tests t
        LEFT JOIN batches b ON t.batch_id  = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN users   u ON t.created_by = u.id
        $wSQL
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $allT = $types . 'ii';
    $allP = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($allT, ...$allP);
    $stmt->execute();
    $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $statsRow = $conn->query("
        SELECT COUNT(*) AS total,
               SUM(type='entry') AS entry,
               (SELECT COUNT(*) FROM test_attempts) AS attempts,
               SUM(entry_active=1 AND type='entry') AS live
        FROM tests
    ")->fetch_assoc();

    jsonResponse('success', 'OK', [
        'tests'    => $tests,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'stats'    => $statsRow,
    ]);
}

// ── Get single test record ──────────────────────────────────
function getOne()
{
    global $conn;
    $id = (int)($_POST['test_id'] ?? $_GET['test_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $stmt = $conn->prepare("
        SELECT t.*, b.name AS batch_name, c.title AS course_title
        FROM tests t
        LEFT JOIN batches b ON t.batch_id  = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        WHERE t.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) jsonResponse('error', 'Not found');
    jsonResponse('success', 'OK', $t);
}

// ── Get all questions + options for a test ──────────────────
function getQuestions()
{
    global $conn;
    $id = (int)($_POST['test_id'] ?? $_GET['test_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $stmt = $conn->prepare("
        SELECT tq.id AS question_id, tq.question_text, tq.is_code, tqm.sort_order
        FROM test_question_map tqm
        JOIN test_questions tq ON tqm.question_id = tq.id
        WHERE tqm.test_id = ?
        ORDER BY tqm.sort_order ASC
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($questions as &$q) {
        $os = $conn->prepare("
            SELECT option_text, is_correct, sort_order
            FROM test_options
            WHERE question_id = ?
            ORDER BY sort_order ASC
        ");
        $os->bind_param('i', $q['question_id']);
        $os->execute();
        $q['options'] = $os->get_result()->fetch_all(MYSQLI_ASSOC);
        $os->close();
    }

    jsonResponse('success', 'OK', ['questions' => $questions]);
}

// ── Get full test details with attempts ─────────────────────
function getDetails()
{
    global $conn;
    $id = (int)($_POST['test_id'] ?? $_GET['test_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $stmt = $conn->prepare("
        SELECT t.*,
               b.name  AS batch_name,
               c.title AS course_title,
               u.full_name AS creator_name,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id=t.id) AS question_count
        FROM tests t
        LEFT JOIN batches b ON t.batch_id  = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN users   u ON t.created_by = u.id
        WHERE t.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$test) jsonResponse('error', 'Not found');

    $stmt = $conn->prepare("
        SELECT ta.*,
               u.full_name, u.user_id_number AS student_id_number,
               ca.status    AS application_status,
               ca.rejection_reason
        FROM test_attempts ta
        JOIN users u ON ta.student_id = u.id
        LEFT JOIN course_applications ca ON ta.application_id = ca.id
        WHERE ta.test_id = ?
        ORDER BY ta.percentage DESC, ta.submitted_at ASC
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonResponse('success', 'OK', ['test' => $test, 'attempts' => $attempts]);
}

// ── Create test ─────────────────────────────────────────────
function saveTest()
{
    global $conn, $me, $myRole;

    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $batchId      = (int)($_POST['batch_id'] ?? 0) ?: null;
    $timeMinutes  = max(5, (int)($_POST['time_minutes'] ?? 30));
    $status       = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $questionsRaw = $_POST['questions'] ?? '[]';

    if (!$title)  jsonResponse('error', 'Title is required');
    if (!$batchId) jsonResponse('error', 'Batch is required for all test types');

    // Teachers can only create weekly/monthly — not entry
    if ($myRole === 'teacher') {
        if (!in_array($type, ['weekly', 'monthly'])) {
            jsonResponse('error', 'Teachers can only create weekly or monthly tests.');
        }
        // Verify teacher is assigned to this batch
        $check = $conn->query("SELECT id FROM batch_teachers WHERE teacher_id=$me AND batch_id=$batchId");
        if (!$check || $check->num_rows === 0) {
            jsonResponse('error', 'You are not assigned to this batch.');
        }
    } else {
        if (!in_array($type, ['entry', 'weekly', 'monthly'])) jsonResponse('error', 'Invalid test type');
    }

    $questions = json_decode($questionsRaw, true);
    if (!is_array($questions) || empty($questions)) jsonResponse('error', 'Add at least one question');

    _validateQuestions($questions);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO tests (title, description, type, batch_id, time_minutes, status, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('sssissi', $title, $description, $type, $batchId, $timeMinutes, $status, $me);
        if (!$stmt->execute()) throw new Exception('Failed to create test: ' . $conn->error);
        $testId = (int)$conn->insert_id;
        $stmt->close();

        _insertQuestions($conn, $testId, $questions, $me);

        $conn->commit();
        logActivity($conn, $me, "Created test: $title (ID $testId)", 'tests');
        jsonResponse('success', 'Test created successfully', ['test_id' => $testId]);
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse('error', $e->getMessage());
    }
}

// ── Full update — details + questions (with lock logic) ──────
function updateTest()
{
    global $conn, $me, $myRole;

    $id           = (int)($_POST['test_id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $batchId      = (int)($_POST['batch_id'] ?? 0) ?: null;
    $timeMinutes  = max(5, (int)($_POST['time_minutes'] ?? 30));
    $status       = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $questionsRaw = $_POST['questions'] ?? '[]';
    $force        = (int)($_POST['force'] ?? 0);

    if (!$id)    jsonResponse('error', 'Invalid ID');
    if (!$title) jsonResponse('error', 'Title is required');
    if (!$batchId) jsonResponse('error', 'Batch is required');

    // Teachers restricted to weekly/monthly and their assigned batches
    if ($myRole === 'teacher') {
        if (!in_array($type, ['weekly', 'monthly'])) {
            jsonResponse('error', 'Teachers can only create weekly or monthly tests.');
        }
        $chk = $conn->query("SELECT id FROM batch_teachers WHERE teacher_id=$me AND batch_id=$batchId");
        if (!$chk || $chk->num_rows === 0) jsonResponse('error', 'You are not assigned to this batch.');
        // Verify ownership/assignment of the test being edited
        $allowed = $conn->query("
            SELECT t.id FROM tests t
            LEFT JOIN batch_teachers bt ON t.batch_id = bt.batch_id AND bt.teacher_id = $me
            WHERE t.id = $id AND (t.created_by = $me OR bt.teacher_id IS NOT NULL)
        ")->fetch_assoc();
        if (!$allowed) jsonResponse('error', 'You do not have permission to edit this test.');
    } else {
        if (!in_array($type, ['entry', 'weekly', 'monthly'])) jsonResponse('error', 'Invalid test type');
    }

    $t = $conn->query("SELECT * FROM tests WHERE id=$id")->fetch_assoc();
    if (!$t) jsonResponse('error', 'Test not found');

    $questions         = json_decode($questionsRaw, true);
    $updatingQuestions = is_array($questions) && !empty($questions);

    if ($updatingQuestions) {
        _validateQuestions($questions);
    }

    // Lock check — if questions are locked and we're updating questions, need force
    if ($updatingQuestions && $t['questions_locked'] && !$force) {
        $attemptCount = (int)$conn->query("SELECT COUNT(*) AS cnt FROM test_attempts WHERE test_id=$id")->fetch_assoc()['cnt'];
        jsonResponse('locked', 'Questions are locked — students have submitted attempts.', ['attempt_count' => $attemptCount]);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE tests SET title=?, description=?, type=?, batch_id=?, time_minutes=?, status=? WHERE id=?");
        $stmt->bind_param('sssissi', $title, $description, $type, $batchId, $timeMinutes, $status, $id);
        if (!$stmt->execute()) throw new Exception('Failed to update test');
        $stmt->close();

        if ($updatingQuestions) {
            if ($force) {
                $conn->query("DELETE FROM test_attempts WHERE test_id=$id");
                $conn->query("UPDATE tests SET questions_locked=0 WHERE id=$id");
            }
            $conn->query("DELETE FROM test_question_map WHERE test_id=$id");
            _insertQuestions($conn, $id, $questions, $me);
        }

        $conn->commit();
        $logNote = $updatingQuestions ? ($force ? ' — force-reset, all attempts deleted' : ' + questions updated') : '';
        logActivity($conn, $me, "Updated test: $title (ID $id)$logNote", 'tests');
        jsonResponse('success', 'Test updated successfully');
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse('error', $e->getMessage());
    }
}

// ── Copy test ───────────────────────────────────────────────
function copyTest()
{
    global $conn, $me, $myRole;

    $id = (int)($_POST['test_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $orig = $conn->query("SELECT * FROM tests WHERE id=$id")->fetch_assoc();
    if (!$orig) jsonResponse('error', 'Test not found');

    // Teachers can only copy tests for their assigned batches
    if ($myRole === 'teacher') {
        $allowed = $conn->query("
            SELECT t.id FROM tests t
            LEFT JOIN batch_teachers bt ON t.batch_id = bt.batch_id AND bt.teacher_id = $me
            WHERE t.id = $id AND (t.created_by = $me OR bt.teacher_id IS NOT NULL)
              AND t.type IN ('weekly','monthly')
        ")->fetch_assoc();
        if (!$allowed) jsonResponse('error', 'You can only copy tests for your assigned batches.');
    }

    $conn->begin_transaction();
    try {
        $newTitle  = $orig['title'] . ' (Copy)';
        $newStatus = 'inactive';
        $stmt = $conn->prepare("INSERT INTO tests (title, description, type, batch_id, time_minutes, status, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('sssissi', $newTitle, $orig['description'], $orig['type'], $orig['batch_id'], $orig['time_minutes'], $newStatus, $me);
        if (!$stmt->execute()) throw new Exception('Failed to create copy');
        $newTestId = (int)$conn->insert_id;
        $stmt->close();

        // Get original questions
        $qs = $conn->prepare("
            SELECT tq.question_text, tq.is_code, tqm.sort_order, tq.id AS orig_q_id
            FROM test_question_map tqm
            JOIN test_questions tq ON tqm.question_id = tq.id
            WHERE tqm.test_id = ?
            ORDER BY tqm.sort_order ASC
        ");
        $qs->bind_param('i', $id);
        $qs->execute();
        $origQuestions = $qs->get_result()->fetch_all(MYSQLI_ASSOC);
        $qs->close();

        foreach ($origQuestions as $q) {
            // Copy question
            $qi = $conn->prepare("INSERT INTO test_questions (question_text, is_code, created_by) VALUES (?,?,?)");
            $qi->bind_param('sii', $q['question_text'], $q['is_code'], $me);
            $qi->execute();
            $newQId = (int)$conn->insert_id;
            $qi->close();

            // Copy options
            $opts = $conn->query("SELECT option_text, is_correct, sort_order FROM test_options WHERE question_id={$q['orig_q_id']} ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
            foreach ($opts as $opt) {
                $oi = $conn->prepare("INSERT INTO test_options (question_id, option_text, is_correct, sort_order) VALUES (?,?,?,?)");
                $oi->bind_param('isii', $newQId, $opt['option_text'], $opt['is_correct'], $opt['sort_order']);
                $oi->execute();
                $oi->close();
            }

            // Map
            $mi = $conn->prepare("INSERT INTO test_question_map (test_id, question_id, sort_order) VALUES (?,?,?)");
            $mi->bind_param('iii', $newTestId, $newQId, $q['sort_order']);
            $mi->execute();
            $mi->close();
        }

        $conn->commit();
        logActivity($conn, $me, "Copied test: \"{$orig['title']}\" → \"$newTitle\" (ID $newTestId)", 'tests');
        jsonResponse('success', 'Test copied successfully', ['test_id' => $newTestId]);
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse('error', $e->getMessage());
    }
}

// ── Update test details only ────────────────────────────────
function updateDetails()
{
    global $conn, $me;
    $id     = (int)($_POST['test_id'] ?? 0);
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $time   = max(5, (int)($_POST['time_minutes'] ?? 30));
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (!$id || !$title) jsonResponse('error', 'ID and title are required');

    $stmt = $conn->prepare("UPDATE tests SET title=?, description=?, time_minutes=?, status=? WHERE id=?");
    $stmt->bind_param('ssisi', $title, $desc, $time, $status, $id);
    if (!$stmt->execute()) jsonResponse('error', 'Update failed');
    $stmt->close();
    logActivity($conn, $me, "Updated test details: $title (ID $id)", 'tests');
    jsonResponse('success', 'Test updated');
}

// ── Delete test ─────────────────────────────────────────────
function deleteTest()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Only admins can delete tests');
    $id = (int)($_POST['test_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');
    $t = $conn->query("SELECT title FROM tests WHERE id=$id")->fetch_assoc();
    if (!$t) jsonResponse('error', 'Not found');
    $conn->query("DELETE FROM tests WHERE id=$id");
    logActivity($conn, $me, "Deleted test: {$t['title']} (ID $id)", 'tests');
    jsonResponse('success', 'Test deleted');
}

// ── Bulk delete ─────────────────────────────────────────────
function bulkDelete()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Only admins can bulk delete');
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (empty($ids)) jsonResponse('error', 'No IDs provided');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    $stmt = $conn->prepare("DELETE FROM tests WHERE id IN ($ph)");
    $stmt->bind_param($tp, ...$ids);
    $stmt->execute();
    $cnt = $stmt->affected_rows;
    $stmt->close();
    logActivity($conn, $me, "Bulk deleted $cnt tests", 'tests');
    jsonResponse('success', "$cnt test(s) deleted");
}

// ── Bulk status toggle ──────────────────────────────────────
function bulkStatus()
{
    global $conn, $me;
    $ids    = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    if (empty($ids)) jsonResponse('error', 'No IDs provided');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE tests SET status=? WHERE id IN ($ph)");
    $stmt->bind_param('s' . $tp, $status, ...$ids);
    $stmt->execute();
    $cnt = $stmt->affected_rows;
    $stmt->close();
    jsonResponse('success', "$cnt test(s) updated to $status");
}

// ── Toggle entry_active (only one live at a time) ───────────
function toggleEntryLive()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Only admins can toggle entry live');
    $id  = (int)($_POST['test_id'] ?? 0);
    $val = (int)($_POST['entry_active'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $r = $conn->query("SELECT id, title FROM tests WHERE id=$id AND type='entry'")->fetch_assoc();
    if (!$r) jsonResponse('error', 'Not an entry test');

    if ($val) $conn->query("UPDATE tests SET entry_active=0 WHERE type='entry'");
    $conn->query("UPDATE tests SET entry_active=$val WHERE id=$id");
    logActivity($conn, $me, ($val ? 'Activated' : 'Deactivated') . " entry test live: {$r['title']}", 'tests');
    jsonResponse('success', 'Updated');
}

// ── Allow re-attempt ────────────────────────────────────────
function allowReattempt()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Only admins can allow re-attempt');
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    if (!$attemptId) jsonResponse('error', 'Invalid attempt ID');
    $conn->query("UPDATE test_attempts SET allow_reattempt=1 WHERE id=$attemptId");
    logActivity($conn, $me, "Allowed re-attempt for attempt ID $attemptId", 'tests');
    jsonResponse('success', 'Re-attempt allowed');
}

// ── Export Excel (styled HTML-XLS) ──────────────────────────
function exportExcel()
{
    global $conn;
    if (ob_get_level() > 0) ob_end_clean();

    $id = (int)($_GET['test_id'] ?? $_POST['test_id'] ?? 0);
    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    $t = $conn->query("
        SELECT t.*, b.name AS batch_name, c.title AS course_title, u.full_name AS creator_name,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id=t.id) AS question_count
        FROM tests t
        LEFT JOIN batches b ON t.batch_id  = b.id
        LEFT JOIN courses c ON b.course_id = c.id
        LEFT JOIN users   u ON t.created_by = u.id
        WHERE t.id=$id
    ")->fetch_assoc();
    if (!$t) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
        exit;
    }

    $attempts = $conn->query("
        SELECT ta.*, u.full_name, u.user_id_number, u.email
        FROM test_attempts ta
        JOIN users u ON ta.student_id=u.id
        WHERE ta.test_id=$id ORDER BY ta.percentage DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $safeName = preg_replace('/[^a-z0-9_\-]+/', '_', strtolower($t['title']));
    $filename = 'test_' . $safeName . '_results_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    $scope         = ($t['course_title'] ? $t['course_title'] . ' — ' : '') . ($t['batch_name'] ?? '—');
    $totalAttempts = count($attempts);
    $avgPct        = $totalAttempts ? round(array_sum(array_column($attempts, 'percentage')) / $totalAttempts, 2) : 0;
    $superb        = count(array_filter($attempts, fn($a) => $a['percentage'] >= 90));
    $excellent     = count(array_filter($attempts, fn($a) => $a['percentage'] >= 75 && $a['percentage'] < 90));
    $good          = count(array_filter($attempts, fn($a) => $a['percentage'] >= 60 && $a['percentage'] < 75));
    $average       = count(array_filter($attempts, fn($a) => $a['percentage'] >= 40 && $a['percentage'] < 60));
    $weak          = count(array_filter($attempts, fn($a) => $a['percentage'] < 40));

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
  body{font-family:Calibri,Arial,sans-serif;font-size:10pt;}
  table{border-collapse:collapse;width:100%;}
  .title td{background:#1e1b4b;color:#fff;font-size:15pt;font-weight:bold;padding:14px 18px;}
  .sub td{background:#312e81;color:#c7d2fe;font-size:9.5pt;padding:6px 18px;}
  .gap td{height:12px;}
  .shd td{background:#4f46e5;color:#fff;font-size:10pt;font-weight:bold;padding:8px 14px;text-transform:uppercase;letter-spacing:.04em;}
  .summ td{background:#f0f9ff;border:1px solid #bae6fd;padding:7px 14px;font-size:9.5pt;}
  .sl{font-weight:bold;color:#0c4a6e;}
  .perf td{background:#fafafa;border:1px solid #e2e8f0;padding:6px 14px;font-size:9.5pt;}
  .hdr th{background:#4f46e5;color:#fff;font-size:9.5pt;font-weight:bold;padding:8px 12px;border:1px solid #6366f1;text-align:center;}
  .dr td{border:1px solid #e2e8f0;padding:7px 12px;font-size:9pt;vertical-align:middle;}
  .dr:nth-child(even) td{background:#f8fafc;}
  .c{text-align:center;} .b{font-weight:bold;}
  .sp{color:#059669;font-weight:bold;} .ex{color:#4f46e5;font-weight:bold;}
  .go{color:#0891b2;font-weight:bold;} .av{color:#d97706;font-weight:bold;} .wk{color:#dc2626;font-weight:bold;}
</style></head><body><table cellspacing="0" cellpadding="0">';

    echo '<tr class="title"><td colspan="9">📊 EduFlow LMS — Test Results Report</td></tr>';
    echo '<tr class="sub"><td colspan="9">' . htmlspecialchars($t['title']) . '  |  Type: ' . ucfirst($t['type']) . '  |  Scope: ' . htmlspecialchars($scope) . '  |  Duration: ' . $t['time_minutes'] . ' min  |  Questions: ' . ($t['question_count'] ?? '?') . '  |  Exported: ' . date('M j, Y  g:i A') . '</td></tr>';
    echo '<tr class="gap"><td colspan="9"></td></tr>';
    echo '<tr class="shd"><td colspan="9">Summary</td></tr>';
    echo '<tr class="summ"><td class="sl">Total Attempts</td><td class="c b">' . $totalAttempts . '</td><td class="sl">Average Score</td><td class="c b">' . $avgPct . '%</td><td class="sl">Questions</td><td class="c b">' . ($t['question_count'] ?? '?') . '</td><td class="sl">Created By</td><td class="c b" colspan="2">' . htmlspecialchars($t['creator_name'] ?? '—') . '</td></tr>';
    echo '<tr class="gap"><td colspan="9"></td></tr>';
    echo '<tr class="shd"><td colspan="9">Performance Distribution</td></tr>';
    echo '<tr class="perf"><td colspan="2" class="sp">🏆 Superb (90–100%)</td><td class="c b sp">' . $superb . ' students</td><td colspan="2" class="ex">⭐ Excellent (75–89%)</td><td class="c b ex">' . $excellent . ' students</td><td colspan="2" class="go">✅ Good (60–74%)</td><td class="c b go">' . $good . ' students</td></tr>';
    echo '<tr class="perf"><td colspan="2" class="av">📈 Average (40–59%)</td><td class="c b av">' . $average . ' students</td><td colspan="2" class="wk">⚠️ Weak (0–39%)</td><td class="c b wk">' . $weak . ' students</td><td colspan="3"></td></tr>';
    echo '<tr class="gap"><td colspan="9"></td></tr>';
    echo '<tr class="shd"><td colspan="9">Student Results</td></tr>';
    echo '<tr class="hdr"><th>#</th><th>Student ID</th><th>Full Name</th><th>Email</th><th>Score</th><th>Percentage</th><th>Correct</th><th>Wrong</th><th>Skipped</th></tr>';

    foreach ($attempts as $i => $a) {
        $pct  = (float)$a['percentage'];
        $cls  = $pct >= 90 ? 'sp' : ($pct >= 75 ? 'ex' : ($pct >= 60 ? 'go' : ($pct >= 40 ? 'av' : 'wk')));
        $band = $pct >= 90 ? 'Superb' : ($pct >= 75 ? 'Excellent' : ($pct >= 60 ? 'Good' : ($pct >= 40 ? 'Average' : 'Weak')));
        echo '<tr class="dr"><td class="c">' . ($i + 1) . '</td><td class="c b">' . htmlspecialchars($a['user_id_number'] ?? '—') . '</td><td>' . htmlspecialchars($a['full_name']) . '</td><td>' . htmlspecialchars($a['email'] ?? '—') . '</td><td class="c b">' . number_format((float)$a['score'], 2) . '</td><td class="c ' . $cls . '">' . number_format($pct, 2) . '% (' . $band . ')</td><td class="c b" style="color:#059669;">' . $a['correct_count'] . '</td><td class="c b" style="color:#dc2626;">' . $a['wrong_count'] . '</td><td class="c" style="color:#64748b;">' . $a['unanswered_count'] . '</td></tr>';
    }
    if (!$attempts) echo '<tr class="dr"><td colspan="9" class="c" style="color:#94a3b8;padding:20px;">No student attempts yet.</td></tr>';
    echo '</table></body></html>';
    exit;
}

// ── Shared helpers ───────────────────────────────────────────
function _validateQuestions($questions)
{
    foreach ($questions as $i => $q) {
        if (empty($q['text']))                                   jsonResponse('error', 'Question ' . ($i + 1) . ': text is required');
        if (!isset($q['options']) || count($q['options']) !== 4) jsonResponse('error', 'Question ' . ($i + 1) . ': needs exactly 4 options');
        if (!isset($q['correct']) || !in_array((int)$q['correct'], [0, 1, 2, 3])) jsonResponse('error', 'Question ' . ($i + 1) . ': mark the correct answer');
        foreach ($q['options'] as $opt) {
            if (empty(trim($opt))) jsonResponse('error', 'Question ' . ($i + 1) . ': all options must be filled');
        }
    }
}

function _insertQuestions($conn, $testId, $questions, $createdBy)
{
    foreach ($questions as $sortOrder => $q) {
        $qText  = $q['text'];
        $isCode = (int)($q['is_code'] ?? 0);

        $qs = $conn->prepare("INSERT INTO test_questions (question_text, is_code, created_by) VALUES (?,?,?)");
        $qs->bind_param('sii', $qText, $isCode, $createdBy);
        if (!$qs->execute()) throw new Exception('Failed to insert question ' . ($sortOrder + 1));
        $qId = (int)$conn->insert_id;
        $qs->close();

        foreach ($q['options'] as $optIdx => $optText) {
            $isCorrect = ($optIdx === (int)$q['correct']) ? 1 : 0;
            $os = $conn->prepare("INSERT INTO test_options (question_id, option_text, is_correct, sort_order) VALUES (?,?,?,?)");
            $os->bind_param('isii', $qId, $optText, $isCorrect, $optIdx);
            if (!$os->execute()) throw new Exception('Failed to insert option');
            $os->close();
        }

        $ms = $conn->prepare("INSERT INTO test_question_map (test_id, question_id, sort_order) VALUES (?,?,?)");
        $ms->bind_param('iii', $testId, $qId, $sortOrder);
        if (!$ms->execute()) throw new Exception('Failed to map question to test');
        $ms->close();
    }
}

// ── Get teacher's assigned batches (for wizard dropdown) ─────
function getTeacherBatches()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'teacher') jsonResponse('error', 'Access denied');

    $stmt = $conn->prepare("
        SELECT b.id, b.name, c.title AS course_title
        FROM batch_teachers bt
        JOIN batches b  ON bt.batch_id  = b.id
        JOIN courses c  ON b.course_id  = c.id
        WHERE bt.teacher_id = ? AND b.status = 'active'
        ORDER BY c.title ASC, b.name ASC
    ");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonResponse('success', 'OK', ['batches' => $batches]);
}
