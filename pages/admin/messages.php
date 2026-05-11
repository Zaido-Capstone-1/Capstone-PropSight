<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/messages-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Messages';
$active_page = 'messages';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$adminId = (int) $_SESSION['user_id'];

$threadsRes = mysqli_query($conn, "
    SELECT DISTINCT
        IF(m.from_user=$adminId, m.to_user, m.from_user) AS other_id,
        CONCAT(u.first_name,' ',u.last_name) AS other_name,
        u.email AS other_email,
        (SELECT body FROM messages WHERE (from_user=$adminId AND to_user=other_id) OR (from_user=other_id AND to_user=$adminId) ORDER BY created_at DESC LIMIT 1) AS last_body,
        (SELECT created_at FROM messages WHERE (from_user=$adminId AND to_user=other_id) OR (from_user=other_id AND to_user=$adminId) ORDER BY created_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages WHERE from_user=other_id AND to_user=$adminId AND is_read=0) AS unread
    FROM messages m
    JOIN users u ON u.user_id = IF(m.from_user=$adminId, m.to_user, m.from_user)
    WHERE m.from_user=$adminId OR m.to_user=$adminId
    ORDER BY last_time DESC
");
$threads = [];
while ($t = mysqli_fetch_assoc($threadsRes))
    $threads[] = $t;

$usersRes = mysqli_query($conn, "SELECT user_id, first_name, last_name, email FROM users WHERE role='user' AND is_active=1 ORDER BY first_name");
$userList = [];
while ($u = mysqli_fetch_assoc($usersRes))
    $userList[] = $u;
?>
<link rel="stylesheet" href="../../assets/css/admin-css/message.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner" style="overflow:hidden;">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Messages</h1>
            <p class="dash-subtitle">Communications with tenants and staff.</p>
        </div>
        <div class="dash-header-actions">
            <button class="btn btn-primary" onclick="openNewMessage()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New Message
            </button>
        </div>
    </div>
    
    <div class="msg-layout">

        <div class="msg-list">
            <div class="msg-list-header">
                <input type="text" placeholder="Search messages..." />
            </div>
            <div class="msg-threads">
                <?php if (empty($threads)): ?>
                    <div style="padding:24px;text-align:center;color:#888;font-size:13px;">No messages yet.</div>
                <?php else: ?>
                    <?php foreach ($threads as $i => $t):
                        $initials = strtoupper(mb_substr($t['other_name'], 0, 1));
                        $preview = mb_strimwidth($t['last_body'] ?? '', 0, 50, '...');
                        $timeAgo = $t['last_time'] ? (time() - strtotime($t['last_time']) < 86400 ? date('g:i A', strtotime($t['last_time'])) : date('M j', strtotime($t['last_time']))) : '';
                        $unread = (int) $t['unread'];
                        ?>
                        <div class="msg-thread" data-user-id="<?= $t['other_id'] ?>"
                            onclick="loadConversation(<?= $t['other_id'] ?>, '<?= htmlspecialchars($t['other_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['other_email'] ?? '', ENT_QUOTES) ?>')"
                            style="cursor:pointer;">
                            <div class="avatar"><?= $initials ?></div>
                            <div class="msg-thread-info">
                                <div class="msg-thread-name"><?= htmlspecialchars($t['other_name']) ?></div>
                                <div class="msg-thread-preview thread-preview"><?= htmlspecialchars($preview) ?></div>
                            </div>
                            <div class="msg-thread-meta">
                                <div class="msg-thread-time"><?= $timeAgo ?></div>
                                <?php if ($unread > 0): ?>
                                    <div class="msg-unread"><?= $unread ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="msg-pane" id="msgPane">
            <div id="msgPaneEmpty"
                style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#94a3b8;padding:40px;">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" height="48"
                    style="color:#cbd5e1;">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                <div style="font-size:15px;font-weight:600;color:#64748b;">Select a conversation</div>
                <div style="font-size:13px;text-align:center;max-width:220px;">Click on a message thread to view and
                    reply</div>
            </div>
        </div>

    </div>
</div>

<script>

</script>
<script>
    const ADMIN_ID = <?php echo $adminId; ?>;
    const ADMIN_API = '../../api/messages.php';
    window.__PS_ADMIN_MSG_USERS__ = <?php echo json_encode($userList); ?>;
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
</script>
<script>window.PS_RT_PAGE = 'messages';</script>
<script src="../../assets/js/admin/messages.js"></script>

<?php include '../../includes/layout_close.php'; ?>