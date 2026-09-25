<?php
// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Human-readable time-ago string
 */
function timeAgo($datetime)
{
    if (!$datetime) return 'Never';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Human-readable file size
 */
function fileSizeHuman($bytes)
{
    if (!$bytes) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

/**
 * File type icon HTML based on extension
 */
function fileTypeIcon($extension, $size = 20)
{
    $ext = strtolower($extension);
    $s   = (int)$size;
    $box = $s + 12;

    // ── SVG path library ─────────────────────────────────────────
    // Each entry: [bg-color, icon-color, svg-path-data]
    $svgLib = [
        // ── Documents ────────────────────────────────────────────
        'pdf'   => ['#fee2e2','#ef4444','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'],
        'doc'   => ['#dbeafe','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'docx'  => ['#dbeafe','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'odt'   => ['#dbeafe','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'rtf'   => ['#dbeafe','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'txt'   => ['#f1f5f9','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>'],
        'md'    => ['#f1f5f9','#475569','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="9" y2="12"/><polyline points="12 12 12 15 15 12 15 15"/>'],

        // ── Spreadsheets ─────────────────────────────────────────
        'xls'   => ['#dcfce7','#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 9 6 6m0-6-6 6"/>'],
        'xlsx'  => ['#dcfce7','#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 9 6 6m0-6-6 6"/>'],
        'ods'   => ['#dcfce7','#22c55e','<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/>'],
        'csv'   => ['#dcfce7','#16a34a','<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>'],

        // ── Presentations ─────────────────────────────────────────
        'ppt'   => ['#ffedd5','#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        'pptx'  => ['#ffedd5','#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        'odp'   => ['#ffedd5','#f97316','<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],

        // ── Archives ─────────────────────────────────────────────
        'zip'   => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>'],
        'rar'   => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>'],
        '7z'    => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        'tar'   => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],
        'gz'    => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],

        // ── Images ────────────────────────────────────────────────
        'jpg'   => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'jpeg'  => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'png'   => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'gif'   => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'webp'  => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'],
        'svg'   => ['#d1fae5','#059669','<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z"/>'],
        'bmp'   => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>'],
        'ico'   => ['#d1fae5','#10b981','<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>'],

        // ── Video ─────────────────────────────────────────────────
        'mp4'   => ['#ede9fe','#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        'mkv'   => ['#ede9fe','#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        'avi'   => ['#ede9fe','#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        'mov'   => ['#ede9fe','#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        'webm'  => ['#ede9fe','#8b5cf6','<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],

        // ── Audio ─────────────────────────────────────────────────
        'mp3'   => ['#fce7f3','#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>'],
        'wav'   => ['#fce7f3','#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],
        'ogg'   => ['#fce7f3','#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],
        'flac'  => ['#fce7f3','#ec4899','<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/>'],

        // ── Web / Frontend ────────────────────────────────────────
        'html'  => ['#ffedd5','#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'htm'   => ['#ffedd5','#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'css'   => ['#dbeafe','#2563eb','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'js'    => ['#fef9c3','#d97706','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'ts'    => ['#dbeafe','#1d4ed8','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'jsx'   => ['#e0f2fe','#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'tsx'   => ['#e0f2fe','#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'vue'   => ['#dcfce7','#16a34a','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'json'  => ['#f8fafc','#475569','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'xml'   => ['#f1f5f9','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'yaml'  => ['#f1f5f9','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'yml'   => ['#f1f5f9','#64748b','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],

        // ── PHP / Laravel ────────────────────────────────────────
        'php'   => ['#ede9fe','#7c3aed','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'sql'   => ['#e0f2fe','#0369a1','<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>'],
        'env'   => ['#f0fdf4','#15803d','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'blade' => ['#fce7f3','#be185d','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],

        // ── Python ───────────────────────────────────────────────
        'py'    => ['#fef9c3','#ca8a04','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'pyc'   => ['#fef9c3','#a16207','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'pyw'   => ['#fef9c3','#ca8a04','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'ipynb' => ['#fff7ed','#f97316','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],

        // ── Java ─────────────────────────────────────────────────
        'java'  => ['#fef3c7','#b45309','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'class' => ['#fef3c7','#a16207','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'jar'   => ['#fef9c3','#ca8a04','<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>'],

        // ── C / C++ ───────────────────────────────────────────────
        'c'     => ['#dbeafe','#1e40af','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'cpp'   => ['#dbeafe','#1e40af','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'h'     => ['#eff6ff','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
        'hpp'   => ['#eff6ff','#3b82f6','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],

        // ── C# ────────────────────────────────────────────────────
        'cs'    => ['#f3e8ff','#7e22ce','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],

        // ── Other Languages ───────────────────────────────────────
        'rb'    => ['#fee2e2','#dc2626','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'go'    => ['#e0f2fe','#0369a1','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'rs'    => ['#fef3c7','#92400e','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'kt'    => ['#f5f3ff','#6d28d9','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'swift' => ['#fff7ed','#ea580c','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'dart'  => ['#e0f2fe','#0284c7','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'lua'   => ['#ede9fe','#6d28d9','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'pl'    => ['#fef9c3','#b45309','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'scala' => ['#fee2e2','#dc2626','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],
        'r'     => ['#dbeafe','#1d4ed8','<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'],

        // ── Shell / Scripts ────────────────────────────────────────
        'sh'    => ['#f0fdf4','#166534','<rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 18 9 12 15 12 15 18"/><polyline points="9 6 9 9 15 9 15 6"/>'],
        'bash'  => ['#f0fdf4','#166534','<rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 18 9 12 15 12 15 18"/>'],
        'bat'   => ['#f0fdf4','#15803d','<rect x="3" y="3" width="18" height="18" rx="2"/>'],
        'ps1'   => ['#eff6ff','#1d4ed8','<rect x="3" y="3" width="18" height="18" rx="2"/>'],
    ];

    if (isset($svgLib[$ext])) {
        [$bg, $color, $path] = $svgLib[$ext];
    } else {
        [$bg, $color, $path] = ['#f1f5f9', '#64748b',
            '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>'];
    }

    return sprintf(
        '<span class="file-icon" style="background:%s;display:inline-flex;align-items:center;justify-content:center;width:%dpx;height:%dpx;border-radius:7px;flex-shrink:0;">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">%s</svg>'
        . '</span>',
        $bg, $box, $box, $s, $s, $color, $path
    );
}

/**
 * Truncate text to given length
 */
function truncateText($text, $length = 80)
{
    if (!$text) return '';
    $clean = strip_tags($text);
    if (mb_strlen($clean) <= $length) return htmlspecialchars($clean);
    return htmlspecialchars(mb_substr($clean, 0, $length)) . '…';
}

/**
 * Status badge HTML
 */
function statusBadge($status)
{
    $badges = [
        'active'    => ['class' => 'badge-success',  'dot' => '#10b981', 'label' => 'Active'],
        'inactive'  => ['class' => 'badge-warning',  'dot' => '#f59e0b', 'label' => 'Inactive'],
        'archived'  => ['class' => 'badge-secondary', 'dot' => '#64748b', 'label' => 'Archived'],
        'completed' => ['class' => 'badge-info',     'dot' => '#06b6d4', 'label' => 'Completed'],
        'submitted' => ['class' => 'badge-info',     'dot' => '#3b82f6', 'label' => 'Submitted'],
        'graded'    => ['class' => 'badge-success',  'dot' => '#10b981', 'label' => 'Graded'],
        'returned'  => ['class' => 'badge-purple',   'dot' => '#8b5cf6', 'label' => 'Returned'],
        'published' => ['class' => 'badge-success',  'dot' => '#10b981', 'label' => 'Published'],
        'draft'     => ['class' => 'badge-secondary', 'dot' => '#64748b', 'label' => 'Draft'],
    ];
    $b = $badges[$status] ?? ['class' => 'badge-secondary', 'dot' => '#64748b', 'label' => ucfirst($status)];
    return sprintf(
        '<span class="badge %s"><span class="badge-dot" style="background:%s"></span>%s</span>',
        $b['class'],
        $b['dot'],
        htmlspecialchars($b['label'])
    );
}

/**
 * Priority badge HTML
 */
function priorityBadge($priority)
{
    $badges = [
        'normal'    => ['class' => 'badge-secondary', 'label' => 'Normal'],
        'important' => ['class' => 'badge-warning',   'label' => 'Important'],
        'urgent'    => ['class' => 'badge-danger badge-pulse', 'label' => '🔴 Urgent'],
    ];
    $b = $badges[$priority] ?? ['class' => 'badge-secondary', 'label' => ucfirst($priority)];
    return sprintf('<span class="badge %s">%s</span>', $b['class'], htmlspecialchars($b['label']));
}

/**
 * User avatar HTML (image or initials fallback)
 */
function userAvatar($user, $size = 40)
{
    $name = htmlspecialchars($user['full_name'] ?? 'U');
    $isOnline = !empty($user['is_online']);

    // Generate color from name
    $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];
    $colorIndex = crc32($name) % count($colors);
    $color = $colors[abs($colorIndex)];

    // Initials
    $parts = explode(' ', trim($user['full_name'] ?? 'U'));
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

    $onlineDot = $isOnline
        ? '<span class="avatar-online-dot"></span>'
        : '';

    $s  = $size;
    $fs = (int)($size * 0.36);

    if (!empty($user['profile_picture'])) {
        // Build both disk path and URL using BASE_PATH constant (not template syntax)
        $pic      = $user['profile_picture'];
        $diskPath = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . '/uploads/profiles/' . $pic;
        $urlPath  = BASE_PATH . '/uploads/profiles/' . htmlspecialchars($pic, ENT_QUOTES);
        if (file_exists($diskPath)) {
            return '<div class="avatar-wrapper" style="width:' . $s . 'px;height:' . $s . 'px;position:relative;flex-shrink:0;">'
                . '<img src="' . $urlPath . '" style="width:' . $s . 'px;height:' . $s . 'px;border-radius:50%;object-fit:cover;" alt="' . $name . '">'
                . $onlineDot
                . '</div>';
        }
    }

    return '<div class="avatar-wrapper" style="width:' . $s . 'px;height:' . $s . 'px;position:relative;flex-shrink:0;">'
        . '<div style="width:' . $s . 'px;height:' . $s . 'px;background:' . $color . ';color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:' . $fs . 'px;font-family:Poppins,sans-serif;">' . $initials . '</div>'
        . $onlineDot
        . '</div>';
}

/**
 * Pagination HTML
 */
function paginate($total, $page, $perPage, $urlPattern = '?page={page}')
{
    $totalPages = max(1, ceil($total / $perPage));
    if ($totalPages <= 1) return '';

    $page = max(1, min($page, $totalPages));
    $start = ($page - 1) * $perPage + 1;
    $end   = min($page * $perPage, $total);

    $html  = '<div class="pagination-wrapper">';
    $html .= '<span class="pagination-info">Showing ' . number_format($start) . '–' . number_format($end) . ' of ' . number_format($total) . ' results</span>';
    $html .= '<div class="pagination-controls">';

    // Prev
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $prevUrl = str_replace('{page}', $page - 1, $urlPattern);
    $html .= '<a href="' . ($page > 1 ? $prevUrl : '#') . '" class="page-btn' . $prevDisabled . '">‹</a>';

    // Pages
    $window = 2;
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - $window && $i <= $page + $window)) {
            $active = $i == $page ? ' active' : '';
            $url = str_replace('{page}', $i, $urlPattern);
            $html .= '<a href="' . $url . '" class="page-btn' . $active . '">' . $i . '</a>';
        } elseif ($i == $page - $window - 1 || $i == $page + $window + 1) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
    }

    // Next
    $nextDisabled = $page >= $totalPages ? ' disabled' : '';
    $nextUrl = str_replace('{page}', $page + 1, $urlPattern);
    $html .= '<a href="' . ($page < $totalPages ? $nextUrl : '#') . '" class="page-btn' . $nextDisabled . '">›</a>';

    $html .= '</div></div>';
    return $html;
}

/**
 * Log user activity
 */
function logActivity($conn, $userId, $action, $section = 'general')
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Handle proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : 'unknown';

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, section, ip_address) VALUES (?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param('isss', $userId, $action, $section, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Format date/datetime nicely
 */
function formatDate($datetime, $format = 'M j, Y')
{
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Format datetime with time
 */
function formatDateTime($datetime)
{
    if (!$datetime) return '—';
    return date('M j, Y g:i A', strtotime($datetime));
}

/**
 * Due date display component
 */
function dueDateBadge($dueDate, $allowLate = false)
{
    if (!$dueDate) return '<span class="text-muted">No due date</span>';

    $now  = time();
    $due  = strtotime($dueDate);
    $diff = $due - $now;

    if ($diff < 0) {
        $overdueDays = abs(floor($diff / 86400));
        return '<span class="due-badge due-overdue">🔴 Overdue ' . ($overdueDays > 0 ? $overdueDays . 'd' : 'today') . ' · ' . date('M j', $due) . '</span>';
    } elseif ($diff < 86400) {
        return '<span class="due-badge due-soon">🟡 Due today · ' . date('g:i A', $due) . '</span>';
    } elseif ($diff < 86400 * 3) {
        $days = ceil($diff / 86400);
        return '<span class="due-badge due-near">🟠 Due in ' . $days . 'd · ' . date('M j', $due) . '</span>';
    } else {
        return '<span class="due-badge due-ok">🟢 Due ' . date('M j, Y', $due) . '</span>';
    }
}

/**
 * Sanitize output
 */
function e($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get role badge
 */
function roleBadge($role)
{
    $map = [
        'admin'   => 'badge-danger',
        'teacher' => 'badge-info',
        'student' => 'badge-success',
    ];
    $class = $map[$role] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . ucfirst(e($role)) . '</span>';
}

/**
 * Generate CSRF token
 */
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrf($token)
{
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Send JSON response and exit
 */
function jsonResponse($status, $message, $data = [])
{
    // Discard any stray output (PHP warnings, notices) so JSON is always clean
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

/**
 * REPLACEMENT for saveUpload() and validateUpload() in includes/helpers.php
 * 
 * Replace the existing saveUpload() and validateUpload() functions in helpers.php
 * with these versions. Everything else in helpers.php stays the same.
 *
 * ─── PASTE THIS OVER THE TWO FUNCTIONS IN helpers.php ───────────────────────
 */

/* ── Sanitize a string for use as a folder/filename ─────────── */
function sanitizeSlug(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9_\-]+/', '_', $s);  // keep alphanumeric, dash, underscore
    $s = preg_replace('/_+/', '_', $s);               // collapse multiple underscores
    return rtrim(ltrim($s, '_-'), '_-') ?: 'file';
}

/* ── Pad user/student ID to 5 digits ─────────────────────────── */
function paddedId(int $id): string {
    return str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

/**
 * validateUpload($file, $allowedExts, $maxSizeMB)
 * Returns ['ok' => bool, 'error' => string]
 */
function validateUpload(array $file, array $allowedTypes = [], float $maxSizeMB = 10): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errs = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing upload tmp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        return ['ok' => false, 'error' => $errs[$file['error']] ?? 'Upload error'];
    }
    if ($file['size'] > $maxSizeMB * 1024 * 1024) {
        return ['ok' => false, 'error' => "File too large. Max size is {$maxSizeMB}MB"];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($allowedTypes && !in_array($ext, $allowedTypes, true)) {
        return ['ok' => false, 'error' => 'File type not allowed: .' . $ext];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * saveUpload($file, $context, $contextMeta)
 *
 * Structured storage:
 *
 *   Topic file:
 *     uploads/topics/{topic_slug}/original_filename.ext
 *     $context = 'topics', $contextMeta = ['topic_title' => '...']
 *
 *   Assignment file:
 *     uploads/assignments/{assignment_slug}/original_filename.ext
 *     $context = 'assignments', $contextMeta = ['assignment_title' => '...']
 *
 *   Submission file:
 *     uploads/submissions/{00012_firstname}/original_filename.ext
 *     $context = 'submissions', $contextMeta = ['student_id' => N, 'student_name' => '...']
 *
 *   Profile image:
 *     uploads/profiles/{00001_firstname}/profile.ext
 *     $context = 'profiles', $contextMeta = ['user_id' => N, 'user_name' => '...', 'old_path' => '...']
 *
 * Returns: the relative path stored in DB (e.g. "looping/notes.pdf")
 *          or null on failure.
 */
function saveUpload(array $file, string $context, array $contextMeta = []): ?string {
    $uploadRoot = __DIR__ . '/../uploads/';
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $orig = pathinfo($file['name'], PATHINFO_FILENAME); // name without ext

    switch ($context) {
        /* ── TOPICS ───────────────────────────────────────────── */
        case 'topics': {
            $topicSlug = sanitizeSlug($contextMeta['topic_title'] ?? 'topic');
            $dir = $uploadRoot . 'topics/' . $topicSlug . '/';
            @mkdir($dir, 0755, true);
            $safeName  = sanitizeSlug($orig) . '.' . $ext;
            // Avoid collisions
            $safeName  = _uniqueFilename($dir, $safeName);
            if (!move_uploaded_file($file['tmp_name'], $dir . $safeName)) return null;
            return $topicSlug . '/' . $safeName;
        }

        /* ── ASSIGNMENTS ───────────────────────────────────────── */
        case 'assignments': {
            $aSlug = sanitizeSlug($contextMeta['assignment_title'] ?? 'assignment');
            $dir   = $uploadRoot . 'assignments/' . $aSlug . '/';
            @mkdir($dir, 0755, true);
            $safeName = sanitizeSlug($orig) . '.' . $ext;
            $safeName = _uniqueFilename($dir, $safeName);
            if (!move_uploaded_file($file['tmp_name'], $dir . $safeName)) return null;
            return $aSlug . '/' . $safeName;
        }

        /* ── SUBMISSIONS ───────────────────────────────────────── */
        // Structure: submissions/assignment_name/00012_ali_filename.ext
        case 'submissions': {
            $sid        = (int)($contextMeta['student_id'] ?? 0);
            $sname      = sanitizeSlug($contextMeta['student_name'] ?? 'student');
            // Use user_id_number (5-digit DB field) if provided, else pad primary id
            $idStr      = !empty($contextMeta['student_id_number'])
                          ? $contextMeta['student_id_number']
                          : paddedId($sid);
            $aSlug      = sanitizeSlug($contextMeta['assignment_title'] ?? 'assignment');
            // First level: assignment name
            $dir        = $uploadRoot . 'submissions/' . $aSlug . '/';
            @mkdir($dir, 0755, true);
            // Filename: 00012_ali_originalname.ext
            $firstName  = sanitizeSlug(explode(' ', trim($contextMeta['student_name'] ?? 'student'))[0]);
            $safeName   = $idStr . '_' . $firstName . '_' . sanitizeSlug($orig) . '.' . $ext;
            $safeName   = _uniqueFilename($dir, $safeName);
            if (!move_uploaded_file($file['tmp_name'], $dir . $safeName)) return null;
            return $aSlug . '/' . $safeName;
        }

        /* ── PROFILES ─────────────────────────────────────────── */
        case 'profiles': {
            $uid       = (int)($contextMeta['user_id'] ?? 0);
            $uname     = sanitizeSlug($contextMeta['user_name'] ?? 'user');
            // Use user_id_number (5-digit DB field) if provided, else pad primary id
            $idStr     = !empty($contextMeta['user_id_number'])
                         ? $contextMeta['user_id_number']
                         : paddedId($uid);
            $folder    = $idStr . '_' . $uname;
            $dir       = $uploadRoot . 'profiles/' . $folder . '/';
            @mkdir($dir, 0755, true);

            // Delete old profile image from its folder if it exists
            $oldPath = $contextMeta['old_path'] ?? null;
            if ($oldPath) {
                $oldFull = $uploadRoot . 'profiles/' . $oldPath;
                if (file_exists($oldFull)) @unlink($oldFull);
            }

            // Always name the file "profile.ext" — one profile pic per user
            $safeName = 'profile.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . $safeName)) return null;
            return $folder . '/' . $safeName;
        }

        /* ── LEGACY FALLBACK (flat storage) ───────────────────── */
        default: {
            $dir = $uploadRoot . trim($context, '/') . '/';
            @mkdir($dir, 0755, true);
            $uuid = bin2hex(random_bytes(8)) . '_' . sanitizeSlug($orig) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . $uuid)) return null;
            return $uuid;
        }
    }
}

/** Ensure filename is unique in the target directory by appending _1, _2 etc. */
function _uniqueFilename(string $dir, string $filename): string {
    if (!file_exists($dir . $filename)) return $filename;
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $i = 1;
    do {
        $candidate = $base . '_' . $i . ($ext ? '.' . $ext : '');
        $i++;
    } while (file_exists($dir . $candidate));
    return $candidate;
}