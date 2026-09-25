<?php
// ============================================================
// TEACHER / ADMIN — LIVE SESSIONS
// No ?session_id  → list page
// With ?session_id → waiting room + Jitsi host (JaaS moderator)
// ============================================================
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
if ($userRole !== 'teacher' && $userRole !== 'admin') {
  header('Location: ' . BASE_PATH . '/' . $userRole . '/');
  exit;
}
require_once __DIR__ . '/../includes/jaas_jwt.php';

$sessionId = (int)($_GET['session_id'] ?? 0);

// ═══════════════════════════════════════════════════════════════
// HOST PAGE — session_id provided
// ═══════════════════════════════════════════════════════════════
if ($sessionId) {
  if ($userRole === 'teacher') {
    $s = $conn->prepare("SELECT ls.*, b.name AS batch_name, c.title AS course_title, TIMESTAMPDIFF(SECOND, ls.started_at, NOW()) AS elapsed_seconds FROM live_sessions ls JOIN batches b ON ls.batch_id=b.id JOIN courses c ON b.course_id=c.id WHERE ls.id=? AND ls.started_by=?");
    $s->bind_param('ii', $sessionId, $currentUser['id']);
  } else {
    $s = $conn->prepare("SELECT ls.*, b.name AS batch_name, c.title AS course_title, TIMESTAMPDIFF(SECOND, ls.started_at, NOW()) AS elapsed_seconds FROM live_sessions ls JOIN batches b ON ls.batch_id=b.id JOIN courses c ON b.course_id=c.id WHERE ls.id=?");
    $s->bind_param('i', $sessionId);
  }
  $s->execute();
  $session = $s->get_result()->fetch_assoc();
  $s->close();
  if (!$session) {
    header('Location: ' . BASE_PATH . '/' . $userRole . '/live.php?error=not_found');
    exit;
  }
  if ($session['status'] === 'ended') {
    header('Location: ' . BASE_PATH . '/' . $userRole . '/live.php?msg=ended');
    exit;
  }

  $roomFull   = $session['room_name'];
  $roomSuffix = strpos($roomFull, '/') !== false ? substr($roomFull, strrpos($roomFull, '/') + 1) : $roomFull;
  $jwt        = generateJaaSJWT($roomSuffix, ['id' => $currentUser['id'], 'name' => $currentUser['full_name'], 'email' => $currentUser['email'] ?? ''], true);
  $appId      = defined('JAAS_APP_ID') ? JAAS_APP_ID : '';
  $isWaiting  = ($session['status'] === 'waiting');
  $elapsed    = max(0, (int)($session['elapsed_seconds'] ?? 0));
  $hostLabel  = $userRole === 'admin' ? 'Admin' : 'Teacher';
  $pageTitle  = 'Live: ' . $session['title'];
  include __DIR__ . '/../includes/header.php';
?>
  <style>
    body {
      overflow: hidden
    }

    .hw {
      display: flex;
      flex-direction: column;
      height: 100svh;
      background: #07071a;
      font-family: "DM Sans", sans-serif
    }

    .hbar {
      height: 56px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 0 20px;
      background: linear-gradient(90deg, #0c0a22, #111030);
      border-bottom: 1px solid rgba(99, 102, 241, .15);
      z-index: 50
    }

    .hbar-logo {
      width: 32px;
      height: 32px;
      border-radius: 9px;
      flex-shrink: 0;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem
    }

    .hbar-brand {
      font-family: "Poppins", sans-serif;
      font-weight: 800;
      font-size: .9rem;
      color: #fff;
      letter-spacing: -.03em;
      flex-shrink: 0
    }

    .hbdg {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 3px 11px;
      border-radius: 999px;
      flex-shrink: 0
    }

    .hbdg.w {
      background: rgba(245, 158, 11, .12);
      border: 1px solid rgba(245, 158, 11, .28)
    }

    .hbdg.live {
      background: rgba(239, 68, 68, .12);
      border: 1px solid rgba(239, 68, 68, .28)
    }

    .hbdg-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      animation: dp 1.4s ease infinite
    }

    .hbdg.w .hbdg-dot {
      background: #f59e0b
    }

    .hbdg.live .hbdg-dot {
      background: #ef4444
    }

    @keyframes dp {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .45
      }
    }

    .hbdg-lbl {
      font-size: .67rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em
    }

    .hbdg.w .hbdg-lbl {
      color: #f59e0b
    }

    .hbdg.live .hbdg-lbl {
      color: #ef4444
    }

    .hinfo {
      display: flex;
      flex-direction: column;
      min-width: 0
    }

    .hinfo-ttl {
      font-family: "Poppins", sans-serif;
      font-weight: 700;
      font-size: .86rem;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 260px
    }

    .hinfo-sub {
      font-size: .66rem;
      color: rgba(255, 255, 255, .4);
      white-space: nowrap
    }

    .hsp {
      flex: 1
    }

    .hstats {
      display: flex;
      align-items: center;
      gap: 14px;
      font-size: .77rem;
      color: rgba(255, 255, 255, .55)
    }

    .hstats strong {
      color: #fff;
      font-weight: 700
    }

    .htmr {
      font-family: "JetBrains Mono", "Courier New", monospace;
      font-size: .85rem;
      font-weight: 700;
      color: #10b981;
      background: rgba(16, 185, 129, .1);
      border: 1px solid rgba(16, 185, 129, .2);
      padding: 4px 12px;
      border-radius: 999px;
      min-width: 62px;
      text-align: center
    }

    .hend-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 7px 15px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #fff;
      border: none;
      border-radius: 9px;
      font-weight: 700;
      font-size: .78rem;
      cursor: pointer;
      font-family: "DM Sans", sans-serif;
      flex-shrink: 0;
      box-shadow: 0 3px 14px rgba(239, 68, 68, .35);
      transition: opacity .15s, transform .1s
    }

    .hend-btn:hover {
      opacity: .85;
      transform: translateY(-1px)
    }

    .hend-btn:active {
      transform: scale(.96)
    }

    .hbody {
      flex: 1;
      overflow: hidden;
      position: relative
    }

    #wrp {
      position: absolute;
      inset: 0;
      display: flex
    }

    .wrl {
      width: 310px;
      flex-shrink: 0;
      background: #0d0b22;
      border-right: 1px solid rgba(255, 255, 255, .07);
      display: flex;
      flex-direction: column;
      gap: 16px;
      padding: 22px 18px;
      overflow-y: auto
    }

    .wrh {
      font-family: "Poppins", sans-serif;
      font-size: .96rem;
      font-weight: 800;
      color: #fff
    }

    .wrsb {
      font-size: .75rem;
      color: rgba(255, 255, 255, .4);
      margin-top: 2px
    }

    .wrcts {
      display: flex;
      gap: 10px
    }

    .wrct {
      flex: 1;
      background: rgba(99, 102, 241, .08);
      border: 1px solid rgba(99, 102, 241, .15);
      border-radius: 12px;
      padding: 13px
    }

    .wrct-val {
      font-family: "Poppins", sans-serif;
      font-size: 1.45rem;
      font-weight: 900;
      color: #6366f1
    }

    .wrct-lbl {
      font-size: .62rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: rgba(255, 255, 255, .35);
      margin-top: 2px
    }

    .btnst {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: "Poppins", sans-serif;
      font-size: .9rem;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 6px 24px rgba(99, 102, 241, .4);
      transition: transform .15s, box-shadow .15s
    }

    .btnst:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(99, 102, 241, .55)
    }

    .btnst:active {
      transform: scale(.97)
    }

    .btnst:disabled {
      opacity: .55;
      cursor: not-allowed;
      transform: none;
      box-shadow: none
    }

    .wr-ll {
      font-size: .65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: rgba(255, 255, 255, .35)
    }

    .wr-list {
      display: flex;
      flex-direction: column;
      gap: 7px;
      margin-top: 7px
    }

    .wr-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      background: rgba(255, 255, 255, .04);
      border: 1px solid rgba(255, 255, 255, .07);
      border-radius: 10px;
      animation: sli .2s ease
    }

    @keyframes sli {
      from {
        opacity: 0;
        transform: translateX(-10px)
      }

      to {
        opacity: 1;
        transform: translateX(0)
      }
    }

    .wrav {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .75rem;
      font-weight: 700;
      color: #fff
    }

    .wrname {
      font-size: .82rem;
      font-weight: 600;
      color: rgba(255, 255, 255, .88)
    }

    .wrjoin {
      font-size: .67rem;
      color: rgba(255, 255, 255, .35);
      margin-top: 1px
    }

    .wr-empty {
      text-align: center;
      padding: 22px 0;
      color: rgba(255, 255, 255, .3);
      font-size: .78rem;
      line-height: 1.7
    }

    .wr-empty-ico {
      font-size: 1.8rem;
      margin-bottom: 7px
    }

    .wrr {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 14px;
      text-align: center;
      padding: 32px;
      background: radial-gradient(ellipse at 50% 40%, #120f2e 0%, #07071a 100%)
    }

    .wrr-ico {
      width: 76px;
      height: 76px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      box-shadow: 0 0 0 14px rgba(99, 102, 241, .1), 0 0 0 28px rgba(99, 102, 241, .05);
      animation: glow 2.5s ease infinite
    }

    @keyframes glow {

      0%,
      100% {
        box-shadow: 0 0 0 14px rgba(99, 102, 241, .1), 0 0 0 28px rgba(99, 102, 241, .05)
      }

      50% {
        box-shadow: 0 0 0 20px rgba(99, 102, 241, .15), 0 0 0 40px rgba(99, 102, 241, .07)
      }
    }

    .wrr-ttl {
      font-family: "Poppins", sans-serif;
      font-size: 1.2rem;
      font-weight: 800;
      color: #fff
    }

    .wrr-sub {
      font-size: .82rem;
      color: rgba(255, 255, 255, .42);
      max-width: 340px;
      line-height: 1.8
    }

    #jip {
      position: absolute;
      inset: 0;
      display: none
    }

    #jitsi-container {
      width: 100%;
      height: 100%
    }

    .eov {
      position: fixed;
      inset: 0;
      z-index: 9000;
      background: rgba(0, 0, 0, .72);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity .2s
    }

    .eov.show {
      opacity: 1;
      pointer-events: all
    }

    .eov-box {
      background: #12102c;
      border: 1px solid rgba(99, 102, 241, .2);
      border-radius: 20px;
      padding: 34px 36px;
      max-width: 400px;
      width: 92%;
      text-align: center;
      box-shadow: 0 24px 80px rgba(0, 0, 0, .7);
      animation: pIn .22s cubic-bezier(.34, 1.56, .64, 1)
    }

    @keyframes pIn {
      from {
        opacity: 0;
        transform: scale(.88) translateY(8px)
      }

      to {
        opacity: 1;
        transform: scale(1) translateY(0)
      }
    }

    .eov-ico {
      font-size: 1.8rem;
      margin-bottom: 14px
    }

    .eov-ttl {
      font-family: "Poppins", sans-serif;
      font-size: 1.05rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 9px
    }

    .eov-sub {
      font-size: .83rem;
      color: rgba(255, 255, 255, .5);
      line-height: 1.7;
      margin-bottom: 22px
    }

    .eov-btns {
      display: flex;
      gap: 10px;
      justify-content: center
    }

    @media(max-width:768px) {
      .wrl {
        width: 260px;
        padding: 16px 12px
      }

      .hbar-brand,
      .hinfo-sub {
        display: none
      }
    }

    @media(max-width:600px) {
      #wrp {
        flex-direction: column
      }

      .wrl {
        width: 100%;
        height: 52%;
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, .07)
      }

      .wrr {
        height: 48%;
        padding: 16px
      }
    }
  </style>
  <div class="hw">
    <div class="hbar">
      <div class="hbar-logo">🎓</div>
      <span class="hbar-brand">EduFlow</span>
      <div class="hbdg <?= $isWaiting ? 'w' : 'live' ?>" id="hbdg">
        <div class="hbdg-dot"></div>
        <span class="hbdg-lbl"><?= $isWaiting ? 'Waiting Room' : 'Live' ?></span>
      </div>
      <div class="hinfo">
        <div class="hinfo-ttl"><?= e($session['title']) ?></div>
        <div class="hinfo-sub"><?= e($session['course_title']) ?> › <?= e($session['batch_name']) ?> · <?= $hostLabel ?></div>
      </div>
      <div class="hsp"></div>
      <div class="hstats">
        <span id="hcnt" style="display:none"><strong id="hcntv">—</strong> in class</span>
        <div class="htmr" id="htmr">00:00</div>
      </div>
      <button class="hend-btn" onclick="confirmEnd()">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor">
          <rect x="3" y="3" width="18" height="18" rx="3" />
        </svg>
        End Session
      </button>
    </div>
    <div class="hbody">
      <div id="wrp" <?= !$isWaiting ? 'style="display:none"' : '' ?>>
        <div class="wrl">
          <div>
            <div class="wrh">Waiting Room 👋</div>
            <div class="wrsb">Students are notified. Start whenever you're ready.</div>
          </div>
          <div class="wrcts">
            <div class="wrct">
              <div class="wrct-val" id="wrw">0</div>
              <div class="wrct-lbl">Waiting</div>
            </div>
            <div class="wrct">
              <div class="wrct-val" id="wrt">—</div>
              <div class="wrct-lbl">Enrolled</div>
            </div>
          </div>
          <button class="btnst" id="btnst" onclick="startClass()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
              <polygon points="5,3 19,12 5,21" />
            </svg>
            🚀 Start Class Now
          </button>
          <div>
            <div class="wr-ll">Students waiting</div>
            <div class="wr-list" id="wrlist">
              <div class="wr-empty">
                <div class="wr-empty-ico">👥</div>No students yet — they'll appear here as they arrive.
              </div>
            </div>
          </div>
        </div>
        <div class="wrr">
          <div class="wrr-ico">🎓</div>
          <div class="wrr-ttl"><?= e($session['title']) ?></div>
          <div class="wrr-sub">Your session is live and students are being notified.<br>See who's waiting on the left, then click <strong style="color:#a5b4fc;">Start Class Now</strong> when ready.</div>
        </div>
      </div>
      <div id="jip" <?= !$isWaiting ? 'style="display:block"' : '' ?>>
        <div id="jitsi-container"></div>
      </div>
    </div>
  </div>
  <div class="eov" id="eov">
    <div class="eov-box">
      <div class="eov-ico">🛑</div>
      <div class="eov-ttl">End Live Session?</div>
      <div class="eov-sub">This disconnects all students and ends the class permanently.</div>
      <div class="eov-btns">
        <button class="btn btn-secondary" onclick="document.getElementById('eov').classList.remove('show')">Keep Going</button>
        <button class="btn btn-danger" id="eovbtn" onclick="doEnd()">End Now</button>
      </div>
    </div>
  </div>
  <script src="https://8x8.vc/<?= urlencode($appId) ?>/external_api.js" onerror="this.onerror=null;var s=document.createElement('script');s.src='https://meet.jit.si/external_api.js';document.head.appendChild(s)"></script>
  <script>
    'use strict';
    const SID = <?= (int)$sessionId ?>,
      RFULL = <?= json_encode($roomFull) ?>,
      DNAME = <?= json_encode($currentUser['full_name'] . ' (' . $hostLabel . ')') ?>,
      JWT = <?= json_encode($jwt) ?>;
    let ISW = <?= $isWaiting ? 'true' : 'false' ?>,
      el = <?= $elapsed ?>,
      japi = null,
      tint = null,
      pint = null,
      wint = null;
    const COLS = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];

    function esc(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    }

    function tick() {
      el++;
      const h = Math.floor(el / 3600),
        m = Math.floor((el % 3600) / 60),
        s = el % 60,
        p = n => String(n).padStart(2, '0');
      document.getElementById('htmr').textContent = h > 0 ? `${p(h)}:${p(m)}:${p(s)}` : `${p(m)}:${p(s)}`
    }
    async function pollW() {
      try {
        const fd = new FormData();
        fd.append('action', 'get_waiting_list');
        fd.append('session_id', SID);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        const d = await (await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
          method: 'POST',
          body: fd
        })).json();
        if (d.status !== 'success') return;
        document.getElementById('wrw').textContent = d.data.waiting_count || 0;
        document.getElementById('wrt').textContent = d.data.total_students || '—';
        const l = document.getElementById('wrlist');
        if (!d.data.waiting || !d.data.waiting.length) {
          l.innerHTML = '<div class="wr-empty"><div class="wr-empty-ico">👥</div>No students yet.</div>';
          return
        }
        l.innerHTML = d.data.waiting.map(u => {
          const i = (u.full_name || 'U').trim().split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
          const c = COLS[Math.abs((u.full_name || '').split('').reduce((h, c) => h * 31 + c.charCodeAt(0), 0)) % COLS.length];
          return `<div class="wr-item"><div class="wrav" style="background:${c}">${i}</div><div><div class="wrname">${esc(u.full_name)}</div><div class="wrjoin">Waiting…</div></div></div>`
        }).join('')
      } catch (e) {}
    }
    async function startClass() {
      const b = document.getElementById('btnst');
      b.disabled = true;
      b.textContent = 'Opening…';
      try {
        const fd = new FormData();
        fd.append('action', 'open');
        fd.append('session_id', SID);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        const d = await (await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
          method: 'POST',
          body: fd
        })).json();
        if (d.status !== 'success') {
          b.disabled = false;
          b.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg> 🚀 Start Class Now';
          if (window.Toast) Toast.error(d.message || 'Failed');
          return
        }
        clearInterval(wint);
        go();
      } catch (e) {
        b.disabled = false;
        b.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg> 🚀 Start Class Now'
      }
    }

    function go() {
      ISW = false;
      document.getElementById('wrp').style.display = 'none';
      document.getElementById('jip').style.display = 'block';
      const bd = document.getElementById('hbdg');
      bd.className = 'hbdg live';
      bd.innerHTML = '<div class="hbdg-dot"></div><span class="hbdg-lbl">Live</span>';
      document.getElementById('hcnt').style.display = 'flex';
      initJ();
      tint = setInterval(tick, 1000);
      pint = setInterval(sc, 20000);
      sc();
    }

    function initJ() {
      if (japi) return;
      const o = {
        roomName: RFULL,
        parentNode: document.getElementById('jitsi-container'),
        width: '100%',
        height: '100%',
        userInfo: {
          displayName: DNAME
        },
        configOverwrite: {
          startWithAudioMuted: false,
          startWithVideoMuted: false,
          prejoinPageEnabled: false,
          disableDeepLinking: true,
          enableWelcomePage: false,
          toolbarButtons: ['microphone', 'camera', 'desktop', 'chat', 'raisehand', 'tileview', 'participants-pane', 'settings', 'hangup']
        },
        interfaceConfigOverwrite: {
          SHOW_JITSI_WATERMARK: false,
          SHOW_BRAND_WATERMARK: false,
          APP_NAME: 'EduFlow Live',
          PROVIDER_NAME: 'EduFlow',
          HIDE_INVITE_MORE_HEADER: true,
          DISPLAY_WELCOME_FOOTER: false
        }
      };
      if (JWT) o.jwt = JWT;
      japi = new JitsiMeetExternalAPI('8x8.vc', o);
      japi.addEventListener('readyToClose', () => doEnd());
      japi.addEventListener('participantJoined', sc);
      japi.addEventListener('participantLeft', sc);
    }
    async function sc() {
      try {
        const fd = new FormData();
        fd.append('action', 'get_participants');
        fd.append('session_id', SID);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        const d = await (await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
          method: 'POST',
          body: fd
        })).json();
        if (d.status === 'success') document.getElementById('hcntv').textContent = d.data.live_count || 0
      } catch (e) {}
    }

    function confirmEnd() {
      document.getElementById('eov').classList.add('show')
    }
    async function doEnd() {
      const b = document.getElementById('eovbtn');
      b.disabled = true;
      b.textContent = 'Ending…';
      clearInterval(tint);
      clearInterval(pint);
      clearInterval(wint);
      try {
        const fd = new FormData();
        fd.append('action', 'end');
        fd.append('session_id', SID);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
          method: 'POST',
          body: fd
        })
      } catch (e) {}
      if (japi) {
        try {
          japi.dispose()
        } catch (e) {}
      }
      window.location.href = window.LMS_BASE + '/<?= $userRole ?>/live.php';
    }
    document.addEventListener('DOMContentLoaded', () => {
      if (ISW) {
        // Initial load of waiting list
        pollW();
        // Pusher fires 'waiting-update' whenever a student knocks —
        // no polling interval needed. 30s safety fallback only.
        document.addEventListener('lms:waiting-update', e => {
          if (e.detail && parseInt(e.detail.session_id) === SID) pollW();
        });
        wint = setInterval(pollW, 30000); // safety fallback only
      } else {
        go()
      }
      window.addEventListener('beforeunload', e => {
        e.preventDefault();
        e.returnValue = 'Leaving ends the session for all students.'
      });
    });
  </script>
<?php include __DIR__ . '/../includes/footer.php';
  exit;
}

// ═══════════════════════════════════════════════════════════════
// LIST PAGE — no session_id
// ═══════════════════════════════════════════════════════════════
$pageTitle   = 'Live Sessions';
$breadcrumbs = [['label' => ucfirst($userRole), 'url' => BASE_PATH . '/' . $userRole . '/'], ['label' => 'Live Sessions']];

if ($userRole === 'teacher') {
  $s = $conn->prepare("SELECT ls.id, ls.title, ls.status, ls.started_at, ls.ended_at, b.name AS batch_name, c.title AS course_title, TIMESTAMPDIFF(SECOND, ls.started_at, COALESCE(ls.ended_at,NOW())) AS duration_seconds, (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id) AS total_joined, (SELECT COUNT(*) FROM batch_students WHERE batch_id=ls.batch_id) AS total_students FROM live_sessions ls JOIN batches b ON ls.batch_id=b.id JOIN courses c ON b.course_id=c.id WHERE ls.started_by=? ORDER BY ls.started_at DESC LIMIT 40");
  $s->bind_param('i', $currentUser['id']);
  $s->execute();
  $sessions = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  $s->close();
  $sb = $conn->prepare("SELECT b.id, b.name, c.title AS course_title FROM batch_teachers bt JOIN batches b ON bt.batch_id=b.id JOIN courses c ON b.course_id=c.id WHERE bt.teacher_id=? AND b.status='active' ORDER BY c.title, b.name");
  $sb->bind_param('i', $currentUser['id']);
  $sb->execute();
  $myBatches = $sb->get_result()->fetch_all(MYSQLI_ASSOC);
  $sb->close();
} else {
  $r = $conn->query("SELECT ls.id, ls.title, ls.status, ls.started_at, ls.ended_at, b.name AS batch_name, c.title AS course_title, u.full_name AS teacher_name, TIMESTAMPDIFF(SECOND, ls.started_at, COALESCE(ls.ended_at,NOW())) AS duration_seconds, (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id) AS total_joined, (SELECT COUNT(*) FROM batch_students WHERE batch_id=ls.batch_id) AS total_students FROM live_sessions ls JOIN batches b ON ls.batch_id=b.id JOIN courses c ON b.course_id=c.id JOIN users u ON ls.started_by=u.id ORDER BY ls.started_at DESC LIMIT 50");
  $sessions = $r->fetch_all(MYSQLI_ASSOC);
  $myBatches = [];
}

$liveSessions = array_filter($sessions, fn($s) => in_array($s['status'], ['waiting', 'active']));
$msg = $_GET['msg'] ?? '';
$err = $_GET['error'] ?? '';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Live Sessions</h1>
          <p class="page-subtitle">Start a live class and manage your sessions</p>
        </div>
        <?php if (!empty($myBatches) || $userRole === 'admin'): ?>
          <button class="btn btn-danger" onclick="Modal.open('start-live')">
            <i data-lucide="video" style="width:15px;height:15px;"></i> 🔴 Go Live
          </button>
        <?php endif; ?>
      </div>

      <?php if ($msg === 'ended'): ?><div class="alert alert-success" style="margin-bottom:20px;">✅ Session ended successfully.</div><?php endif; ?>
      <?php if ($err === 'not_found'): ?><div class="alert alert-error" style="margin-bottom:20px;">⚠️ Session not found.</div><?php endif; ?>

      <?php if (!empty($liveSessions)): ?>
        <div class="card" style="margin-bottom:20px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.04);padding:16px 20px;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:lp 1.4s ease infinite;display:inline-block;"></span>
            <span style="font-weight:700;font-size:.88rem;">🔴 Live Right Now</span>
          </div>
          <style>
            @keyframes lp {

              0%,
              100% {
                opacity: 1
              }

              50% {
                opacity: .4
              }
            }
          </style>
          <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach ($liveSessions as $ls): ?>
              <div style="display:flex;align-items:center;gap:12px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:12px 16px;flex:1;min-width:220px;">
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:700;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($ls['title']) ?></div>
                  <div style="font-size:.72rem;color:var(--text-muted);"><?= e($ls['batch_name']) ?> · <?= $ls['status'] === 'waiting' ? '🟡 Waiting Room' : '🔴 Active' ?></div>
                </div>
                <a href="<?= BASE_PATH ?>/<?= $userRole ?>/live.php?session_id=<?= $ls['id'] ?>" class="btn btn-primary btn-sm">
                  <i data-lucide="video" style="width:13px;height:13px;margin-right:5px;"></i><?= $ls['status'] === 'waiting' ? 'Open Room' : 'Rejoin' ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);">
          <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.95rem;">Session History</div>
        </div>
        <?php if (empty($sessions)): ?>
          <div style="text-align:center;padding:48px 24px;">
            <div style="font-size:2.5rem;margin-bottom:12px;">📹</div>
            <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:1rem;margin-bottom:8px;">No sessions yet</div>
            <div style="color:var(--text-muted);font-size:.84rem;">Click <strong>Go Live</strong> to start your first class.</div>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Session</th>
                  <th>Batch</th><?= $userRole === 'admin' ? '<th>Teacher</th>' : '' ?><th>Status</th>
                  <th>Started</th>
                  <th>Duration</th>
                  <th>Attended</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sessions as $ls):
                  $sec = (int)$ls['duration_seconds'];
                  $dh = floor($sec / 3600);
                  $dm = floor(($sec % 3600) / 60);
                  $dur = $sec < 60 ? '<1m' : ($dh > 0 ? "{$dh}h {$dm}m" : "{$dm}m");
                  $started = $ls['started_at'] ? date('M j, g:i A', strtotime($ls['started_at'])) : '—'; ?>
                  <tr>
                    <td>
                      <div style="font-weight:600;"><?= e($ls['title']) ?></div>
                      <div style="font-size:.7rem;color:var(--text-muted);"><?= e($ls['course_title']) ?></div>
                    </td>
                    <td style="font-size:.82rem;"><?= e($ls['batch_name']) ?></td>
                    <?php if ($userRole === 'admin'): ?><td style="font-size:.82rem;"><?= e($ls['teacher_name'] ?? '') ?></td><?php endif; ?>
                    <td><?php if ($ls['status'] === 'active'): ?><span class="badge badge-danger"><span class="badge-dot" style="background:#ef4444;"></span>Active</span><?php elseif ($ls['status'] === 'waiting'): ?><span class="badge badge-warning"><span class="badge-dot" style="background:#f59e0b;"></span>Waiting</span><?php else: ?><span class="badge badge-success"><span class="badge-dot" style="background:#10b981;"></span>Ended</span><?php endif; ?></td>
                    <td style="font-size:.8rem;"><?= $started ?></td>
                    <td style="font-size:.82rem;"><?= $dur ?></td>
                    <td><span style="font-weight:700;"><?= $ls['total_joined'] ?></span><span style="color:var(--text-muted);font-size:.73rem;"> / <?= $ls['total_students'] ?></span></td>
                    <td><?php if (in_array($ls['status'], ['waiting', 'active'])): ?><a href="<?= BASE_PATH ?>/<?= $userRole ?>/live.php?session_id=<?= $ls['id'] ?>" class="btn btn-primary btn-sm"><i data-lucide="video" style="width:12px;height:12px;"></i> <?= $ls['status'] === 'waiting' ? 'Open Room' : 'Rejoin' ?></a><?php else: ?><span style="font-size:.75rem;color:var(--text-muted);">Completed</span><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<div class="modal-overlay" id="start-live">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">🔴 Start Live Class</h3>
      <button class="modal-close" data-modal-close="start-live"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body" style="padding:24px;">
      <div class="form-group">
        <label class="form-label">Session Title <span class="required">*</span></label>
        <input type="text" id="live-title" class="form-control" placeholder="e.g. Chapter 5 — Introduction to React">
      </div>
      <?php if (!empty($myBatches)): ?>
        <div class="form-group">
          <label class="form-label">Batch <span class="required">*</span></label>
          <select id="live-batch" class="form-control">
            <option value="">Select a batch…</option>
            <?php foreach ($myBatches as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?> — <?= e($b['course_title']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:9px;padding:11px 14px;font-size:.78rem;color:var(--text-muted);line-height:1.6;">
        A <strong>waiting room</strong> opens first. Students are notified and can join the waiting area. You'll see them appear before starting.
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close="start-live">Cancel</button>
      <button class="btn btn-danger" id="go-live-btn" onclick="goLive()">
        <i data-lucide="video" style="width:14px;height:14px;"></i>
        <span class="btn-text">🔴 Go Live</span>
      </button>
    </div>
  </div>
</div>
<script>
  async function goLive() {
    const title = document.getElementById('live-title').value.trim() || 'Live Class';
    const batchEl = document.getElementById('live-batch');
    const batchId = batchEl ? batchEl.value : '';
    const btn = document.getElementById('go-live-btn');
    if (!batchId) {
      if (window.Toast) Toast.error('Please select a batch');
      return
    }
    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Starting…';
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/live.ajax.php', {
        action: 'start',
        batch_id: batchId,
        title: title
      });
      if (res.status === 'success') {
        Modal.close('start-live');
        if (window.Toast) Toast.success('Waiting room open! Redirecting…');
        setTimeout(() => {
          window.location.href = window.LMS_BASE + '/<?= $userRole ?>/live.php?session_id=' + res.data.session_id
        }, 500)
      } else {
        if (window.Toast) Toast.error(res.message || 'Failed');
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = '🔴 Go Live'
      }
    } catch (e) {
      if (window.Toast) Toast.error('Network error');
      btn.disabled = false;
      btn.querySelector('.btn-text').textContent = '🔴 Go Live'
    }
  }
  document.getElementById('live-title')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') goLive()
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>