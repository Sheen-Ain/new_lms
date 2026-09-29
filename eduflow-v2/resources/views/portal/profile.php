<?php
$user = $user ?? $currentUser ?? [];
$roles = $user['roles'] ?? [];
$gender = $user['gender'] ?? '';
$theme = $user['theme_preference'] ?? 'system';
?>
<section class="page-head">
  <div><h1>My profile</h1><p class="sub">Account details, security, and preferences.</p></div>
</section>

<div class="split">
  <section class="panel">
    <div class="panel-head"><h2>Personal details</h2></div>
    <div class="panel-note">
      <div class="row-center gap-12" style="justify-content:flex-start">
        <div class="profile-avatar"><?= user_avatar($user, 64) ?></div>
        <div><strong><?= e($user['full_name'] ?? '') ?></strong><div class="muted small"><?= e($user['email'] ?? '') ?></div></div>
      </div>
      <form id="profile-avatar-form" class="form mt-16" enctype="multipart/form-data">
        <label class="field-label" for="profile-avatar-file">Profile photo</label>
        <div class="row-center gap-10" style="justify-content:flex-start">
          <input class="input" type="file" id="profile-avatar-file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
          <button class="btn btn-sm" type="submit">Upload</button>
        </div>
        <div class="field-hint">JPG, PNG, or WebP image.</div>
      </form>
    </div>
    <form id="profile-details-form" class="form panel-note">
      <label class="field"><span class="field-label" for="profile-name">Full name</span>
        <input class="input" id="profile-name" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required maxlength="150"></label>
      <label class="field"><span class="field-label" for="profile-phone">Phone</span>
        <input class="input" id="profile-phone" name="phone" type="tel" value="<?= e($user['phone'] ?? '') ?>" maxlength="30"></label>
      <label class="field"><span class="field-label" for="profile-gender">Gender</span>
        <select class="select" id="profile-gender" name="gender" <?= count($roles) === 1 && ($roles[0] ?? '') === 'student' ? 'disabled' : '' ?>>
          <option value="">Prefer not to say</option>
          <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $gender === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span class="field-label" for="profile-bio">About</span>
        <textarea class="textarea" id="profile-bio" name="bio" rows="4" maxlength="1000"><?= e($user['bio'] ?? '') ?></textarea></label>
      <div class="row-center" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Save details</button></div>
    </form>
  </section>

  <div class="stack gap-16">
    <section class="panel">
      <div class="panel-head"><h2>Appearance</h2></div>
      <div class="panel-note">
        <label class="field"><span class="field-label" for="profile-theme">Theme</span>
          <select class="select" id="profile-theme">
            <option value="system"<?= $theme === 'system' ? ' selected' : '' ?>>Use device setting</option>
            <option value="light"<?= $theme === 'light' ? ' selected' : '' ?>>Light</option>
            <option value="dark"<?= $theme === 'dark' ? ' selected' : '' ?>>Dark</option>
          </select>
        </label>
      </div>
    </section>

    <?php if (count($roles) > 1): ?>
      <section class="panel" id="role">
        <div class="panel-head"><h2>Active role</h2></div>
        <div class="panel-note">
          <label class="field"><span class="field-label" for="profile-role">Switch portal</span>
            <select class="select" id="profile-role">
              <?php foreach ($roles as $role): ?>
                <option value="<?= e($role) ?>"<?= ($user['current_role'] ?? '') === $role ? ' selected' : '' ?>><?= e(ucfirst($role)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2>Change password</h2></div>
      <form id="profile-password-form" class="form panel-note">
        <label class="field"><span class="field-label" for="current-password">Current password</span>
          <input class="input" id="current-password" name="current_password" type="password" autocomplete="current-password" required></label>
        <label class="field"><span class="field-label" for="new-password">New password</span>
          <input class="input" id="new-password" name="new_password" type="password" autocomplete="new-password" required></label>
        <label class="field"><span class="field-label" for="confirm-password">Confirm new password</span>
          <input class="input" id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required></label>
        <div class="row-center" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Update password</button></div>
      </form>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var details = document.getElementById('profile-details-form');
  var password = document.getElementById('profile-password-form');
  var avatar = document.getElementById('profile-avatar-form');
  var theme = document.getElementById('profile-theme');
  var role = document.getElementById('profile-role');

  function submitForm(form, endpoint, onSuccess) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var button = form.querySelector('button[type="submit"]');
      EF.api.submit(endpoint, new FormData(form), {
        form: form,
        button: button,
        reload: false,
        onSuccess: onSuccess || function () {}
      });
    });
  }

  submitForm(details, '/profile/update');
  submitForm(password, '/profile/password', function () { password.reset(); });
  submitForm(avatar, '/profile/avatar', function (response) {
    var preview = document.querySelector('.profile-avatar');
    if (preview && response.data && response.data.url) {
      preview.innerHTML = '<span class="avatar" style="width:64px;height:64px"><img src="' + EF.util.escape(response.data.url) + '" alt="Profile photo"></span>';
    }
    avatar.reset();
  });

  if (theme) {
    theme.addEventListener('change', function () {
      var mode = theme.value;
      var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme-mode', mode);
      try { localStorage.setItem('eduflow-theme', mode); } catch (error) {}
      EF.api.submit('/profile/theme', { theme: mode }, { reload: false, silent: true });
    });
  }

  if (role) {
    role.addEventListener('change', function () {
      EF.api.submit('/profile/switch-role', { role: role.value }, { redirect: true });
    });
  }
});
</script>