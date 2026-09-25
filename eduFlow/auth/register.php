<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
  header('Location: ' . BASE_PATH . '/' . ($_SESSION['role'] ?? 'student') . '/');
  exit;
}

$pageTitle = 'Create Account';
include __DIR__ . '/header.php';
?>

<div class="auth-page">
  <!-- Left panel -->
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
        <h1 class="auth-hero-title">Start your<br>journey today.</h1>
        <p class="auth-hero-sub">Register once with your CNIC and get a unique Student ID to access courses, assignments and progress — all in one place.</p>
      </div>
      <div style="margin-top:8px;">
        <?php foreach (
          [
            ['fingerprint', 'One account per CNIC — fair &amp; secure'],
            ['hash',        'Unique 5-digit Student ID generated instantly'],
            ['mail',        'Credentials emailed — download for safekeeping'],
            ['shield-check', 'Role-based access for safe collaboration'],
          ] as [$ico, $txt]
        ): ?>
          <div class="auth-feature">
            <div class="auth-feature-icon"><i data-lucide="<?= $ico ?>" style="width:18px;height:18px;color:rgba(255,255,255,.8);"></i></div>
            <span class="auth-feature-text"><?= $txt ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right form -->
  <div class="auth-panel-right">
    <div class="auth-form-wrap">

      <!-- ══ STEP 1: Details ══ -->
      <div class="auth-step active" id="step-form">
        <div class="auth-form-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="22" y1="11" x2="16" y2="11" />
          </svg>
        </div>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-sub">Fill in your details to register</p>

        <div class="auth-alert auth-alert-error" id="reg-err" style="display:none;"></div>

        <div style="margin-bottom:15px;">
          <label class="auth-label" for="r-name">Full Name</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg></span>
            <input type="text" id="r-name" class="auth-input" placeholder="Muhammad Ali" autocomplete="name">
          </div>
          <div class="auth-field-err" id="err-name"></div>
        </div>

        <div style="margin-bottom:15px;">
          <label class="auth-label" for="r-email">Email Address</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg></span>
            <input type="email" id="r-email" class="auth-input" placeholder="you@example.com" autocomplete="email">
          </div>
          <div class="auth-field-err" id="err-email"></div>
        </div>

        <div style="margin-bottom:15px;">
          <label class="auth-label" for="r-cnic">Pakistani CNIC</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <line x1="2" y1="10" x2="22" y2="10" />
              </svg></span>
            <input type="text" id="r-cnic" class="auth-input" placeholder="XXXXX-XXXXXXX-X" maxlength="15" inputmode="numeric" autocomplete="off">
          </div>
          <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Format: 42101-1234567-8 · One account per CNIC</div>
          <div class="auth-field-err" id="err-cnic"></div>
        </div>

        <div style="margin-bottom:15px;">
          <label class="auth-label" for="r-gender">Gender</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg></span>
            <select id="r-gender" class="auth-input" style="padding-left:38px;">
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">Controls chat permissions (cannot be changed later)</div>
          <div class="auth-field-err" id="err-gender"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px;">
          <div>
            <label class="auth-label" for="r-pass">Password</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg></span>
              <input type="password" id="r-pass" class="auth-input has-right" placeholder="Min. 8 chars" autocomplete="new-password">
              <button type="button" class="auth-input-eye" id="eye-rp" onclick="toggleEye('r-pass','eye-rp')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </div>
            <div class="pwd-strength" style="margin-top:6px;">
              <div class="pwd-strength-fill" id="pwd-bar"></div>
            </div>
          </div>
          <div>
            <label class="auth-label" for="r-conf">Confirm</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg></span>
              <input type="password" id="r-conf" class="auth-input" placeholder="Repeat password" autocomplete="new-password">
            </div>
            <div class="auth-field-err" id="err-pass"></div>
          </div>
        </div>

        <button class="auth-btn" id="reg-btn" onclick="submitRegister()">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="22" y1="11" x2="16" y2="11" />
          </svg>
          <span id="reg-btn-text">Create Account</span>
        </button>
        <p class="auth-footer-text">Already have an account? <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-link">Sign in</a></p>
      </div>

      <!-- ══ STEP 2: Credentials ══ -->
      <div class="auth-step" id="step-done">
        <div style="text-align:center;margin-bottom:24px;">
          <div style="width:72px;height:72px;background:rgba(16,185,129,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
          <h1 class="auth-title" style="color:var(--success);">Account Created!</h1>
          <p class="auth-sub">Save your credentials below — you'll need them to log in</p>
        </div>

        <div class="cred-card">
          <div class="cred-row">
            <span class="cred-lbl">Student ID</span>
            <span class="cred-val" id="cred-id">—</span>
          </div>
          <div class="cred-row">
            <span class="cred-lbl">Full Name</span>
            <span style="font-weight:600;font-size:.9rem;" id="cred-name">—</span>
          </div>
          <div class="cred-row">
            <span class="cred-lbl">Email</span>
            <span style="font-weight:600;font-size:.9rem;" id="cred-email">—</span>
          </div>
        </div>

        <div class="auth-alert auth-alert-info">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          Check your email and click the verification link before logging in.
        </div>

        <button class="auth-btn" onclick="downloadCreds()" style="margin-bottom:12px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
          </svg>
          Download Credentials
        </button>
        <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-btn" style="background:var(--bg);color:var(--primary);border:1.5px solid var(--border);box-shadow:none;text-decoration:none;">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
          </svg>
          Go to Sign In
        </a>
      </div>

    </div>
  </div>
</div>

<script>
  let _creds = {};

  function showStep(id) {
    document.querySelectorAll('.auth-step').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    if (window.lucide) lucide.createIcons();
  }

  function showErr(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
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

  // CNIC auto-format
  document.getElementById('r-cnic')?.addEventListener('input', function() {
    let raw = this.value.replace(/\D/g, '').slice(0, 13);
    if (raw.length > 12) raw = raw.slice(0, 5) + '-' + raw.slice(5, 12) + '-' + raw.slice(12);
    else if (raw.length > 5) raw = raw.slice(0, 5) + '-' + raw.slice(5);
    if (this.value !== raw) this.value = raw;
  });

  document.getElementById('r-pass')?.addEventListener('input', function() {
    const s = checkPwdStrength(this.value);
    const bar = document.getElementById('pwd-bar');
    if (bar) {
      bar.style.width = s.width;
      bar.style.background = s.color;
    }
  });

  async function submitRegister() {
    // Clear errors
    ['reg-err', 'err-name', 'err-email', 'err-cnic', 'err-gender', 'err-pass'].forEach(hideErr);

    const name = document.getElementById('r-name').value.trim();
    const email = document.getElementById('r-email').value.trim();
    const cnic = document.getElementById('r-cnic').value.trim();
    const gender = document.getElementById('r-gender').value;
    const pass = document.getElementById('r-pass').value;
    const conf = document.getElementById('r-conf').value;

    let ok = true;
    if (!name) {
      document.getElementById('err-name').textContent = 'Full name is required';
      ok = false;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      document.getElementById('err-email').textContent = 'Valid email required';
      ok = false;
    }
    if (!cnic || cnic.replace(/\D/g, '').length !== 13) {
      document.getElementById('err-cnic').textContent = 'Valid 13-digit CNIC required';
      ok = false;
    }
    if (!gender) {
      document.getElementById('err-gender').textContent = 'Please select gender';
      ok = false;
    }
    if (pass.length < 8) {
      document.getElementById('err-pass').textContent = 'Minimum 8 characters';
      ok = false;
    } else if (pass !== conf) {
      document.getElementById('err-pass').textContent = 'Passwords do not match';
      ok = false;
    }
    if (!ok) return;

    const btn = document.getElementById('reg-btn');
    btn.disabled = true;
    document.getElementById('reg-btn-text').textContent = 'Creating account…';
    btn.insertAdjacentHTML('afterbegin', '<span class="auth-spinner" style="margin-right:8px;flex-shrink:0;"></span>');

    try {
      const fd = new FormData();
      fd.append('action', 'register');
      fd.append('full_name', name);
      fd.append('email', email);
      fd.append('cnic', cnic);
      fd.append('gender', gender);
      fd.append('password', pass);
      fd.append('confirm_password', conf);
      fd.append('csrf_token', window.CSRF_TOKEN);

      const res = await fetch(window.LMS_BASE + '/ajax/auth.ajax.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      const d = await res.json();

      btn.disabled = false;
      btn.querySelector('.auth-spinner')?.remove();
      document.getElementById('reg-btn-text').textContent = 'Create Account';

      if (d.status !== 'success') {
        showErr('reg-err', d.message);
        return;
      }

      _creds = d.data || {};
      document.getElementById('cred-id').textContent = _creds.student_id || '—';
      document.getElementById('cred-name').textContent = _creds.full_name || name;
      document.getElementById('cred-email').textContent = _creds.email || email;
      showStep('step-done');

    } catch (e) {
      btn.disabled = false;
      btn.querySelector('.auth-spinner')?.remove();
      document.getElementById('reg-btn-text').textContent = 'Create Account';
      showErr('reg-err', 'Network error. Please try again.');
    }
  }

  function downloadCreds() {
    const txt = [
      'EduFlow LMS — Login Credentials',
      '================================',
      'Student ID : ' + (_creds.student_id || '—'),
      'Full Name  : ' + (_creds.full_name || '—'),
      'Email      : ' + (_creds.email || '—'),
      '',
      'Login URL  : <?= BASE_PATH ?>/auth/login.php',
      '',
      'Keep this file secure. Do not share your password.',
    ].join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(txt);
    a.download = 'eduflow-credentials.txt';
    a.click();
  }

  document.addEventListener('DOMContentLoaded', () => {
    initAurora('auth-aurora');
    document.getElementById('r-name')?.focus();
  });
</script>

<?php include __DIR__ . '/footer.php'; ?>