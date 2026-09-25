<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Users';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Users']];
include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Users</h1>
          <p class="page-subtitle">Manage all users, roles & permissions</p>
        </div>
        <button class="btn btn-primary" onclick="Modal.open('add-user')">
          <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Add User
        </button>
      </div>

      <div class="card">
        <div class="table-controls">
          <div class="search-box">
            <i data-lucide="search" class="search-box-icon"></i>
            <input type="text" id="user-search" class="form-control" placeholder="Search name or email…" autocomplete="off">
          </div>
          <select id="filter-role" class="form-control" style="width:130px;">
            <option value="">All Roles</option>
            <option value="admin">Admin</option>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
          </select>
          <select id="filter-status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <select id="filter-verified" class="form-control" style="width:140px;">
            <option value="">All Verified</option>
            <option value="1">Verified</option>
            <option value="0">Unverified</option>
          </select>
          <div class="table-controls-right">
            <select id="per-page" class="form-control" style="width:110px;">
              <option value="10">10 / page</option>
              <option value="25">25 / page</option>
              <option value="50">50 / page</option>
            </select>
          </div>
        </div>

        <div style="overflow-x:auto;">
          <table id="users-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th class="sortable" data-col="full_name">User</th>
                <th>Email</th>
                <th>Role(s)</th>
                <th class="sortable" data-col="status">Status</th>
                <th>Verified</th>
                <th class="sortable" data-col="last_seen">Last Seen</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="users-tbody">
              <?php for ($i = 0; $i < 6; $i++): ?>
                <tr>
                  <td>
                    <div class="skeleton" style="width:16px;height:16px;border-radius:3px;"></div>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="skeleton skeleton-avatar"></div>
                      <div>
                        <div class="skeleton skeleton-text" style="width:110px;"></div>
                        <div class="skeleton skeleton-text" style="width:70px;height:10px;margin-top:4px;"></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="skeleton skeleton-text" style="width:150px;"></div>
                  </td>
                  <td>
                    <div class="skeleton" style="width:60px;height:22px;border-radius:20px;"></div>
                  </td>
                  <td>
                    <div class="skeleton" style="width:60px;height:22px;border-radius:20px;"></div>
                  </td>
                  <td>
                    <div class="skeleton" style="width:50px;height:22px;border-radius:20px;"></div>
                  </td>
                  <td>
                    <div class="skeleton skeleton-text" style="width:80px;"></div>
                  </td>
                  <td>
                    <div class="skeleton" style="width:90px;height:28px;border-radius:6px;float:right;"></div>
                  </td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="users-pagination"></div>
      </div>
    </main>
  </div>
</div>

<!-- Bulk Bar -->
<div class="bulk-action-bar" id="bulk-action-bar">
  <span class="bulk-action-count" id="bulk-count">0 selected</span>
  <span class="bulk-action-sep">|</span>
  <button class="bulk-btn" onclick="bulkAction('activate')">✓ Activate</button>
  <button class="bulk-btn" onclick="bulkAction('deactivate')">⊘ Deactivate</button>
  <button class="bulk-btn bulk-btn-danger" onclick="bulkAction('delete')">🗑 Delete</button>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="add-user-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="user-plus" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Add New User</h3>
      <button class="modal-close" data-modal-close="add-user"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="add-user-form" onsubmit="submitUser(event,'create')" style="display:flex;flex-direction:column;flex:1;overflow:hidden;min-height:0;">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" class="form-control" placeholder="John Doe" required></div>
          <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" class="form-control" placeholder="john@example.com" required></div>
          <div class="form-group"><label class="form-label">Password <span class="required">*</span></label><input type="password" name="password" class="form-control" placeholder="Min. 8 chars" required></div>
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+92 300 0000000"></div>
          <div class="form-group">
            <label class="form-label">Gender <span class="required">*</span></label>
            <select name="gender" id="au-gender" class="form-control" required>
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:4px;">
              <i data-lucide="lock" style="width:10px;height:10px;"></i> Locked after creation — controls chat permissions
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">CNIC <small style="font-weight:400;color:var(--text-muted);">(optional)</small></label>
            <input type="text" name="cnic" id="au-cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X" maxlength="15" inputmode="numeric" autocomplete="off">
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;">Leave blank if not applicable</div>
          </div>
          <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select></div>
        </div>
        <div class="form-group">
          <label class="form-label">Roles <span class="required">*</span></label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
              <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:7px;">Available</div>
              <div id="au-roles-available" style="min-height:70px;border:1.5px dashed var(--border);border-radius:var(--radius);padding:6px;display:flex;flex-direction:column;gap:5px;">
                <div class="role-chip" data-role-id="1" data-role-name="Student" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border:1.5px solid var(--border);border-radius:7px;cursor:pointer;transition:all 0.15s;" onclick="moveRoleChip(this,'au-roles-available','au-roles-assigned')"><i data-lucide="graduation-cap" style="width:14px;height:14px;color:var(--success);flex-shrink:0;"></i><span style="font-size:0.82rem;font-weight:600;">Student</span><i data-lucide="chevron-right" style="width:12px;height:12px;margin-left:auto;color:var(--text-muted);"></i></div>
                <div class="role-chip" data-role-id="2" data-role-name="Teacher" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border:1.5px solid var(--border);border-radius:7px;cursor:pointer;transition:all 0.15s;" onclick="moveRoleChip(this,'au-roles-available','au-roles-assigned')"><i data-lucide="presentation" style="width:14px;height:14px;color:var(--info);flex-shrink:0;"></i><span style="font-size:0.82rem;font-weight:600;">Teacher</span><i data-lucide="chevron-right" style="width:12px;height:12px;margin-left:auto;color:var(--text-muted);"></i></div>
                <div class="role-chip" data-role-id="3" data-role-name="Admin" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border:1.5px solid var(--border);border-radius:7px;cursor:pointer;transition:all 0.15s;" onclick="moveRoleChip(this,'au-roles-available','au-roles-assigned')"><i data-lucide="shield-check" style="width:14px;height:14px;color:var(--danger);flex-shrink:0;"></i><span style="font-size:0.82rem;font-weight:600;">Admin</span><i data-lucide="chevron-right" style="width:12px;height:12px;margin-left:auto;color:var(--text-muted);"></i></div>
              </div>
            </div>
            <div>
              <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--primary);margin-bottom:7px;">Assigned</div>
              <div id="au-roles-assigned" style="min-height:70px;border:1.5px solid var(--primary);border-radius:var(--radius);padding:6px;display:flex;flex-direction:column;gap:5px;background:rgba(99,102,241,0.04);">
                <div id="au-roles-empty" style="text-align:center;padding:12px 0;color:var(--text-muted);font-size:0.75rem;">Click a role to assign</div>
              </div>
            </div>
          </div>
          <input type="hidden" id="au-roles-value" name="roles">
        </div>
        <div class="form-group">
          <label class="toggle-wrapper">
            <div class="toggle"><input type="checkbox" name="is_verified" value="1" checked><span class="toggle-slider"></span></div><span style="font-size:0.875rem;color:var(--text-secondary);margin-left:8px;">Mark as verified</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-user">Cancel</button>
        <button type="submit" class="btn btn-primary" id="add-user-submit"><i data-lucide="user-plus" style="width:15px;height:15px;"></i><span class="btn-text"> Create User</span></button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-user-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="edit-2" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Edit User</h3>
      <button class="modal-close" data-modal-close="edit-user"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="edit-user-form" onsubmit="submitUser(event,'update')" style="display:flex;flex-direction:column;flex:1;overflow:hidden;min-height:0;">
      <input type="hidden" name="user_id" id="edit-user-id">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" id="eu-name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" id="eu-email" class="form-control" required></div>
          <div class="form-group"><label class="form-label">New Password <small style="font-weight:400;color:var(--text-muted);">(leave blank)</small></label><input type="password" name="password" class="form-control" placeholder="New password"></div>
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="eu-phone" class="form-control"></div>
          <div class="form-group">
            <label class="form-label">Gender <span class="required">*</span></label>
            <select name="gender" id="eu-gender" class="form-control" required>
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:4px;">
              <i data-lucide="lock" style="width:10px;height:10px;"></i> Changing this affects chat permissions
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">CNIC <small style="font-weight:400;color:var(--text-muted);">(optional)</small></label>
            <input type="text" name="cnic" id="eu-cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X" maxlength="15" inputmode="numeric" autocomplete="off">
          </div>
          <div class="form-group"><label class="form-label">Status</label><select name="status" id="eu-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select></div>
          <div class="form-group">
            <label class="form-label">Student ID</label>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
              <i data-lucide="hash" style="width:14px;height:14px;color:var(--text-muted);flex-shrink:0;"></i>
              <span id="eu-student-id" style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1rem;color:var(--primary);letter-spacing:3px;">—</span>
              <span style="margin-left:auto;font-size:0.7rem;color:var(--text-muted);">Auto-generated</span>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Roles</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
              <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);margin-bottom:7px;">Available</div>
              <div id="eu-roles-available" style="min-height:70px;border:1.5px dashed var(--border);border-radius:var(--radius);padding:6px;display:flex;flex-direction:column;gap:5px;"></div>
            </div>
            <div>
              <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--primary);margin-bottom:7px;">Assigned</div>
              <div id="eu-roles-assigned" style="min-height:70px;border:1.5px solid var(--primary);border-radius:var(--radius);padding:6px;display:flex;flex-direction:column;gap:5px;background:rgba(99,102,241,0.04);"></div>
            </div>
          </div>
          <input type="hidden" id="eu-roles-value" name="roles">
        </div>
        <div class="form-group">
          <label class="toggle-wrapper">
            <div class="toggle"><input type="checkbox" name="is_verified" id="eu-verified" value="1"><span class="toggle-slider"></span></div><span style="font-size:0.875rem;color:var(--text-secondary);margin-left:8px;">Email verified</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="edit-user">Cancel</button>
        <button type="submit" class="btn btn-primary" id="edit-user-submit"><i data-lucide="save" style="width:15px;height:15px;"></i><span class="btn-text"> Save Changes</span></button>
      </div>
    </form>
  </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="view-user-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="eye" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">User Details</h3>
      <button class="modal-close" data-modal-close="view-user"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" id="view-user-body">
      <div style="text-align:center;padding:32px;color:var(--text-muted);">Loading…</div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="view-user">Close</button></div>
  </div>
</div>

<style>
  .role-chip:hover {
    border-color: var(--primary) !important;
    background: rgba(99, 102, 241, 0.06) !important;
  }

  .role-chip-assigned {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    background: rgba(99, 102, 241, 0.1);
    border: 1.5px solid var(--primary);
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s;
  }

  .role-chip-assigned:hover {
    background: rgba(239, 68, 68, 0.08) !important;
    border-color: var(--danger) !important;
  }

  @media(max-width:600px) {
    .modal-lg .modal .role-picker-grid {
      grid-template-columns: 1fr !important
    }
  }
</style>
<script>
  /* ─── Role Picker ──────────────────────────────────────────── */
  const ROLE_META = {
    '1': {
      name: 'Student',
      icon: 'graduation-cap',
      color: 'var(--success)'
    },
    '2': {
      name: 'Teacher',
      icon: 'presentation',
      color: 'var(--info)'
    },
    '3': {
      name: 'Admin',
      icon: 'shield-check',
      color: 'var(--danger)'
    },
  };

  function _roleChipHTML(id, isAssigned, prefix) {
    const m = ROLE_META[id];
    if (isAssigned) {
      return `<div class="role-chip-assigned" data-role-id="${id}" onclick="moveRoleChip(this,'${prefix}-roles-assigned','${prefix}-roles-available')">
      <i data-lucide="${m.icon}" style="width:14px;height:14px;color:${m.color};flex-shrink:0;"></i>
      <span style="font-size:0.82rem;font-weight:600;">${m.name}</span>
      <i data-lucide="x" style="width:12px;height:12px;margin-left:auto;color:var(--danger);"></i>
    </div>`;
    } else {
      return `<div class="role-chip" data-role-id="${id}" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border:1.5px solid var(--border);border-radius:7px;cursor:pointer;transition:all 0.15s;" onclick="moveRoleChip(this,'${prefix}-roles-available','${prefix}-roles-assigned')">
      <i data-lucide="${m.icon}" style="width:14px;height:14px;color:${m.color};flex-shrink:0;"></i>
      <span style="font-size:0.82rem;font-weight:600;">${m.name}</span>
      <i data-lucide="chevron-right" style="width:12px;height:12px;margin-left:auto;color:var(--text-muted);"></i>
    </div>`;
    }
  }

  function moveRoleChip(chip, fromId, toId) {
    const fromEl = document.getElementById(fromId);
    const toEl = document.getElementById(toId);
    const prefix = fromId.split('-')[0]; // 'au' or 'eu'
    const id = chip.dataset.roleId;
    const isMovingToAssigned = toId.includes('assigned');

    chip.remove();

    // Clear any empty-state placeholders in destination
    toEl.querySelectorAll(':not([data-role-id])').forEach(el => el.remove());

    // Insert new chip
    const tmp = document.createElement('div');
    tmp.innerHTML = _roleChipHTML(id, isMovingToAssigned, prefix);
    toEl.appendChild(tmp.firstElementChild);

    // Show empty placeholder in source if needed
    if (!fromEl.querySelector('[data-role-id]')) {
      fromEl.innerHTML = isMovingToAssigned ?
        '<div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:0.73rem;">All roles assigned</div>' :
        '<div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:0.73rem;">No roles assigned</div>';
    }

    _syncRoleValue(prefix);
    if (window.lucide) lucide.createIcons({
      nodes: [toEl, fromEl]
    });
  }

  function _syncRoleValue(prefix) {
    const assigned = document.getElementById(prefix + '-roles-assigned');
    if (!assigned) return;
    const ids = [...assigned.querySelectorAll('[data-role-id]')].map(el => el.dataset.roleId);
    const hidden = document.getElementById(prefix + '-roles-value');
    if (hidden) hidden.value = ids.join(',');
  }

  function _resetRolePicker(prefix) {
    const availEl = document.getElementById(prefix + '-roles-available');
    const assgnEl = document.getElementById(prefix + '-roles-assigned');
    if (!availEl || !assgnEl) return;
    availEl.innerHTML = ['1', '2', '3'].map(id => _roleChipHTML(id, false, prefix)).join('');
    assgnEl.innerHTML = '<div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:0.73rem;">Click a role to assign</div>';
    const hidden = document.getElementById(prefix + '-roles-value');
    if (hidden) hidden.value = '';
    if (window.lucide) lucide.createIcons({
      nodes: [availEl, assgnEl]
    });
  }

  function _populateRolePicker(prefix, assignedRoles) {
    // assignedRoles: array of role name strings e.g. ['student','admin']
    const nameToId = {
      student: '1',
      teacher: '2',
      admin: '3'
    };
    const assignedIds = assignedRoles.map(r => nameToId[r.trim().toLowerCase()]).filter(Boolean);
    const availEl = document.getElementById(prefix + '-roles-available');
    const assgnEl = document.getElementById(prefix + '-roles-assigned');
    if (!availEl || !assgnEl) return;

    const availChips = ['1', '2', '3'].filter(id => !assignedIds.includes(id));
    availEl.innerHTML = availChips.length ?
      availChips.map(id => _roleChipHTML(id, false, prefix)).join('') :
      '<div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:0.73rem;">All roles assigned</div>';

    assgnEl.innerHTML = assignedIds.length ?
      assignedIds.map(id => _roleChipHTML(id, true, prefix)).join('') :
      '<div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:0.73rem;">Click a role to assign</div>';

    const hidden = document.getElementById(prefix + '-roles-value');
    if (hidden) hidden.value = assignedIds.join(',');
    if (window.lucide) lucide.createIcons({
      nodes: [availEl, assgnEl]
    });
  }

  /* Reset add-user picker whenever that modal closes (Cancel, X, backdrop) */
  const _addUserOverlay = document.getElementById('add-user-overlay');
  if (_addUserOverlay) {
    const _obs = new MutationObserver(() => {
      if (!_addUserOverlay.classList.contains('open')) {
        setTimeout(() => _resetRolePicker('au'), 250);
      }
    });
    _obs.observe(_addUserOverlay, {
      attributes: true,
      attributeFilter: ['class']
    });
  }

  /* ─── Table state ──────────────────────────────────────────── */
  let uPage = 1,
    uPerPage = 10,
    uSortCol = 'created_at',
    uSortDir = 'desc',
    uSearch = '',
    uRole = '',
    uStatus = '',
    uVerified = '';

  async function loadUsers() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
        action: 'list',
        page: uPage,
        per_page: uPerPage,
        search: uSearch,
        role: uRole,
        status: uStatus,
        verified: uVerified,
        sort_col: uSortCol,
        sort_dir: uSortDir
      });
      if (res.status === 'success') {
        renderUsers(res.data.users);
        renderPagination(res.data.total, res.data.page, res.data.per_page);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function renderUsers(users) {
    const tb = document.getElementById('users-tbody');
    if (!users || !users.length) {
      tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">👥</div><div class="empty-state-title">No users found</div><div class="empty-state-text">Try adjusting filters</div></div></td></tr>`;
      return;
    }
    tb.innerHTML = users.map(u => {
      const roles = (u.all_roles || '').split(',').filter(Boolean).map(r => `<span class="badge ${r==='admin'?'badge-danger':r==='teacher'?'badge-info':'badge-success'}">${r}</span>`).join(' ');
      const stToggle = `<span class="badge ${u.status==='active'?'badge-success':'badge-warning'} status-toggle" data-id="${u.id}" data-status="${u.status}" style="cursor:pointer;" data-tooltip="Toggle status"><span class="badge-dot" style="background:${u.status==='active'?'#10b981':'#f59e0b'}"></span>${u.status==='active'?'Active':'Inactive'}</span>`;
      const verBadge = u.is_verified == 1 ? '<span class="badge badge-success">✓ Yes</span>' : '<span class="badge badge-warning">✗ No</span>';
      const initials = u.full_name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
      const bg = colors[Math.abs(u.full_name.split('').reduce((a, c) => a + c.charCodeAt(0), 0)) % colors.length];
      const onlineDot = u.is_online == 1 ? `<span style="position:absolute;bottom:0;right:0;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid var(--bg-card);"></span>` : '';
      const isStudent = (u.all_roles || '').split(',').includes('student');
      return `<tr>
      <td style="width:40px;"><input type="checkbox" class="row-cb" value="${u.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="display:flex;align-items:center;gap:10px;">
        <div style="position:relative;flex-shrink:0;"><div style="width:36px;height:36px;background:${bg};color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;font-family:Poppins,sans-serif;">${initials}</div>${onlineDot}</div>
        <div><div style="font-weight:600;font-size:0.875rem;">${escapeHtml(u.full_name)}</div><div style="font-size:0.72rem;color:var(--text-muted);">ID #${u.id}</div></div>
      </div></td>
      <td style="font-size:0.875rem;color:var(--text-secondary);">${escapeHtml(u.email)}</td>
      <td>${roles||'<span style="color:var(--text-muted);">—</span>'}</td>
      <td>${stToggle}</td>
      <td>${verBadge}</td>
      <td style="font-size:0.82rem;color:var(--text-muted);">${u.last_seen?timeAgoJS(u.last_seen):'Never'}</td>
      <td><div class="table-actions" style="justify-content:flex-end;flex-wrap:nowrap;min-width:130px;">
        <button class="action-btn action-btn-view" onclick="viewUser(${u.id})" data-tooltip="View"><i data-lucide="eye" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-edit" onclick="editUser(${u.id})" data-tooltip="Edit"><i data-lucide="edit-2" style="width:13px;height:13px;"></i></button>
        ${isStudent ? `<button class="action-btn bypass-btn-${u.id}" onclick="toggleBypassGate(${u.id},'${escapeHtml(u.full_name)}',${u.bypass_gate||0})" data-tooltip="${u.bypass_gate==1?'Dashboard bypass ON — click to disable':'Dashboard locked — click to grant access'}" style="color:${u.bypass_gate==1?'#10b981':'var(--text-muted)'}"><i data-lucide="${u.bypass_gate==1?'shield-check':'shield-off'}" style="width:13px;height:13px;"></i></button>` : ''}
        <button class="action-btn action-btn-delete" onclick="deleteUser(${u.id},'${escapeHtml(u.full_name)}')" data-tooltip="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('users-tbody')]
    });
    document.querySelectorAll('.status-toggle').forEach(el => el.addEventListener('click', () => toggleStatus(el.dataset.id, el.dataset.status === 'active' ? 'inactive' : 'active')));
    BulkSelect.init('users-table');
  }

  function renderPagination(total, page, perPage) {
    const el = document.getElementById('users-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pages = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pages += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="goPage(${i});return false;">${i}</a>`;
      else if (i === page - 3 || i === page + 3) pages += '<span class="page-ellipsis">…</span>';
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="goPage(${page-1});return false;">‹</a>${pages}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="goPage(${page+1});return false;">›</a></div></div>`;
  }

  function goPage(p) {
    uPage = p;
    loadUsers();
  }

  async function toggleStatus(id, status) {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
        action: 'toggle_status',
        user_id: id,
        status
      });
      if (res.status === 'success') {
        Toast.success(`User ${status==='active'?'activated':'deactivated'}`);
        loadUsers();
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Failed');
    }
  }

  async function submitUser(e, action) {
    e.preventDefault();
    const form = e.target,
      btn = form.querySelector('[type=submit]');
    const fd = new FormData(form);
    const data = {
      action,
      csrf_token: window.CSRF_TOKEN
    };
    for (let [k, v] of fd.entries()) data[k] = v;
    // Collect roles from the two-column picker
    const prefix2 = action === 'create' ? 'au' : 'eu';
    _syncRoleValue(prefix2);
    const rolesHidden = document.getElementById(prefix2 + '-roles-value');
    data.roles = rolesHidden ? rolesHidden.value : '';
    data.is_verified = form.querySelector('[name=is_verified]')?.checked ? 1 : 0;
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', data);
      if (res.status === 'success') {
        Toast.success(action === 'create' ? 'User created!' : 'User updated!');
        Modal.close(action === 'create' ? 'add-user' : 'edit-user');
        form.reset();
        loadUsers();
      } else Toast.error(res.message);
    } catch (err) {
      Toast.error('Request failed');
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function editUser(id) {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
        action: 'get_one',
        user_id: id
      });
      if (res.status !== 'success') {
        Toast.error(res.message);
        return;
      }
      const u = res.data;
      document.getElementById('edit-user-id').value = u.id;
      document.getElementById('eu-name').value = u.full_name || '';
      document.getElementById('eu-email').value = u.email || '';
      document.getElementById('eu-phone').value = u.phone || '';
      document.getElementById('eu-gender').value = u.gender || '';
      document.getElementById('eu-cnic').value = u.cnic || '';
      document.getElementById('eu-status').value = u.status || 'active';
      document.getElementById('eu-verified').checked = u.is_verified == 1;
      document.getElementById('eu-student-id').textContent = u.user_id_number || '—';
      // Populate the two-column role picker
      const existingRoles = (u.all_roles || '').split(',').filter(Boolean);
      _populateRolePicker('eu', existingRoles);
      Modal.open('edit-user');
    } catch (e) {
      Toast.error('Failed to load user');
    }
  }

  async function viewUser(id) {
    Modal.open('view-user');
    const body = document.getElementById('view-user-body');
    body.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);">Loading…</div>';
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
        action: 'get_one',
        user_id: id
      });
      if (res.status !== 'success') {
        body.innerHTML = '<div style="text-align:center;padding:32px;color:var(--danger);">Failed</div>';
        return;
      }
      const u = res.data;
      const initials = u.full_name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];
      const bg = colors[Math.abs(u.full_name.split('').reduce((a, c) => a + c.charCodeAt(0), 0)) % colors.length];
      const roles = (u.all_roles || '').split(',').filter(Boolean).map(r => `<span class="badge ${r==='admin'?'badge-danger':r==='teacher'?'badge-info':'badge-success'}">${r}</span>`).join(' ');
      body.innerHTML = `
      <div style="text-align:center;margin-bottom:24px;">
        <div style="width:72px;height:72px;background:${bg};color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.4rem;font-family:Poppins,sans-serif;margin:0 auto 12px;">${initials}</div>
        <h3 style="font-family:Poppins,sans-serif;font-size:1.1rem;font-weight:700;">${escapeHtml(u.full_name)}</h3>
        <div style="color:var(--text-muted);font-size:0.85rem;margin-top:2px;">${escapeHtml(u.email)}</div>
        <div style="margin-top:10px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">${roles}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        ${[['Student ID',u.user_id_number||'—'],['Status',u.status==='active'?'✅ Active':'⚠️ Inactive'],['Verified',u.is_verified==1?'✅ Yes':'❌ No'],['Phone',u.phone||'—'],['Gender',u.gender?u.gender.charAt(0).toUpperCase()+u.gender.slice(1):'—'],['CNIC',u.cnic||'—'],['Online',u.is_online==1?'🟢 Yes':'⚫ No'],['Joined',formatDateJS(u.created_at)],['Last Seen',u.last_seen?timeAgoJS(u.last_seen):'Never'],['Current Role',u.current_role||'—']].map(([l,v])=>`
          <div style="padding:10px 12px;background:var(--bg);border-radius:var(--radius);">
            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:3px;">${l}</div>
            <div style="font-weight:600;font-size:0.875rem;">${v}</div>
          </div>`).join('')}
      </div>
      ${u.bio?`<div style="margin-top:10px;padding:12px;background:var(--bg);border-radius:var(--radius);"><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">Bio</div><div style="font-size:0.875rem;color:var(--text-secondary);">${escapeHtml(u.bio)}</div></div>`:''}
    `;
    } catch (e) {
      body.innerHTML = '<div style="text-align:center;padding:32px;color:var(--danger);">Error loading</div>';
    }
  }

  function deleteUser(id, name) {
    Modal.confirm({
      title: 'Delete User',
      message: `Permanently delete <strong>${escapeHtml(name)}</strong>? All their data, enrollments and submissions will be removed.`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
          action: 'delete',
          user_id: id
        });
        if (res.status === 'success') {
          Toast.success('User deleted');
          loadUsers();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkAction(action) {
    const ids = BulkSelect.getSelected('users-table');
    if (!ids.length) {
      Toast.warning('No users selected');
      return;
    }
    const labels = {
      activate: 'Activate',
      deactivate: 'Deactivate',
      delete: 'Delete'
    };
    const classes = {
      activate: 'btn-success',
      deactivate: 'btn-secondary',
      delete: 'btn-danger'
    };
    Modal.confirm({
      title: `${labels[action]} ${ids.length} User${ids.length>1?'s':''}`,
      message: action === 'delete' ? `Permanently delete <strong>${ids.length} users</strong>?` : `This will ${action} <strong>${ids.length} users</strong>.`,
      confirmText: labels[action],
      confirmClass: classes[action],
      icon: action === 'delete' ? '🗑️' : '⚠️',
      iconClass: action === 'delete' ? 'modal-icon-danger' : 'modal-icon-warning',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
          action: action === 'delete' ? 'bulk_delete' : 'bulk_status',
          ids: ids.join(','),
          status: action === 'activate' ? 'active' : action === 'deactivate' ? 'inactive' : ''
        });
        if (res.status === 'success') {
          Toast.success(res.message);
          loadUsers();
        } else throw new Error(res.message);
      }
    });
  }

  // Sort
  document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', () => {
    if (uSortCol === th.dataset.col) uSortDir = uSortDir === 'asc' ? 'desc' : 'asc';
    else {
      uSortCol = th.dataset.col;
      uSortDir = 'asc';
    }
    document.querySelectorAll('th.sortable').forEach(t => t.className = 'sortable');
    th.classList.add('sort-' + uSortDir);
    uPage = 1;
    loadUsers();
  }));

  // Filters
  const debouncedLoad = debounce(() => {
    uPage = 1;
    loadUsers();
  }, 300);
  document.getElementById('user-search').addEventListener('input', e => {
    uSearch = e.target.value;
    debouncedLoad();
  });
  document.getElementById('filter-role').addEventListener('change', e => {
    uRole = e.target.value;
    uPage = 1;
    loadUsers();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    uStatus = e.target.value;
    uPage = 1;
    loadUsers();
  });
  document.getElementById('filter-verified').addEventListener('change', e => {
    uVerified = e.target.value;
    uPage = 1;
    loadUsers();
  });
  document.getElementById('per-page').addEventListener('change', e => {
    uPerPage = e.target.value;
    uPage = 1;
    loadUsers();
  });

  // Helpers
  function timeAgoJS(dt) {
    const s = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (s < 60) return 'Just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    if (s < 2592000) return Math.floor(s / 86400) + 'd ago';
    return new Date(dt).toLocaleDateString();
  }

  function formatDateJS(dt) {
    return dt ? new Date(dt).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }) : '—';
  }

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
  });

  // CNIC auto-format for both modals
  ['au-cnic', 'eu-cnic'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function() {
      let raw = this.value.replace(/[^0-9]/g, '').slice(0, 13);
      if (raw.length > 12) raw = raw.slice(0, 5) + '-' + raw.slice(5, 12) + '-' + raw.slice(12);
      else if (raw.length > 5) raw = raw.slice(0, 5) + '-' + raw.slice(5);
      if (this.value !== raw) this.value = raw;
    });
  });

  // ── Toggle Bypass Gate ──────────────────────────────────────
  async function toggleBypassGate(userId, name, currentVal) {
    const enabling = currentVal == 0;
    Modal.confirm({
      title: enabling ? 'Grant Dashboard Access?' : 'Revoke Dashboard Access?',
      message: enabling
        ? '<strong>' + escapeHtml(name) + '</strong> will be able to log into the LMS dashboard without applying to any course. They will see empty modules until enrolled in a batch.'
        : '<strong>' + escapeHtml(name) + '</strong> will be blocked by the application gate again on next login.',
      confirmText: enabling ? 'Grant Access' : 'Revoke Access',
      confirmClass: enabling ? 'btn-primary' : 'btn-danger',
      icon: enabling ? '🔓' : '🔒',
      iconClass: enabling ? 'modal-icon-primary' : 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/users.ajax.php', {
          action: 'toggle_bypass_gate',
          user_id: userId
        });
        if (res.status !== 'success') { Toast.error(res.message); return; }
        Toast.success(res.message);
        loadUsers();
      }
    });
  }

  // Debounced search is handled per-page
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>