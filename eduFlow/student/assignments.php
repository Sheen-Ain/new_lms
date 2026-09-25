<?php
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'My Assignments';
$breadcrumbs = [['label' => 'Student'], ['label' => 'Assignments']];
$uid = (int)$currentUser['id'];
$tr = $conn->prepare("SELECT DISTINCT t.id,t.title FROM topics t JOIN batch_students bs ON t.batch_id=bs.batch_id WHERE bs.student_id=? AND t.status='active' ORDER BY t.title");
$tr->bind_param('i', $uid);
$tr->execute();
$topics = $tr->get_result()->fetch_all(MYSQLI_ASSOC);
$tr->close();
include __DIR__ . '/../includes/header.php';
?>
<style>
  /* ── Assignments page responsive ────────────────────────── */

  /* Filter bar */
  .asn-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    padding: 14px 18px;
  }

  .asn-filter-bar .search-box {
    flex: 1;
    min-width: 180px;
  }

  .asn-filter-bar select {
    width: 190px;
    flex-shrink: 0;
  }

  /* Status tabs */
  .asn-tabs {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: var(--bg-card);
    padding: 5px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    width: fit-content;
    max-width: 100%;
  }

  /* Assignment card grid */
  #assignments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
  }

  /* Submit + oath modals */
  #submit-modal-overlay .modal,
  #oath-modal-overlay .modal,
  #grade-view-overlay .modal,
  #afiles-modal-overlay .modal {
    max-width: min(660px, 96vw);
  }

  /* File drop zone */
  .file-drop-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
  }

  .file-drop-zone:hover,
  .file-drop-zone.dragover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.04);
  }

  .file-drop-zone-icon {
    margin-bottom: 10px;
  }

  .file-drop-zone-text {
    font-size: 0.84rem;
    color: var(--text-secondary);
    line-height: 1.6;
  }

  /* ── Responsive ─────────────────────────────────────────── */
  @media (max-width: 768px) {
    .asn-filter-bar select {
      width: 100%;
    }

    #assignments-grid {
      grid-template-columns: 1fr;
    }

    .asn-tabs {
      width: 100%;
      overflow-x: auto;
      flex-wrap: nowrap;
      padding-bottom: 6px;
    }

    .asn-tabs::-webkit-scrollbar {
      height: 3px;
    }

    .asn-tabs::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 99px;
    }
  }

  /* Description modal */
  #desc-modal-overlay .modal { max-width: min(660px, 96vw); }
  #desc-modal-overlay .modal-body {
    white-space: pre-wrap;
    font-size: 0.875rem;
    line-height: 1.8;
    color: var(--text-secondary);
    max-height: 60vh;
    overflow-y: auto;
  }

  /* Bulk toolbar */
  #bulk-bar {
    display: none;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 16px;
    background: rgba(99,102,241,0.07);
    border: 1.5px solid rgba(99,102,241,0.25);
    border-radius: var(--radius);
    margin-bottom: 14px;
  }
  #bulk-bar.visible { display: flex; }

  @media (max-width: 540px) {
    .asn-filter-bar {
      padding: 10px 12px;
      gap: 8px;
    }

    #submit-modal-overlay .modal-body,
    #oath-modal-overlay .modal-body {
      padding: 16px 16px;
    }

    #oath-modal-overlay .modal-footer,
    #submit-modal-overlay .modal-footer {
      flex-direction: column;
      gap: 8px;
    }

    #oath-modal-overlay .modal-footer .btn,
    #submit-modal-overlay .modal-footer .btn {
      width: 100%;
      justify-content: center;
    }
  }
</style>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">My Assignments</h1>
          <p class="page-subtitle" id="result-subtitle">View, submit and track your assignments</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-ghost btn-sm" id="select-all-btn" onclick="toggleSelectAll()" style="display:none;">
            <i data-lucide="check-square" style="width:14px;height:14px;"></i> <span id="sel-all-lbl">Select All</span>
          </button>
          <button class="btn btn-primary btn-sm" onclick="startBulkMode()" id="bulk-mode-btn">
            <i data-lucide="download-cloud" style="width:14px;height:14px;"></i> Download Submissions
          </button>
        </div>
      </div>
      <!-- Bulk download toolbar -->
      <div id="bulk-bar">
        <span id="bulk-count" style="font-size:0.82rem;font-weight:600;color:var(--primary);"></span>
        <button class="btn btn-primary btn-sm" id="bulk-dl-btn" onclick="doBulkDownload()" disabled>
          <i data-lucide="download" style="width:13px;height:13px;"></i> Download ZIP
        </button>
        <button class="btn btn-ghost btn-sm" onclick="cancelBulkMode()">
          <i data-lucide="x" style="width:13px;height:13px;"></i> Cancel
        </button>
      </div>

      <!-- Search + Topic filter bar -->
      <div class="card" style="margin-bottom:16px;">
        <div class="asn-filter-bar">
          <div class="search-box">
            <i data-lucide="search" class="search-box-icon"></i>
            <input type="text" id="a-search" class="form-control" placeholder="Search assignments…" oninput="applyFilters()" autocomplete="off">
          </div>
          <?php if ($topics): ?>
            <select id="a-topic" class="form-control" onchange="applyFilters()">
              <option value="">All Topics</option>
              <?php foreach ($topics as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm" id="clear-btn" style="display:none;" onclick="clearFilters()">
            <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
          </button>
        </div>
      </div>

      <!-- Status tabs -->
      <div class="asn-tabs">
        <button class="btn btn-primary btn-sm tab-btn active" onclick="setFilter('',this)">
          <i data-lucide="layout-grid" style="width:13px;height:13px;margin-right:4px;"></i>All
        </button>
        <button class="btn btn-ghost btn-sm tab-btn" onclick="setFilter('pending',this)">
          <i data-lucide="clock" style="width:13px;height:13px;margin-right:4px;"></i>Pending
        </button>
        <button class="btn btn-ghost btn-sm tab-btn" onclick="setFilter('submitted',this)">
          <i data-lucide="send" style="width:13px;height:13px;margin-right:4px;"></i>Submitted
        </button>
        <button class="btn btn-ghost btn-sm tab-btn" onclick="setFilter('graded',this)">
          <i data-lucide="award" style="width:13px;height:13px;margin-right:4px;"></i>Graded
        </button>
        <button class="btn btn-ghost btn-sm tab-btn" onclick="setFilter('returned',this)">
          <i data-lucide="refresh-ccw" style="width:13px;height:13px;margin-right:4px;"></i>Returned
        </button>
        <button class="btn btn-ghost btn-sm tab-btn" onclick="setFilter('overdue',this)">
          <i data-lucide="alert-circle" style="width:13px;height:13px;margin-right:4px;"></i>Overdue
        </button>
      </div>

      <div id="assignments-grid">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="card" style="height:180px;">
            <div class="skeleton" style="width:60%;height:18px;border-radius:6px;margin-bottom:10px;"></div>
            <div class="skeleton" style="width:40%;height:14px;border-radius:6px;margin-bottom:8px;"></div>
            <div class="skeleton" style="width:100%;height:12px;border-radius:6px;margin-bottom:6px;"></div>
            <div class="skeleton" style="width:80%;height:12px;border-radius:6px;"></div>
          </div>
        <?php endfor; ?>
      </div>
      <div id="a-pagination" style="margin-top:20px;"></div>
    </main>
  </div>
</div>

<!-- ─────── SUBMIT MODAL ─────── -->
<div class="modal-overlay" id="submit-modal-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="upload" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="sm-title">Submit Assignment</h3>
      <button class="modal-close" data-modal-close="submit-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body">
      <!-- Warning banner -->
      <div style="display:flex;gap:12px;align-items:flex-start;padding:14px 16px;background:rgba(239,68,68,0.08);border:1.5px solid rgba(239,68,68,0.25);border-radius:var(--radius);margin-bottom:20px;">
        <i data-lucide="alert-triangle" style="width:20px;height:20px;color:#dc2626;flex-shrink:0;margin-top:1px;"></i>
        <div>
          <div style="font-weight:700;font-size:0.875rem;color:#dc2626;margin-bottom:4px;">Review carefully before submitting</div>
          <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.6;">Submissions are <strong>final and permanent</strong>. You will <strong>not</strong> be able to update, edit, or delete your submission after clicking Submit. Make sure your work is complete and ready.</div>
        </div>
      </div>
      <!-- Assignment info -->
      <div id="sm-info" style="padding:14px;background:var(--bg);border-radius:var(--radius);margin-bottom:20px;border:1px solid var(--border);"></div>
      <!-- File upload -->
      <form id="submit-form" onsubmit="requestOathConfirm(event)" enctype="multipart/form-data">
        <input type="hidden" name="assignment_id" id="sm-id">
        <div class="form-group">
          <label class="form-label">Upload Your Work</label>
          <div id="sm-drop" class="file-drop-zone">
            <div class="file-drop-zone-icon"><i data-lucide="folder-open" style="width:28px;height:28px;color:var(--primary);opacity:0.7;"></i></div>
            <div class="file-drop-zone-text">Drop file here or <strong>click to browse</strong><br><small style="color:var(--text-muted);">PDF, DOC, ZIP, Images, Code files — max 20MB</small></div>
          </div>
          <input type="file" name="file" id="sm-file" style="display:none;" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.txt,.php,.js,.html,.sql,.csv">
          <div id="sm-preview" style="margin-top:8px;display:none;"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes for Teacher</label>
          <textarea name="notes" id="sm-notes" class="form-control" rows="3" placeholder="Any notes or comments for your teacher…"></textarea>
        </div>
        <div class="modal-footer" style="padding:0;margin-top:0;border:none;">
          <button type="button" class="btn btn-secondary" data-modal-close="submit-modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="sm-btn">
            <i data-lucide="send" style="width:15px;height:15px;"></i><span class="btn-text"> Submit Assignment</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ─────── OATH CONFIRMATION MODAL ─────── -->
<div class="modal-overlay" id="oath-modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon" style="background:rgba(99,102,241,0.12);color:var(--primary);">
        <i data-lucide="shield-check" style="width:20px;height:20px;"></i>
      </div>
      <h3 class="modal-title">Academic Integrity Pledge</h3>
    </div>
    <div class="modal-body">
      <div style="background:linear-gradient(135deg,rgba(99,102,241,0.06),rgba(139,92,246,0.06));border:1.5px solid rgba(99,102,241,0.2);border-radius:var(--radius);padding:20px 22px;margin-bottom:20px;">
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <i data-lucide="scroll-text" style="width:22px;height:22px;color:var(--primary);flex-shrink:0;margin-top:2px;"></i>
          <div>
            <p style="font-weight:700;font-size:0.9rem;margin-bottom:10px;color:var(--text-primary);">By submitting this assignment, I solemnly affirm that:</p>
            <ul style="margin:0;padding-left:18px;font-size:0.85rem;line-height:2;color:var(--text-secondary);">
              <li>The work I am submitting is <strong>entirely my own</strong> original effort.</li>
              <li>I have <strong>not copied</strong> or plagiarised any part from another student, the internet, or any unauthorised source.</li>
              <li>I have <strong>not used any AI tool</strong> (ChatGPT, Copilot, etc.) to generate or complete my work unless explicitly permitted.</li>
              <li>I understand that academic dishonesty may result in <strong>disciplinary action</strong>.</li>
            </ul>
          </div>
        </div>
      </div>
      <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px 14px;background:var(--bg);border-radius:var(--radius);border:1.5px solid var(--border);" id="oath-label">
        <input type="checkbox" id="oath-check" style="width:18px;height:18px;margin-top:1px;accent-color:var(--primary);flex-shrink:0;" onchange="updateOathBtn()">
        <span style="font-size:0.85rem;line-height:1.6;color:var(--text-secondary);">I have read and understood the above pledge. I confirm that this submission is my own honest work and I accept full responsibility for its contents.</span>
      </label>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="Modal.close('oath-modal')">Go Back &amp; Review</button>
      <button class="btn btn-primary" id="oath-confirm-btn" disabled onclick="doSubmit()" style="opacity:0.5;cursor:not-allowed;">
        <i data-lucide="check-circle" style="width:15px;height:15px;"></i> Confirm &amp; Submit
      </button>
    </div>
  </div>
</div>

<!-- ─────── GRADE VIEW MODAL ─────── -->
<div class="modal-overlay" id="grade-view-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon modal-icon-success"><i data-lucide="award" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Grade &amp; Feedback</h3>
      <button class="modal-close" data-modal-close="grade-view"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" id="gv-body" style="min-height:80px;"></div>
    <div class="modal-footer" id="gv-footer" style="gap:8px;"><button class="btn btn-secondary" data-modal-close="grade-view">Close</button></div>
  </div>
</div>

<!-- ─────── ASSIGNMENT FILES MODAL ─────── -->
<div class="modal-overlay" id="afiles-modal-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="paperclip" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="afiles-title">Assignment Files</h3>
      <button class="modal-close" data-modal-close="afiles-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" id="afiles-body"></div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="afiles-modal">Close</button></div>
  </div>
</div>

<!-- ─────── DESCRIPTION MODAL ─────── -->
<div class="modal-overlay" id="desc-modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="file-text" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="desc-modal-title">Assignment Description</h3>
      <button class="modal-close" data-modal-close="desc-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" id="desc-modal-body"></div>
    <div class="modal-footer" style="gap:8px;">
      <button class="btn btn-primary btn-sm" id="desc-dl-btn" onclick="downloadDescription()">
        <i data-lucide="download" style="width:13px;height:13px;"></i> Download .txt
      </button>
      <button class="btn btn-secondary" data-modal-close="desc-modal">Close</button>
    </div>
  </div>
</div>

<!-- Hidden form for bulk ZIP download -->
<form id="bulk-dl-form" method="POST" action="<?= BASE_PATH ?>/download-zip.php" style="display:none;">
  <input type="hidden" name="type" value="my_submissions">
  <input type="hidden" name="ids" id="bulk-ids-input">
</form>

<script>
  let aFilter = '',
    aPage = 1,
    allItems = [],
    pendingFormData = null,
    aSearch = '',
    aTopicId = '';

  function applyFilters() {
    aSearch = document.getElementById('a-search').value.trim().toLowerCase();
    const sel = document.getElementById('a-topic');
    aTopicId = sel ? sel.value : '';
    const btn = document.getElementById('clear-btn');
    if (btn) btn.style.display = (aSearch || aTopicId) ? '' : 'none';
    renderCards(filterItems(allItems));
  }

  function clearFilters() {
    document.getElementById('a-search').value = '';
    const sel = document.getElementById('a-topic');
    if (sel) sel.value = '';
    aSearch = '';
    aTopicId = '';
    const btn = document.getElementById('clear-btn');
    if (btn) btn.style.display = 'none';
    renderCards(filterItems(allItems));
  }


  async function loadAssignments() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
        action: 'list',
        page: aPage,
        per_page: 200
      });
      if (res.status !== 'success') {
        Toast.error(res.message);
        return;
      }
      allItems = res.data.assignments;
      renderCards(filterItems(allItems));
      renderPag(res.data.total, res.data.page, res.data.per_page);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function filterItems(items) {
    const now = Date.now();
    return items.filter(a => {
      const s = a.my_submission;
      const over = a.due_date && new Date(a.due_date) < now;
      if (aFilter === 'pending'   && !(!s && !over)) return false;
      if (aFilter === 'submitted' && !(s && s.status === 'submitted')) return false;
      if (aFilter === 'graded'    && !(s && s.status === 'graded')) return false;
      if (aFilter === 'returned'  && !(s && s.status === 'returned')) return false;
      if (aFilter === 'overdue'   && !(!s && over)) return false;
      if (aSearch && !a.title.toLowerCase().includes(aSearch)) return false;
      if (aTopicId && String(a.topic_id) !== String(aTopicId)) return false;
      return true;
    });
  }

  function setFilter(f, btn) {
    aFilter = f;
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active', 'btn-primary');
      b.classList.add('btn-ghost');
    });
    btn.classList.add('active', 'btn-primary');
    btn.classList.remove('btn-ghost');
    renderCards(filterItems(allItems));
  }

  function renderCards(items) {
    const grid = document.getElementById('assignments-grid');
    const now = Date.now();
    if (!items.length) {
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">
      <div class="empty-state-icon"><i data-lucide="clipboard-x" style="width:40px;height:40px;opacity:0.35;"></i></div>
      <div class="empty-state-title">No assignments found</div>
      <div class="empty-state-text">${aSearch||aTopicId?'Try adjusting your filters':'Check back later'}</div>
    </div>`;
      if (window.lucide) lucide.createIcons({
        nodes: [grid]
      });
      return;
    }
    grid.innerHTML = items.map(a => {
      const s = a.my_submission;
      const over = a.due_date && new Date(a.due_date) < now;
      const diff = a.due_date ? new Date(a.due_date) - now : null;
      const allowLate = a.allow_late == 1;
      const locked = over && !allowLate && !s;
      // A returned submission can always be resubmitted regardless of due date

      let badge = '',
        border = 'var(--border)';
      if (s?.status === 'graded') {
        badge = '<span class="badge badge-success" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="award" style="width:11px;height:11px;"></i>Graded</span>';
        border = 'var(--success)';
      } else if (s?.status === 'returned') {
        badge = '<span class="badge badge-warning badge-pulse" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="refresh-ccw" style="width:11px;height:11px;"></i>Returned – Resubmit</span>';
        border = 'var(--warning)';
      } else if (s?.status === 'submitted') {
        badge = '<span class="badge badge-info" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="send" style="width:11px;height:11px;"></i>Submitted</span>';
        border = 'var(--info)';
      } else if (locked) {
        badge = '<span class="badge badge-danger" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="lock" style="width:11px;height:11px;"></i>Closed</span>';
        border = 'var(--danger)';
      } else if (over && allowLate) {
        badge = '<span class="badge badge-warning badge-pulse" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="alert-triangle" style="width:11px;height:11px;"></i>Overdue – Late OK</span>';
        border = 'var(--warning)';
      } else if (diff !== null && diff < 86400000) {
        badge = '<span class="badge badge-warning" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="clock" style="width:11px;height:11px;"></i>Due Soon</span>';
        border = 'var(--warning)';
      } else {
        badge = '<span class="badge badge-secondary" style="display:inline-flex;align-items:center;gap:4px;"><i data-lucide="clipboard-list" style="width:11px;height:11px;"></i>Pending</span>';
      }

      let due = 'No due date';
      if (a.due_date) {
        if (diff < 0) due = `<span style="color:var(--danger);font-weight:600;">Overdue</span>`;
        else if (diff < 3600000) due = `<span style="color:var(--danger);">Due in ${Math.ceil(diff/60000)}m</span>`;
        else if (diff < 86400000) due = `<span style="color:var(--warning);">Due in ${Math.ceil(diff/3600000)}h</span>`;
        else due = new Date(a.due_date).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        });
      }

      let gradeBlock = '';
      if (s?.status === 'graded' && s.marks != null) {
        const pct = Math.round((s.marks / (a.total_marks || 100)) * 100);
        const gc = pct >= 80 ? 'var(--success)' : pct >= 60 ? 'var(--warning)' : 'var(--danger)';
        gradeBlock = `<div style="margin-top:12px;padding:10px 14px;background:var(--bg);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;border:1px solid var(--border);">
        <span style="display:flex;align-items:center;gap:5px;font-size:0.78rem;color:var(--text-muted);"><i data-lucide="bar-chart-2" style="width:12px;height:12px;"></i>Your Score</span>
        <span style="font-weight:800;font-size:1.1rem;color:${gc};">${s.marks}<span style="font-size:0.75rem;font-weight:400;color:var(--text-muted);">/${a.total_marks||100}</span> <span style="font-size:0.72rem;color:${gc};">(${pct}%)</span></span>
      </div>`;
      }

      const topicChip = a.topic_title ? `<span style="display:inline-flex;align-items:center;gap:3px;font-size:0.72rem;color:var(--primary);background:rgba(99,102,241,0.08);padding:2px 8px;border-radius:20px;border:1px solid rgba(99,102,241,0.2);"><i data-lucide="file-text" style="width:10px;height:10px;"></i>${escapeHtml(a.topic_title)}</span>` : '';

      let refFiles = a.file_count > 0 ? `<button class="btn btn-ghost btn-sm" onclick="viewAFiles(${a.id},'${escapeHtml(a.title)}')" style="font-size:0.75rem;"><i data-lucide="paperclip" style="width:11px;height:11px;"></i> ${a.file_count} Reference File${a.file_count>1?'s':''}</button>` : '';

      let actions = '';
      if (s?.status === 'graded') {
        actions = `<button class="btn btn-secondary btn-sm" style="flex:1;" onclick="viewGrade(${s.id})"><i data-lucide="message-square" style="width:13px;height:13px;"></i> View Feedback</button>`;
      } else if (s?.status === 'returned') {
        actions = `<button class="btn btn-warning btn-sm" style="flex:1;" onclick="openSubmit(${a.id},'${escapeHtml(a.title)}',${a.total_marks||100},'${a.due_date||''}',${allowLate?1:0})"><i data-lucide="refresh-ccw" style="width:13px;height:13px;"></i> Resubmit</button>
        <button class="btn btn-ghost btn-sm" onclick="viewGrade(${s.id})"><i data-lucide="message-square" style="width:13px;height:13px;"></i></button>`;
      } else if (!s && !locked) {
        actions = `<button class="btn btn-primary btn-sm" style="flex:1;" onclick="openSubmit(${a.id},'${escapeHtml(a.title)}',${a.total_marks||100},'${a.due_date||''}',${allowLate?1:0})"><i data-lucide="upload" style="width:13px;height:13px;"></i> Submit</button>`;
      } else if (!s && locked) {
        actions = `<span style="font-size:0.78rem;color:var(--danger);font-weight:600;display:flex;align-items:center;gap:5px;justify-content:center;flex:1;padding:6px 0;"><i data-lucide="lock" style="width:13px;height:13px;"></i>Submission closed</span>`;
      } else if (s?.status === 'submitted') {
        actions = `<span style="font-size:0.78rem;color:var(--text-muted);display:flex;align-items:center;gap:5px;justify-content:center;flex:1;padding:6px 0;"><i data-lucide="clock" style="width:13px;height:13px;"></i>Awaiting grading</span>`;
      }

      return `<div class="card" style="border-left:3px solid ${border};transition:transform 0.15s,box-shadow 0.15s;padding:18px 20px;position:relative;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-lg)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
      ${s?.id?`<input type="checkbox" class="sub-cb" data-sid="${s.id}" style="position:absolute;top:14px;right:14px;width:16px;height:16px;accent-color:var(--primary);cursor:pointer;display:none;" onchange="onCbChange()">`:''}
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
        <h3 style="font-size:0.88rem;font-weight:700;font-family:Poppins,sans-serif;line-height:1.3;margin:0;">${escapeHtml(a.title)}</h3>
        ${badge}
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;">
        <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.75rem;color:var(--text-muted);"><i data-lucide="layers" style="width:11px;height:11px;"></i>${escapeHtml(a.batch_name||'—')}</span>
        ${topicChip}
      </div>
      ${a.description?`<div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:8px;line-height:1.5;">${escapeHtml(a.description.slice(0,120))}${a.description.length>120?`<span>… <button class="btn btn-ghost btn-sm" style="padding:0 4px;font-size:0.75rem;height:auto;color:var(--primary);" onclick="openDescModal('${escapeHtml(a.title).replace(/'/g,"&#39;")}','${encodeURIComponent(a.description||'')}')">Read more</button></span>`:''}</div>`:''}
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border);">
        <span style="display:flex;align-items:center;gap:4px;font-size:0.75rem;color:var(--text-muted);"><i data-lucide="calendar" style="width:12px;height:12px;"></i>${due}</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:0.78rem;font-weight:600;color:var(--primary);"><i data-lucide="star" style="width:12px;height:12px;"></i>${a.total_marks||100} pts</span>
      </div>
      ${gradeBlock}
      ${s?.file_name ? `<div style="margin-top:10px;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;gap:10px;">
        <div style="flex-shrink:0;">${fileIcon(s.file_name, 16)}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:1px;">Submitted File</div>
          <div style="font-size:0.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(s.file_name)}</div>
          ${s.file_size ? `<div style="font-size:0.7rem;color:var(--text-muted);">${s.file_size > 1048576 ? (s.file_size/1048576).toFixed(1)+' MB' : (s.file_size/1024).toFixed(0)+' KB'}</div>` : ''}
        </div>
        <button class="btn btn-ghost btn-sm" onclick="viewMySubmission(${s.id},'${escapeHtml(s.file_name)}')" style="flex-shrink:0;gap:4px;font-size:0.72rem;">
          <i data-lucide="eye" style="width:12px;height:12px;"></i> Preview
        </button>
      </div>` : ''}
      ${refFiles?`<div style="margin-top:10px;">${refFiles}</div>`:''}
      <div style="display:flex;gap:8px;margin-top:14px;">${actions}</div>
    </div>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('assignments-grid')]
    });
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('a-pagination');
    if (total <= perPage) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage);
    let pgs = '';
    for (let i = 1; i <= tp; i++) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="aPage=${i};loadAssignments();return false;">${i}</a>`;
    el.innerHTML = `<div class="pagination-wrapper"><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="aPage=${page-1};loadAssignments();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="aPage=${page+1};loadAssignments();return false;">›</a></div></div>`;
  }

  function openSubmit(id, title, marks, due, allowLate) {
    const now = Date.now();
    const overdue = due && new Date(due) < now;
    document.getElementById('sm-id').value = id;
    document.getElementById('sm-title').textContent = 'Submit: ' + title;
    document.getElementById('sm-notes').value = '';
    document.getElementById('sm-preview').style.display = 'none';
    document.getElementById('sm-file').value = '';
    document.getElementById('oath-check').checked = false;
    updateOathBtn();
    let dueHtml = '';
    if (due) {
      const d = new Date(due);
      const diff = d - now;
      if (overdue && allowLate) dueHtml = `<span style="display:inline-flex;align-items:center;gap:5px;color:var(--warning);font-weight:600;"><i data-lucide="alert-triangle" style="width:13px;height:13px;"></i>Overdue — late submission allowed</span>`;
      else if (!overdue && diff < 86400000) dueHtml = `<span style="color:var(--warning);">Due in ${Math.ceil(diff/3600000)}h</span>`;
      else dueHtml = d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
    document.getElementById('sm-info').innerHTML = `
    <div style="font-weight:700;font-size:0.9rem;margin-bottom:8px;">${escapeHtml(title)}</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
      <span style="display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;color:var(--text-muted);"><i data-lucide="star" style="width:13px;height:13px;color:var(--primary);"></i>Total marks: <strong>${marks}</strong></span>
      ${due?`<span style="display:inline-flex;align-items:center;gap:5px;font-size:0.8rem;color:var(--text-muted);"><i data-lucide="calendar" style="width:13px;height:13px;"></i>${dueHtml}</span>`:''}
    </div>`;
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('sm-info')]
    });
    Modal.open('submit-modal');
  }

  function requestOathConfirm(e) {
    e.preventDefault();
    const form = document.getElementById('submit-form');
    pendingFormData = new FormData(form);
    pendingFormData.append('action', 'submit');
    pendingFormData.append('csrf_token', window.CSRF_TOKEN);
    Modal.close('submit-modal');
    setTimeout(() => Modal.open('oath-modal'), 220);
  }

  function updateOathBtn() {
    const btn = document.getElementById('oath-confirm-btn');
    const checked = document.getElementById('oath-check').checked;
    btn.disabled = !checked;
    btn.style.opacity = checked ? '1' : '0.5';
    btn.style.cursor = checked ? 'pointer' : 'not-allowed';
  }

  async function doSubmit() {
    if (!document.getElementById('oath-check').checked) {
      Toast.warning('Please check the pledge first');
      return;
    }
    const btn = document.getElementById('oath-confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="btn-text">Submitting…</span>';
    try {
      const res = await fetch(window.LMS_BASE + '/ajax/submissions.ajax.php', {
        method: 'POST',
        body: pendingFormData
      });
      const data = await res.json();
      if (data.status === 'success') {
        Toast.success('Assignment submitted successfully!');
        Modal.close('oath-modal');
        pendingFormData = null;
        loadAssignments();
      } else {
        Toast.error(data.message);
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check-circle" style="width:15px;height:15px;"></i> Confirm & Submit';
        if (window.lucide) lucide.createIcons({
          nodes: [btn]
        });
      }
    } catch (err) {
      Toast.error('Request failed');
      btn.disabled = false;
    }
  }

  async function viewGrade(subId) {
    Modal.open('grade-view');
    const body = document.getElementById('gv-body');
    body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">Loading…</div>';
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', {
        action: 'get_one',
        submission_id: subId
      });
      if (res.status !== 'success') {
        body.innerHTML = '<div style="color:var(--danger);">Failed to load</div>';
        return;
      }
      const s = res.data;
      const pct = s.marks != null ? Math.round((s.marks / (s.total_marks || 100)) * 100) : null;
      const gc = pct >= 80 ? 'var(--success)' : pct >= 60 ? 'var(--warning)' : 'var(--danger)';
      body.innerHTML = `
      ${pct!==null?`<div style="text-align:center;margin-bottom:24px;">
        <div style="width:80px;height:80px;border-radius:50%;background:conic-gradient(${gc} ${pct*3.6}deg,var(--bg-hover) 0deg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;position:relative;">
          <div style="width:62px;height:62px;border-radius:50%;background:var(--bg-card);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;color:${gc};">${pct}%</div>
        </div>
        <div style="font-size:1.4rem;font-weight:800;color:${gc};">${s.marks} / ${s.total_marks||100}</div>
        <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:4px;justify-content:center;flex-wrap:wrap;">
          <i data-lucide="calendar-check" style="width:12px;height:12px;"></i>Graded ${s.graded_at?new Date(s.graded_at).toLocaleDateString():''}
          ${s.graded_by_name?`&nbsp;·&nbsp;<i data-lucide="user-check" style="width:12px;height:12px;"></i><strong>${escapeHtml(s.graded_by_name)}</strong>`:''}
        </div>
      </div>`:``}
      ${s.feedback?`<div style="padding:16px;background:var(--bg);border-radius:var(--radius);border-left:3px solid var(--primary);">
        <div style="display:flex;align-items:center;gap:6px;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;"><i data-lucide="message-square" style="width:12px;height:12px;"></i>Teacher Feedback</div>
        <div style="font-size:0.875rem;line-height:1.7;color:var(--text-secondary);">${escapeHtml(s.feedback)}</div>
      </div>`:'<div style="text-align:center;color:var(--text-muted);padding:12px;">No feedback provided</div>'}`;
      // Add download button to footer if file exists
      const gvFooter = document.getElementById('gv-footer');
      if (gvFooter) {
        const existingDl = gvFooter.querySelector('.gv-dl-btn');
        if (existingDl) existingDl.remove();
        if (s.file_path) {
          const dlBtn = document.createElement('a');
          dlBtn.className = 'btn btn-primary btn-sm gv-dl-btn';
          dlBtn.href = window.LMS_BASE + '/download.php?type=submission&id=' + s.id;
          dlBtn.setAttribute('download', s.file_name || 'submission');
          dlBtn.innerHTML = '<i data-lucide="download" style="width:13px;height:13px;"></i> Download My File';
          gvFooter.insertBefore(dlBtn, gvFooter.firstChild);
        }
      }
      if (window.lucide) lucide.createIcons({
        nodes: [body, document.getElementById('gv-footer')]
      });
    } catch (err) {
      body.innerHTML = '<div style="color:var(--danger);">Error loading</div>';
    }
  }

  async function viewMySubmission(subId, fname) {
    // Use the global LMSViewer — same viewer used for topic/assignment files
    const dlUrl = window.LMS_BASE + '/download.php?type=submission&id=' + subId;
    if (window.LMSViewer) {
      window.LMSViewer.open(subId, 'submission', fname, dlUrl);
      return;
    }
    // Fallback: straight download if viewer not loaded
    window.location.href = dlUrl;
  }

  async function viewAFiles(assignId, title) {
    document.getElementById('afiles-title').textContent = 'Reference Files: ' + title;
    Modal.open('afiles-modal');
    const body = document.getElementById('afiles-body');
    body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'get_one',
      assignment_id: assignId
    });
    if (res.status !== 'success') {
      body.innerHTML = '<div style="color:var(--danger);">Error</div>';
      return;
    }
    const files = (res.data.files || []).filter(f => f.status !== 'inactive');
    if (!files.length) {
      body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">No reference files available</div>';
      return;
    }
    body.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;">' + files.map(f => {
      const ext = (f.file_name || '').split('.').pop();
      const sz = f.file_size ? (f.file_size > 1048576 ? (f.file_size / 1048576).toFixed(1) + ' MB' : (f.file_size / 1024).toFixed(0) + ' KB') : '';
      return `<div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
      <div style="flex-shrink:0;">${fileIcon(ext)}</div>
      <div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:0.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>${sz?`<div style="font-size:0.72rem;color:var(--text-muted);">${sz}</div>`:''}</div>
      <button class="btn btn-secondary btn-sm lms-view-btn" style="padding:5px 12px;"
        data-fid="${f.id}" data-ftype="assignment" data-fname="${escapeHtml(f.file_name)}" title="View">
        <i data-lucide="eye" style="width:13px;height:13px;"></i> View
      </button>
      <a href="<?= BASE_PATH ?>/download.php?type=assignment&id=${f.id}" class="btn btn-primary btn-sm"><i data-lucide="download" style="width:13px;height:13px;"></i> Download</a>
    </div>`;
    }).join('') + '</div>';
    if (window.lucide) lucide.createIcons({
      nodes: [body]
    });
  }

  // File drop for submit modal
  const drop = document.getElementById('sm-drop'),
    fi = document.getElementById('sm-file');
  drop.addEventListener('click', () => fi.click());
  drop.addEventListener('dragover', e => {
    e.preventDefault();
    drop.classList.add('dragover');
  });
  drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
  drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.classList.remove('dragover');
    if (e.dataTransfer.files[0]) {
      fi.files = e.dataTransfer.files;
      showPreview(fi.files[0]);
    }
  });
  fi.addEventListener('change', e => {
    if (e.target.files[0]) showPreview(e.target.files[0]);
  });

  function showPreview(f) {
    const p = document.getElementById('sm-preview');
    p.style.display = 'block';
    const ext = f.name.split('.').pop();
    p.innerHTML = `<div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:var(--radius);border:1.5px solid var(--primary);">
    <div style="flex-shrink:0;">${fileIcon(ext)}</div>
    <div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:0.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.name)}</div><div style="font-size:0.72rem;color:var(--text-muted);">${formatBytes(f.size)}</div></div>
    <button type="button" onclick="clearFile()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);flex-shrink:0;"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
  </div>`;
    if (window.lucide) lucide.createIcons({
      nodes: [p]
    });
  }

  function clearFile() {
    fi.value = '';
    document.getElementById('sm-preview').style.display = 'none';
  }

  function formatBytes(b) {
    if (!b) return '0 B';
    const k = 1024,
      s = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(b) / Math.log(k));
    return parseFloat((b / Math.pow(k, i)).toFixed(1)) + ' ' + s[i];
  }

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  // ── Bulk download mode ────────────────────────────────────────
  let _bulkMode = false;

  function startBulkMode() {
    _bulkMode = true;
    document.getElementById('bulk-bar').classList.add('visible');
    document.getElementById('bulk-mode-btn').style.display = 'none';
    document.getElementById('select-all-btn').style.display = '';
    document.querySelectorAll('.sub-cb').forEach(cb => cb.style.display = '');
    updateBulkCount();
  }

  function cancelBulkMode() {
    _bulkMode = false;
    document.getElementById('bulk-bar').classList.remove('visible');
    document.getElementById('bulk-mode-btn').style.display = '';
    document.getElementById('select-all-btn').style.display = 'none';
    document.getElementById('sel-all-lbl').textContent = 'Select All';
    document.querySelectorAll('.sub-cb').forEach(cb => {
      cb.checked = false;
      cb.style.display = 'none';
    });
    updateBulkCount();
  }

  function onCbChange() {
    updateBulkCount();
    const allCbs = [...document.querySelectorAll('.sub-cb')];
    const allChecked = allCbs.every(cb => cb.checked);
    document.getElementById('sel-all-lbl').textContent = allChecked ? 'Deselect All' : 'Select All';
  }

  function toggleSelectAll() {
    const allCbs = [...document.querySelectorAll('.sub-cb')];
    const allChecked = allCbs.every(cb => cb.checked);
    allCbs.forEach(cb => cb.checked = !allChecked);
    document.getElementById('sel-all-lbl').textContent = allChecked ? 'Select All' : 'Deselect All';
    updateBulkCount();
  }

  function updateBulkCount() {
    const checked = [...document.querySelectorAll('.sub-cb:checked')];
    const btn = document.getElementById('bulk-dl-btn');
    const countEl = document.getElementById('bulk-count');
    if (countEl) countEl.textContent = checked.length ? `${checked.length} file${checked.length>1?'s':''} selected` : 'Select submissions to download';
    if (btn) btn.disabled = !checked.length;
  }

  function doBulkDownload() {
    const ids = [...document.querySelectorAll('.sub-cb:checked')].map(cb => cb.dataset.sid);
    if (!ids.length) { Toast.warning('Select at least one submission'); return; }
    document.getElementById('bulk-ids-input').value = ids.join(',');
    document.getElementById('bulk-dl-form').submit();
    Toast.success('Preparing your ZIP download…');
  }

  // ── Description modal ─────────────────────────────────────────
  let _descRaw = '';

  function openDescModal(title, encodedDesc) {
    _descRaw = decodeURIComponent(encodedDesc);
    document.getElementById('desc-modal-title').textContent = title;
    document.getElementById('desc-modal-body').textContent = _descRaw;
    Modal.open('desc-modal');
  }

  function downloadDescription() {
    if (!_descRaw) return;
    const title = document.getElementById('desc-modal-title').textContent || 'description';
    const blob = new Blob([_descRaw], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = title.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'') + '.txt';
    a.click();
    URL.revokeObjectURL(a.href);
  }

  document.addEventListener('DOMContentLoaded', loadAssignments);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>