<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html><html><body>
    <script src="../../assets/js/responsive.js"></script>
    </body></html>';
    exit;
}

$page_title = 'Refunds';
$active_page = 'refunds';

include '../../includes/db.php';
include '../../lib/admin-queries/refunds_queries.php';
include '../../includes/layout_open.php';

function fmt_peso(float $v): string
{
    return '₱ ' . number_format($v, 2);
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/payments.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/refunds.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Refunds</h1>
            <p class="dash-subtitle">Review and process guest refund requests.</p>
        </div>
    </div>

    <div class="cards-area">

        <!-- Stat Cards -->
        <div class="stat-row">
            <div class="stat-card sc-gold">
                <div class="stat-card-left">
                    <div class="stat-label">Pending Refunds</div>
                    <div class="stat-value"><?= (int) $stats['pending_count'] ?></div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-card-left">
                    <div class="stat-label">Approved This Month</div>
                    <div class="stat-value">
                        <?= fmt_peso((float) $stats['approved_amount']) ?>
                        <span class="stat-trend neutral"><?= (int) $stats['approved_this_month'] ?> requests</span>
                    </div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-card-left">
                    <div class="stat-label">Rejected</div>
                    <div class="stat-value"><?= (int) $stats['rejected_count'] ?></div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Refund Requests Table -->
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span class="card-title">Refund Requests</span>
                    <?php if ((int) $stats['pending_count'] > 0): ?>
                        <span class="record-count" id="pendingBadge"><?= (int) $stats['pending_count'] ?> pending</span>
                    <?php endif; ?>
                </div>

                <!-- Dynamic filter controls (no form / no page reload) -->
                <div style="display:flex;align-items:center;gap:8px;">
                    <!-- Search -->
                    <div style="position:relative;flex-shrink:0;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                            height="13"
                            style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text-soft);pointer-events:none;">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" id="refSearchInput" placeholder="Search guest, booking ID…"
                            style="padding:7px 10px 7px 28px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:12.5px;width:190px;background:var(--white);">
                    </div>

                    <!-- Status filter -->
                    <div class="inv-status-dropdown-wrap" id="refStatusWrap">
                        <button type="button" class="inv-status-trigger" onclick="toggleRefStatus()">
                            <span id="refStatusLabel">All Status</span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div class="inv-status-menu" id="refStatusMenu" style="display:none;right:0;left:auto;">
                            <?php foreach (['all' => 'All Status', 'pending' => 'Pending', 'completed' => 'Approved', 'rejected' => 'Rejected'] as $val => $lbl): ?>
                                <button type="button" class="inv-status-opt <?= $val === 'all' ? 'active' : '' ?>"
                                    data-value="<?= $val ?>" onclick="selectRefStatus(this)">
                                    <?php if ($val !== 'all'): ?>
                                        <span
                                            class="inv-status-dot <?= $val === 'completed' ? 'paid' : ($val === 'pending' ? 'pending' : 'overdue') ?>"></span>
                                    <?php endif; ?>
                                    <?= $lbl ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Guest</th>
                            <th>Unit</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="refundTableBody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8">
                                <div class="txn-pagination">
                                    <span class="txn-page-info" id="refPageInfo"></span>
                                    <div class="txn-page-controls" id="refPageControls"></div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal-backdrop" style="display:none;"
    onclick="if(event.target===this)closeApproveModal()">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3>Approve Refund</h3>
            <button class="modal-close" onclick="closeApproveModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-soft);margin:0 0 16px;">
                You are about to approve a refund of <strong id="approveAmount"></strong> for
                <strong id="approveGuest"></strong> (<span id="approveBkRef"></span>).
            </p>
            <p style="color:var(--text-soft);margin:0;font-size:13px;">
                This will trigger the PayMongo refund and notify the guest by email.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeApproveModal()">Cancel</button>
            <button class="btn btn-primary" id="approveSubmitBtn" onclick="submitApprove()">Confirm Approve</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-backdrop" style="display:none;" onclick="if(event.target===this)closeRejectModal()">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3>Reject Refund</h3>
            <button class="modal-close" onclick="closeRejectModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-soft);margin:0 0 16px;">
                Rejecting refund of <strong id="rejectAmount"></strong> for
                <strong id="rejectGuest"></strong> (<span id="rejectBkRef"></span>).
            </p>
            <div class="form-group">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">
                    Reason for rejection <span style="color:var(--terra,#e07043);">*</span>
                </label>
                <textarea id="rejectReason" rows="3" placeholder="Explain why this refund is being rejected…"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeRejectModal()">Cancel</button>
            <button class="btn btn-danger" id="rejectSubmitBtn" onclick="submitReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<script>
    window.ALL_REFUNDS = <?= json_encode($js_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../../assets/js/admin/refunds.js"></script>

<?php include '../../includes/layout_close.php'; ?>