<?php
/**
 * lib/admin-queries/report_generation_queries.php
 * Data layer for the "Generate Report" feature (endpoints/generate_report.php).
 * These are intentionally separate from the *_reports_queries.php dashboard files —
 * pure functions, no top-level execution, so including this file never runs a query
 * on its own and never touches the existing dashboard pages.
 *
 * Requires: $conn (mysqli)
 */

/* ═══════════════════════════════════════════════ FINANCIAL ═══════════════ */

function ps_getFinancialReportData(mysqli $conn, string $from, string $to): array
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
         WHERE type='Income' AND transaction_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $totalIncome = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE expense_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $totalExpenses = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(refund_amount),0) AS v FROM refunds
         WHERE refund_status IN ('completed','processing') AND refund_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $totalRefunds = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $netProfit = $totalIncome - $totalExpenses - $totalRefunds;
    $margin = $totalIncome > 0 ? round($netProfit / $totalIncome * 100, 1) : 0.0;

    // Monthly P&L breakdown across the range
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS v
         FROM transactions WHERE type='Income' AND transaction_date BETWEEN ? AND ?
         GROUP BY ym"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $incByMonth = [];
    foreach ($stmt->get_result() as $r)
        $incByMonth[$r['ym']] = (float) $r['v'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS v
         FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY ym"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $expByMonth = [];
    foreach ($stmt->get_result() as $r)
        $expByMonth[$r['ym']] = (float) $r['v'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(refund_date, '%Y-%m') AS ym, COALESCE(SUM(refund_amount),0) AS v
         FROM refunds WHERE refund_status IN ('completed','processing') AND refund_date BETWEEN ? AND ?
         GROUP BY ym"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $refByMonth = [];
    foreach ($stmt->get_result() as $r)
        $refByMonth[$r['ym']] = (float) $r['v'];
    $stmt->close();

    $months = array_unique(array_merge(array_keys($incByMonth), array_keys($expByMonth), array_keys($refByMonth)));
    sort($months);
    $pnlRows = [];
    foreach ($months as $ym) {
        $rev = $incByMonth[$ym] ?? 0.0;
        $exp = $expByMonth[$ym] ?? 0.0;
        $ref = $refByMonth[$ym] ?? 0.0;
        $pft = $rev - $exp - $ref;
        $m = $rev > 0 ? round($pft / $rev * 100, 1) : 0.0;
        $pnlRows[] = [date('M Y', strtotime($ym . '-01')), ps_money($rev), ps_money($exp), ps_money($ref), ps_money($pft), $m . '%'];
    }

    // Revenue mix by property
    $stmt = $conn->prepare(
        "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
         FROM properties p
         LEFT JOIN transactions t ON t.property_id = p.property_id
           AND t.type='Income' AND t.transaction_date BETWEEN ? AND ?
         GROUP BY p.property_id, p.property_name HAVING total > 0 ORDER BY total DESC"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $revenueMixRows = [];
    foreach ($stmt->get_result() as $r) {
        $pct = $totalIncome > 0 ? round($r['total'] / $totalIncome * 100, 1) : 0;
        $revenueMixRows[] = [$r['property_name'], ps_money((float) $r['total']), $pct . '%'];
    }
    $stmt->close();

    // Expense breakdown by category
    $stmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(expense_category,''),'Other') AS cat, COALESCE(SUM(amount),0) AS total
         FROM expenses WHERE expense_date BETWEEN ? AND ?
         GROUP BY cat ORDER BY total DESC"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $expenseRows = [];
    foreach ($stmt->get_result() as $r) {
        $pct = $totalExpenses > 0 ? round($r['total'] / $totalExpenses * 100, 1) : 0;
        $expenseRows[] = [$r['cat'], ps_money((float) $r['total']), $pct . '%'];
    }
    $stmt->close();

    // Transaction-level detail (full export, CSV/XLSX only)
    $stmt = $conn->prepare(
        "SELECT t.transaction_date, t.type, COALESCE(p.property_name,'—') AS property_name,
                COALESCE(NULLIF(t.category,''),'—') AS category, t.amount
         FROM transactions t
         LEFT JOIN properties p ON p.property_id = t.property_id
         WHERE t.transaction_date BETWEEN ? AND ?
         ORDER BY t.transaction_date"
    );
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $detailRows = [];
    foreach ($stmt->get_result() as $r) {
        $detailRows[] = [$r['transaction_date'], $r['type'], $r['property_name'], $r['category'], ps_money((float) $r['amount'])];
    }
    $stmt->close();

    return [
        'stats' => [
            'total_revenue' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_refunds' => $totalRefunds,
            'net_profit' => $netProfit,
            'margin' => $margin,
        ],
        'pnl_rows' => $pnlRows,
        'revenue_mix_rows' => $revenueMixRows,
        'expense_rows' => $expenseRows,
        'detail_rows' => $detailRows,
    ];
}

/* ═══════════════════════════════════════════════ BOOKING ═════════════════ */

function ps_getBookingReportData(mysqli $conn, string $from, string $to): array
{
    $fromStart = $from . ' 00:00:00';
    $toEnd = $to . ' 23:59:59';

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status='confirmed') AS confirmed,
                SUM(status='active')    AS active_cnt,
                SUM(status='completed') AS completed,
                SUM(status='cancelled') AS cancelled,
                SUM(status='pending')   AS pending,
                COALESCE(AVG(DATEDIFF(checkout_date,checkin_date)),0) AS avg_nights,
                COALESCE(SUM(total_amount),0) AS total_revenue
         FROM bookings WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->bind_param('ss', $fromStart, $toEnd);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = max(1, (int) $stats['total']);
    $cancelRate = round((int) $stats['cancelled'] / $total * 100, 1);
    $confirmRate = round(((int) $stats['confirmed'] + (int) $stats['completed'] + (int) $stats['active_cnt']) / $total * 100, 1);

    // By property
    $stmt = $conn->prepare(
        "SELECT p.property_name, COUNT(b.booking_id) AS total, COALESCE(SUM(b.total_amount),0) AS revenue
         FROM bookings b
         JOIN units u ON u.unit_id = b.unit_id
         JOIN properties p ON p.property_id = u.property_id
         WHERE b.status NOT IN('cancelled','pending') AND b.created_at BETWEEN ? AND ?
         GROUP BY p.property_id ORDER BY total DESC"
    );
    $stmt->bind_param('ss', $fromStart, $toEnd);
    $stmt->execute();
    $byPropertyRows = [];
    foreach ($stmt->get_result() as $r)
        $byPropertyRows[] = [$r['property_name'], (int) $r['total'], ps_money((float) $r['revenue'])];
    $stmt->close();

    // By payment method
    $stmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(payment_method,''),'Unknown') AS method, COUNT(*) AS total
         FROM bookings WHERE created_at BETWEEN ? AND ? GROUP BY payment_method ORDER BY total DESC"
    );
    $stmt->bind_param('ss', $fromStart, $toEnd);
    $stmt->execute();
    $paymentRows = [];
    foreach ($stmt->get_result() as $r)
        $paymentRows[] = [ucfirst(strtolower($r['method'])), (int) $r['total']];
    $stmt->close();

    // Top units
    $stmt = $conn->prepare(
        "SELECT CASE WHEN u.unit_name IS NOT NULL AND u.unit_name!='' THEN u.unit_name
                     WHEN u.unit_number IS NOT NULL AND u.unit_number!='' THEN u.unit_number
                     ELSE CONCAT('Unit #',u.unit_id) END AS unit_label,
                p.property_name, COUNT(b.booking_id) AS total_bookings, COALESCE(SUM(b.total_amount),0) AS revenue
         FROM bookings b
         JOIN units u ON u.unit_id = b.unit_id
         JOIN properties p ON p.property_id = u.property_id
         WHERE b.status NOT IN('cancelled') AND b.created_at BETWEEN ? AND ?
         GROUP BY u.unit_id ORDER BY total_bookings DESC LIMIT 10"
    );
    $stmt->bind_param('ss', $fromStart, $toEnd);
    $stmt->execute();
    $topUnitRows = [];
    foreach ($stmt->get_result() as $r)
        $topUnitRows[] = [$r['unit_label'], $r['property_name'], (int) $r['total_bookings'], ps_money((float) $r['revenue'])];
    $stmt->close();

    // Full booking-level detail (CSV/XLSX only)
    $stmt = $conn->prepare(
        "SELECT b.booking_id, CONCAT(u2.first_name,' ',u2.last_name) AS guest_name,
                p.property_name,
                CASE WHEN u.unit_name IS NOT NULL AND u.unit_name!='' THEN u.unit_name ELSE CONCAT('Unit #',u.unit_id) END AS unit_label,
                b.checkin_date, b.checkout_date, b.status, b.total_amount, b.payment_method, b.created_at
         FROM bookings b
         JOIN units u ON u.unit_id = b.unit_id
         JOIN properties p ON p.property_id = u.property_id
         JOIN users u2 ON u2.user_id = b.user_id
         WHERE b.created_at BETWEEN ? AND ?
         ORDER BY b.created_at"
    );
    $stmt->bind_param('ss', $fromStart, $toEnd);
    $stmt->execute();
    $detailRows = [];
    foreach ($stmt->get_result() as $r) {
        $detailRows[] = [
            $r['booking_id'], $r['guest_name'], $r['property_name'], $r['unit_label'],
            $r['checkin_date'], $r['checkout_date'], ucfirst($r['status']),
            ps_money((float) $r['total_amount']), $r['payment_method'] ?: '—', $r['created_at'],
        ];
    }
    $stmt->close();

    return [
        'stats' => [
            'total_bookings' => (int) $stats['total'],
            'confirmed' => (int) $stats['confirmed'],
            'cancelled' => (int) $stats['cancelled'],
            'pending' => (int) $stats['pending'],
            'completed' => (int) $stats['completed'],
            'active' => (int) $stats['active_cnt'],
            'total_revenue' => (float) $stats['total_revenue'],
            'avg_nights' => round((float) $stats['avg_nights'], 1),
            'cancel_rate' => $cancelRate,
            'confirm_rate' => $confirmRate,
        ],
        'by_property_rows' => $byPropertyRows,
        'payment_rows' => $paymentRows,
        'top_unit_rows' => $topUnitRows,
        'detail_rows' => $detailRows,
    ];
}

/* ═══════════════════════════════════════════════ OCCUPANCY ═══════════════ */

function ps_getOccupancyReportData(mysqli $conn, string $from, string $to): array
{
    $totalUnits = max(1, (int) ($conn->query("SELECT COUNT(*) AS c FROM units")->fetch_assoc()['c'] ?? 1));

    // Distinct units with at least one night booked in-range
    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT unit_id) AS c FROM bookings
         WHERE status IN('confirmed','active','completed') AND checkin_date <= ? AND checkout_date >= ?"
    );
    $stmt->bind_param('ss', $to, $from);
    $stmt->execute();
    $occupiedUnits = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $overallRate = round($occupiedUnits / $totalUnits * 100, 1);

    // Booked-night-based occupancy rate (more accurate for a date range)
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(
              GREATEST(0, DATEDIFF(LEAST(checkout_date, ?), GREATEST(checkin_date, ?)))
            ),0) AS nights
         FROM bookings WHERE status IN('confirmed','active','completed')
           AND checkin_date <= ? AND checkout_date >= ?"
    );
    $stmt->bind_param('ssss', $to, $from, $to, $from);
    $stmt->execute();
    $bookedNights = (int) ($stmt->get_result()->fetch_assoc()['nights'] ?? 0);
    $stmt->close();

    $periodDays = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
    $availableNights = $totalUnits * $periodDays;
    $nightRate = $availableNights > 0 ? round($bookedNights / $availableNights * 100, 1) : 0.0;

    $avgNights = round((float) ($conn->query(
        "SELECT COALESCE(AVG(DATEDIFF(checkout_date,checkin_date)),0) AS v FROM bookings WHERE status='completed'"
    )->fetch_assoc()['v'] ?? 0), 1);

    // Per-property occupancy within range
    $propRes = $conn->query(
        "SELECT p.property_id, p.property_name, COUNT(DISTINCT u.unit_id) AS total_units
         FROM properties p LEFT JOIN units u ON u.property_id = p.property_id
         GROUP BY p.property_id ORDER BY p.property_name"
    );
    $stmtOcc = $conn->prepare(
        "SELECT COUNT(DISTINCT b.unit_id) AS c FROM bookings b JOIN units u ON u.unit_id = b.unit_id
         WHERE u.property_id = ? AND b.status IN('confirmed','active','completed')
           AND b.checkin_date <= ? AND b.checkout_date >= ?"
    );
    $perPropertyRows = [];
    while ($p = $propRes->fetch_assoc()) {
        $pid = (int) $p['property_id'];
        $pTotal = max(1, (int) $p['total_units']);
        $stmtOcc->bind_param('iss', $pid, $to, $from);
        $stmtOcc->execute();
        $occ = (int) ($stmtOcc->get_result()->fetch_assoc()['c'] ?? 0);
        $rate = round($occ / $pTotal * 100, 1);
        $perPropertyRows[] = [$p['property_name'], $pTotal, $occ, $rate . '%'];
    }
    $stmtOcc->close();

    // Monthly trend across the range
    $months = [];
    $cursor = strtotime(date('Y-m-01', strtotime($from)));
    $endCursor = strtotime(date('Y-m-01', strtotime($to)));
    while ($cursor <= $endCursor) {
        $months[] = date('Y-m', $cursor);
        $cursor = strtotime('+1 month', $cursor);
    }
    $stmtTrend = $conn->prepare(
        "SELECT COUNT(DISTINCT unit_id) AS c FROM bookings
         WHERE status IN('confirmed','active','completed') AND checkin_date <= ? AND checkout_date >= ?"
    );
    $trendRows = [];
    foreach ($months as $ym) {
        $mStart = $ym . '-01';
        $mEnd = date('Y-m-t', strtotime($mStart));
        $stmtTrend->bind_param('ss', $mEnd, $mStart);
        $stmtTrend->execute();
        $occ = (int) ($stmtTrend->get_result()->fetch_assoc()['c'] ?? 0);
        $rate = round($occ / $totalUnits * 100, 1);
        $trendRows[] = [date('M Y', strtotime($mStart)), $occ, $rate . '%'];
    }
    $stmtTrend->close();

    // Per-unit detail (CSV/XLSX only)
    $stmt = $conn->prepare(
        "SELECT CASE WHEN u.unit_name IS NOT NULL AND u.unit_name!='' THEN u.unit_name ELSE CONCAT('Unit #',u.unit_id) END AS unit_label,
                p.property_name,
                COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(b.checkout_date, ?), GREATEST(b.checkin_date, ?)))),0) AS nights_booked
         FROM units u
         JOIN properties p ON p.property_id = u.property_id
         LEFT JOIN bookings b ON b.unit_id = u.unit_id AND b.status IN('confirmed','active','completed')
           AND b.checkin_date <= ? AND b.checkout_date >= ?
         GROUP BY u.unit_id ORDER BY nights_booked DESC"
    );
    $stmt->bind_param('ssss', $to, $from, $to, $from);
    $stmt->execute();
    $detailRows = [];
    foreach ($stmt->get_result() as $r) {
        $rate = $periodDays > 0 ? round(min(100, $r['nights_booked'] / $periodDays * 100), 1) : 0;
        $detailRows[] = [$r['unit_label'], $r['property_name'], (int) $r['nights_booked'], $periodDays, $rate . '%'];
    }
    $stmt->close();

    return [
        'stats' => [
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'overall_rate' => $overallRate,
            'night_based_rate' => $nightRate,
            'avg_nights' => $avgNights,
            'period_days' => $periodDays,
        ],
        'per_property_rows' => $perPropertyRows,
        'trend_rows' => $trendRows,
        'detail_rows' => $detailRows,
    ];
}