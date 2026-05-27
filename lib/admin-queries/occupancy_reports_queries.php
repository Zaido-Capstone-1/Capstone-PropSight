<?php
/**
 * lib/admin/occupancy_reports_data.php
 * Data layer for pages/admin/occupancy_reports.php
 * Requires: $conn (mysqli)
 */

$selectedMonth = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = array_map('intval', explode('-', $selectedMonth . '-01'));
$monthLabel = date('F Y', mktime(0, 0, 0, $mo, 1, $yr));

// Current snapshot
$snap = $conn->query(
    "SELECT COUNT(*) AS total, SUM(status='occupied') AS occupied,
            SUM(status='vacant') AS vacant, SUM(status='maintenance') AS maintenance
     FROM units"
)->fetch_assoc();
$totalUnits = max(1, (int) $snap['total']);
$occupiedNow = (int) $snap['occupied'];
$vacantNow = (int) $snap['vacant'];
$overallRate = round($occupiedNow / $totalUnits * 100, 1);

// Previous month
$pmo = $mo - 1;
$pyr = $yr;
if ($pmo < 1) {
    $pmo = 12;
    $pyr--;
}
$prevStart = sprintf('%04d-%02d-01', $pyr, $pmo);
$prevEnd = date('Y-m-t', strtotime($prevStart));

$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT b.unit_id) AS c FROM bookings b
     WHERE b.status IN('confirmed','active','completed')
       AND b.checkin_date  <= ?
       AND b.checkout_date >= ?"
);
$stmt->bind_param('ss', $prevEnd, $prevStart);
$stmt->execute();
$prevOcc = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$prevRate = round($prevOcc / $totalUnits * 100, 1);
$rateChange = round($overallRate - $prevRate, 1);

// Average nights overall
$avgNights = round((float) ($conn->query(
    "SELECT COALESCE(AVG(DATEDIFF(checkout_date,checkin_date)),0) AS v
     FROM bookings WHERE status='completed'"
)->fetch_assoc()['v'] ?? 0), 1);

// Per-property occupancy
$propRes = $conn->query(
    "SELECT p.property_id, p.property_name,
            COUNT(DISTINCT u.unit_id) AS total_units,
            COUNT(DISTINCT CASE WHEN u.status='occupied' THEN u.unit_id END) AS occupied_units
     FROM properties p
     LEFT JOIN units u ON u.property_id = p.property_id
     GROUP BY p.property_id ORDER BY p.property_name"
);
$perProperty = [];
while ($r = $propRes->fetch_assoc()) {
    $t = max(1, (int) $r['total_units']);
    $o = (int) $r['occupied_units'];
    $r['rate'] = round($o / $t * 100);
    $perProperty[] = $r;
}

// 12-month overall trend
$stmtTrend = $conn->prepare(
    "SELECT COUNT(DISTINCT unit_id) AS c FROM bookings
     WHERE status IN('confirmed','active','completed')
       AND checkin_date  <= ?
       AND checkout_date >= ?"
);
$trend = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, $mo - $i, 1, $yr);
    $month_start = date('Y-m-01', $ts);
    $month_end = date('Y-m-t', $ts);
    $stmtTrend->bind_param('ss', $month_end, $month_start);
    $stmtTrend->execute();
    $occ = (int) ($stmtTrend->get_result()->fetch_assoc()['c'] ?? 0);
    $trend[] = ['label' => date('M Y', $ts), 'occupied' => $occ, 'rate' => round($occ / $totalUnits * 100)];
}
$stmtTrend->close();

// Per-property 6-month trend
$colors6 = ['#2563c4', '#deaf37', '#2ECC71', '#93c5fd', '#E74C3C', '#8B5CF6'];
$last6Labels = [];
for ($i = 5; $i >= 0; $i--)
    $last6Labels[] = date('M Y', mktime(0, 0, 0, $mo - $i, 1, $yr));

$stmtPT = $conn->prepare(
    "SELECT COUNT(DISTINCT b.unit_id) AS c FROM bookings b
     JOIN units u ON u.unit_id = b.unit_id
     WHERE u.property_id = ?
       AND b.status IN('confirmed','active','completed')
       AND b.checkin_date  <= ?
       AND b.checkout_date >= ?"
);
$propTrendDatasets = [];
foreach ($perProperty as $ci => $prop) {
    $pid = (int) $prop['property_id'];
    $pTot = max(1, (int) $prop['total_units']);
    $data = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = mktime(0, 0, 0, $mo - $i, 1, $yr);
        $month_start = date('Y-m-01', $ts);
        $month_end = date('Y-m-t', $ts);
        $stmtPT->bind_param('iss', $pid, $month_end, $month_start);
        $stmtPT->execute();
        $occ = (int) ($stmtPT->get_result()->fetch_assoc()['c'] ?? 0);
        $data[] = round($occ / $pTot * 100);
    }
    $propTrendDatasets[] = [
        'label' => $prop['property_name'],
        'data' => $data,
        'borderColor' => $colors6[$ci % 6],
        'borderWidth' => 2,
        'tension' => 0.4,
        'fill' => false,
        'pointRadius' => 3,
    ];
}
$stmtPT->close();

// Available months dropdown
$avMonths = [];
for ($i = 11; $i >= 0; $i--)
    $avMonths[] = date('Y-m', mktime(0, 0, 0, $mo - $i, 1, $yr));

// Avg nights per property
$stmtAvg = $conn->prepare(
    "SELECT COALESCE(AVG(DATEDIFF(b.checkout_date,b.checkin_date)),0) AS v
     FROM bookings b JOIN units u ON u.unit_id=b.unit_id
     WHERE u.property_id=? AND b.status='completed'"
);
$avgLabels = [];
$avgData = [];
foreach ($perProperty as $prop) {
    $pid = (int) $prop['property_id'];
    $stmtAvg->bind_param('i', $pid);
    $stmtAvg->execute();
    $avgLabels[] = $prop['property_name'];
    $avgData[] = round((float) ($stmtAvg->get_result()->fetch_assoc()['v'] ?? 0), 1);
}
$stmtAvg->close();

// Chart arrays
$overallRates = array_column($trend, 'rate');
$overallLabels = array_column($trend, 'label');
$overallDatasets = [
    [
        'label' => 'Occupancy Rate (%)',
        'data' => $overallRates,
        'borderColor' => '#2563c4',
        'borderWidth' => 2,
        'tension' => 0.4,
        'fill' => false,
        'pointRadius' => 3,
    ]
];