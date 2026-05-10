<?php

include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html><html><body>
<script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/support-inline.js"></script>
</body></html>';
    exit;
}

$page_title = 'Support Tickets';
$active_page = 'support';

include '../../includes/db.php';
include '../../includes/layout_open.php';

$adminId = (int) $_SESSION['user_id'];

// ── Filters ──────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$perPage = 15;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

// ── Support Tickets ───────────────────────────────────────────────
$ticketWhere = "WHERE 1=1";
if ($statusFilter !== 'all') {
    $sf = mysqli_real_escape_string($conn, $statusFilter);
    $ticketWhere .= " AND t.status = '$sf'";
}
if ($search !== '') {
    $se = mysqli_real_escape_string($conn, $search);
    $ticketWhere .= " AND (t.subject LIKE '%$se%' OR CONCAT(u.first_name,' ',u.last_name) LIKE '%$se%' OR t.category LIKE '%$se%')";
}

$ticketCountRes = mysqli_query($conn, "
    SELECT COUNT(*) AS c
    FROM support_tickets t
    JOIN users u ON u.user_id = t.user_id
    $ticketWhere
");
$ticketTotal = ($ticketCountRes && ($r = mysqli_fetch_assoc($ticketCountRes))) ? (int) ($r['c'] ?? 0) : 0;
$ticketPages = max(1, (int) ceil($ticketTotal / $perPage));

$ticketsRes = mysqli_query($conn, "
    SELECT
        t.ticket_id, t.category, t.subject, t.priority, t.status, t.created_at,
        CONCAT(u.first_name,' ',u.last_name) AS user_name,
        u.email AS user_email,
        u.profile_photo AS user_photo,
        (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = t.ticket_id) AS msg_count,
        (SELECT sm2.body FROM support_messages sm2 WHERE sm2.ticket_id = t.ticket_id ORDER BY sm2.created_at DESC LIMIT 1) AS last_message
    FROM support_tickets t
    JOIN users u ON u.user_id = t.user_id
    $ticketWhere
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$tickets = [];
if ($ticketsRes)
    while ($r = mysqli_fetch_assoc($ticketsRes))
        $tickets[] = $r;

// ── Summary Stats ─────────────────────────────────────────────────
$ticketStats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open_cnt,
        SUM(status = 'in_progress') AS in_progress_cnt,
        SUM(status = 'resolved') AS resolved_cnt,
        SUM(status = 'closed') AS closed_cnt
    FROM support_tickets
")) ?: [];

// ── PHP Helpers ───────────────────────────────────────────────────
function ticketBadge(string $s): array
{
    return match ($s) {
        'open' => ['label' => 'Open', 'cls' => 'badge-open'],
        'in_progress' => ['label' => 'In Progress', 'cls' => 'badge-progress'],
        'resolved' => ['label' => 'Resolved', 'cls' => 'badge-done'],
        'closed' => ['label' => 'Closed', 'cls' => 'badge-done'],
        default => ['label' => ucfirst($s), 'cls' => 'badge-pending'],
    };
}

function priorityBadge(string $p): array
{
    return match (strtolower($p)) {
        'urgent', 'high' => ['label' => ucfirst($p), 'cls' => 'pri-high'],
        'medium', 'normal' => ['label' => 'Medium', 'cls' => 'pri-med'],
        default => ['label' => 'Low', 'cls' => 'pri-low'],
    };
}

function buildQS(array $overrides = []): string
{
    $base = array_merge($_GET, $overrides);
    return '?' . htmlspecialchars(http_build_query($base));
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/support.css">

<!-- ── Page Header ──────────────────────────────────────────────── -->
<div class="page-header">
    <div class="top-header">
        <h2>Support Tickets</h2>
        <div class="page-header-sub">Manage and respond to guest support tickets.</div>
    </div>
</div>

<div class="page-inner">

    <div class="sm-stat-row">

        <div class="sm-stat">
            <div>
                <div class="sm-stat-label">Total Tickets</div>
                <div class="sm-stat-value"><?= (int) ($ticketStats['total'] ?? 0) ?></div>
            </div>
            <div class="sm-stat-icon ic-slate" style="margin-left:auto;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
            </div>
        </div>

        <div class="sm-stat">
            <div>
                <div class="sm-stat-label">Open</div>
                <div class="sm-stat-value"><?= (int) ($ticketStats['open_cnt'] ?? 0) ?></div>
            </div>
            <div class="sm-stat-icon ic-red" style="margin-left:auto;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
            </div>
        </div>

        <div class="sm-stat">
            <div>
                <div class="sm-stat-label">In Progress</div>
                <div class="sm-stat-value"><?= (int) ($ticketStats['in_progress_cnt'] ?? 0) ?></div>
            </div>
            <div class="sm-stat-icon ic-blue" style="margin-left:auto;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="23 4 23 10 17 10" />
                    <polyline points="1 20 1 14 7 14" />
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                </svg>
            </div>
        </div>

        <div class="sm-stat">
            <div>
                <div class="sm-stat-label">Resolved</div>
                <div class="sm-stat-value"><?= (int) ($ticketStats['resolved_cnt'] ?? 0) ?></div>
            </div>
            <div class="sm-stat-icon ic-green" style="margin-left:auto;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
        </div>

    </div>

    <div class="card">
        <div class="card-header-with-filters">
            <div class="header-left">
                Support Tickets
                <span
                    style="font-size:12px;font-weight:600;background:var(--hover,#f1f5f9);color:var(--text-soft,#666);border-radius:20px;padding:2px 10px;margin-left:8px;"
                    id="sptTicketCount"><?= $ticketTotal ?></span>
            </div>
            <div class="header-right">
                <div class="filter-bar" style="margin:0;">
                    <div style="position:relative;display:flex;align-items:center;">
                        <svg style="position:absolute;left:9px;opacity:.4;flex-shrink:0;" width="13" height="13"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" id="sptSearch" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search subject, user, category…" style="padding-left:28px;">
                    </div>

                    <!-- Custom status dropdown -->
                    <div class="inv-status-dropdown-wrap" id="sptStatusWrap">
                        <button type="button" class="inv-status-trigger" id="sptStatusTrigger"
                            onclick="toggleSptStatus()">
                            <span
                                id="sptStatusLabel"><?= $statusFilter === 'all' ? 'All Status' : ucwords(str_replace('_', ' ', $statusFilter)) ?></span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12" id="sptStatusChevron">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" id="sptStatusVal" value="<?= htmlspecialchars($statusFilter) ?>">
                        <div class="inv-status-menu" id="sptStatusMenu" style="display:none; right:0; left:auto;">
                            <button type="button" class="inv-status-opt <?= $statusFilter === 'all' ? 'active' : '' ?>"
                                data-value="all" onclick="selectSptStatus(this)">All Status</button>
                            <button type="button" class="inv-status-opt <?= $statusFilter === 'open' ? 'active' : '' ?>"
                                data-value="open" onclick="selectSptStatus(this)">
                                <span class="inv-status-dot" style="background:#ef4444;"></span>Open
                            </button>
                            <button type="button"
                                class="inv-status-opt <?= $statusFilter === 'in_progress' ? 'active' : '' ?>"
                                data-value="in_progress" onclick="selectSptStatus(this)">
                                <span class="inv-status-dot" style="background:#3b82f6;"></span>In Progress
                            </button>
                            <button type="button"
                                class="inv-status-opt <?= $statusFilter === 'resolved' ? 'active' : '' ?>"
                                data-value="resolved" onclick="selectSptStatus(this)">
                                <span class="inv-status-dot" style="background:#22c55e;"></span>Resolved
                            </button>
                            <button type="button"
                                class="inv-status-opt <?= $statusFilter === 'closed' ? 'active' : '' ?>"
                                data-value="closed" onclick="selectSptStatus(this)">
                                <span class="inv-status-dot" style="background:#6b7280;"></span>Closed
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:15px;overflow-x:auto;-webkit-overflow-scrolling:touch;">
            <table id="supportTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SUBJECT</th>
                        <th>USER</th>
                        <th>CATEGORY</th>
                        <th>PRIORITY</th>
                        <th>STATUS</th>
                        <th>MESSAGES</th>
                        <th>DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="sptTableBody">
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="table-empty">
                                    <div class="table-empty-text">No support tickets found.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $tk):
                            $tb = ticketBadge((string) ($tk['status'] ?? 'open'));
                            $pb = priorityBadge((string) ($tk['priority'] ?? 'medium'));
                            $nameParts = array_filter(explode(' ', trim($tk['user_name'] ?? '')));
                            $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice($nameParts, 0, 2)));
                            $photo = $tk['user_photo'] ?? '';
                            $search_val = strtolower(($tk['subject'] ?? '') . ' ' . ($tk['user_name'] ?? '') . ' ' . ($tk['category'] ?? ''));
                            ?>
                            <tr data-status="<?= htmlspecialchars($tk['status']) ?>"
                                data-search="<?= htmlspecialchars($search_val) ?>">
                                <td class="muted tkt-id">#TKT-<?= str_pad((string) $tk['ticket_id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="subject-cell">
                                    <strong><?= htmlspecialchars(mb_strimwidth($tk['subject'] ?? '', 0, 55, '…')) ?></strong>
                                    <span><?= htmlspecialchars(mb_strimwidth($tk['last_message'] ?? 'No messages yet', 0, 60, '…')) ?></span>
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <?php if ($photo): ?>
                                            <img src="../../<?= htmlspecialchars($photo) ?>" class="user-avatar-img"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div class="user-avatar" style="display:none;"><?= $initials ?></div>
                                        <?php else: ?>
                                            <div class="user-avatar"><?= $initials ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="user-name"><?= htmlspecialchars($tk['user_name'] ?? '—') ?></div>
                                            <div class="user-email"><?= htmlspecialchars($tk['user_email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="muted"><?= htmlspecialchars($tk['category'] ?? '—') ?></td>
                                <td><span class="badge <?= $pb['cls'] ?>"><?= $pb['label'] ?></span></td>
                                <td><span class="badge <?= $tb['cls'] ?>"><?= $tb['label'] ?></span></td>
                                <td class="muted" style="text-align:center;"><?= (int) $tk['msg_count'] ?></td>
                                <td class="muted" style="white-space:nowrap;">
                                    <?= date('M j, Y', strtotime($tk['created_at'])) ?></td>
                                <td>
                                    <div class="tbl-actions">
                                        <button class="btn-icon btn-edit"
                                            onclick="openTicketModal(<?= (int) $tk['ticket_id'] ?>, <?= htmlspecialchars(json_encode($tk), ENT_QUOTES) ?>)">
                                            View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot id="sptTableFoot" style="display:none;">
                    <tr>
                        <td colspan="9">
                            <div class="txn-pagination">
                                <span class="txn-page-info" id="sptPageInfo"></span>
                                <div class="txn-page-controls" id="sptPageControls" style="display:none;">
                                    <button type="button" id="sptPrevBtn" class="txn-chevron-btn"
                                        onclick="sptChangePage(-1)" disabled>
                                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"
                                            width="14" height="14">
                                            <polyline points="15 18 9 12 15 6" />
                                        </svg>
                                    </button>
                                    <span id="sptPageNumbers" class="txn-page-numbers"></span>
                                    <button type="button" id="sptNextBtn" class="txn-chevron-btn"
                                        onclick="sptChangePage(1)" disabled>
                                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"
                                            width="14" height="14">
                                            <polyline points="9 18 15 12 9 6" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="sptEmptyState" style="display:none;text-align:center;padding:52px 16px;">
            <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5" viewBox="0 0 24 24"
                style="margin:0 auto 12px;display:block;">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <div style="color:#aaa;font-size:14px;">No tickets match your filters.</div>
        </div>
    </div>

    <div class="sm-modal-overlay" id="ticketModal">
        <div class="sm-modal">
            <button class="sm-modal-close" onclick="closeModal('ticketModal')">✕</button>
            <div class="sm-modal-title" id="ticketModalTitle">Ticket Details</div>
            <div class="sm-modal-sub" id="ticketModalSub"></div>

            <div class="sm-detail-grid" id="ticketDetailGrid"></div>

            <hr class="sm-divider">

            <div class="sm-field-label">Conversation</div>
            <div class="sm-msg-thread" id="ticketMsgThread">
                <div style="text-align:center;color:#94a3b8;padding:20px;font-size:0.84rem;">Loading messages…</div>
            </div>

            <hr class="sm-divider">

            <div class="sm-field-label" style="margin-bottom:10px;">Reply &amp; Update Status</div>
            <div class="sm-reply-area">
                <textarea id="ticketReplyBody" placeholder="Type your reply to the guest…"></textarea>
                <div class="sm-status-row">
                    <select id="ticketStatusSelect">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button class="sm-action-btn" onclick="sendTicketReply()" style="margin-left:auto;">Send
                        Reply</button>
                    <button class="sm-action-btn" onclick="updateTicketStatus()">Update Status</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ADMIN_ID = <?= $adminId ?>;
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
</script>
<script src="../../assets/js/admin/support.js"></script>
<script src="../../assets/js/toast.js"></script>
<script>window.PS_RT_PAGE = 'support';</script>

<?php include '../../includes/layout_close.php'; ?>