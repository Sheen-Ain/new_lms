// ============================================================
// TOAST NOTIFICATION SYSTEM
// ============================================================

const Toast = (() => {
  let container = null;
  let queue = [];
  const MAX_VISIBLE = 3;
  let visible = 0;

  const ICONS = {
    success: '<i data-lucide="check-circle-2"></i>',
    error:   '<i data-lucide="x-circle"></i>',
    warning: '<i data-lucide="alert-triangle"></i>',
    info:    '<i data-lucide="info"></i>',
  };

  const TITLES = {
    success: 'Success',
    error:   'Error',
    warning: 'Warning',
    info:    'Info',
  };

  function getContainer() {
    if (!container) {
      container = document.getElementById('toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
      }
    }
    return container;
  }

  function show(type, message, options = {}) {
    const {
      title     = TITLES[type] || 'Notification',
      duration  = 4000,
      persistent = false,
    } = options;

    if (visible >= MAX_VISIBLE) {
      queue.push({ type, message, options });
      return;
    }

    visible++;
    const c = getContainer();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const dur = persistent ? 0 : duration;

    toast.innerHTML = `
      <div class="toast-border"></div>
      <div class="toast-icon">${ICONS[type] || ICONS.info}</div>
      <div class="toast-content">
        <div class="toast-title">${escapeHtmlT(title)}</div>
        ${message ? `<div class="toast-message">${escapeHtmlT(message)}</div>` : ''}
      </div>
      <button class="toast-close" aria-label="Close">
        <i data-lucide="x" style="width:14px;height:14px;"></i>
      </button>
      ${!persistent ? `<div class="toast-progress" style="animation-duration:${dur}ms;"></div>` : ''}
    `;

    c.appendChild(toast);
    if (window.lucide) lucide.createIcons({ nodes: [toast] });

    // Trigger animation
    requestAnimationFrame(() => {
      requestAnimationFrame(() => toast.classList.add('show'));
    });

    const dismiss = () => {
      toast.classList.remove('show');
      toast.classList.add('hide');
      setTimeout(() => {
        toast.remove();
        visible = Math.max(0, visible - 1);
        if (queue.length > 0) {
          const next = queue.shift();
          show(next.type, next.message, next.options);
        }
      }, 350);
    };

    toast.querySelector('.toast-close').addEventListener('click', dismiss);

    if (!persistent && dur > 0) {
      setTimeout(dismiss, dur);
    }

    return { dismiss };
  }

  function escapeHtmlT(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  return {
    success: (message, options) => show('success', message, options),
    error:   (message, options) => show('error',   message, options),
    warning: (message, options) => show('warning', message, options),
    info:    (message, options) => show('info',    message, options),
    show,
  };
})();

window.Toast = Toast;
