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
include '../../lib/admin-queries/support_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/support.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Support Tickets</h1>
            <p class="dash-subtitle">Manage and respond to guest support tickets.</p>
        </div>
    </div>

    <div class="sm-stat-row">

        <div class="sm-stat">
            <div>
                <div class="sm-stat-label">Total Tickets</div>
                <div class="sm-stat-value" id="stat-spt-total"><?= (int) ($ticketStats['total'] ?? 0) ?></div>
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
                <div class="sm-stat-value" id="stat-spt-open"><?= (int) ($ticketStats['open_cnt'] ?? 0) ?></div>
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
                <div class="sm-stat-value" id="stat-spt-progress"><?= (int) ($ticketStats['in_progress_cnt'] ?? 0) ?>
                </div>
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
                <div class="sm-stat-value" id="stat-spt-resolved"><?= (int) ($ticketStats['resolved_cnt'] ?? 0) ?></div>
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
                            placeholder="Search subject, user, category…" style="padding-left:28px;height:30px;">
                    </div>

                    <!-- Custom status dropdown -->
                    <div class="inv-status-dropdown-wrap" id="sptStatusWrap">
                        <button type="button" class="inv-status-trigger" id="sptStatusTrigger"
                            onclick="toggleSptStatus()" style="height: 30px;">
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
                                    <?= date('M j, Y', strtotime($tk['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <button class="btn-icon btn-edit"
                                            onclick="openTicketModal(<?= (int) $tk['ticket_id'] ?>, <?= htmlspecialchars(json_encode($tk), ENT_QUOTES) ?>)">
                                            View
                                        </button>
                                        <button class="ts-btn ts-btn-delete"
                                            onclick="deleteTicket(<?= (int) $tk['ticket_id'] ?>)" title="Delete">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                width="13" height="13">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="sptTableFoot" style="display:none;border-top:1.5px solid var(--border);">
            <div class="txn-pagination">
                <span class="txn-page-info" id="sptPageInfo"></span>
                <div class="txn-page-controls" id="sptPageControls" style="display:none;">
                    <button type="button" id="sptPrevBtn" class="txn-chevron-btn" onclick="sptChangePage(-1)" disabled>
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                            height="14">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <span id="sptPageNumbers" class="txn-page-numbers"></span>
                    <button type="button" id="sptNextBtn" class="txn-chevron-btn" onclick="sptChangePage(1)" disabled>
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                            height="14">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="sptEmptyState" style="display:none;text-align:center;padding:52px 16px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" stroke-width="1.5"
                class="bi bi-ticket-perforated" viewBox="0 0 16 16" id="IconChangeColor">
                <path
                    d="M4 4.85v.9h1v-.9H4Zm7 0v.9h1v-.9h-1Zm-7 1.8v.9h1v-.9H4Zm7 0v.9h1v-.9h-1Zm-7 1.8v.9h1v-.9H4Zm7 0v.9h1v-.9h-1Zm-7 1.8v.9h1v-.9H4Zm7 0v.9h1v-.9h-1Z"
                    id="mainIconPathAttribute"></path>
                <path
                    d="M1.5 3A1.5 1.5 0 0 0 0 4.5V6a.5.5 0 0 0 .5.5 1.5 1.5 0 1 1 0 3 .5.5 0 0 0-.5.5v1.5A1.5 1.5 0 0 0 1.5 13h13a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 0-.5-.5 1.5 1.5 0 0 1 0-3A.5.5 0 0 0 16 6V4.5A1.5 1.5 0 0 0 14.5 3h-13ZM1 4.5a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v1.05a2.5 2.5 0 0 0 0 4.9v1.05a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-1.05a2.5 2.5 0 0 0 0-4.9V4.5Z"
                    id="mainIconPathAttribute"></path>
            </svg>
            <div style="color:#aaa;font-size:14px;" id="sptEmptyText">No tickets yet.</div>
        </div>
    </div>

    <div class="sm-modal-overlay" id="ticketModal">
        <div class="sm-modal">

            <div class="sm-modal-head">
                <div class="sm-modal-head-info">
                    <div class="sm-modal-head-avatar" id="ticketModalAvatar"></div>
                    <div class="sm-modal-head-text">
                        <div class="sm-modal-title" id="ticketModalTitle">Ticket Details</div>
                        <div class="sm-modal-sub" id="ticketModalSub"></div>
                    </div>
                </div>
                <button class="sm-modal-close" onclick="closeModal('ticketModal')" title="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="15"
                        height="15">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="sm-detail-grid" id="ticketDetailGrid"></div>

            <div class="sm-modal-body">

                <div>
                    <div class="sm-section-label">Conversation</div>
                    <div class="sm-msg-thread" id="ticketMsgThread">
                        <div style="text-align:center;color:var(--text-soft,#94a3b8);padding:20px;font-size:0.84rem;">
                            Loading messages…</div>
                    </div>
                </div>

                <div>
                    <div class="sm-section-label">Reply &amp; Update Status</div>
                    <div class="sm-reply-area">
                        <textarea id="ticketReplyBody" placeholder="Type your reply to the guest…" rows="3"></textarea>
                        <div class="sm-status-row">
                            <select id="ticketStatusSelect">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <button class="sm-action-btn btn-status" onclick="updateTicketStatus()">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <polyline points="23 4 23 10 17 10" />
                                    <polyline points="1 20 1 14 7 14" />
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                                </svg>
                                Update Status
                            </button>
                            <button class="sm-action-btn btn-send" onclick="sendTicketReply()">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="13"
                                    height="13">
                                    <line x1="22" y1="2" x2="11" y2="13" />
                                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                                </svg>
                                Send Reply
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="confirm-modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon">
                <svg fill="none" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2" />
                </svg>
            </div>
            <div class="confirm-modal-title">Delete Ticket?</div>
            <div class="confirm-modal-msg"><strong>This ticket and all its messages will be permanently
                    removed.</strong><br>This cannot be undone.</div>
            <div class="confirm-modal-actions">
                <button class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="confirm-btn-delete" id="confirmDeleteBtn">Delete</button>
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