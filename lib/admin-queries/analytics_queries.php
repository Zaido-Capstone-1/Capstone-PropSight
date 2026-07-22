<?php
/**
 * lib/admin/analytics_data.php
 * Data layer for pages/admin/analytics.php
 * Requires: $conn (mysqli)
 */

$year = (int) date('Y');
$prevYear = $year - 1;
$currentMonth = (int) date('n');

// ── Revenue ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=?"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$totalRevenue = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=?"
);
$stmt->bind_param('i', $prevYear);
$stmt->execute();
$lastYearRevenue = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
$stmt->close();

$revGrowth = $lastYearRevenue > 0
    ? round(($totalRevenue - $lastYearRevenue) / $lastYearRevenue * 100, 1)
    : 0;

// ── Units ──────────────────────────────────────────────────────────────────
$snap = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='occupied')    AS occupied,
            SUM(status='vacant')      AS vacant,
            SUM(status='maintenance') AS maintenance
     FROM units"
)->fetch_assoc();
$totalUnits = max(1, (int) $snap['total']);
$occupiedUnits = (int) $snap['occupied'];
$vacantUnits = (int) $snap['vacant'];
$maintenanceUnits = (int) $snap['maintenance'];
$occupancyRate = round($occupiedUnits / $totalUnits * 100);

// ── Bookings ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS v FROM bookings WHERE YEAR(created_at)=?"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$totalBookings = (int) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS v FROM bookings WHERE YEAR(created_at)=?"
);
$stmt->bind_param('i', $prevYear);
$stmt->execute();
$lastYearBookings = (int) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
$stmt->close();

$bookingGrowth = $lastYearBookings > 0
    ? round(($totalBookings - $lastYearBookings) / $lastYearBookings * 100, 1)
    : 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS v FROM bookings WHERE status='cancelled' AND YEAR(created_at)=?"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$cancelledBookings = (int) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
$stmt->close();

$cancelRate = $totalBookings > 0 ? round($cancelledBookings / $totalBookings * 100, 1) : 0;

// ── Revenue by property ────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t ON t.property_id=p.property_id
         AND t.type='Income' AND YEAR(t.transaction_date)=?
     GROUP BY p.property_id ORDER BY total DESC LIMIT 6"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
$revByPropLabels = [];
$revByPropData = [];
while ($r = $res->fetch_assoc()) {
    $revByPropLabels[] = $r['property_name'];
    $revByPropData[] = (float) $r['total'];
}
$stmt->close();

// ── Monthly bookings ───────────────────────────────────────────────────────
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthBookings = array_fill(0, 12, 0);

$stmt = $conn->prepare(
    "SELECT MONTH(checkin_date)-1 AS m, COUNT(*) AS c
     FROM bookings WHERE status NOT IN('cancelled') AND YEAR(checkin_date)=?
     GROUP BY m"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc())
    $monthBookings[(int) $r['m']] = (int) $r['c'];
$stmt->close();

$activeMonthLabels = array_slice($monthLabels, 0, $currentMonth);
$activeMonthBookings = array_slice($monthBookings, 0, $currentMonth);

// ── Revenue trend ──────────────────────────────────────────────────────────
$revTrendData = array_fill(0, 12, 0);

$stmt = $conn->prepare(
    "SELECT MONTH(transaction_date)-1 AS m, SUM(amount) AS v
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=?
     GROUP BY m"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc())
    $revTrendData[(int) $r['m']] = (float) $r['v'];
$stmt->close();

$revActual = [];
for ($i = 0; $i < 12; $i++)
    $revActual[] = $i < $currentMonth ? ($revTrendData[$i] ?? 0) : null;

// ── Booking status breakdown ───────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT status AS source, COUNT(*) AS total
     FROM bookings WHERE YEAR(created_at)=?
     GROUP BY status ORDER BY total DESC"
);
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
$sourceLabels = [];
$sourceData = [];
while ($r = $res->fetch_assoc()) {
    $sourceLabels[] = ucfirst($r['source']);
    $sourceData[] = (int) $r['total'];
}
$stmt->close();

// Fallback to payment method breakdown if no status rows
if (empty($sourceLabels)) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(payment_method,''),'Unknown') AS source, COUNT(*) AS total
         FROM bookings WHERE YEAR(created_at)=?
         GROUP BY source ORDER BY total DESC"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sourceLabels[] = $r['source'];
        $sourceData[] = (int) $r['total'];
    }
    $stmt->close();
}

$sourceTotal = array_sum($sourceData) ?: 1;

// ── Seasonality weights (1.0 = baseline) ──────────────────────────────────
$seasonality = [
    0 => 1.30,  // Jan   - Peak
    1 => 1.30,  // Feb   - Peak
    2 => 1.10,  // Mar   - (buffer between peak/high)
    3 => 1.15,  // Apr   - High
    4 => 1.15,  // May   - High
    5 => 0.80,  // Jun   - Low
    6 => 0.80,  // Jul   - Low
    7 => 0.80,  // Aug   - Low
    8 => 0.80,  // Sep   - Low
    9 => 0.80,  // Oct   - Low
    10 => 1.15,  // Nov   - High
    11 => 1.30,  // Dec   - Peak
];

// ── Revenue forecast (linear regression + seasonality) ────────────────────
$forecastRev = array_fill(0, 12, null);
$actualPoints = [];
for ($i = 0; $i < $currentMonth; $i++)
    if ($revActual[$i] !== null)
        $actualPoints[] = ['x' => $i, 'y' => $revActual[$i] / $seasonality[$i]];

if (count($actualPoints) >= 2) {
    $n = count($actualPoints);
    $sumX = $sumY = $sumXY = $sumX2 = 0;
    foreach ($actualPoints as $p) {
        $sumX += $p['x'];
        $sumY += $p['y'];
        $sumXY += $p['x'] * $p['y'];
        $sumX2 += $p['x'] * $p['x'];
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / max(1, $n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;

    for ($i = $currentMonth - 1; $i < 12; $i++) {
        $baseline = $intercept + $slope * $i;
        $forecastRev[$i] = max(0, round($baseline * $seasonality[$i])); // re-apply season
    }
}

// ── Booking forecast (linear regression + seasonality) ────────────────────
$forecastBookings = array_fill(0, 12, null);
if ($currentMonth >= 2) {
    $n = $currentMonth;
    $sumX = $sumY = $sumXY = $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $deseasonedY = $monthBookings[$i] / $seasonality[$i]; // de-seasonalise
        $sumX += $i;
        $sumY += $deseasonedY;
        $sumXY += $i * $deseasonedY;
        $sumX2 += $i * $i;
    }
    $slope = ($n * $sumXY - $sumX * $sumY) / max(1, $n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;

    for ($i = $currentMonth - 1; $i < 12; $i++) {
        $baseline = $intercept + $slope * $i;
        $forecastBookings[$i] = max(0, round($baseline * $seasonality[$i])); // re-apply season
    }
}

// ── Revenue by property as percentage ─────────────────────────────────────
$revByPropTotal = array_sum($revByPropData) ?: 1;
$revByPropPct = array_map(fn($v) => round($v / $revByPropTotal * 100, 1), $revByPropData);

// ── Authoritative "has data" flags ─────────────────────────────────────────
// Derived from the real query results ($totalRevenue / $totalBookings are
// COUNT/SUM values from the DB), not from summing the chart-ready arrays.
// This avoids treating a legitimate all-zero month/property as "no data".
$hasRevenueData = $totalRevenue > 0;
$hasBookingData = $totalBookings > 0;