<?php
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