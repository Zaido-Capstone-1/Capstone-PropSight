<?php
/**
 * API: /api/booking_report.php
 * GET  — booking stats, trends, and breakdowns
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

$allowed_roles = ['admin', 'accounting', 'manager', 'frontdesk'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$range = $_GET['range'] ?? '30'; // 30 | 60 | 365 (year)
$range = in_array($range, ['30', '60', '365', 'all']) ? $range : '30';

$dateFilter = $range === 'all'
    ? '1=1'
    : "b.created_at >= DATE_SUB(NOW(), INTERVAL $range DAY)";

// ── Summary stats ─────────────────────────────────────
$stats = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT
        COUNT(*)                                AS total,
        SUM(status='confirmed')                 AS confirmed,
        SUM(status='active')                    AS active_cnt,
        SUM(status='completed')                 AS completed,
        SUM(status='cancelled')                 AS cancelled,
        SUM(status='pending')                   AS pending,
        AVG(DATEDIFF(checkout_date,checkin_date)) AS avg_nights,
        COALESCE(SUM(total_amount),0)           AS total_revenue
     FROM bookings b WHERE $dateFilter"
));

$total = max(1, (int) $stats['total']);
$stats['cancel_rate'] = round($stats['cancelled'] / $total * 100, 1);
$stats['confirm_rate'] = round(($stats['confirmed'] + $stats['completed'] + $stats['active_cnt']) / $total * 100, 1);
$stats['avg_nights'] = round((float) $stats['avg_nights'], 1);

// ── Monthly volume (last 12 months) ───────────────────
$monthlyRes = mysqli_query(
    $conn,
    "SELECT
        DATE_FORMAT(b.created_at,'%b %Y') AS label,
        YEAR(b.created_at) AS y,
        MONTH(b.created_at) AS m,
        COUNT(*) AS total,
        SUM(b.status='cancelled') AS cancelled,
        SUM(b.status IN('confirmed','active','completed')) AS confirmed
     FROM bookings b
     WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY y, m
     ORDER BY y, m"
);
$monthly = [];
while ($r = mysqli_fetch_assoc($monthlyRes))
    $monthly[] = $r;

// ── Bookings by property ──────────────────────────────
$byPropRes = mysqli_query(
    $conn,
    "SELECT p.property_name,
            COUNT(b.booking_id) AS total,
            COALESCE(SUM(b.total_amount),0) AS revenue
     FROM bookings b
     JOIN units      u ON u.unit_id      = b.unit_id
     JOIN properties p ON p.property_id  = u.property_id
     WHERE $dateFilter
     GROUP BY p.property_id, p.property_name
     ORDER BY total DESC"
);
$byProperty = [];
while ($r = mysqli_fetch_assoc($byPropRes))
    $byProperty[] = $r;

// ── Bookings by payment method ────────────────────────
$byPayRes = mysqli_query(
    $conn,
    "SELECT COALESCE(payment_method,'Unknown') AS method, COUNT(*) AS total
     FROM bookings b WHERE $dateFilter
     GROUP BY payment_method ORDER BY total DESC"
);
$byPayment = [];
while ($r = mysqli_fetch_assoc($byPayRes))
    $byPayment[] = $r;

// ── Top booked units ──────────────────────────────────
$topUnitsRes = mysqli_query(
    $conn,
    "SELECT u.unit_name, u.unit_number, p.property_name,
            COUNT(b.booking_id) AS total_bookings,
            COALESCE(SUM(b.total_amount),0) AS revenue
     FROM bookings b
     JOIN units      u ON u.unit_id      = b.unit_id
     JOIN properties p ON p.property_id  = u.property_id
     WHERE $dateFilter AND b.status NOT IN('cancelled')
     GROUP BY u.unit_id
     ORDER BY total_bookings DESC
     LIMIT 8"
);
$topUnits = [];
while ($r = mysqli_fetch_assoc($topUnitsRes))
    $topUnits[] = $r;

// ── Booking lead time (days between created and checkin) ──
$leadRes = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT AVG(DATEDIFF(checkin_date, DATE(created_at))) AS avg_lead
     FROM bookings b WHERE $dateFilter AND status NOT IN('cancelled')"
));
$avgLead = round((float) ($leadRes['avg_lead'] ?? 0), 1);

echo json_encode([
    'success' => true,
    'range' => $range,
    'stats' => $stats,
    'monthly' => $monthly,
    'by_property' => $byProperty,
    'by_payment' => $byPayment,
    'top_units' => $topUnits,
    'avg_lead_days' => $avgLead,
]);