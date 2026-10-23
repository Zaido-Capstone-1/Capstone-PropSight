<?php
/**
 * API: /endpoints/admin/calendar_month.php
 * GET ?year=YYYY&month=M — returns the calendar data for one month as JSON,
 * so the admin calendar page can switch months without a full page reload.
 */
include '../../includes/session.php';
include '../../includes/db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// calendar_queries.php reads $_GET['year'] / $_GET['month'] and, using $conn,
// builds every variable the calendar page needs ($day_data, $bookings_by_day,
// $blocked_dates, the stat totals, month/year labels, prev/next pointers...).
require_once '../../lib/admin-queries/calendar_queries.php';

echo json_encode([
    'success' => true,
    'year' => $year,
    'month_num' => $month_num,
    'month_name' => $month_name,
    'month_short' => $month_short,
    'days_in_month' => $days_in_month,
    'start_dow' => $start_dow,
    'today_day' => $today_day,
    'day_data' => $day_data,
    'bookings_by_day' => $bookings_by_day,
    'blocked_dates' => $blocked_dates,
    'total_booked' => $total_booked,
    'total_partial' => $total_partial,
    'total_free' => $total_free,
    'occ_rate' => $occ_rate,
    'prev_year' => $prev_year,
    'prev_month' => $prev_month,
    'next_year' => $next_year,
    'next_month' => $next_month,
]);