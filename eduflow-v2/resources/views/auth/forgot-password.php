<?php
/** Password reset — three steps handled with AJAX. */
$asideHeading = 'Recover access to your account.';
$asideLead = 'We send a 6-digit code to your registered email address, then you choose a new password.';
?>
<h1>Reset your password</h1>
<p class="lead">We will email a 6-digit verification code.</p>

<?= App\Core\View::partial('partials/flash', ['flashMessages' => $flashMessages]) ?>

<div class="panel">
  <div class="panel-body">

    <!-- Step 1: email -->
    <div id="step-email">
      <div class="section-title">Step 1 · Your email address</div>
      <form id="form-otp" novalidate>
        <div class="field">
          <label class="field-label" for="otp-email">Email address <span class="req">*</span></label>
          <div class="input-icon">
            <?= icon('mail', 16) ?>
            <input class="input" type="email" id="otp-email" name="email" autocomplete="email"
                   placeholder="name@example.com" data-rules="required|email" data-label="Email address" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= icon('send', 15) ?> Send reset code</button>
      </form>
    </div>

    <!-- Step 2: OTP -->
    <div id="step-code" class="hidden">
      <div class="section-title">Step 2 · Verification code</div>
      <p class="small muted">
        Enter the 6-digit code sent to <strong id="otp-email-echo"></strong>.
        It expires in <?= (int) OTP_EXPIRY_MINUTES ?> minutes.
      </p>

      <div id="dev-otp" class="alert alert-info hidden mb-12">
        <?= icon('info', 17) ?>
        <div>Development mode — the code could not be emailed. Use: <strong id="dev-otp-value" class="mono"></strong></div>
      </div>

      <form id="form-verify" novalidate>
        <div class="field">
          <label class="field-label" for="otp-value">Verification code <span class="req">*</span></label>
          <input class="input mono" type="text" id="otp-value" name="otp" inputmode="numeric" maxlength="6"
                 placeholder="000000" style="letter-spacing:6px;text-align:center;font-size:1.125rem"
                 data-rules="required" data-label="Code" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= icon('check', 15) ?> Verify code</button>
      </form>

      <button type="button" class="btn btn-link mt-12" id="back-to-email">Use a different email address</button>
    </div>

    <!-- Step 3: new password -->
    <div id="step-password" class="hidden">
      <div class="section-title">Step 3 · New password</div>
      <form id="form-reset" novalidate>
        <input type="hidden" id="reset-token" name="reset_token">
        <div class="field">
          <label class="field-label" for="new-password">New password <span class="req">*</span></label>
          <div class="input-icon has-suffix">
            <?= icon('lock', 16) ?>
            <input class="input" type="password" id="new-password" name="password" autocomplete="new-password"
                   placeholder="At least 8 characters" data-rules="required|min:8" data-label="New password" required>
            <button type="button" class="suffix-btn" data-toggle-password="new-password" aria-label="Show password"><?= icon('eye', 16) ?></button>
          </div>
          <div class="strength" data-strength="new-password" data-level="0">
            <div class="strength-bars"><span></span><span></span><span></span><span></span></div>
            <div class="field-hint" data-strength-text></div>
          </div>
        </div>
        <div class="field">
          <label class="field-label" for="new-password-confirm">Confirm password <span class="req">*</span></label>
          <div class="input-icon has-suffix">
            <?= icon('lock', 16) ?>
            <input class="input" type="password" id="new-password-confirm" name="password_confirmation"
                   autocomplete="new-password" placeholder="Repeat password"
                   data-rules="required|same:password" data-label="Password confirmation" required>
            <button type="button" class="suffix-btn" data-toggle-password="new-password-confirm" aria-label="Show password"><?= icon('eye', 16) ?></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= icon('save', 15) ?> Update password</button>
      </form>
    </div>

  </div>
</div>

<div class="auth-foot">
  <div class="row-between">
    <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    <a href="<?= e(url('/register')) ?>">Create an account</a>
  </div>
</div>

<script>
(function () {
  var email = '';
  var steps = {
    email: document.getElementById('step-email'),
    code: document.getElementById('step-code'),
    password: document.getElementById('step-password')
  };

  function show(step) {
    Object.keys(steps).forEach(function (key) { steps[key].classList.toggle('hidden', key !== step); });
  }

  document.getElementById('form-otp').addEventListener('submit', function (event) {
    event.preventDefault();
    var input = document.getElementById('otp-email');
    if (!EF.form.validateField(input)) return;
    email = input.value.trim();

    EF.api.submit('/forgot-password/send', { email: email }, {
      button: this.querySelector('[type="submit"]'),
      reload: false,
      onSuccess: function (response) {
        document.getElementById('otp-email-echo').textContent = email;
        var dev = response.data && response.data.dev_otp;
        if (dev) {
          document.getElementById('dev-otp-value').textContent = dev;
          document.getElementById('dev-otp').classList.remove('hidden');
        }
        show('code');
        document.getElementById('otp-value').focus();
      }
    });
  });

  document.getElementById('form-verify').addEventListener('submit', function (event) {
    event.preventDefault();
    var input = document.getElementById('otp-value');
    if (input.value.trim().length !== 6) {
      EF.form.markError(input, 'Enter the 6-digit code.');
      return;
    }

    EF.api.submit('/forgot-password/verify', { email: email, otp: input.value.trim() }, {
      button: this.querySelector('[type="submit"]'),
      reload: false,
      onSuccess: function (response) {
        document.getElementById('reset-token').value = response.data.reset_token;
        show('password');
        document.getElementById('new-password').focus();
      }
    });
  });

  document.getElementById('form-reset').addEventListener('submit', function (event) {
    event.preventDefault();
    if (!EF.form.validateForm(this)) return;

    EF.api.submit('/forgot-password/reset', {
      reset_token: document.getElementById('reset-token').value,
      password: document.getElementById('new-password').value,
      password_confirmation: document.getElementById('new-password-confirm').value
    }, {
      button: this.querySelector('button[type="submit"]'),
      reload: false,
      onSuccess: function () {
        setTimeout(function () { window.location.href = EF.util.url('/login'); }, 900);
      }
    });
  });

  document.getElementById('back-to-email').addEventListener('click', function () { show('email'); });
})();
</script>
