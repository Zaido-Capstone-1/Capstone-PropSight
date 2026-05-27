<?php
/**
 * lib/admin/calendar_data.php
 * Data layer for pages/admin/calendar.php
 * Requires: $conn (mysqli)
 */

$now = new DateTime();
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) $now->format('Y');
$month_num = isset($_GET['month']) ? (int) $_GET['month'] : (int) $now->format('m');

if ($month_num < 1) {
    $month_num = 12;
    $year--;
}
if ($month_num > 12) {
    $month_num = 1;
    $year++;
}

$first_of_month = new DateTime("$year-$month_num-01");
$days_in_month = (int) $first_of_month->format('t');
$start_dow = (int) $first_of_month->format('w');
$month_name = $first_of_month->format('F Y');
$month_short = $first_of_month->format('M');
$today_day = ($now->format('Y-m') === "$year-" . str_pad($month_num, 2, '0', STR_PAD_LEFT))
    ? (int) $now->format('d') : -1;

$prev_month = $month_num - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$next_month = $month_num + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$month_start = "$year-" . str_pad($month_num, 2, '0', STR_PAD_LEFT) . "-01";
$month_end = "$year-" . str_pad($month_num, 2, '0', STR_PAD_LEFT) . "-$days_in_month";

// Active bookings for this month
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status,
            b.guests, b.total_amount,
            CONCAT(u.first_name,' ',u.last_name) AS guest_name,
            u.email AS guest_email,
            COALESCE(un.unit_name, CONCAT(p.property_name,' — Unit ',un.unit_number)) AS unit_label,
            p.property_name, p.property_id
     FROM bookings b
     JOIN users u  ON u.user_id  = b.user_id
     JOIN units un ON un.unit_id = b.unit_id
     LEFT JOIN properties p ON p.property_id = un.property_id
     WHERE b.status IN ('confirmed','active')
       AND b.checkin_date  <= ?
       AND b.checkout_date >= ?
     ORDER BY b.checkin_date ASC"
);
$stmt->bind_param('ss', $month_end, $month_start);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Blocked dates
$stmt = $conn->prepare(
    "SELECT id, blocked_date, reason FROM blocked_dates
     WHERE blocked_date BETWEEN ? AND ?"
);
$stmt->bind_param('ss', $month_start, $month_end);
$stmt->execute();
$res = $stmt->get_result();
$blocked_dates = [];
while ($row = $res->fetch_assoc())
    $blocked_dates[date('j', strtotime($row['blocked_date']))] = $row;
$stmt->close();

// Total active units (no user input)
$total_units = max(1, (int) $conn->query(
    "SELECT COUNT(*) AS cnt FROM units WHERE status != 'inactive'"
)->fetch_assoc()['cnt']);

// Properties dropdown (no user input)
$props_res = $conn->query("SELECT property_id, property_name FROM properties ORDER BY property_name");
$properties = $props_res->fetch_all(MYSQLI_ASSOC);

// Build day_data and bookings_by_day
$day_data = [];
$bookings_by_day = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $day_data[$d] = ['status' => 'free', 'count' => 0, 'total' => $total_units];
    $bookings_by_day[$d] = [];
}

foreach ($bookings as $bk) {
    $ci = new DateTime($bk['checkin_date']);
    $co = new DateTime($bk['checkout_date']);
    $iter = clone $ci;
    while ($iter <= $co) {
        $d = (int) $iter->format('j');
        $m = (int) $iter->format('m');
        $y = (int) $iter->format('Y');
        if ($m === $month_num && $y === $year && $d >= 1 && $d <= $days_in_month) {
            $day_data[$d]['count']++;
            if (!in_array($bk['booking_id'], array_column($bookings_by_day[$d], 'booking_id')))
                $bookings_by_day[$d][] = $bk;
            if (!empty($bk['property_id']) && !in_array($bk['property_id'], $day_data[$d]['props'] ?? []))
                $day_data[$d]['props'][] = (int) $bk['property_id'];
        }
        $iter->modify('+1 day');
    }
}

for ($d = 1; $d <= $days_in_month; $d++) {
    if (isset($blocked_dates[$d])) {
        $day_data[$d]['status'] = 'blocked';
    } else {
        $cnt = $day_data[$d]['count'];
        if ($cnt === 0)
            $day_data[$d]['status'] = 'free';
        elseif ($cnt >= $total_units)
            $day_data[$d]['status'] = 'booked';
        else
            $day_data[$d]['status'] = 'partial';
    }
}

$total_booked = 0;
$total_partial = 0;
$total_free = 0;
foreach ($day_data as $info) {
    if ($info['status'] === 'booked')
        $total_booked++;
    if ($info['status'] === 'partial')
        $total_partial++;
    if ($info['status'] === 'free')
        $total_free++;
}
$occ_rate = round(($total_booked + $total_partial * 0.5) / $days_in_month * 100);

$selected_day = $today_day > 0 ? $today_day : 1;
$selected_bookings = $bookings_by_day[$selected_day] ?? [];