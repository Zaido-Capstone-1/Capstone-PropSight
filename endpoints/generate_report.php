<?php
/**
 * API: /endpoints/generate_report.php
 * GET — generates and streams a report file (pdf | xlsx | csv) for a date range.
 *
 * Params:
 *   type   = financial | booking | occupancy   (required)
 *   format = pdf | xlsx | csv                   (required)
 *   from   = YYYY-MM-DD                         (required)
 *   to     = YYYY-MM-DD                         (required)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/report_lib.php';
require_once __DIR__ . '/../lib/admin-queries/report_generation_queries.php';

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? '';

$allowedRoles = [
    'financial' => ['admin', 'accounting', 'manager'],
    'booking' => ['admin'],
    'occupancy' => ['admin'],
];

if (!isset($allowedRoles[$type])) {
    http_response_code(400);
    exit('Unknown report type.');
}
if (!in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
    http_response_code(400);
    exit('Unknown report format.');
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles[$type], true)) {
    http_response_code(403);
    exit('Unauthorized.');
}

try {
    [$from, $to] = ps_report_parse_range($_GET);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit($e->getMessage());
}

$periodLabel = ps_report_period_label($from, $to);
$stamp = date('Ymd');

switch ($type) {
    case 'financial': {
        $data = ps_getFinancialReportData($conn, $from, $to);
        $s = $data['stats'];
        $title = 'Financial Report';
        $filenameBase = "financial-report-$stamp";

        $sections = [
            [
                'type' => 'stats',
                'title' => 'Summary',
                'items' => [
                    ['label' => 'Total Revenue', 'value' => ps_money_pdf($s['total_revenue'])],
                    ['label' => 'Total Expenses', 'value' => ps_money_pdf($s['total_expenses'])],
                    ['label' => 'Total Refunds', 'value' => ps_money_pdf($s['total_refunds'])],
                    ['label' => 'Net Profit', 'value' => ps_money_pdf($s['net_profit'])],
                    ['label' => 'Profit Margin', 'value' => $s['margin'] . '%'],
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Monthly Profit & Loss',
                'headers' => ['Month', 'Revenue', 'Expenses', 'Refunds', 'Net Profit', 'Margin'],
                'rows' => $data['pnl_rows'],
                'widths' => [28, 32, 32, 30, 32, 26],
                'aligns' => ['L', 'R', 'R', 'R', 'R', 'R'],
            ],
            [
                'type' => 'table',
                'title' => 'Revenue Mix by Property',
                'headers' => ['Property', 'Revenue', 'Share'],
                'rows' => $data['revenue_mix_rows'],
                'widths' => [90, 55, 35],
                'aligns' => ['L', 'R', 'R'],
            ],
            [
                'type' => 'table',
                'title' => 'Expense Breakdown by Category',
                'headers' => ['Category', 'Amount', 'Share'],
                'rows' => $data['expense_rows'],
                'widths' => [90, 55, 35],
                'aligns' => ['L', 'R', 'R'],
            ],
        ];

        $exportSections = [
            ['title' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => [
                ['Total Revenue', ps_money($s['total_revenue'])],
                ['Total Expenses', ps_money($s['total_expenses'])],
                ['Total Refunds', ps_money($s['total_refunds'])],
                ['Net Profit', ps_money($s['net_profit'])],
                ['Margin', $s['margin'] . '%'],
            ]],
            ['title' => 'Monthly Profit & Loss', 'headers' => ['Month', 'Revenue', 'Expenses', 'Refunds', 'Net Profit', 'Margin'], 'rows' => $data['pnl_rows']],
            ['title' => 'Revenue Mix by Property', 'headers' => ['Property', 'Revenue', 'Share'], 'rows' => $data['revenue_mix_rows']],
            ['title' => 'Expense Breakdown by Category', 'headers' => ['Category', 'Amount', 'Share'], 'rows' => $data['expense_rows']],
            ['title' => 'Transaction Detail', 'headers' => ['Date', 'Type', 'Property', 'Payment Method', 'Amount'], 'rows' => $data['detail_rows']],
        ];
        break;
    }

    case 'booking': {
        $data = ps_getBookingReportData($conn, $from, $to);
        $s = $data['stats'];
        $title = 'Booking Report';
        $filenameBase = "booking-report-$stamp";

        $sections = [
            [
                'type' => 'stats',
                'title' => 'Summary',
                'items' => [
                    ['label' => 'Total Bookings', 'value' => $s['total_bookings']],
                    ['label' => 'Confirmation Rate', 'value' => $s['confirm_rate'] . '%'],
                    ['label' => 'Cancellation Rate', 'value' => $s['cancel_rate'] . '%'],
                    ['label' => 'Total Revenue', 'value' => ps_money_pdf($s['total_revenue'])],
                    ['label' => 'Avg. Nights / Stay', 'value' => $s['avg_nights']],
                    ['label' => 'Confirmed', 'value' => $s['confirmed']],
                    ['label' => 'Completed', 'value' => $s['completed']],
                    ['label' => 'Cancelled', 'value' => $s['cancelled']],
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Bookings by Property',
                'headers' => ['Property', 'Bookings', 'Revenue'],
                'rows' => $data['by_property_rows'],
                'widths' => [90, 40, 50],
                'aligns' => ['L', 'R', 'R'],
            ],
            [
                'type' => 'table',
                'title' => 'By Payment Method',
                'headers' => ['Method', 'Bookings'],
                'rows' => $data['payment_rows'],
                'widths' => [130, 50],
                'aligns' => ['L', 'R'],
            ],
            [
                'type' => 'table',
                'title' => 'Top Units',
                'headers' => ['Unit', 'Property', 'Bookings', 'Revenue'],
                'rows' => $data['top_unit_rows'],
                'widths' => [50, 70, 25, 35],
                'aligns' => ['L', 'L', 'R', 'R'],
            ],
        ];

        $exportSections = [
            ['title' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => [
                ['Total Bookings', $s['total_bookings']],
                ['Confirmed', $s['confirmed']],
                ['Active', $s['active']],
                ['Completed', $s['completed']],
                ['Cancelled', $s['cancelled']],
                ['Pending', $s['pending']],
                ['Confirmation Rate', $s['confirm_rate'] . '%'],
                ['Cancellation Rate', $s['cancel_rate'] . '%'],
                ['Total Revenue', ps_money($s['total_revenue'])],
                ['Avg. Nights / Stay', $s['avg_nights']],
            ]],
            ['title' => 'Bookings by Property', 'headers' => ['Property', 'Bookings', 'Revenue'], 'rows' => $data['by_property_rows']],
            ['title' => 'By Payment Method', 'headers' => ['Method', 'Bookings'], 'rows' => $data['payment_rows']],
            ['title' => 'Top Units', 'headers' => ['Unit', 'Property', 'Bookings', 'Revenue'], 'rows' => $data['top_unit_rows']],
            ['title' => 'Booking Detail', 'headers' => ['Booking ID', 'Guest', 'Property', 'Unit', 'Check-in', 'Check-out', 'Status', 'Amount', 'Payment Method', 'Booked On'], 'rows' => $data['detail_rows']],
        ];
        break;
    }

    case 'occupancy': {
        $data = ps_getOccupancyReportData($conn, $from, $to);
        $s = $data['stats'];
        $title = 'Occupancy Report';
        $filenameBase = "occupancy-report-$stamp";

        $sections = [
            [
                'type' => 'stats',
                'title' => 'Summary',
                'items' => [
                    ['label' => 'Total Units', 'value' => $s['total_units']],
                    ['label' => 'Units Occupied (in range)', 'value' => $s['occupied_units']],
                    ['label' => 'Occupancy Rate (unit-based)', 'value' => $s['overall_rate'] . '%'],
                    ['label' => 'Occupancy Rate (night-based)', 'value' => $s['night_based_rate'] . '%'],
                    ['label' => 'Avg. Nights / Completed Stay', 'value' => $s['avg_nights']],
                    ['label' => 'Period Length', 'value' => $s['period_days'] . ' days'],
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Occupancy by Property',
                'headers' => ['Property', 'Total Units', 'Occupied', 'Rate'],
                'rows' => $data['per_property_rows'],
                'widths' => [80, 35, 30, 35],
                'aligns' => ['L', 'R', 'R', 'R'],
            ],
            [
                'type' => 'table',
                'title' => 'Monthly Trend',
                'headers' => ['Month', 'Units Occupied', 'Rate'],
                'rows' => $data['trend_rows'],
                'widths' => [60, 60, 60],
                'aligns' => ['L', 'R', 'R'],
            ],
        ];

        $exportSections = [
            ['title' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => [
                ['Total Units', $s['total_units']],
                ['Units Occupied (in range)', $s['occupied_units']],
                ['Occupancy Rate (unit-based)', $s['overall_rate'] . '%'],
                ['Occupancy Rate (night-based)', $s['night_based_rate'] . '%'],
                ['Avg. Nights / Completed Stay', $s['avg_nights']],
                ['Period Length (days)', $s['period_days']],
            ]],
            ['title' => 'Occupancy by Property', 'headers' => ['Property', 'Total Units', 'Occupied', 'Rate'], 'rows' => $data['per_property_rows']],
            ['title' => 'Monthly Trend', 'headers' => ['Month', 'Units Occupied', 'Rate'], 'rows' => $data['trend_rows']],
            ['title' => 'Per-Unit Detail', 'headers' => ['Unit', 'Property', 'Nights Booked', 'Period Days', 'Rate'], 'rows' => $data['detail_rows']],
        ];
        break;
    }
}

switch ($format) {
    case 'pdf':
        ps_send_pdf("$filenameBase.pdf", $title, $periodLabel, $sections);
        break;
    case 'xlsx':
        ps_send_xlsx("$filenameBase.xlsx", $title, $periodLabel, $exportSections);
        break;
    case 'csv':
        ps_send_csv("$filenameBase.csv", $title, $periodLabel, $exportSections);
        break;
}