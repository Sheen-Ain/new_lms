// ============================================================
// MODAL SYSTEM
// ============================================================

const Modal = (() => {
  const openModals = [];

  function open(modalId) {
    const overlay = document.getElementById(modalId + '-overlay') || document.getElementById(modalId);
    if (!overlay) return console.warn('Modal not found:', modalId);

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    openModals.push(modalId);

    // Trap focus
    const focusable = overlay.querySelectorAll('input,select,textarea,button,[tabindex]');
    if (focusable.length) focusable[0].focus();

    // Close on overlay click (not modal itself)
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) close(modalId);
    }, { once: true });
  }

  function close(modalId) {
    const overlay = document.getElementById(modalId + '-overlay') || document.getElementById(modalId);
    if (!overlay) return;

    overlay.classList.remove('open');
    const idx = openModals.indexOf(modalId);
    if (idx > -1) openModals.splice(idx, 1);

    if (openModals.length === 0) {
      document.body.style.overflow = '';
    }
  }

  function closeAll() {
    [...openModals].forEach(id => close(id));
  }

  // Close on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && openModals.length) {
      close(openModals[openModals.length - 1]);
    }
  });

  // Wire up close buttons
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-modal-close]');
    if (btn) {
      const id = btn.dataset.modalClose;
      close(id);
    }
  });

  // Wire up open buttons
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-modal-open]');
    if (btn) {
      const id = btn.dataset.modalOpen;
      open(id);
    }
  });

  /**
   * Programmatic confirm modal
   */
  function confirm({
    title         = 'Are you sure?',
    message       = 'This action cannot be undone.',
    confirmText   = 'Confirm',
    cancelText    = 'Cancel',
    confirmClass  = 'btn-primary',
    icon          = '⚠️',
    iconClass     = 'modal-icon-warning',
    onConfirm     = () => {},
    onCancel      = () => {},
  } = {}) {
    // Remove existing confirm modal
    document.getElementById('confirm-modal-overlay')?.remove();

    const html = `
      <div class="modal-overlay" id="confirm-modal-overlay">
        <div class="modal modal-sm">
          <div class="modal-header">
            <div class="modal-icon ${iconClass}">${icon}</div>
            <h3 class="modal-title">${escapeHtml(title)}</h3>
            <button class="modal-close" data-modal-close="confirm-modal">
              <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
          </div>
          <div class="modal-body">
            <p style="color:var(--text-secondary);font-size:0.9rem;line-height:1.6;">${message}</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" id="confirm-cancel-btn">${escapeHtml(cancelText)}</button>
            <button class="btn ${confirmClass}" id="confirm-ok-btn">
              <span class="btn-text">${escapeHtml(confirmText)}</span>
            </button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
    if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('confirm-modal-overlay')] });

    const overlay = document.getElementById('confirm-modal-overlay');
    const okBtn   = document.getElementById('confirm-ok-btn');
    const cancelBtn = document.getElementById('confirm-cancel-btn');

    setTimeout(() => overlay.classList.add('open'), 10);
    document.body.style.overflow = 'hidden';

    const cleanup = () => {
      overlay.classList.remove('open');
      setTimeout(() => { overlay.remove(); document.body.style.overflow = ''; }, 300);
    };

    okBtn.addEventListener('click', async () => {
      okBtn.disabled = true;
      okBtn.classList.add('btn-loading');
      try {
        await onConfirm();
      } finally {
        okBtn.disabled = false;
        okBtn.classList.remove('btn-loading');
        cleanup();
      }
    });

    cancelBtn.addEventListener('click', () => { onCancel(); cleanup(); });
    overlay.addEventListener('click', (e) => { if (e.target === overlay) { onCancel(); cleanup(); } });
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { onCancel(); cleanup(); document.removeEventListener('keydown', esc); }
    });
  }

  function escapeHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /**
   * Reset a form inside a modal
   */
  function resetForm(modalId) {
    const overlay = document.getElementById(modalId + '-overlay') || document.getElementById(modalId);
    overlay?.querySelectorAll('form').forEach(f => f.reset());
    overlay?.querySelectorAll('.form-error').forEach(e => e.textContent = '');
    overlay?.querySelectorAll('.form-control.error').forEach(e => e.classList.remove('error'));
  }

  /**
   * Fill form fields from a data object
   */
  function fillForm(modalId, data) {
    const overlay = document.getElementById(modalId + '-overlay') || document.getElementById(modalId);
    if (!overlay) return;

    Object.entries(data).forEach(([key, val]) => {
      const field = overlay.querySelector(`[name="${key}"]`);
      if (!field) return;

      if (field.type === 'checkbox') {
        field.checked = !!val;
      } else if (field.type === 'radio') {
        overlay.querySelectorAll(`[name="${key}"]`).forEach(r => r.checked = r.value == val);
      } else {
        field.value = val ?? '';
      }
    });
  }

  return { open, close, closeAll, confirm, resetForm, fillForm };
})();

window.Modal = Modal;
