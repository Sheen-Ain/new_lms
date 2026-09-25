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

  window.EF = EF;
})(window, document);
