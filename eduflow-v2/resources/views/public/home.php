<?php
/** Public landing page. */
$courses = $courses ?? [];
$stats = $stats ?? [];
?>
<section class="hero">
  <div class="container hero-inner">
    <div>
      <span class="eyebrow"><?= icon('shield', 13) ?> Institution ready</span>
      <h1>Deliver courses, assignments and online assessments in one place.</h1>
      <p class="lead">
        EduFlow gives institutes a structured workspace for batch-based teaching: publish topics and material,
        collect assignment submissions, run timed multiple-choice assessments with automatic marking, host live
        classes and keep learners informed.
      </p>

      <div class="hero-actions">
        <?php if (user()): ?>
          <a class="btn btn-primary btn-lg" href="<?= e(url(App\Core\Auth::homeFor())) ?>">
            <?= icon('dashboard', 16) ?> Open my portal
          </a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>">
            <?= icon('user-plus', 16) ?> Create student account
          </a>
          <a class="btn btn-lg" href="<?= e(url('/login')) ?>">Sign in</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/entry-test')) ?>">
          <?= icon('checklist', 16) ?> Take an entry test
        </a>
      </div>

      <div class="hero-note">
        <?= icon('info', 14) ?>
        Applications are reviewed by the administration before batch admission.
      </div>
    </div>

    <div class="preview" aria-hidden="true">
      <div class="preview-head">
        <div>
          <div class="title">Entry assessment · Software Development</div>
          <div class="tiny muted">Question 3 of 20 · single choice</div>
        </div>
        <span class="badge badge-primary"><?= icon('checklist', 12) ?> Formative</span>
      </div>
      <div class="preview-body">
        <div class="preview-q">Which statement about PHP is correct?</div>
        <div class="preview-options">
          <div class="preview-option"><span class="mark">A</span> A compiled systems language</div>
          <div class="preview-option is-correct"><span class="mark"><?= icon('check', 12) ?></span> A server-side scripting language</div>
          <div class="preview-option"><span class="mark">C</span> A client-side styling language</div>
          <div class="preview-option"><span class="mark">D</span> A database engine</div>
        </div>
      </div>
      <div class="preview-foot">
        <span class="preview-timer"><?= icon('clock', 14) ?> 06:42 remaining</span>
        <span>Auto-marked · negative marking enabled</span>
      </div>
    </div>
  </div>
</section>

<section class="section-alt section">
  <div class="container">
    <div class="stat-strip">
      <div>
        <div class="value"><?= (int) ($stats['courses'] ?? 0) ?></div>
        <div class="label">Active courses</div>
      </div>
      <div>
        <div class="value"><?= (int) ($stats['batches'] ?? 0) ?></div>
        <div class="label">Running batches</div>
      </div>
      <div>
        <div class="value"><?= (int) ($stats['learners'] ?? 0) ?></div>
        <div class="label">Registered learners</div>
      </div>
      <div>
        <div class="value"><?= (int) ($stats['assessments'] ?? 0) ?></div>
        <div class="label">Assessments published</div>
      </div>
    </div>
  </div>
</section>

<section class="section" id="capabilities">
  <div class="container">
    <div class="section-head">
      <h2>Built around how institutes actually teach</h2>
      <p>Each capability maps to a specific step of the delivery cycle, from admission to final assessment.</p>
    </div>

    <div class="feature-grid">
      <?php
      $features = [
          ['book', 'Course & batch structure', 'Courses hold the curriculum; batches schedule delivery with their own students, teachers and dates.'],
          ['clipboard', 'Assignments & submissions', 'Publish work with deadlines, collect files in an organised folder tree, grade with feedback.'],
          ['checklist', 'Timed assessments', 'Build question banks, publish tests to a batch, auto-mark answers and release results.'],
          ['file-text', 'Learning material', 'Attach slide decks, source files and documents to topics in the order they are taught.'],
          ['video', 'Live classes', 'Start a session for a batch, notify participants and track attendance automatically.'],
          ['megaphone', 'Announcements & feedback', 'Broadcast operational notices and collect anonymous batch feedback per term.'],
          ['inbox', 'Admissions workflow', 'Applicants register, apply for a batch, sit the entry test and are approved by administrators.'],
          ['shield', 'Role based access', 'Students, teachers and administrators each see only what their role permits.'],
          ['activity', 'Auditable activity', 'Every meaningful action is written to an activity trail for review.'],
      ];
      foreach ($features as $feature): ?>
        <article class="feature">
          <div class="tile"><?= icon($feature[0], 18) ?></div>
          <h3><?= e($feature[1]) ?></h3>
          <p><?= e($feature[2]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-alt section" id="workflow">
  <div class="container">
    <div class="section-head">
      <h2>From application to certification</h2>
      <p>A predictable four-stage path keeps applicants, learners and staff aligned.</p>
    </div>

    <div class="steps">
      <div class="step">
        <h3>Register</h3>
        <p>Applicants create an account, verify their email address and receive a unique student ID.</p>
      </div>
      <div class="step">
        <h3>Apply &amp; assess</h3>
        <p>Choose an open batch and complete its entry assessment within the timed window.</p>
      </div>
      <div class="step">
        <h3>Learn</h3>
        <p>Access topics, download material, submit assignments and join scheduled live classes.</p>
      </div>
      <div class="step">
        <h3>Progress</h3>
        <p>Weekly and monthly tests are auto-marked with results, bands and feedback available instantly.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" id="assessment">
  <div class="container">
    <div class="grid grid-2" style="align-items:center;gap:48px">
      <div>
        <span class="eyebrow"><?= icon('checklist', 13) ?> Assessment engine</span>
        <h2 class="mt-16 mb-12">Assessment designed for real classroom conditions.</h2>
        <p class="muted">
          Tests are composed from a reusable question bank. Each question holds four options with a single correct
          answer, and learners may move freely between questions until the timer expires.
        </p>

        <div class="stack gap-16 mt-24">
          <?php
          $points = [
              ['clock', 'Configurable time limit', 'Per-test duration with a live countdown and automatic submission.'],
              ['target', 'Instant auto-marking', 'Correct answers score 1.00, incorrect answers deduct 0.25.'],
              ['chart', 'Band based reporting', 'Scores are grouped into Superb, Excellent, Good, Average and Weak bands.'],
              ['lock', 'Integrity controls', 'Question sets lock after the first submission and re-attempts are granted per learner.'],
          ];
          foreach ($points as $point): ?>
            <div class="auth-point">
              <span class="icon-tile" style="background:var(--brand-100);color:var(--brand-700)"><?= icon($point[0], 16) ?></span>
              <div>
                <div class="title" style="color:var(--ink-900)"><?= e($point[1]) ?></div>
                <div class="text" style="color:var(--ink-600)"><?= e($point[2]) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h3>Result summary</h3>
            <div class="sub">Weekly test · Algorithms</div>
          </div>
          <span class="badge badge-success"><?= icon('check-circle', 12) ?> Marked</span>
        </div>
        <div class="panel-body">
          <div class="score-ring mb-20">
            <div class="ring" style="background:conic-gradient(var(--success-600) 0deg, var(--success-600) 302deg, var(--surface-3) 302deg)">
              <div class="ring-inner">
                <div>
                  <div class="ring-value">84%</div>
                  <div class="ring-label">Score</div>
                </div>
              </div>
            </div>
            <div>
              <div class="mb-4"><span class="badge badge-success badge-dot">Excellent band</span></div>
              <div class="small muted">17 of 20 questions answered correctly.</div>
              <div class="small muted mt-4">Submitted in 12m 04s of a 30m window.</div>
            </div>
          </div>

          <div class="score-grid">
            <div class="score-box"><div class="value text-success">17</div><div class="label">Correct</div></div>
            <div class="score-box"><div class="value text-danger">2</div><div class="label">Incorrect</div></div>
            <div class="score-box"><div class="value">1</div><div class="label">Skipped</div></div>
            <div class="score-box"><div class="value">16.5</div><div class="label">Points</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($courses)): ?>
<section class="section-alt section">
  <div class="container">
    <div class="section-head">
      <h2>Courses currently delivered on <?= e(APP_NAME) ?></h2>
      <p>Batch schedules are published by the academic office.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ($courses as $course): ?>
        <article class="feature">
          <div class="tile"><?= icon('book', 18) ?></div>
          <h3><?= e($course['title']) ?></h3>
          <p class="clamp-2"><?= e(($course['description'] ?? '') !== '' ? $course['description'] : 'Curriculum delivered in scheduled batches.') ?></p>
          <div class="row-center gap-8 mt-12">
            <span class="badge badge-muted"><?= (int) $course['batch_count'] ?> batch<?= (int) $course['batch_count'] === 1 ? '' : 'es' ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="cta-band">
      <div>
        <h2>Ready to join the next batch?</h2>
        <p>Create an account, verify your email and submit your application. The academic office reviews every entry test result.</p>
      </div>
      <div class="row-center gap-10">
        <?php if (user()): ?>
          <a class="btn btn-primary btn-lg" href="<?= e(url(App\Core\Auth::homeFor())) ?>">Open my portal</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>">Create account</a>
          <a class="btn btn-lg" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.35)"
             href="<?= e(url('/login')) ?>">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

