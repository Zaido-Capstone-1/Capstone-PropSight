<?php
// ── Stat cards for selected month ($y, $m come from payments.php) ─────────────
$statsStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN payment_status = 'paid'    THEN amount_paid END), 0) AS collected,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount_paid END), 0) AS pending_amt,
        COALESCE(SUM(CASE WHEN payment_status = 'late'    THEN amount_paid END), 0) AS overdue_amt,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) AS pending_cnt,
        COUNT(CASE WHEN payment_status = 'late'    THEN 1 END) AS overdue_cnt,
        COUNT(*)                                                AS total_cnt,
        COUNT(CASE WHEN payment_status = 'paid'    THEN 1 END) AS paid_cnt
    FROM payments
    WHERE YEAR(payment_date) = ? AND MONTH(payment_date) = ?
");
$statsStmt->bind_param('ii', $y, $m);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?? [
    'collected' => 0,
    'pending_amt' => 0,
    'overdue_amt' => 0,
    'pending_cnt' => 0,
    'overdue_cnt' => 0,
    'total_cnt' => 0,
    'paid_cnt' => 0,
];
$statsStmt->close();

$collection_rate = $stats['total_cnt'] > 0
    ? round(($stats['paid_cnt'] / $stats['total_cnt']) * 100)
    : 0;

// ── 6-month collection trend (no user input) ──────────────────────────────────
$trend_rows = $conn->query("
    SELECT
        DATE_FORMAT(payment_date, '%b') AS mo,
        YEAR(payment_date)              AS yr,
        MONTH(payment_date)             AS mn,
        COALESCE(SUM(CASE WHEN payment_status = 'paid'               THEN amount_paid END), 0) AS collected,
        COALESCE(SUM(CASE WHEN payment_status IN ('pending', 'late') THEN amount_paid END), 0) AS outstanding
    FROM payments
    WHERE payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY yr, mn, mo
    ORDER BY yr, mn
")->fetch_all(MYSQLI_ASSOC);

$trend_labels = array_column($trend_rows, 'mo');
$trend_collected = array_map('floatval', array_column($trend_rows, 'collected'));
$trend_outstanding = array_map('floatval', array_column($trend_rows, 'outstanding'));

// ── Main payment records — ALL records loaded; month filtered client-side ──────
$where = [];
$types = '';
$params = [];

if ($filter_status !== 'all') {
    $where[] = 'p.payment_status = ?';
    $types .= 's';
    $params[] = $filter_status;
}

if ($search !== '') {
    $where[] = '(COALESCE(t.full_name, CONCAT(COALESCE(u2.first_name,""), " ", COALESCE(u2.last_name,"")), "") LIKE ?
                  OR COALESCE(p.manual_tenant_name,"") LIKE ?
                  OR COALESCE(u.unit_number,"") LIKE ?
                  OR CAST(p.payment_id AS CHAR) LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = !empty($where) ? implode(' AND ', $where) : '1=1';

$recStmt = $conn->prepare("
    SELECT
        p.payment_id, p.booking_id, p.manual_tenant_name, p.manual_unit_id, p.payment_date, p.amount_paid,
        p.payment_method, p.payment_status, p.notes, p.created_at,
        COALESCE(
            NULLIF(t.full_name,''),
            NULLIF(CONCAT(COALESCE(u2.first_name,''), ' ', COALESCE(u2.last_name,'')),' '),
            NULLIF(COALESCE(inv_t.full_name,''),''),
            NULLIF(p.manual_tenant_name,''),
            '—'
        ) AS full_name,
        COALESCE(t.tenant_id, inv_t.tenant_id) AS tenant_id,
        COALESCE(u.unit_number, inv.unit, manual_u.unit_number) AS unit_number,
        COALESCE(u.unit_id, manual_u.unit_id) AS unit_id,
        COALESCE(u2.profile_photo, inv_u.profile_photo) AS profile_photo
    FROM payments p
    LEFT JOIN bookings b   ON b.booking_id = p.booking_id AND p.booking_id > 0
    LEFT JOIN tenants  t   ON t.tenant_id  = b.tenant_id
    LEFT JOIN units    u   ON u.unit_id    = b.unit_id
    LEFT JOIN users    u2  ON u2.user_id   = b.user_id
    LEFT JOIN units manual_u ON manual_u.unit_id = p.manual_unit_id
    -- invoice payment joins (booking_id IS NULL, notes = 'INV-PMT-{id}')
    LEFT JOIN invoices inv  ON p.booking_id IS NULL
                           AND p.notes = CONCAT('INV-PMT-', inv.id)
    LEFT JOIN tenants inv_t ON inv_t.tenant_id = inv.tenant_id
    LEFT JOIN users   inv_u ON inv_u.email = inv_t.email
    WHERE $where_sql
    ORDER BY p.created_at DESC
");

if ($types && $params) {
    $recStmt->bind_param($types, ...$params);
}
$recStmt->execute();
$records = $recStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recStmt->close();

// ── Booking options for "Record Payment" modal dropdown ───────────────────────
$bookingOptStmt = $conn->prepare("
    SELECT
        b.booking_id,
        COALESCE(NULLIF(t.full_name,''), CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')), '—') AS full_name,
        COALESCE(un.unit_number, '(deleted unit)') AS unit_number
    FROM bookings b
    LEFT JOIN tenants t  ON t.tenant_id = b.tenant_id
    LEFT JOIN users   u  ON u.user_id   = b.user_id
    LEFT JOIN units   un ON un.unit_id  = b.unit_id
    WHERE b.status NOT IN ('cancelled', 'completed', 'booked')
      AND b.booking_id = (
          SELECT b2.booking_id
          FROM bookings b2
          WHERE b2.status NOT IN ('cancelled', 'completed')
            AND COALESCE(b2.tenant_id, -1) = COALESCE(b.tenant_id, -1)
            AND COALESCE(b2.user_id, -1)   = COALESCE(b.user_id, -1)
          ORDER BY FIELD(b2.status, 'active', 'confirmed', 'pending'), b2.checkin_date DESC, b2.booking_id DESC
          LIMIT 1
      )
    ORDER BY full_name
");
$bookingOptStmt->execute();
$booking_options = $bookingOptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bookingOptStmt->close();

// ── Unit options for manual-entry "Record Payment" modal ──────────────────────
$unitOptStmt = $conn->prepare("
    SELECT unit_id, unit_number, unit_name
    FROM units
    ORDER BY unit_number
");
$unitOptStmt->execute();
$unit_options = $unitOptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unitOptStmt->close();