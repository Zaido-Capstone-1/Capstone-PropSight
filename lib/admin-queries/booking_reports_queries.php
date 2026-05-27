<?php
/**
 * lib/admin/booking_reports_data.php
 * Data layer for pages/admin/booking_reports.php
 * Requires: $conn (mysqli)
 */

$range = $_GET['range'] ?? '30';
$range = in_array($range, ['30', '60', '365', 'all']) ? $range : '30';
$rangeLbl = ['30' => 'Last 30 days', '60' => 'Last 60 days', '365' => 'This year', 'all' => 'All time'][$range];
$dateThreshold = ($range !== 'all') ? date('Y-m-d', strtotime("-{$range} days")) : null;

// Helper: execute a prepared query with optional date threshold bound to 'b.created_at >= ?'
function brQuery(mysqli $conn, string $sql, ?string $threshold): mysqli_result
{
    if ($threshold !== null) {
        $hasWhere = stripos($sql, 'WHERE') !== false;
        $clause = $hasWhere ? ' AND b.created_at >= ?' : ' WHERE b.created_at >= ?';

        // Insert before GROUP BY / ORDER BY / LIMIT if any exist
        if (preg_match('/\b(GROUP\s+BY|ORDER\s+BY|LIMIT)\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $sql = substr($sql, 0, $pos) . $clause . ' ' . substr($sql, $pos);
        } else {
            $sql .= $clause;
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $threshold);
    } else {
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Summary stats
$statsRes = brQuery(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(status='confirmed')  AS confirmed,
            SUM(status='active')     AS active_cnt,
            SUM(status='completed')  AS completed,
            SUM(status='cancelled')  AS cancelled,
            SUM(status='pending')    AS pending,
            COALESCE(AVG(DATEDIFF(checkout_date,checkin_date)),0) AS avg_nights,
            COALESCE(SUM(total_amount),0) AS total_revenue
     FROM bookings b",
    $dateThreshold
);
$stats = $statsRes->fetch_assoc();
$total = max(1, (int) $stats['total']);
$cancelRate = round($stats['cancelled'] / $total * 100, 1);
$confirmRate = round(($stats['confirmed'] + $stats['completed'] + $stats['active_cnt']) / $total * 100, 1);
$avgNights = round((float) $stats['avg_nights'], 1);

// 12-month trend
$monthlyLabels = [];
$monthlyTotal = [];
$monthlyCancelled = [];
$monthlyConfirmed = [];
$monthlyPending = [];

$stmtM = $conn->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status='cancelled') AS cancelled,
            SUM(status IN('confirmed','active','completed')) AS confirmed,
            SUM(status='pending') AS pending
     FROM bookings WHERE YEAR(created_at)=? AND MONTH(created_at)=?"
);
for ($i = 11; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, date('n') - $i, 1, date('Y'));
    $ty = (int) date('Y', $ts);
    $tm = (int) date('n', $ts);
    $stmtM->bind_param('ii', $ty, $tm);
    $stmtM->execute();
    $r = $stmtM->get_result()->fetch_assoc();
    $monthlyLabels[] = date('M Y', $ts);
    $monthlyTotal[] = (int) ($r['total'] ?? 0);
    $monthlyCancelled[] = (int) ($r['cancelled'] ?? 0);
    $monthlyConfirmed[] = (int) ($r['confirmed'] ?? 0);
    $monthlyPending[] = (int) ($r['pending'] ?? 0);
}
$stmtM->close();

// By property
$bpRes = brQuery(
    $conn,
    "SELECT p.property_name, COUNT(b.booking_id) AS total,
            COALESCE(SUM(b.total_amount),0) AS revenue
     FROM bookings b
     JOIN units      u ON u.unit_id      = b.unit_id
     JOIN properties p ON p.property_id  = u.property_id
     WHERE b.status NOT IN('cancelled','pending')
     GROUP BY p.property_id ORDER BY total DESC",
    $dateThreshold
);
$byProperty = $bpRes->fetch_all(MYSQLI_ASSOC);

// Payment methods
$payRes = brQuery(
    $conn,
    "SELECT COALESCE(NULLIF(payment_method,''),'Unknown') AS method, COUNT(*) AS total
     FROM bookings b
     GROUP BY payment_method ORDER BY total DESC",
    $dateThreshold
);
$byPayment = [];
$payLabels = [];
$payData = [];
while ($r = $payRes->fetch_assoc()) {
    $byPayment[] = $r;
    $payLabels[] = ucfirst(strtolower($r['method']));
    $payData[] = (int) $r['total'];
}

// Top units
$tuRes = brQuery(
    $conn,
    "SELECT CASE
              WHEN u.unit_name IS NOT NULL AND u.unit_name!='' THEN u.unit_name
              WHEN u.unit_number IS NOT NULL AND u.unit_number!='' THEN u.unit_number
              ELSE CONCAT('Unit #',u.unit_id)
            END AS unit_label,
            p.property_name,
            COUNT(b.booking_id) AS total_bookings,
            COALESCE(SUM(b.total_amount),0) AS revenue
     FROM bookings b
     JOIN units      u ON u.unit_id     = b.unit_id
     JOIN properties p ON p.property_id = u.property_id
     WHERE b.status NOT IN('cancelled')
     GROUP BY u.unit_id ORDER BY total_bookings DESC LIMIT 8",
    $dateThreshold
);
$topUnits = $tuRes->fetch_all(MYSQLI_ASSOC);

// Guest demographics
$demoRes = brQuery(
    $conn,
    "SELECT COALESCE(NULLIF(TRIM(u.nationality),''),'Unknown') AS nationality,
            COUNT(DISTINCT b.user_id) AS guests,
            COUNT(b.booking_id)       AS bookings,
            COALESCE(SUM(b.total_amount),0) AS revenue
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     WHERE b.status NOT IN('cancelled')
     GROUP BY nationality ORDER BY bookings DESC LIMIT 10",
    $dateThreshold
);
$demographics = $demoRes->fetch_all(MYSQLI_ASSOC);

// Avg lead time
$leadRes = brQuery(
    $conn,
    "SELECT AVG(DATEDIFF(checkin_date,DATE(created_at))) AS v
     FROM bookings b WHERE status NOT IN('cancelled')",
    $dateThreshold
);
$avgLead = round((float) ($leadRes->fetch_assoc()['v'] ?? 0), 1);

// Booking source
$srcRes = brQuery(
    $conn,
    "SELECT COALESCE(NULLIF(booking_source,''),'Direct') AS src, COUNT(*) AS cnt
     FROM bookings b
     GROUP BY src ORDER BY cnt DESC LIMIT 8",
    $dateThreshold
);
$sourceLabels = [];
$sourceCounts = [];
while ($r = $srcRes->fetch_assoc()) {
    $sourceLabels[] = $r['src'];
    $sourceCounts[] = (int) $r['cnt'];
}

// Chart data
$donutLabels = ['Confirmed', 'Active', 'Completed', 'Cancelled', 'Pending'];
$donutData = [(int) $stats['confirmed'], (int) $stats['active_cnt'], (int) $stats['completed'], (int) $stats['cancelled'], (int) $stats['pending']];
$donutColors = ['#2ECC71', '#2563c4', '#93c5fd', '#E74C3C', '#deaf37'];
$unitLabels = array_column($topUnits, 'unit_label');
$unitBookingCounts = array_map('intval', array_column($topUnits, 'total_bookings'));