<?php
include '../../includes/session.php';
require_not_blacklisted(false);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../../index.php');
    exit;
}

$email = htmlspecialchars($_SESSION['email'] ?? '');
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));

$page_title = 'Messages';
$active_nav = 'messages';
$page_hero_html = 'My <em>Messages</em>';
$page_hero_sub = 'Direct conversations with our property team.';
$page_hero_icon = '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>';

require '../../includes/_layout.php';
require_once '../../lib/user-queries/messages_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/user-css/messages.css">

<!-- Info strip above messages -->
<div class="reveal"
    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px;">
    <div
        style="background:#fff;border:1px solid var(--blue-100);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;">
        <div
            style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#3b82f6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
        </div>
        <div>
            <div style="font-size:0.78rem;font-weight:700;color:var(--text-dark);">Direct Messaging</div>
            <div style="font-size:0.72rem;color:var(--text-soft);">Chat directly with our property team</div>
        </div>
    </div>
    <div
        style="background:#fff;border:1px solid var(--blue-100);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;">
        <div
            style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#dcfce7,#16a34a);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        </div>
        <div>
            <div style="font-size:0.78rem;font-weight:700;color:var(--text-dark);">Response Time</div>
            <div style="font-size:0.72rem;color:var(--text-soft);">Team replies within ~2 hours</div>
        </div>
    </div>
    <div
        style="background:#fff;border:1px solid var(--blue-100);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;">
        <div
            style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#fef3c7,#f59e0b);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
        </div>
        <div>
            <div style="font-size:0.78rem;font-weight:700;color:var(--text-dark);">All messages secure</div>
            <div style="font-size:0.72rem;color:var(--text-soft);">End-to-end private conversations</div>
        </div>
    </div>
</div>

<div class="msg-page reveal">

    <!-- ── Left: thread list ── -->
    <div class="msg-sidebar" id="msgSidebar">
        <div class="msg-sidebar-header">
            <span class="msg-sidebar-title">Messages</span>
            <button class="msg-new-btn" onclick="openNewMsg()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New
            </button>
        </div>
        <div class="msg-search-wrap">
            <input type="text" id="threadSearch" placeholder="Search messages…" oninput="filterThreads(this.value)">
        </div>
        <div class="msg-threads" id="threadList">
            <?php if (empty($threads)): ?>
                <div class="msg-empty-threads">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                    No conversations yet.<br>Click <strong>New</strong> to message the team.
                </div>
            <?php else: ?>
                <?php foreach ($threads as $t):
                    $initial = strtoupper(mb_substr($t['admin_name'], 0, 1));
                    $preview = mb_strimwidth($t['last_body'] ?? '', 0, 48, '…');
                    $ts = $t['last_time'] ? strtotime($t['last_time']) : 0;
                    $timeStr = $ts ? (time() - $ts < 86400 ? date('g:i A', $ts) : date('M j', $ts)) : '';
                    $unread = (int) $t['unread'];
                    $adminPhoto = !empty($t['admin_photo']) ? htmlspecialchars('../../' . ltrim($t['admin_photo'], '/')) : '';
                    ?>
                    <div class="msg-thread-item<?= $unread ? ' has-unread' : '' ?>" data-admin-id="<?= $t['admin_id'] ?>"
                        data-admin-name="<?= htmlspecialchars($t['admin_name'], ENT_QUOTES) ?>"
                        data-admin-photo="<?= $adminPhoto ?>"
                        data-search="<?= strtolower(htmlspecialchars($t['admin_name'], ENT_QUOTES)) ?>"
                        onclick="openConversation(<?= $t['admin_id'] ?>, '<?= htmlspecialchars($t['admin_name'], ENT_QUOTES) ?>', '<?= $adminPhoto ?>')">
                        <div class="ti-avatar">
                            <?php if ($adminPhoto): ?>
                                <img src="<?= $adminPhoto ?>" alt=""
                                    style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='';">
                                <span style="display:none;"><?= $initial ?></span>
                            <?php else: ?>
                                <?= $initial ?>
                            <?php endif; ?>
                        </div>
                        <div class="ti-body">
                            <div class="ti-name"><?= htmlspecialchars($t['admin_name']) ?></div>
                            <div class="ti-preview"><?= htmlspecialchars($preview) ?></div>
                        </div>
                        <div class="ti-meta">
                            <div class="ti-time"><?= $timeStr ?></div>
                            <?php if ($unread): ?>
                                <div class="ti-badge"><?= $unread ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: chat pane ── -->
    <div class="msg-chat" id="msgChat">
        <div class="msg-chat-placeholder" id="chatPlaceholder">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
            <span>Select a conversation to get started</span>
        </div>
    </div>
</div>

<!-- ── New message modal ── -->
<div class="msg-modal-overlay" id="newMsgModal" onclick="if(event.target===this)closeNewMsg()">
    <div class="msg-modal">
        <h3>New Message</h3>
        <label>Send to</label>
        <select id="nm_admin">
            <?php foreach ($admins as $a): ?>
                <option value="<?= $a['user_id'] ?>"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Subject <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
        <input type="text" id="nm_subject" placeholder="e.g. Question about my booking">
        <label>Message</label>
        <textarea id="nm_body" placeholder="Type your message here…"></textarea>
        <p id="nm_err" style="color:#ef4444;font-size:12px;margin:8px 0 0;display:none;"></p>
        <div class="msg-modal-actions">
            <button class="msg-modal-cancel" onclick="closeNewMsg()">Cancel</button>
            <button class="msg-modal-send" id="nm_sendBtn" onclick="sendNewMsg()">Send Message</button>
        </div>
    </div>
</div>

<script>
    window.__PS_USER_MSG__ = {
        userId: <?= $userId ?>,
        apiUrl: '../../api/user/messages.php'
    };
</script>
<script>window.PS_RT_PAGE = 'messages';</script>
<script src="../../assets/js/user-js/messages.js"></script>

<?php require '../../includes/_layout_end.php'; ?>
<script src="../../assets/js/user-js/messages-inline.js"></script>