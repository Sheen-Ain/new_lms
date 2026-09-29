(function (window, document) {
  'use strict';

  var EF = window.EF;
  var module = window.EF_ADMIN_MODULE;
  if (!EF || !module) return;

  var configs = {
    users: { base: '/api/users', id: 'user_id', dataKey: 'user' },
    courses: { base: '/api/courses', id: 'course_id', dataKey: 'course' },
    batches: { base: '/api/batches', id: 'batch_id', dataKey: 'batch' },
    topics: { base: '/api/topics', id: 'topic_id', dataKey: 'topic' },
    assignments: { base: '/api/assignments', id: 'assignment_id', dataKey: 'assignment' },
    announcements: { base: '/api/announcements', id: 'announcement_id', dataKey: 'announcement' },
    tests: { base: '/api/tests', id: 'test_id', dataKey: 'test' },
    applications: { base: '/api/applications', id: 'application_id', dataKey: 'application' },
    submissions: { base: '/api/submissions', id: 'submission_id', dataKey: 'submission' },
    live: { base: '/api/live', id: 'session_id', dataKey: 'session' },
    feedback: { base: '/api/feedback', id: 'entry_id', dataKey: 'entry' },
    activity: { base: '/api/activity' }
  };

  function request(path, payload) {
    return EF.api.post(path, payload || {}).then(function (response) {
      if (response.status !== 'success') throw new Error(response.message || 'Request failed.');
      return response.data || {};
    });
  }

  function mutate(path, payload, refresh) {
    return EF.api.submit(path, payload || {}, {
      reload: false,
      onSuccess: function (response) {
        if (refresh !== false && EF.reload) EF.reload(module.id);
        return response;
      }
    });
  }

  function fail(error) {
    EF.toast.error(error && error.message ? error.message : 'The action could not be completed.');
  }

  function record(type, id) {
    var config = configs[type];
    return request(config.base + '/get', (function () {
      var payload = {};
      payload[config.id] = id;
      return payload;
    })()).then(function (data) { return data[config.dataKey]; });
  }

  function rows(path, property) {
    return request(path, { per_page: 100 }).then(function (data) {
      return data[property] || data.rows || [];
    });
  }

  function selectOptions(items, valueKey, labelKey, emptyLabel) {
    var options = {};
    if (emptyLabel) options[''] = emptyLabel;
    items.forEach(function (item) {
      options[item[valueKey]] = item[labelKey];
    });
    return options;
  }

  function setFieldDefault(fields, name, value) {
    fields.some(function (field) {
      if (field.name !== name) return false;
      field.value = value;
      return true;
    });
  }

  function formFields(type, editing) {
    var options;
    if (type === 'courses') {
      return [
        { name: 'title', label: 'Course title', rules: 'required|min:2|max:200' },
        { name: 'description', label: 'Description', type: 'textarea', rows: 4 },
        { name: 'thumbnail', label: 'Thumbnail', type: 'file', accept: 'image/jpeg,image/png,image/gif,image/webp' },
        { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', inactive: 'Inactive', archived: 'Archived' } }
      ];
    }
    if (type === 'users') {
      options = [
        { name: 'full_name', label: 'Full name', rules: 'required|min:3|max:150' },
        { name: 'email', label: 'Email', type: 'email', rules: 'required|email' },
        { name: 'gender', label: 'Gender', type: 'select', options: { male: 'Male', female: 'Female', other: 'Other' } },
        { name: 'cnic', label: 'CNIC (optional)', placeholder: 'XXXXX-XXXXXXX-X' },
        { name: 'phone', label: 'Phone' },
        { name: 'roles', label: 'Roles', type: 'multiselect', options: { student: 'Student', teacher: 'Teacher', admin: 'Admin' } },
        { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', inactive: 'Inactive' } },
        { name: 'is_verified', label: 'Email verification', type: 'checkbox', checkboxLabel: 'Mark email as verified' }
      ];
      if (!editing) options.splice(2, 0, { name: 'password', label: 'Temporary password', type: 'password', rules: 'required|password' });
      else options.push({ name: 'password', label: 'New password (optional)', type: 'password', hint: 'Leave blank to keep the current password.' });
      return options;
    }
    if (type === 'batches') {
      return rows('/api/courses/list', 'rows').then(function (items) {
        return [
          { name: 'name', label: 'Batch name', rules: 'required|min:3|max:150' },
          { name: 'course_id', label: 'Course', type: 'select', options: selectOptions(items, 'id', 'title', 'Select course') },
          { name: 'description', label: 'Description', type: 'textarea', rows: 3 },
          { name: 'start_date', label: 'Start date', type: 'date' },
          { name: 'end_date', label: 'End date', type: 'date' },
          { name: 'max_students', label: 'Maximum students', type: 'number', min: 1, max: 5000 },
          { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', inactive: 'Inactive', completed: 'Completed' } }
        ];
      });
    }
    if (type === 'topics') {
      return rows('/api/batches/list', 'rows').then(function (items) {
        var batchOptions = { '': 'Global topic' };
        items.forEach(function (item) { batchOptions[item.id] = item.name; });
        return [
          { name: 'title', label: 'Title', rules: 'required|min:3|max:200' },
          { name: 'description', label: 'Description', type: 'textarea', rows: 3 },
          { name: 'batch_id', label: 'Batch', type: 'select', options: batchOptions },
          { name: 'sort_order', label: 'Sort order', type: 'number', min: 0 },
          { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', inactive: 'Inactive' } }
        ];
      });
    }
    if (type === 'assignments') {
      var baseFields = [
        { name: 'title', label: 'Title', rules: 'required|min:3|max:200' },
        { name: 'description', label: 'Description', type: 'textarea', rows: 3 },
        { name: 'total_marks', label: 'Total marks', type: 'number', min: 1, max: 1000 },
        { name: 'due_date', label: 'Due date and time', type: 'datetime-local' },
        { name: 'allow_late', label: 'Allow late submissions', type: 'checkbox', checkboxLabel: 'Allow late submissions' },
        { name: 'status', label: 'Status', type: 'select', options: { active: 'Active', draft: 'Draft', inactive: 'Inactive' } }
      ];
      return Promise.all([rows('/api/batches/list', 'rows'), rows('/api/topics/list', 'rows')]).then(function (lists) {
        baseFields.splice(2, 0,
          { name: 'batch_id', label: 'Batch', type: 'select', options: selectOptions(lists[0], 'id', 'name', 'Select batch') },
          { name: 'topic_id', label: 'Topic', type: 'select', options: selectOptions(lists[1], 'id', 'title', 'No topic') }
        );
        return baseFields;
      });
    }
    if (type === 'announcements') {
      return rows('/api/batches/list', 'rows').then(function (items) {
        var batchOptions = { '': 'Everyone' };
        items.forEach(function (item) { batchOptions[item.id] = item.name; });
        return [
          { name: 'title', label: 'Title', rules: 'required|min:3|max:200' },
          { name: 'content', label: 'Message', type: 'textarea', rows: 6, rules: 'required|min:5' },
          { name: 'batch_id', label: 'Audience', type: 'select', options: batchOptions },
          { name: 'priority', label: 'Priority', type: 'select', options: { normal: 'Normal', important: 'Important', urgent: 'Urgent' } },
          { name: 'status', label: 'Status', type: 'select', options: { published: 'Published', draft: 'Draft' } },
          { name: 'is_pinned', label: 'Pin announcement', type: 'checkbox', checkboxLabel: 'Keep at top' }
        ];
      });
    }
    return [];
  }

  function openEditor(type, item) {
    var config = configs[type];
    var editing = !!item;
    Promise.resolve(formFields(type, editing)).then(function (fields) {
      if (!editing && type === 'courses') setFieldDefault(fields, 'status', 'active');
      if (!editing && type === 'users') {
        setFieldDefault(fields, 'roles', ['student']);
        setFieldDefault(fields, 'status', 'active');
        setFieldDefault(fields, 'is_verified', 1);
      }
      if (editing && type === 'users') setFieldDefault(fields, 'roles', String(item.all_roles || '').split(',').filter(Boolean));
      if (!editing && type === 'batches') {
        setFieldDefault(fields, 'max_students', 50);
        setFieldDefault(fields, 'status', 'active');
      }
      if (!editing && type === 'topics') setFieldDefault(fields, 'status', 'active');
      if (!editing && type === 'assignments') {
        setFieldDefault(fields, 'total_marks', 100);
        setFieldDefault(fields, 'status', 'active');
      }
      if (type === 'announcements' && !editing) {
        setFieldDefault(fields, 'priority', 'normal');
        setFieldDefault(fields, 'status', 'published');
      }
      if (type === 'assignments' && editing && item.due_date) {
        item = Object.assign({}, item, { due_date: String(item.due_date).replace(' ', 'T').slice(0, 16) });
      }
      var values = item || null;
      var extra = {};
      if (editing) extra[config.id] = item.id;
      var labels = { users: 'user', courses: 'course', batches: 'batch', topics: 'topic', assignments: 'assignment', announcements: 'announcement' };
      EF.formModal({
        title: (editing ? 'Edit ' : 'New ') + (labels[type] || type),
        endpoint: config.base + (editing ? '/update' : '/create'),
        fields: fields,
        values: values,
        extra: extra,
        list: module.id
      });
    }).catch(fail);
  }

  function editRecord(type, id) {
    record(type, id).then(function (item) {
      if (!item) throw new Error('Record not found.');
      openEditor(type, item);
    }).catch(fail);
  }

  function detail(type, id) {
    var cache = window.EF_ADMIN_ROW_DATA && window.EF_ADMIN_ROW_DATA[module.id];
    var current = cache && cache[id];
    var config = configs[type];
    var result = config && type !== 'live' && type !== 'feedback'
      ? record(type, id).catch(function () { return current; })
      : Promise.resolve(current);
    result.then(function (item) {
      if (!item) throw new Error('Details are unavailable.');
      if (type === 'tests') {
        return request('/api/tests/get', { test_id: id }).then(function (data) {
          var questions = data.questions || [];
          var rowsData = [['Status', item.status], ['Type', item.type], ['Batch', item.batch_name || 'All batches'], ['Questions', questions.length]];
          questions.forEach(function (question, index) {
            rowsData.push(['Question ' + (index + 1), question.text]);
            (question.options || []).forEach(function (option, optionIndex) {
              rowsData.push(['Option ' + String.fromCharCode(65 + optionIndex), option.text + (option.is_correct ? ' (correct)' : '')]);
            });
          });
          EF.detail({ title: item.title, rows: rowsData });
        });
      }
      if (type === 'applications') {
        return request('/api/applications/get', { application_id: id }).then(function (data) {
          var app = data.application || item;
          var rowsData = [['Student', app.full_name], ['Student ID', app.user_id_number], ['Email', app.email], ['Course', app.course_title], ['Batch', app.batch_name], ['Status', app.status], ['Applied', app.applied_at]];
          (data.attempts || []).forEach(function (attempt, index) {
            rowsData.push(['Test attempt ' + (index + 1), attempt.percentage + '% · ' + attempt.status + ' · ' + attempt.submitted_at]);
          });
          EF.detail({ title: 'Application details', rows: rowsData, size: 'lg' });
        });
      }
      if (type === 'submissions') {
        return request('/api/submissions/get', { submission_id: id }).then(function (data) {
          var submission = data.submission || item;
          var rowsData = [['Student', submission.student_name], ['Student ID', submission.user_id_number], ['Assignment', submission.assignment_title], ['Batch', submission.batch_name], ['Status', submission.status], ['Marks', (submission.marks === null ? 'Not graded' : submission.marks) + ' / ' + submission.total_marks], ['Feedback', submission.feedback || '—'], ['Submitted', submission.submitted_at]];
          if (submission.file_path) rowsData.push(['File', '<a href="' + EF.util.url('/files/download', { path: submission.file_path, name: submission.file_name }) + '">Download ' + EF.h.esc(submission.file_name || 'submission') + '</a>', true]);
          EF.detail({ title: 'Submission details', rows: rowsData, size: 'lg' });
        });
      }
      if (type === 'live') {
        return request('/api/live/participants', { session_id: id }).then(function (data) {
          var names = (data.rows || []).map(function (participant) { return participant.full_name + ' · ' + participant.current_role; });
          EF.detail({ title: item.title || 'Live session', rows: [['Course', item.course_title], ['Batch', item.batch_name], ['Host', item.host_name], ['Status', item.status], ['Joined', item.total_joined || 0], ['Currently live', item.live_count || 0], ['Participants', names.join(', ') || 'None']] });
        });
      }
      var fields = Object.keys(item).filter(function (key) {
        return ['password', 'profile_picture', 'file_path', 'categories'].indexOf(key) === -1 && item[key] !== null && typeof item[key] !== 'object';
      }).slice(0, 14);
      EF.detail({ title: item.full_name || item.title || 'Record details', rows: fields.map(function (key) { return [key.replace(/_/g, ' '), item[key]]; }) });
    }).catch(fail);
  }

  function deleteRecord(type, id) {
    var config = configs[type];
    if (!config) return;
    if (type === 'feedback') {
      return EF.remove({ endpoint: '/api/feedback/delete-entry', payload: { entry_id: id }, list: module.id, title: 'Delete this feedback entry?', message: 'Anonymous feedback is permanently deleted.', tone: 'danger' });
    }
    EF.remove({
      endpoint: config.base + '/delete',
      payload: (function () { var payload = {}; payload[config.id] = id; return payload; })(),
      list: module.id,
      title: 'Delete this ' + type.replace(/s$/, '') + '?',
      message: type === 'courses' ? 'This removes the course and related batches and learning records.' : 'This action cannot be undone.',
      tone: 'danger'
    });
  }

  function toggleStatus(type, id, item) {
    if (type === 'users') {
      return mutate('/api/users/status', { user_id: id, status: item.status === 'active' ? 'inactive' : 'active' });
    }
    var config = configs[type];
    record(type, id).then(function (saved) {
      if (!saved) throw new Error('Record not found.');
      var payload = Object.assign({}, saved);
      payload[config.id] = id;
      payload.status = type === 'announcements'
        ? (saved.status === 'published' ? 'draft' : 'published')
        : (saved.status === 'active' ? 'inactive' : 'active');
      return mutate(config.base + '/update', payload);
    }).catch(fail);
  }

  function rejectApplications(ids) {
    var modal = EF.modal.open({
      title: 'Reject application' + (ids.length === 1 ? '' : 's'),
      body: '<label class="field"><span class="field-label">Reason (optional)</span><textarea class="textarea" rows="4" data-rejection-reason></textarea></label>',
      footer: '<button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-danger" data-rejection-submit>Reject</button>'
    });
    modal.find('[data-rejection-submit]').addEventListener('click', function () {
      mutate('/api/applications/reject', {
        application_id: ids.join(','),
        reason: modal.find('[data-rejection-reason]').value
      }).then(function () { modal.close(); }).catch(fail);
    });
  }

  function openGrade(id) {
    request('/api/submissions/get', { submission_id: id }).then(function (data) {
      var submission = data.submission;
      var modal = EF.modal.open({
        title: 'Grade submission',
        subtitle: submission.student_name + ' · ' + submission.assignment_title,
        body: '<form id="admin-grade-form" class="form"><label class="field"><span class="field-label">Marks (out of ' + Number(submission.total_marks) + ')</span><input class="input" type="number" name="marks" min="0" max="' + Number(submission.total_marks) + '" value="' + Number(submission.marks || 0) + '" required></label><label class="field"><span class="field-label">Feedback</span><textarea class="textarea" name="feedback" rows="4">' + EF.h.esc(submission.feedback || '') + '</textarea></label></form>',
        footer: '<button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-primary" data-admin-grade>Save grade</button>'
      });
      modal.find('[data-admin-grade]').addEventListener('click', function () {
        var form = modal.find('#admin-grade-form');
        var payload = Object.fromEntries(new FormData(form).entries());
        payload.submission_id = id;
        mutate('/api/submissions/grade', payload).then(function () { modal.close(); }).catch(fail);
      });
    }).catch(fail);
  }

  function loadFiles(type, id, modal) {
    var endpoint = type === 'topics' ? '/api/topics/files' : '/api/assignments/files';
    var idKey = type === 'topics' ? 'topic_id' : 'assignment_id';
    request(endpoint, (function () { var payload = {}; payload[idKey] = id; return payload; })()).then(function (data) {
      var files = data.files || [];
      var listHtml = files.length ? files.map(function (file) {
        var filePath = EF.h.esc(file.file_path);
        var fileName = EF.h.esc(file.file_name);
        var deleteButton = '<button type="button" class="btn-icon is-danger" data-file-delete="' + Number(file.id) + '" data-tip="Delete file" aria-label="Delete file">' + EF.icon('trash', 14) + '</button>';
        return '<div class="mini-item"><div class="main"><div class="title">' + fileName + '</div><div class="meta">' + EF.util.bytes(file.file_size) + ' · ' + EF.h.esc(file.uploader_name || '') + '</div></div><div class="row-actions"><button type="button" class="btn-icon" data-file-preview="' + filePath + '" data-file-name="' + fileName + '" data-tip="Preview" aria-label="Preview">' + EF.icon('eye', 14) + '</button><a class="btn-icon" href="' + EF.util.url('/files/download', { path: filePath, name: fileName }) + '" data-tip="Download" aria-label="Download">' + EF.icon('download', 14) + '</a>' + deleteButton + '</div></div>';
      }).join('') : '<div class="empty"><h3>No files yet</h3><p>Upload reference material for this ' + (type === 'topics' ? 'topic' : 'assignment') + '.</p></div>';
      modal.find('[data-admin-file-list]').innerHTML = listHtml;
      modal.find('[data-admin-file-list]').querySelectorAll('[data-file-preview]').forEach(function (button) {
        button.addEventListener('click', function () { EF.viewFile({ path: button.dataset.filePreview, name: button.dataset.fileName }); });
      });
      modal.find('[data-admin-file-list]').querySelectorAll('[data-file-delete]').forEach(function (button) {
        button.addEventListener('click', function () {
          EF.remove({
            endpoint: type === 'topics' ? '/api/topics/delete-file' : '/api/assignments/delete-file',
            payload: { file_id: button.dataset.fileDelete }, list: null,
            title: 'Delete this file?', message: 'The uploaded file will be removed.', tone: 'danger',
            onSuccess: function () { loadFiles(type, id, modal); }
          });
        });
      });
    }).catch(fail);
  }

  function openFiles(type, item) {
    var id = item.id;
    var modal = EF.modal.open({
      title: (type === 'topics' ? 'Topic files' : 'Assignment files') + ' · ' + (item.title || item.name),
      size: 'lg',
      body: '<label class="field"><span class="field-label">Upload reference files</span><input class="input" type="file" data-admin-file-input multiple></label><div class="mini-list" data-admin-file-list><div class="muted small">Loading files…</div></div>',
      footer: '<button type="button" class="btn" data-modal-close>Close</button>'
    });
    var input = modal.find('[data-admin-file-input]');
    input.addEventListener('change', function () {
      var files = Array.prototype.slice.call(input.files || []);
      var endpoint = type === 'topics' ? '/api/topics/upload' : '/api/assignments/upload';
      var idKey = type === 'topics' ? 'topic_id' : 'assignment_id';
      files.reduce(function (chain, file) {
        return chain.then(function () {
          var body = new FormData();
          body.append(idKey, id);
          body.append('file', file);
          return request(endpoint, body);
        });
      }, Promise.resolve()).then(function () {
        EF.toast.success('Files uploaded.');
        input.value = '';
        loadFiles(type, id, modal);
      }).catch(fail);
    });
    loadFiles(type, id, modal);
  }

  function openRoster(batchId, kind) {
    request('/api/batches/members', { batch_id: batchId }).then(function (data) {
      var isTeacher = kind === 'teachers';
      var people = isTeacher ? (data.teachers || []) : (data.students || []);
      var selected = isTeacher ? (data.assigned || []) : (data.enrolled || []);
      var searchName = isTeacher ? 'teacher' : 'student';
      var items = people.map(function (person) {
        var checked = selected.indexOf(Number(person.id)) !== -1;
        return '<label class="choice"><input type="checkbox" value="' + Number(person.id) + '"' + (checked ? ' checked' : '') + '><span><strong>' + EF.h.esc(person.full_name) + '</strong><br><span class="muted tiny">' + EF.h.esc(person.email) + (person.user_id_number ? ' · ' + EF.h.esc(person.user_id_number) : '') + '</span></span></label>';
      }).join('');
      var modal = EF.modal.open({
        title: isTeacher ? 'Assign teachers' : 'Enroll students',
        size: 'lg',
        body: '<label class="search grow"><input class="input" type="search" data-roster-search placeholder="Search ' + searchName + 's"></label><div class="choice-grid" data-roster-list>' + (items || '<div class="empty"><h3>No ' + searchName + 's found</h3></div>') + '</div>',
        footer: '<button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-primary" data-roster-save>Save</button>'
      });
      modal.find('[data-roster-search]').addEventListener('input', function (event) {
        var term = event.target.value.toLowerCase();
        modal.find('[data-roster-list]').querySelectorAll('.choice').forEach(function (choice) {
          choice.hidden = choice.textContent.toLowerCase().indexOf(term) === -1;
        });
      });
      modal.find('[data-roster-save]').addEventListener('click', function () {
        var ids = Array.prototype.slice.call(modal.find('[data-roster-list]').querySelectorAll('input:checked')).map(function (input) { return input.value; });
        var payload = { batch_id: batchId };
        payload[isTeacher ? 'teacher_ids' : 'student_ids'] = ids.join(',');
        mutate(isTeacher ? '/api/batches/assign' : '/api/batches/enroll', payload).then(function () { modal.close(); }).catch(fail);
      });
    }).catch(fail);
  }

  function openFeedbackRounds() {
    Promise.all([request('/api/feedback/sessions'), rows('/api/batches/list', 'rows')]).then(function (results) {
      var sessions = results[0].sessions || [];
      var batches = results[1];
      var list = sessions.length ? sessions.map(function (session) {
        return '<div class="mini-item"><div class="main"><div class="title">' + EF.h.esc(session.title) + '</div><div class="meta">' + EF.h.esc(session.course_title) + ' · ' + EF.h.esc(session.batch_name) + ' · ' + session.entry_count + ' responses (' + session.unread_count + ' new)</div></div><div class="row-actions"><button type="button" class="btn-icon" data-round-edit="' + Number(session.batch_id) + '" data-title="' + EF.h.esc(session.title) + '" data-description="' + EF.h.esc(session.description || '') + '" data-tip="Edit round" aria-label="Edit round">' + EF.icon('edit', 15) + '</button><button type="button" class="btn-icon" data-round-toggle="' + Number(session.id) + '" data-active="' + Number(session.is_active) + '" data-tip="' + (Number(session.is_active) ? 'Close round' : 'Open round') + '" aria-label="Toggle feedback round">' + EF.icon(Number(session.is_active) ? 'refresh' : 'play', 15) + '</button><button type="button" class="btn-icon is-danger" data-round-delete="' + Number(session.id) + '" data-tip="Delete round" aria-label="Delete round">' + EF.icon('trash', 15) + '</button></div></div>';
      }).join('') : '<div class="empty"><h3>No feedback rounds yet</h3><p>Create one for a batch to collect anonymous responses.</p></div>';
      var batchOptions = batches.map(function (batch) { return '<option value="' + Number(batch.id) + '">' + EF.h.esc(batch.course_title + ' · ' + batch.name) + '</option>'; }).join('');
      var modal = EF.modal.open({
        title: 'Feedback rounds', size: 'lg',
        body: '<div class="form-grid"><label class="field"><span class="field-label">Batch</span><select class="select" data-round-batch><option value="">Select a batch</option>' + batchOptions + '</select></label><label class="field"><span class="field-label">Round title</span><input class="input" data-round-title value="Batch feedback"></label><label class="field span-2"><span class="field-label">Description</span><textarea class="textarea" data-round-description rows="2"></textarea></label></div><div class="divider"></div><div class="mini-list" data-round-list>' + list + '</div>',
        footer: '<button type="button" class="btn" data-modal-close>Close</button><button type="button" class="btn btn-primary" data-round-create>Create or update round</button>'
      });
      modal.find('[data-round-create]').addEventListener('click', function () {
        mutate('/api/feedback/create-session', {
          batch_id: modal.find('[data-round-batch]').value,
          title: modal.find('[data-round-title]').value,
          description: modal.find('[data-round-description]').value
        }, false).then(function () { modal.close(); openFeedbackRounds(); });
      });
      modal.find('[data-round-list]').addEventListener('click', function (event) {
        var edit = event.target.closest('[data-round-edit]');
        var toggle = event.target.closest('[data-round-toggle]');
        var remove = event.target.closest('[data-round-delete]');
        if (edit) {
          modal.find('[data-round-batch]').value = edit.dataset.roundEdit;
          modal.find('[data-round-title]').value = edit.dataset.title;
          modal.find('[data-round-description]').value = edit.dataset.description;
          modal.find('[data-round-title]').focus();
        } else if (toggle) {
          mutate('/api/feedback/toggle-session', { session_id: toggle.dataset.roundToggle }).then(function () { modal.close(); openFeedbackRounds(); });
        } else if (remove) {
          EF.remove({ endpoint: '/api/feedback/delete-session', payload: { session_id: remove.dataset.roundDelete }, list: null, title: 'Delete feedback round?', message: 'All responses for this round will also be deleted.', tone: 'danger', onSuccess: function () { modal.close(); openFeedbackRounds(); } });
        }
      });
    }).catch(fail);
  }

  function openTestBuilder(testId, copying) {
    Promise.all([
      rows('/api/batches/list', 'rows'),
      testId ? request('/api/tests/get', { test_id: testId }) : Promise.resolve(null)
    ]).then(function (results) {
      var batches = results[0];
      var source = results[1];
      var sourceTest = source && source.test;
      var sourceQuestions = source && source.questions ? source.questions : [];
      var options = batches.map(function (batch) { return '<option value="' + Number(batch.id) + '">' + EF.h.esc(batch.course_title + ' · ' + batch.name) + '</option>'; }).join('');
      var modal = EF.modal.open({
        title: testId && !copying ? 'Edit test' : (copying ? 'Copy test' : 'Create test'), size: 'lg',
        body: '<form class="form" data-test-form><div class="form-grid"><label class="field"><span class="field-label">Title</span><input class="input" name="title" required></label><label class="field"><span class="field-label">Type</span><select class="select" name="type"><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="entry">Entry</option></select></label><label class="field"><span class="field-label">Batch</span><select class="select" name="batch_id"><option value="">All batches</option>' + options + '</select></label><label class="field"><span class="field-label">Duration (minutes)</span><input class="input" type="number" name="time_minutes" min="1" max="480" value="30" required></label><label class="field"><span class="field-label">Status</span><select class="select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="draft">Draft</option></select></label><label class="field span-2"><span class="field-label">Description</span><textarea class="textarea" name="description" rows="2"></textarea></label></div><div class="divider"></div><div data-test-questions></div><button type="button" class="btn btn-sm" data-test-add-question>Add question</button></form>',
        footer: '<button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-primary" data-test-save>' + (testId && !copying ? 'Save changes' : 'Create test') + '</button>'
      });
      var questionList = modal.find('[data-test-questions]');
      var nextQuestion = 0;
      function addQuestion(question) {
        nextQuestion++;
        var number = nextQuestion;
        var block = document.createElement('section');
        block.className = 'panel-note';
        block.setAttribute('data-test-question', '');
        block.innerHTML = '<div class="row-center" style="justify-content:space-between"><strong>Question ' + number + '</strong><button type="button" class="btn-icon is-danger" data-test-remove aria-label="Remove question">' + EF.icon('trash', 14) + '</button></div><label class="field mt-10"><span class="field-label">Question text</span><textarea class="textarea" data-question-text rows="2" required></textarea></label><div class="form-grid"><label class="field"><span class="field-label">Option A</span><input class="input" data-option required></label><label class="field"><span class="field-label">Option B</span><input class="input" data-option required></label><label class="field"><span class="field-label">Option C</span><input class="input" data-option required></label><label class="field"><span class="field-label">Option D</span><input class="input" data-option required></label><label class="field"><span class="field-label">Correct option</span><select class="select" data-correct><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label></div>';
        questionList.appendChild(block);
        if (question) {
          block.querySelector('[data-question-text]').value = question.text || '';
          block.querySelectorAll('[data-option]').forEach(function (input, index) { input.value = question.options && question.options[index] ? question.options[index].text : ''; });
          var correct = (question.options || []).findIndex(function (option) { return option.is_correct; });
          block.querySelector('[data-correct]').value = correct < 0 ? 0 : correct;
        }
      }
      if (sourceTest) {
        var testForm = modal.find('[data-test-form]');
        testForm.elements.title.value = sourceTest.title + (copying ? ' (copy)' : '');
        testForm.elements.type.value = sourceTest.type;
        testForm.elements.batch_id.value = sourceTest.batch_id || '';
        testForm.elements.time_minutes.value = sourceTest.time_minutes;
        testForm.elements.status.value = copying ? 'active' : sourceTest.status;
        testForm.elements.description.value = sourceTest.description || '';
        sourceQuestions.forEach(addQuestion);
      } else {
        addQuestion();
      }
      modal.find('[data-test-add-question]').addEventListener('click', addQuestion);
      questionList.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-test-remove]');
        if (remove && questionList.querySelectorAll('[data-test-question]').length > 1) remove.closest('[data-test-question]').remove();
      });
      modal.find('[data-test-save]').addEventListener('click', function () {
        var form = modal.find('[data-test-form]');
        if (!form.reportValidity()) return;
        var questions = Array.prototype.slice.call(questionList.querySelectorAll('[data-test-question]')).map(function (block) {
          return {
            text: block.querySelector('[data-question-text]').value,
            options: Array.prototype.slice.call(block.querySelectorAll('[data-option]')).map(function (input) { return input.value; }),
            correct: Number(block.querySelector('[data-correct]').value),
            is_code: false
          };
        });
        var payload = Object.fromEntries(new FormData(form).entries());
        payload.questions = JSON.stringify(questions);
        if (testId && !copying) payload.test_id = testId;
        mutate(testId && !copying ? '/api/tests/update' : '/api/tests/create', payload).then(function () { modal.close(); }).catch(fail);
      });
    }).catch(fail);
  }

  function openLiveSession() {
    rows('/api/batches/list', 'rows').then(function (batches) {
      var options = selectOptions(batches, 'id', 'name', 'Select batch');
      EF.formModal({
        title: 'Start live session', endpoint: '/api/live/start', list: module.id,
        fields: [
          { name: 'batch_id', label: 'Batch', type: 'select', options: options, rules: 'required' },
          { name: 'title', label: 'Session title', value: 'Live class', rules: 'required|max:200' }
        ],
        onSuccess: function (response) {
          if (response.data && response.data.session_id) {
            mutate('/api/live/open', { session_id: response.data.session_id }, false)
              .then(function () { EF.reload(module.id); })
              .catch(fail);
          } else {
            EF.reload(module.id);
          }
        }
      });
    }).catch(fail);
  }

  function openNew() {
    if (module.type === 'feedback') return openFeedbackRounds();
    if (module.type === 'tests') return openTestBuilder();
    if (module.type === 'live') return openLiveSession();
    if (configs[module.type]) return openEditor(module.type, null);
  }

  function runAction(action, id, button) {
    var type = module.type;
    var cache = window.EF_ADMIN_ROW_DATA && window.EF_ADMIN_ROW_DATA[module.id];
    var item = cache && cache[id] ? cache[id] : {};
    if (action === 'edit') return type === 'tests' ? openTestBuilder(id, false) : editRecord(type, id);
    if (action === 'copy') return openTestBuilder(id, true);
    if (action === 'view') return detail(type, id);
    if (action === 'delete') return deleteRecord(type, id);
    if (action === 'status') {
      if (type === 'tests') return mutate('/api/tests/status', { test_id: id, status: item.status === 'active' ? 'inactive' : 'active' });
      return toggleStatus(type, id, item);
    }
    if (action === 'bypass') return mutate('/api/users/bypass', { user_id: id, bypass_gate: Number(item.bypass_gate) ? 0 : 1 });
    if (action === 'teachers' || action === 'students') return openRoster(id, action);
    if (action === 'files') return openFiles(type, item);
    if (action === 'grade') return openGrade(id);
    if (action === 'approve') return mutate('/api/applications/approve', { application_id: id });
    if (action === 'reject') return rejectApplications([id]);
    if (action === 'pin') return mutate('/api/announcements/pin', { announcement_id: id });
    if (action === 'review') return mutate('/api/feedback/mark-reviewed', { entry_id: id, reviewed: Number(item.is_reviewed) ? 0 : 1 });
    if (action === 'open') return mutate('/api/live/open', { session_id: id });
    if (action === 'end') return mutate('/api/live/end', { session_id: id });
    if (action === 'download') {
      var path = item.file_path;
      if (path) window.location.href = EF.util.url('/files/download', { path: path, name: item.file_name });
    }
  }

  function selectedIds() {
    var list = window.EF_LISTS && window.EF_LISTS[module.id];
    return list ? list.selected() : [];
  }

  function removeMany(type, ids) {
    if (type === 'users') {
      return Promise.all(ids.map(function (id) { return request('/api/users/delete', { user_id: id }); }));
    }
    if (type === 'feedback') return request('/api/feedback/delete-entry', { ids: ids.join(',') });
    var config = configs[type];
    return request(config.base + '/delete', { ids: ids.join(',') });
  }

  function exportList() {
    var list = window.EF_LISTS[module.id];
    var payload = list ? list.payload() : {};
    payload.per_page = 100;
    request(configs[module.type].base + '/list', payload).then(function (data) {
      var columns = (module.columns || []).filter(function (column) { return column.field && column.format !== 'actions'; });
      var csv = [columns.map(function (column) { return '"' + String(column.label || column.field).replace(/"/g, '""') + '"'; }).join(',')].concat((data.rows || []).map(function (row) {
        return columns.map(function (column) {
          var value = row[column.field];
          if (Array.isArray(value)) value = value.join(', ');
          return '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"';
        }).join(',');
      })).join('\r\n');
      var link = document.createElement('a');
      link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
      link.download = module.type + '.csv';
      link.click();
      URL.revokeObjectURL(link.href);
    }).catch(fail);
  }

  function openManualEnrollment() {
    rows('/api/batches/list', 'rows').then(function (batches) {
      var options = batches.map(function (batch) { return '<option value="' + Number(batch.id) + '">' + EF.h.esc(batch.course_title + ' · ' + batch.name) + '</option>'; }).join('');
      var modal = EF.modal.open({
        title: 'Enroll student', size: 'md',
        body: '<form class="form"><label class="field"><span class="field-label">Batch</span><select class="select" data-enroll-batch><option value="">Select batch</option>' + options + '</select></label><label class="field"><span class="field-label">Find eligible student</span><input class="input" type="search" data-enroll-search placeholder="Search name or student ID"></label><div class="mini-list" data-enroll-results></div></form>',
        footer: '<button type="button" class="btn" data-modal-close>Cancel</button><button type="button" class="btn btn-primary" data-enroll-save disabled>Enroll</button>'
      });
      var selected = null;
      function loadEligible() {
        selected = null;
        modal.find('[data-enroll-save]').disabled = true;
        var batchId = modal.find('[data-enroll-batch]').value;
        var search = modal.find('[data-enroll-search]').value;
        if (!batchId) return;
        request('/api/applications/eligible', { batch_id: batchId, search: search }).then(function (data) {
          var candidates = data.rows || [];
          modal.find('[data-enroll-results]').innerHTML = candidates.length ? candidates.map(function (student) {
            return '<button type="button" class="mini-item" data-enroll-student="' + Number(student.id) + '" style="width:100%;text-align:left"><span class="main"><span class="title">' + EF.h.esc(student.full_name) + '</span><span class="meta">' + EF.h.esc(student.user_id_number || student.email || '') + '</span></span></button>';
          }).join('') : '<div class="panel-note">No eligible students found.</div>';
        }).catch(fail);
      }
      modal.find('[data-enroll-batch]').addEventListener('change', loadEligible);
      modal.find('[data-enroll-search]').addEventListener('input', EF.util.debounce(loadEligible, 250));
      modal.find('[data-enroll-results]').addEventListener('click', function (event) {
        var button = event.target.closest('[data-enroll-student]');
        if (!button) return;
        selected = button.dataset.enrollStudent;
        modal.find('[data-enroll-save]').disabled = false;
        modal.find('[data-enroll-results]').querySelectorAll('[data-enroll-student]').forEach(function (item) { item.classList.toggle('is-selected', item === button); });
      });
      modal.find('[data-enroll-save]').addEventListener('click', function () {
        if (!selected) return;
        mutate('/api/applications/enroll', { batch_id: modal.find('[data-enroll-batch]').value, student_id: selected }, false)
          .then(function () { modal.close(); EF.reload(module.id); }).catch(fail);
      });
    }).catch(fail);
  }

  function runToolbar(action) {
    if (action === 'manual-enroll') return openManualEnrollment();
    if (action === 'export') return exportList();
    if (action === 'clear') {
      EF.remove({ endpoint: '/api/activity/clear', payload: {}, list: module.id, title: 'Clear activity logs?', message: 'This permanently deletes the activity history.', tone: 'danger' });
    }
  }

  function runBulk(action) {
    var ids = selectedIds();
    if (action === 'export') return exportList();
    if (action === 'clear') {
      EF.remove({ endpoint: '/api/activity/clear', payload: {}, list: module.id, title: 'Clear activity logs?', message: 'This permanently deletes the activity history.', tone: 'danger' });
      return;
    }
    if (!ids.length) return EF.toast.error('Select at least one row.');
    if (action === 'activate' || action === 'deactivate') {
      if (module.type !== 'users') {
        if (module.type === 'tests') {
          return Promise.all(ids.map(function (id) { return request('/api/tests/status', { test_id: id, status: action === 'activate' ? 'active' : 'inactive' }); }))
            .then(function () { EF.toast.success('Test statuses updated.'); EF.reload(module.id); }).catch(fail);
        }
        return;
      }
      return Promise.all(ids.map(function (id) { return request('/api/users/status', { user_id: id, status: action === 'activate' ? 'active' : 'inactive' }); }))
        .then(function () { EF.toast.success('User statuses updated.'); EF.reload(module.id); }).catch(fail);
    }
    if (action === 'approve') {
      return mutate('/api/applications/approve', { application_id: ids.join(',') });
    }
    if (action === 'reject') return rejectApplications(ids);
    if (action === 'review') return mutate('/api/feedback/mark-reviewed', { ids: ids.join(',') });
    if (action === 'download') {
      var data = window.EF_ADMIN_ROW_DATA[module.id] || {};
      ids.forEach(function (id) {
        if (data[id] && data[id].file_path) {
          var link = document.createElement('a');
          link.href = EF.util.url('/files/download', { path: data[id].file_path, name: data[id].file_name });
          link.download = data[id].file_name || 'submission';
          link.click();
        }
      });
      return;
    }
    if (action === 'delete') {
      EF.modal.confirm({ title: 'Delete selected records?', message: 'This action cannot be undone.', confirmText: 'Delete selected', tone: 'danger' }).then(function (yes) {
        if (!yes) return;
        removeMany(module.type, ids).then(function () { EF.toast.success('Selected records deleted.'); EF.reload(module.id); }).catch(fail);
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (event) {
      var create = event.target.closest('[data-admin-create]');
      if (create) return openNew();
      var action = event.target.closest('[data-admin-action]');
      if (action) return runAction(action.dataset.adminAction, action.dataset.id, action);
      var bulkAction = event.target.closest('[data-bulk-action]');
      if (bulkAction) runBulk(bulkAction.dataset.bulkAction);
      var toolbarAction = event.target.closest('[data-admin-toolbar]');
      if (toolbarAction) runToolbar(toolbarAction.dataset.adminToolbar);
    });
  });
})(window, document);