<?php
/**
 * API: /api/occupancy_report.php
 * GET  — occupancy data (overall, per property, monthly trend)
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

$month = $_GET['month'] ?? date('Y-m');
$parts = explode('-', $month . '-01');
$year = (int) $parts[0];
$mon = (int) $parts[1];

// ── Unit totals (current) ──────────────────────────────
$unitTotals = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT
        COUNT(*)                             AS total,
        SUM(status='occupied')               AS occupied,
        SUM(status='vacant')                 AS vacant,
        SUM(status='maintenance')            AS maintenance
     FROM units"
));

$totalUnits = (int) ($unitTotals['total'] ?? 1);
$occupiedNow = (int) ($unitTotals['occupied'] ?? 0);
$vacantNow = (int) ($unitTotals['vacant'] ?? 0);
$maintNow = (int) ($unitTotals['maintenance'] ?? 0);
$overallRate = $totalUnits > 0 ? round($occupiedNow / $totalUnits * 100, 1) : 0;

// ── Avg stay duration (completed bookings) ─────────────
$avgRes = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT AVG(DATEDIFF(checkout_date, checkin_date)) AS avg_nights
     FROM bookings WHERE status='completed'"
));
$avgNights = round((float) ($avgRes['avg_nights'] ?? 0), 1);

// ── Occupancy per property (for selected month) ────────
$propRes = mysqli_query(
    $conn,
    "SELECT
        p.property_id, p.property_name,
        COUNT(DISTINCT u.unit_id) AS total_units,
        COUNT(DISTINCT CASE WHEN b.booking_id IS NOT NULL THEN u.unit_id END) AS occupied_units
     FROM properties p
     LEFT JOIN units u ON u.property_id = p.property_id
     LEFT JOIN bookings b
       ON b.unit_id = u.unit_id
       AND b.status IN ('confirmed','active')
       AND b.checkin_date  <= LAST_DAY(CONCAT('$year-', LPAD($mon,2,'0'), '-01'))
       AND b.checkout_date >= CONCAT('$year-', LPAD($mon,2,'0'), '-01')
     GROUP BY p.property_id, p.property_name
     ORDER BY p.property_name"
);
$perProperty = [];
while ($r = mysqli_fetch_assoc($propRes)) {
    $t = (int) $r['total_units'];
    $o = (int) $r['occupied_units'];
    $r['rate'] = $t > 0 ? round($o / $t * 100) : 0;
    $perProperty[] = $r;
}

// ── Monthly trend (12 months back from selected month) ──
$trend = [];
$tRes = mysqli_query(
    $conn,
    "SELECT
        DATE_FORMAT(month_date,'%b %Y') AS label,
        YEAR(month_date) AS y,
        MONTH(month_date) AS m
     FROM (
        SELECT DATE_FORMAT(CONCAT('$year-', LPAD($mon,2,'0'), '-01') - INTERVAL (n) MONTH, '%Y-%m-01') AS month_date
        FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
              UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) nums
     ) months ORDER BY month_date ASC"
);

while ($r = mysqli_fetch_assoc($tRes)) {
    $yr = (int) $r['y'];
    $mo = (int) $r['m'];

    $occ = (int) (mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(DISTINCT b.unit_id) AS c FROM bookings b
         WHERE b.status IN ('confirmed','active','completed')
           AND YEAR(b.checkin_date)=$yr AND MONTH(b.checkin_date)=$mo"
    ))['c'] ?? 0);

    $tot = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM units"))['c'] ?? 1);
    $rate = $tot > 0 ? round($occ / $tot * 100) : 0;

    $trend[] = ['label' => $r['label'], 'occupied' => $occ, 'total' => $tot, 'rate' => $rate];
}

// ── Available months for filter ───────────────────────
$avMonths = [];
for ($i = 11; $i >= 0; $i--) {
    $avMonths[] = date('Y-m', strtotime("-$i months"));
}

echo json_encode([
    'success' => true,
    'month' => $month,
    'total_units' => $totalUnits,
    'occupied' => $occupiedNow,
    'vacant' => $vacantNow,
    'maintenance' => $maintNow,
    'overall_rate' => $overallRate,
    'avg_nights' => $avgNights,
    'per_property' => $perProperty,
    'trend' => $trend,
    'available_months' => $avMonths,
]);