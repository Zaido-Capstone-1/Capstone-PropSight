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
$monthlyRefunds = array_fill(0, 12, 0);

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

$refRes = mysqli_query(
    $conn,
    "SELECT MONTH(refund_date)-1 AS m, COALESCE(SUM(refund_amount),0) AS v
     FROM refunds WHERE refund_status IN ('completed','processing') AND YEAR(refund_date)=$year GROUP BY m"
);
while ($r = mysqli_fetch_assoc($refRes))
    $monthlyRefunds[(int) $r['m']] = (float) $r['v'];

// ── Totals ─────────────────────────────────────────────
$totalIncome = array_sum($monthlyIncome);
$totalExpenses = array_sum($monthlyExpenses);
$totalRefunds = array_sum($monthlyRefunds);
$netProfit = $totalIncome - $totalExpenses - $totalRefunds;

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

// ── Expense breakdown by subcategory (for stacked chart) ──
$monthlyMaint = array_fill(0, 12, 0.0);
$monthlyUtil  = array_fill(0, 12, 0.0);
$monthlySal   = array_fill(0, 12, 0.0);
$monthlyAdm   = array_fill(0, 12, 0.0);
$expSubRes = mysqli_query($conn,
    "SELECT MONTH(expense_date)-1 AS m,
            COALESCE(SUM(CASE WHEN expense_category='Maintenance' THEN amount END),0) AS maint,
            COALESCE(SUM(CASE WHEN expense_category='Utilities'   THEN amount END),0) AS util,
            COALESCE(SUM(CASE WHEN expense_category='Salaries'    THEN amount END),0) AS sal,
            COALESCE(SUM(CASE WHEN expense_category='Admin'       THEN amount END),0) AS adm
     FROM expenses WHERE YEAR(expense_date)=$year GROUP BY m");
while ($r = mysqli_fetch_assoc($expSubRes)) {
    $m = (int)$r['m'];
    $monthlyMaint[$m] = (float)$r['maint'];
    $monthlyUtil[$m]  = (float)$r['util'];
    $monthlySal[$m]   = (float)$r['sal'];
    $monthlyAdm[$m]   = (float)$r['adm'];
}

// ── Revenue mix by property ────────────────────────────
$revMixRes = mysqli_query($conn,
    "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t ON t.property_id=p.property_id AND t.type='Income' AND YEAR(t.transaction_date)=$year
     GROUP BY p.property_id, p.property_name HAVING total>0 ORDER BY total DESC");
$revenueMix = [];
while ($r = mysqli_fetch_assoc($revMixRes)) {
    $pct = $totalIncome > 0 ? (int)round((float)$r['total'] / $totalIncome * 100) : 0;
    $revenueMix[$r['property_name']] = $pct;
}

// ── P&L summary table ─────────────────────────────────
$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$pnlSummary  = [];
$prevProfit  = null;
$lastMonth   = ($year === (int)date('Y')) ? (int)date('n') : 12;
for ($i = 1; $i <= $lastMonth; $i++) {
    $m   = $i - 1;
    $rev = $monthlyIncome[$m];
    $exp = $monthlyExpenses[$m];
    $ref = $monthlyRefunds[$m];
    $pft = $rev - $exp - $ref;
    $margin  = $rev > 0 ? round($pft / $rev * 100, 1) : 0;
    $vsPrior = '—';
    if ($prevProfit !== null && $prevProfit != 0) {
        $chg = round(($pft - $prevProfit) / abs($prevProfit) * 100, 1);
        $vsPrior = $chg > 0 ? "▲ $chg%" : ($chg < 0 ? "▼ ".abs($chg)."%" : "—");
    }
    $pnlSummary[] = [
        $monthNames[$i],
        '₱ '.number_format($rev, 0),
        '₱ '.number_format($exp, 0),
        '₱ '.number_format($ref, 0),
        '₱ '.number_format($pft, 0),
        $margin.'%',
        $vsPrior,
    ];
    $prevProfit = $pft;
}

// ── Growth rates ──────────────────────────────────────
$prevNet      = $prevIncome - $prevExpenses;
$revenueGrowth  = $prevIncome   > 0 ? round(($totalIncome   - $prevIncome)   / $prevIncome   * 100, 1) : 0;
$expenseGrowth  = $prevExpenses > 0 ? round(($totalExpenses - $prevExpenses) / $prevExpenses * 100, 1) : 0;
$profitGrowth   = $prevNet      > 0 ? round(($netProfit     - $prevNet)      / $prevNet      * 100, 1) : 0;
$roi            = $totalIncome  > 0 ? round($netProfit / $totalIncome * 100, 1) : 0;

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
    $monthlyNet[$i] = round($monthlyIncome[$i] - $monthlyExpenses[$i] - $monthlyRefunds[$i], 2);
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
    'success'          => true,
    'year'             => $year,
    'years'            => $years,
    'monthly_income'   => array_values($monthlyIncome),
    'monthly_expenses' => array_values($monthlyExpenses),
    'monthly_refunds'  => array_values($monthlyRefunds),
    'monthly_net'      => array_values($monthlyNet),
    'maintenance'      => array_values($monthlyMaint),
    'utilities'        => array_values($monthlyUtil),
    'salaries'         => array_values($monthlySal),
    'admin'            => array_values($monthlyAdm),
    'revenue_mix'      => $revenueMix,
    'pnl_summary'      => $pnlSummary,
    'total_income'     => $totalIncome,
    'total_expenses'   => $totalExpenses,
    'total_refunds'    => $totalRefunds,
    'net_profit'       => $netProfit,
    'roi'              => $roi,
    'revenue_growth'   => $revenueGrowth,
    'expense_growth'   => $expenseGrowth,
    'profit_growth'    => $profitGrowth,
    'prev_income'      => $prevIncome,
    'prev_expenses'    => $prevExpenses,
    'prev_net'         => $prevNet,
    'exp_by_category'  => $expByCategory,
    'inc_by_category'  => $incByCategory,
    'revenue_by_property' => $revenueByProperty,
]);