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
include '../../lib/admin-queries/messages_queries.php';
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
                        $timeAgo = $t['last_time'] ? (time() - strtotime($t['last_time'] . ' UTC') < 86400 ? gmdate('g:i A', strtotime($t['last_time'] . ' UTC')) : gmdate('M j', strtotime($t['last_time'] . ' UTC'))) : '';
                        $unread = (int) $t['unread'];
                        $otherPhoto = !empty($t['other_photo']) ? htmlspecialchars('../../' . ltrim($t['other_photo'], '/')) : '';
                        ?>
                        <div class="msg-thread" data-user-id="<?= $t['other_id'] ?>" data-user-photo="<?= $otherPhoto ?>"
                            onclick="loadConversation(<?= $t['other_id'] ?>, '<?= htmlspecialchars($t['other_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['other_email'] ?? '', ENT_QUOTES) ?>', '<?= $otherPhoto ?>')"
                            style="cursor:pointer;">
                            <div class="avatar">
                                <?php if ($otherPhoto): ?>
                                    <img src="<?= $otherPhoto ?>" alt=""
                                        style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='';">
                                    <span style="display:none;"><?= $initials ?></span>
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <div class="msg-thread-info">
                                <div class="msg-thread-name"><?= htmlspecialchars($t['other_name']) ?></div>
                                <div class="msg-thread-preview thread-preview"><?= htmlspecialchars($preview) ?></div>
                            </div>
                            <div class="msg-thread-meta">
                                <div class="msg-thread-time" data-ts="<?= $t['last_time'] ?>"></div>
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
    const ADMIN_API = '../../endpoints/messages.php';
    window.__PS_ADMIN_MSG_USERS__ = <?php echo json_encode($userList); ?>;
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
</script>
<script>window.PS_RT_PAGE = 'messages';</script>
<script src="../../assets/js/admin/messages.js?v=<?= time() ?>"></script>

<?php include '../../includes/layout_close.php'; ?>