<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><head></head><body></body></html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));

$page_title = 'Payment History';
$page_hero_html = 'Payment <em>History</em>';
$page_hero_sub = 'A full log of all your transactions and booking payments.';
$page_hero_icon = '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>';
$active_nav = 'payment';
require '../../includes/_layout.php';

require_once '../../includes/db.php';
$userId = (int) $_SESSION['user_id'];

/* ── Payment logs ── */
$bRes = mysqli_query($conn, "
    SELECT py.payment_id, py.payment_date, py.amount_paid, py.payment_method, py.payment_status,
           COALESCE(u.unit_name, u.unit_number, '—') AS unit_label,
           DATEDIFF(b.checkout_date, b.checkin_date)  AS nights,
           p.property_name
    FROM payments py
    JOIN bookings    b  ON b.booking_id  = py.booking_id
    JOIN units       u  ON u.unit_id     = b.unit_id
    LEFT JOIN properties p ON p.property_id = u.property_id
    WHERE b.user_id = $userId
    ORDER BY py.payment_date DESC
    LIMIT 100
");
$bills = [];
$total_spent = 0;
$paid_count = 0;
$pending_count = 0;
while ($r = mysqli_fetch_assoc($bRes)) {
    $bills[] = $r;
    if ($r['payment_status'] === 'paid') {
        $total_spent += $r['amount_paid'];
        $paid_count++;
    }
    if ($r['payment_status'] === 'pending') {
        $pending_count++;
    }
}

/* ── Refunds ── */
$refundsRes = mysqli_query($conn, "
    SELECT r.refund_id, r.refund_amount, r.refund_reason, r.refund_status,
           r.refund_method, r.refund_date, r.processed_date, r.created_at,
           COALESCE(u.unit_name, u.unit_number, '—') AS unit_label,
           p.property_name, py.payment_id, py.payment_method
    FROM refunds r
    JOIN payments py ON py.payment_id = r.payment_id
    LEFT JOIN bookings b ON b.booking_id = r.booking_id
    LEFT JOIN units u ON u.unit_id = b.unit_id
    LEFT JOIN properties p ON p.property_id = u.property_id
    WHERE r.user_id = $userId
    ORDER BY r.created_at DESC
    LIMIT 100
");
$refunds = [];
$total_refunded = 0;
$pending_refunds = 0;
while ($r = mysqli_fetch_assoc($refundsRes)) {
    $refunds[] = $r;
    if ($r['refund_status'] === 'completed') {
        $total_refunded += $r['refund_amount'];
    }
    if ($r['refund_status'] === 'pending') {
        $pending_refunds++;
    }
}

/* ── Build unified timeline (payments + refunds), newest first ── */
$unified = [];
foreach ($bills as $b) {
    $unified[] = [
        'type' => 'payment',
        'sort_date' => $b['payment_date'],
        'date' => $b['payment_date'],
        'property_name' => $b['property_name'] ?? '—',
        'unit_label' => $b['unit_label'] ?? '',
        'nights' => (int) ($b['nights'] ?? 0),
        'method' => $b['payment_method'] ?: 'N/A',
        'amount' => $b['amount_paid'],
        'status' => $b['payment_status'],
        'payment_id' => $b['payment_id'],
        'reason' => null,
        'processed_date' => null,
    ];
}
foreach ($refunds as $rf) {
    $unified[] = [
        'type' => 'refund',
        'sort_date' => $rf['created_at'],
        'date' => $rf['created_at'],
        'property_name' => $rf['property_name'] ?? '—',
        'unit_label' => $rf['unit_label'] ?? '',
        'nights' => null,
        'method' => $rf['refund_method'] ?: ($rf['payment_method'] ?: 'N/A'),
        'amount' => $rf['refund_amount'],
        'status' => $rf['refund_status'],
        'payment_id' => $rf['payment_id'],
        'reason' => $rf['refund_reason'],
        'processed_date' => $rf['processed_date'],
    ];
}
usort($unified, fn($a, $b) => strtotime($b['sort_date']) - strtotime($a['sort_date']));

/* ── Spending by method (sidebar chart) ── */
$methodTotals = [];
foreach ($bills as $b) {
    if ($b['payment_status'] !== 'paid')
        continue;
    $m = $b['payment_method'] ?: 'Other';
    $methodTotals[$m] = ($methodTotals[$m] ?? 0) + $b['amount_paid'];
}
arsort($methodTotals);
?>

<link rel="stylesheet" href="../../assets/css/user-css/payment.css">
<div class="page-two-col">

    <!-- ════════════ MAIN COLUMN ════════════ -->
    <div class="col-main">

        <!-- Stats -->
        <div class="pay-stats-row reveal">
            <div class="pay-stat-card accent">
                <div class="pay-stat-label">Total Spent</div>
                <div class="pay-stat-value">₱<?php echo number_format($total_spent, 0); ?></div>
                <div class="pay-stat-sub"><?php echo $paid_count; ?> Confirmed
                    Payment<?php echo $paid_count !== 1 ? 's' : ''; ?></div>
            </div>
            <div class="pay-stat-card">
                <div class="pay-stat-label">Transactions</div>
                <div class="pay-stat-value"><?php echo count($bills); ?></div>
                <div class="pay-stat-sub">Across all bookings</div>
            </div>
            <div class="pay-stat-card">
                <div class="pay-stat-label">Pending</div>
                <div class="pay-stat-value"
                    style="color:<?php echo $pending_count > 0 ? 'var(--terra)' : 'var(--navy-800)'; ?>">
                    <?php echo $pending_count; ?>
                </div>
                <div class="pay-stat-sub">Awaiting Confirmation</div>
            </div>
        </div>

        <!-- Unified Transaction Log -->
        <div class="card reveal rd1">
            <div class="card-title" style="font-size: 22px">
                <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                </svg>
                Transaction History
            </div>

            <div class="billing-filters">

                <!-- right-hand group: dropdowns + search fused together -->
                <div class="filter-bar-group">

                    <!-- Type dropdown -->
                    <div class="fdd-wrap" id="fddTypeWrap">
                        <button class="fdd-trigger" id="fddTypeBtn" type="button" onclick="toggleFdd('Type')"
                            aria-haspopup="listbox" aria-expanded="false">
                            <span class="fdd-label" id="fddTypeLabel">All Types</span>
                            <svg class="fdd-chevron" viewBox="0 0 24 24">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul class="fdd-menu" id="fddTypeMenu" role="listbox">
                            <li class="fdd-option selected" data-val="all" onclick="pickFdd('Type','all','All Types')">
                                All Types</li>
                            <li class="fdd-option" data-val="payment" onclick="pickFdd('Type','payment','Payments')">
                                Payments</li>
                            <li class="fdd-option" data-val="refund" onclick="pickFdd('Type','refund','Refunds')">
                                Refunds</li>
                        </ul>
                    </div>

                    <div class="fdd-sep"></div>

                    <!-- Status dropdown -->
                    <div class="fdd-wrap" id="fddStatusWrap">
                        <button class="fdd-trigger" id="fddStatusBtn" type="button" onclick="toggleFdd('Status')"
                            aria-haspopup="listbox" aria-expanded="false">
                            <span class="fdd-label" id="fddStatusLabel">Any Status</span>
                            <svg class="fdd-chevron" viewBox="0 0 24 24">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <ul class="fdd-menu" id="fddStatusMenu" role="listbox">
                            <li class="fdd-option selected" data-val="all"
                                onclick="pickFdd('Status','all','Any Status')">Any Status</li>
                            <li class="fdd-option" data-val="paid" onclick="pickFdd('Status','paid','Paid')">Paid</li>
                            <li class="fdd-option" data-val="pending" onclick="pickFdd('Status','pending','Pending')">
                                Pending</li>
                            <li class="fdd-option" data-val="completed"
                                onclick="pickFdd('Status','completed','Refunded')">Refunded</li>
                            <li class="fdd-option" data-val="processing"
                                onclick="pickFdd('Status','processing','Processing')">Processing</li>
                            <li class="fdd-option" data-val="failed" onclick="pickFdd('Status','failed','Failed')">
                                Failed</li>
                        </ul>
                    </div>

                    <div class="fdd-sep"></div>

                    <!-- Search -->
                    <div class="fdd-search">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" id="billingSearch" placeholder="Search…" oninput="filterUnified()">
                    </div>

                </div><!-- /filter-bar-group -->

                <!-- hidden inputs consumed by filterUnified() -->
                <input type="hidden" id="filterType" value="all">
                <input type="hidden" id="filterStatus" value="all">

            </div>

            <div style="overflow-x:auto;">
                <table class="billing-table" id="billingTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Property / Room</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unified)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:var(--ink-soft);">
                                    <svg viewBox="0 0 24 24"
                                        style="width:38px;height:38px;stroke:var(--border);fill:none;stroke-width:1.5;display:block;margin:0 auto 10px;">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                    </svg>
                                    No transaction records yet.
                                </td>
                            </tr>
                        <?php else:
                            foreach ($unified as $row):
                                $isPayment = $row['type'] === 'payment';
                                $isRefund = $row['type'] === 'refund';

                                $dateDisp = date('M j, Y', strtotime($row['date']));
                                $timeDisp = date('g:i A', strtotime($row['date']));
                                $searchStr = strtolower(
                                    ($row['property_name'] ?? '') . ' ' .
                                    ($row['unit_label'] ?? '') . ' ' .
                                    ($row['method'] ?? '') . ' ' .
                                    ($row['reason'] ?? '') . ' ' .
                                    $row['type']
                                );

                                /* Status badge */
                                $status = $row['status'];
                                $badgeCls = match ($status) {
                                    'paid', 'completed' => 'badge-green',
                                    'pending', 'processing' => 'badge-gold',
                                    default => 'badge-red'
                                };
                                $statusLabel = match ($status) {
                                    'completed' => 'Refunded',
                                    default => ucfirst($status)
                                };

                                /* Type badge */
                                if ($isPayment) {
                                    $typePill = '<span class="type-pill type-payment">Payment</span>';
                                } else {
                                    $typePill = '<span class="type-pill type-refund">Refund</span>';
                                }

                                /* Amount sign */
                                $amountHtml = $isRefund
                                    ? '<span style="color:var(--terra);">−₱' . number_format($row['amount'], 2) . '</span>'
                                    : '₱' . number_format($row['amount'], 2);

                                /* Sub-line under property */
                                if ($isPayment && $row['nights'] !== null) {
                                    $nights = $row['nights'];
                                    $subLine = htmlspecialchars($row['unit_label']) . ' · ' . $nights . ' night' . ($nights !== 1 ? 's' : '');
                                } elseif ($isRefund && $row['reason']) {
                                    $subLine = '<span title="' . htmlspecialchars($row['reason']) . '" class="refund-reason-col">' . htmlspecialchars($row['reason']) . '</span>';
                                } else {
                                    $subLine = htmlspecialchars($row['unit_label']);
                                }
                                ?>
                                <tr data-type="<?php echo $row['type']; ?>"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                    data-search="<?php echo htmlspecialchars($searchStr); ?>">

                                    <td style="white-space:nowrap;">
                                        <div style="font-size:.82rem;color:var(--ink-mid);"><?php echo $dateDisp; ?></div>
                                        <div style="font-size:.7rem;color:var(--ink-faint);"><?php echo $timeDisp; ?></div>
                                    </td>

                                    <td><?php echo $typePill; ?></td>

                                    <td style="max-width:200px;">
                                        <div
                                            style="font-weight:600;font-size:.82rem;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo htmlspecialchars($row['property_name']); ?>
                                        </div>
                                        <div style="font-size:.72rem;color:var(--ink-soft);"><?php echo $subLine; ?></div>
                                    </td>

                                    <td>
                                        <span class="method-pill"><?php echo htmlspecialchars($row['method']); ?></span>
                                    </td>

                                    <td class="bt-amount"><?php echo $amountHtml; ?></td>

                                    <td>
                                        <span class="badge <?php echo $badgeCls; ?>"><?php echo $statusLabel; ?></span>
                                    </td>

                                    <td>
                                        <?php if ($isPayment): ?>
                                            <button class="btn-secondary" style="font-size:.7rem;padding:5px 12px;"
                                                onclick="downloadInvoice(<?php echo $row['payment_id']; ?>,this)">
                                                Invoice
                                            </button>
                                        <?php elseif ($row['processed_date']): ?>
                                            <span style="font-size:.72rem;color:var(--ink-faint);">
                                                <?php echo date('M j, Y', strtotime($row['processed_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div class="billing-empty" id="billingEmpty" style="display:none;">No transactions match your filter.
                </div>
            </div>
        </div>

    </div><!-- /col-main -->

    <div class="col-side">
        <?php if (!empty($methodTotals)): ?>
            <div class="widget-card reveal">
                <div class="widget-title">
                    <svg viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                    Spending by Method
                </div>
                <?php $maxSpend = max($methodTotals);
                foreach ($methodTotals as $mName => $mAmt): ?>
                    <div class="spend-bar-row">
                        <div class="spend-bar-label" title="<?php echo htmlspecialchars($mName); ?>">
                            <?php echo htmlspecialchars($mName); ?>
                        </div>
                        <div class="spend-bar-track">
                            <div class="spend-bar-fill" style="width:<?php echo round(($mAmt / $maxSpend) * 100); ?>%"></div>
                        </div>
                        <div class="spend-bar-amount">₱<?php echo number_format($mAmt, 0); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="widget-card reveal rd1">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <rect x="1" y="4" width="22" height="16" rx="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
                Summary
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total paid</span>
                <span class="mini-stat-val"
                    style="color:var(--navy-700);">₱<?php echo number_format($total_spent, 2); ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total refunded</span>
                <span class="mini-stat-val"
                    style="color:var(--terra);">₱<?php echo number_format($total_refunded, 2); ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Paid transactions</span>
                <span class="mini-stat-val"><?php echo $paid_count; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Pending payments</span>
                <span class="mini-stat-val"
                    style="color:<?php echo $pending_count > 0 ? 'var(--terra)' : 'inherit'; ?>">
                    <?php echo $pending_count; ?>
                </span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Pending refunds</span>
                <span class="mini-stat-val"
                    style="color:<?php echo $pending_refunds > 0 ? 'var(--terra)' : 'inherit'; ?>">
                    <?php echo $pending_refunds; ?>
                </span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total records</span>
                <span class="mini-stat-val"><?php echo count($unified); ?></span>
            </div>
        </div>

        <div class="widget-card reveal rd2" style="background:var(--navy-50);border-color:var(--navy-200);">
            <div class="widget-title" style="margin-bottom:8px;">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                About This Page
            </div>
            <p style="font-size:.76rem;color:var(--ink-soft);line-height:1.6;">
                This log shows all payments and refunds linked to your bookings. Refund amounts are shown in red.
                For disputes or billing questions, please contact our support team.
            </p>
        </div>

    </div><!-- /col-side -->

</div><!-- /page-two-col -->

<style>
    
</style>

<script src="../../assets/js/user-js/payment.js"></script>

<script>window.PS_RT_PAGE = 'payment';</script>
<?php require '../../includes/_layout_end.php'; ?>