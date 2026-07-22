<?php
// Floating messages bubble — shown on every admin page via includes/sidebar.php.
// Skipped on pages/admin/messages.php itself, which still renders the full
// server-side inbox and would otherwise duplicate all of this markup/JS.
if (($active_page ?? '') === 'messages') {
    return;
}
?>
<link rel="stylesheet" href="../../assets/css/admin-css/message.css">
<link rel="stylesheet" href="../../assets/css/admin-css/messages-widget.css">

<button class="ps-msgw-bubble" id="psMsgwBubble" aria-label="Messages" aria-expanded="false" title="Messages">
    <svg class="ps-msgw-icon-chat" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 5.94 2 10.8c0 2.68 1.42 5.07 3.64 6.68-.13 1.11-.53 2.35-1.5 3.6a.5.5 0 0 0 .5.8c2.02-.4 3.62-1.24 4.73-2.02.83.2 1.7.31 2.63.31 5.52 0 10-3.94 10-8.8S17.52 2 12 2z"/>
        <circle class="ps-msgw-dot" cx="7.5" cy="10.8" r="1.35" fill="var(--blue-700,#1d4ed8)"/>
        <circle class="ps-msgw-dot" cx="12" cy="10.8" r="1.35" fill="var(--blue-700,#1d4ed8)"/>
        <circle class="ps-msgw-dot" cx="16.5" cy="10.8" r="1.35" fill="var(--blue-700,#1d4ed8)"/>
    </svg>
    <svg class="ps-msgw-icon-close" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:20px;height:20px;">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
    <span class="nav-badge" data-rt="messages" id="psMsgwBadge">0</span>
</button>

<div class="ps-msgw-panel" id="psMsgwPanel" role="dialog" aria-label="Messages">
    <div class="ps-msgw-header">
        <h3>Messages</h3>
        <div class="ps-msgw-header-actions">
            <button class="ps-msgw-icon-btn" id="psMsgwNewBtn" title="New message" aria-label="New message" onclick="openNewMessage()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
            </button>
            <button class="ps-msgw-icon-btn" id="psMsgwCloseBtn" title="Close" aria-label="Close messages">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
    </div>

    <div class="ps-msgw-body">
        <div class="msg-layout">
            <div class="msg-list">
                <div class="msg-list-header">
                    <input type="text" placeholder="Search messages..." id="psMsgwSearch" />
                </div>
                <div class="msg-threads" id="psMsgwThreads">
                    <div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">Loading…</div>
                </div>
            </div>

            <div class="msg-pane" id="msgPane">
                <div id="msgPaneEmpty"
                    style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#94a3b8;padding:24px;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="40" height="40"
                        style="color:#cbd5e1;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    <div style="font-size:14px;font-weight:600;color:#64748b;">Select a conversation</div>
                    <div style="font-size:12.5px;text-align:center;max-width:200px;">Click on a message thread to view
                        and reply</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ADMIN_ID = <?= (int) ($_SESSION['user_id'] ?? 0) ?>;
    const ADMIN_API = '../../endpoints/messages.php';
    // Fallback for pages (e.g. index.php) that don't include layout_open.php,
    // which is otherwise the only place window.PS_CSRF_TOKEN gets set.
    window.PS_CSRF_TOKEN = window.PS_CSRF_TOKEN || <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="../../assets/js/admin/messages.js?v=<?= time() ?>"></script>
<script src="../../assets/js/admin/messages-widget.js?v=<?= time() ?>"></script>