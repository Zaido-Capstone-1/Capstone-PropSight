<?php
/**
 * lib/admin/checkin_checkout_data.php
 * Data layer for pages/admin/checkin_checkout.php
 * Requires: $conn (mysqli)
 *
 * NOTE: The AJAX early-exit block (ajax_activity) must remain in the page file
 *       before this include, since it calls header() and exit().
 */

$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!strtotime($selected_date))
    $selected_date = date('Y-m-d');

$dateLabel = date('F j, Y', strtotime($selected_date));
$isToday = ($selected_date === date('Y-m-d'));

// Check-ins
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status, b.guests,
            CONCAT(u.first_name,' ',u.last_name) AS guest_name, u.email,
            COALESCE(un.unit_name, CONCAT(p.property_name,' — ',un.unit_number)) AS unit_label,
            p.property_name, b.checkin_status
     FROM   bookings b
     JOIN   users u  ON u.user_id  = b.user_id
     JOIN   units un ON un.unit_id = b.unit_id
     LEFT JOIN properties p ON p.property_id = un.property_id
     WHERE  b.checkin_date = ? AND b.status NOT IN ('cancelled','completed')
     ORDER  BY b.checkin_date ASC"
);
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check-outs
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status, b.guests,
            CONCAT(u.first_name,' ',u.last_name) AS guest_name, u.email,
            COALESCE(un.unit_name, CONCAT(p.property_name,' — ',un.unit_number)) AS unit_label,
            p.property_name, b.checkout_status, b.checkin_status
     FROM   bookings b
     JOIN   users u  ON u.user_id  = b.user_id
     JOIN   units un ON un.unit_id = b.unit_id
     LEFT JOIN properties p ON p.property_id = un.property_id
     WHERE  b.checkout_date = ? AND b.status NOT IN ('cancelled','completed')
     ORDER  BY b.checkout_date ASC"
);
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$checkouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Currently staying
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM bookings
     WHERE status NOT IN ('cancelled','completed')
       AND checkin_date  <= ?
       AND checkout_date >= ?"
);
$stmt->bind_param('ss', $selected_date, $selected_date);
$stmt->execute();
$staying = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$today_str = date('Y-m-d');
$ci_done = count(array_filter($checkins, fn($r) => ($r['checkin_status'] ?? '') === 'done'));
$co_done = count(array_filter($checkouts, fn($r) => ($r['checkout_status'] ?? '') === 'done'));
$overdue = count(array_filter($checkouts, fn($r) => ($r['checkout_status'] ?? '') !== 'done' && $selected_date < $today_str));

// Calendar activity for same month
$cal_year = date('Y', strtotime($selected_date));
$cal_month = date('m', strtotime($selected_date));
$cal_start = "$cal_year-$cal_month-01";
$cal_end = date('Y-m-t', strtotime($selected_date));

$stmt = $conn->prepare(
    "SELECT DATE(checkin_date) AS ci_date, DATE(checkout_date) AS co_date
     FROM bookings
     WHERE status NOT IN ('cancelled','completed')
       AND (checkin_date BETWEEN ? AND ? OR checkout_date BETWEEN ? AND ?)"
);
$stmt->bind_param('ssss', $cal_start, $cal_end, $cal_start, $cal_end);
$stmt->execute();
$actRes = $stmt->get_result();
$ci_days = [];
$co_days = [];
while ($row = $actRes->fetch_assoc()) {
    if ($row['ci_date'] >= $cal_start && $row['ci_date'] <= $cal_end) {
        $d = (int) date('j', strtotime($row['ci_date']));
        $ci_days[$d] = ($ci_days[$d] ?? 0) + 1;
    }
    if ($row['co_date'] >= $cal_start && $row['co_date'] <= $cal_end) {
        $d = (int) date('j', strtotime($row['co_date']));
        $co_days[$d] = ($co_days[$d] ?? 0) + 1;
    }
}
$stmt->close();
// $ci_days and $co_days are now associative: { day => count }

function ciStatusLabel($row): array
{
    return match ($row['checkin_status'] ?? '') {
        'done' => ['Done', 'success'],
        'no_show' => ['No Show', 'danger'],
        default => ['Expected', 'pending'],
    };
}
function coStatusLabel($row, $selectedDate): array
{
    if (($row['checkout_status'] ?? '') === 'done')
        return ['Done', 'success'];
    if ($selectedDate < date('Y-m-d'))
        return ['Overdue', 'danger'];
    return ['Pending', 'pending'];
}