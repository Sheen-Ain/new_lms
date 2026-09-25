<?php

/**
 * auth/header.php — Shared header for all auth pages
 * Usage: include __DIR__ . '/header.php';
 * Requires $pageTitle to be set before inclusion.
 */

// Load config + helpers if not already loaded
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/helpers.php';
}

// CSRF token for forms/AJAX
$_csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="<?= (($_SESSION['theme'] ?? 'system') === 'dark' || ($_SESSION['theme'] ?? '') === '') ? '' : '' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'EduFlow') ?> — EduFlow LMS</title>

    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/assets/eduflow_icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <!-- App CSS (design tokens, components) -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">

    <!-- Lucide icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <!-- Theme flash prevention -->
    <script>
        (function() {
            try {
                const t = localStorage.getItem('lms_theme') || 'system';
                if (t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme:dark)').matches))
                    document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>

    <!-- Global JS constants — must come before any page scripts -->
    <script>
        window.LMS_BASE = '<?= BASE_PATH ?>';
        window.CSRF_TOKEN = '<?= e($_csrfToken) ?>';
    </script>

    <style>
        /* ── Auth layout ──────────────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        /* Lock the page — NO outer scroll on any auth page */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        .auth-page {
            height: 100svh;
            display: flex;
            overflow: hidden;
            background: var(--bg, #f1f5f9);
        }

        /* ── Left branding panel ──────────────────────────────────── */
        .auth-panel-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 32px 48px;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #1e1b4b 100%);
            position: relative;
            overflow: hidden;
            height: 100svh;
        }

        .auth-panel-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, .35) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, .3) 0%, transparent 55%),
                radial-gradient(ellipse at 60% 10%, rgba(6, 182, 212, .2) 0%, transparent 50%);
            pointer-events: none;
        }

        .auth-aurora {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            width: 100%;
            height: 100%;
        }

        /* All direct children except the canvas rise above it */
        .auth-panel-left>*:not(.auth-aurora) {
            position: relative;
            z-index: 1;
        }

        /* Content wrapper fills the panel as a flex column */
        .auth-panel-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .auth-brand-logo {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }

        .auth-brand-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 1.55rem;
            color: #fff;
            letter-spacing: -0.04em;
        }

        .auth-brand-sub {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, .4);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-top: 1px;
        }

        .auth-hero-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 1.9rem;
            color: #fff;
            line-height: 1.2;
            margin: 0 0 10px;
            letter-spacing: -0.03em;
        }

        .auth-hero-sub {
            font-size: .85rem;
            color: rgba(255, 255, 255, .55);
            line-height: 1.6;
            margin-bottom: 16px;
            max-width: 380px;
        }

        .auth-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
        }

        .auth-feature:last-child {
            border-bottom: none;
        }

        .auth-feature-icon {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, .08);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .auth-feature-text {
            font-size: .88rem;
            color: rgba(255, 255, 255, .7);
            line-height: 1.5;
        }

        .auth-stats {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }

        .auth-stat-num {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: #fff;
        }

        .auth-stat-lbl {
            font-size: .72rem;
            color: rgba(255, 255, 255, .4);
            margin-top: 2px;
        }

        /* ── Right form panel ─────────────────────────────────────── */
        .auth-panel-right {
            width: 460px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 44px;
            background: var(--bg-card, #fff);
            border-left: 1px solid var(--border, #e2e8f0);
            overflow-y: auto;
            height: 100svh;
        }

        .auth-form-wrap {
            width: 100%;
            max-width: 360px;
        }

        /* ── Form card logo ───────────────────────────────────────── */
        .auth-form-logo {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary, #6366f1), var(--secondary, #8b5cf6));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 6px 18px rgba(99, 102, 241, .35);
        }

        /* ── Form components ──────────────────────────────────────── */
        .auth-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--text);
            text-align: center;
            margin: 0 0 6px;
            letter-spacing: -.025em;
        }

        .auth-sub {
            font-size: .82rem;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .auth-label {
            display: block;
            font-weight: 600;
            font-size: .8rem;
            color: var(--text-secondary);
            margin-bottom: 7px;
        }

        .auth-input-wrap {
            position: relative;
        }

        .auth-input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            display: flex;
        }

        .auth-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1.5px solid var(--border, #e2e8f0);
            border-radius: var(--radius, 10px);
            background: var(--bg-input, #f8fafc);
            color: var(--text);
            font-size: .9rem;
            font-family: 'DM Sans', sans-serif;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .auth-input:focus {
            border-color: var(--primary, #6366f1);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
            background: var(--bg-card, #fff);
        }

        .auth-input.has-right {
            padding-right: 46px;
        }

        .auth-input.error {
            border-color: var(--danger, #ef4444);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .1);
        }

        .auth-input.valid {
            border-color: var(--success, #10b981);
        }

        .auth-input-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            display: flex;
            border-radius: 4px;
            transition: color .15s;
        }

        .auth-input-eye:hover {
            color: var(--text);
        }

        .auth-field-err {
            font-size: .75rem;
            color: var(--danger, #ef4444);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
            min-height: 18px;
        }

        .auth-field-err:empty {
            display: none;
        }

        .auth-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: var(--radius);
            font-size: .84rem;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .auth-alert-error {
            background: rgba(239, 68, 68, .08);
            border: 1px solid rgba(239, 68, 68, .2);
            color: #b91c1c;
        }

        .dark .auth-alert-error {
            color: #fca5a5;
        }

        .auth-alert-success {
            background: rgba(16, 185, 129, .08);
            border: 1px solid rgba(16, 185, 129, .2);
            color: #065f46;
        }

        .dark .auth-alert-success {
            color: #6ee7b7;
        }

        .auth-alert-info {
            background: rgba(99, 102, 241, .08);
            border: 1px solid rgba(99, 102, 241, .2);
            color: #4338ca;
        }

        .dark .auth-alert-info {
            color: #a5b4fc;
        }

        .auth-btn {
            width: 100%;
            padding: 11px 20px;
            background: linear-gradient(135deg, var(--primary, #6366f1), #7c3aed);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-family: 'Poppins', sans-serif;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(99, 102, 241, .35);
            transition: opacity .15s, transform .1s, box-shadow .15s;
            letter-spacing: .01em;
        }

        .auth-btn:hover {
            opacity: .92;
            transform: translateY(-1px);
            box-shadow: 0 7px 28px rgba(99, 102, 241, .45);
        }

        .auth-btn:active {
            transform: scale(.97);
        }

        .auth-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: var(--text-muted);
            font-size: .78rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .auth-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: opacity .15s;
        }

        .auth-link:hover {
            opacity: .8;
            text-decoration: underline;
        }

        .auth-footer-text {
            text-align: center;
            font-size: .84rem;
            color: var(--text-muted);
            margin-top: 22px;
        }

        /* OTP boxes */
        .otp-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 22px 0;
        }

        .otp-digit {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 1.55rem;
            font-weight: 900;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: var(--bg-card);
            color: var(--text);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .otp-digit:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
        }

        .otp-digit.filled {
            border-color: var(--primary);
            background: rgba(99, 102, 241, .06);
        }

        .otp-digit.error {
            border-color: var(--danger) !important;
            background: rgba(239, 68, 68, .05) !important;
            animation: shake .4s ease;
        }

        /* Step transitions */
        .auth-step {
            display: none;
            animation: authFadeUp .3s ease;
        }

        .auth-step.active {
            display: block;
        }

        @keyframes authFadeUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        /* Password strength bar */
        .pwd-strength {
            height: 4px;
            border-radius: 99px;
            background: var(--border);
            margin-top: 8px;
            overflow: hidden;
        }

        .pwd-strength-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .3s, background .3s;
        }

        /* Spinner */
        .auth-spinner {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2.5px solid rgba(255, 255, 255, .35);
            border-top-color: #fff;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0)
            }

            20% {
                transform: translateX(-6px)
            }

            40% {
                transform: translateX(6px)
            }

            60% {
                transform: translateX(-4px)
            }

            80% {
                transform: translateX(4px)
            }
        }

        /* Dev OTP box */
        .dev-otp-box {
            background: var(--bg);
            border: 1.5px dashed var(--primary);
            border-radius: var(--radius);
            padding: 12px 16px;
            text-align: center;
            margin-bottom: 16px;
            font-size: .82rem;
        }

        .dev-otp-val {
            font-size: 1.8rem;
            font-weight: 900;
            font-family: 'JetBrains Mono', monospace;
            color: var(--primary);
            letter-spacing: 8px;
            display: block;
            margin-top: 4px;
        }

        /* Credential download card */
        .cred-card {
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 18px;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .cred-row:last-child {
            border-bottom: none;
        }

        .cred-lbl {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
        }

        .cred-val {
            font-weight: 700;
            font-size: .9rem;
            font-family: 'JetBrains Mono', monospace;
            color: var(--primary);
        }

        /* Responsive */
        @media(max-width:900px) {
            .auth-panel-left {
                display: none;
            }

            .auth-panel-right {
                width: 100%;
                border-left: none;
                height: 100svh;
                overflow-y: auto;
            }
        }

        @media(max-width:480px) {
            .auth-panel-right {
                padding: 32px 22px;
            }

            .auth-hero-title {
                font-size: 1.8rem;
            }

            .otp-digit {
                width: 44px;
                height: 54px;
                font-size: 1.3rem;
                gap: 6px;
            }
        }
    </style>
</head>

<body>
    <div id="toast-container"></div>