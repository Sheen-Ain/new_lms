<?php
// ============================================================
// STUDENT — LIVE SESSION
// Waiting room → auto-join when teacher starts → JaaS participant
// ============================================================
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/jaas_jwt.php';
require_once __DIR__ . '/../config/config.php';

$sessionId = (int)($_GET['session_id'] ?? 0);
if (!$sessionId) {
  header('Location: ' . BASE_PATH . '/student/index.php');
  exit;
}

// Fetch session + verify enrollment
$s = $conn->prepare("
    SELECT ls.*, b.name AS batch_name, c.title AS course_title,
           u.full_name AS teacher_name, u.profile_picture AS teacher_pic,
           TIMESTAMPDIFF(SECOND, ls.started_at, NOW()) AS elapsed_seconds
    FROM live_sessions ls
    JOIN batches b ON ls.batch_id=b.id
    JOIN courses c ON b.course_id=c.id
    JOIN users u ON ls.started_by=u.id
    WHERE ls.id=?
");
$s->bind_param('i', $sessionId);
$s->execute();
$session = $s->get_result()->fetch_assoc();
$s->close();

if (!$session) {
  header('Location: ' . BASE_PATH . '/student/index.php?error=session_not_found');
  exit;
}

$s = $conn->prepare("SELECT id FROM batch_students WHERE batch_id=? AND student_id=?");
$s->bind_param('ii', $session['batch_id'], $currentUser['id']);
$s->execute();
if (!$s->get_result()->num_rows) {
  header('Location: ' . BASE_PATH . '/student/index.php?error=not_enrolled');
  exit;
}
$s->close();

if ($session['status'] === 'ended') {
  header('Location: ' . BASE_PATH . '/student/index.php?msg=session_ended');
  exit;
}

// ── Generate JaaS JWT (participant) ──────────────────────────
$roomFull   = $session['room_name'];
$roomSuffix = strpos($roomFull, '/') !== false ? substr($roomFull, strrpos($roomFull, '/') + 1) : $roomFull;
$jwt = generateJaaSJWT($roomSuffix, [
  'id'    => $currentUser['id'],
  'name'  => $currentUser['full_name'],
  'email' => $currentUser['email'] ?? '',
], false); // false = participant (not moderator)

$appId     = defined('JAAS_APP_ID') ? JAAS_APP_ID : '';
$isWaiting = ($session['status'] === 'waiting');
$elapsed   = max(0, (int)($session['elapsed_seconds'] ?? 0));
$pageTitle = 'Live: ' . $session['title'];

// Teacher initials for avatar fallback
$tParts  = explode(' ', $session['teacher_name']);
$tInits  = strtoupper(substr($tParts[0], 0, 1) . (isset($tParts[1]) ? substr($tParts[1], 0, 1) : ''));

include __DIR__ . '/../includes/header.php';
?>
<style>
  body {
    overflow: hidden;
  }

  .student-wrap {
    display: flex;
    flex-direction: column;
    height: 100svh;
    background: #070714;
    font-family: 'DM Sans', sans-serif;
  }

  /* ── Bar ────────────────────────────────────────────────────── */
  .sb {
    height: 54px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 0 18px;
    background: linear-gradient(90deg, #0a0921, #100e27);
    border-bottom: 1px solid rgba(255, 255, 255, .06);
    z-index: 50;
  }

  .sb-logo {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
  }

  .sb-brand {
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: .88rem;
    color: #fff;
    letter-spacing: -.03em
  }

  .sb-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    flex-shrink: 0
  }

  .sb-badge.waiting {
    background: rgba(245, 158, 11, .12);
    border: 1px solid rgba(245, 158, 11, .28)
  }

  .sb-badge.live {
    background: rgba(239, 68, 68, .12);
    border: 1px solid rgba(239, 68, 68, .28)
  }

  .sb-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    animation: dp 1.4s ease infinite
  }

  .sb-badge.waiting .sb-dot {
    background: #f59e0b
  }

  .sb-badge.live .sb-dot {
    background: #ef4444
  }

  @keyframes dp {

    0%,
    100% {
      opacity: 1
    }

    50% {
      opacity: .4
    }
  }

  .sb-badge-lbl {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em
  }

  .sb-badge.waiting .sb-badge-lbl {
    color: #f59e0b
  }

  .sb-badge.live .sb-badge-lbl {
    color: #ef4444
  }

  .sb-info {
    display: flex;
    flex-direction: column;
    min-width: 0
  }

  .sb-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: .83rem;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px
  }

  .sb-meta {
    font-size: .65rem;
    color: rgba(255, 255, 255, .38)
  }

  .sb-space {
    flex: 1
  }

  .sb-timer {
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: .82rem;
    font-weight: 700;
    color: #10b981;
    background: rgba(16, 185, 129, .1);
    border: 1px solid rgba(16, 185, 129, .2);
    padding: 3px 11px;
    border-radius: 999px;
    min-width: 60px;
    text-align: center;
    display: none;
  }

  .sb-leave {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: rgba(239, 68, 68, .1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, .22);
    border-radius: 8px;
    font-weight: 700;
    font-size: .75rem;
    cursor: pointer;
    transition: background .15s;
    font-family: 'DM Sans', sans-serif;
    flex-shrink: 0;
  }

  .sb-leave:hover {
    background: rgba(239, 68, 68, .18)
  }

  /* ── Body ───────────────────────────────────────────────────── */
  .s-body {
    flex: 1;
    overflow: hidden;
    position: relative
  }

  /* ── WAITING ROOM ───────────────────────────────────────────── */
  #s-wr {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(ellipse at 50% 30%, #14112e 0%, #070714 70%);
    padding: 20px;
  }

  .wr-card {
    background: rgba(255, 255, 255, .03);
    border: 1px solid rgba(99, 102, 241, .12);
    border-radius: 26px;
    padding: 40px 44px;
    max-width: 500px;
    width: 100%;
    text-align: center;
    box-shadow: 0 20px 70px rgba(0, 0, 0, .6);
    animation: fadeUp .45s ease;
  }

  @keyframes fadeUp {
    from {
      opacity: 0;
      transform: translateY(18px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  /* Teacher avatar with rotating ring */
  .t-av-wrap {
    position: relative;
    width: 180px;
    height: 180px;
    margin: 0 auto 22px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .t-rings {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
  }

  .t-ring {
    position: absolute;
    border-radius: 50%;
    border: 1.5px solid rgba(99, 102, 241, .2);
  }

  .t-ring:nth-child(1) {
    width: 130px;
    height: 130px;
    animation: pr 2s .0s ease-out infinite
  }

  .t-ring:nth-child(2) {
    width: 160px;
    height: 160px;
    animation: pr 2s .5s ease-out infinite
  }

  .t-ring:nth-child(3) {
    width: 188px;
    height: 188px;
    animation: pr 2s 1s ease-out infinite
  }

  @keyframes pr {
    0% {
      transform: scale(.7);
      opacity: .6
    }

    100% {
      transform: scale(1.05);
      opacity: 0
    }
  }

  .t-av {
    position: relative;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Poppins', sans-serif;
    font-size: 1.8rem;
    font-weight: 900;
    color: #fff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .4);
    z-index: 1;
  }

  .t-av img {
    width: 100%;
    height: 100%;
    object-fit: cover
  }

  .wrc-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.2rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 7px
  }

  .wrc-sub {
    font-size: .83rem;
    color: rgba(255, 255, 255, .42);
    line-height: 1.8;
    margin-bottom: 24px
  }

  .wrc-teacher {
    color: #a5b4fc;
    font-weight: 700
  }

  /* Animated dots */
  .wr-dots {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-bottom: 22px
  }

  .wr-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6366f1;
    animation: db 1.2s ease infinite
  }

  .wr-dot:nth-child(1) {
    animation-delay: .0s
  }

  .wr-dot:nth-child(2) {
    animation-delay: .18s
  }

  .wr-dot:nth-child(3) {
    animation-delay: .36s
  }

  @keyframes db {

    0%,
    80%,
    100% {
      transform: scale(1);
      opacity: .4
    }

    40% {
      transform: scale(1.45);
      opacity: 1
    }
  }

  .wr-pills {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 6px
  }

  .wr-pill {
    padding: 4px 13px;
    border-radius: 999px;
    font-size: .73rem;
    font-weight: 600;
    background: rgba(99, 102, 241, .1);
    border: 1px solid rgba(99, 102, 241, .2);
    color: rgba(255, 255, 255, .6);
  }

  /* ── JITSI ──────────────────────────────────────────────────── */
  #s-jitsi {
    position: absolute;
    inset: 0;
    display: none
  }

  #jitsi-container {
    width: 100%;
    height: 100%
  }

  /* ── ENDED OVERLAY ──────────────────────────────────────────── */
  .ended-ov {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: rgba(0, 0, 0, .9);
    backdrop-filter: blur(14px);
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    padding: 20px;
  }

  .ended-ov.show {
    display: flex
  }

  .ended-box {
    background: #12102c;
    border: 1px solid rgba(99, 102, 241, .2);
    border-radius: 22px;
    padding: 38px 42px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 24px 80px rgba(0, 0, 0, .7);
    animation: pIn .28s cubic-bezier(.34, 1.56, .64, 1);
  }

  @keyframes pIn {
    from {
      opacity: 0;
      transform: scale(.88) translateY(14px)
    }

    to {
      opacity: 1;
      transform: scale(1) translateY(0)
    }
  }

  .ended-em {
    font-size: 3rem;
    margin-bottom: 14px
  }

  .ended-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.15rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 9px
  }

  .ended-sub {
    font-size: .82rem;
    color: rgba(255, 255, 255, .45);
    line-height: 1.7;
    margin-bottom: 20px
  }

  .ended-stats {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 20px
  }

  .ended-stat {
    background: rgba(255, 255, 255, .04);
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 11px;
    padding: 12px 18px
  }

  .ended-stat-val {
    font-size: 1.2rem;
    font-weight: 900;
    color: #6366f1
  }

  .ended-stat-lbl {
    font-size: .65rem;
    color: rgba(255, 255, 255, .35);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-top: 2px
  }

  @media(max-width:600px) {
    .wr-card {
      padding: 28px 20px
    }

    .sb-brand,
    .sb-meta {
      display: none
    }
  }
</style>

<div class="student-wrap">

  <!-- BAR -->
  <div class="sb">
    <div class="sb-logo">🎓</div>
    <span class="sb-brand">EduFlow</span>

    <div class="sb-badge <?= $isWaiting ? 'waiting' : 'live' ?>" id="s-badge">
      <div class="sb-dot"></div>
      <span class="sb-badge-lbl"><?= $isWaiting ? 'Waiting Room' : 'Live' ?></span>
    </div>

    <div class="sb-info">
      <div class="sb-title"><?= e($session['title']) ?></div>
      <div class="sb-meta"><?= e($session['course_title']) ?> › <?= e($session['batch_name']) ?></div>
    </div>

    <div class="sb-space"></div>
    <div class="sb-timer" id="s-timer">00:00</div>
    <button class="sb-leave" onclick="leaveSession()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
        <polyline points="16 17 21 12 16 7" />
        <line x1="21" y1="12" x2="9" y2="12" />
      </svg>
      Leave
    </button>
  </div>

  <!-- BODY -->
  <div class="s-body">

    <!-- WAITING ROOM -->
    <div id="s-wr" <?= !$isWaiting ? 'style="display:none"' : '' ?>>
      <div class="wr-card">
        <!-- Teacher avatar + pulse rings -->
        <div class="t-av-wrap">
          <div class="t-rings">
            <div class="t-ring"></div>
            <div class="t-ring"></div>
            <div class="t-ring"></div>
          </div>
          <div class="t-av">
            <?php if (!empty($session['teacher_pic'])): ?>
              <img src="<?= BASE_PATH ?>/uploads/profiles/<?= e($session['teacher_pic']) ?>" alt=""
                onerror="this.parentElement.textContent='<?= $tInits ?>'">
            <?php else: ?>
              <?= $tInits ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="wrc-title">Class is almost ready 🎓</div>
        <div class="wrc-sub">
          <span class="wrc-teacher"><?= e($session['teacher_name']) ?></span> is setting up.<br>
          You'll join automatically the moment class starts — don't close this tab!
        </div>

        <div class="wr-dots">
          <div class="wr-dot"></div>
          <div class="wr-dot"></div>
          <div class="wr-dot"></div>
        </div>

        <div class="wr-pills">
          <div class="wr-pill">📚 <?= e($session['course_title']) ?></div>
          <div class="wr-pill">👥 <?= e($session['batch_name']) ?></div>
          <div class="wr-pill" id="s-count-pill">👤 connecting…</div>
        </div>
      </div>
    </div>

    <!-- JITSI -->
    <div id="s-jitsi" <?= !$isWaiting ? 'style="display:block"' : '' ?>>
      <div id="jitsi-container"></div>
    </div>

  </div>
</div>

<!-- ENDED OVERLAY -->
<div class="ended-ov" id="ended-ov">
  <div class="ended-box">
    <div class="ended-em">🎓</div>
    <div class="ended-title">Class Has Ended</div>
    <div class="ended-sub">
      <strong><?= e($session['teacher_name']) ?></strong> has ended the session.<br>
      Great work today — see you next class!
    </div>
    <div class="ended-stats">
      <div class="ended-stat">
        <div class="ended-stat-val" id="ended-dur">—</div>
        <div class="ended-stat-lbl">Duration</div>
      </div>
      <div class="ended-stat">
        <div class="ended-stat-val" id="ended-cnt">—</div>
        <div class="ended-stat-lbl">Attended</div>
      </div>
    </div>
    <a href="<?= BASE_PATH ?>/student/index.php" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;">
      Back to Dashboard
    </a>
  </div>
</div>

<script src="https://8x8.vc/<?= urlencode($appId) ?>/external_api.js"
  onerror="this.onerror=null;var s=document.createElement('script');s.src='https://meet.jit.si/external_api.js';document.head.appendChild(s)"></script>

<script>
  'use strict';
  const SESSION_ID = <?= (int)$sessionId ?>;
  const ROOM_FULL = <?= json_encode($roomFull) ?>;
  const DISPLAY_NAME = <?= json_encode($currentUser['full_name']) ?>;
  const JAAS_JWT = <?= json_encode($jwt) ?>;
  const JAAS_DOMAIN = '8x8.vc';
  let IS_WAITING = <?= $isWaiting ? 'true' : 'false' ?>;
  let sessionActive = true;

  let elapsed = <?= $elapsed ?>;
  let jitsiApi = null,
    timerInt = null,
    safetyInt = null; // 60s fallback poll — Pusher is primary

  function tick() {
    elapsed++;
    const h = Math.floor(elapsed / 3600),
      m = Math.floor((elapsed % 3600) / 60),
      s = elapsed % 60,
      p = n => String(n).padStart(2, '0');
    document.getElementById('s-timer').textContent = h > 0 ? `${p(h)}:${p(m)}:${p(s)}` : `${p(m)}:${p(s)}`;
  }

  // ── Knock (register in waiting room — called once on load) ────
  async function knock() {
    try {
      const fd = new FormData();
      fd.append('action', 'knock');
      fd.append('session_id', SESSION_ID);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
        method: 'POST',
        body: fd
      });
    } catch (e) {}
  }

  // ── Fetch session status once (used only for ended-overlay stats
  //    and as a 60s safety fallback in case Pusher drops) ────────
  async function checkStatus() {
    try {
      const fd = new FormData();
      fd.append('action', 'get_session_status');
      fd.append('session_id', SESSION_ID);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
        method: 'POST',
        body: fd
      });
      const d = await r.json();
      if (d.status !== 'success') return;
      const data = d.data;

      if (data.status === 'active' && IS_WAITING) {
        // Pusher missed the open event — fallback catches it
        IS_WAITING = false;
        clearInterval(safetyInt);
        await enterClass();
      } else if (data.status === 'ended' && sessionActive) {
        sessionActive = false;
        clearInterval(safetyInt);
        showEnded(data);
      }
    } catch (e) {}
  }

  // ── Enter class ───────────────────────────────────────────────
  async function enterClass() {
    // Log join
    try {
      const fd = new FormData();
      fd.append('action', 'join');
      fd.append('session_id', SESSION_ID);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
        method: 'POST',
        body: fd
      });
    } catch (e) {}

    document.getElementById('s-wr').style.display = 'none';
    document.getElementById('s-jitsi').style.display = 'block';
    document.getElementById('s-timer').style.display = 'block';

    const badge = document.getElementById('s-badge');
    badge.className = 'sb-badge live';
    badge.innerHTML = '<div class="sb-dot"></div><span class="sb-badge-lbl">Live</span>';

    initJitsi();
    timerInt = setInterval(tick, 1000);
    // Safety poll every 60s while live — Pusher handles instant events
    safetyInt = setInterval(checkStatus, 60000);
  }

  // ── Init Jitsi (participant) ──────────────────────────────────
  function initJitsi() {
    if (jitsiApi) return;

    const options = {
      roomName: ROOM_FULL,
      parentNode: document.getElementById('jitsi-container'),
      width: '100%',
      height: '100%',
      userInfo: {
        displayName: DISPLAY_NAME
      },
      configOverwrite: {
        startWithAudioMuted: true,
        startWithVideoMuted: true,
        prejoinPageEnabled: false,
        disableDeepLinking: true,
        enableWelcomePage: false,
        toolbarButtons: ['microphone', 'camera', 'chat', 'raisehand', 'tileview', 'hangup'],
      },
      interfaceConfigOverwrite: {
        SHOW_JITSI_WATERMARK: false,
        SHOW_BRAND_WATERMARK: false,
        APP_NAME: 'EduFlow Live',
        PROVIDER_NAME: 'EduFlow',
        HIDE_INVITE_MORE_HEADER: true,
        DISPLAY_WELCOME_FOOTER: false,
      },
    };

    if (JAAS_JWT) options.jwt = JAAS_JWT;

    jitsiApi = new JitsiMeetExternalAPI(JAAS_DOMAIN, options);
    jitsiApi.addEventListener('readyToClose', () => leaveSession());
  }

  function showEnded(data) {
    clearInterval(timerInt);
    clearInterval(safetyInt);
    if (jitsiApi) {
      try {
        jitsiApi.executeCommand('hangup');
      } catch (e) {}
    }
    const sec = parseInt(data.duration_seconds || 0),
      h = Math.floor(sec / 3600),
      m = Math.floor((sec % 3600) / 60),
      p = n => String(n).padStart(2, '0');
    document.getElementById('ended-dur').textContent = h > 0 ? `${p(h)}h ${p(m)}m` : `${p(m)}m`;
    document.getElementById('ended-cnt').textContent = data.total_joined || '—';
    document.getElementById('ended-ov').classList.add('show');
  }

  function leaveSession() {
    const fd = new FormData();
    fd.append('action', 'leave');
    fd.append('session_id', SESSION_ID);
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    navigator.sendBeacon(window.LMS_BASE + '/ajax/live.ajax.php', fd);
    clearInterval(timerInt);
    clearInterval(safetyInt);
    if (jitsiApi) {
      try {
        jitsiApi.dispose();
      } catch (e) {}
    }
    window.location.href = window.LMS_BASE + '/student/index.php';
  }

  // ── Pusher events (dispatched by chat-widget.php) ─────────────
  // These fire instantly — no polling needed
  document.addEventListener('lms:live-opened', e => {
    if (e.detail && parseInt(e.detail.session_id) === SESSION_ID && IS_WAITING) {
      IS_WAITING = false;
      clearInterval(safetyInt);
      enterClass();
    }
  });
  document.addEventListener('lms:live-ended', e => {
    if (e.detail && parseInt(e.detail.session_id) === SESSION_ID && sessionActive) {
      sessionActive = false;
      clearInterval(safetyInt);
      // Fetch once to get duration/attendance stats for the ended overlay
      checkStatus();
    }
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (IS_WAITING) {
      // Knock once to register in the waiting room — Pusher will fire
      // lms:live-opened the moment teacher starts, no polling needed.
      await knock();
      // 60s safety fallback in case Pusher connection drops
      safetyInt = setInterval(checkStatus, 60000);
    } else {
      await enterClass();
    }
    window.addEventListener('beforeunload', () => {
      const fd = new FormData();
      fd.append('action', 'leave');
      fd.append('session_id', SESSION_ID);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      navigator.sendBeacon(window.LMS_BASE + '/ajax/live.ajax.php', fd);
    });
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>