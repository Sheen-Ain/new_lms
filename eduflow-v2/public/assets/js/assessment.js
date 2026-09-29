/* ============================================================
   EduFlow V2 — assessment.js
   Timed multiple-choice runner used by the entry test and every
   in-portal assessment. Each answer is saved as it is chosen so a
   dropped connection never loses progress.
   ============================================================ */
(function (window, document) {
  'use strict';

  var EF = window.EF;
  if (!EF) return;

  /**
   * options: questions, timeLimit (seconds), attemptId, answers,
   *          saveUrl, submitUrl, onSubmitted
   */
  function Runner(options) {
    this.options = options || {};
    this.questions = this.options.questions || [];
    this.answers = this.options.answers || {};
    this.flags = {};
    this.current = 0;
    this.remaining = Number(this.options.timeLimit || 0);
    this.timer = null;
    this.submitting = false;

    this.els = {
      palette: document.getElementById('question-palette'),
      stage: document.getElementById('question-stage'),
      counter: document.getElementById('question-counter'),
      timer: document.getElementById('attempt-timer'),
      progress: document.getElementById('attempt-progress'),
      prev: document.getElementById('btn-prev'),
      next: document.getElementById('btn-next'),
      submit: document.getElementById('btn-submit'),
      flag: document.getElementById('btn-flag')
    };

    this.bind();
    this.startTimer();
    this.showQuestion(0);
  }

  Runner.prototype.bind = function () {
    var self = this;
    if (this.els.prev) this.els.prev.addEventListener('click', function () { self.showQuestion(self.current - 1); });
    if (this.els.next) this.els.next.addEventListener('click', function () { self.showQuestion(self.current + 1); });
    if (this.els.submit) this.els.submit.addEventListener('click', function () { self.requestSubmit(); });
    if (this.els.flag) this.els.flag.addEventListener('click', function () { self.toggleFlag(); });

    document.addEventListener('keydown', function (event) {
      if (event.target.matches('input, textarea, select')) return;
      if (event.key === 'ArrowLeft') self.showQuestion(self.current - 1);
      if (event.key === 'ArrowRight') self.showQuestion(self.current + 1);
    });

    window.addEventListener('beforeunload', function (event) {
      if (!self.submitting && Object.keys(self.answers).length) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
  };

  Runner.prototype.startTimer = function () {
    var self = this;
    if (this.remaining <= 0) {
      if (this.els.timer) this.els.timer.classList.add('hidden');
      return;
    }

    this.paintTimer();
    this.timer = setInterval(function () {
      self.remaining -= 1;
      if (self.remaining <= 0) {
        self.remaining = 0;
        self.paintTimer();
        clearInterval(self.timer);
        EF.toast.warning('Time is up — submitting your answers.');
        self.submit(true);
        return;
      }
      self.paintTimer();
    }, 1000);
  };

  Runner.prototype.paintTimer = function () {
    if (!this.els.timer) return;
    var minutes = Math.floor(this.remaining / 60);
    var seconds = this.remaining % 60;
    this.els.timer.innerHTML = EF.icon('clock', 16) +
      '<span>' + ('0' + minutes).slice(-2) + ':' + ('0' + seconds).slice(-2) + '</span>';
    this.els.timer.classList.toggle('is-warning', this.remaining <= 300 && this.remaining > 60);
    this.els.timer.classList.toggle('is-danger', this.remaining <= 60);
  };

  Runner.prototype.renderPalette = function () {
    var self = this;
    if (!this.els.palette) return;

    var html = '<div class="palette-title">Questions</div><div class="palette-grid">';
    this.questions.forEach(function (question, index) {
      var classes = ['palette-btn'];
      if (self.answers[question.id]) classes.push('is-answered');
      if (self.flags[question.id]) classes.push('is-flagged');
      if (index === self.current) classes.push('is-current');
      html += '<button type="button" class="' + classes.join(' ') + '" data-index="' + index + '">' + (index + 1) + '</button>';
    });
    html += '</div><div class="palette-legend">' +
      '<span><i class="legend-dot is-answered"></i> Answered</span>' +
      '<span><i class="legend-dot"></i> Unanswered</span>' +
      '<span><i class="legend-dot is-flagged"></i> Flagged for review</span>' +
      '<span><i class="legend-dot is-current"></i> Current question</span>' +
      '</div>';

    this.els.palette.innerHTML = html;
    this.els.palette.querySelectorAll('[data-index]').forEach(function (button) {
      button.addEventListener('click', function () {
        self.showQuestion(Number(button.getAttribute('data-index')));
      });
    });
  };

  Runner.prototype.showQuestion = function (index) {
    if (index < 0 || index >= this.questions.length) return;
    this.current = index;

    var self = this;
    var question = this.questions[index];
    var selected = this.answers[question.id];

    var html = '<div class="question-index">Question ' + (index + 1) + ' of ' + this.questions.length + '</div>';
    html += question.is_code
      ? '<div class="question-text is-code">' + EF.util.escape(question.text) + '</div>'
      : '<div class="question-text">' + EF.util.escape(question.text) + '</div>';

    html += '<div class="options" role="radiogroup" aria-label="Answer options">';
    question.options.forEach(function (option, optionIndex) {
      var isSelected = String(selected) === String(option.id);
      html += '<label class="option' + (isSelected ? ' is-selected' : '') + '">' +
        '<input type="radio" name="answer" value="' + option.id + '"' + (isSelected ? ' checked' : '') + '>' +
        '<span class="key">' + String.fromCharCode(65 + optionIndex) + '</span>' +
        '<span>' + EF.util.escape(option.text) + '</span></label>';
    });
    html += '</div>';

    this.els.stage.innerHTML = html;

    this.els.stage.querySelectorAll('input[name="answer"]').forEach(function (input) {
      input.addEventListener('change', function () { self.record(question.id, input.value); });
    });

    if (this.els.counter) {
      this.els.counter.textContent = 'Question ' + (index + 1) + ' of ' + this.questions.length;
    }
    if (this.els.progress) {
      this.els.progress.style.width = Math.round(((index + 1) / this.questions.length) * 100) + '%';
    }
    if (this.els.prev) this.els.prev.disabled = index === 0;
    if (this.els.next) this.els.next.classList.toggle('hidden', index === this.questions.length - 1);
    if (this.els.flag) this.els.flag.classList.toggle('btn-warning', !!this.flags[question.id]);

    this.renderPalette();
    this.updateAnsweredCount();
  };

  Runner.prototype.record = function (questionId, optionId) {
    this.answers[questionId] = optionId;
    var selected = document.querySelectorAll('.option.is-selected');
    Array.prototype.forEach.call(selected, function (el) { el.classList.remove('is-selected'); });
    var checked = this.els.stage.querySelector('input[name="answer"]:checked');
    if (checked) checked.closest('.option').classList.add('is-selected');

    this.renderPalette();
    this.updateAnsweredCount();

    if (!this.options.saveUrl) return;
    var payload = { question_id: questionId, option_id: optionId };
    if (this.options.attemptId) payload.attempt_id = this.options.attemptId;
    EF.api.post(this.options.saveUrl, payload).catch(function () {
      EF.toast.warning('Answer stored locally only — connection issue.');
    });
  };

  Runner.prototype.toggleFlag = function () {
    var question = this.questions[this.current];
    this.flags[question.id] = !this.flags[question.id];
    if (this.els.flag) this.els.flag.classList.toggle('btn-warning', !!this.flags[question.id]);
    this.renderPalette();
  };

  Runner.prototype.updateAnsweredCount = function () {
    var element = document.getElementById('answered-count');
    if (element) element.textContent = Object.keys(this.answers).length;
  };

  Runner.prototype.requestSubmit = function () {
    var self = this;
    var answered = Object.keys(this.answers).length;
    var total = this.questions.length;
    var unanswered = total - answered;

    EF.modal.confirm({
      title: 'Submit your answers?',
      tone: unanswered > 0 ? 'warning' : 'info',
      confirmText: 'Submit now',
      message: unanswered > 0
        ? unanswered + ' of ' + total + ' questions are unanswered. Unanswered questions score zero and answers cannot be changed after submitting.'
        : 'All ' + total + ' questions are answered. Answers cannot be changed after submitting.'
    }).then(function (ok) {
      if (ok) self.submit(false);
    });
  };

  Runner.prototype.submit = function (forced) {
    var self = this;
    if (this.submitting) return;
    this.submitting = true;
    if (this.timer) clearInterval(this.timer);
    if (this.els.submit) {
      this.els.submit.classList.add('is-loading');
      this.els.submit.disabled = true;
    }

    var payload = { answers: JSON.stringify(this.answers), forced: forced ? 1 : 0 };
    if (this.options.attemptId) payload.attempt_id = this.options.attemptId;

    EF.api.post(this.options.submitUrl, payload)
      .then(function (response) {
        if (response.status === 'success') {
          window.onbeforeunload = null;
          if (typeof self.options.onSubmitted === 'function') {
            self.options.onSubmitted(response);
            return;
          }
          EF.toast.success(response.message);
          setTimeout(function () { window.location.reload(); }, 900);
        } else {
          self.submitting = false;
          if (self.els.submit) {
            self.els.submit.classList.remove('is-loading');
            self.els.submit.disabled = false;
          }
          EF.toast.error(response.message || 'Submission failed. Please try again.');
        }
      })
      .catch(function () {
        self.submitting = false;
        if (self.els.submit) {
          self.els.submit.classList.remove('is-loading');
          self.els.submit.disabled = false;
        }
        EF.toast.error('Submission failed. Check your connection and try again.');
      });
  };

  EF.Runner = Runner;
  EF.runner = function (options) { return new Runner(options); };

  /** Paint a result ring from a percentage. */
  EF.paintRing = function (element, percentage, tone) {
    if (!element) return;
    var degrees = Math.max(0, Math.min(100, Number(percentage) || 0)) * 3.6;
    var color = tone === 'success' ? 'var(--success-600)'
      : tone === 'warning' ? 'var(--warning-600)'
        : tone === 'danger' ? 'var(--danger-600)' : 'var(--brand-600)';
    element.style.background = 'conic-gradient(' + color + ' 0deg, ' + color + ' ' + degrees +
      'deg, var(--surface-3) ' + degrees + 'deg)';
  };

  window.EF = EF;
})(window, document);
