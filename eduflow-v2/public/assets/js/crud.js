/* ============================================================
   EduFlow V2 — crud.js
   Reusable AJAX building blocks shared by every module:
   modal forms, confirmation actions, detail panels, row helpers.
   Built on top of core.js (EF.api, EF.modal, EF.toast).
   ============================================================ */
(function (window, document) {
  'use strict';

  var EF = window.EF;
  if (!EF) return;

  /* ── Small formatting helpers ────────────────────────────── */
  var H = {
    esc: EF.util.escape,

    /** dd Mon yyyy */
    date: function (value) {
      if (!value) return '—';
      var d = new Date(String(value).replace(' ', 'T'));
      if (isNaN(d.getTime())) return '—';
      var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return String(d.getDate()).padStart(2, '0') + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    },

    /** dd Mon yyyy, hh:mm */
    dateTime: function (value) {
      if (!value) return '—';
      var d = new Date(String(value).replace(' ', 'T'));
      if (isNaN(d.getTime())) return '—';
      var hours = d.getHours();
      var suffix = hours >= 12 ? 'PM' : 'AM';
      var h12 = hours % 12 === 0 ? 12 : hours % 12;
      return H.date(value) + ', ' + h12 + ':' + String(d.getMinutes()).padStart(2, '0') + ' ' + suffix;
    },

    badge: function (label, tone, iconName) {
      return '<span class="badge badge-' + (tone || 'muted') + '">' +
        (iconName ? EF.icon(iconName, 13) : '') + H.esc(label) + '</span>';
    },

    status: function (status) {
      var map = {
        active: 'success', published: 'success', approved: 'success', graded: 'success',
        reviewed: 'success', completed: 'info', submitted: 'info', test_submitted: 'info',
        in_progress: 'warning', pending: 'warning', returned: 'warning', waiting: 'muted',
        inactive: 'muted', draft: 'muted', archived: 'muted', ended: 'muted',
        rejected: 'danger', urgent: 'danger', important: 'warning', normal: 'muted'
      };
      var label = String(status || '').replace(/_/g, ' ');
      return H.badge(label.charAt(0).toUpperCase() + label.slice(1), map[status] || 'muted');
    },

    /** One row-action button. */
    action: function (options) {
      var tag = options.href ? 'a' : 'button';
      var attrs = 'class="btn-icon' + (options.danger ? ' is-danger' : '') + '"';
      if (!options.href) attrs += ' type="button"';
      if (options.href) attrs += ' href="' + options.href + '"';
      if (options.data) {
        Object.keys(options.data).forEach(function (key) {
          attrs += ' data-' + key + '="' + H.esc(options.data[key]) + '"';
        });
      }
      return '<' + tag + ' ' + attrs + ' data-tip="' + H.esc(options.tip || '') + '" ' +
        'aria-label="' + H.esc(options.tip || 'Action') + '">' + EF.icon(options.icon, 15) + '</' + tag + '>';
    },

    /** File badge + name + meta, used by every file list in the product. */
    file: function (file, options) {
      options = options || {};
      var size = EF.util.bytes(file.file_size);
      var meta = [size, file.uploader_name || file.uploaded_at ? EF.util.timeAgo(file.uploaded_at) : '']
        .filter(Boolean).join(' · ');
      return '<div class="file-row" data-file-id="' + (file.id || '') + '">' +
        (options.selectable ? '<input type="checkbox" class="table-check" data-file-check value="' + (file.id || '') + '">' : '') +
        '<span class="file-badge file-badge-primary">' + EF.icon('fileText', 18) + '</span>' +
        '<div class="file-row-main">' +
          '<div class="file-row-name truncate">' + H.esc(file.file_name) + '</div>' +
          '<div class="file-row-meta">' + H.esc(meta) + '</div>' +
        '</div>' +
        '<div class="file-row-actions">' +
          '<button type="button" class="btn-icon" data-file-view="' + H.esc(file.file_path) + '" ' +
            'data-file-name="' + H.esc(file.file_name) + '" data-tip="Preview">' + EF.icon('eye', 15) + '</button>' +
          '<a class="btn-icon" data-tip="Download" href="' +
            EF.util.url('/files/download', { path: file.file_path, name: file.file_name }) + '">' + EF.icon('download', 15) + '</a>' +
          (options.removable
            ? '<button type="button" class="btn-icon is-danger" data-file-remove="' + (file.id || '') + '" data-tip="Delete">' +
              EF.icon('trash', 15) + '</button>'
            : '') +
        '</div>' +
      '</div>';
    },

    empty: function (title, text, iconName) {
      return '<div class="empty">' + EF.icon(iconName || 'inbox', 30) +
        '<h3>' + H.esc(title) + '</h3><p>' + H.esc(text || '') + '</p></div>';
    }
  };

  EF.h = H;

  /* ── Modal form builder ──────────────────────────────────── */

  /**
   * Render a single field definition.
   * { name, label, type, value, rules, hint, placeholder, options, span, rows, accept }
   */
  function fieldMarkup(field) {
    var name = field.name;
    var type = field.type || 'text';
    var value = field.value === null || field.value === undefined ? '' : field.value;
    var rules = field.rules ? ' data-rules="' + H.esc(field.rules) + '"' : '';
    var label = field.label
      ? '<label class="field-label" for="ef-' + name + '">' + H.esc(field.label) +
        (field.rules && field.rules.indexOf('required') === 0 ? '<span class="req">*</span>' : '') + '</label>'
      : '';
    var hint = field.hint ? '<div class="field-hint">' + H.esc(field.hint) + '</div>' : '';
    var span = field.span === 2 ? ' span-2' : '';
    var control;
    var selected;
    var checked;

    if (type === 'textarea') {
      control = '<textarea class="textarea" id="ef-' + name + '" name="' + name + '" rows="' + (field.rows || 4) + '"' +
        (field.placeholder ? ' placeholder="' + H.esc(field.placeholder) + '"' : '') + rules + '>' +
        H.esc(value) + '</textarea>';
    } else if (type === 'multiselect') {
      var selectedValues = Array.isArray(value) ? value.map(String) : String(value || '').split(',').map(function (item) { return item.trim(); });
      control = '<div class="choice-grid">';
      Object.keys(field.options || {}).forEach(function (key) {
        var isSelected = selectedValues.indexOf(String(key)) !== -1;
        control += '<label class="choice"><input type="checkbox" name="' + H.esc(name) + '[]" value="' + H.esc(key) + '"' + (isSelected ? ' checked' : '') + '><span>' + H.esc(field.options[key]) + '</span></label>';
      });
      control += '</div>';
    } else if (type === 'select') {
      control = '<select class="select" id="ef-' + name + '" name="' + name + '"' + rules + '>';
      control += '<option value="">' + H.esc(field.placeholder || 'Select…') + '</option>';
      Object.keys(field.options || {}).forEach(function (key) {
        selected = String(key) === String(value) ? ' selected' : '';
        control += '<option value="' + H.esc(key) + '"' + selected + '>' + H.esc(field.options[key]) + '</option>';
      });
      control += '</select>';
    } else if (type === 'checkbox' || type === 'switch') {
      checked = value === '1' || value === 1 || value === true || value === 'true';
      control = '<label class="check"><input type="checkbox" id="ef-' + name + '" name="' + name + '" value="1"' +
        (checked ? ' checked' : '') + '>' +
        (field.checkboxLabel ? '<span>' + H.esc(field.checkboxLabel) + '</span>' : '') + '</label>';
      label = '';
    } else if (type === 'static') {
      control = '<div class="static-value">' + (field.html || H.esc(value)) + '</div>';
      label = '';
    } else if (type === 'file') {
      control = '<input class="input" type="file" id="ef-' + name + '" name="' + name + '"' +
        (field.accept ? ' accept="' + H.esc(field.accept) + '"' : '') + rules + '>' +
        (field.current ? '<div class="field-hint">Current: ' + H.esc(field.current) + '</div>' : '');
    } else {
      control = '<input class="input" type="' + type + '" id="ef-' + name + '" name="' + name + '" value="' +
        H.esc(type === 'password' ? '' : value) + '"' +
        (field.placeholder ? ' placeholder="' + H.esc(field.placeholder) + '"' : '') +
        (field.min !== undefined ? ' min="' + field.min + '"' : '') +
        (field.max !== undefined ? ' max="' + field.max + '"' : '') +
        (field.step ? ' step="' + field.step + '"' : '') +
        (field.disabled ? ' disabled' : '') + rules + '>';
    }

    return '<div class="field' + span + '">' + label + control + hint + '</div>';
  }

  function fieldsMarkup(fields) {
    var html = '';
    (fields || []).forEach(function (field) {
      if (field.fullWidth && field.type !== 'textarea') {
        field = Object.assign({}, field, { span: 2 });
      }
      html += fieldMarkup(field);
    });
    return html;
  }


  /**
   * Open a modal form and POST it to an endpoint.
   *
   *   EF.form({
   *     title: 'New course',
   *     endpoint: '/api/courses/create',
   *     submitLabel: 'Create course',
   *     fields: [...],
   *     values: {...},                     // present => edit mode
   *     onSuccess: function (response) { EF.reload('courses'); }
   *   });
   */
  EF.formModal = function (options) {
    options = options || {};
    var isEdit = !!options.values;
    var values = options.values || {};

    var fields = (options.fields || []).map(function (field) {
      if (values[field.name] !== undefined && field.value === undefined && field.type !== 'password') {
        field = Object.assign({}, field, { value: values[field.name] });
      }
      return field;
    });

    var modal = EF.modal.open({
      title: options.title || (isEdit ? 'Edit record' : 'New record'),
      subtitle: options.subtitle || '',
      size: options.size || 'md',
      body: '<form id="ef-crud-form" class="form" data-validate="server" novalidate>' +
          (options.intro ? '<div class="alert alert-info mb-16">' + EF.icon('info', 17) +
            '<div>' + options.intro + '</div></div>' : '') +
          '<div class="form-grid">' + fieldsMarkup(fields) + '</div>' +
        '</form>',
      footer: '<button type="button" class="btn" data-modal-close>' + H.esc(options.cancelLabel || 'Cancel') + '</button>' +
        '<button type="submit" form="ef-crud-form" class="btn btn-primary" id="ef-crud-submit">' +
        H.esc(options.submitLabel || (isEdit ? 'Save changes' : 'Create')) + '</button>'
    });

    var form = modal.find('#ef-crud-form');
    var button = modal.find('#ef-crud-submit');
    EF.form.bind(form);

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!EF.form.validateForm(form)) return;

      var payload = new FormData(form);
      Object.keys(options.extra || {}).forEach(function (key) {
        payload.append(key, options.extra[key]);
      });

      EF.api.submit(options.endpoint, payload, {
        form: form,
        button: button,
        reload: false,
        onSuccess: function (response) {
          modal.close();
          if (typeof options.onSuccess === 'function') {
            options.onSuccess(response);
          } else if (options.list) {
            EF.reload(options.list);
          }
          return response;
        }
      });
    });

    return modal;
  };

  /* ── Confirmation + POST (destructive actions) ───────────── */

  /**
   * Ask for confirmation, then POST and refresh the list.
   *   EF.remove({ endpoint: '/api/courses/delete', payload: { ids: [1] }, list: 'courses' });
   */
  EF.remove = function (options) {
    options = options || {};
    return EF.modal.confirm({
      title: options.title || 'Please confirm',
      message: options.message || 'This action cannot be undone.',
      confirmText: options.confirmText || (options.tone === 'danger' ? 'Delete' : 'Confirm'),
      tone: options.tone || 'warning'
    }).then(function (ok) {
      if (!ok) return null;
      return EF.api.submit(options.endpoint, options.payload || {}, {
        reload: false,
        onSuccess: function (response) {
          if (options.list) EF.reload(options.list);
          if (typeof options.onSuccess === 'function') options.onSuccess(response);
          return response;
        }
      });
    });
  };

  /* ── Read-only detail modal ─────────────────────────────── */

  /** rows: [ ['Label', 'value'], ['Label', '<em>html</em>', true] ] */
  EF.detail = function (options) {
    options = options || {};
    var rows = (options.rows || []).map(function (row) {
      return '<div class="detail-row"><dt>' + H.esc(row[0]) + '</dt><dd>' +
        (row[2] ? row[1] : H.esc(row[1] === null || row[1] === undefined ? '—' : row[1])) + '</dd></div>';
    }).join('');

    return EF.modal.open({
      title: options.title || 'Details',
      subtitle: options.subtitle || '',
      size: options.size || 'md',
      body: '<dl class="detail-list">' + rows + '</dl>' +
        (options.body ? '<div class="mt-16">' + options.body + '</div>' : ''),
      footer: options.footer === false ? false : (options.footer ||
        '<button type="button" class="btn" data-modal-close>Close</button>')
    });
  };

  /* Global delegation: preview any [data-file-view] element. */
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-file-view]');
    if (!trigger) return;
    event.preventDefault();
    EF.viewFile({
      path: trigger.getAttribute('data-file-view'),
      name: trigger.getAttribute('data-file-name') || 'File preview'
    });
  });
})(window, document);
