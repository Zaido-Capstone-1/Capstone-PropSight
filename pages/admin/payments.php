<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/payments-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Payments';
$active_page = 'payments';
include '../../includes/db.php';
include '../../includes/layout_open.php';

function fmt_peso(float $v): string
{
    return '₱ ' . number_format($v, 0);
}


$filter_status = $_GET['status'] ?? 'all';
$filter_month = $_GET['month'] ?? ($_SESSION['payments_filter_month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}

if (isset($_GET['month'])) {
    $_SESSION['payments_filter_month'] = $filter_month;
}

$search = trim($_GET['q'] ?? '');

[$y, $m] = explode('-', $filter_month . '-01');
$y = (int) $y;
$m = (int) $m;

require_once '../../lib/admin-queries/payment_queries.php';

$pay_per_page = 10;
$pay_total_records = count($records);
$pay_total_pages = ceil($pay_total_records / $pay_per_page);
$pay_page = isset($_GET['pay_page']) ? max(1, min((int) $_GET['pay_page'], max(1, $pay_total_pages))) : 1;
$pay_offset = ($pay_page - 1) * $pay_per_page;
$pay_records = array_slice($records, $pay_offset, $pay_per_page);

// Build URL for pagination links
$pay_url_params = $_GET;
unset($pay_url_params['pay_page']);
$pay_base_url = '?' . http_build_query($pay_url_params) . (empty($pay_url_params) ? '' : '&');
?>

<link rel="stylesheet" href="../../assets/css/admin-css/payments.css">

<?php if (!empty($_SESSION['flash'])): ?>

    <?php unset($_SESSION['flash']);
endif;
?>
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Payments</h1>
            <p class="dash-subtitle">Track rent collections and payment status.</p>
        </div>
        <div class="dash-header-actions">
            <a href="../../api/admin/payments_export.php?<?= http_build_query($_GET) ?>" class="btn btn-outline">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Export CSV
            </a>
            <button class="btn btn-primary" onclick="openModal('add')">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Record Payment
            </button>
        </div>
    </div>

    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card sc-green">
                <div class="stat-card-left">
                    <div class="stat-label">Collected This Month</div>
                    <div class="stat-value"><?= fmt_peso((float) $stats['collected']) ?></div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-gold">
                <div class="stat-card-left">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= fmt_peso((float) $stats['pending_amt']) ?>
                        <span class="stat-trend neutral"><?= (int) $stats['pending_cnt'] ?> tenants</span>
                    </div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-card-left">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value"><?= fmt_peso((float) $stats['overdue_amt']) ?>
                        <span class="stat-trend down"><?= (int) $stats['overdue_cnt'] ?> tenants</span>
                    </div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-card-left">
                    <div class="stat-label">Collection Rate</div>
                    <div class="stat-value"><?= $collection_rate ?>%</div>
                </div>
                <div class="stat-icon-wrap blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="1" y="4" width="22" height="16" rx="2" />
                        <line x1="1" y1="10" x2="23" y2="10" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Collection Trend (6 months)</span></div>
            <div class="chart-wrap" style="height:180px;"><canvas id="collectionChart"></canvas></div>
        </div>

        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span class="card-title">Payment Records</span>
                </div>
                <form method="GET" id="filterForm" style="display:flex;align-items:center;gap:8px;">

                    <div style="position:relative;" id="monthPickerWrap">
                        <button type="button" id="monthPickerBtn" onclick="toggleMonthPicker()"
                            style="display:flex;align-items:center;gap:7px;padding:7px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--white);font-size:12.5px;font-weight:600;cursor:pointer;color:var(--text);white-space:nowrap;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                height="13">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            <span id="monthPickerLabel">
                                <?= date('F Y', strtotime($filter_month . '-01')) ?>
                            </span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11"
                                height="11">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" name="month" id="monthPickerValue"
                            value="<?= htmlspecialchars($filter_month) ?>">

                        <!-- Dropdown Calendar -->
                        <div id="monthPickerDropdown"
                            style="display:none;position:absolute;top:calc(100% + 6px);right:0;z-index:999;background:var(--white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.13);padding:16px;min-width:248px;">
                            <div
                                style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                <button type="button" onclick="changePickerYear(-1)"
                                    style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">‹</button>
                                <span id="pickerYear" style="font-size:13.5px;font-weight:700;color:var(--text);">
                                    <?= date('Y', strtotime($filter_month . '-01')) ?>
                                </span>
                                <button type="button" onclick="changePickerYear(1)"
                                    style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">›</button>
                            </div>
                            <div id="pickerMonthGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;">
                                <?php
                                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                $curPickerMonth = (int) date('m', strtotime($filter_month . '-01'));
                                $curPickerYear = (int) date('Y', strtotime($filter_month . '-01'));
                                foreach ($months as $i => $mon):
                                    $isActive = ($i + 1) === $curPickerMonth;
                                    ?>
                                    <button type="button" data-month="<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>"
                                        onclick="selectPickerMonth(this)"
                                        class="picker-month-btn<?= $isActive ? ' picker-active' : '' ?>"
                                        style="padding:6px 4px;border:1.5px solid <?= $isActive ? 'var(--primary,#3b6ef5)' : 'var(--border)' ?>;border-radius:7px;font-size:11.5px;font-weight:<?= $isActive ? '700' : '500' ?>;cursor:pointer;background:<?= $isActive ? 'var(--primary,#3b6ef5)' : 'var(--white)' ?>;color:<?= $isActive ? 'white' : 'var(--text)' ?>;transition:all .15s;">
                                        <?= $mon ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
                                <button type="button" onclick="closeMonthPicker()"
                                    style="padding:5px 11px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;background:none;cursor:pointer;color:var(--text-soft);">Cancel</button>
                                <button type="button" onclick="applyMonthPicker()"
                                    style="padding:5px 13px;border:none;border-radius:6px;font-size:12px;font-weight:600;background:var(--primary,#3b6ef5);color:white;cursor:pointer;">Apply</button>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div style="position:relative;flex-shrink:0;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                            height="13"
                            style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--text-soft);pointer-events:none;">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search tenant, unit, ID…"
                            style="padding:7px 10px 7px 28px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:12.5px;width:180px;background:var(--white);">
                    </div>

                    <!-- Status -->
                    <div class="inv-status-dropdown-wrap" id="payStatusDropdownWrap">
                        <button type="button" class="inv-status-trigger" id="payStatusTrigger"
                            onclick="togglePayStatusDropdown()">
                            <span id="payStatusTriggerLabel">
                                <?php
                                $labels = ['all' => 'All Status', 'paid' => 'Paid', 'pending' => 'Pending', 'late' => 'Overdue'];
                                echo $labels[$filter_status] ?? 'All Status';
                                ?>
                            </span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12" id="payStatusChevron">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" name="status" id="payStatusInput"
                            value="<?= htmlspecialchars($filter_status) ?>">
                        <div class="inv-status-menu" id="payStatusMenu" style="display:none; right:0; left:auto;">
                            <button type="button" class="inv-status-opt <?= $filter_status === 'all' ? 'active' : '' ?>"
                                data-value="all" onclick="selectPayStatusOpt(this)">All Status</button>
                            <button type="button"
                                class="inv-status-opt <?= $filter_status === 'paid' ? 'active' : '' ?>"
                                data-value="paid" onclick="selectPayStatusOpt(this)">
                                <span class="inv-status-dot paid"></span>Paid
                            </button>
                            <button type="button"
                                class="inv-status-opt <?= $filter_status === 'pending' ? 'active' : '' ?>"
                                data-value="pending" onclick="selectPayStatusOpt(this)">
                                <span class="inv-status-dot pending"></span>Pending
                            </button>
                            <button type="button"
                                class="inv-status-opt <?= $filter_status === 'late' ? 'active' : '' ?>"
                                data-value="late" onclick="selectPayStatusOpt(this)">
                                <span class="inv-status-dot overdue"></span>Overdue
                            </button>
                        </div>
                    </div>

                </form>
            </div><!-- /card-header -->


            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tenant / User</th>
                            <th>Unit</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pay_records)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:40px;color:var(--text-soft);">
                                    <?= empty($records) ? 'No payment records found.' : 'No records on this page.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $p):
                                $badge = $p['payment_status'] === 'paid' ? 'success' : ($p['payment_status'] === 'late' ? 'danger' : 'pending');
                                $label = $p['payment_status'] === 'paid' ? 'Paid' : ($p['payment_status'] === 'late' ? 'Overdue' : 'Pending');
                                $display_name = $p['full_name'] ?? $p['tenant_name'] ?? '—';
                                // Two-letter initials: first letter of each word (e.g. "Jr Marticio" → "JM")
                                $name_parts = explode(' ', trim($display_name));
                                $initial = strtoupper(mb_substr($name_parts[0] ?? '', 0, 1));
                                if (count($name_parts) > 1) {
                                    $initial .= strtoupper(mb_substr(end($name_parts), 0, 1));
                                }
                                $payment_num = '#PAY-' . str_pad($p['payment_id'], 3, '0', STR_PAD_LEFT);
                                $month_val = substr($p['payment_date'], 0, 7);
                                $search_val = strtolower($p['payment_id'] . ' ' . $display_name . ' ' . ($p['unit_number'] ?? ''));
                                $data_json = htmlspecialchars(json_encode($p), ENT_QUOTES);
                                ?>
                                <tr data-payment-id="<?= (int) $p['payment_id'] ?>"
                                    data-status="<?= htmlspecialchars($p['payment_status']) ?>"
                                    data-month="<?= htmlspecialchars($month_val) ?>"
                                    data-search="<?= htmlspecialchars($search_val) ?>">
                                    <td><strong><?= $payment_num ?></strong></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <?php if (!empty($p['profile_photo'])): ?>
                                                <img src="../../<?= htmlspecialchars($p['profile_photo']) ?>"
                                                    alt="<?= htmlspecialchars($initial) ?>"
                                                    style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;display:block;"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                <div style="display:none;width:36px;height:36px;border-radius:50%;flex-shrink:0;
                                                            background:linear-gradient(135deg,#0f2744,#1a3a6b);color:#e8c86a;
                                                            font-weight:700;font-size:0.82rem;
                                                            align-items:center;justify-content:center;"><?= $initial ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                                                            background:linear-gradient(135deg,#0f2744,#1a3a6b);color:#e8c86a;
                                                            font-weight:700;font-size:0.82rem;display:flex;
                                                            align-items:center;justify-content:center;"><?= $initial ?>
                                                </div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($display_name) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($p['unit_number'] ?? '—') ?></td>
                                    <td><?= $p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : '—' ?></td>
                                    <td style="font-weight:700;">
                                        <?= $p['amount_paid'] ? fmt_peso((float) $p['amount_paid']) : '—' ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['payment_method'] ?? '—') ?></td>
                                    <td><span class="badge badge-<?= $badge ?>"><?= $label ?></span></td>
                                    <td style="color:var(--text-soft);font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                        title="<?= htmlspecialchars($p['notes'] ?? '') ?>">
                                        <?= htmlspecialchars($p['notes'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;justify-content:center;">
                                            <button class="btn-icon btn-edit" title="Edit"
                                                onclick="openModal('edit', <?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                    width="15" height="15">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                            <button class="btn-icon btn-delete" title="Delete"
                                                onclick="confirmDelete(<?= (int) $p['payment_id'] ?>)">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                    width="15" height="15">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                    <path d="M10 11v6M14 11v6" />
                                                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div id="payEmptyState" style="display:none;text-align:center;padding:52px 16px;">
                    <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5" viewBox="0 0 24 24"
                        style="margin:0 auto 12px;display:block;">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <div style="color:#aaa;font-size:14px;">No payments match your filters.</div>
                </div>
            </div>

            <!-- Pagination outside table-wrap so it never scrolls horizontally -->
            <div id="payTableFoot" class="pay-pagination-wrap" style="display:none;">
                <div class="txn-pagination">
                    <span class="txn-page-info" id="payPageInfo"></span>
                    <div class="txn-page-controls" id="payPageControls" style="display:none;">
                        <button type="button" id="payPrevBtn" class="txn-chevron-btn" onclick="payChangePage(-1)"
                            disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                                height="14">
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                        </button>
                        <span id="payPageNumbers" class="txn-page-numbers"></span>
                        <button type="button" id="payNextBtn" class="txn-chevron-btn" onclick="payChangePage(1)"
                            disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                                height="14">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="paymentModal" class="modal-backdrop" style="display:none;" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Record Payment</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="paymentForm" method="POST" action="../../api/payments.php">
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="form_action" id="formAction" value="add">
            <input type="hidden" name="payment_id" id="formPaymentId" value="">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Tenant / Booking <span class="req">*</span></label>
                        <!-- Shown only in Record Payment (add) mode -->
                        <select name="booking_id" id="formBookingId" required
                            style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
                            <option value="">Select tenant</option>
                            <?php foreach ($booking_options as $b): ?>
                                <option value="<?= (int) $b['booking_id'] ?>">
                                    <?= htmlspecialchars($b['full_name']) ?> — <?= htmlspecialchars($b['unit_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Shown only in Edit mode -->
                        <div id="editTenantDisplay"
                            style="display:none;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);display:none;align-items:center;gap:8px;">
                            <img id="editTenantPhoto" src="" alt=""
                                style="width:28px;height:28px;border-radius:50%;object-fit:cover;display:none;">
                            <div id="editTenantInitial" class="avatar"
                                style="width:28px;height:28px;font-size:12px;flex-shrink:0;"></div>
                            <span id="editTenantName" style="font-weight:500;"></span>
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Payment Date <span class="req">*</span></label>
                        <input type="date" name="payment_date" id="formPaymentDate" required
                            value="<?= date('Y-m-d') ?>"
                            style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label>Amount Paid <span class="req">*</span></label>
                        <div style="position:relative;">
                            <span
                                style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-soft);font-size:13px;">₱</span>
                            <input type="number" name="amount_paid" id="formAmountPaid" step="0.01" min="0" required
                                placeholder="0.00"
                                style="width:100%;padding:9px 12px 9px 26px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;box-sizing:border-box;">
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Payment Method</label>
                        <select name="payment_method" id="formPaymentMethod"
                            style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
                            <option value="">Select</option>
                            <option>Cash</option>
                            <option>GCash</option>
                            <option>Maya</option>
                            <option>Bank Transfer</option>
                            <option>Check</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Status <span class="req">*</span></label>
                        <select name="payment_status" id="formPaymentStatus" required
                            style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="late">Overdue</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="formNotes" rows="2" placeholder="Optional notes…"
                        style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteModal()">
    <div class="modal-box" style="max-width:400px;">

        <div class="modal-header">
            <h3>Delete Payment</h3>
            <button class="modal-close" onclick="closeDeleteModal()">×</button>
        </div>

        <div class="modal-body">
            <p style="color:var(--text-soft);margin:0;">
                Are you sure you want to delete this payment record? This cannot be undone.
            </p>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>

            <button type="button" class="btn btn-danger" onclick="deletePayment()">
                Delete
            </button>
        </div>

        <input type="hidden" id="deletePaymentId">

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    window.__PS_PAYMENTS__ = window.__PS_DATA__ || {};
    window.__PS_PAYMENTS__.trendLabels = <?= json_encode($trend_labels) ?>;
    window.__PS_PAYMENTS__.trendCollected = <?= json_encode($trend_collected) ?>;
    window.__PS_PAYMENTS__.trendOutstanding = <?= json_encode($trend_outstanding) ?>;
</script>
<script>
    (function () {
        let pickerYear = <?= $curPickerYear ?>;
        let selectedMonth = '<?= str_pad($curPickerMonth, 2, '0', STR_PAD_LEFT) ?>';

        window.toggleMonthPicker = function () {
            const d = document.getElementById('monthPickerDropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        };
        window.closeMonthPicker = function () {
            document.getElementById('monthPickerDropdown').style.display = 'none';
        };
        window.changePickerYear = function (dir) {
            const newYear = pickerYear + dir;
            if (newYear < 2000 || newYear > new Date().getFullYear() + 1) return;
            pickerYear = newYear;
            document.getElementById('pickerYear').textContent = pickerYear;
        };
        window.selectPickerMonth = function (btn) {
            document.querySelectorAll('.picker-month-btn').forEach(b => {
                b.style.background = 'var(--white)';
                b.style.borderColor = 'var(--border)';
                b.style.color = 'var(--text)';
                b.style.fontWeight = '500';
            });
            btn.style.background = 'var(--primary,#3b6ef5)';
            btn.style.borderColor = 'var(--primary,#3b6ef5)';
            btn.style.color = 'white';
            btn.style.fontWeight = '700';
            selectedMonth = btn.dataset.month;
        };
        window.applyMonthPicker = function () {
            const val = pickerYear + '-' + selectedMonth;
            document.getElementById('monthPickerValue').value = val;
            const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
                'October', 'November', 'December'
            ];
            document.getElementById('monthPickerLabel').textContent = names[parseInt(selectedMonth) - 1] + ' ' + pickerYear;
            closeMonthPicker();
            applyPayFilters();
        };
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('monthPickerWrap');
            if (wrap && !wrap.contains(e.target)) closeMonthPicker();
        });
    })();
</script>
<script src="../../assets/js/admin/payments.js"></script>

<?php include '../../includes/layout_close.php'; ?>