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
require_once '../../lib/user-queries/payment_queries.php';

?>

<link rel="stylesheet" href="../../assets/css/user-css/payment.css">
<link rel="stylesheet" href="../../assets/css/receipt_modal.css">
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

                                // Emit the raw UTC datetime string — psDate() in datetime.js
                                // appends 'Z' if no tz suffix, giving correct local-time display.
                                $dateDisp = htmlspecialchars($row['date'] ?? '');
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
                                $unitLbl = trim((string) ($row['unit_label'] ?? ''));
                                $isInvoicePayment = $isPayment && str_starts_with($unitLbl, 'Invoice ');
                                $isBookingPayment = $isPayment && str_starts_with($unitLbl, 'Booking #');
                                if ($isInvoicePayment) {
                                    // Invoice payment: show invoice number
                                    $subLine = htmlspecialchars($unitLbl);
                                } elseif ($isBookingPayment) {
                                    // Booking payment: show "Booking #BK-000027 · 7 nights"
                                    $nights = (int) ($row['nights'] ?? 0);
                                    $nightsStr = $nights . ' night' . ($nights !== 1 ? 's' : '');
                                    $subLine = htmlspecialchars($unitLbl) . ' · ' . $nightsStr;
                                } elseif ($isRefund && $row['reason']) {
                                    $subLine = '<span title="' . htmlspecialchars($row['reason']) . '" class="refund-reason-col">' . htmlspecialchars($row['reason']) . '</span>';
                                } else {
                                    $subLine = htmlspecialchars($unitLbl);
                                }
                                ?>
                                <tr data-type="<?php echo $row['type']; ?>"
                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                    data-search="<?php echo htmlspecialchars($searchStr); ?>">

                                    <td style="white-space:nowrap;">
                                        <div class="ps-dt-date" data-date="<?php echo $dateDisp; ?>"
                                            style="font-size:.82rem;color:var(--ink-mid);"></div>
                                        <div class="ps-dt-time" data-date="<?php echo $dateDisp; ?>"
                                            style="font-size:.7rem;color:var(--ink-faint);"></div>
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

                                            <?php
                                            $isInvoiceRow = !empty($row['invoice_id']);
                                            $paymongoMethods = ['GCash', 'Maya', 'Bank Transfer'];

                                            if ($isInvoiceRow):
                                                // ── Invoice payment ──────────────────────────────────
                                                // Check if user already has any refund for this invoice
                                                $existingInvRefund = $invoiceRefundMap[$row['invoice_id']] ?? null;
                                                $invoiceCanRefund  = in_array($row['method'], $paymongoMethods)
                                                    && $row['status'] === 'paid'
                                                    && $existingInvRefund === null; // no existing refund of any status
                                            else:
                                                // ── Booking payment ──────────────────────────────────
                                                $bkChk = $conn->prepare("SELECT status FROM bookings WHERE booking_id = ? LIMIT 1");
                                                $bkChk->bind_param('i', $row['booking_id']);
                                                $bkChk->execute();
                                                $bkChkRow = $bkChk->get_result()->fetch_assoc();
                                                $bkChk->close();

                                                $refChk = $conn->prepare("SELECT refund_id FROM refunds WHERE booking_id = ? AND refund_status IN ('pending','processing','completed') LIMIT 1");
                                                $refChk->bind_param('i', $row['booking_id']);
                                                $refChk->execute();
                                                $alreadyRefunded = $refChk->get_result()->fetch_assoc();
                                                $refChk->close();

                                                $invoiceCanRefund = false;
                                                $canRefund = in_array($row['method'], $paymongoMethods)
                                                    && $row['status'] === 'paid'
                                                    && ($bkChkRow['status'] ?? '') === 'cancelled'
                                                    && !$alreadyRefunded;
                                            endif;
                                            ?>

                                            <?php if (!$isInvoiceRow): // booking receipt button ?>
                                                <button class="btn-secondary" style="font-size:.7rem;padding:5px 12px;"
                                                    onclick="openReceiptModal(<?php echo (int) $row['booking_id']; ?>)">
                                                    Receipt
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($isInvoiceRow && $invoiceCanRefund): ?>
                                                <!-- Invoice Refund button -->
                                                <button class="btn-secondary"
                                                    style="font-size:.7rem;padding:5px 12px;color:var(--terra);border-color:var(--terra);"
                                                    onclick="openInvoiceRefundModal(
                                                        <?php echo (int) $row['invoice_id']; ?>,
                                                        '<?php echo htmlspecialchars($row['property_name'] . ' · ' . $row['unit_label'], ENT_QUOTES); ?>',
                                                        '<?php echo number_format($row['amount'], 2); ?>'
                                                    )">
                                                    Refund
                                                </button>
                                            <?php elseif ($isInvoiceRow && $existingInvRefund): ?>
                                                <!-- Show current refund status if one exists -->
                                                <span style="font-size:.7rem;color:var(--ink-soft);white-space:nowrap;">
                                                    <?php echo match($existingInvRefund) {
                                                        'pending'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Refund pending',
                                                        'processing' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Refund processing',
                                                        'completed'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg>Refunded',
                                                        'rejected'   => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Refund rejected',
                                                        default      => ucfirst($existingInvRefund)
                                                    }; ?>
                                                </span>
                                            <?php elseif (!$isInvoiceRow && ($canRefund ?? false)): ?>
                                                <!-- Booking Refund button -->
                                                <button class="btn-secondary"
                                                    style="font-size:.7rem;padding:5px 12px;margin-left:4px;color:var(--terra);border-color:var(--terra);"
                                                    onclick="openRefundModal(
                                                        <?php echo (int) $row['booking_id']; ?>,
                                                        '<?php echo htmlspecialchars($row['property_name'] . ' · ' . $row['unit_label'], ENT_QUOTES); ?>',
                                                        '<?php echo number_format($row['amount'], 2); ?>'
                                                    )">
                                                    Refund
                                                </button>
                                            <?php endif; ?>

                                        <?php elseif ($row['processed_date']): ?>
                                            <span class="ps-dt-date"
                                                data-date="<?php echo htmlspecialchars($row['processed_date']); ?>"
                                                style="font-size:.72rem;color:var(--ink-faint);display:block;"></span>
                                            <span class="ps-dt-time"
                                                data-date="<?php echo htmlspecialchars($row['processed_date']); ?>"
                                                style="font-size:.7rem;color:var(--ink-faint);display:block;"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="txPaginationWrap" style="width:100%;overflow-x:hidden;"></div>
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
<script src="../../assets/js/user-js/refund.js"></script>
<script src="../../assets/js/receipt_modal.js"></script>
<script>window.PS_RT_PAGE = 'payment';</script>
<script>
    // Render payment history datetimes via datetime.js psDate() so UTC→local is correct.
    // Uses dedicated classes to avoid colliding with global .ps-date / .ps-time handlers.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ps-dt-date[data-date]').forEach(function (el) {
            const d = psDate(el.dataset.date);
            if (d) el.textContent = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        });
        document.querySelectorAll('.ps-dt-time[data-date]').forEach(function (el) {
            const d = psDate(el.dataset.date);
            if (d) el.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        });
    });
</script>

<!-- ── Refund Request Modal ─────────────────────────────────────────────── -->
<div id="refundModal" class="modal-overlay">
    <div class="modal-box" style="max-width:460px;">
        <button class="modal-close-btn" onclick="closeRefundModal()">✕</button>

        <div class="modal-title">Request a Refund</div>
        <div class="modal-sub" id="refundModalDesc"></div>

        <div class="form-field" style="margin-bottom:18px;">
            <label>Reason for refund <span style="color:var(--terra);">*</span></label>
            <textarea id="refundReason" placeholder="Please describe why you are requesting a refund…"></textarea>
        </div>

        <p style="font-size:.72rem;color:var(--ink-faint);line-height:1.6;margin:0 0 20px;">
            Requests are reviewed within 1–2 business days. You'll be notified by email once a decision is made.
        </p>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeRefundModal()">Cancel</button>
            <button class="btn-primary" id="refundSubmitBtn" onclick="submitRefundRequest()">
                Submit Request
            </button>
        </div>
    </div>
</div>

<?php require '../../includes/_layout_end.php'; ?>