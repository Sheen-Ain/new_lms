<?php
// ============================================================
// CHAT AJAX
// ============================================================

// ob_start MUST be first — before ANY require/include/output
// This ensures PHP warnings/notices don't corrupt JSON output
ob_start();

// Shutdown handler — catches fatal errors and returns clean JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'PHP Fatal: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line'],
            'data'    => []
        ]);
    }
});

set_exception_handler(function ($e) {
    if (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage(), 'data' => []]);
    exit;
});

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pusher.php';
require_once __DIR__ . '/../includes/helpers.php';

// Discard any output from requires (warnings, notices from config etc.)
ob_clean();

if (!headers_sent()) header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_conversations':
        getConversations();
        break;
    case 'get_or_create':
        getOrCreate();
        break;
    case 'get_messages':
        getMessages();
        break;
    case 'send':
        sendMessage();
        break;
    case 'mark_delivered':
        markDelivered();
        break;
    case 'mark_seen':
        markSeen();
        break;
    case 'delete_message':
        deleteMessage();
        break;
    case 'react':
        reactMessage();
        break;
    case 'typing':
        sendTyping();
        break;
    case 'get_unread_count':
        getUnreadCount();
        break;
    case 'block_user':
        blockUser();
        break;
    case 'unblock_user':
        unblockUser();
        break;
    case 'get_blocked_users':
        getBlockedUsers();
        break;
    case 'delete_conversation':
        deleteConversation();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}

// ── Get ALL permanently assigned roles for a user ─────────────────────────────
// CRITICAL: use user_roles table, NOT current_role.
// Role switching changes current_role but NOT user_roles.
// So a teacher who switched to student still has the teacher role here.
function getUserRoles(int $userId): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT r.name FROM roles r
         JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_column($rows, 'name');
}

// ── Permission check ─────────────────────────────────────────────────────────
// Rules (all based on ACTUAL assigned roles, role-switch-proof):
//
//   ADMIN    → can message anyone
//   TEACHER  → can message any other teacher/admin freely (no gender restriction)
//              can message students ONLY in their assigned batches
//   STUDENT  → can message teachers of their own batches
//              can message same-batch + same-gender students only
//              cannot initiate with admin (but can always reply/open existing conv)
//
function canChat(int $me, int $other, string $ignored): array
{
    global $conn;

    $myRoles    = getUserRoles($me);
    $otherRoles = getUserRoles($other);

    // Determine each person's effective category
    $iAmAdmin    = in_array('admin',   $myRoles);
    $iAmTeacher  = in_array('teacher', $myRoles);
    $iAmStudent  = !$iAmAdmin && !$iAmTeacher && in_array('student', $myRoles);

    $otherIsAdmin   = in_array('admin',   $otherRoles);
    $otherIsTeacher = in_array('teacher', $otherRoles);
    $otherIsStaff   = $otherIsAdmin || $otherIsTeacher;
    $otherIsStudent = !$otherIsAdmin && !$otherIsTeacher && in_array('student', $otherRoles);

    // Fetch other user's gender + status (active check)
    $stmt = $conn->prepare("SELECT gender, status FROM users WHERE id = ?");
    $stmt->bind_param('i', $other);
    $stmt->execute();
    $otherUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otherUser || $otherUser['status'] !== 'active')
        return ['ok' => false, 'reason' => 'User not found or inactive'];

    $otherGender = $otherUser['gender'];

    // ── ADMIN: can message anyone ──────────────────────────────
    if ($iAmAdmin) return ['ok' => true];

    // ── TEACHER: free with all staff, batch-restricted with students ───────────
    if ($iAmTeacher) {
        if ($otherIsStaff) return ['ok' => true]; // teacher↔teacher, teacher↔admin — no gender restriction

        if ($otherIsStudent) {
            $stmt = $conn->prepare(
                "SELECT 1 FROM batch_teachers bt
                 JOIN batch_students bs ON bt.batch_id = bs.batch_id
                 WHERE bt.teacher_id = ? AND bs.student_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $me, $other);
            $stmt->execute();
            $ok = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            return $ok
                ? ['ok' => true]
                : ['ok' => false, 'reason' => 'This student is not in any of your batches'];
        }
    }

    // ── STUDENT ────────────────────────────────────────────────
    if ($iAmStudent) {
        // Student → teacher (of their batch)
        if ($otherIsTeacher && !$otherIsAdmin) {
            $stmt = $conn->prepare(
                "SELECT 1 FROM batch_students bs
                 JOIN batch_teachers bt ON bt.batch_id = bs.batch_id
                 WHERE bs.student_id = ? AND bt.teacher_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $me, $other);
            $stmt->execute();
            $ok = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            return $ok
                ? ['ok' => true]
                : ['ok' => false, 'reason' => 'This teacher is not assigned to your batch'];
        }

        // Student → admin (pure admin, no teacher role): blocked from initiating
        if ($otherIsAdmin && !$otherIsTeacher)
            return ['ok' => false, 'reason' => 'You cannot message admins directly'];

        // Student → student: same batch + same gender
        if ($otherIsStudent) {
            $stmt = $conn->prepare(
                "SELECT 1 FROM batch_students bs1
                 JOIN batch_students bs2 ON bs1.batch_id = bs2.batch_id
                 WHERE bs1.student_id = ? AND bs2.student_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $me, $other);
            $stmt->execute();
            $sameBatch = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if (!$sameBatch) return ['ok' => false, 'reason' => 'You are not in the same batch'];

            $stmt2 = $conn->prepare("SELECT gender FROM users WHERE id = ?");
            $stmt2->bind_param('i', $me);
            $stmt2->execute();
            $myGender = $stmt2->get_result()->fetch_assoc()['gender'] ?? '';
            $stmt2->close();

            if (!$myGender || !$otherGender)
                return ['ok' => false, 'reason' => 'Gender not set — please update your profile'];
            if ($myGender !== $otherGender)
                return ['ok' => false, 'reason' => 'Students can only message same-gender classmates'];

            return ['ok' => true];
        }
    }

    return ['ok' => false, 'reason' => 'Messaging not allowed'];
}

// ── Block check helper ────────────────────────────────────────
// Returns true only if either user has blocked the other
function isBlocked(int $a, int $b): bool
{
    global $conn;
    // blocked_users table may not exist yet — guard with @ and check result
    $result = @$conn->query(
        "SELECT id FROM blocked_users
         WHERE (blocker_id=$a AND blocked_id=$b)
            OR (blocker_id=$b AND blocked_id=$a)
         LIMIT 1"
    );
    return ($result && $result->num_rows > 0);
}

// Ensure user1_id < user2_id for consistent conversation lookup
function convUserIds(int $a, int $b): array
{
    return $a < $b ? [$a, $b] : [$b, $a];
}

// ── Get Conversations ─────────────────────────────────────────
function getConversations()
{
    global $conn, $me;

    // ── Step 1: Run the original proven query (8 params — unchanged) ──────────
    $sql = "
        SELECT
            c.id,
            c.updated_at,
            IF(c.user1_id = ?, c.user2_id, c.user1_id) AS other_id,
            u.full_name,
            u.profile_picture,
            u.current_role,
            u.last_seen,
            CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS is_online,
            (
                SELECT COUNT(*) FROM messages m
                WHERE m.conversation_id = c.id
                  AND m.sender_id != ?
                  AND m.status != 'seen'
                  AND m.deleted_for_receiver = 0
                  AND m.type = 'text'
            ) AS unread_count,
            (
                SELECT m2.content FROM messages m2
                WHERE m2.conversation_id = c.id
                  AND (
                        (m2.sender_id = ?  AND m2.deleted_for_sender   = 0) OR
                        (m2.sender_id != ? AND m2.deleted_for_receiver = 0)
                      )
                ORDER BY m2.created_at DESC LIMIT 1
            ) AS last_content,
            (
                SELECT m3.type FROM messages m3
                WHERE m3.conversation_id = c.id
                ORDER BY m3.created_at DESC LIMIT 1
            ) AS last_type,
            (
                SELECT m4.sender_id FROM messages m4
                WHERE m4.conversation_id = c.id
                ORDER BY m4.created_at DESC LIMIT 1
            ) AS last_sender_id,
            (
                SELECT m5.created_at FROM messages m5
                WHERE m5.conversation_id = c.id
                ORDER BY m5.created_at DESC LIMIT 1
            ) AS last_msg_at,
            (
                SELECT m6.status FROM messages m6
                WHERE m6.conversation_id = c.id
                  AND m6.sender_id = ?
                ORDER BY m6.created_at DESC LIMIT 1
            ) AS last_my_status
        FROM conversations c
        JOIN users u ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.updated_at DESC
        LIMIT 50
    ";
    // ^ Exactly 8 `?` markers — same as original file
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiiiii', $me, $me, $me, $me, $me, $me, $me, $me);
    $stmt->execute();
    $convs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ── Step 2: Filter hidden conversations (safe — columns added by migration) ─
    $convs = array_filter($convs, function ($c) use ($me) {
        global $conn;
        // Check hidden_user1/2 columns — they exist after migration
        $cid = (int)$c['id'];
        $r = @$conn->query(
            "SELECT hidden_user1, hidden_user2, user1_id, user2_id
             FROM conversations WHERE id=$cid LIMIT 1"
        );
        if (!$r) return true; // column doesn't exist yet → show everything
        $row = $r->fetch_assoc();
        if (!$row) return true;
        if ((int)$row['user1_id'] === $me && (int)$row['hidden_user1'] === 1) return false;
        if ((int)$row['user2_id'] === $me && (int)$row['hidden_user2'] === 1) return false;
        return true;
    });

    // ── Step 3: Add block flags per conversation (graceful — table may not exist)
    $convs = array_values($convs);
    foreach ($convs as &$c) {
        $otherId         = (int)$c['other_id'];
        $c['i_blocked_them']  = 0;
        $c['they_blocked_me'] = 0;
        $r = @$conn->query(
            "SELECT blocker_id FROM blocked_users
             WHERE (blocker_id=$me AND blocked_id=$otherId)
                OR (blocker_id=$otherId AND blocked_id=$me)
             LIMIT 2"
        );
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                if ((int)$row['blocker_id'] === $me)       $c['i_blocked_them']  = 1;
                if ((int)$row['blocker_id'] === $otherId)  $c['they_blocked_me'] = 1;
            }
        }
    }
    unset($c);

    jsonResponse('success', 'OK', ['conversations' => $convs]);
}

// ── Get or Create Conversation ────────────────────────────────
function getOrCreate()
{
    global $conn, $me, $myRole;

    $otherId = (int)($_POST['other_id'] ?? 0);
    if (!$otherId || $otherId === $me) jsonResponse('error', 'Invalid user');

    [$u1, $u2] = convUserIds($me, $otherId);

    // Check if conversation already exists
    // If it does, always allow opening it (you may have received a msg from someone)
    // canChat only gates NEW conversations
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE user1_id=? AND user2_id=?");
    $stmt->bind_param('ii', $u1, $u2);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $convId = (int)$existing['id'];
        // Un-hide if they had deleted this conv and are re-opening it
        @$conn->query("UPDATE conversations SET hidden_user1=0, hidden_user1_at=NULL WHERE id=$convId AND user1_id=$me");
        @$conn->query("UPDATE conversations SET hidden_user2=0, hidden_user2_at=NULL WHERE id=$convId AND user2_id=$me");
    } else {
        // New conversation — enforce permissions
        $perm = canChat($me, $otherId, $myRole);
        if (!$perm['ok']) jsonResponse('error', $perm['reason'] ?? 'Messaging not allowed');

        $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?,?)");
        $stmt->bind_param('ii', $u1, $u2);
        if (!$stmt->execute()) jsonResponse('error', 'Failed to create conversation');
        $convId = $conn->insert_id;
        $stmt->close();
    }

    // Fetch other user info
    $stmt = $conn->prepare(
        "SELECT id, full_name, profile_picture, current_role, gender, last_seen,
                CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS is_online
         FROM users WHERE id=?"
    );
    $stmt->bind_param('i', $otherId);
    $stmt->execute();
    $other = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Block flags (graceful)
    $iBlockedThem  = false;
    $theyBlockedMe = false;
    $r = @$conn->query(
        "SELECT blocker_id FROM blocked_users
         WHERE (blocker_id=$me AND blocked_id=$otherId)
            OR (blocker_id=$otherId AND blocked_id=$me)
         LIMIT 2"
    );
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            if ((int)$row['blocker_id'] === $me)       $iBlockedThem  = true;
            if ((int)$row['blocker_id'] === $otherId)  $theyBlockedMe = true;
        }
    }

    jsonResponse('success', 'OK', [
        'conversation_id' => $convId,
        'is_new'          => !$existing,
        'other'           => $other,
        'i_blocked_them'  => $iBlockedThem,
        'they_blocked_me' => $theyBlockedMe,
    ]);
}

// ── Get Messages ─────────────────────────────────────────────
function getMessages()
{
    global $conn, $me;

    $convId  = (int)($_POST['conversation_id'] ?? 0);
    $page    = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 50;
    $offset  = ($page - 1) * $perPage;

    if (!$convId) jsonResponse('error', 'Invalid conversation');

    // Verify user is in this conversation
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
    $stmt->bind_param('iii', $convId, $me, $me);
    $stmt->execute();
    if (!$stmt->get_result()->num_rows) jsonResponse('error', 'Access denied');
    $stmt->close();

    // Check if there's a hide-after date (from delete_conversation)
    // Gracefully handles missing columns
    $hideClause = '';
    $r = @$conn->query(
        "SELECT user1_id, user2_id, hidden_user1_at, hidden_user2_at
         FROM conversations WHERE id=$convId LIMIT 1"
    );
    if ($r) {
        $cr = $r->fetch_assoc();
        if ($cr) {
            $hideAt = null;
            if ((int)$cr['user1_id'] === $me && !empty($cr['hidden_user1_at'])) {
                $hideAt = $cr['hidden_user1_at'];
            } elseif ((int)$cr['user2_id'] === $me && !empty($cr['hidden_user2_at'])) {
                $hideAt = $cr['hidden_user2_at'];
            }
            if ($hideAt) {
                $safeDate = $conn->real_escape_string($hideAt);
                $hideClause = "AND m.created_at > '$safeDate'";
            }
        }
    }

    // Fetch messages (newest first, then reverse)
    $sql = "
        SELECT m.id, m.conversation_id, m.sender_id, m.content, m.type,
               m.status, m.reactions, m.deleted_for_sender, m.deleted_for_receiver,
               m.deleted_at, m.created_at,
               u.full_name AS sender_name, u.profile_picture AS sender_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
          AND NOT (m.sender_id =  ? AND m.deleted_for_sender   = 1)
          AND NOT (m.sender_id != ? AND m.deleted_for_receiver = 1)
          $hideClause
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiii', $convId, $me, $me, $perPage, $offset);
    $stmt->execute();
    $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Count total
    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM messages m
         WHERE m.conversation_id=?
           AND NOT (m.sender_id=?  AND m.deleted_for_sender=1)
           AND NOT (m.sender_id!=? AND m.deleted_for_receiver=1)
           $hideClause"
    );
    $stmt2->bind_param('iii', $convId, $me, $me);
    $stmt2->execute();
    $total = (int)$stmt2->get_result()->fetch_assoc()['cnt'];
    $stmt2->close();

    foreach ($msgs as &$m) {
        $raw = $m['reactions'] ? json_decode($m['reactions'], true) : [];
        // Normalise reactions: support both old int-array format and new {id,name} format
        $normalised = [];
        foreach ($raw as $emoji => $reactors) {
            if (!is_array($reactors)) continue;
            $normalised[$emoji] = array_map(function ($r) {
                if (is_array($r)) return $r;                          // already {id,name}
                return ['id' => (int)$r, 'name' => 'User'];           // old int format
            }, $reactors);
        }
        $m['reactions'] = $normalised;
        $m['is_mine']   = (int)$m['sender_id'] === (int)$me ? 1 : 0;
    }
    unset($m);

    $msgs = array_reverse($msgs); // oldest first

    jsonResponse('success', 'OK', [
        'messages'  => $msgs,
        'total'     => $total,
        'page'      => $page,
        'has_more'  => ($total > $page * $perPage),
    ]);
}

// ── Send Message ─────────────────────────────────────────────
function sendMessage()
{
    global $conn, $me, $myRole;

    $otherId = (int)($_POST['other_id']   ?? 0);
    $convId  = (int)($_POST['conv_id']    ?? 0);
    $content = trim($_POST['content']     ?? '');

    if (!$content || mb_strlen($content) > 4000) jsonResponse('error', 'Invalid message');
    if (!$otherId) jsonResponse('error', 'Missing recipient');

    // Block check
    if (isBlocked($me, $otherId)) jsonResponse('error', 'Cannot send message — user is blocked');

    // Resolve conversation — existing conv skips permission check (reply always allowed)
    [$u1, $u2] = convUserIds($me, $otherId);
    if (!$convId) {
        $stmt = $conn->prepare("SELECT id FROM conversations WHERE user1_id=? AND user2_id=?");
        $stmt->bind_param('ii', $u1, $u2);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $convId = (int)$existing['id'];
        } else {
            // New conversation — enforce permissions
            $perm = canChat($me, $otherId, $myRole);
            if (!$perm['ok']) jsonResponse('error', $perm['reason'] ?? 'Not allowed');
            $stmt = $conn->prepare("INSERT INTO conversations (user1_id,user2_id) VALUES (?,?)");
            $stmt->bind_param('ii', $u1, $u2);
            $stmt->execute();
            $convId = $conn->insert_id;
            $stmt->close();
        }
    }

    // Un-hide for recipient (graceful — columns may not exist yet)
    @$conn->query("UPDATE conversations SET hidden_user1=0, hidden_user1_at=NULL WHERE id=$convId AND user1_id=$otherId");
    @$conn->query("UPDATE conversations SET hidden_user2=0, hidden_user2_at=NULL WHERE id=$convId AND user2_id=$otherId");

    // Insert message
    $stmt = $conn->prepare(
        "INSERT INTO messages (conversation_id, sender_id, content, type, status) VALUES (?,?,?,'text','sent')"
    );
    $stmt->bind_param('iis', $convId, $me, $content);
    if (!$stmt->execute()) jsonResponse('error', 'Failed to send');
    $msgId = $conn->insert_id;
    $stmt->close();

    // Touch conversation updated_at
    $conn->query("UPDATE conversations SET updated_at=NOW() WHERE id=$convId");

    // Fetch sender info
    $stmt = $conn->prepare("SELECT full_name, profile_picture FROM users WHERE id=?");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $sender = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $payload = [
        'id'              => $msgId,
        'conversation_id' => $convId,
        'sender_id'       => $me,
        'sender_name'     => $sender['full_name'],
        'sender_pic'      => $sender['profile_picture'],
        'content'         => $content,
        'type'            => 'text',
        'status'          => 'sent',
        'reactions'       => [],
        'created_at'      => date('Y-m-d H:i:s'),
    ];

    // Push to recipient
    pusherTrigger("private-user-{$otherId}", 'new-message', $payload);

    jsonResponse('success', 'sent', ['message' => $payload]);
}

// ── Mark Delivered ────────────────────────────────────────────
function markDelivered()
{
    global $conn, $me;

    $convId = (int)($_POST['conversation_id'] ?? 0);
    if (!$convId) jsonResponse('error', 'Missing conv');

    $stmt = $conn->prepare("SELECT id, user1_id, user2_id FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
    $stmt->bind_param('iii', $convId, $me, $me);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$conv) jsonResponse('error', 'Access denied');

    $senderId = (int)($conv['user1_id'] === $me ? $conv['user2_id'] : $conv['user1_id']);

    $conn->query(
        "UPDATE messages SET status='delivered'
         WHERE conversation_id=$convId
           AND sender_id=$senderId
           AND status='sent'"
    );

    if ($conn->affected_rows > 0) {
        pusherTrigger("private-user-{$senderId}", 'message-delivered', [
            'conversation_id' => $convId,
        ]);
    }

    jsonResponse('success', 'OK');
}

// ── Mark Seen ─────────────────────────────────────────────────
function markSeen()
{
    global $conn, $me;

    $convId = (int)($_POST['conversation_id'] ?? 0);
    if (!$convId) jsonResponse('error', 'Missing conv');

    $stmt = $conn->prepare("SELECT id, user1_id, user2_id FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
    $stmt->bind_param('iii', $convId, $me, $me);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$conv) jsonResponse('error', 'Access denied');

    $senderId = (int)($conv['user1_id'] === $me ? $conv['user2_id'] : $conv['user1_id']);

    $conn->query(
        "UPDATE messages SET status='seen'
         WHERE conversation_id=$convId
           AND sender_id=$senderId
           AND status IN ('sent','delivered')"
    );

    if ($conn->affected_rows > 0) {
        pusherTrigger("private-user-{$senderId}", 'message-seen', [
            'conversation_id' => $convId,
        ]);
    }

    jsonResponse('success', 'OK');
}

// ── Delete Message ────────────────────────────────────────────
function deleteMessage()
{
    global $conn, $me;

    $msgId  = (int)($_POST['message_id'] ?? 0);
    $forAll = (int)($_POST['for_all']    ?? 0);

    if (!$msgId) jsonResponse('error', 'Invalid message');

    $stmt = $conn->prepare(
        "SELECT m.*, c.user1_id, c.user2_id
         FROM messages m
         JOIN conversations c ON m.conversation_id = c.id
         WHERE m.id=? AND (c.user1_id=? OR c.user2_id=?)"
    );
    $stmt->bind_param('iii', $msgId, $me, $me);
    $stmt->execute();
    $msg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$msg) jsonResponse('error', 'Message not found');

    $isMine  = (int)$msg['sender_id'] === $me;
    $otherId = (int)($msg['user1_id'] === $me ? $msg['user2_id'] : $msg['user1_id']);
    $convId  = (int)$msg['conversation_id'];

    if ($forAll) {
        if (!$isMine) jsonResponse('error', 'Only sender can delete for everyone');
        $ageMinutes = (time() - strtotime($msg['created_at'])) / 60;
        if ($ageMinutes > 10) jsonResponse('error', 'Can only delete for everyone within 10 minutes');

        $conn->query("UPDATE messages SET type='deleted', content='', deleted_at=NOW() WHERE id=$msgId");

        pusherTrigger(
            ["private-user-{$me}", "private-user-{$otherId}"],
            'message-deleted',
            ['message_id' => $msgId, 'conversation_id' => $convId, 'for_all' => true]
        );
    } else {
        if ($isMine) {
            $conn->query("UPDATE messages SET deleted_for_sender=1 WHERE id=$msgId");
        } else {
            $conn->query("UPDATE messages SET deleted_for_receiver=1 WHERE id=$msgId");
        }
        pusherTrigger("private-user-{$me}", 'message-deleted', [
            'message_id'      => $msgId,
            'conversation_id' => $convId,
            'for_all'         => false,
        ]);
    }

    jsonResponse('success', 'Deleted');
}

// ── React ─────────────────────────────────────────────────────
// Stores reactions as: {"👍": [{"id":1,"name":"Ali"},{"id":3,"name":"Sara"}]}
// Fully backward-compatible with old int-array format
function reactMessage()
{
    global $conn, $me;

    $msgId   = (int)($_POST['message_id'] ?? 0);
    $emoji   = trim($_POST['emoji']       ?? '');
    $ALLOWED = ['👍', '❤️', '😂', '😮', '😢', '🔥'];

    if (!$msgId || !in_array($emoji, $ALLOWED)) jsonResponse('error', 'Invalid');

    $stmt = $conn->prepare(
        "SELECT m.reactions, c.user1_id, c.user2_id
         FROM messages m JOIN conversations c ON m.conversation_id=c.id
         WHERE m.id=? AND (c.user1_id=? OR c.user2_id=?)"
    );
    $stmt->bind_param('iii', $msgId, $me, $me);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) jsonResponse('error', 'Message not found');

    // Get my name for the reaction label
    $stmt2 = $conn->prepare("SELECT full_name FROM users WHERE id=?");
    $stmt2->bind_param('i', $me);
    $stmt2->execute();
    $myName = $stmt2->get_result()->fetch_assoc()['full_name'] ?? 'User';
    $stmt2->close();

    $reactions = $row['reactions'] ? json_decode($row['reactions'], true) : [];
    $otherId   = (int)($row['user1_id'] === $me ? $row['user2_id'] : $row['user1_id']);

    // Normalise old int format to {id, name} objects
    if (!empty($reactions[$emoji])) {
        $reactions[$emoji] = array_map(function ($r) {
            return is_array($r) ? $r : ['id' => (int)$r, 'name' => 'User'];
        }, $reactions[$emoji]);
    }

    // Toggle
    $alreadyIn = false;
    if (!empty($reactions[$emoji])) {
        foreach ($reactions[$emoji] as $r) {
            if ((int)(is_array($r) ? $r['id'] : $r) === $me) {
                $alreadyIn = true;
                break;
            }
        }
    }

    if ($alreadyIn) {
        // Remove my reaction
        $reactions[$emoji] = array_values(
            array_filter($reactions[$emoji], function ($r) use ($me) {
                return (int)(is_array($r) ? $r['id'] : $r) !== $me;
            })
        );
        if (empty($reactions[$emoji])) unset($reactions[$emoji]);
    } else {
        // Add my reaction
        $reactions[$emoji][] = ['id' => $me, 'name' => $myName];
    }

    $json = $conn->real_escape_string(json_encode($reactions, JSON_UNESCAPED_UNICODE));
    $conn->query("UPDATE messages SET reactions='$json' WHERE id=$msgId");

    pusherTrigger(
        ["private-user-{$me}", "private-user-{$otherId}"],
        'message-reaction',
        ['message_id' => $msgId, 'reactions' => $reactions]
    );

    jsonResponse('success', 'OK', ['reactions' => $reactions]);
}

// ── Typing Indicator ─────────────────────────────────────────
function sendTyping()
{
    global $conn, $me;

    $otherId = (int)($_POST['other_id'] ?? 0);
    $convId  = (int)($_POST['conv_id']  ?? 0);
    if (!$otherId) jsonResponse('error', 'Missing recipient');

    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id=?");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $me_name = $stmt->get_result()->fetch_assoc()['full_name'] ?? '';
    $stmt->close();

    pusherTrigger("private-user-{$otherId}", 'typing', [
        'sender_id'       => $me,
        'sender_name'     => $me_name,
        'conversation_id' => $convId,
    ]);

    jsonResponse('success', 'OK');
}

// ── Get Unread Count ──────────────────────────────────────────
function getUnreadCount()
{
    global $conn, $me;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM messages m
         JOIN conversations c ON m.conversation_id = c.id
         WHERE (c.user1_id=? OR c.user2_id=?)
           AND m.sender_id != ?
           AND m.status != 'seen'
           AND m.deleted_for_receiver = 0
           AND m.type = 'text'"
    );
    $stmt->bind_param('iii', $me, $me, $me);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    jsonResponse('success', 'OK', ['unread' => $cnt]);
}

// ── Block User ────────────────────────────────────────────────
function blockUser()
{
    global $conn, $me;
    $otherId = (int)($_POST['other_id'] ?? 0);
    if (!$otherId || $otherId === $me) jsonResponse('error', 'Invalid user');

    $r = @$conn->query("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES ($me, $otherId)");
    if (!$r) jsonResponse('error', 'Block feature not available yet — run migrations');

    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id=?");
    $stmt->bind_param('i', $otherId);
    $stmt->execute();
    $name = $stmt->get_result()->fetch_assoc()['full_name'] ?? 'User';
    $stmt->close();

    logActivity($conn, $me, "Blocked user: $name (ID $otherId)", 'chat');
    jsonResponse('success', 'User blocked');
}

// ── Unblock User ──────────────────────────────────────────────
function unblockUser()
{
    global $conn, $me;
    $otherId = (int)($_POST['other_id'] ?? 0);
    if (!$otherId) jsonResponse('error', 'Invalid user');

    @$conn->query("DELETE FROM blocked_users WHERE blocker_id=$me AND blocked_id=$otherId");

    jsonResponse('success', 'User unblocked');
}

// ── Get Blocked Users ─────────────────────────────────────────
function getBlockedUsers()
{
    global $conn, $me;

    $r = @$conn->query(
        "SELECT u.id, u.full_name, u.profile_picture, u.current_role, bu.created_at AS blocked_at
         FROM blocked_users bu
         JOIN users u ON u.id = bu.blocked_id
         WHERE bu.blocker_id = $me
         ORDER BY bu.created_at DESC"
    );

    if (!$r) {
        // Table doesn't exist yet
        jsonResponse('success', 'OK', ['blocked_users' => []]);
        return;
    }

    $users = $r->fetch_all(MYSQLI_ASSOC);
    jsonResponse('success', 'OK', ['blocked_users' => $users]);
}

// ── Delete Conversation (hide from my side only) ──────────────
function deleteConversation()
{
    global $conn, $me;

    $convId = (int)($_POST['conversation_id'] ?? 0);
    if (!$convId) jsonResponse('error', 'Missing conversation');

    $stmt = $conn->prepare("SELECT id, user1_id, user2_id FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)");
    $stmt->bind_param('iii', $convId, $me, $me);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$conv) jsonResponse('error', 'Conversation not found');

    $now = date('Y-m-d H:i:s');
    if ((int)$conv['user1_id'] === $me) {
        @$conn->query("UPDATE conversations SET hidden_user1=1, hidden_user1_at='$now' WHERE id=$convId");
    } else {
        @$conn->query("UPDATE conversations SET hidden_user2=1, hidden_user2_at='$now' WHERE id=$convId");
    }

    jsonResponse('success', 'Conversation deleted');
}
