<?php
/**
 * lib/admin-queries/transactions_queries.php
 * All DB queries for pages/admin/transactions.php
 * Requires: $conn (mysqli), $year (int)
 */

// ── Total Income (full year) ──────────────────────────────────────────────────
$stmtIncome = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(amount), 0)
    FROM transactions
    WHERE type = 'Income' AND YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtIncome, 'i', $year);
mysqli_stmt_execute($stmtIncome);
mysqli_stmt_bind_result($stmtIncome, $totalIncomeYear);
mysqli_stmt_fetch($stmtIncome);
mysqli_stmt_close($stmtIncome);
$totalIncomeYear = (int) $totalIncomeYear;

// ── Total Expenses (full year) ────────────────────────────────────────────────
$stmtExpense = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(ABS(amount)), 0)
    FROM transactions
    WHERE type = 'Expense' AND YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtExpense, 'i', $year);
mysqli_stmt_execute($stmtExpense);
mysqli_stmt_bind_result($stmtExpense, $totalExpenseYear);
mysqli_stmt_fetch($stmtExpense);
mysqli_stmt_close($stmtExpense);
$totalExpenseYear = (int) $totalExpenseYear;

$netProfitYear = $totalIncomeYear - $totalExpenseYear;

// ── Transaction count (full year) ─────────────────────────────────────────────
$stmtCount = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM transactions WHERE YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtCount, 'i', $year);
mysqli_stmt_execute($stmtCount);
mysqli_stmt_bind_result($stmtCount, $totalCountYear);
mysqli_stmt_fetch($stmtCount);
mysqli_stmt_close($stmtCount);
$totalCountYear = (int) $totalCountYear;

// ── All rows for the ledger table ─────────────────────────────────────────────
// Sorted by created_at DESC so newest inserts always appear first,
// regardless of transaction_date (which is a DATE-only column and causes
// same-day ordering issues when sorted alone).
$stmtAll = mysqli_prepare($conn, "
    SELECT
        t.id,
        DATE_FORMAT(t.transaction_date, '%b %d') AS date_label,
        DATE_FORMAT(t.transaction_date, '%Y-%m')  AS month_val,
        t.transaction_date,
        t.created_at,
        t.reference_no,
        t.description,
        t.category,
        COALESCE(
            p.property_name,
            bp.property_name,
            '—'
        ) AS property_name,
        t.type,
        t.amount
    FROM transactions t
    LEFT JOIN properties p   ON p.property_id  = t.property_id
    LEFT JOIN bookings   bk  ON bk.booking_id  = t.booking_id
    LEFT JOIN units      bu  ON bu.unit_id      = bk.unit_id
    LEFT JOIN properties bp  ON bp.property_id  = bu.property_id
    WHERE YEAR(t.transaction_date) = ?
    ORDER BY t.created_at DESC, t.id DESC
");
mysqli_stmt_bind_param($stmtAll, 'i', $year);
mysqli_stmt_execute($stmtAll);
$result = mysqli_stmt_get_result($stmtAll);
$txns = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmtAll);

// ── Dynamic category list for the filter dropdown ─────────────────────────────
$categories = array_values(array_unique(array_filter(array_column($txns, 'category'))));
sort($categories);

// ── Month picker defaults ─────────────────────────────────────────────────────
$txn_filter_month = date('Y-m');
$txn_cur_picker_month = (int) date('m');
$txn_cur_picker_year = (int) date('Y');