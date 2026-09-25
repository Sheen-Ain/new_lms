/* ============================================================
   EduFlow V2 — core.js
   Utilities, AJAX client, toasts, modals, confirmations,
   dropdowns, tabs, theme, sidebar. No framework, no build step.
   ============================================================ */
(function (window, document) {
  'use strict';

  var EF = window.EF || {};
  EF.config = EF.config || { base: '', csrf: '', role: '', userId: 0, urls: {} };

  /* ── Utilities ───────────────────────────────────────────── */
  var Util = {
    escape: function (value) {
      if (value === null || value === undefined) return '';
      return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },
    bytes: function (bytes) {
      bytes = Number(bytes) || 0;
      if (bytes <= 0) return '0 B';
      var units = ['B', 'KB', 'MB', 'GB', 'TB'];
      var i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      var size = bytes / Math.pow(1024, i);
      return (size >= 100 ? Math.round(size) : size.toFixed(2)) + ' ' + units[i];
    },
    timeAgo: function (value) {
      if (!value) return 'Never';
      var then = new Date(String(value).replace(' ', 'T'));
      if (isNaN(then.getTime())) return '—';
      var seconds = Math.floor((Date.now() - then.getTime()) / 1000);
      if (seconds < 45) return 'Just now';
      var steps = [['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60]];
      for (var i = 0; i < steps.length; i++) {
        if (seconds >= steps[i][1]) {
          var n = Math.floor(seconds / steps[i][1]);
          return n + ' ' + steps[i][0] + (n > 1 ? 's' : '') + ' ago';
        }
      }
      return 'Just now';
    },
    debounce: function (fn, wait) {
      var timer;
      return function () {
        var args = arguments, self = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(self, args); }, wait || 250);
      };
    },
    url: function (path, query) {
      var target = EF.config.base + '/index.php?r=' + encodeURIComponent('/' + String(path).replace(/^\/+/, ''));
      if (query) {
        Object.keys(query).forEach(function (key) {
          var value = query[key];
          if (value !== null && value !== undefined && value !== '') {
            target += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
          }
        });
      }
      return target;
    },
    initials: function (name) {
      var parts = String(name || 'U').trim().split(/\s+/);
      var out = '';
      for (var i = 0; i < parts.length && out.length < 2; i++) {
        if (parts[i]) out += parts[i].charAt(0).toUpperCase();
      }
      return out || 'U';
    },
    avatar: function (user, size) {
      size = size || 30;
      var name = (user && user.full_name) || 'User';
      var pic = (user && user.profile_picture) ? user.profile_picture : '';
      var style = 'width:' + size + 'px;height:' + size + 'px;font-size:' + Math.max(10, Math.round(size * 0.38)) + 'px;';
      if (pic) {
        return '<span class="avatar" style="' + style + '"><img src="' + EF.config.base +
          '/uploads/profiles/' + encodeURI(pic) + '" alt="' + Util.escape(name) + '" loading="lazy"></span>';
      }
      return '<span class="avatar" style="' + style + '">' + Util.escape(Util.initials(name)) + '</span>';
    },
    badge: function (text, tone) {
      return '<span class="badge badge-' + (tone || 'muted') + '">' + Util.escape(text) + '</span>';
    },
    empty: function (message, sub, iconName) {
      return '<div class="empty"><div class="empty-icon">' + EF.icon(iconName || 'inbox', 22) + '</div>' +
        '<h3>' + Util.escape(message) + '</h3>' + (sub ? '<p>' + Util.escape(sub) + '</p>' : '') + '</div>';
    }
  };
  EF.util = Util;

  /* ── Inline icons for JS-rendered markup ─────────────────── */
  var ICON_PATHS = {
    search: '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.4 15.4 4.1 4.1"/>',
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    edit: '<path d="M4 20.2h4.2L19 9.4a2.1 2.1 0 0 0 0-3l-1.4-1.4a2.1 2.1 0 0 0-3 0L3.8 16z"/><path d="m14.2 6 3.8 3.8"/>',
    trash: '<path d="M4.5 7h15"/><path d="M9.5 4.2h5"/><path d="M6.2 7l.9 12.1A2 2 0 0 0 9.1 21h5.8a2 2 0 0 0 2-1.9L17.8 7"/>',
    download: '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 19.5h15"/>',
    upload: '<path d="M12 20.5v-11"/><path d="M7.5 13.5 12 9l4.5 4.5"/><path d="M4.5 4.5h15"/>',
    eye: '<path d="M2.8 12S6.6 5.8 12 5.8 21.2 12 21.2 12 17.4 18.2 12 18.2 2.8 12 2.8 12z"/><circle cx="12" cy="12" r="2.9"/>',
    check: '<path d="m5 12.5 4.5 4.5L19 7"/>',
    checkCircle: '<circle cx="12" cy="12" r="8.8"/><path d="m8.2 12.2 2.6 2.6 5-5.2"/>',
    xCircle: '<circle cx="12" cy="12" r="8.8"/><path d="m9.2 9.2 5.6 5.6"/><path d="m14.8 9.2-5.6 5.6"/>',
    alert: '<path d="M10.3 4.4 2.9 17.1A2 2 0 0 0 4.6 20.2h14.8a2 2 0 0 0 1.7-3.1L13.7 4.4a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4.2"/><path d="M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="8.8"/><path d="M12 11v5.5"/><path d="M12 7.8h.01"/>',
    clock: '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.4V12l3.4 2.2"/>',
    close: '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
    chevronLeft: '<path d="m14.5 6.5-5.5 5.5 5.5 5.5"/>',
    chevronRight: '<path d="m9.5 6.5 5.5 5.5-5.5 5.5"/>',
    chevronDown: '<path d="m6.5 9.5 5.5 5.5 5.5-5.5"/>',
    refresh: '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4v4.5h-4.5"/>',
    fileText: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/>',
    user: '<circle cx="12" cy="8.2" r="3.6"/><path d="M4.8 20.5v-1a4.9 4.9 0 0 1 4.9-4.9h4.6a4.9 4.9 0 0 1 4.9 4.9v1"/>',
    play: '<path d="M8 5.6 18.5 12 8 18.4z"/>',
    inbox: '<path d="M3.5 13.5 6 5a2 2 0 0 1 1.9-1.4h8.2A2 2 0 0 1 18 5l2.5 8.5"/><path d="M3.5 13.5h4l1 2.5h7l1-2.5h4v3A2.5 2.5 0 0 1 18 19H6a2.5 2.5 0 0 1-2.5-2.5z"/>',
    flag: '<path d="M6 21V4"/><path d="M6 4.5h11.5l-1.8 4 1.8 4H6z"/>',
    save: '<path d="M5.5 4h9.6L20 8.9v9.6A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4z"/><path d="M8 4v5.5h6.5V4"/><rect x="8" y="13" width="8" height="7"/>',
    mail: '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m4 7.5 8 5.6 8-5.6"/>'
  };
  var ICON_ALIAS = { success: 'checkCircle', error: 'xCircle', warning: 'alert', danger: 'alert', delete: 'trash', add: 'plus' };

  EF.icon = function (name, size, className) {
    var key = ICON_ALIAS[name] || name;
    var path = ICON_PATHS[key] || ICON_PATHS.fileText;
    size = size || 16;
    return '<svg class="icon' + (className ? ' ' + className : '') + '" width="' + size + '" height="' + size +
      '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"' +
      ' stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
  };

  /* ── Toasts ──────────────────────────────────────────────── */
  var Toast = {
    region: null,
    ensure: function () {
      if (!Toast.region) {
        Toast.region = document.querySelector('.toast-region');
        if (!Toast.region) {
          Toast.region = document.createElement('div');
          Toast.region.className = 'toast-region';
          Toast.region.setAttribute('role', 'status');
          Toast.region.setAttribute('aria-live', 'polite');
          document.body.appendChild(Toast.region);
        }
      }
      return Toast.region;
    },
    show: function (type, message, title, timeout) {
      if (!message) return null;
      type = type || 'info';
      var region = Toast.ensure();
      var icons = { success: 'checkCircle', error: 'xCircle', warning: 'alert', info: 'info' };
      var labels = { success: 'Success', error: 'Error', warning: 'Attention', info: 'Notice' };

      var el = document.createElement('div');
      el.className = 'toast toast-' + type;
      el.innerHTML =
        '<span class="toast-icon">' + EF.icon(icons[type] || 'info', 17) + '</span>' +
        '<div class="toast-body">' +
          '<div class="toast-title">' + Util.escape(title || labels[type] || 'Notice') + '</div>' +
          '<div class="toast-text">' + Util.escape(message) + '</div>' +
        '</div>' +
        '<button type="button" class="toast-close" aria-label="Dismiss">' + EF.icon('close', 14) + '</button>';

      region.appendChild(el);

      var close = function () {
        el.classList.add('is-leaving');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 180);
      };
      el.querySelector('.toast-close').addEventListener('click', close);
      setTimeout(close, timeout || 5200);
      return el;
    },
    success: function (m, t) { return Toast.show('success', m, t); },
    error: function (m, t) { return Toast.show('error', m, t); },
    warning: function (m, t) { return Toast.show('warning', m, t); },
    info: function (m, t) { return Toast.show('info', m, t); }
  };
  EF.toast = Toast;

  /* ── Modal + confirmation dialog ─────────────────────────── */
  var Modal = {
    open: function (options) {
      options = options || {};
      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop is-open';

      var head = options.title === false ? '' :
        '<div class="modal-head">' +
          '<div>' +
            '<h2>' + Util.escape(options.title || '') + '</h2>' +
            (options.subtitle ? '<div class="sub">' + Util.escape(options.subtitle) + '</div>' : '') +
          '</div>' +
          '<button type="button" class="modal-close" data-modal-close aria-label="Close">' + EF.icon('close', 16) + '</button>' +
        '</div>';

      backdrop.innerHTML =
        '<div class="modal' + (options.size ? ' modal-' + options.size : '') + '" role="dialog" aria-modal="true">' +
          head +
          '<div class="modal-body' + (options.flush ? ' flush' : '') + '">' + (options.body || '') + '</div>' +
          (options.footer === false ? '' :
            '<div class="modal-foot' + (options.footerBetween ? ' between' : '') + '">' +
              (options.footer || '<button type="button" class="btn" data-modal-close>Close</button>') +
            '</div>') +
        '</div>';

      document.body.appendChild(backdrop);
      document.body.style.overflow = 'hidden';

      var close = function () {
        backdrop.classList.remove('is-open');
        document.body.style.overflow = '';
        setTimeout(function () { if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }, 120);
        if (typeof options.onClose === 'function') options.onClose();
      };

      backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop || event.target.closest('[data-modal-close]')) close();
      });
      backdrop.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
      });

      var modal = {
        element: backdrop,
        body: backdrop.querySelector('.modal-body'),
        close: close,
        find: function (selector) { return backdrop.querySelector(selector); },
        all: function (selector) { return Array.prototype.slice.call(backdrop.querySelectorAll(selector)); }
      };
      if (typeof options.onOpen === 'function') options.onOpen(modal);
      return modal;
    },

    /** Reusable confirmation component used by every destructive action. */
    confirm: function (options) {
      if (typeof options === 'string') options = { message: options };
      options = options || {};
      var tone = options.tone || 'warning';

      return new Promise(function (resolve) {
        var settled = false;
        var modal = Modal.open({
          size: 'sm',
          title: false,
          flush: true,
          body: '<div class="confirm-dialog">' +
              '<div class="confirm-icon' + (tone === 'danger' ? ' is-danger' : (tone === 'info' ? ' is-info' : '')) + '">' +
                EF.icon(tone === 'info' ? 'info' : 'alert', 22) +
              '</div>' +
              '<h2>' + Util.escape(options.title || 'Please confirm') + '</h2>' +
              '<p>' + (options.html || Util.escape(options.message || 'This action cannot be undone.')) + '</p>' +
            '</div>',
          footer: '<button type="button" class="btn" data-modal-close>' + Util.escape(options.cancelText || 'Cancel') + '</button>' +
            '<button type="button" class="btn btn-primary" data-confirm-ok>' + Util.escape(options.confirmText || 'Confirm') + '</button>',
          onOpen: function (m) {
            var ok = m.find('[data-confirm-ok]');
            ok.focus();
            ok.addEventListener('click', function () { settled = true; m.close(); resolve(true); });
          },
          onClose: function () { if (!settled) resolve(false); }
        });
        return modal;
      });
    },

    /** Single-input prompt (rename, rejection reason, marks, ...). */
    prompt: function (options) {
      options = options || {};
      return new Promise(function (resolve) {
        var settled = false;
        Modal.open({
          size: 'sm',
          title: options.title || 'Enter a value',
          body: '<div class="field" style="margin:0">' +
              (options.label ? '<label class="field-label" for="prompt-input">' + Util.escape(options.label) + '</label>' : '') +
              (options.multiline
                ? '<textarea id="prompt-input" class="textarea" rows="4" placeholder="' + Util.escape(options.placeholder || '') + '"></textarea>'
                : '<input id="prompt-input" class="input" type="' + (options.type || 'text') + '" placeholder="' + Util.escape(options.placeholder || '') + '">') +
              (options.help ? '<div class="field-hint">' + Util.escape(options.help) + '</div>' : '') +
            '</div>',
          footer: '<button type="button" class="btn" data-modal-close>Cancel</button>' +
            '<button type="button" class="btn btn-primary" data-prompt-ok>' + Util.escape(options.confirmText || 'Save') + '</button>',
          onOpen: function (m) {
            var input = m.find('#prompt-input');
            if (options.value) input.value = options.value;
            input.focus();
            m.find('[data-prompt-ok]').addEventListener('click', function () {
              var value = input.value.trim();
              if (options.required !== false && value === '') {
                input.classList.add('is-invalid');
                return;
              }
              settled = true;
              m.close();
              resolve(value);
            });
          },
          onClose: function () { if (!settled) resolve(null); }
        });
      });
    }
  };
  EF.modal = Modal;

  /* ── AJAX client ─────────────────────────────────────────── */
  var Api = {
    /**
     * POST to an application route with CSRF protection.
     * Resolves with the parsed envelope, rejects on transport failure.
     */
    post: function (path, payload, options) {
      options = options || {};
      var query = options.query || {};
      var url = Util.url(path, query);
      var body = payload instanceof FormData ? payload : new FormData();

      if (!(payload instanceof FormData) && payload) {
        Object.keys(payload).forEach(function (key) {
          var value = payload[key];
          if (value === null || value === undefined) return;
          if (Array.isArray(value)) {
            value.forEach(function (item) { body.append(key + '[]', item); });
          } else {
            body.append(key, value);
          }
        });
      }
      if (!body.has('_token')) body.append('_token', EF.config.csrf);

      if (options.progress && typeof options.progress === 'function') {
        return Api.xhr(url, body, options);
      }

      return fetch(url, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (response) {
        return response.text().then(function (text) {
          var data;
          try { data = JSON.parse(text); }
          catch (error) {
            throw new Error('Unexpected server response. Please refresh and try again.');
          }
          if (response.status === 401 || data.message === 'Unauthorized') {
            EF.toast.error('Your session expired. Redirecting to sign in…');
            setTimeout(function () { window.location.href = Util.url('/login'); }, 1200);
            throw new Error(data.message);
          }
          return data;
        });
      });
    },

    /** XHR variant so uploads can report progress. */
    xhr: function (url, body, options) {
      return new Promise(function (resolve, reject) {
        var request = new XMLHttpRequest();
        request.open('POST', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');

        if (options.progress) {
          request.upload.addEventListener('progress', function (event) {
            if (event.lengthComputable) {
              options.progress(Math.round((event.loaded / event.total) * 100));
            }
          });
        }
        request.addEventListener('load', function () {
          var data;
          try { data = JSON.parse(request.responseText); }
          catch (error) { reject(new Error('Unexpected server response.')); return; }
          resolve(data);
        });
        request.addEventListener('error', function () { reject(new Error('Network error. Please retry.')); });
        request.send(body);
      });
    },

    /**
     * Submit wrapper: posts, toasts the outcome, returns the data.
     * onSuccess / onFailure callbacks may abort the default behaviour.
     */
    submit: function (path, payload, options) {
      options = options || {};
      var button = options.button;
      if (button) {
        button.classList.add('is-loading');
        button.disabled = true;
      }

      var restore = function () {
        if (button) {
          button.classList.remove('is-loading');
          button.disabled = false;
        }
      };

      return Api.post(path, payload, options)
        .then(function (response) {
          restore();
          if (response.status === 'success') {
            if (options.silent !== true) EF.toast.success(response.message);
            if (typeof options.onSuccess === 'function') return options.onSuccess(response);
            if (options.reload !== false) {
              if (options.redirect) {
                window.location.href = options.redirect;
              } else {
                window.location.reload();
              }
            }
            return response;
          }

          if (response.data && response.data.errors && options.showErrors !== false) {
            FormValidator.applyErrors(options.form, response.data.errors);
          }
          EF.toast.error(response.message || 'The action could not be completed.');
          if (typeof options.onFailure === 'function') options.onFailure(response);
          return response;
        })
        .catch(function (error) {
          restore();
          EF.toast.error(error.message || 'Network error.');
          throw error;
        });
    },

    get: function (path, query, options) {
      options = options || {};
      return fetch(Util.url(path, query), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (response) { return response.json(); })
        .catch(function () {
          if (options.silent) return { status: 'error' };
          EF.toast.error('Could not load data.');
          return { status: 'error' };
        });
    }
  };
  EF.api = Api;

  /* ── Client-side form validation (mirrors the server rules) ─ */
  var FormValidator = {
    patterns: {
      email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
      cnic: /^\d{5}-?\d{7}-?\d$/,
      phone: /^[0-9+\-\s()]{10,15}$/
    },

    /** data-rules="required|min:8|email" on any input. */
    validateField: function (input) {
      var rules = (input.getAttribute('data-rules') || '').split('|').filter(Boolean);
      if (!rules.length) return true;

      var value = input.type === 'checkbox' ? (input.checked ? '1' : '') : String(input.value || '').trim();
      var label = input.getAttribute('data-label') || input.getAttribute('placeholder') || 'This field';

      for (var i = 0; i < rules.length; i++) {
        var rule = rules[i];
        var param = null;
        if (rule.indexOf(':') > -1) {
          var parts = rule.split(':');
          rule = parts[0];
          param = parts[1];
        }

        var message = null;
        if (rule === 'required' && value === '') {
          message = label + ' is required.';
        } else if (value !== '') {
          if (rule === 'email' && !FormValidator.patterns.email.test(value)) {
            message = 'Enter a valid email address.';
          } else if (rule === 'min' && value.length < Number(param)) {
            message = label + ' must be at least ' + param + ' characters.';
          } else if (rule === 'max' && value.length > Number(param)) {
            message = label + ' may not exceed ' + param + ' characters.';
          } else if (rule === 'password' && (value.length < 8 || !/[A-Za-z]/.test(value) || !/\d/.test(value))) {
            message = 'Use at least 8 characters with letters and numbers.';
          } else if (rule === 'cnic' && !FormValidator.patterns.cnic.test(value)) {
            message = 'CNIC must be 13 digits (12345-1234567-1).';
          } else if (rule === 'phone' && !FormValidator.patterns.phone.test(value)) {
            message = 'Enter a valid phone number.';
          } else if (rule === 'number' && isNaN(Number(value))) {
            message = label + ' must be a number.';
          } else if (rule === 'same' && value !== String((document.getElementById(param) || {}).value || '')) {
            message = 'Values do not match.';
          }
        }

        if (message) {
          FormValidator.markError(input, message);
          return false;
        }
      }
      FormValidator.clearError(input);
      return true;
    },

    markError: function (input, message) {
      input.classList.add('is-invalid');
      var holder = input.closest('.field') || input.parentNode;
      var box = holder.querySelector('.field-error');
      if (!box) {
        box = document.createElement('div');
        box.className = 'field-error';
        holder.appendChild(box);
      }
      box.textContent = message;
    },

    clearError: function (input) {
      input.classList.remove('is-invalid');
      var holder = input.closest('.field') || input.parentNode;
      var box = holder.querySelector('.field-error');
      if (box) box.textContent = '';
    },

    /** Validate a whole form; returns true when everything passes. */
    validateForm: function (form) {
      var fields = Array.prototype.slice.call(form.querySelectorAll('[data-rules]'));
      var ok = true;
      fields.forEach(function (input) {
        if (!FormValidator.validateField(input)) ok = false;
      });
      if (!ok) {
        var first = form.querySelector('.is-invalid');
        if (first) first.focus();
      }
      return ok;
    },

    /** Paint server-side errors onto matching named fields. */
    applyErrors: function (form, errors) {
      if (!form || !errors) return;
      Object.keys(errors).forEach(function (name) {
        var input = form.querySelector('[name="' + name + '"]');
        if (input) FormValidator.markError(input, errors[name][0] || errors[name]);
      });
    },

    bind: function (form) {
      if (!form || form.dataset.efValidationBound === '1') return;
      form.dataset.efValidationBound = '1';

      form.addEventListener('input', function (event) {
        if (event.target.hasAttribute('data-rules')) FormValidator.validateField(event.target);
      });
      form.addEventListener('blur', function (event) {
        if (event.target.hasAttribute('data-rules')) FormValidator.validateField(event.target);
      }, true);

      form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-validate') === 'server') return;
        if (!FormValidator.validateForm(form)) {
          event.preventDefault();
          EF.toast.warning('Please correct the highlighted fields.');
          return;
        }
        var submit = form.querySelector('[type="submit"]');
        if (submit && !form.hasAttribute('data-no-loading')) {
          submit.classList.add('is-loading');
        }
      });
    }
  };
  EF.form = FormValidator;

  /* ── Dropdowns, tabs, sidebar, theme ─────────────────────── */
  var UI = {
    closeAllDropdowns: function (except) {
      document.querySelectorAll('.dropdown-menu.is-open').forEach(function (menu) {
        if (menu !== except) menu.classList.remove('is-open');
      });
    },

    bindDropdowns: function () {
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-dropdown]');
        if (trigger) {
          event.preventDefault();
          var menu = document.getElementById(trigger.getAttribute('data-dropdown'));
          if (!menu) {
            var wrap = trigger.closest('.dropdown');
            menu = wrap ? wrap.querySelector('.dropdown-menu') : null;
          }
          if (!menu) return;
          var willOpen = !menu.classList.contains('is-open');
          UI.closeAllDropdowns(menu);
          if (willOpen) menu.classList.add('is-open');
          return;
        }
        if (!event.target.closest('.dropdown-menu')) UI.closeAllDropdowns();
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') UI.closeAllDropdowns();
      });
    },

    /** Tabs: [data-tab="key"] buttons with [data-tab-panel="key"] panels. */
    bindTabs: function () {
      document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-tab]');
        if (!tab) return;
        event.preventDefault();
        var group = tab.closest('[data-tab-group]') || document;
        var key = tab.getAttribute('data-tab');

        group.querySelectorAll('[data-tab]').forEach(function (item) {
          item.classList.toggle('is-active', item === tab);
        });
        group.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
          panel.classList.toggle('hidden', panel.getAttribute('data-tab-panel') !== key);
        });
        history.replaceState(null, '', '#' + key);
      });

      var hash = (window.location.hash || '').replace('#', '');
      if (hash) {
        var trigger = document.querySelector('[data-tab="' + hash + '"]');
        if (trigger) trigger.click();
      }
    },

    bindSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      var scrim = document.querySelector('.sidebar-scrim');
      var toggle = document.getElementById('sidebar-toggle');
      if (!sidebar || !toggle) return;

      var close = function () {
        sidebar.classList.remove('is-open');
        if (scrim) scrim.classList.remove('is-open');
      };

      toggle.addEventListener('click', function () {
        var open = !sidebar.classList.contains('is-open');
        sidebar.classList.toggle('is-open', open);
        if (scrim) scrim.classList.toggle('is-open', open);
      });
      if (scrim) scrim.addEventListener('click', close);
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
      });
    },

    bindTheme: function () {
      var apply = function (mode) {
        var resolved = mode;
        if (mode === 'system') {
          resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.setAttribute('data-theme-mode', mode);
        try { localStorage.setItem('eduflow-theme', mode); } catch (error) { /* ignore */ }
      };

      var stored = null;
      try { stored = localStorage.getItem('eduflow-theme'); } catch (error) { /* ignore */ }
      apply(stored || 'system');

      document.querySelectorAll('[data-theme-set]').forEach(function (button) {
        button.addEventListener('click', function () {
          var mode = button.getAttribute('data-theme-set');
          apply(mode);
          EF.api.post('/profile/theme', { theme: mode }).catch(function () { /* silent */ });
        });
      });

      document.querySelectorAll('[data-theme-cycle]').forEach(function (button) {
        button.addEventListener('click', function () {
          var current = document.documentElement.getAttribute('data-theme-mode') || 'system';
          var order = ['system', 'light', 'dark'];
          var next = order[(order.indexOf(current) + 1) % order.length];
          apply(next);
          EF.api.post('/profile/theme', { theme: next }).catch(function () { /* silent */ });
        });
      });
    }
  };
  EF.ui = UI;

  /* ── Confirmation, password toggles, strength meters ─────── */
  UI.bindConfirmations = function () {
    document.addEventListener('click', function (event) {
      var target = event.target.closest('[data-confirm]');
      if (!target) return;
      event.preventDefault();

      var form = target.closest('form');
      EF.modal.confirm({
        message: target.getAttribute('data-confirm'),
        title: target.getAttribute('data-confirm-title') || 'Please confirm',
        tone: target.getAttribute('data-confirm-tone') || 'warning',
        confirmText: target.getAttribute('data-confirm-ok') || 'Confirm'
      }).then(function (ok) {
        if (!ok) return;
        if (form) { form.submit(); return; }
        var href = target.getAttribute('href');
        if (href && href !== '#') window.location.href = href;
      });
    });
  };

  UI.bindPasswordToggles = function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.innerHTML = EF.icon(show ? 'eye-off' : 'eye', 16);
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  };

  UI.bindStrengthMeters = function () {
    document.querySelectorAll('[data-strength]').forEach(function (meter) {
      var input = document.getElementById(meter.getAttribute('data-strength'));
      if (!input) return;
      input.addEventListener('input', function () {
        var value = input.value;
        var score = 0;
        if (value.length >= 8) score++;
        if (/[A-Za-z]/.test(value) && /\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value) && value.length >= 10) score++;
        if (value.length >= 12) score++;
        meter.setAttribute('data-level', String(score));
        var hint = meter.querySelector('[data-strength-text]');
        if (hint) hint.textContent = value ? ['Too short', 'Weak', 'Fair', 'Strong', 'Very strong'][score] : '';
      });
    });
  };

  /** Flash messages queued server side. */
  UI.showFlash = function () {
    (window.EF_FLASH || []).forEach(function (item, index) {
      setTimeout(function () { EF.toast.show(item.type, item.message); }, index * 160);
    });
  };

  /* ── Boot ────────────────────────────────────────────────── */
  EF.boot = function () {
    UI.bindDropdowns();
    UI.bindTabs();
    UI.bindSidebar();
    UI.bindTheme();
    UI.bindConfirmations();
    UI.bindPasswordToggles();
    UI.bindStrengthMeters();
    UI.showFlash();

    document.querySelectorAll('form').forEach(function (form) {
      if (form.hasAttribute('data-rules-form') || form.querySelector('[data-rules]')) {
        FormValidator.bind(form);
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', EF.boot);
  } else {
    EF.boot();
  }

  window.EF = EF;
})(window, document);


