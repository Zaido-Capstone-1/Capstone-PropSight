<?php
/**
 * API: /endpoints/analytics.php
 * GET — returns KPIs, charts data, property breakdowns
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$period = $_GET['period'] ?? '12months'; // 12months | 6months | thisyear
$now = new DateTime();

switch ($period) {
    case '6months':
        $from = (clone $now)->modify('-6 months')->format('Y-m-01');
        break;
    case 'thisyear':
        $from = $now->format('Y-01-01');
        break;
    default:
        $from = (clone $now)->modify('-12 months')->format('Y-m-01');
}
$fromEsc = mysqli_real_escape_string($conn, $from);
$year = (int) $now->format('Y');

// ── Total Revenue ─────────────────────────────────────────────
$rev = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND transaction_date >= '$fromEsc'"
))['v'] ?? 0);

$revPrev = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND transaction_date >= DATE_SUB('$fromEsc', INTERVAL 12 MONTH)
       AND transaction_date < '$fromEsc'"
))['v'] ?? 0);

// ── Bookings ──────────────────────────────────────────────────
$totalBookings = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE created_at >= '$fromEsc'"
))['c'] ?? 0);

$cancelledBookings = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE status='cancelled' AND created_at >= '$fromEsc'"
))['c'] ?? 0);

$cancelRate = $totalBookings > 0 ? round(($cancelledBookings / $totalBookings) * 100, 1) : 0;

// ── Occupancy ─────────────────────────────────────────────────
$totalUnits = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM units"))['c'] ?? 1);
$occupiedUnits = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM units WHERE status='occupied'"
))['c'] ?? 0);
$occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

// ── Revenue by Property ───────────────────────────────────────
$revByPropRes = mysqli_query($conn, "
    SELECT p.property_name,
           COALESCE(SUM(t.amount),0) AS total
    FROM properties p
    LEFT JOIN transactions t ON t.property_id = p.property_id
        AND t.type='Income' AND t.transaction_date >= '$fromEsc'
    GROUP BY p.property_id, p.property_name
    ORDER BY total DESC
    LIMIT 8
");
$revByProp = [];
while ($row = mysqli_fetch_assoc($revByPropRes)) {
    fmt_dt_row($row);
    $revByProp[] = $row;
}

// ── Monthly Occupancy ─────────────────────────────────────────
$monthlyOccRes = mysqli_query($conn, "
    SELECT
        DATE_FORMAT(b.checkin_date,'%b') AS mo,
        MONTH(b.checkin_date)            AS mn,
        YEAR(b.checkin_date)             AS yr,
        COUNT(DISTINCT b.booking_id)     AS bookings
    FROM bookings b
    WHERE b.status NOT IN ('cancelled')
      AND b.checkin_date >= '$fromEsc'
    GROUP BY yr, mn, mo
    ORDER BY yr, mn
");
$monthlyOcc = [];
while ($row = mysqli_fetch_assoc($monthlyOccRes)) {
    fmt_dt_row($row);
    $monthlyOcc[] = $row;
}

// ── Revenue Trend (monthly) ───────────────────────────────────
$revTrendRes = mysqli_query($conn, "
    SELECT
        DATE_FORMAT(transaction_date,'%b') AS mo,
        MONTH(transaction_date)             AS mn,
        YEAR(transaction_date)              AS yr,
        COALESCE(SUM(CASE WHEN type='Income'  THEN amount END),0) AS income,
        COALESCE(SUM(CASE WHEN type='Expense' THEN amount END),0) AS expense
    FROM transactions
    WHERE transaction_date >= '$fromEsc'
    GROUP BY yr, mn, mo
    ORDER BY yr, mn
");
$revTrend = [];
while ($row = mysqli_fetch_assoc($revTrendRes))
    $revTrend[] = $row;

// ── Top Units by Revenue ──────────────────────────────────────
$topUnitsRes = mysqli_query($conn, "
    SELECT u.unit_number, u.unit_name, u.unit_type,
           p.property_name,
           COUNT(b.booking_id) AS booking_count,
           COALESCE(SUM(b.total_amount),0) AS revenue
    FROM units u
    LEFT JOIN properties p ON p.property_id = u.property_id
    LEFT JOIN bookings   b ON b.unit_id = u.unit_id
        AND b.status='completed'
        AND b.created_at >= '$fromEsc'
    GROUP BY u.unit_id
    ORDER BY revenue DESC
    LIMIT 5
");
$topUnits = [];
while ($row = mysqli_fetch_assoc($topUnitsRes))
    $topUnits[] = $row;

// ── Revenue pct change ────────────────────────────────────────
$revChange = $revPrev > 0 ? round((($rev - $revPrev) / $revPrev) * 100, 1) : ($rev > 0 ? 100 : 0);

echo json_encode([
    'success' => true,
    'kpis' => [
        'total_revenue' => $rev,
        'revenue_change' => $revChange,
        'occupancy_rate' => $occupancyRate,
        'total_bookings' => $totalBookings,
        'cancellation_rate' => $cancelRate,
        'total_units' => $totalUnits,
        'occupied_units' => $occupiedUnits,
    ],
    'charts' => [
        'revenue_by_property' => $revByProp,
        'monthly_occupancy' => $monthlyOcc,
        'revenue_trend' => $revTrend,
    ],
    'top_units' => $topUnits,
]);
