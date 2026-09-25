<?php
/* ──────────────────────────────────────────────────────────────
   auth/forgot-password.php
   3-step flow: Email → OTP → New Password
   FIX: properly loads config + helpers + sets window.LMS_BASE
────────────────────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
  header('Location: ' . BASE_PATH . '/' . ($_SESSION['role'] ?? 'student') . '/');
  exit;
}

$pageTitle = 'Reset Password';
include __DIR__ . '/header.php';
?>

<div class="auth-page">
  <!-- ── Left panel ── -->
  <div class="auth-panel-left">
    <canvas id="auth-aurora" class="auth-aurora"></canvas>
    <div class="auth-panel-content">
      <div class="auth-brand">
        <div class="auth-brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
            <path d="M6 12v5c3 3 9 3 12 0v-5" />
          </svg>
        </div>
        <div>
          <div class="auth-brand-name">EduFlow</div>
          <div class="auth-brand-sub">Learning Management</div>
        </div>
      </div>
      <div>
        <h1 class="auth-hero-title">Forgot your<br>password?</h1>
        <p class="auth-hero-sub">No worries. Enter your email — we'll send a 6-digit code to verify it's you, then you can set a new password.</p>
      </div>
      <div style="margin-top:40px;padding:20px;background:rgba(255,255,255,.06);border-radius:14px;border:1px solid rgba(255,255,255,.1);">
        <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-bottom:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">How it works</div>
        <?php foreach (
          [
            ['mail', 'Enter your registered email address'],
            ['key', 'Receive a 6-digit verification code'],
            ['shield-check', 'Enter the code to verify identity'],
            ['lock', 'Set your new secure password'],
          ] as [$icon, $txt]
        ): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);">
            <div style="width:32px;height:32px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i data-lucide="<?= $icon ?>" style="width:15px;height:15px;color:rgba(255,255,255,.7);"></i>
            </div>
            <span style="font-size:.83rem;color:rgba(255,255,255,.6);"><?= $txt ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Right form ── -->
  <div class="auth-panel-right">
    <div class="auth-form-wrap">

      <!-- ═══ STEP 1: Email ═══ -->
      <div class="auth-step active" id="step-email">
        <div class="auth-form-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-sub">We'll send a 6-digit code to your email</p>
        <div class="auth-alert auth-alert-error" id="email-err" style="display:none;"></div>
        <div style="margin-bottom:20px;">
          <label class="auth-label" for="fp-email">Email Address</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg></span>
            <input type="email" id="fp-email" class="auth-input" placeholder="you@example.com" autocomplete="email" autofocus>
          </div>
        </div>
        <button class="auth-btn" id="send-btn" onclick="sendOtp()">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
          <span id="send-btn-text">Send Reset Code</span>
        </button>
        <p class="auth-footer-text">Remembered it? <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-link">Sign in</a></p>
      </div>

      <!-- ═══ STEP 2: OTP ═══ -->
      <div class="auth-step" id="step-otp">
        <div class="auth-form-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
          </svg>
        </div>
        <h1 class="auth-title">Check your email</h1>
        <p class="auth-sub">6-digit code sent to<br><strong id="otp-dest" style="color:var(--primary);"></strong></p>
        <div class="auth-alert auth-alert-error" id="otp-err" style="display:none;"></div>
        <!-- Dev OTP fallback box -->
        <div id="dev-otp-box" class="dev-otp-box" style="display:none;">
          <span style="font-size:.78rem;color:var(--text-muted);">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:inline;">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            Dev mode — mail not configured, use this code:
          </span>
          <span class="dev-otp-val" id="dev-otp-val"></span>
        </div>
        <div class="otp-row" id="otp-inputs">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input class="otp-digit" maxlength="1" inputmode="numeric" pattern="\d" <?= $i === 0 ? 'autocomplete="one-time-code"' : '' ?>>
          <?php endfor; ?>
        </div>
        <!-- Countdown -->
        <div style="text-align:center;margin-bottom:20px;">
          <span id="cd-text" style="font-size:.82rem;color:var(--text-muted);">Code expires in </span>
          <span id="cd-timer" style="font-size:.82rem;font-weight:700;color:var(--primary);font-family:'JetBrains Mono',monospace;"></span>
        </div>
        <button class="auth-btn" id="verify-btn" onclick="verifyOtp()" disabled style="opacity:.6;">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
          <span id="verify-btn-text">Verify Code</span>
        </button>
        <p class="auth-footer-text">
          Didn't receive it?
          <button onclick="resendOtp()" id="resend-btn" style="background:none;border:none;color:var(--primary);font-weight:600;cursor:pointer;font-size:.84rem;padding:0;">Resend</button>
          &nbsp;·&nbsp;
          <button onclick="showStep('step-email')" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:.84rem;padding:0;">Different email</button>
        </p>
      </div>

      <!-- ═══ STEP 3: New Password ═══ -->
      <div class="auth-step" id="step-reset">
        <div class="auth-form-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <h1 class="auth-title">New password</h1>
        <p class="auth-sub">Must be at least 8 characters</p>
        <div class="auth-alert auth-alert-error" id="reset-err" style="display:none;"></div>
        <div style="margin-bottom:18px;">
          <label class="auth-label" for="np">New Password</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg></span>
            <input type="password" id="np" class="auth-input has-right" placeholder="New password" autocomplete="new-password">
            <button type="button" class="auth-input-eye" id="eye-np" onclick="toggleEye('np','eye-np')">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
          <div class="pwd-strength" style="margin-top:8px;">
            <div class="pwd-strength-fill" id="pwd-bar"></div>
          </div>
          <div id="pwd-label" style="font-size:.72rem;margin-top:4px;color:var(--text-muted);min-height:16px;"></div>
        </div>
        <div style="margin-bottom:24px;">
          <label class="auth-label" for="cp">Confirm Password</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg></span>
            <input type="password" id="cp" class="auth-input has-right" placeholder="Confirm password" autocomplete="new-password">
            <button type="button" class="auth-input-eye" id="eye-cp" onclick="toggleEye('cp','eye-cp')">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
          <div class="auth-field-err" id="err-cp"></div>
        </div>
        <button class="auth-btn" id="reset-btn" onclick="submitReset()">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
          <span id="reset-btn-text">Set New Password</span>
        </button>
      </div>

      <!-- ═══ STEP 4: Done ═══ -->
      <div class="auth-step" id="step-done">
        <div style="text-align:center;padding:20px 0;">
          <div style="width:72px;height:72px;background:rgba(16,185,129,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
          <h1 class="auth-title" style="color:var(--success);">Password reset!</h1>
          <p class="auth-sub">Your password has been updated successfully. You can now sign in with your new credentials.</p>
          <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-btn" style="margin-top:10px;text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
              <polyline points="10 17 15 12 10 7" />
              <line x1="15" y1="12" x2="3" y2="12" />
            </svg>
            Sign In Now
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  let currentEmail = '',
    resetToken = '',
    cdInterval = null;

  /* ── Step switcher ─────────────────────────────────── */
  function showStep(id) {
    document.querySelectorAll('.auth-step').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    // Re-init lucide icons in new step
    if (window.lucide) lucide.createIcons();
  }

  /* ── Alert helpers ─────────────────────────────────── */
  function showErr(id, msg) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
    el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msg}`;
  }

  function hideErr(id) {
    const el = document.getElementById(id);
    if (el) {
      el.style.display = 'none';
      el.innerHTML = '';
    }
  }

  /* ── Countdown timer ───────────────────────────────── */
  function startCountdown(secs) {
    if (cdInterval) clearInterval(cdInterval);
    let remaining = secs || 300;
    const cdTimer = document.getElementById('cd-timer');
    const cdText = document.getElementById('cd-text');

    function tick() {
      const m = Math.floor(remaining / 60),
        s = remaining % 60;
      if (cdTimer) cdTimer.textContent = `${m}:${String(s).padStart(2,'0')}`;
      if (remaining <= 0) {
        clearInterval(cdInterval);
        if (cdText) cdText.textContent = 'Code expired. ';
        if (cdTimer) cdTimer.textContent = '';
        document.getElementById('verify-btn').disabled = true;
      }
      remaining--;
    }
    tick();
    cdInterval = setInterval(tick, 1000);
  }

  /* ── Loading state ─────────────────────────────────── */
  function setLoading(btnId, textId, loading, text) {
    const btn = document.getElementById(btnId);
    const txt = document.getElementById(textId);
    btn.disabled = loading;
    if (loading) {
      btn.style.opacity = '.7';
      if (txt) txt.textContent = text;
      if (!btn.querySelector('.auth-spinner'))
        btn.insertAdjacentHTML('afterbegin', '<span class="auth-spinner" style="margin-right:8px;flex-shrink:0;"></span>');
    } else {
      btn.style.opacity = '';
      if (txt) txt.textContent = text;
      btn.querySelector('.auth-spinner')?.remove();
    }
  }

  /* ── STEP 1: Send OTP ──────────────────────────────── */
  async function sendOtp() {
    hideErr('email-err');
    const email = document.getElementById('fp-email').value.trim();
    if (!email) {
      showErr('email-err', 'Please enter your email address.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showErr('email-err', 'Enter a valid email address.');
      return;
    }

    setLoading('send-btn', 'send-btn-text', true, 'Sending…');
    try {
      const fd = new FormData();
      fd.append('action', 'send_otp');
      fd.append('email', email);
      const res = await fetch(window.LMS_BASE + '/ajax/auth.ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const d = await res.json();
      setLoading('send-btn', 'send-btn-text', false, 'Send Reset Code');

      if (d.status !== 'success') {
        showErr('email-err', d.message);
        return;
      }

      currentEmail = email;
      document.getElementById('otp-dest').textContent = email;

      if (d.data && d.data.dev_otp) {
        document.getElementById('dev-otp-val').textContent = d.data.dev_otp;
        document.getElementById('dev-otp-box').style.display = 'block';
      } else {
        document.getElementById('dev-otp-box').style.display = 'none';
      }

      showStep('step-otp');
      startCountdown(300);
      document.querySelector('.otp-digit')?.focus();

    } catch (e) {
      setLoading('send-btn', 'send-btn-text', false, 'Send Reset Code');
      showErr('email-err', 'Network error. Please try again.');
    }
  }

  /* ── STEP 2: OTP boxes ─────────────────────────────── */
  const digits = () => [...document.querySelectorAll('.otp-digit')];

  function getOtp() {
    return digits().map(d => d.value).join('');
  }

  function syncVerifyBtn() {
    const ok = /^\d{6}$/.test(getOtp());
    const btn = document.getElementById('verify-btn');
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.6';
  }

  document.querySelectorAll('.otp-digit').forEach((box, i, all) => {
    box.addEventListener('input', e => {
      box.value = e.target.value.replace(/\D/g, '').slice(-1);
      box.classList.toggle('filled', !!box.value);
      box.classList.remove('error');
      syncVerifyBtn();
      if (box.value && i < 5) all[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (!box.value && i > 0) {
          all[i - 1].focus();
          all[i - 1].value = '';
          syncVerifyBtn();
        }
        box.classList.remove('error');
      }
      if (e.key === 'ArrowLeft' && i > 0) all[i - 1].focus();
      if (e.key === 'ArrowRight' && i < 5) all[i + 1].focus();
      if (e.key === 'Enter') verifyOtp();
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      paste.split('').forEach((ch, j) => {
        if (all[j]) {
          all[j].value = ch;
          all[j].classList.add('filled');
        }
      });
      syncVerifyBtn();
      all[Math.min(paste.length, 5)].focus();
    });
  });

  async function verifyOtp() {
    hideErr('otp-err');
    const otp = getOtp();
    if (!/^\d{6}$/.test(otp)) {
      showErr('otp-err', 'Enter the 6-digit code.');
      return;
    }

    setLoading('verify-btn', 'verify-btn-text', true, 'Verifying…');
    try {
      const fd = new FormData();
      fd.append('action', 'verify_otp');
      fd.append('email', currentEmail);
      fd.append('otp', otp);
      const res = await fetch(window.LMS_BASE + '/ajax/auth.ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const d = await res.json();
      setLoading('verify-btn', 'verify-btn-text', false, 'Verify Code');

      if (d.status !== 'success') {
        digits().forEach(b => {
          b.classList.add('error');
          b.value = '';
        });
        syncVerifyBtn();
        digits()[0]?.focus();
        showErr('otp-err', d.message);
        return;
      }
      resetToken = d.data.reset_token;
      if (cdInterval) clearInterval(cdInterval);
      showStep('step-reset');
      document.getElementById('np')?.focus();
    } catch (e) {
      setLoading('verify-btn', 'verify-btn-text', false, 'Verify Code');
      showErr('otp-err', 'Network error. Please try again.');
    }
  }

  async function resendOtp() {
    document.getElementById('fp-email').value = currentEmail;
    showStep('step-email');
    await sendOtp();
  }

  /* ── STEP 3: Reset password ────────────────────────── */
  document.getElementById('np')?.addEventListener('input', function() {
    const s = checkPwdStrength(this.value);
    const bar = document.getElementById('pwd-bar');
    const lbl = document.getElementById('pwd-label');
    if (bar) {
      bar.style.width = s.width;
      bar.style.background = s.color;
    }
    if (lbl) {
      lbl.textContent = s.label;
      lbl.style.color = s.color;
    }
  });

  async function submitReset() {
    hideErr('reset-err');
    document.getElementById('err-cp').textContent = '';
    const pass = document.getElementById('np').value;
    const conf = document.getElementById('cp').value;

    if (pass.length < 8) {
      showErr('reset-err', 'Password must be at least 8 characters.');
      return;
    }
    if (pass !== conf) {
      document.getElementById('err-cp').textContent = 'Passwords do not match';
      return;
    }

    setLoading('reset-btn', 'reset-btn-text', true, 'Saving…');
    try {
      const fd = new FormData();
      fd.append('action', 'reset_password');
      fd.append('email', currentEmail);
      fd.append('reset_token', resetToken);
      fd.append('new_password', pass);
      fd.append('confirm_password', conf);
      const res = await fetch(window.LMS_BASE + '/ajax/auth.ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const d = await res.json();
      setLoading('reset-btn', 'reset-btn-text', false, 'Set New Password');

      if (d.status !== 'success') {
        showErr('reset-err', d.message);
        return;
      }
      showStep('step-done');
    } catch (e) {
      setLoading('reset-btn', 'reset-btn-text', false, 'Set New Password');
      showErr('reset-err', 'Network error. Please try again.');
    }
  }

  /* ── On load ───────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initAurora('auth-aurora');
    document.getElementById('fp-email')?.addEventListener('keydown', e => {
      if (e.key === 'Enter') sendOtp();
    });
  });
</script>

<?php include __DIR__ . '/footer.php'; ?>