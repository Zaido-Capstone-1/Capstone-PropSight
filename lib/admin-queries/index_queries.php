<?php
/**
 * lib/admin/index_data.php
 * Data layer for pages/admin/index.php (Dashboard)
 * Requires: $conn (mysqli) already open.
 */

$year = (int) date('Y');
$prevYear = $year - 1;

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

$pendingBookings = (int) $conn->query(
    "SELECT COUNT(*) AS v FROM bookings WHERE status='pending'"
)->fetch_assoc()['v'];

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

$cancelledThisMonth = (int) $conn->query(
    "SELECT COUNT(*) AS v FROM bookings
     WHERE status='cancelled' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
)->fetch_assoc()['v'];

$totalThisMonth = max(1, (int) $conn->query(
    "SELECT COUNT(*) AS v FROM bookings
     WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
)->fetch_assoc()['v']);

$cancelRate = round($cancelledThisMonth / $totalThisMonth * 100, 1);

// ── Full-year chart data (Jan–Dec, current year) ───────────────────────────
$chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$revData = array_fill(0, 12, 0.0);
$expData = array_fill(0, 12, 0.0);

$stmtRev = $conn->prepare(
    "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=?
     GROUP BY m"
);
$stmtRev->bind_param('i', $year);
$stmtRev->execute();
$res = $stmtRev->get_result();
while ($r = $res->fetch_assoc())
    $revData[(int) $r['m']] = round((float) $r['v'] / 1000, 1);
$stmtRev->close();

$stmtExp = $conn->prepare(
    "SELECT MONTH(expense_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM expenses WHERE YEAR(expense_date)=?
     GROUP BY m"
);
$stmtExp->bind_param('i', $year);
$stmtExp->execute();
$res = $stmtExp->get_result();
while ($r = $res->fetch_assoc())
    $expData[(int) $r['m']] = round((float) $r['v'] / 1000, 1);
$stmtExp->close();

$revData = array_values($revData);
$expData = array_values($expData);

// ── Properties list ────────────────────────────────────────────────────────
$propRes = $conn->query(
    "SELECT p.property_id, p.property_name, p.address,
            COUNT(u.unit_id)         AS total_units,
            SUM(u.status='occupied') AS occupied
     FROM properties p
     LEFT JOIN units u ON u.property_id = p.property_id
     GROUP BY p.property_id
     ORDER BY occupied DESC
     LIMIT 4"
);
$properties = [];
while ($r = $propRes->fetch_assoc())
    $properties[] = $r;

// ── Recent maintenance tasks ───────────────────────────────────────────────
$taskRes = $conn->query(
    "SELECT m.issue_description AS title, p.property_name, m.priority, m.request_status AS status
     FROM maintenance_requests m
     LEFT JOIN units u ON u.unit_id = m.unit_id
     LEFT JOIN properties p ON p.property_id = u.property_id
     WHERE m.request_status NOT IN ('completed', 'closed')
     ORDER BY m.request_date DESC
     LIMIT 5"
);
$tasks = [];
while ($r = $taskRes->fetch_assoc())
    $tasks[] = $r;

$taskOpenCount = 0;
$taskInProgressCount = 0;
foreach ($tasks as $t) {
    $s = strtolower(trim((string) ($t['status'] ?? '')));
    if ($s === 'open')
        $taskOpenCount++;
    if ($s === 'in_progress')
        $taskInProgressCount++;
}

// ── Available years for chart switcher ────────────────────────────────────
$years = [];
$yrRes = $conn->query(
    "SELECT y FROM (
       SELECT DISTINCT YEAR(transaction_date) AS y FROM transactions
       UNION
       SELECT DISTINCT YEAR(expense_date) AS y FROM expenses
     ) z
     WHERE y IS NOT NULL
     ORDER BY y DESC"
);
while ($yrRes && ($r = $yrRes->fetch_assoc()))
    $years[] = (int) $r['y'];
$currentYear = (int) date('Y');
if (!in_array($currentYear, $years, true))
    array_unshift($years, $currentYear);

// ── UI maps ────────────────────────────────────────────────────────────────
$taskStatusMap = [
    'open' => ['bg' => 'var(--danger-light)', 'color' => 'var(--danger)', 'label' => 'Urgent'],
    'in_progress' => ['bg' => 'var(--blue-50)', 'color' => 'var(--blue-500)', 'label' => 'In Progress'],
    'pending' => ['bg' => 'var(--pending-light)', 'color' => 'var(--accent-dk)', 'label' => 'Pending'],
    'completed' => ['bg' => 'var(--success-light)', 'color' => 'var(--success)', 'label' => 'Done'],
    'closed' => ['bg' => 'var(--success-light)', 'color' => 'var(--success)', 'label' => 'Closed'],
];
$taskDotColor = [
    'open' => 'var(--danger)',
    'in_progress' => 'var(--blue-400)',
    'pending' => 'var(--gold)',
    'completed' => 'var(--success)',
    'closed' => 'var(--success)',
];
$taskPriorityMap = [
    'high' => ['bg' => 'var(--danger-light)', 'color' => 'var(--danger)', 'label' => 'High'],
    'medium' => ['bg' => 'var(--pending-light)', 'color' => 'var(--accent-dk)', 'label' => 'Medium'],
    'low' => ['bg' => 'var(--blue-50)', 'color' => 'var(--blue-500)', 'label' => 'Low'],
];