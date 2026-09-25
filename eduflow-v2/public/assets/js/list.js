/* ============================================================
   EduFlow V2 — list.js
   Reusable AJAX list controller (filters, pagination, bulk
   selection) + upload helper + light file viewer.
   ============================================================ */
(function (window, document) {
  'use strict';

  var EF = window.EF;
  if (!EF) return;

  /**
   * List — binds a filter bar + table body to an AJAX endpoint.
   *
   *   EF.list({
   *     endpoint: '/admin/courses/list',
   *     form: '#course-filters',
   *     tbody: '#course-rows',
   *     pagination: '#course-pagination',
   *     render: function (row, index, number) { return '<tr>...</tr>'; }
   *   });
   */
  function List(options) {
    this.options = options || {};
    this.state = { page: 1, per_page: this.options.perPage || 10, search: '', filters: {} };
    this.form = typeof this.options.form === 'string' ? document.querySelector(this.options.form) : this.options.form;
    this.tbody = typeof this.options.tbody === 'string' ? document.querySelector(this.options.tbody) : this.options.tbody;
    this.pagination = typeof this.options.pagination === 'string' ? document.querySelector(this.options.pagination) : this.options.pagination;
    this.bind();
    this.load();
  }

  List.prototype.bind = function () {
    var self = this;

    if (this.form) {
      var searchInput = this.form.querySelector('[name="search"]');
      if (searchInput) {
        searchInput.addEventListener('input', EF.util.debounce(function () {
          self.state.search = searchInput.value.trim();
          self.state.page = 1;
          self.load();
        }, 320));
      }

      this.form.querySelectorAll('[name]').forEach(function (field) {
        if (field === searchInput) return;
        field.addEventListener('change', function () {
          self.refreshFilters();
          self.state.page = 1;
          self.load();
        });
      });

      this.form.addEventListener('submit', function (event) {
        event.preventDefault();
        self.refreshFilters();
        self.load();
      });

      var reset = this.form.querySelector('[data-filter-reset]');
      if (reset) {
        reset.addEventListener('click', function () {
          self.form.reset();
          self.state = { page: 1, per_page: self.state.per_page, search: '', filters: {} };
          self.load();
        });
      }
    }

    if (this.pagination) {
      this.pagination.addEventListener('click', function (event) {
        var button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        self.state.page = Number(button.getAttribute('data-page')) || 1;
        self.load();
      });
    }

    var selectAll = this.options.selectAll ? document.querySelector(this.options.selectAll) : null;
    if (selectAll && this.tbody) {
      selectAll.addEventListener('change', function () {
        self.tbody.querySelectorAll('[data-row-check]').forEach(function (box) {
          box.checked = selectAll.checked;
        });
        self.updateBulkBar();
      });
      this.tbody.addEventListener('change', function (event) {
        if (event.target.matches('[data-row-check]')) self.updateBulkBar();
      });
    }

    var refresh = this.options.refresh ? document.querySelector(this.options.refresh) : null;
    if (refresh) {
      refresh.addEventListener('click', function () { self.load(); });
    }
  };

  List.prototype.refreshFilters = function () {
    if (!this.form) return;
    var filters = {};
    this.form.querySelectorAll('[name]').forEach(function (field) {
      if (field.name === 'search' || field.type === 'submit') return;
      if (field.type === 'checkbox') {
        if (field.checked) filters[field.name] = field.value || '1';
      } else if (field.value !== '') {
        filters[field.name] = field.value;
      }
    });
    this.state.filters = filters;
  };

  List.prototype.payload = function () {
    var payload = { page: this.state.page, per_page: this.state.per_page };
    var self = this;

    if (this.state.search) payload.search = this.state.search;
    Object.keys(this.state.filters).forEach(function (key) { payload[key] = self.state.filters[key]; });
    if (this.options.extra) {
      Object.keys(this.options.extra).forEach(function (key) { payload[key] = self.options.extra[key]; });
    }
    return payload;
  };

  List.prototype.load = function () {
    var self = this;
    if (!this.tbody) return;

    var columns = this.options.colspan || 6;
    this.tbody.innerHTML = '<tr><td colspan="' + columns + '" style="padding:22px">' +
      '<div class="row-center gap-10"><span class="spinner"></span><span class="muted small">Loading…</span></div></td></tr>';

    EF.api.post(this.options.endpoint, this.payload()).then(function (response) {
      if (response.status !== 'success') {
        self.tbody.innerHTML = '<tr><td colspan="' + columns + '">' +
          EF.util.empty(response.message || 'Could not load records.') + '</td></tr>';
        return;
      }

      var data = response.data || {};
      var rows = data.rows || data.items || data.records || [];
      var total = data.total === undefined ? rows.length : data.total;
      var perPage = data.per_page || self.state.per_page;

      if (!rows.length) {
        self.tbody.innerHTML = '<tr><td colspan="' + columns + '">' +
          EF.util.empty(self.options.emptyTitle || 'Nothing to show yet',
            self.options.emptyText || 'Records will appear here once they are added.') + '</td></tr>';
      } else {
        self.tbody.innerHTML = rows.map(function (row, index) {
          return self.options.render(row, index, (self.state.page - 1) * perPage + index + 1);
        }).join('');
      }

      if (typeof self.options.afterRender === 'function') self.options.afterRender(rows, data);
      if (typeof self.options.onCount === 'function') self.options.onCount(total, data);
      self.renderPagination(total, perPage);
    }).catch(function () {
      self.tbody.innerHTML = '<tr><td colspan="' + columns + '">' +
        EF.util.empty('Could not load records', 'Please check your connection and try again.') + '</td></tr>';
    });
  };

  List.prototype.renderPagination = function (total, perPage) {
    if (!this.pagination) return;

    var pages = Math.max(1, Math.ceil(total / perPage));
    var page = Math.min(this.state.page, pages);
    var from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    var to = Math.min(total, page * perPage);

    if (pages <= 1) {
      this.pagination.innerHTML = '<div class="pagination-meta">' + total + ' record' + (total === 1 ? '' : 's') + '</div>';
      return;
    }

    var numbers = [];
    var start = Math.max(1, page - 2);
    var end = Math.min(pages, start + 4);
    start = Math.max(1, end - 4);
    for (var i = start; i <= end; i++) numbers.push(i);

    var html = '<nav class="pagination"><span class="pagination-meta">Showing ' + from + '–' + to + ' of ' + total +
      '</span><div class="pagination-controls">';
    html += '<button type="button" class="page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>' +
      EF.icon('chevronLeft', 15) + '</button>';
    if (start > 1) {
      html += '<button type="button" class="page-btn" data-page="1">1</button>';
      if (start > 2) html += '<span class="page-gap">…</span>';
    }
    numbers.forEach(function (number) {
      html += '<button type="button" class="page-btn' + (number === page ? ' is-active' : '') +
        '" data-page="' + number + '">' + number + '</button>';
    });
    if (end < pages) {
      if (end < pages - 1) html += '<span class="page-gap">…</span>';
      html += '<button type="button" class="page-btn" data-page="' + pages + '">' + pages + '</button>';
    }
    html += '<button type="button" class="page-btn" data-page="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>' +
      EF.icon('chevronRight', 15) + '</button>';
    html += '</div></nav>';

    this.pagination.innerHTML = html;
  };

  List.prototype.updateBulkBar = function () {
    var bar = this.options.bulkBar ? document.querySelector(this.options.bulkBar) : null;
    if (!bar || !this.tbody) return;
    var count = this.tbody.querySelectorAll('[data-row-check]:checked').length;
    bar.classList.toggle('hidden', count === 0);
    var counter = bar.querySelector('[data-bulk-count]');
    if (counter) counter.textContent = count;
  };

  List.prototype.selected = function () {
    if (!this.tbody) return [];
    return Array.prototype.slice.call(this.tbody.querySelectorAll('[data-row-check]:checked'))
      .map(function (box) { return box.value; });
  };

  EF.List = List;
  EF.list = function (options) { return new List(options); };

  /* ── Upload helper with progress feedback ────────────────── */
  EF.upload = function (path, formData, options) {
    options = options || {};
    var bar = options.progressBar ? document.querySelector(options.progressBar) : null;

    return EF.api.post(path, formData, {
      progress: function (percent) {
        if (!bar) return;
        bar.classList.remove('hidden');
        var inner = bar.querySelector('span');
        if (inner) inner.style.width = percent + '%';
      }
    }).then(function (response) {
      if (bar) setTimeout(function () { bar.classList.add('hidden'); }, 400);
      if (response.status === 'success') {
        EF.toast.success(response.message);
      } else {
        EF.toast.error(response.message);
      }
      if (typeof options.onDone === 'function') options.onDone(response);
      return response;
    });
  };

  /* ── In-page file viewer (code, text, images, PDF) ───────── */
  EF.viewFile = function (options) {
    options = options || {};
    var modal = EF.modal.open({
      title: options.name || 'File preview',
      subtitle: options.subtitle || '',
      size: 'lg',
      flush: true,
      body: '<div style="min-height:220px"><div class="spinner" style="margin:44px auto"></div></div>',
      footer: '<a class="btn" href="' + EF.util.url('/files/download', { path: options.path, name: options.name }) + '">' +
        EF.icon('download', 15) + ' Download</a>' +
        '<button type="button" class="btn btn-primary" data-modal-close>Close</button>'
    });

    fetch(EF.util.url('/files/view', { path: options.path, name: options.name }), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.status !== 'success') {
          modal.body.innerHTML = EF.util.empty(data.message || 'Preview not available.', '', 'fileText');
          return;
        }
        if (data.data.type === 'image') {
          modal.body.innerHTML = '<div style="padding:16px;text-align:center">' +
            '<img src="' + data.data.url + '" alt="' + EF.util.escape(options.name || '') + '" style="max-height:70vh;margin:0 auto"></div>';
        } else if (data.data.type === 'pdf' || data.data.type === 'media') {
          modal.body.innerHTML = '<iframe src="' + data.data.url + '" style="width:100%;height:70vh;border:0"></iframe>';
        } else {
          modal.body.innerHTML = '<pre class="mono" style="margin:0;padding:16px;max-height:70vh;overflow:auto;white-space:pre-wrap">' +
            EF.util.escape(data.data.content || '') + '</pre>';
        }
      })
      .catch(function () {
        modal.body.innerHTML = EF.util.empty('Preview not available.', 'You can still download the file.', 'fileText');
      });
  };

  window.EF = EF;
})(window, document);


