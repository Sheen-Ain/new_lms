<?php
if (empty($_SESSION['user_id'])) return;
$_chatMe   = (int)$_SESSION['user_id'];
$_chatRole = $_SESSION['role'] ?? '';
?>

<!-- Pusher + Lottie -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
<script>
  window.CHAT_ME_ID = <?= $_chatMe ?>;
  window.PUSHER_KEY = '<?= PUSHER_KEY ?>';
  window.PUSHER_CLUSTER = '<?= PUSHER_CLUSTER ?>';
</script>

<!-- ╔═══════════════════════════════════════════╗ -->
<!-- ║  EDUFLOW CHAT WIDGET — UPGRADED           ║ -->
<!-- ╚═══════════════════════════════════════════╝ -->

<!-- FAB -->
<button id="chat-fab" aria-label="Open chat" title="Messages">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
  </svg>
  <span id="chat-fab-badge" style="display:none;"></span>
</button>

<!-- Widget -->
<div id="chat-widget" role="dialog" aria-label="Chat" style="display:none;">

  <!-- VIEW 1: Conversation List -->
  <div id="chat-view-convs" class="chat-view">
    <div class="chat-header">
      <div class="chat-header-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.85;">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <span>Messages</span>
        <span id="chat-total-unread-badge" style="display:none;"></span>
      </div>
      <div style="display:flex;gap:4px;align-items:center;">
        <button class="chat-header-btn" id="chat-blocked-btn" title="Blocked users" data-tooltip="Blocked users">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
          </svg>
        </button>
        <button class="chat-header-btn" id="chat-close-btn" title="Close">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>
    <div id="chat-conv-list" class="chat-scrollable"></div>
  </div>

  <!-- VIEW 2: Blocked Users -->
  <div id="chat-view-blocked" class="chat-view" style="display:none;">
    <div class="chat-header">
      <button class="chat-header-btn" id="chat-blocked-back-btn" title="Back">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <polyline points="15 18 9 12 15 6" />
        </svg>
      </button>
      <div class="chat-header-title"><span>Blocked Users</span></div>
      <button class="chat-header-btn" id="chat-close-btn3" title="Close">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
    <div id="chat-blocked-list" class="chat-scrollable"></div>
  </div>

  <!-- VIEW 3: Open Conversation -->
  <div id="chat-view-chat" class="chat-view" style="display:none;">
    <div class="chat-header">
      <button class="chat-header-btn" id="chat-back-btn" title="Back">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <polyline points="15 18 9 12 15 6" />
        </svg>
      </button>
      <div id="chat-peer-info" class="chat-peer-info" style="cursor:default;">
        <div id="chat-peer-avatar" style="position:relative;"></div>
        <div>
          <div id="chat-peer-name"></div>
          <div id="chat-peer-status"></div>
        </div>
      </div>
      <!-- Peer action menu -->
      <div style="position:relative;">
        <button class="chat-header-btn" id="chat-peer-menu-btn" title="Options">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <circle cx="12" cy="5" r="1" />
            <circle cx="12" cy="12" r="1" />
            <circle cx="12" cy="19" r="1" />
          </svg>
        </button>
        <div id="chat-peer-menu" class="chat-dropdown" style="display:none;">
          <button id="chat-peer-block-btn" class="chat-dropdown-item">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="10" />
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
            </svg>
            <span id="chat-peer-block-label">Block User</span>
          </button>
          <button id="chat-peer-delete-btn" class="chat-dropdown-item danger">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6l-1 14H6L5 6" />
              <path d="M9 6V4h6v2" />
            </svg>
            Delete Chat
          </button>
        </div>
      </div>
      <button class="chat-header-btn" id="chat-close-btn2" title="Close">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>

    <!-- Blocked banner -->
    <div id="chat-blocked-banner" style="display:none;" class="chat-blocked-banner"></div>

    <div id="chat-messages-area" class="chat-scrollable" tabindex="0"></div>

    <div id="chat-typing-bar" class="chat-typing-bar" style="display:none;">
      <span class="typing-dots"><span></span><span></span><span></span></span>
      <span id="chat-typing-name"></span> is typing…
    </div>

    <div class="chat-input-area">
      <button id="chat-emoji-toggle" class="chat-icon-btn" title="Emoji">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="10" />
          <path d="M8 14s1.5 2 4 2 4-2 4-2" />
          <line x1="9" y1="9" x2="9.01" y2="9" />
          <line x1="15" y1="9" x2="15.01" y2="9" />
        </svg>
      </button>
      <textarea id="chat-input" class="chat-input" placeholder="Type a message…" maxlength="4000" rows="1"></textarea>
      <button id="chat-send-btn" class="chat-send-btn" title="Send">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13" />
          <polygon points="22 2 15 22 11 13 2 9 22 2" />
        </svg>
      </button>
    </div>
    <div id="chat-emoji-picker" style="display:none;"></div>
  </div>
</div>

<!-- Reaction picker (Lottie-powered) -->
<div id="chat-reaction-picker" style="display:none;" role="toolbar" aria-label="React to message">
  <!-- Buttons injected by JS -->
</div>

<!-- Message context menu -->
<div id="chat-msg-menu" style="display:none;" role="menu">
  <button id="chat-menu-react" class="chat-menu-item" role="menuitem">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <circle cx="12" cy="12" r="10" />
      <path d="M8 14s1.5 2 4 2 4-2 4-2" />
      <line x1="9" y1="9" x2="9.01" y2="9" />
      <line x1="15" y1="9" x2="15.01" y2="9" />
    </svg>
    Add Reaction
  </button>
  <button id="chat-menu-del-me" class="chat-menu-item" role="menuitem">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6l-1 14H6L5 6" />
      <path d="M9 6V4h6v2" />
    </svg>
    Delete for me
  </button>
  <button id="chat-menu-del-all" class="chat-menu-item danger" role="menuitem" style="display:none;">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6l-1 14H6L5 6" />
      <path d="M9 6V4h6v2" />
    </svg>
    Delete for everyone
  </button>
</div>

<!-- ── STYLES ─────────────────────────────────────────────── -->
<style>
  /* ── FAB ─────────────────────────────────────────────── */
  #chat-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.5), 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s;
  }

  #chat-fab:hover {
    transform: scale(1.12);
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.65);
  }

  #chat-fab:active {
    transform: scale(0.94);
  }

  #chat-fab-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 800;
    min-width: 19px;
    height: 19px;
    border-radius: 999px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2.5px solid var(--bg-sidebar, #1e1b4b);
    pointer-events: none;
    animation: badgePop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  @keyframes badgePop {
    from {
      transform: scale(0);
    }

    to {
      transform: scale(1);
    }
  }

  /* ── Widget Container ─────────────────────────────────── */
  #chat-widget {
    position: fixed;
    right: 24px;
    /* Sit just above the FAB (56px) + its gap (16px) = 72px, with 16px breathing room */
    bottom: 92px;
    width: 370px;
    /* Fluid height: ideal 570px, but never taller than the space above the FAB */
    height: min(570px, calc(100svh - 116px));
    /* Hard floor so the widget is still usable on very short screens */
    min-height: 280px;
    background: var(--bg-card, #ffffff);
    border-radius: 22px;
    box-shadow:
      0 24px 64px rgba(0, 0, 0, 0.2),
      0 4px 16px rgba(99, 102, 241, 0.12),
      0 0 0 1px rgba(99, 102, 241, 0.1);
    z-index: 1040;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.18);
    animation: chatPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  @keyframes chatPop {
    from {
      opacity: 0;
      transform: scale(0.85) translateY(16px);
    }

    to {
      opacity: 1;
      transform: scale(1) translateY(0);
    }
  }

  /* ── Views ────────────────────────────────────────────── */
  .chat-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
  }

  /* ── Header ───────────────────────────────────────────── */
  .chat-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 13px 14px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #7c3aed 100%);
    flex-shrink: 0;
    position: relative;
    /* overflow must stay visible so the peer options dropdown can escape the header */
    overflow: visible;
  }

  /* Subtle shine line across header */
  .chat-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 60%;
    height: 100%;
    background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, 0.08) 50%, transparent 60%);
    pointer-events: none;
  }

  .chat-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.15);
  }

  .chat-header-title {
    flex: 1;
    font-weight: 700;
    font-size: 0.9rem;
    font-family: 'Poppins', sans-serif;
    display: flex;
    align-items: center;
    gap: 7px;
    color: #fff;
  }

  .chat-header-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    transition: background 0.15s, transform 0.1s;
    flex-shrink: 0;
  }

  .chat-header-btn:hover {
    background: rgba(255, 255, 255, 0.28);
    transform: scale(1.1);
  }

  .chat-header-btn:active {
    transform: scale(0.92);
  }

  #chat-total-unread-badge {
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    font-size: 0.63rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
  }

  /* ── Peer info ────────────────────────────────────────── */
  .chat-peer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
  }

  #chat-peer-name {
    font-weight: 700;
    font-size: 0.875rem;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  #chat-peer-status {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 1px;
  }

  /* ── Scrollable ───────────────────────────────────────── */
  .chat-scrollable {
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: rgba(99, 102, 241, 0.2) transparent;
  }

  .chat-scrollable::-webkit-scrollbar {
    width: 3px;
  }

  .chat-scrollable::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.25);
    border-radius: 99px;
  }

  /* ── Conversation list ────────────────────────────────── */
  .chat-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    cursor: pointer;
    transition: background 0.12s;
    position: relative;
    border-bottom: 1px solid var(--border, rgba(0, 0, 0, 0.06));
    border-left: 3px solid transparent;
    transition: background 0.12s, border-left-color 0.15s;
  }

  .chat-conv-item:hover {
    background: var(--bg-hover, rgba(99, 102, 241, 0.05));
    border-left-color: rgba(99, 102, 241, 0.4);
  }

  .chat-conv-item:last-child {
    border-bottom: none;
  }

  .chat-conv-item.is-blocked {
    opacity: 0.55;
  }

  .chat-conv-avatar {
    position: relative;
    flex-shrink: 0;
  }

  .chat-conv-online-dot {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: 2.5px solid var(--bg-card, #fff);
  }

  .chat-conv-online-dot.on {
    background: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  }

  .chat-conv-online-dot.off {
    background: #94a3b8;
  }

  .chat-conv-body {
    flex: 1;
    min-width: 0;
  }

  .chat-conv-name {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .chat-conv-preview {
    font-size: 0.76rem;
    color: var(--text-muted, #94a3b8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
  }

  .chat-conv-preview.unread {
    color: var(--text, #0f172a);
    font-weight: 600;
  }

  .chat-conv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
    flex-shrink: 0;
  }

  .chat-conv-time {
    font-size: 0.67rem;
    color: var(--text-muted, #94a3b8);
  }

  .chat-conv-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-size: 0.61rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
  }

  /* ── Blocked users list ───────────────────────────────── */
  .chat-blocked-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border, rgba(0, 0, 0, 0.06));
  }

  .chat-blocked-item:last-child {
    border-bottom: none;
  }

  .chat-unblock-btn {
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
    font-family: 'DM Sans', sans-serif;
  }

  .chat-unblock-btn:hover {
    background: rgba(239, 68, 68, 0.15);
  }

  /* ── Blocked banner in chat ───────────────────────────── */
  .chat-blocked-banner {
    padding: 10px 16px;
    font-size: 0.8rem;
    text-align: center;
    background: rgba(239, 68, 68, 0.07);
    color: #ef4444;
    border-bottom: 1px solid rgba(239, 68, 68, 0.15);
    font-weight: 500;
    flex-shrink: 0;
  }

  /* ── Messages area ────────────────────────────────────── */
  #chat-messages-area {
    padding: 14px 12px 6px;
    display: flex;
    flex-direction: column;
    gap: 3px;
  }

  .chat-date-divider {
    text-align: center;
    font-size: 0.66rem;
    color: var(--text-muted, #94a3b8);
    margin: 10px 0 6px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .chat-date-divider::before,
  .chat-date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border, rgba(0, 0, 0, 0.07));
  }

  /* ── Message bubble ───────────────────────────────────── */
  .chat-msg-wrap {
    display: flex;
    align-items: flex-end;
    gap: 7px;
    animation: msgIn 0.2s ease;
  }

  @keyframes msgIn {
    from {
      opacity: 0;
      transform: translateY(6px);
    }

    to {
      opacity: 1;
      transform: none;
    }
  }

  .chat-msg-wrap.mine {
    flex-direction: row-reverse;
  }

  .chat-msg-wrap.theirs {
    flex-direction: row;
  }

  .chat-msg-group {
    display: flex;
    flex-direction: column;
    max-width: 73%;
    min-width: 0;
  }

  .chat-msg-wrap.mine .chat-msg-group {
    align-items: flex-end;
  }

  .chat-msg-wrap.theirs .chat-msg-group {
    align-items: flex-start;
  }

  .chat-bubble {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 18px;
    font-size: 0.86rem;
    line-height: 1.5;
    font-family: 'DM Sans', sans-serif;
    word-break: break-word;
    overflow-wrap: break-word;
    cursor: context-menu;
    transition: filter 0.1s;
    position: relative;
  }

  .chat-bubble:hover {
    filter: brightness(0.97);
  }

  .chat-msg-wrap.mine .chat-bubble {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 3px 12px rgba(99, 102, 241, 0.35);
  }

  .chat-msg-wrap.theirs .chat-bubble {
    background: var(--bg, #f1f5f9);
    color: var(--text, #0f172a);
    border-bottom-left-radius: 4px;
    border: 1px solid var(--border, rgba(0, 0, 0, 0.08));
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  }

  .dark .chat-msg-wrap.theirs .chat-bubble {
    background: #1e293b;
    border-color: #334155;
    color: #e2e8f0;
  }

  .chat-bubble.deleted-msg {
    background: var(--bg, #f8fafc) !important;
    color: var(--text-muted, #94a3b8) !important;
    border: 1px dashed var(--border, #e2e8f0) !important;
    box-shadow: none !important;
    font-style: italic;
    opacity: 0.7;
    font-size: 0.8rem;
    border-radius: 12px !important;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .chat-msg-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.61rem;
    color: var(--text-muted, #94a3b8);
    padding: 3px 2px 0;
    line-height: 1;
  }

  .chat-msg-wrap.mine .chat-msg-meta {
    justify-content: flex-end;
  }

  .chat-msg-wrap.theirs .chat-msg-meta {
    justify-content: flex-start;
  }

  .chat-tick {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  .chat-tick svg {
    width: 15px;
    height: 15px;
  }

  /* Sent: hollow circle with a single check */
  .tick-sent {
    color: rgba(255, 255, 255, 0.5);
  }

  /* Delivered: filled circle with a check */
  .tick-delivered {
    color: rgba(255, 255, 255, 0.8);
  }

  /* Seen: peer's profile picture as tiny avatar circle */
  .tick-seen-wrap {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  .tick-seen-avatar {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid rgba(255, 255, 255, 0.5);
    display: block;
    flex-shrink: 0;
  }

  .tick-seen-avatar-fb {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.42rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    border: 1.5px solid rgba(255, 255, 255, 0.4);
    font-family: 'Poppins', sans-serif;
  }

  /* ── Reaction pills ───────────────────────────────────── */
  .chat-reactions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 5px;
    padding: 0 2px;
  }

  .chat-msg-wrap.mine .chat-reactions {
    justify-content: flex-end;
  }

  .chat-reaction-pill-wrap {
    position: relative;
    display: inline-flex;
  }

  .chat-reaction-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-card, #fff);
    border: 1.5px solid var(--border, rgba(0, 0, 0, 0.1));
    border-radius: 999px;
    padding: 2px 9px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  }

  .chat-reaction-pill:hover {
    border-color: #6366f1;
    transform: scale(1.08);
    box-shadow: 0 3px 10px rgba(99, 102, 241, 0.2);
  }

  .chat-reaction-pill.reacted {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
  }

  .chat-reaction-pill .pill-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary, #475569);
  }

  .chat-reaction-pill.reacted .pill-count {
    color: #6366f1;
  }

  /* Reaction tooltip — who reacted */
  .reaction-who-tooltip {
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(8px);
    color: #fff;
    font-size: 0.7rem;
    white-space: nowrap;
    padding: 5px 10px;
    border-radius: 8px;
    pointer-events: none;
    z-index: 1070;
    opacity: 0;
    transition: opacity 0.15s;
    font-family: 'DM Sans', sans-serif;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .reaction-who-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: rgba(15, 23, 42, 0.9);
  }

  .chat-reaction-pill-wrap:hover .reaction-who-tooltip {
    opacity: 1;
  }

  /* ── Reaction Picker — Lottie ─────────────────────────── */
  #chat-reaction-picker {
    position: fixed;
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, rgba(0, 0, 0, 0.08));
    border-radius: 999px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18), 0 2px 8px rgba(0, 0, 0, 0.08);
    padding: 8px 12px;
    display: flex;
    gap: 6px;
    z-index: 1060;
    animation: pickerPop 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  @keyframes pickerPop {
    from {
      opacity: 0;
      transform: scale(0.7);
    }

    to {
      opacity: 1;
      transform: scale(1);
    }
  }

  .reaction-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    width: 42px;
    padding: 5px 0;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 12px;
    transition: background 0.12s, transform 0.15s;
    position: relative;
  }

  .reaction-btn:hover {
    background: var(--bg-hover, rgba(99, 102, 241, 0.08));
    transform: scale(1.15) translateY(-3px);
  }

  .reaction-lottie-wrap {
    width: 32px;
    height: 32px;
    position: relative;
    pointer-events: none;
  }

  /* CSS fallback emoji (shown when lottie not loaded/loading) */
  .reaction-emoji-fallback {
    font-size: 26px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .reaction-btn:hover .reaction-emoji-fallback {
    transform: scale(1.25);
  }

  /* Per-emoji CSS animations on hover */
  .reaction-btn[data-emoji="👍"]:hover .reaction-emoji-fallback {
    animation: likeAnim 0.55s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .reaction-btn[data-emoji="❤️"]:hover .reaction-emoji-fallback {
    animation: heartAnim 0.7s ease;
  }

  .reaction-btn[data-emoji="😂"]:hover .reaction-emoji-fallback {
    animation: laughAnim 0.6s ease;
  }

  .reaction-btn[data-emoji="😮"]:hover .reaction-emoji-fallback {
    animation: wowAnim 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  .reaction-btn[data-emoji="😢"]:hover .reaction-emoji-fallback {
    animation: sadAnim 0.65s ease;
  }

  .reaction-btn[data-emoji="🔥"]:hover .reaction-emoji-fallback {
    animation: fireAnim 0.7s ease infinite;
  }

  @keyframes likeAnim {
    0% {
      transform: scale(1) rotate(0);
    }

    30% {
      transform: scale(1.55) rotate(-18deg) translateY(-3px);
    }

    60% {
      transform: scale(1.35) rotate(8deg);
    }

    80% {
      transform: scale(1.2) rotate(-3deg);
    }

    100% {
      transform: scale(1.25) rotate(0);
    }
  }

  @keyframes heartAnim {

    0%,
    100% {
      transform: scale(1.25);
    }

    14% {
      transform: scale(1.5);
    }

    28% {
      transform: scale(1.2);
    }

    42% {
      transform: scale(1.55);
    }

    70% {
      transform: scale(1.3);
    }
  }

  @keyframes laughAnim {

    0%,
    100% {
      transform: scale(1.25) rotate(0);
    }

    20% {
      transform: scale(1.3) rotate(-9deg);
    }

    40% {
      transform: scale(1.35) rotate(9deg);
    }

    60% {
      transform: scale(1.3) rotate(-5deg);
    }

    80% {
      transform: scale(1.25) rotate(4deg);
    }
  }

  @keyframes wowAnim {
    0% {
      transform: scale(1);
    }

    45% {
      transform: scale(1.65) rotate(-5deg);
    }

    70% {
      transform: scale(1.35) rotate(3deg);
    }

    100% {
      transform: scale(1.25);
    }
  }

  @keyframes sadAnim {

    0%,
    100% {
      transform: scale(1.25) translateY(0);
    }

    30% {
      transform: scale(1.2) translateY(3px) rotate(-5deg);
    }

    70% {
      transform: scale(1.2) translateY(3px) rotate(4deg);
    }
  }

  @keyframes fireAnim {

    0%,
    100% {
      transform: scale(1.25) scaleX(1);
      filter: hue-rotate(0deg);
    }

    25% {
      transform: scale(1.35) scaleX(0.88);
      filter: hue-rotate(22deg);
    }

    50% {
      transform: scale(1.4) scaleX(1.1);
      filter: hue-rotate(-12deg);
    }

    75% {
      transform: scale(1.3) scaleX(0.93);
      filter: hue-rotate(16deg);
    }
  }

  .reaction-label {
    font-size: 0.58rem;
    font-weight: 600;
    color: var(--text-muted, #94a3b8);
    font-family: 'DM Sans', sans-serif;
    letter-spacing: 0.02em;
    transition: color 0.12s;
  }

  .reaction-btn:hover .reaction-label {
    color: #6366f1;
  }

  /* ── Typing bar ───────────────────────────────────────── */
  .chat-typing-bar {
    padding: 7px 16px;
    font-size: 0.77rem;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid var(--border, rgba(0, 0, 0, 0.07));
    background: var(--bg-card, #fff);
    flex-shrink: 0;
  }

  .typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 3px;
  }

  .typing-dots span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #6366f1;
    animation: typingBounce 1.2s ease infinite;
  }

  .typing-dots span:nth-child(2) {
    animation-delay: .2s;
  }

  .typing-dots span:nth-child(3) {
    animation-delay: .4s;
  }

  @keyframes typingBounce {

    0%,
    80%,
    100% {
      transform: translateY(0);
      opacity: .4;
    }

    40% {
      transform: translateY(-4px);
      opacity: 1;
    }
  }

  /* ── Input area ───────────────────────────────────────── */
  .chat-input-area {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid var(--border, rgba(0, 0, 0, 0.07));
    background: var(--bg-card, #fff);
    flex-shrink: 0;
    position: relative;
  }

  .chat-input-area::before {
    content: '';
    position: absolute;
    top: 0;
    left: 16px;
    right: 16px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.18), transparent);
  }

  .chat-icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.12s, color 0.12s;
    flex-shrink: 0;
  }

  .chat-icon-btn:hover {
    background: var(--bg-hover, rgba(99, 102, 241, 0.08));
    color: #6366f1;
  }

  .chat-input {
    flex: 1;
    border: 1.5px solid var(--border, rgba(0, 0, 0, 0.1));
    border-radius: 20px;
    padding: 9px 14px;
    font-size: 0.875rem;
    font-family: 'DM Sans', sans-serif;
    background: var(--bg, #f8fafc);
    color: var(--text, #0f172a);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    resize: none;
    overflow-y: auto;
    min-height: 38px;
    max-height: 120px;
    line-height: 1.5;
    /* Hide scrollbar — it auto-expands so scrollbar is never needed visually */
    scrollbar-width: none;
  }

  .chat-input::-webkit-scrollbar {
    display: none;
  }

  .chat-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    background: var(--bg-card, #fff);
  }

  .chat-send-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s, box-shadow 0.15s;
    flex-shrink: 0;
    box-shadow: 0 3px 12px rgba(99, 102, 241, 0.4);
  }

  .chat-send-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 18px rgba(99, 102, 241, 0.55);
  }

  .chat-send-btn:disabled {
    opacity: .4;
    cursor: default;
    transform: none;
    box-shadow: none;
  }

  /* ── Emoji input picker ───────────────────────────────── */
  #chat-emoji-picker {
    position: absolute;
    bottom: 60px;
    left: 0;
    right: 0;
    background: var(--bg-card, #fff);
    border-top: 1px solid var(--border, rgba(0, 0, 0, 0.07));
    padding: 10px;
    z-index: 10;
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 3px;
    box-shadow: 0 -6px 20px rgba(0, 0, 0, 0.07);
  }

  #chat-emoji-picker button {
    font-size: 1.2rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 7px;
    padding: 4px;
    transition: background 0.1s, transform 0.1s;
  }

  #chat-emoji-picker button:hover {
    background: var(--bg-hover);
    transform: scale(1.25);
  }

  /* ── Dropdown menu ────────────────────────────────────── */
  .chat-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, rgba(0, 0, 0, 0.08));
    border-radius: 12px;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.15);
    min-width: 170px;
    z-index: 1065;
    padding: 4px 0;
    overflow: hidden;
    animation: dropIn 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  @keyframes dropIn {
    from {
      opacity: 0;
      transform: scale(0.9) translateY(-4px);
    }

    to {
      opacity: 1;
      transform: none;
    }
  }

  .chat-dropdown-item {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 9px 14px;
    font-size: 0.84rem;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--text, #0f172a);
    text-align: left;
    transition: background 0.1s;
    font-family: 'DM Sans', sans-serif;
  }

  .chat-dropdown-item:hover {
    background: var(--bg-hover, rgba(99, 102, 241, 0.06));
  }

  .chat-dropdown-item.danger {
    color: #ef4444;
  }

  .chat-dropdown-item.danger:hover {
    background: rgba(239, 68, 68, 0.07);
  }

  /* ── Message context menu ─────────────────────────────── */
  #chat-msg-menu {
    position: fixed;
    background: var(--bg-card, #fff);
    border: 1px solid var(--border, rgba(0, 0, 0, 0.08));
    border-radius: 12px;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.15);
    min-width: 170px;
    z-index: 1060;
    padding: 4px 0;
    overflow: hidden;
  }

  .chat-menu-item {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 10px 16px;
    font-size: 0.84rem;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--text, #0f172a);
    text-align: left;
    transition: background 0.1s;
    font-family: 'DM Sans', sans-serif;
  }

  .chat-menu-item:hover {
    background: var(--bg-hover, rgba(99, 102, 241, 0.06));
  }

  .chat-menu-item.danger {
    color: #ef4444;
  }

  .chat-menu-item.danger:hover {
    background: rgba(239, 68, 68, 0.07);
  }

  /* ── Loading / Empty states ───────────────────────────── */
  .chat-loading-row {
    padding: 36px 16px;
    text-align: center;
    color: var(--text-muted, #94a3b8);
    font-size: 0.84rem;
  }

  .chat-empty-convs {
    padding: 44px 20px;
    text-align: center;
    color: var(--text-muted, #94a3b8);
  }

  .chat-empty-icon {
    font-size: 2.8rem;
    margin-bottom: 10px;
  }

  .chat-empty-convs p {
    font-size: 0.83rem;
    line-height: 1.65;
  }

  /* Load more */
  .chat-load-more-btn {
    width: 100%;
    padding: 8px;
    border: 1px dashed var(--border, rgba(0, 0, 0, 0.1));
    border-radius: 8px;
    background: transparent;
    color: var(--text-muted, #94a3b8);
    font-size: 0.78rem;
    cursor: pointer;
    transition: background 0.12s;
    margin-bottom: 8px;
  }

  .chat-load-more-btn:hover {
    background: var(--bg-hover);
    color: var(--text);
  }

  /* ── Notification popup ───────────────────────────────── */
  .chat-notif {
    position: fixed;
    bottom: 92px;
    right: 24px;
    width: 310px;
    background: var(--bg-card, #fff);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18), 0 2px 8px rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.12);
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    cursor: pointer;
    z-index: 1030;
    animation: notifSlide 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    transition: opacity 0.25s, transform 0.25s;
  }

  @keyframes notifSlide {
    from {
      opacity: 0;
      transform: translateX(24px);
    }

    to {
      opacity: 1;
      transform: none;
    }
  }

  .chat-notif:hover {
    background: var(--bg-hover);
  }

  .chat-notif-avatar {
    flex-shrink: 0;
  }

  .chat-notif-body {
    flex: 1;
    min-width: 0;
  }

  .chat-notif-app {
    font-size: 0.62rem;
    font-weight: 700;
    color: #6366f1;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 2px;
  }

  .chat-notif-name {
    font-weight: 700;
    font-size: 0.84rem;
    color: var(--text);
  }

  .chat-notif-msg {
    font-size: 0.76rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
  }

  .chat-notif-close {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .chat-notif-close:hover {
    background: var(--bg-hover);
  }

  /* ── Mobile & Short-screen ────────────────────────────── */

  /* Narrow phones: full-screen widget */
  @media (max-width: 480px) {
    #chat-widget {
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100dvh;
      min-height: unset;
      border-radius: 0;
      border: none;
    }

    #chat-fab {
      bottom: 16px;
      right: 16px;
    }

    .chat-notif {
      right: 12px;
      bottom: 86px;
      width: calc(100vw - 24px);
    }
  }

  /* Short viewports (laptops with small screens): drop FAB a bit,
   widget height is already fluid via min() — just tighten vertical gap */
  @media (max-height: 640px) {
    #chat-widget {
      bottom: 84px;
    }

    #chat-fab {
      bottom: 16px;
    }

    .chat-notif {
      bottom: 84px;
    }
  }

  /* Very short viewports: full-screen sheet */
  @media (max-height: 480px) {
    #chat-widget {
      right: 0;
      bottom: 0;
      width: 100vw;
      height: 100dvh;
      min-height: unset;
      border-radius: 0;
      border: none;
    }

    #chat-fab {
      bottom: 12px;
      right: 16px;
    }
  }
</style>

<!-- ── JAVASCRIPT ─────────────────────────────────────────── -->
<script>
  'use strict';

  const LMSChat = window.LMSChat = (() => {
    // ── State ────────────────────────────────────────────
    let pusherInst = null;
    let userChannel = null;
    let convId = null;
    let otherId = null;
    let otherData = {};
    let iBlockedThem = false;
    let theyBlockedMe = false;
    let page = 1;
    let hasMore = false;
    let typingTimer = null;
    let typingHide = null;
    let ctxMsgId = null;
    let ctxIsMine = false;
    let ctxTs = null;
    let widgetOpen = false;
    let lottieMap = {}; // emoji → lottie animation instance
    let lottieLoaded = {}; // emoji → boolean

    const ME = window.CHAT_ME_ID;
    const COLORS = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];

    // Lottie animation sources (graceful fallback to CSS if they fail)
    const REACTION_CONFIG = {
      '👍': {
        label: 'Like',
        src: 'https://assets4.lottiefiles.com/packages/lf20_yd8fbnml.json'
      },
      '❤️': {
        label: 'Love',
        src: 'https://assets9.lottiefiles.com/packages/lf20_irtst5ly.json'
      },
      '😂': {
        label: 'Haha',
        src: 'https://assets6.lottiefiles.com/packages/lf20_ngjzaqlf.json'
      },
      '😮': {
        label: 'Wow',
        src: 'https://assets7.lottiefiles.com/packages/lf20_kxsd2yfn.json'
      },
      '😢': {
        label: 'Sad',
        src: 'https://assets8.lottiefiles.com/packages/lf20_9pxqrfpq.json'
      },
      '🔥': {
        label: 'Fire',
        src: 'https://assets3.lottiefiles.com/packages/lf20_v4kcrgwf.json'
      },
    };

    const EMOJI_LIST = [
      '😀', '😂', '🥰', '😍', '😎', '🤔', '😅', '😭', '😤', '🤯', '🥳', '😴', '👍', '👎', '👏',
      '🙏', '🤝', '💪', '❤️', '🔥', '⭐', '✅', '❌', '💯', '🎉', '📚', '✍️', '💡', '🎯', '💬'
    ];

    // ── DOM refs ──────────────────────────────────────────
    const $id = id => document.getElementById(id);
    const fab = () => $id('chat-fab');
    const widget = () => $id('chat-widget');
    const vConvs = () => $id('chat-view-convs');
    const vChat = () => $id('chat-view-chat');
    const vBlocked = () => $id('chat-view-blocked');
    const convList = () => $id('chat-conv-list');
    const msgArea = () => $id('chat-messages-area');
    const inputEl = () => $id('chat-input');
    const sendBtn = () => $id('chat-send-btn');
    const typBar = () => $id('chat-typing-bar');

    // ── Helpers ───────────────────────────────────────────
    function nameColor(n) {
      let h = 0;
      for (let i = 0; i < n.length; i++) h = n.charCodeAt(i) + ((h << 5) - h);
      return COLORS[Math.abs(h) % COLORS.length];
    }

    function initials(n) {
      const p = (n || '?').trim().split(' ');
      return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
    }

    function esc(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function timeStr(dt) {
      if (!dt) return '';
      const d = new Date(dt.replace(' ', 'T')),
        now = new Date(),
        diff = (now - d) / 1000;
      if (diff < 60) return 'now';
      if (diff < 3600) return Math.floor(diff / 60) + 'm';
      if (diff < 86400) return d.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
      });
      return d.toLocaleDateString([], {
        month: 'short',
        day: 'numeric'
      });
    }

    function fullTimeStr(dt) {
      if (!dt) return '';
      return new Date(dt.replace(' ', 'T')).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    function dateLabel(dt) {
      if (!dt) return '';
      const d = new Date(dt.replace(' ', 'T')),
        now = new Date();
      if (d.toDateString() === now.toDateString()) return 'Today';
      const y = new Date();
      y.setDate(y.getDate() - 1);
      if (d.toDateString() === y.toDateString()) return 'Yesterday';
      return d.toLocaleDateString([], {
        weekday: 'short',
        month: 'short',
        day: 'numeric'
      });
    }

    // Smart last-seen: "2m ago" | "1h ago" | "Today 2:00 PM" | "Yesterday 11:00 AM" | "Sunday 8:55 PM" | "Mar 4, 5:27 AM"
    function lastSeenStr(dt) {
      if (!dt) return 'Offline';
      const d = new Date(dt.replace(' ', 'T'));
      const now = new Date();
      const diffSec = Math.floor((now - d) / 1000);
      if (diffSec < 60) return 'Just now';
      const diffMin = Math.floor(diffSec / 60);
      if (diffMin < 60) return diffMin + 'm ago';
      const diffHr = Math.floor(diffMin / 60);
      if (diffHr < 2) return diffHr + 'h ago';
      // 2+ hours: show label + time
      const t = d.toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
      const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      const yesterdayStart = new Date(todayStart);
      yesterdayStart.setDate(todayStart.getDate() - 1);
      const weekStart = new Date(todayStart);
      weekStart.setDate(todayStart.getDate() - 6);
      if (d >= todayStart) return 'Today ' + t;
      if (d >= yesterdayStart) return 'Yesterday ' + t;
      if (d >= weekStart) return d.toLocaleDateString([], {
        weekday: 'long'
      }) + ' ' + t;
      return d.toLocaleDateString([], {
        month: 'short',
        day: 'numeric'
      }) + ', ' + t;
    }

    function avatarHTML(user, size = 36) {
      const sz = size + 'px',
        fs = Math.floor(size * .35);
      if (user.profile_picture)
        return `<img src="<?= BASE_PATH ?>/uploads/profiles/${esc(user.profile_picture)}" style="width:${sz};height:${sz};border-radius:50%;object-fit:cover;display:block;" alt="${esc(user.full_name||'')}" onerror="this.outerHTML=window.initAvatar('${esc(user.full_name||'?')}',${size})">`;
      return `<div style="width:${sz};height:${sz};border-radius:50%;background:${nameColor(user.full_name||'?')};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:${fs}px;font-family:'Poppins',sans-serif;flex-shrink:0;">${initials(user.full_name||'?')}</div>`;
    }
    window.initAvatar = (name, size) => {
      const sz = size + 'px',
        fs = Math.floor(size * .35);
      return `<div style="width:${sz};height:${sz};border-radius:50%;background:${nameColor(name)};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:${fs}px;">${initials(name)}</div>`;
    };
    async function chatAjax(data) {
      if (!data.csrf_token) data.csrf_token = window.CSRF_TOKEN || '';
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
      const r = await fetch(window.LMS_BASE + '/ajax/chat.ajax.php', {
        method: 'POST',
        body: fd
      });
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('Chat AJAX raw response:', text.slice(0, 500));
        return {
          status: 'error',
          message: 'Chat error: ' + (text.slice(0, 120) || 'Empty response from server'),
          data: {}
        };
      }
    }

    // ── Tick icons ────────────────────────────────────────
    // sent      = hollow circle + single check
    // delivered = filled circle + single check
    // seen      = peer's profile picture as tiny circle
    function tickHTML(status, isMine, peer) {
      if (!isMine) return '';
      if (status === 'sent') {
        return `<span class="chat-tick tick-sent" title="Sent">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <polyline points="9 12.5 11 14.5 15 10"/>
          </svg>
        </span>`;
      }
      if (status === 'delivered') {
        return `<span class="chat-tick tick-delivered" title="Delivered">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="9" fill="rgba(255,255,255,0.55)"/>
            <polyline points="9 12.5 11 14.5 15 10" fill="none" stroke="rgba(99,102,241,0.95)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>`;
      }
      if (status === 'seen') {
        const col = nameColor(peer ? (peer.full_name || '?') : '?');
        const ini = initials(peer ? (peer.full_name || '?') : '?');
        if (peer && peer.profile_picture) {
          const src = '<?= BASE_PATH ?>/uploads/profiles/' + peer.profile_picture;
          return `<span class="tick-seen-wrap" title="Seen"><img class="tick-seen-avatar" src="${src}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span class="tick-seen-avatar-fb" style="background:${col};display:none;">${ini}</span></span>`;
        }
        return `<span class="tick-seen-wrap" title="Seen"><span class="tick-seen-avatar-fb" style="background:${col};">${ini}</span></span>`;
      }
      return '';
    }

    // ── Reaction pills with who-reacted tooltip ───────────
    function reactionsHTML(reactions, msgId) {
      if (!reactions || !Object.keys(reactions).length) return '';
      let html = '<div class="chat-reactions">';
      for (const [emoji, reactors] of Object.entries(reactions)) {
        if (!reactors || !reactors.length) continue;
        const names = reactors.map(r => {
          const rid = typeof r === 'object' ? r.id : r;
          const rname = typeof r === 'object' ? r.name : 'User';
          return rid == ME ? 'You' : rname.split(' ')[0];
        });
        const iReacted = reactors.some(r => (typeof r === 'object' ? r.id : r) == ME);
        const tooltip = `${emoji} ${names.join(', ')}`;
        html += `<div class="chat-reaction-pill-wrap">
        <button class="chat-reaction-pill${iReacted?' reacted':''}" onclick="LMSChat.react(${msgId},'${emoji}')">
          <span>${emoji}</span><span class="pill-count">${reactors.length}</span>
        </button>
        <div class="reaction-who-tooltip">${esc(tooltip)}</div>
      </div>`;
      }
      return html + '</div>';
    }

    // ── Render one message ────────────────────────────────
    function renderMsg(m) {
      const isMine = m.sender_id == ME || m.is_mine == 1;
      const cls = isMine ? 'mine' : 'theirs';
      const deleted = m.type === 'deleted';
      const content = deleted ?
        `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>This message was deleted` :
        esc(m.content).replace(/\n/g, '<br>');
      const reactions = !deleted ? reactionsHTML(m.reactions || {}, m.id) : '';
      const peerAvatar = !isMine ?
        `<div class="chat-bubble-avatar-wrap">${avatarHTML({full_name:m.sender_name||'?',profile_picture:m.sender_pic},26)}</div>` :
        '';

      return `<div class="chat-msg-wrap ${cls}" data-msg-id="${m.id}" data-mine="${isMine?1:0}" data-ts="${esc(m.created_at)}">
      ${peerAvatar}
      <div class="chat-msg-group">
        <div class="chat-bubble${deleted?' deleted-msg':''}"
             oncontextmenu="LMSChat._ctx(event,${m.id},${isMine?1:0},'${esc(m.created_at)}')"
             onmousedown="LMSChat._lp(event,${m.id},${isMine?1:0},'${esc(m.created_at)}')">
          ${content}
          <div class="chat-msg-meta">
            <span>${fullTimeStr(m.created_at)}</span>
            ${tickHTML(m.status, isMine, otherData)}
          </div>
        </div>
        ${reactions}
      </div>
    </div>`;
    }

    // ── Build reaction picker HTML ────────────────────────
    function buildReactionPicker() {
      const picker = $id('chat-reaction-picker');
      picker.innerHTML = Object.entries(REACTION_CONFIG).map(([emoji, cfg]) =>
        `<button class="reaction-btn" data-emoji="${emoji}" title="${cfg.label}">
        <div class="reaction-lottie-wrap" id="lottie-${emoji.codePointAt(0)}">
          <div class="reaction-emoji-fallback">${emoji}</div>
        </div>
        <span class="reaction-label">${cfg.label}</span>
      </button>`
      ).join('');

      // Wire reaction picker clicks
      picker.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const e = btn.dataset.emoji;
          react(parseInt(picker.dataset.msgId || 0), e);
          picker.style.display = 'none';
        });
        // Lazy-load lottie on first hover
        btn.addEventListener('mouseenter', () => loadLottie(btn.dataset.emoji), {
          once: true
        });
      });
    }

    // ── Lottie lazy loader ────────────────────────────────
    function loadLottie(emoji) {
      if (lottieLoaded[emoji] || typeof lottie === 'undefined') return;
      lottieLoaded[emoji] = true;
      const cfg = REACTION_CONFIG[emoji];
      if (!cfg || !cfg.src) return;
      const containerId = `lottie-${emoji.codePointAt(0)}`;
      const container = $id(containerId);
      if (!container) return;

      try {
        const anim = lottie.loadAnimation({
          container,
          renderer: 'svg',
          loop: true,
          autoplay: false,
          path: cfg.src,
        });
        anim.addEventListener('DOMLoaded', () => {
          // Hide fallback emoji, show lottie
          const fallback = container.querySelector('.reaction-emoji-fallback');
          if (fallback) fallback.style.display = 'none';
          container.style.display = 'flex';
          lottieMap[emoji] = anim;

          // Play on hover
          const btn = container.closest('.reaction-btn');
          if (btn) {
            btn.addEventListener('mouseenter', () => anim.play());
            btn.addEventListener('mouseleave', () => {
              anim.stop();
            });
          }
        });
        anim.addEventListener('error', () => {
          lottieLoaded[emoji] = false;
        }); // allow retry
      } catch (e) {
        lottieLoaded[emoji] = false;
      }
    }

    // ── Pusher ────────────────────────────────────────────
    function initPusher() {
      Pusher.logToConsole = false;
      pusherInst = new Pusher(window.PUSHER_KEY, {
        cluster: window.PUSHER_CLUSTER,
        authEndpoint: window.LMS_BASE + '/ajax/chat-auth.php',
        auth: {
          params: {
            csrf_token: window.CSRF_TOKEN || ''
          }
        },
      });
      userChannel = pusherInst.subscribe('private-user-' + ME);
      userChannel.bind('new-message', onNewMessage);
      userChannel.bind('message-delivered', onDelivered);
      userChannel.bind('message-seen', onSeen);
      userChannel.bind('message-deleted', onDeleted);
      userChannel.bind('message-reaction', onReaction);
      userChannel.bind('typing', onTyping);
      // Live session events — dispatched as DOM events so any page can listen
      userChannel.bind('live-started', d => document.dispatchEvent(new CustomEvent('lms:live-started', {
        detail: d
      })));
      userChannel.bind('live-opened', d => document.dispatchEvent(new CustomEvent('lms:live-opened', {
        detail: d
      })));
      userChannel.bind('live-ended', d => document.dispatchEvent(new CustomEvent('lms:live-ended', {
        detail: d
      })));
      userChannel.bind('waiting-update', d => document.dispatchEvent(new CustomEvent('lms:waiting-update', {
        detail: d
      })));
    }

    function onNewMessage(data) {
      fetchUnread();
      if (convId && data.conversation_id == convId) {
        appendMsg(data);
        scrollBottom();
        chatAjax({
          action: 'mark_seen',
          conversation_id: convId
        });
      } else {
        showNotifPopup(data);
      }
      if (widgetOpen && !convId) loadConvs();
      chatAjax({
        action: 'mark_delivered',
        conversation_id: data.conversation_id
      });
    }

    function onDelivered(data) {
      if (!convId || data.conversation_id != convId) return;
      msgArea().querySelectorAll('.chat-msg-wrap.mine .tick-sent').forEach(el => {
        el.outerHTML = tickHTML('delivered', true, otherData);
      });
    }

    function onSeen(data) {
      if (!convId || data.conversation_id != convId) return;
      // Replace all tick elements (sent or delivered) with the seen avatar
      msgArea().querySelectorAll('.chat-msg-wrap.mine .tick-sent, .chat-msg-wrap.mine .tick-delivered, .chat-msg-wrap.mine .tick-seen-wrap').forEach(el => {
        el.outerHTML = tickHTML('seen', true, otherData);
      });
    }

    function onDeleted(data) {
      const wrap = document.querySelector(`.chat-msg-wrap[data-msg-id="${data.message_id}"]`);
      if (!wrap) return;
      if (data.for_all) {
        const b = wrap.querySelector('.chat-bubble');
        if (b) {
          b.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>This message was deleted`;
          b.classList.add('deleted-msg');
        }
        wrap.querySelector('.chat-reactions')?.remove();
      } else {
        wrap.remove();
      }
    }

    function onReaction(data) {
      const wrap = document.querySelector(`.chat-msg-wrap[data-msg-id="${data.message_id}"]`);
      if (!wrap) return;
      const re = wrap.querySelector('.chat-reactions');
      const newHtml = reactionsHTML(data.reactions, data.message_id);
      if (re) re.outerHTML = newHtml;
      else wrap.querySelector('.chat-msg-group')?.insertAdjacentHTML('beforeend', newHtml);
    }

    function onTyping(data) {
      if (!convId || data.conversation_id != convId) return;
      $id('chat-typing-name').textContent = (data.sender_name || '').split(' ')[0];
      typBar().style.display = 'flex';
      clearTimeout(typingHide);
      typingHide = setTimeout(() => {
        typBar().style.display = 'none';
      }, 3000);
    }

    // ── Open / close ──────────────────────────────────────
    function toggleWidget() {
      widgetOpen = !widgetOpen;
      widget().style.display = widgetOpen ? 'flex' : 'none';
      if (widgetOpen) showConvsView();
    }

    function closeWidget() {
      widgetOpen = false;
      convId = null;
      otherId = null;
      widget().style.display = 'none';
      // Hide all dropdowns
      $id('chat-reaction-picker').style.display = 'none';
      $id('chat-msg-menu').style.display = 'none';
      $id('chat-peer-menu').style.display = 'none';
    }

    function showConvsView() {
      convId = null;
      otherId = null;
      vConvs().style.display = 'flex';
      vChat().style.display = 'none';
      vBlocked().style.display = 'none';
      loadConvs();
    }

    function showBlockedView() {
      vConvs().style.display = 'none';
      vChat().style.display = 'none';
      vBlocked().style.display = 'flex';
      loadBlocked();
    }

    // ── Load conversations ────────────────────────────────
    async function loadConvs() {
      convList().innerHTML = '<div class="chat-loading-row">Loading…</div>';
      let res;
      try {
        res = await chatAjax({
          action: 'get_conversations'
        });
      } catch (e) {
        convList().innerHTML = '<div class="chat-loading-row" style="color:#ef4444;">⚠️ Failed to load. Check your connection.</div>';
        return;
      }
      if (res.status !== 'success') {
        convList().innerHTML = `<div class="chat-loading-row" style="color:#ef4444;">⚠️ ${esc(res.message||'Failed to load conversations')}</div>`;
        return;
      }
      const convs = res.data?.conversations || [];
      if (!convs.length) {
        convList().innerHTML = `<div class="chat-empty-convs"><div class="chat-empty-icon">💬</div><p>No conversations yet.<br>Click a user in the online panel to start chatting.</p></div>`;
        return;
      }
      convList().innerHTML = convs.map(c => {
        const online = c.is_online == 1,
          unread = parseInt(c.unread_count) || 0;
        const blocked = c.i_blocked_them || c.they_blocked_me;
        const preview = blocked ? '🚫 Blocked' :
          c.last_type === 'deleted' ? 'Message deleted' :
          (c.last_content ? (c.last_sender_id == ME ? 'You: ' + c.last_content : c.last_content) : '');
        return `<div class="chat-conv-item${blocked?' is-blocked':''}" onclick="LMSChat._openConv(${c.id},${c.other_id})">
        <div class="chat-conv-avatar">
          ${avatarHTML({full_name:c.full_name,profile_picture:c.profile_picture},42)}
          <span class="chat-conv-online-dot ${online&&!blocked?'on':'off'}"></span>
        </div>
        <div class="chat-conv-body">
          <div class="chat-conv-name">${esc(c.full_name)}</div>
          <div class="chat-conv-preview ${unread&&!blocked?'unread':''}">${esc(preview)}</div>
        </div>
        <div class="chat-conv-meta">
          <span class="chat-conv-time">${timeStr(c.last_msg_at||c.updated_at)}</span>
          ${unread&&!blocked?`<span class="chat-conv-badge">${unread}</span>`:''}
        </div>
      </div>`;
      }).join('');
    }

    // ── Load blocked users ────────────────────────────────
    async function loadBlocked() {
      const list = $id('chat-blocked-list');
      list.innerHTML = '<div class="chat-loading-row">Loading…</div>';
      const res = await chatAjax({
        action: 'get_blocked_users'
      });
      if (res.status !== 'success') return;
      const users = res.data?.blocked_users || [];
      if (!users.length) {
        list.innerHTML = '<div class="chat-loading-row">No blocked users</div>';
        return;
      }
      list.innerHTML = users.map(u => `
      <div class="chat-blocked-item">
        ${avatarHTML(u,40)}
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:0.875rem;color:var(--text);">${esc(u.full_name)}</div>
          <div style="font-size:0.72rem;color:var(--text-muted);">Blocked ${timeStr(u.blocked_at)}</div>
        </div>
        <button class="chat-unblock-btn" onclick="LMSChat._unblock(${u.id})">Unblock</button>
      </div>`).join('');
    }

    // ── Open conversation ─────────────────────────────────
    async function _openConv(cId, oId) {
      convId = cId;
      otherId = oId;
      page = 1;
      hasMore = false;
      vConvs().style.display = 'none';
      vChat().style.display = 'flex';
      msgArea().innerHTML = '<div class="chat-loading-row">Loading…</div>';
      typBar().style.display = 'none';
      $id('chat-blocked-banner').style.display = 'none';

      const res = await chatAjax({
        action: 'get_or_create',
        other_id: oId
      });
      if (res.status !== 'success') {
        if (window.Toast) Toast.error(res.message || 'Cannot open conversation');
        showConvsView();
        return;
      }
      otherData = res.data.other || {};
      convId = res.data.conversation_id;
      iBlockedThem = res.data.i_blocked_them || false;
      theyBlockedMe = res.data.they_blocked_me || false;

      // Update header
      const online = otherData.is_online == 1;
      const peerAvHTML = avatarHTML(otherData, 36);
      $id('chat-peer-avatar').innerHTML = `<div style="position:relative;">${peerAvHTML}${online?`<span style="position:absolute;bottom:1px;right:1px;width:9px;height:9px;border-radius:50%;background:#10b981;border:2px solid var(--bg-sidebar,#1e1b4b);"></span>`:''}`;
      $id('chat-peer-name').textContent = otherData.full_name || '';
      $id('chat-peer-status').textContent = online ? '● Online' : 'Last seen ' + lastSeenStr(otherData.last_seen);
      $id('chat-peer-status').style.color = online ? 'rgba(255,255,255,0.95)' : 'rgba(255,255,255,0.65)';
      $id('chat-peer-block-label').textContent = iBlockedThem ? 'Unblock User' : 'Block User';

      // Blocked banner
      if (iBlockedThem || theyBlockedMe) {
        const banner = $id('chat-blocked-banner');
        banner.textContent = iBlockedThem ? `You blocked ${(otherData.full_name||'').split(' ')[0]}. Unblock to message.` : `You can't message ${(otherData.full_name||'').split(' ')[0]}.`;
        banner.style.display = 'block';
      }
      // Disable input if blocked
      const locked = iBlockedThem || theyBlockedMe;
      inputEl().disabled = locked;
      sendBtn().disabled = locked;
      inputEl().placeholder = locked ? 'Messaging is unavailable' : 'Type a message…';

      chatAjax({
        action: 'mark_delivered',
        conversation_id: convId
      });
      await loadMsgs(false);
      chatAjax({
        action: 'mark_seen',
        conversation_id: convId
      });
      fetchUnread();
    }

    async function openWith(userId, otherGender, otherRole) {
      // Frontend gender check
      const myRole = window.USER_ROLE || '',
        myGender = window.USER_GENDER || '';
      if (myRole === 'student' && otherRole === 'student' && otherGender && myGender && myGender !== otherGender) {
        if (window.Toast) Toast.error('Students can only message same-gender classmates.');
        return;
      }
      if (!widgetOpen) {
        widgetOpen = true;
        widget().style.display = 'flex';
      }
      await _openConv(null, userId);
    }

    // ── Load messages ─────────────────────────────────────
    async function loadMsgs(append) {
      if (!convId) return;
      let res;
      try {
        res = await chatAjax({
          action: 'get_messages',
          conversation_id: convId,
          page
        });
      } catch (e) {
        msgArea().innerHTML = '<div class="chat-loading-row" style="color:#ef4444;">⚠️ Failed to load messages.</div>';
        return;
      }
      if (res.status !== 'success') {
        msgArea().innerHTML = `<div class="chat-loading-row" style="color:#ef4444;">⚠️ ${esc(res.message||'Failed to load messages')}</div>`;
        return;
      }
      const msgs = res.data?.messages || [];
      hasMore = res.data?.has_more || false;
      if (append) {
        const before = msgArea().scrollHeight;
        $id('chat-load-more')?.remove();
        buildMsgHTML(msgs, true);
        msgArea().scrollTop += (msgArea().scrollHeight - before);
      } else {
        buildMsgHTML(msgs, false);
        scrollBottom();
      }
      if (hasMore) msgArea().insertAdjacentHTML('afterbegin', `<button id="chat-load-more" class="chat-load-more-btn" onclick="LMSChat._loadMore()">↑ Load older messages</button>`);
    }

    function buildMsgHTML(msgs, prepend) {
      if (!msgs.length && !prepend) {
        msgArea().innerHTML = '<div class="chat-loading-row">Say hello! 👋</div>';
        return;
      }
      let html = '',
        lastDate = null;
      msgs.forEach(m => {
        const d = dateLabel(m.created_at);
        if (d !== lastDate) {
          html += `<div class="chat-date-divider">${esc(d)}</div>`;
          lastDate = d;
        }
        html += renderMsg(m);
      });
      if (prepend) msgArea().insertAdjacentHTML('afterbegin', html);
      else msgArea().innerHTML = html;
    }

    function appendMsg(m) {
      const d = dateLabel(m.created_at);
      const last = msgArea().querySelector('.chat-date-divider:last-of-type');
      if (d !== last?.textContent) msgArea().insertAdjacentHTML('beforeend', `<div class="chat-date-divider">${esc(d)}</div>`);
      msgArea().insertAdjacentHTML('beforeend', renderMsg(m));
    }

    function scrollBottom() {
      setTimeout(() => {
        msgArea().scrollTop = msgArea().scrollHeight;
      }, 30);
    }

    // ── Send ──────────────────────────────────────────────
    async function sendMsg() {
      const inp = inputEl(),
        content = inp.value.trim();
      if (!content || !otherId || iBlockedThem || theyBlockedMe) return;
      inp.value = '';
      autoResize(inp);
      sendBtn().disabled = true;
      const res = await chatAjax({
        action: 'send',
        other_id: otherId,
        conv_id: convId || 0,
        content
      });
      sendBtn().disabled = false;
      if (res.status === 'success') {
        const msg = res.data?.message;
        if (msg) {
          appendMsg(msg);
          scrollBottom();
        }
        if (!convId && res.data?.message?.conversation_id) convId = res.data.message.conversation_id;
      } else {
        if (window.Toast) Toast.error(res.message || 'Failed to send');
      }
    }

    function onInput() {
      if (!otherId || !convId) return;
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => chatAjax({
        action: 'typing',
        other_id: otherId,
        conv_id: convId
      }), 400);
    }

    function autoResize(el) {
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    }

    // ── FAB badge ─────────────────────────────────────────
    async function fetchUnread() {
      const res = await chatAjax({
        action: 'get_unread_count'
      });
      const cnt = res.data?.unread || 0;
      const b = $id('chat-fab-badge'),
        tb = $id('chat-total-unread-badge');
      if (cnt > 0) {
        b.textContent = cnt > 99 ? '99+' : cnt;
        b.style.display = 'flex';
        if (tb) {
          tb.textContent = cnt;
          tb.style.display = 'flex';
        }
      } else {
        b.style.display = 'none';
        if (tb) tb.style.display = 'none';
      }
    }

    // ── Notification popup ────────────────────────────────
    function showNotifPopup(data) {
      document.querySelectorAll('.chat-notif').forEach(n => n.remove());
      const n = document.createElement('div');
      n.className = 'chat-notif';
      n.innerHTML = `
      <div class="chat-notif-avatar">${avatarHTML({full_name:data.sender_name||'?',profile_picture:data.sender_pic},38)}</div>
      <div class="chat-notif-body">
        <div class="chat-notif-app">EduFlow Chat</div>
        <div class="chat-notif-name">${esc(data.sender_name||'New Message')}</div>
        <div class="chat-notif-msg">${esc((data.content||'').slice(0,70))}</div>
      </div>
      <button class="chat-notif-close" onclick="this.closest('.chat-notif').remove()">✕</button>`;
      n.addEventListener('click', e => {
        if (e.target.closest('.chat-notif-close')) return;
        n.remove();
        openWith(data.sender_id);
      });
      document.body.appendChild(n);
      setTimeout(() => {
        n.style.opacity = '0';
        n.style.transform = 'translateX(20px)';
        setTimeout(() => n.remove(), 280);
      }, 5500);
    }

    // ── Context menu ──────────────────────────────────────
    let lpTimer = null;

    function _ctx(e, msgId, isMine, ts) {
      e.preventDefault();
      showMsgMenu(e.clientX, e.clientY, msgId, isMine, ts);
    }

    function _lp(e, msgId, isMine, ts) {
      if (e.button !== 0) return;
      lpTimer = setTimeout(() => showMsgMenu(e.clientX, e.clientY, msgId, isMine, ts), 600);
    }
    document.addEventListener('mouseup', () => clearTimeout(lpTimer));

    function showMsgMenu(x, y, msgId, isMine, ts) {
      ctxMsgId = msgId;
      ctxIsMine = isMine;
      ctxTs = ts;
      const menu = $id('chat-msg-menu'),
        da = $id('chat-menu-del-all');
      da.style.display = (isMine && ts && ((Date.now() - new Date(ts.replace(' ', 'T'))) / 60000) <= 10) ? 'flex' : 'none';
      menu.style.display = 'block';
      menu.style.left = Math.min(x, window.innerWidth - 185) + 'px';
      menu.style.top = Math.min(y, window.innerHeight - 135) + 'px';
      setTimeout(() => document.addEventListener('click', closeMsgMenu, {
        once: true
      }), 10);
    }

    function closeMsgMenu() {
      $id('chat-msg-menu').style.display = 'none';
    }

    async function doDelete(forAll) {
      if (!ctxMsgId) return;
      const res = await chatAjax({
        action: 'delete_message',
        message_id: ctxMsgId,
        for_all: forAll ? 1 : 0
      });
      if (res.status !== 'success' && window.Toast) Toast.error(res.message);
    }

    // ── Reaction picker ───────────────────────────────────
    function showReactionPicker(msgId) {
      const wrap = document.querySelector(`.chat-msg-wrap[data-msg-id="${msgId}"]`);
      if (!wrap) return;
      const rect = wrap.getBoundingClientRect(),
        picker = $id('chat-reaction-picker');
      picker.dataset.msgId = msgId;
      picker.style.display = 'flex';
      picker.style.top = (rect.top - 62) + 'px';
      picker.style.left = Math.min(Math.max(rect.left, 8), window.innerWidth - 280) + 'px';
      setTimeout(() => document.addEventListener('click', () => picker.style.display = 'none', {
        once: true
      }), 10);
    }

    async function react(msgId, emoji) {
      if (!msgId) return;
      await chatAjax({
        action: 'react',
        message_id: msgId,
        emoji
      });
    }

    // ── Block / unblock ───────────────────────────────────
    async function _toggleBlock() {
      if (!otherId) return;
      $id('chat-peer-menu').style.display = 'none';
      if (iBlockedThem) {
        const res = await chatAjax({
          action: 'unblock_user',
          other_id: otherId
        });
        if (res.status === 'success') {
          iBlockedThem = false;
          $id('chat-peer-block-label').textContent = 'Block User';
          $id('chat-blocked-banner').style.display = 'none';
          inputEl().disabled = false;
          sendBtn().disabled = false;
          inputEl().placeholder = 'Type a message…';
          if (window.Toast) Toast.success('User unblocked');
        }
      } else {
        const firstName = (otherData.full_name || 'this user').split(' ')[0];
        Modal.confirm({
          title: 'Block ' + firstName + '?',
          message: firstName + ' won\'t be able to message you and you won\'t see their messages.',
          confirmText: 'Block User',
          cancelText: 'Cancel',
          confirmClass: 'btn-danger',
          icon: '🚫',
          iconClass: 'modal-icon-danger',
          onConfirm: async () => {
            const res = await chatAjax({
              action: 'block_user',
              other_id: otherId
            });
            if (res.status === 'success') {
              iBlockedThem = true;
              $id('chat-peer-block-label').textContent = 'Unblock User';
              const banner = $id('chat-blocked-banner');
              banner.textContent = `You blocked ${firstName}. Unblock to message.`;
              banner.style.display = 'block';
              inputEl().disabled = true;
              sendBtn().disabled = true;
              inputEl().placeholder = 'Messaging is unavailable';
              if (window.Toast) Toast.info(firstName + ' has been blocked');
            } else {
              if (window.Toast) Toast.error(res.message || 'Failed to block user');
            }
          }
        });
      }
    }

    async function _unblock(uid) {
      const res = await chatAjax({
        action: 'unblock_user',
        other_id: uid
      });
      if (res.status === 'success') {
        if (window.Toast) Toast.success('Unblocked');
        loadBlocked();
      } else if (window.Toast) Toast.error(res.message);
    }

    // ── Delete conversation ───────────────────────────────
    async function _deleteConv() {
      $id('chat-peer-menu').style.display = 'none';
      const firstName = (otherData.full_name || 'this user').split(' ')[0];
      Modal.confirm({
        title: 'Delete conversation?',
        message: 'This chat will be removed from your side only. ' + firstName + ' will still see the messages.',
        confirmText: 'Delete Chat',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        icon: '🗑️',
        iconClass: 'modal-icon-danger',
        onConfirm: async () => {
          const res = await chatAjax({
            action: 'delete_conversation',
            conversation_id: convId
          });
          if (res.status === 'success') {
            if (window.Toast) Toast.success('Chat deleted');
            showConvsView();
          } else {
            if (window.Toast) Toast.error(res.message || 'Failed to delete chat');
          }
        }
      });
    }

    // ── Emoji input picker ────────────────────────────────
    function toggleEmojiPicker() {
      const ep = $id('chat-emoji-picker');
      if (ep.style.display === 'grid') {
        ep.style.display = 'none';
        return;
      }
      if (!ep.children.length) ep.innerHTML = EMOJI_LIST.map(e => `<button onclick="LMSChat._insertEmoji('${e}')">${e}</button>`).join('');
      ep.style.display = 'grid';
    }

    function _insertEmoji(e) {
      const inp = inputEl(),
        pos = inp.selectionStart;
      inp.value = inp.value.slice(0, pos) + e + inp.value.slice(pos);
      inp.focus();
      inp.selectionStart = inp.selectionEnd = pos + e.length;
      autoResize(inp);
    }

    function _loadMore() {
      page++;
      loadMsgs(true);
    }

    // ── Init ──────────────────────────────────────────────
    function init() {
      initPusher();
      buildReactionPicker();
      fetchUnread();
      setInterval(fetchUnread, 30000);

      fab().addEventListener('click', toggleWidget);
      $id('chat-close-btn')?.addEventListener('click', closeWidget);
      $id('chat-close-btn2')?.addEventListener('click', closeWidget);
      $id('chat-close-btn3')?.addEventListener('click', closeWidget);
      $id('chat-back-btn')?.addEventListener('click', showConvsView);
      $id('chat-blocked-btn')?.addEventListener('click', showBlockedView);
      $id('chat-blocked-back-btn')?.addEventListener('click', showConvsView);

      // Peer action menu
      $id('chat-peer-menu-btn')?.addEventListener('click', e => {
        e.stopPropagation();
        const m = $id('chat-peer-menu');
        m.style.display = m.style.display === 'block' ? 'none' : 'block';
        if (m.style.display === 'block')
          setTimeout(() => document.addEventListener('click', () => m.style.display = 'none', {
            once: true
          }), 10);
      });
      $id('chat-peer-block-btn')?.addEventListener('click', _toggleBlock);
      $id('chat-peer-delete-btn')?.addEventListener('click', _deleteConv);

      sendBtn().addEventListener('click', sendMsg);
      const ta = inputEl();
      if (ta) {
        ta.addEventListener('input', function() {
          autoResize(this);
          onInput();
        });
        ta.addEventListener('keydown', e => {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMsg();
          }
        });
        autoResize(ta);
      }

      $id('chat-emoji-toggle')?.addEventListener('click', e => {
        e.stopPropagation();
        toggleEmojiPicker();
      });

      $id('chat-menu-react')?.addEventListener('click', () => {
        closeMsgMenu();
        showReactionPicker(ctxMsgId);
      });
      $id('chat-menu-del-me')?.addEventListener('click', () => {
        closeMsgMenu();
        doDelete(false);
      });
      $id('chat-menu-del-all')?.addEventListener('click', () => {
        closeMsgMenu();
        doDelete(true);
      });

      document.addEventListener('click', e => {
        if (!e.target.closest('#chat-emoji-picker') && !e.target.closest('#chat-emoji-toggle'))
          $id('chat-emoji-picker').style.display = 'none';
      });

      msgArea()?.addEventListener('focus', () => {
        if (convId) chatAjax({
          action: 'mark_seen',
          conversation_id: convId
        });
      });
    }

    return {
      init,
      openWith,
      react,
      _openConv,
      _loadMore,
      _ctx,
      _lp,
      _insertEmoji,
      _unblock
    };
  })();

  document.addEventListener('DOMContentLoaded', LMSChat.init);
</script>