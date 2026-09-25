<?php

namespace App\Support;

/**
 * Icons — the EduFlow icon set.
 *
 * A single, hand-authored stroke set on a 24×24 grid so the whole product
 * shares one visual language (no emoji, no icon font, no CDN request).
 * Icons are inlined as SVG and inherit currentColor.
 */
class Icons
{
    /** @var array<string,string> */
    private static $paths = [
        /* ── Navigation & modules ─────────────────────────────── */
        'dashboard' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="4.5" rx="1.5"/><rect x="13.5" y="10.5" width="7.5" height="10.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20.5v-1.2a4.3 4.3 0 0 1 4.3-4.3h2.4a4.3 4.3 0 0 1 4.3 4.3v1.2"/><path d="M16.5 5.4a3 3 0 0 1 0 5.6"/><path d="M18 20.5v-1.4a4 4 0 0 0-2-3.4"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H19v3H6.5"/>',
        'layers' => '<path d="M12 3.5 21 8l-9 4.5L3 8z"/><path d="M4.5 12.5 12 16.2l7.5-3.7"/><path d="M4.5 16.8 12 20.5l7.5-3.7"/>',
        'file-text' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/>',
        'clipboard' => '<rect x="5" y="4.5" width="14" height="16" rx="2.2"/><path d="M9 4.5V3.6A1.6 1.6 0 0 1 10.6 2h2.8A1.6 1.6 0 0 1 15 3.6v.9z"/><path d="M9 11.5h6"/><path d="M9 15.5h4"/>',
        'send' => '<path d="M4 11.5 20 4l-7.4 16-2.1-6.4z"/><path d="m10.5 13.6 3.6-3.6"/>',
        'megaphone' => '<path d="M4 10.5v3a1.5 1.5 0 0 0 1.5 1.5h1.3l4.6 3.3a1 1 0 0 0 1.6-.8V6.5a1 1 0 0 0-1.6-.8L6.8 9H5.5A1.5 1.5 0 0 0 4 10.5z"/><path d="M16.5 8.5a5 5 0 0 1 0 7"/><path d="M19 6a8.5 8.5 0 0 1 0 12"/>',
        'video' => '<rect x="2.5" y="6" width="13" height="12" rx="2.4"/><path d="m15.5 12 6-3.6v7.2z"/>',
        'checklist' => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="m3 6 1.7 1.7L7.5 4.6"/><path d="m3 12 1.7 1.7 2.8-3.1"/><path d="m3 18 1.7 1.7 2.8-3.1"/>',
        'inbox' => '<path d="M3.5 13.5 6 5a2 2 0 0 1 1.9-1.4h8.2A2 2 0 0 1 18 5l2.5 8.5"/><path d="M3.5 13.5h4l1 2.5h7l1-2.5h4v3A2.5 2.5 0 0 1 18 19H6a2.5 2.5 0 0 1-2.5-2.5z"/>',
        'message' => '<path d="M20.5 12.5c0 3.9-3.8 7-8.5 7a10 10 0 0 1-2.6-.3L5 21.5l1-3.3A6.6 6.6 0 0 1 3.5 12.5c0-3.9 3.8-7 8.5-7s8.5 3.1 8.5 7z"/><path d="M8.5 12.5h7"/>',
        'activity' => '<path d="M3 12h3.5l2.2-6 3.1 12 2.4-7.5 1.6 3.5H21"/>',
        'settings' => '<circle cx="12" cy="12" r="3.1"/><path d="M12 2.8v2.4M12 18.8v2.4M4.5 4.5l1.7 1.7M17.8 17.8l1.7 1.7M2.8 12h2.4M18.8 12h2.4M4.5 19.5l1.7-1.7M17.8 6.2l1.7-1.7"/>',
        'graduation' => '<path d="M12 4 2.5 8.5 12 13l9.5-4.5z"/><path d="M6.5 10.8v4.3c0 1.4 2.5 2.6 5.5 2.6s5.5-1.2 5.5-2.6v-4.3"/><path d="M21.5 8.5v5"/>',
        'presentation' => '<rect x="3" y="4" width="18" height="11.5" rx="2"/><path d="M12 15.5V20"/><path d="m8.5 20 3.5-2 3.5 2"/><path d="M7.5 8.5h4M7.5 11.5h6.5"/>',
        'shield' => '<path d="M12 3.2 5 5.8v5.4c0 4.2 2.9 7.7 7 9.6 4.1-1.9 7-5.4 7-9.6V5.8z"/><path d="m9.2 11.8 2 2 3.6-3.8"/>',
        'chart' => '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7.5" y="12" width="3" height="5" rx="1"/><rect x="12.5" y="8.5" width="3" height="8.5" rx="1"/><rect x="17.5" y="5.5" width="3" height="11.5" rx="1"/>',
        'folder' => '<path d="M3.5 7.5A2 2 0 0 1 5.5 5.5h3.7l1.8 2.3h7.5a2 2 0 0 1 2 2v7.7a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',

        /* ── Actions ──────────────────────────────────────────── */
        'search' => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.4 15.4 4.1 4.1"/>',
        'filter' => '<path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'edit' => '<path d="M4 20.2h4.2L19 9.4a2.1 2.1 0 0 0 0-3l-1.4-1.4a2.1 2.1 0 0 0-3 0L3.8 16z"/><path d="m14.2 6 3.8 3.8"/>',
        'trash' => '<path d="M4.5 7h15"/><path d="M9.5 4.2h5"/><path d="M6.2 7l.9 12.1A2 2 0 0 0 9.1 21h5.8a2 2 0 0 0 2-1.9L17.8 7"/><path d="M10.5 11v6M13.5 11v6"/>',
        'download' => '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 19.5h15"/>',
        'upload' => '<path d="M12 20.5v-11"/><path d="M7.5 13.5 12 9l4.5 4.5"/><path d="M4.5 4.5h15"/>',
        'eye' => '<path d="M2.8 12S6.6 5.8 12 5.8 21.2 12 21.2 12 17.4 18.2 12 18.2 2.8 12 2.8 12z"/><circle cx="12" cy="12" r="2.9"/>',
        'eye-off' => '<path d="M4 4l16 16"/><path d="M9.6 5.9A9.5 9.5 0 0 1 12 5.6c5.4 0 9.2 6.2 9.2 6.2a17 17 0 0 1-3 3.7"/><path d="M6.7 7.4A16.7 16.7 0 0 0 2.8 11.8s3.8 6.2 9.2 6.2a9.1 9.1 0 0 0 3.4-.6"/>',
        'lock' => '<rect x="4.8" y="10.5" width="14.4" height="10" rx="2.2"/><path d="M8.3 10.5V7.8a3.7 3.7 0 0 1 7.4 0v2.7"/><path d="M12 14.5v3"/>',
        'unlock' => '<rect x="4.8" y="10.5" width="14.4" height="10" rx="2.2"/><path d="M8.3 10.5V7.8a3.7 3.7 0 0 1 7.1-1.4"/>',
        'key' => '<circle cx="8" cy="15" r="3.6"/><path d="m10.7 12.3 7.3-7.3"/><path d="m15.5 7.5 2 2"/><path d="m18 5 2 2"/>',
        'logout' => '<path d="M9.5 21H5.8A1.8 1.8 0 0 1 4 19.2V4.8A1.8 1.8 0 0 1 5.8 3h3.7"/><path d="M15.5 16.5 20 12l-4.5-4.5"/><path d="M20 12H9.5"/>',
        'user' => '<circle cx="12" cy="8.2" r="3.6"/><path d="M4.8 20.5v-1a4.9 4.9 0 0 1 4.9-4.9h4.6a4.9 4.9 0 0 1 4.9 4.9v1"/>',
        'user-plus' => '<circle cx="10" cy="8.2" r="3.4"/><path d="M3.5 20.5v-1a4.7 4.7 0 0 1 4.7-4.7h3.6a4.7 4.7 0 0 1 3.2 1.2"/><path d="M17.5 13.5v6"/><path d="M14.5 16.5h6"/>',
        'user-check' => '<circle cx="10" cy="8.2" r="3.4"/><path d="M3.5 20.5v-1a4.7 4.7 0 0 1 4.7-4.7h3.6a4.7 4.7 0 0 1 3.2 1.2"/><path d="m15 16.8 2 2 3.5-3.6"/>',
        'bell' => '<path d="M6.3 10.4a5.7 5.7 0 0 1 11.4 0v3.4l1.4 2.6H4.9l1.4-2.6z"/><path d="M10 19.2a2.2 2.2 0 0 0 4 0"/>',
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close' => '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.8"/><path d="m8.2 12.2 2.6 2.6 5-5.2"/>',
        'x-circle' => '<circle cx="12" cy="12" r="8.8"/><path d="m9.2 9.2 5.6 5.6"/><path d="m14.8 9.2-5.6 5.6"/>',
        'alert' => '<path d="M10.3 4.4 2.9 17.1A2 2 0 0 0 4.6 20.2h14.8a2 2 0 0 0 1.7-3.1L13.7 4.4a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4.2"/><path d="M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="8.8"/><path d="M12 11v5.5"/><path d="M12 7.8h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.4V12l3.4 2.2"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.2"/><path d="M3.5 10h17"/><path d="M8 3.2v3.4M16 3.2v3.4"/>',
        'pin' => '<path d="M14.4 3.5 20.5 9.6l-2.4 1-2 4.6-2.4-2.4-5.3 5.3-1.5-1.5 5.3-5.3-2.4-2.4 4.6-2z"/>',
        'star' => '<path d="m12 4 2.5 5.1 5.6.8-4.1 3.9 1 5.5-5-2.7-5 2.7 1-5.5L4 9.9l5.6-.8z"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4v4.5h-4.5"/>',
        'more-vertical' => '<circle cx="12" cy="5.5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="18.5" r="1.4"/>',
        'more-horizontal' => '<circle cx="5.5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18.5" cy="12" r="1.4"/>',
        'external' => '<path d="M14 4.5h5.5V10"/><path d="m19.2 4.8-7.4 7.4"/><path d="M18.5 14v4.5a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 18.5V7.8A1.8 1.8 0 0 1 5.8 6H10"/>',
        'play' => '<path d="M8 5.6 18.5 12 8 18.4z"/>',
        'stop' => '<rect x="6.5" y="6.5" width="11" height="11" rx="2"/>',
        'copy' => '<rect x="8.5" y="8.5" width="12" height="12" rx="2.2"/><path d="M15.5 8.5V6.2A2.2 2.2 0 0 0 13.3 4H6.2A2.2 2.2 0 0 0 4 6.2v7.1a2.2 2.2 0 0 0 2.2 2.2h2.3"/>',
        'save' => '<path d="M5.5 4h9.6L20 8.9v9.6A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-13A1.5 1.5 0 0 1 5.5 4z"/><path d="M8 4v5.5h6.5V4"/><rect x="8" y="13" width="8" height="7"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m10.5 6.5-5.5 5.5 5.5 5.5"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m13.5 6.5 5.5 5.5-5.5 5.5"/>',
        'chevron-down' => '<path d="m6.5 9.5 5.5 5.5 5.5-5.5"/>',
        'chevron-up' => '<path d="m6.5 14.5 5.5-5.5 5.5 5.5"/>',
        'chevron-left' => '<path d="m14.5 6.5-5.5 5.5 5.5 5.5"/>',
        'chevron-right' => '<path d="m9.5 6.5 5.5 5.5-5.5 5.5"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
        'list' => '<path d="M8.5 6.5h11.5"/><path d="M8.5 12h11.5"/><path d="M8.5 17.5h11.5"/><path d="M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01"/>',
        'table' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17"/><path d="M9.5 9.5V19.5"/>',
        'target' => '<circle cx="12" cy="12" r="8.6"/><circle cx="12" cy="12" r="4.6"/><circle cx="12" cy="12" r="1"/>',
        'award' => '<circle cx="12" cy="9.5" r="5.5"/><path d="m8.8 14.2-1.3 6.3 4.5-2.3 4.5 2.3-1.3-6.3"/>',
        'flag' => '<path d="M6 21V4"/><path d="M6 4.5h11.5l-1.8 4 1.8 4H6z"/>',
        'link' => '<path d="M10 13.5a3.6 3.6 0 0 0 5.1 0l2.8-2.8a3.6 3.6 0 0 0-5.1-5.1L11.4 7"/><path d="M14 10.5a3.6 3.6 0 0 0-5.1 0L6.1 13.3a3.6 3.6 0 0 0 5.1 5.1L12.6 17"/>',
        'server' => '<rect x="3.5" y="4" width="17" height="6.5" rx="2"/><rect x="3.5" y="13.5" width="17" height="6.5" rx="2"/><path d="M7.5 7.2h.01M7.5 16.8h.01"/>',
        'database' => '<ellipse cx="12" cy="6" rx="7.5" ry="3"/><path d="M4.5 6v12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6"/><path d="M4.5 12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m4 7.5 8 5.6 8-5.6"/>',
        'phone' => '<path d="M6.5 3.5h3l1.5 4-2 1.4a11 11 0 0 0 5.1 5.1l1.4-2 4 1.5v3A2 2 0 0 1 17.4 19C10.2 18.4 5.6 13.8 5 6.6a2 2 0 0 1 1.5-3.1z"/>',
        'id-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2.4"/><circle cx="8.5" cy="11" r="2.4"/><path d="M4.8 16.5c.6-1.6 2-2.4 3.7-2.4s3.1.8 3.7 2.4"/><path d="M15 10h4M15 13.5h4"/>',
        'code' => '<path d="m8.5 8-4.5 4 4.5 4"/><path d="m15.5 8 4.5 4-4.5 4"/><path d="m13.5 5.5-3 13"/>',
        'archive' => '<rect x="3.5" y="4" width="17" height="4.5" rx="1.4"/><path d="M5 8.5v9.4A2 2 0 0 0 7 20h10a2 2 0 0 0 2-2.1V8.5"/><path d="M10 12.5h4"/>',
        'image' => '<rect x="3" y="4.5" width="18" height="15" rx="2.4"/><circle cx="9" cy="10" r="2"/><path d="m4 17.5 4.5-4 4 3.5 3-2.5L20 18"/>',
        'map-pin' => '<path d="M12 21s6.5-6 6.5-11a6.5 6.5 0 1 0-13 0C5.5 15 12 21 12 21z"/><circle cx="12" cy="10" r="2.5"/>',
        'volume' => '<path d="M4 10v4h3l4.5 3.5v-15L7 10z"/><path d="M15.5 9.5a3.5 3.5 0 0 1 0 5"/><path d="M18 7a7 7 0 0 1 0 10"/>',
        'sun' => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>',
        'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
        'palette' => '<path d="M12 3.5a8.5 8.5 0 0 0 0 17c1.4 0 2-.9 2-1.9s-.7-1.9-.7-2.7c0-.9.7-1.6 1.6-1.6h1.4a4.2 4.2 0 0 0 4.2-4.2c0-3.6-3.8-6.6-8.5-6.6z"/><circle cx="8.4" cy="10.4" r="1.1"/><circle cx="12" cy="7.8" r="1.1"/><circle cx="15.7" cy="10.2" r="1.1"/>',
    ];

    /** Icons drawn as filled shapes rather than strokes. */
    private static $solid = [
        'dot' => '<circle cx="12" cy="12" r="3.6"/>',
    ];

    /** Natural-language aliases used by views and navigation. */
    private static $aliases = [
        'home' => 'dashboard', 'tests' => 'checklist', 'test' => 'checklist',
        'assignments' => 'clipboard', 'assignment' => 'clipboard',
        'topics' => 'file-text', 'topic' => 'file-text',
        'courses' => 'book', 'course' => 'book',
        'batches' => 'layers', 'batch' => 'layers',
        'submissions' => 'send', 'submission' => 'send',
        'announcements' => 'megaphone', 'announcement' => 'megaphone',
        'live' => 'video', 'applications' => 'inbox', 'feedback' => 'message',
        'messages' => 'message', 'chat' => 'message', 'profile' => 'user',
        'change-password' => 'lock', 'theme' => 'palette', 'logs' => 'activity',
        'activity-logs' => 'activity', 'switch-role' => 'refresh',
        'warning' => 'alert', 'success' => 'check-circle', 'error' => 'x-circle',
        'danger' => 'alert', 'notifications' => 'bell', 'help' => 'info',
        'add' => 'plus', 'remove' => 'minus', 'delete' => 'trash',
        'quality' => 'award', 'onboarding' => 'target', 'room' => 'map-pin',
        'reports' => 'chart',
    ];

    /** Map a file extension to a themed file mark. */
    public static function fileIcon($filename)
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return 'file-pdf';
        }
        if (in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt', 'md'], true)) {
            return 'file-text';
        }
        if (in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true)) {
            return 'file-sheet';
        }
        if (in_array($ext, ['ppt', 'pptx', 'odp'], true)) {
            return 'file-slides';
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true)) {
            return 'file-image';
        }
        if (in_array($ext, ['mp3', 'wav', 'ogg', 'flac'], true)) {
            return 'file-audio';
        }
        if (in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'webm'], true)) {
            return 'file-video';
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true)) {
            return 'file-archive';
        }
        if (in_array($ext, ['php', 'py', 'js', 'ts', 'jsx', 'tsx', 'css', 'html', 'htm',
            'sql', 'json', 'xml', 'yml', 'yaml', 'c', 'cpp', 'h', 'hpp', 'cs', 'java',
            'rb', 'go', 'rs', 'kt', 'swift', 'sh', 'bash', 'bat', 'ps1', 'env', 'blade',
            'r', 'lua', 'pl', 'scala', 'vue'], true)) {
            return 'file-code';
        }
        return 'file-text';
    }

    /** Icon inside a tinted square — used in every file list. */
    public static function fileBadge($filename, $size = 34)
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $name = self::fileIcon($filename);
        $tone = 'muted';

        if ($name === 'file-pdf') {
            $tone = 'danger';
        } elseif ($name === 'file-image') {
            $tone = 'info';
        } elseif ($name === 'file-sheet') {
            $tone = 'success';
        } elseif ($name === 'file-slides') {
            $tone = 'warning';
        } elseif ($name === 'file-code') {
            $tone = 'primary';
        } elseif ($name === 'file-archive') {
            $tone = 'warning';
        } elseif ($name === 'file-video' || $name === 'file-audio') {
            $tone = 'info';
        }

        return '<span class="file-badge file-badge-' . $tone . '" title="' . e($ext !== '' ? strtoupper($ext) : 'FILE') . '">'
            . self::render($name, (int) round($size * 0.58))
            . '</span>';
    }

    public static function extensionLabel($filename)
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        return $ext !== '' ? strtoupper($ext) : 'FILE';
    }

    /* ── Rendering ─────────────────────────────────────────────── */

    public static function path($name)
    {
        $name = strtolower(trim((string) $name));
        if (isset(self::$aliases[$name])) {
            $name = self::$aliases[$name];
        }
        if (isset(self::$paths[$name])) {
            return self::$paths[$name];
        }
        if (isset(self::$solid[$name])) {
            return self::$solid[$name];
        }
        return self::$paths['file-text'];
    }

    public static function exists($name)
    {
        $name = strtolower(trim((string) $name));
        if (isset(self::$aliases[$name])) {
            $name = self::$aliases[$name];
        }
        return isset(self::$paths[$name]) || isset(self::$solid[$name]);
    }

    /** Inline SVG for an icon. */
    public static function render($name, $size = 18, $class = '')
    {
        $raw = strtolower(trim((string) $name));
        $isSolid = isset(self::$solid[$raw]);
        $size = max(8, (int) $size);

        $attributes = $isSolid
            ? 'fill="currentColor" stroke="none"'
            : 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"';

        return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '"'
            . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
            . ' aria-hidden="true" focusable="false" ' . $attributes . '>'
            . self::path($raw) . '</svg>';
    }

    /** Every icon name (used by the design-system reference page). */
    public static function names()
    {
        $names = array_merge(array_keys(self::$paths), array_keys(self::$solid));
        sort($names);
        return $names;
    }
}





