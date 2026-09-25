<?php
// ============================================================
// GLOBAL HEADER — HTML head + CSS + JS imports
// ============================================================
// Expected vars: $pageTitle (string), $currentUser (array)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers.php';

$pageTitle  = $pageTitle ?? 'LMS';
$bodyClass  = $bodyClass ?? '';
$isAuthPage = $isAuthPage ?? false;

// Apply saved theme immediately (before page paint) to prevent flash
$theme = $_SESSION['theme'] ?? 'system';
if (isset($_currentUser['theme_preference'])) {
  $theme = $_currentUser['theme_preference'];
}
?>
<!DOCTYPE html>
<html lang="en" class="<?= $theme === 'dark' ? 'dark' : '' ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> — LMS</title>

  <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/assets/eduflow_icon.png">
  
  <!-- Preconnect -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>


  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <!-- Flatpickr date/time picker -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- Anti-FOUC: apply theme before paint -->
  <script>
    (function() {
      const saved = localStorage.getItem('lms_theme') || '<?= e($theme) ?>';
      if (saved === 'dark' || (saved === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <!-- Tailwind dark mode config -->
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {}
      }
    };
  </script>

  <!-- CSRF Token for JS -->
  <script>
    window.CSRF_TOKEN = '<?= e(csrfToken()) ?>';
    window.LMS_BASE = '<?= BASE_PATH ?>';
    window.USER_ROLE = '<?= e($_SESSION['role'] ?? '') ?>';
    window.USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    window.USER_GENDER = '<?= e($_currentUser['gender'] ?? '') ?>';
  </script>


  <!-- Core utilities available immediately (before footer scripts) -->
  <script>
    function debounce(fn, delay) {
      let t;
      return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), delay);
      };
    }
    async function ajax(url, data) {
      if (!data.csrf_token) data.csrf_token = window.CSRF_TOKEN;
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
      const res = await fetch(url, {
        method: 'POST',
        body: fd
      });
      return res.json();
    }

// ── Global file icon: mirrors PHP fileTypeIcon() in helpers.php ──────────
    // Accepts a full filename ("notes.py") OR a bare extension ("py").
    // Returns an HTML string: <span class="file-icon">…<svg>…</svg></span>
    window.fileIcon = function(filenameOrExt, size) {
      size = size || 20;
      var box  = size + 12;
      var ext  = (filenameOrExt || '').split('.').pop().toLowerCase().trim();
      var dark = document.documentElement.classList.contains('dark');

      // [lightBg, darkBg, strokeColor, svgPath]
      var lib = {
        // Documents
        pdf:   ['#fee2e2','rgba(239,68,68,0.18)',  '#ef4444','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'],
        doc:   ['#dbeafe','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        docx:  ['#dbeafe','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        odt:   ['#dbeafe','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        rtf:   ['#dbeafe','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        txt:   ['#f1f5f9','rgba(100,116,139,0.18)','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>'],
        md:    ['#f1f5f9','rgba(71,85,105,0.18)',  '#475569','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="9" y2="12"/><polyline points="12 12 12 15 15 12 15 15"/>'],
        // Spreadsheets
        xls:   ['#dcfce7','rgba(34,197,94,0.18)',  '#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 9 6 6m0-6-6 6"/>'],
        xlsx:  ['#dcfce7','rgba(34,197,94,0.18)',  '#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 9 6 6m0-6-6 6"/>'],
        ods:   ['#dcfce7','rgba(34,197,94,0.18)',  '#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/>'],
        csv:   ['#dcfce7','rgba(22,163,74,0.18)',  '#16a34a','<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>'],
        // Presentations
        ppt:   ['#ffedd5','rgba(249,115,22,0.18)', '#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        pptx:  ['#ffedd5','rgba(249,115,22,0.18)', '#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        odp:   ['#ffedd5','rgba(249,115,22,0.18)', '#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        // Archives
        zip:   ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>'],
        rar:   ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>'],
        gz:    ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        tar:   ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        '7z':  ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        // Images
        jpg:   ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        jpeg:  ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        png:   ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        gif:   ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        webp:  ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        svg:   ['#d1fae5','rgba(5,150,105,0.18)',  '#059669','<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"/>'],
        bmp:   ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>'],
        ico:   ['#d1fae5','rgba(16,185,129,0.18)', '#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>'],
        // Video
        mp4:   ['#ede9fe','rgba(139,92,246,0.18)', '#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        mkv:   ['#ede9fe','rgba(139,92,246,0.18)', '#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        avi:   ['#ede9fe','rgba(139,92,246,0.18)', '#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        mov:   ['#ede9fe','rgba(139,92,246,0.18)', '#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        webm:  ['#ede9fe','rgba(139,92,246,0.18)', '#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        // Audio
        mp3:   ['#fce7f3','rgba(236,72,153,0.18)', '#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'],
        wav:   ['#fce7f3','rgba(236,72,153,0.18)', '#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],
        ogg:   ['#fce7f3','rgba(236,72,153,0.18)', '#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],
        flac:  ['#fce7f3','rgba(236,72,153,0.18)', '#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],
        // Web / Frontend
        html:  ['#ffedd5','rgba(234,88,12,0.18)',  '#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        htm:   ['#ffedd5','rgba(234,88,12,0.18)',  '#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        css:   ['#dbeafe','rgba(37,99,235,0.18)',  '#2563eb','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        js:    ['#fef9c3','rgba(217,119,6,0.18)',  '#d97706','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        ts:    ['#dbeafe','rgba(29,78,216,0.18)',  '#1d4ed8','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        jsx:   ['#e0f2fe','rgba(2,132,199,0.18)',  '#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        tsx:   ['#e0f2fe','rgba(2,132,199,0.18)',  '#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        vue:   ['#dcfce7','rgba(22,163,74,0.18)',  '#16a34a','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        json:  ['#f8fafc','rgba(71,85,105,0.18)',  '#475569','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        xml:   ['#f1f5f9','rgba(100,116,139,0.18)','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        yaml:  ['#f1f5f9','rgba(100,116,139,0.18)','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        yml:   ['#f1f5f9','rgba(100,116,139,0.18)','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        // PHP / Laravel
        php:   ['#ede9fe','rgba(124,58,237,0.18)', '#7c3aed','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        sql:   ['#e0f2fe','rgba(3,105,161,0.18)',  '#0369a1','<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>'],
        env:   ['#f0fdf4','rgba(21,128,61,0.18)',  '#15803d','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        blade: ['#fce7f3','rgba(190,24,93,0.18)',  '#be185d','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        // Python
        py:    ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        pyc:   ['#fef9c3','rgba(161,98,7,0.18)',   '#a16207','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        pyw:   ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        ipynb: ['#fff7ed','rgba(249,115,22,0.18)', '#f97316','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        // Java
        java:  ['#fef3c7','rgba(180,83,9,0.18)',   '#b45309','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        jar:   ['#fef9c3','rgba(202,138,4,0.18)',  '#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        // C / C++ / C#
        c:     ['#dbeafe','rgba(30,64,175,0.18)',  '#1e40af','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        cpp:   ['#dbeafe','rgba(30,64,175,0.18)',  '#1e40af','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        h:     ['#eff6ff','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        hpp:   ['#eff6ff','rgba(59,130,246,0.18)', '#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        cs:    ['#f3e8ff','rgba(126,34,206,0.18)', '#7e22ce','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        // Other languages
        rb:    ['#fee2e2','rgba(220,38,38,0.18)',  '#dc2626','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        go:    ['#e0f2fe','rgba(3,105,161,0.18)',  '#0369a1','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        rs:    ['#fef3c7','rgba(146,64,14,0.18)',  '#92400e','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        kt:    ['#f5f3ff','rgba(109,40,217,0.18)', '#6d28d9','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        swift: ['#fff7ed','rgba(234,88,12,0.18)',  '#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        dart:  ['#e0f2fe','rgba(2,132,199,0.18)',  '#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        lua:   ['#ede9fe','rgba(109,40,217,0.18)', '#6d28d9','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        pl:    ['#fef9c3','rgba(180,83,9,0.18)',   '#b45309','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        scala: ['#fee2e2','rgba(220,38,38,0.18)',  '#dc2626','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        r:     ['#dbeafe','rgba(29,78,216,0.18)',  '#1d4ed8','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        // Shell / Scripts
        sh:    ['#f0fdf4','rgba(22,101,52,0.18)',  '#166534','<rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 18 9 12 15 12 15 18"/><polyline points="9 6 9 9 15 9 15 6"/>'],
        bash:  ['#f0fdf4','rgba(22,101,52,0.18)',  '#166534','<rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 18 9 12 15 12 15 18"/>'],
        bat:   ['#f0fdf4','rgba(21,128,61,0.18)',  '#15803d','<rect x="3" y="3" width="18" height="18" rx="2"/>'],
        ps1:   ['#eff6ff','rgba(29,78,216,0.18)',  '#1d4ed8','<rect x="3" y="3" width="18" height="18" rx="2"/>'],
      };
      var fallback = ['#f1f5f9','rgba(100,116,139,0.18)','#64748b','<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>'];
      var d    = lib[ext] || fallback;
      var bg   = dark ? d[1] : d[0];
      return '<span class="file-icon" style="background:' + bg + ';display:inline-flex;align-items:center;justify-content:center;width:' + box + 'px;height:' + box + 'px;border-radius:7px;flex-shrink:0;">'
           + '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="' + d[2] + '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + d[3] + '</svg>'
           + '</span>';
    };
  </script>
  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>

<body class="<?= e($bodyClass) ?>">
  <!-- Cursor elements (desktop only) -->
  <div id="cursor-outer"></div>
  <div id="cursor-inner"></div>

  <!-- Toast container -->
  <div id="toast-container"></div>

  <!-- Sidebar overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebar-overlay"></div>