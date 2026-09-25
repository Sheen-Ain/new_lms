<?php
// ============================================================
// ADMIN — LIVE SESSIONS
// No ?session_id  → management list page
// With ?session_id → host room (admin joins as moderator)
// ============================================================
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/jaas_jwt.php';

$sessionId = (int)($_GET['session_id'] ?? 0);

// ══════════════════════════════════════════════════════════════
// HOST ROOM — when ?session_id is provided
// ══════════════════════════════════════════════════════════════
if ($sessionId) {

    $s = $conn->prepare("
        SELECT ls.*, b.name AS batch_name, c.title AS course_title,
               TIMESTAMPDIFF(SECOND, ls.started_at, NOW()) AS elapsed_seconds
        FROM live_sessions ls
        JOIN batches b ON ls.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE ls.id = ?
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$session) {
        header('Location: ' . BASE_PATH . '/admin/live.php?error=not_found');
        exit;
    }
    if ($session['status'] === 'ended') {
        header('Location: ' . BASE_PATH . '/admin/live.php?msg=ended');
        exit;
    }

    $roomFull   = $session['room_name'];
    $roomSuffix = strpos($roomFull, '/') !== false
        ? substr($roomFull, strrpos($roomFull, '/') + 1)
        : $roomFull;

    $jwt = generateJaaSJWT($roomSuffix, [
        'id'    => $currentUser['id'],
        'name'  => $currentUser['full_name'],
        'email' => $currentUser['email'] ?? '',
    ], true);

    $appId     = defined('JAAS_APP_ID') ? JAAS_APP_ID : '';
    $isWaiting = ($session['status'] === 'waiting');
    $elapsed   = max(0, (int)($session['elapsed_seconds'] ?? 0));
    $pageTitle = 'Live: ' . $session['title'];

    include __DIR__ . '/../includes/header.php';
?>
    <style>
        body {
            overflow: hidden;
        }

        .hw {
            display: flex;
            flex-direction: column;
            height: 100svh;
            background: #07071a;
            font-family: 'DM Sans', sans-serif;
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
            z-index: 50;
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
        }

        .hbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: .9rem;
            color: #fff;
            letter-spacing: -.03em;
            flex-shrink: 0;
        }

        .hbdg {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 11px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .hbdg.w {
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .28);
        }

        .hbdg.live {
            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .28);
        }

        .hbdg-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            animation: dp 1.4s ease infinite;
        }

        .hbdg.w .hbdg-dot {
            background: #f59e0b;
        }

        .hbdg.live .hbdg-dot {
            background: #ef4444;
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
            letter-spacing: .08em;
        }

        .hbdg.w .hbdg-lbl {
            color: #f59e0b;
        }

        .hbdg.live .hbdg-lbl {
            color: #ef4444;
        }

        .hsp {
            flex: 1;
        }

        .htmr {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: .85rem;
            font-weight: 700;
            color: #10b981;
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .2);
            padding: 4px 12px;
            border-radius: 999px;
            min-width: 62px;
            text-align: center;
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
            font-family: 'DM Sans', sans-serif;
            flex-shrink: 0;
            box-shadow: 0 3px 14px rgba(239, 68, 68, .35);
            transition: opacity .15s, transform .1s;
        }

        .hend-btn:hover {
            opacity: .85;
            transform: translateY(-1px);
        }

        .hend-btn:active {
            transform: scale(.96);
        }

        .hbody {
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        #wrp {
            position: absolute;
            inset: 0;
            display: flex;
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
            overflow-y: auto;
        }

        .wrh {
            font-family: 'Poppins', sans-serif;
            font-size: .96rem;
            font-weight: 800;
            color: #fff;
        }

        .wrsb {
            font-size: .75rem;
            color: rgba(255, 255, 255, .4);
            margin-top: 2px;
        }

        .wrcts {
            display: flex;
            gap: 10px;
        }

        .wrct {
            flex: 1;
            background: rgba(99, 102, 241, .08);
            border: 1px solid rgba(99, 102, 241, .15);
            border-radius: 12px;
            padding: 13px;
        }

        .wrct-val {
            font-family: 'Poppins', sans-serif;
            font-size: 1.45rem;
            font-weight: 900;
            color: #6366f1;
        }

        .wrct-lbl {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255, 255, 255, .35);
            margin-top: 2px;
        }

        .btnst {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: .9rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 6px 24px rgba(99, 102, 241, .4);
            transition: transform .15s, box-shadow .15s;
        }

        .btnst:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, .55);
        }

        .btnst:active {
            transform: scale(.97);
        }

        .btnst:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .wr-ll {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255, 255, 255, .35);
        }

        .wr-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-top: 7px;
        }

        .wr-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            animation: sli .2s ease;
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
            color: #fff;
        }

        .wrname {
            font-size: .82rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .88);
        }

        .wr-empty {
            text-align: center;
            padding: 22px 0;
            color: rgba(255, 255, 255, .3);
            font-size: .78rem;
            line-height: 1.7;
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
            background: radial-gradient(ellipse at 50% 40%, #120f2e 0%, #07071a 100%);
        }

        .wrr-ico {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 14px rgba(99, 102, 241, .1), 0 0 0 28px rgba(99, 102, 241, .05);
            animation: glow 2.5s ease infinite;
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
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
        }

        .wrr-sub {
            font-size: .82rem;
            color: rgba(255, 255, 255, .42);
            max-width: 340px;
            line-height: 1.8;
        }

        #jip {
            position: absolute;
            inset: 0;
            display: none;
        }

        #jitsi-container {
            width: 100%;
            height: 100%;
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
            transition: opacity .2s;
        }

        .eov.show {
            opacity: 1;
            pointer-events: all;
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
            animation: pIn .22s cubic-bezier(.34, 1.56, .64, 1);
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
            margin-bottom: 14px;
        }

        .eov-ttl {
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 9px;
        }

        .eov-sub {
            font-size: .83rem;
            color: rgba(255, 255, 255, .5);
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .eov-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @media(max-width:768px) {
            .wrl {
                width: 260px;
                padding: 16px 12px;
            }

            .hbar-brand {
                display: none;
            }
        }

        @media(max-width:600px) {
            #wrp {
                flex-direction: column;
            }

            .wrl {
                width: 100%;
                height: 52%;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, .07);
            }

            .wrr {
                height: 48%;
                padding: 16px;
            }
        }
    </style>

    <div class="hw">
        <div class="hbar">
            <div class="hbar-logo"><i data-lucide="graduation-cap" style="width:18px;height:18px;color:#fff;"></i></div>
            <span class="hbar-brand">EduFlow</span>
            <div class="hbdg <?= $isWaiting ? 'w' : 'live' ?>" id="hbdg">
                <div class="hbdg-dot"></div>
                <span class="hbdg-lbl"><?= $isWaiting ? 'Waiting Room' : 'Live' ?></span>
            </div>
            <div style="display:flex;flex-direction:column;min-width:0;">
                <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.86rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;"><?= e($session['title']) ?></div>
                <div style="font-size:.66rem;color:rgba(255,255,255,.4);"><?= e($session['course_title']) ?> › <?= e($session['batch_name']) ?> · Admin</div>
            </div>
            <div class="hsp"></div>
            <div style="display:flex;align-items:center;gap:14px;">
                <span id="hcnt" style="display:none;font-size:.77rem;color:rgba(255,255,255,.55);">
                    <strong id="hcntv">—</strong> in class
                </span>
                <div class="htmr" id="htmr">00:00</div>
            </div>
            <button class="hend-btn" onclick="confirmEnd()">
                <i data-lucide="square" style="width:12px;height:12px;"></i> End Session
            </button>
        </div>

        <div class="hbody">
            <div id="wrp" <?= !$isWaiting ? 'style="display:none"' : '' ?>>
                <div class="wrl">
                    <div>
                        <div class="wrh">Waiting Room</div>
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
                        <i data-lucide="play" style="width:16px;height:16px;"></i> Start Class Now
                    </button>
                    <div>
                        <div class="wr-ll">Students waiting</div>
                        <div class="wr-list" id="wrlist">
                            <div class="wr-empty">No students yet — they'll appear here as they join.</div>
                        </div>
                    </div>
                </div>
                <div class="wrr">
                    <div class="wrr-ico"><i data-lucide="graduation-cap" style="width:34px;height:34px;color:#fff;"></i></div>
                    <div class="wrr-ttl"><?= e($session['title']) ?></div>
                    <div class="wrr-sub">See who's waiting on the left, then click <strong style="color:#a5b4fc;">Start Class Now</strong> when ready.</div>
                </div>
            </div>
            <div id="jip" <?= !$isWaiting ? 'style="display:block"' : '' ?>>
                <div id="jitsi-container"></div>
            </div>
        </div>
    </div>

    <div class="eov" id="eov">
        <div class="eov-box">
            <div class="eov-ico"><i data-lucide="square" style="width:32px;height:32px;color:#ef4444;"></i></div>
            <div class="eov-ttl">End Session?</div>
            <div class="eov-sub">This disconnects all students permanently. Cannot be undone.</div>
            <div class="eov-btns">
                <button class="btn btn-secondary" onclick="document.getElementById('eov').classList.remove('show')">Keep Going</button>
                <button class="btn btn-danger" id="eovbtn" onclick="doEnd()">End Now</button>
            </div>
        </div>
    </div>

    <script src="https://8x8.vc/<?= urlencode($appId) ?>/external_api.js"
        onerror="this.onerror=null;var s=document.createElement('script');s.src='https://meet.jit.si/external_api.js';document.head.appendChild(s)">
    </script>
    <script>
        'use strict';
        const SID = <?= (int)$sessionId ?>;
        const RFULL = <?= json_encode($roomFull) ?>;
        const DNAME = <?= json_encode($currentUser['full_name'] . ' (Admin)') ?>;
        const JWT = <?= json_encode($jwt) ?>;
        const JAAS_DOM = '8x8.vc';
        let ISW = <?= $isWaiting ? 'true' : 'false' ?>;
        let elapsed = <?= $elapsed ?>,
            japi = null,
            tint = null,
            pint = null,
            wint = null;
        const COLS = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function tick() {
            elapsed++;
            const h = Math.floor(elapsed / 3600),
                m = Math.floor((elapsed % 3600) / 60),
                s = elapsed % 60;
            const p = n => String(n).padStart(2, '0');
            document.getElementById('htmr').textContent = h > 0 ? `${p(h)}:${p(m)}:${p(s)}` : `${p(m)}:${p(s)}`;
        }

        async function pollW() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_waiting_list');
                fd.append('session_id', SID);
                fd.append('csrf_token', window.CSRF_TOKEN || '');
                const res = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await res.json();
                if (d.status !== 'success') return;
                document.getElementById('wrw').textContent = d.data.waiting_count || 0;
                document.getElementById('wrt').textContent = d.data.total_students || '—';
                const list = document.getElementById('wrlist');
                if (!d.data.waiting || !d.data.waiting.length) {
                    list.innerHTML = '<div class="wr-empty">No students yet — they\'ll appear here as they join.</div>';
                    return;
                }
                list.innerHTML = d.data.waiting.map(u => {
                    const ini = (u.full_name || 'U').trim().split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
                    const col = COLS[Math.abs((u.full_name || '').split('').reduce((h, c) => h * 31 + c.charCodeAt(0), 0)) % COLS.length];
                    return `<div class="wr-item">
        <div class="wrav" style="background:${col}">${ini}</div>
        <div>
          <div class="wrname">${esc(u.full_name)}</div>
          <div style="font-size:.67rem;color:rgba(255,255,255,.35);">Waiting…</div>
        </div>
      </div>`;
                }).join('');
            } catch (e) {}
        }

        async function startClass() {
            const btn = document.getElementById('btnst');
            btn.disabled = true;
            btn.innerHTML = '<span>Opening classroom…</span>';
            try {
                const fd = new FormData();
                fd.append('action', 'open');
                fd.append('session_id', SID);
                fd.append('csrf_token', window.CSRF_TOKEN || '');
                const res = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await res.json();
                if (d.status !== 'success') {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="play" style="width:16px;height:16px;"></i> Start Class Now';
                    if (window.lucide) lucide.createIcons({
                        nodes: [btn]
                    });
                    if (window.Toast) Toast.error(d.message || 'Failed to start');
                    return;
                }
                clearInterval(wint);
                switchToJitsi();
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="play" style="width:16px;height:16px;"></i> Start Class Now';
                if (window.lucide) lucide.createIcons({
                    nodes: [btn]
                });
                if (window.Toast) Toast.error('Network error');
            }
        }

        function switchToJitsi() {
            ISW = false;
            document.getElementById('wrp').style.display = 'none';
            document.getElementById('jip').style.display = 'block';
            const badge = document.getElementById('hbdg');
            badge.className = 'hbdg live';
            badge.innerHTML = '<div class="hbdg-dot"></div><span class="hbdg-lbl">Live</span>';
            document.getElementById('hcnt').style.display = 'flex';
            initJitsi();
            tint = setInterval(tick, 1000);
            pint = setInterval(syncCount, 20000);
            syncCount();
        }

        function initJitsi() {
            if (japi) return;
            const options = {
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
                    toolbarButtons: ['microphone', 'camera', 'desktop', 'chat', 'raisehand', 'tileview', 'participants-pane', 'settings', 'hangup'],
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
            if (JWT) options.jwt = JWT;
            japi = new JitsiMeetExternalAPI(JAAS_DOM, options);
            japi.addEventListener('readyToClose', () => doEnd());
            japi.addEventListener('participantJoined', syncCount);
            japi.addEventListener('participantLeft', syncCount);
        }

        async function syncCount() {
            try {
                const fd = new FormData();
                fd.append('action', 'get_participants');
                fd.append('session_id', SID);
                fd.append('csrf_token', window.CSRF_TOKEN || '');
                const res = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await res.json();
                if (d.status === 'success') document.getElementById('hcntv').textContent = d.data.live_count || 0;
            } catch (e) {}
        }

        function confirmEnd() {
            document.getElementById('eov').classList.add('show');
        }

        async function doEnd() {
            const btn = document.getElementById('eovbtn');
            btn.disabled = true;
            btn.textContent = 'Ending…';
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
                });
            } catch (e) {}
            if (japi) {
                try {
                    japi.dispose();
                } catch (e) {}
            }
            window.location.href = window.LMS_BASE + '/admin/live.php';
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (ISW) {
                pollW();
                wint = setInterval(pollW, 5000);
            } else {
                switchToJitsi();
            }
            window.addEventListener('beforeunload', e => {
                e.preventDefault();
                e.returnValue = 'Leaving this page will end the session for all students.';
            });
        });
    </script>
    <?php
    // ── Minimal footer for live host room (no Pusher/chat widget) ──
    ?>
    <script src="<?= BASE_PATH ?>/assets/js/toast.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/modal.js"></script>
    <script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) lucide.createIcons();
        });
    </script>
    </body>

    </html>
<?php
    exit;
}

// ══════════════════════════════════════════════════════════════
// MANAGEMENT LIST PAGE
// ══════════════════════════════════════════════════════════════
$pageTitle   = 'Live Sessions';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Live Sessions']];

$r = $conn->query("SELECT COUNT(*) AS c FROM live_sessions WHERE status='active'");
$cntActive = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM live_sessions WHERE status='waiting'");
$cntWaiting = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM live_sessions WHERE status='ended'");
$cntEnded = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) AS c FROM live_sessions WHERE DATE(started_at)=CURDATE()");
$cntToday = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE,started_at,COALESCE(ended_at,NOW()))),0) AS tot FROM live_sessions WHERE status='ended'");
$totalMins = (int)$r->fetch_assoc()['tot'];
$r = $conn->query("SELECT COUNT(*) AS c FROM session_attendees");
$cntAttend = (int)$r->fetch_assoc()['c'];

$batches = [];
$r = $conn->query("SELECT b.id,b.name,c.title AS course_title FROM batches b JOIN courses c ON b.course_id=c.id WHERE b.status='active' ORDER BY c.title,b.name");
while ($row = $r->fetch_assoc()) $batches[] = $row;

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
                    <p class="page-subtitle">Monitor, manage and join all live classes</p>
                </div>
                <button class="btn btn-danger" onclick="Modal.open('start-live')">
                    <i data-lucide="video" style="width:15px;height:15px;"></i> Start a Session
                </button>
            </div>

            <?php if ($msg === 'ended'): ?><div class="alert alert-success" style="margin-bottom:18px;">Session ended successfully.</div><?php endif; ?>
            <?php if ($err === 'not_found'): ?><div class="alert alert-error" style="margin-bottom:18px;">Session not found.</div><?php endif; ?>

            <div class="stats-grid" style="margin-bottom:24px;">
                <?php $stats = [['video', 'Active Now', $cntActive, 'var(--danger)', 'rgba(239,68,68,0.08)'], ['clock', 'Waiting Room', $cntWaiting, 'var(--warning)', 'rgba(245,158,11,0.08)'], ['check-circle', 'Ended', $cntEnded, 'var(--success)', 'rgba(16,185,129,0.08)'], ['calendar', 'Today', $cntToday, 'var(--primary)', 'rgba(99,102,241,0.08)'], ['timer', 'Total Hours', round($totalMins / 60, 1), 'var(--info)', 'rgba(6,182,212,0.08)'], ['users', 'Attendances', $cntAttend, '#8b5cf6', 'rgba(139,92,246,0.08)']];
                foreach ($stats as [$icon, $lbl, $val, $color, $bg]): ?>
                    <div class="stat-card" style="background:<?= $bg ?>;border:1px solid <?= $color ?>33;">
                        <div class="stat-card-icon" style="background:<?= $bg ?>;color:<?= $color ?>;"><i data-lucide="<?= $icon ?>" style="width:20px;height:20px;"></i></div>
                        <div class="stat-card-value" style="color:<?= $color ?>;"><?= $val ?></div>
                        <div class="stat-card-label"><?= $lbl ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($cntActive + $cntWaiting > 0): ?>
                <div class="card" style="margin-bottom:20px;border:1px solid rgba(239,68,68,.22);background:rgba(239,68,68,.04);padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:lp 1.4s ease infinite;display:inline-block;"></span>
                        <span style="font-weight:700;font-size:.88rem;">Live Right Now</span>
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
                    <div id="live-now-area" style="display:flex;flex-wrap:wrap;gap:10px;">
                        <div style="color:var(--text-muted);font-size:.82rem;">Loading…</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card" style="padding:0;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;">
                    <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:.95rem;">All Sessions</div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input type="text" id="srch" class="form-control" placeholder="Search…" style="width:200px;" oninput="debouncedLoad()">
                        <select id="stFilter" class="form-control" style="width:140px;" onchange="loadSessions(1)">
                            <option value="">All statuses</option>
                            <option value="waiting">Waiting</option>
                            <option value="active">Active</option>
                            <option value="ended">Ended</option>
                        </select>
                        <button class="btn btn-secondary btn-sm" onclick="loadSessions(1);loadLiveNow();" title="Refresh">
                            <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table" style="min-width:860px;">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Teacher</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Attendees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-tbody">
                            <tr>
                                <td colspan="8" style="text-align:center;padding:28px;color:var(--text-muted);">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-top:1px solid var(--border);flex-wrap:wrap;gap:10px;">
                    <div id="pg-info" style="font-size:.8rem;color:var(--text-muted);"></div>
                    <div id="pg-btns" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Start Modal -->
<div class="modal-overlay" id="start-live-overlay">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-icon" style="background:rgba(239,68,68,.1);"><i data-lucide="video" style="width:20px;height:20px;color:var(--danger);"></i></div>
            <h3 class="modal-title">Start Live Session</h3>
            <button class="modal-close" data-modal-close="start-live"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div class="form-group">
                <label class="form-label">Session Title <span class="required">*</span></label>
                <input type="text" id="sm-title" class="form-control" placeholder="e.g. Chapter 5 — React Hooks">
            </div>
            <div class="form-group">
                <label class="form-label">Batch <span class="required">*</span></label>
                <select id="sm-batch" class="form-control">
                    <option value="">Select a batch…</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= e($b['id']) ?>"><?= e($b['name']) ?> — <?= e($b['course_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:9px;padding:12px 14px;font-size:.78rem;color:var(--text-muted);line-height:1.6;">
                <i data-lucide="info" style="width:12px;height:12px;display:inline;margin-right:4px;"></i>
                You'll join as <strong>moderator</strong> with full controls. A waiting room opens first.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close="start-live">Cancel</button>
            <button class="btn btn-danger" id="sm-go-btn" onclick="adminStartSession()">
                <i data-lucide="video" style="width:14px;height:14px;"></i> <span class="btn-text">Go Live</span>
            </button>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detail-modal-overlay">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <div class="modal-icon modal-icon-primary"><i data-lucide="video" style="width:20px;height:20px;"></i></div>
            <h3 class="modal-title" id="det-title">Session Details</h3>
            <button class="modal-close" data-modal-close="detail-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
        </div>
        <div class="modal-body" id="det-body" style="padding:24px;max-height:70vh;overflow-y:auto;">Loading…</div>
        <div class="modal-footer" id="det-footer"></div>
    </div>
</div>

<script>
    'use strict';
    let curPage = 1;
    const debouncedLoad = debounce(() => loadSessions(1), 320);

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtDate(dt) {
        if (!dt) return '—';
        return new Date(dt.replace(' ', 'T') + 'Z').toLocaleString('en-PK', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function fmtDur(mins) {
        const m = parseInt(mins || 0);
        if (m < 1) return m === 0 ? '—' : '<1m';
        const h = Math.floor(m / 60),
            rm = m % 60;
        return h > 0 ? `${h}h ${rm}m` : `${m}m`;
    }

    function statusBadge(s) {
        const m = {
            waiting: '<span class="badge badge-warning">Waiting</span>',
            active: '<span class="badge badge-danger">Active</span>',
            ended: '<span class="badge badge-success">Ended</span>'
        };
        return m[s] || `<span class="badge badge-secondary">${esc(s)}</span>`;
    }

    async function loadSessions(page) {
        if (page) curPage = page;
        const tbody = document.getElementById('sessions-tbody');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">Loading…</td></tr>';
        const fd = new FormData();
        fd.append('action', 'admin_list');
        fd.append('page', curPage);
        fd.append('status_filter', document.getElementById('stFilter').value);
        fd.append('search', document.getElementById('srch').value);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        try {
            const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                method: 'POST',
                body: fd
            });
            const d = await r.json();
            if (d.status !== 'success') {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--danger);">Error: ${esc(d.message)}</td></tr>`;
                return;
            }
            const {
                sessions,
                total,
                page: pg,
                per_page
            } = d.data;
            if (!sessions.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">No sessions found.</td></tr>';
                document.getElementById('pg-info').textContent = '';
                document.getElementById('pg-btns').innerHTML = '';
                return;
            }
            tbody.innerHTML = sessions.map(s => `<tr>
      <td><div style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(s.title)}">${esc(s.title)}</div><div style="font-size:.7rem;color:var(--text-muted);">ID #${s.id}</div></td>
      <td style="font-size:.83rem;font-weight:500;">${esc(s.teacher_name)}</td>
      <td><div style="font-size:.82rem;">${esc(s.batch_name)}</div><div style="font-size:.68rem;color:var(--text-muted);">${esc(s.course_title)}</div></td>
      <td>${statusBadge(s.status)}</td>
      <td style="font-size:.8rem;white-space:nowrap;">${fmtDate(s.started_at)}</td>
      <td style="font-size:.82rem;">${fmtDur(s.duration_minutes)}</td>
      <td><span style="font-weight:700;">${s.total_joined||0}</span><span style="color:var(--text-muted);font-size:.73rem;"> / ${s.total_students||'?'}</span></td>
      <td><div style="display:flex;gap:5px;flex-wrap:wrap;">
        <button class="btn btn-secondary btn-sm" onclick="openDetail(${s.id})" title="Details"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
        ${s.status!=='ended'?`<a href="${window.LMS_BASE}/admin/live.php?session_id=${s.id}" target="_blank" class="btn btn-primary btn-sm" title="Join as Moderator"><i data-lucide="video" style="width:13px;height:13px;"></i></a>
        <button class="btn btn-sm" style="background:rgba(239,68,68,.1);color:var(--danger);border:1px solid rgba(239,68,68,.25);" onclick="forceEnd(${s.id},'${esc(s.title)}')" title="Force End"><i data-lucide="square" style="width:13px;height:13px;"></i></button>`:''}
        <button class="btn btn-sm" style="color:var(--danger);border:1px solid rgba(239,68,68,.2);background:rgba(239,68,68,.06);" onclick="deleteSession(${s.id},'${esc(s.title)}')" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`).join('');
            lucide.createIcons({
                nodes: [tbody]
            });
            const tp = Math.ceil(total / per_page);
            document.getElementById('pg-info').textContent = `Showing ${(pg-1)*per_page+1}–${Math.min(pg*per_page,total)} of ${total}`;
            document.getElementById('pg-btns').innerHTML = Array.from({
                length: tp
            }, (_, i) => `<button class="page-btn${i+1===pg?' active':''}" onclick="loadSessions(${i+1})">${i+1}</button>`).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--danger);">Network error</td></tr>';
        }
    }

    async function loadLiveNow() {
        const area = document.getElementById('live-now-area');
        if (!area) return;
        const fd = new FormData();
        fd.append('action', 'get_active');
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        try {
            const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                method: 'POST',
                body: fd
            });
            const d = await r.json();
            if (d.status !== 'success' || !d.data.sessions.length) {
                area.innerHTML = '<p style="color:var(--text-muted);font-size:.82rem;margin:0;">No sessions running right now.</p>';
                return;
            }
            area.innerHTML = d.data.sessions.map(s => `<div style="display:flex;align-items:center;gap:10px;background:var(--bg-card);border:1px solid var(--border);border-radius:11px;padding:10px 14px;min-width:220px;flex:1;">
      <div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:.83rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(s.title)}</div><div style="font-size:.7rem;color:var(--text-muted);">${esc(s.teacher_name)} · ${esc(s.batch_name)}</div></div>
      ${statusBadge(s.status)}
      <a href="${window.LMS_BASE}/admin/live.php?session_id=${s.id}" target="_blank" class="btn btn-primary btn-sm" style="flex-shrink:0;"><i data-lucide="video" style="width:12px;height:12px;margin-right:4px;"></i>Join</a>
    </div>`).join('');
            lucide.createIcons({
                nodes: [area]
            });
        } catch (e) {}
    }

    async function openDetail(id) {
        document.getElementById('det-body').innerHTML = '<div style="text-align:center;padding:28px;color:var(--text-muted);">Loading…</div>';
        document.getElementById('det-footer').innerHTML = '';
        Modal.open('detail-modal');
        const fd = new FormData();
        fd.append('action', 'get_participants');
        fd.append('session_id', id);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        try {
            const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                method: 'POST',
                body: fd
            });
            const d = await r.json();
            if (d.status !== 'success') {
                document.getElementById('det-body').innerHTML = '<p style="color:var(--danger);">Failed to load.</p>';
                return;
            }
            const s = d.data,
                sec = parseInt(s.duration_seconds || 0),
                h = Math.floor(sec / 3600),
                m = Math.floor((sec % 3600) / 60),
                pp = n => String(n).padStart(2, '0');
            document.getElementById('det-title').textContent = `Session #${id}`;
            document.getElementById('det-body').innerHTML = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      ${[['Status',statusBadge(s.status)],['Duration',h>0?`${pp(h)}h ${pp(m)}m`:`${pp(m)}m`],['Currently Live',s.live_count||0],['Total Joined',s.total_joined||0]]
        .map(([k,v])=>`<div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:11px 14px;"><div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px;">${k}</div><div style="font-size:.88rem;font-weight:600;">${v}</div></div>`).join('')}
    </div>`;
            lucide.createIcons({
                nodes: [document.getElementById('det-body')]
            });
            let btns = '';
            if (s.status !== 'ended') {
                btns += `<a href="${window.LMS_BASE}/admin/live.php?session_id=${id}" target="_blank" class="btn btn-primary"><i data-lucide="video" style="width:14px;height:14px;margin-right:5px;"></i>Join as Moderator</a>
               <button class="btn btn-danger" onclick="Modal.close('detail-modal');forceEnd(${id},'Session #${id}')"><i data-lucide="square" style="width:14px;height:14px;margin-right:5px;"></i>Force End</button>`;
            }
            btns += `<button class="btn btn-secondary" style="color:var(--danger);" onclick="Modal.close('detail-modal');deleteSession(${id},'Session #${id}')"><i data-lucide="trash-2" style="width:14px;height:14px;margin-right:5px;"></i>Delete</button>`;
            document.getElementById('det-footer').innerHTML = btns;
            lucide.createIcons({
                nodes: [document.getElementById('det-footer')]
            });
        } catch (e) {
            document.getElementById('det-body').innerHTML = '<p style="color:var(--danger);">Network error</p>';
        }
    }

    async function adminStartSession() {
        const title = document.getElementById('sm-title').value.trim() || 'Live Class';
        const batchId = document.getElementById('sm-batch').value;
        const btn = document.getElementById('sm-go-btn');
        if (!batchId) {
            if (window.Toast) Toast.error('Please select a batch');
            return;
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
                if (window.Toast) Toast.success('Session created — opening host room…');
                setTimeout(() => {
                    window.open(window.LMS_BASE + '/admin/live.php?session_id=' + res.data.session_id, '_blank');
                    loadSessions(1);
                    loadLiveNow();
                }, 500);
            } else {
                if (window.Toast) Toast.error(res.message || 'Failed');
                btn.disabled = false;
                btn.querySelector('.btn-text').textContent = 'Go Live';
            }
        } catch (e) {
            if (window.Toast) Toast.error('Network error');
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Go Live';
        }
    }

    function forceEnd(id, title) {
        Modal.confirm({
            title: 'Force End Session',
            message: `End <strong>${esc(title)}</strong>? All students will be disconnected.`,
            confirmText: 'Force End',
            confirmClass: 'btn-danger',
            iconClass: 'modal-icon-danger',
            icon: '<i data-lucide="square" style="width:20px;height:20px;color:var(--danger);"></i>',
            onConfirm: async () => {
                const fd = new FormData();
                fd.append('action', 'admin_end');
                fd.append('session_id', id);
                fd.append('csrf_token', window.CSRF_TOKEN || '');
                const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await r.json();
                if (window.Toast) Toast[d.status === 'success' ? 'success' : 'error'](d.message);
                loadSessions(curPage);
                loadLiveNow();
            }
        });
    }

    function deleteSession(id, title) {
        Modal.confirm({
            title: 'Delete Session Record',
            message: `Delete all data for <strong>${esc(title)}</strong>? Cannot be undone.`,
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            iconClass: 'modal-icon-danger',
            icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
            onConfirm: async () => {
                const fd = new FormData();
                fd.append('action', 'admin_delete');
                fd.append('session_id', id);
                fd.append('csrf_token', window.CSRF_TOKEN || '');
                const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
                    method: 'POST',
                    body: fd
                });
                const d = await r.json();
                if (window.Toast) Toast[d.status === 'success' ? 'success' : 'error'](d.message);
                loadSessions(curPage);
                loadLiveNow();
            }
        });
    }

    setInterval(() => {
        loadSessions(curPage);
        loadLiveNow();
    }, 30000);
    document.addEventListener('DOMContentLoaded', () => {
        loadSessions(1);
        loadLiveNow();
    });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>