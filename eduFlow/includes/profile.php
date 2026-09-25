<?php
$requiredRole = null; // Any logged-in user
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'My Profile';
$breadcrumbs = [['label' => 'Profile']];
$uid = $currentUser['id'];

// Get full user with roles
$r = $conn->prepare("SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ',') as all_roles FROM users u LEFT JOIN user_roles ur ON u.id=ur.user_id LEFT JOIN roles r ON ur.role_id=r.id WHERE u.id=? GROUP BY u.id");
$r->bind_param('i', $uid);
$r->execute();
$user = $r->get_result()->fetch_assoc();
$r->close();

// Stats based on role
$stats = [];
$role = $_SESSION['role'];
if ($role === 'admin') {
  $stats['total_users'] = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
  $stats['total_courses'] = (int)$conn->query("SELECT COUNT(*) as c FROM courses")->fetch_assoc()['c'];
} elseif ($role === 'teacher') {
  $r = $conn->prepare("SELECT COUNT(DISTINCT batch_id) as batches, 0 as students FROM batch_teachers WHERE teacher_id=?");
  $r->bind_param('i', $uid);
  $r->execute();
  $s = $r->get_result()->fetch_assoc();
  $r->close();
  $stats['batches'] = $s['batches'];
  $r = $conn->prepare("SELECT COUNT(*) as cnt FROM assignments WHERE created_by=?");
  $r->bind_param('i', $uid);
  $r->execute();
  $stats['assignments'] = (int)$r->get_result()->fetch_assoc()['cnt'];
  $r->close();
} elseif ($role === 'student') {
  $r = $conn->prepare("SELECT COUNT(*) as cnt FROM batch_students WHERE student_id=?");
  $r->bind_param('i', $uid);
  $r->execute();
  $stats['batches'] = (int)$r->get_result()->fetch_assoc()['cnt'];
  $r->close();
  $r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions WHERE student_id=?");
  $r->bind_param('i', $uid);
  $r->execute();
  $stats['submissions'] = (int)$r->get_result()->fetch_assoc()['cnt'];
  $r->close();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div style="max-width:900px;margin:0 auto;">

        <!-- Profile Header Card -->
        <div class="card" style="margin-bottom:20px;overflow:hidden;">
          <div style="background:linear-gradient(135deg,#1e1b4b,#4c1d95,#6d28d9);height:120px;position:relative;">
            <div style="position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2220%22 cy=%2280%22 r=%2240%22 fill=%22rgba(255,255,255,0.03)%22/><circle cx=%2280%22 cy=%2220%22 r=%2260%22 fill=%22rgba(255,255,255,0.03)%22/></svg>');"></div>
          </div>
          <div style="padding:0 24px 24px;position:relative;">
            <div style="display:flex;align-items:flex-end;gap:16px;margin-top:-40px;margin-bottom:16px;flex-wrap:wrap;">
              <!-- Avatar -->
              <div style="position:relative;flex-shrink:0;">
                <div id="avatar-ring" style="width:80px;height:80px;border-radius:50%;border:4px solid var(--bg-card);background:var(--primary);display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;" onclick="document.getElementById('avatar-input').click()">
                  <?php if ($user['profile_picture']): ?>
                    <img id="avatar-img" src="<?= BASE_PATH ?>/uploads/profiles/<?= e($user['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar">
                  <?php else:
                    $initials = implode('', array_map(fn($w) => $w[0], explode(' ', $user['full_name'])));
                    $initials = strtoupper(substr($initials, 0, 2));
                  ?>
                    <div id="avatar-initials" style="color:#fff;font-weight:800;font-size:1.4rem;font-family:Poppins,sans-serif;"><?= e($initials) ?></div>
                  <?php endif; ?>
                </div>
                <div style="position:absolute;bottom:2px;right:2px;width:26px;height:26px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg-card);" onclick="document.getElementById('avatar-input').click()">
                  <i data-lucide="camera" style="width:12px;height:12px;color:#fff;"></i>
                </div>
                <input type="file" id="avatar-input" accept="image/*" style="display:none;" onchange="uploadAvatar(this)">
              </div>
              <div style="flex:1;min-width:200px;">
                <h2 style="font-family:Poppins,sans-serif;font-size:1.2rem;font-weight:800;margin:0;"><?= e($user['full_name']) ?></h2>
                <div style="color:var(--text-muted);font-size:0.85rem;margin-top:2px;"><?= e($user['email']) ?></div>
                <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
                  <?php foreach (explode(',', $user['all_roles'] ?? '') as $r): if (!trim($r)) continue; ?>
                    <?php $roleName = trim($r); ?>
                    <span class="badge <?= $roleName === 'admin' ? 'badge-danger' : ($roleName === 'teacher' ? 'badge-info' : 'badge-success') ?>">
                      <?= e($roleName) ?>
                    </span>
                  <?php endforeach; ?>
                  <?php if ($user['is_online']): ?><span class="badge badge-success"><span class="badge-dot" style="background:#fff;"></span>Online</span><?php endif; ?>
                </div>
              </div>
              <div style="display:flex;gap:10px;">
                <?php if ($role === 'admin'): ?>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);"><?= $stats['total_users'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Users</div>
                  </div>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--success);"><?= $stats['total_courses'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Courses</div>
                  </div>
                <?php elseif ($role === 'teacher'): ?>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);"><?= $stats['batches'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Batches</div>
                  </div>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--warning);"><?= $stats['assignments'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Assignments</div>
                  </div>
                <?php else: ?>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--primary);"><?= $stats['batches'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Batches</div>
                  </div>
                  <div style="text-align:center;padding:10px 20px;background:var(--bg);border-radius:var(--radius);">
                    <div style="font-size:1.3rem;font-weight:800;color:var(--success);"><?= $stats['submissions'] ?? 0 ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Submissions</div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <!-- Edit Profile -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i data-lucide="user" style="width:16px;height:16px;"></i> Edit Profile</h3>
            </div>
            <form id="profile-form" onsubmit="saveProfile(event)">
              <div class="card-body">
                <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+1 234 567 8900"></div>
                <?php if ($role === 'student'): ?>
                  <!-- Gender is LOCKED for students — it was set at registration and controls chat permissions -->
                  <div class="form-group">
                    <label class="form-label">Gender</label>
                    <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg);border-radius:var(--radius);border:1.5px solid var(--border);">
                      <i data-lucide="<?= $user['gender'] === 'male' ? 'user' : ($user['gender'] === 'female' ? 'user' : 'help-circle') ?>" style="width:15px;height:15px;color:var(--primary);flex-shrink:0;"></i>
                      <span style="font-weight:600;font-size:0.875rem;"><?= $user['gender'] ? ucfirst(e($user['gender'])) : '<span style="color:var(--text-muted);">Not set</span>' ?></span>
                      <span style="margin-left:auto;display:inline-flex;align-items:center;gap:4px;font-size:0.72rem;color:var(--text-muted);background:var(--bg-hover);padding:2px 8px;border-radius:20px;">
                        <i data-lucide="lock" style="width:10px;height:10px;"></i> Locked
                      </span>
                    </div>
                    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:5px;display:flex;align-items:center;gap:4px;">
                      <i data-lucide="info" style="width:11px;height:11px;"></i>
                      Gender is set at registration and cannot be changed. Contact an admin if there's an error.
                    </div>
                  </div>
                <?php else: ?>
                  <div class="form-group"><label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                      <option value="">Prefer not to say</option>
                      <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                      <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                      <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                  </div>
                <?php endif; ?>
                <div class="form-group"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3" placeholder="Tell us about yourself…"><?= e($user['bio'] ?? '') ?></textarea></div>
              </div>
              <div style="padding:16px 20px;border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-primary" id="profile-btn"><i data-lucide="save" style="width:15px;height:15px;"></i><span class="btn-text"> Save Changes</span></button>
              </div>
            </form>
          </div>

          <!-- Change Password -->
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i data-lucide="lock" style="width:16px;height:16px;"></i> Change Password</h3>
            </div>
            <form id="password-form" onsubmit="changePassword(event)">
              <div class="card-body">
                <div class="form-group">
                  <label class="form-label">Current Password <span class="required">*</span></label>
                  <div style="position:relative;">
                    <input type="password" name="current_password" id="cur-pass" class="form-control" required placeholder="Your current password">
                    <button type="button" onclick="togglePwd('cur-pass',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);"><i data-lucide="eye" style="width:15px;height:15px;"></i></button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">New Password <span class="required">*</span></label>
                  <div style="position:relative;">
                    <input type="password" name="new_password" id="new-pass" class="form-control" required placeholder="Min. 8 characters" oninput="updateStrength(this.value)">
                    <button type="button" onclick="togglePwd('new-pass',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);"><i data-lucide="eye" style="width:15px;height:15px;"></i></button>
                  </div>
                  <div id="pwd-strength" style="margin-top:8px;display:none;">
                    <div style="height:4px;background:var(--bg-hover);border-radius:4px;overflow:hidden;">
                      <div id="pwd-bar" style="height:100%;border-radius:4px;transition:width 0.3s,background 0.3s;width:0%;"></div>
                    </div>
                    <div id="pwd-label" style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;"></div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm New Password <span class="required">*</span></label>
                  <input type="password" name="confirm_password" id="conf-pass" class="form-control" required placeholder="Repeat new password">
                </div>
              </div>
              <div style="padding:16px 20px;border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-primary" id="pwd-btn"><i data-lucide="shield" style="width:15px;height:15px;"></i><span class="btn-text"> Update Password</span></button>
              </div>
            </form>
          </div>
        </div>

        <!-- Account Info -->
        <div class="card" style="margin-top:20px;">
          <div class="card-header">
            <h3 class="card-title"><i data-lucide="info" style="width:16px;height:16px;"></i> Account Information</h3>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;padding:20px;">
            <?php
            $infoItems = [
              ['Member since', formatDate($user['created_at'])],
              ['Last seen', $user['last_seen'] ? timeAgo($user['last_seen']) : 'Never'],
              ['Current role', ucfirst($user['current_role'] ?? '—')],
              ['Status', ucfirst($user['status'])],
              ['Email verified', $user['is_verified'] ? '✅ Yes' : '❌ No'],
              ['Theme', ucfirst($user['theme_preference'] ?? 'system')],
            ];
            if (!empty($user['user_id_number'])) {
              array_unshift($infoItems, ['Student ID', $user['user_id_number']]);
            }
            if (!empty($user['cnic'])) {
              $infoItems[] = ['CNIC', $user['cnic']];
            }
            foreach ($infoItems as [$label, $value]):
            ?>
              <div style="padding:12px;background:var(--bg);border-radius:var(--radius);">
                <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;"><?= $label ?></div>
                <div style="font-weight:600;font-size:0.875rem;"><?= $value ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>
  async function saveProfile(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('profile-btn');
    const data = {
      action: 'update',
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE+'/ajax/profile.ajax.php', data);
      if (res.status === 'success') Toast.success('Profile updated!');
      else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function changePassword(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('pwd-btn');
    const np = form.querySelector('[name=new_password]').value,
      cp = form.querySelector('[name=confirm_password]').value;
    if (np !== cp) {
      Toast.error('Passwords do not match');
      return;
    }
    const data = {
      action: 'change_password',
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE+'/ajax/profile.ajax.php', data);
      if (res.status === 'success') {
        Toast.success('Password changed!');
        form.reset();
        document.getElementById('pwd-strength').style.display = 'none';
      } else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function uploadAvatar(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload_avatar');
    fd.append('avatar', input.files[0]);
    fd.append('csrf_token', window.CSRF_TOKEN);
    Toast.info('Uploading avatar…');
    const res = await fetch(window.LMS_BASE+'/ajax/profile.ajax.php', {
      method: 'POST',
      body: fd
    });
    const data = await res.json();
    if (data.status === 'success') {
      const ring = document.getElementById('avatar-ring');
      ring.innerHTML = `<img src="${data.data.url}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;" alt="Avatar">`;
      Toast.success('Avatar updated!');
    } else Toast.error(data.message);
  }

  function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    const isHidden = inp.type === 'password';
    inp.type = isHidden ? 'text' : 'password';
    btn.innerHTML = `<i data-lucide="${isHidden?'eye-off':'eye'}" style="width:15px;height:15px;"></i>`;
    lucide.createIcons({
      nodes: [btn]
    });
  }

  function updateStrength(val) {
    const bar = document.getElementById('pwd-bar'),
      lbl = document.getElementById('pwd-label'),
      wrap = document.getElementById('pwd-strength');
    if (!val) {
      wrap.style.display = 'none';
      return;
    }
    wrap.style.display = 'block';
    let score = 0;
    if (val.length >= 8) score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
      ['Weak', 'var(--danger)', 20],
      ['Fair', 'var(--warning)', 40],
      ['Good', 'var(--info)', 60],
      ['Strong', 'var(--success)', 80],
      ['Very Strong', '#059669', 100]
    ];
    const [label, color, width] = levels[Math.min(score, 4)];
    bar.style.width = width + '%';
    bar.style.background = color;
    lbl.textContent = label;
    lbl.style.color = color;
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>