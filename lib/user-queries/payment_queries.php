<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

/* ── Booking payments ── */
$bRes = mysqli_query($conn, "
    SELECT py.payment_id, py.payment_date, py.amount_paid, py.payment_method, py.payment_status,
        b.booking_id,
        NULL           AS invoice_id,
        CONCAT('Booking #BK-', LPAD(b.booking_id, 6, '0')) AS unit_label,
        DATEDIFF(b.checkout_date, b.checkin_date)  AS nights,
        p.property_name,
        COALESCE(pp.paid_at, py.created_at) AS display_datetime,
        COALESCE(pp.paid_at, py.created_at) AS sort_datetime
    FROM payments py
    JOIN bookings    b  ON b.booking_id  = py.booking_id
    JOIN units       u  ON u.unit_id     = b.unit_id
    LEFT JOIN properties p ON p.property_id = u.property_id
    LEFT JOIN paymongo_payments pp ON pp.booking_id = py.booking_id AND pp.status = 'paid'
    WHERE b.user_id = $userId
    ORDER BY sort_datetime DESC
    LIMIT 100
");

/* ── Invoice payments ── */
// Carries invoice_id so the refund button can reference it directly.
$invPayRes = mysqli_query($conn, "
    SELECT
        pp.id          AS payment_id,
        pp.paid_at     AS payment_date,
        pp.amount      AS amount_paid,
        CASE
            WHEN pp.payment_method != '' AND pp.payment_method IS NOT NULL THEN pp.payment_method
            WHEN pp.paymongo_link_id LIKE 'gcash%' THEN 'gcash'
            WHEN pp.paymongo_link_id LIKE 'maya%'  THEN 'maya'
            WHEN pp.paymongo_link_id LIKE 'card%'  THEN 'card'
            ELSE ''
        END AS payment_method,
        'paid'         AS payment_status,
        NULL           AS booking_id,
        i.id           AS invoice_id,
        CONCAT('Invoice ', i.invoice_no) AS unit_label,
        NULL           AS nights,
        'Boracay Accommodation' AS property_name,
        pp.paid_at     AS sort_datetime,
        pp.paid_at     AS display_datetime
    FROM paymongo_payments pp
    JOIN invoices i ON i.id = pp.reference_id
    JOIN tenants  t ON t.tenant_id = i.tenant_id
    JOIN users    u ON u.email = t.email
    WHERE pp.reference_type = 'invoice'
      AND pp.status = 'paid'
      AND u.user_id = $userId
    ORDER BY pp.paid_at DESC
    LIMIT 100
");

$bills         = [];
$total_spent   = 0;
$paid_count    = 0;
$pending_count = 0;

while ($r = mysqli_fetch_assoc($bRes)) {
    $bills[] = $r;
    if ($r['payment_status'] === 'paid')    { $total_spent += $r['amount_paid']; $paid_count++; }
    if ($r['payment_status'] === 'pending') { $pending_count++; }
}
if ($invPayRes) {
    while ($r = mysqli_fetch_assoc($invPayRes)) {
        $bills[] = $r;
        $total_spent += (float) $r['amount_paid'];
        $paid_count++;
    }
}

/* ── Refunds ── */
// Handles both booking refunds (joins payments table) and invoice refunds (invoice_id set).
$refundsRes = mysqli_query($conn, "
    SELECT r.refund_id, r.refund_amount, r.refund_reason, r.refund_status,
           r.refund_method, r.refund_date, r.processed_date, r.created_at,
           r.booking_id, r.invoice_id,
           COALESCE(u2.unit_name, u2.unit_number, '—') AS unit_label,
           p.property_name,
           py.payment_id, py.payment_method
    FROM refunds r
    LEFT JOIN payments   py ON py.payment_id  = r.payment_id
    LEFT JOIN bookings   b  ON b.booking_id   = r.booking_id
    LEFT JOIN units      u2 ON u2.unit_id     = b.unit_id
    LEFT JOIN properties p  ON p.property_id  = u2.property_id
    WHERE r.user_id = $userId
    ORDER BY r.created_at DESC
    LIMIT 100
");
$refunds        = [];
$total_refunded = 0;
$pending_refunds = 0;
while ($r = mysqli_fetch_assoc($refundsRes)) {
    $refunds[] = $r;
    if ($r['refund_status'] === 'completed') { $total_refunded  += $r['refund_amount']; }
    if ($r['refund_status'] === 'pending')   { $pending_refunds++; }
}

/* ── Normalize payment method display label ── */
function normalizeMethod(string $m): string
{
    $map = [
        'gcash'     => 'GCash',
        'paymaya'   => 'Maya',
        'maya'      => 'Maya',
        'paymongo'  => 'PayMongo',
        'bank'      => 'Bank',
        'cash'      => 'Cash',
        'card'      => 'Card',
    ];
    return $map[strtolower(trim($m))] ?? ucfirst($m);
}

/* ── Pre-fetch refund status for all invoice IDs in one query ── */
// Avoids N+1 queries in the template loop below.
$invoiceIdsInBills = array_filter(array_column($bills, 'invoice_id'));
$invoiceRefundMap  = []; // invoice_id => refund_status (worst/latest)
if (!empty($invoiceIdsInBills)) {
    $placeholders = implode(',', array_fill(0, count($invoiceIdsInBills), '?'));
    $types = str_repeat('i', count($invoiceIdsInBills));
    $rfStmt = $conn->prepare("
        SELECT invoice_id, refund_status FROM refunds
        WHERE  invoice_id IN ($placeholders)
          AND  user_id = ?
        ORDER BY created_at DESC
    ");
    $bindArgs = array_merge([$types . 'i'], $invoiceIdsInBills, [$userId]);
    $refs = array_map(fn($v) => $v, $bindArgs);
    call_user_func_array([$rfStmt, 'bind_param'], $refs);
    $rfStmt->execute();
    $rfResult = $rfStmt->get_result();
    while ($rfRow = $rfResult->fetch_assoc()) {
        // Only record the first (latest) per invoice_id
        if (!isset($invoiceRefundMap[$rfRow['invoice_id']])) {
            $invoiceRefundMap[$rfRow['invoice_id']] = $rfRow['refund_status'];
        }
    }
    $rfStmt->close();
}

/* ── Build unified timeline (payments + refunds) newest-first ── */
$unified = [];
foreach ($bills as $b) {
    $unified[] = [
        'type'           => 'payment',
        'sort_date'      => $b['sort_datetime'],
        'date'           => $b['display_datetime'] ?? $b['sort_datetime'] ?? $b['payment_date'],
        'property_name'  => $b['property_name'] ?? '—',
        'unit_label'     => $b['unit_label'] ?? '',
        'nights'         => (int) ($b['nights'] ?? 0),
        'method'         => $b['payment_method'] ? normalizeMethod($b['payment_method']) : 'N/A',
        'amount'         => $b['amount_paid'],
        'status'         => $b['payment_status'],
        'payment_id'     => $b['payment_id'],
        'booking_id'     => $b['booking_id'],
        'invoice_id'     => $b['invoice_id'],        // NEW — null for booking rows
        'reason'         => null,
        'processed_date' => null,
    ];
}
foreach ($refunds as $rf) {
    $unified[] = [
        'type'           => 'refund',
        'sort_date'      => $rf['created_at'],
        'date'           => $rf['created_at'],
        'property_name'  => $rf['property_name'] ?? '—',
        'unit_label'     => $rf['unit_label'] ?? '',
        'nights'         => null,
        'method'         => $rf['refund_method']
                            ? normalizeMethod($rf['refund_method'])
                            : ($rf['payment_method'] ? normalizeMethod($rf['payment_method']) : 'N/A'),
        'amount'         => $rf['refund_amount'],
        'status'         => $rf['refund_status'],
        'payment_id'     => $rf['payment_id'],
        'booking_id'     => $rf['booking_id'] ?? null,
        'invoice_id'     => $rf['invoice_id'] ?? null,
        'reason'         => $rf['refund_reason'],
        'processed_date' => $rf['processed_date'],
    ];
}
usort($unified, fn($a, $b) => strtotime($b['sort_date']) - strtotime($a['sort_date']));

/* ── Spending by method (sidebar chart) ── */
$methodTotals = [];
foreach ($bills as $b) {
    if ($b['payment_status'] !== 'paid') continue;
    $m = $b['payment_method'] ? normalizeMethod($b['payment_method']) : 'Other';
    $methodTotals[$m] = ($methodTotals[$m] ?? 0) + $b['amount_paid'];
}
arsort($methodTotals);