<?php
/**
 * API: /api/financial_report.php
 * GET  — financial summary data for a given year
 *        Used by financial_reports.php for year switching via JS
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

$allowed_roles = ['admin', 'accounting', 'manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$year = (int) ($_GET['year'] ?? date('Y'));

// ── Monthly income & expenses ──────────────────────────
$monthlyIncome = array_fill(0, 12, 0);
$monthlyExpenses = array_fill(0, 12, 0);

$incRes = mysqli_query(
    $conn,
    "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$year
     GROUP BY m"
);
while ($r = mysqli_fetch_assoc($incRes))
    $monthlyIncome[(int) $r['m']] = (float) $r['v'];

$expRes = mysqli_query(
    $conn,
    "SELECT MONTH(expense_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM expenses WHERE YEAR(expense_date)=$year GROUP BY m"
);
while ($r = mysqli_fetch_assoc($expRes))
    $monthlyExpenses[(int) $r['m']] = (float) $r['v'];

// ── Totals ─────────────────────────────────────────────
$totalIncome = array_sum($monthlyIncome);
$totalExpenses = array_sum($monthlyExpenses);
$netProfit = $totalIncome - $totalExpenses;

// ── Previous year comparison ───────────────────────────
$prevYear = $year - 1;
$prevIncome = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$prevYear"
))['v'] ?? 0);
$prevExpenses = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE YEAR(expense_date)=$prevYear"
))['v'] ?? 0);

// ── Expense breakdown by category ─────────────────────
$expCatRes = mysqli_query(
    $conn,
    "SELECT expense_category, COALESCE(SUM(amount),0) AS total
     FROM expenses WHERE YEAR(expense_date)=$year
     GROUP BY expense_category ORDER BY total DESC"
);
$expByCategory = [];
while ($r = mysqli_fetch_assoc($expCatRes))
    $expByCategory[] = $r;

// ── Revenue by property ───────────────────────────────
$revByPropRes = mysqli_query(
    $conn,
    "SELECT p.property_name,
            COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t
       ON t.property_id=p.property_id AND t.type='Income' AND YEAR(t.transaction_date)=$year
     GROUP BY p.property_id, p.property_name
     ORDER BY total DESC"
);
$revenueByProperty = [];
while ($r = mysqli_fetch_assoc($revByPropRes))
    $revenueByProperty[] = $r;

// ── Top income categories ─────────────────────────────
$incCatRes = mysqli_query(
    $conn,
    "SELECT category, COALESCE(SUM(amount),0) AS total
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$year
     GROUP BY category ORDER BY total DESC LIMIT 8"
);
$incByCategory = [];
while ($r = mysqli_fetch_assoc($incCatRes))
    $incByCategory[] = $r;

// ── Net profit by month ───────────────────────────────
$monthlyNet = [];
for ($i = 0; $i < 12; $i++) {
    $monthlyNet[$i] = round($monthlyIncome[$i] - $monthlyExpenses[$i], 2);
}

// ── Available years ───────────────────────────────────
$yearsRes = mysqli_query(
    $conn,
    "SELECT DISTINCT YEAR(transaction_date) AS y FROM transactions
     UNION SELECT DISTINCT YEAR(expense_date) FROM expenses
     ORDER BY y DESC"
);
$years = [];
while ($r = mysqli_fetch_assoc($yearsRes))
    $years[] = (int) $r['y'];
if (!in_array($year, $years))
    $years[] = $year;
rsort($years);

echo json_encode([
    'success' => true,
    'year' => $year,
    'years' => $years,
    'monthly_income' => array_values($monthlyIncome),
    'monthly_expenses' => array_values($monthlyExpenses),
    'monthly_net' => array_values($monthlyNet),
    'total_income' => $totalIncome,
    'total_expenses' => $totalExpenses,
    'net_profit' => $netProfit,
    'prev_income' => $prevIncome,
    'prev_expenses' => $prevExpenses,
    'prev_net' => $prevIncome - $prevExpenses,
    'exp_by_category' => $expByCategory,
    'inc_by_category' => $incByCategory,
    'revenue_by_property' => $revenueByProperty,
]);