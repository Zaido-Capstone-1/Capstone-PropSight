<?php
// includes/queries/refunds_queries.php
// Fetches all stats and refund rows needed by pages/admin/refunds.php

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(CASE WHEN refund_status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN refund_status = 'completed'
                    AND MONTH(processed_date) = MONTH(CURDATE())
                    AND YEAR(processed_date)  = YEAR(CURDATE())  THEN 1 END) AS approved_this_month,
        COALESCE(SUM(CASE WHEN refund_status = 'completed'
                           AND MONTH(processed_date) = MONTH(CURDATE())
                           AND YEAR(processed_date)  = YEAR(CURDATE())
                          THEN refund_amount END), 0) AS approved_amount,
        COUNT(CASE WHEN refund_status = 'rejected' THEN 1 END) AS rejected_count
    FROM refunds
")->fetch_assoc();

// ── All refund rows (JS handles filtering / pagination) ───────────────────────
$stmt = $conn->prepare("
    SELECT
        r.refund_id, r.booking_id, r.payment_id, r.refund_amount,
        r.refund_reason, r.refund_status, r.refund_method,
        r.refund_date, r.processed_date, r.admin_notes, r.created_at,
        u.first_name, u.last_name, u.email, u.profile_photo,
        un.unit_number
    FROM   refunds r
    JOIN   users      u  ON u.user_id   = r.user_id
    LEFT JOIN bookings b  ON b.booking_id = r.booking_id
    LEFT JOIN units    un ON un.unit_id   = b.unit_id
    ORDER BY
        CASE r.refund_status WHEN 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
");
$stmt->execute();
$refunds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Shape rows for JSON embedding ────────────────────────────────────────────
$js_rows = [];
foreach ($refunds as $r) {
    $fullName = trim($r['first_name'] . ' ' . $r['last_name']);
    $refId = '#REF-' . str_pad($r['refund_id'], 4, '0', STR_PAD_LEFT);
    $bkRef = 'BK-' . str_pad($r['booking_id'], 6, '0', STR_PAD_LEFT);
    $isPending = $r['refund_status'] === 'pending';
    $badgeClass = $r['refund_status'] === 'completed' ? 'badge-approved' : 'badge-' . $r['refund_status'];
    $statusLabel = $r['refund_status'] === 'completed' ? 'Approved' : ucfirst($r['refund_status']);

    $js_rows[] = [
        'refund_id' => $r['refund_id'],
        'booking_id' => $r['booking_id'],
        'refund_amount' => (float) $r['refund_amount'],
        'refund_reason' => $r['refund_reason'],
        'refund_status' => $r['refund_status'],
        'admin_notes' => $r['admin_notes'] ?? '',
        'processed_date' => $r['processed_date'] ?? '',
        'created_at' => $r['created_at'],
        'guest_name' => $fullName,
        'email' => $r['email'],
        'unit_number' => $r['unit_number'] ?? '—',
        'profile_photo' => !empty($r['profile_photo']) ? htmlspecialchars($r['profile_photo']) : '',
        'refId' => $refId,
        'bkRef' => $bkRef,
        'isPending' => $isPending,
        'badgeClass' => $badgeClass,
        'statusLabel' => $statusLabel,
        'initial' => strtoupper(mb_substr($fullName, 0, 1)),
    ];
}