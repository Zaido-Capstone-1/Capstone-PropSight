<?php
/**
 * api/admin/get_financial_data.php
 * AJAX endpoint for financial_reports.php year-switcher.
 * Returns the exact JSON structure the page JS expects.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

$allowed_roles = ['admin', 'accounting', 'manager'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$year = (int) ($_GET['year'] ?? date('Y'));
$prevYear = $year - 1;

// ── Monthly income from transactions ──────────────────
$monthlyIncome = array_fill(0, 12, 0.0);
$incRes = mysqli_query(
    $conn,
    "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$year GROUP BY m"
);
while ($r = mysqli_fetch_assoc($incRes))
    $monthlyIncome[(int) $r['m']] = (float) $r['v'] / 1000;

// ── Monthly expenses per category ─────────────────────
$monthlyExpenses = array_fill(0, 12, 0.0);
$monthlyMaint = array_fill(0, 12, 0.0);
$monthlyUtil = array_fill(0, 12, 0.0);
$monthlySalaries = array_fill(0, 12, 0.0);
$monthlyAdmin = array_fill(0, 12, 0.0);

$expRes = mysqli_query(
    $conn,
    "SELECT MONTH(expense_date)-1 AS m,
            COALESCE(SUM(amount),0)                                                         AS total,
            COALESCE(SUM(CASE WHEN expense_category='Maintenance' THEN amount END),0)       AS maint,
            COALESCE(SUM(CASE WHEN expense_category='Utilities'   THEN amount END),0)       AS util,
            COALESCE(SUM(CASE WHEN expense_category='Salaries'    THEN amount END),0)       AS sal,
            COALESCE(SUM(CASE WHEN expense_category='Admin'       THEN amount END),0)       AS adm
     FROM expenses WHERE YEAR(expense_date)=$year GROUP BY m"
);
while ($r = mysqli_fetch_assoc($expRes)) {
    $m = (int) $r['m'];
    $monthlyExpenses[$m] = (float) $r['total'] / 1000;
    $monthlyMaint[$m] = (float) $r['maint'] / 1000;
    $monthlyUtil[$m] = (float) $r['util'] / 1000;
    $monthlySalaries[$m] = (float) $r['sal'] / 1000;
    $monthlyAdmin[$m] = (float) $r['adm'] / 1000;
}

// ── Totals ─────────────────────────────────────────────
$totalIncome = array_sum($monthlyIncome) * 1000;
$totalExpenses = array_sum($monthlyExpenses) * 1000;
$netProfit = $totalIncome - $totalExpenses;
$roi = $totalIncome > 0 ? round($netProfit / $totalIncome * 100, 1) : 0;

// ── Previous year ──────────────────────────────────────
$prevInc = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=$prevYear"
))['v'] ?? 0);
$prevExp = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM expenses
     WHERE YEAR(expense_date)=$prevYear"
))['v'] ?? 0);
$prevNet = $prevInc - $prevExp;

$revenueGrowth = $prevInc > 0 ? round(($totalIncome - $prevInc) / $prevInc * 100, 1) : 0;
$expenseGrowth = $prevExp > 0 ? round(($totalExpenses - $prevExp) / $prevExp * 100, 1) : 0;
$profitGrowth = $prevNet > 0 ? round(($netProfit - $prevNet) / $prevNet * 100, 1) : 0;

// ── Revenue mix by property ────────────────────────────
$revMix = [];
$propRes = mysqli_query(
    $conn,
    "SELECT p.property_name,
            COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t
       ON t.property_id=p.property_id AND t.type='Income' AND YEAR(t.transaction_date)=$year
     GROUP BY p.property_id, p.property_name
     HAVING total > 0
     ORDER BY total DESC"
);
while ($r = mysqli_fetch_assoc($propRes)) {
    $pct = $totalIncome > 0 ? (int) round((float) $r['total'] / $totalIncome * 100) : 0;
    $revMix[$r['property_name']] = $pct;
}

// ── P&L summary table rows ─────────────────────────────
$monthNames = [
    '',
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December'
];
$pnlSummary = [];
$prevProfitK = null;

$currentMonth = (int) date('n');
$lastMonth = ($year === (int) date('Y')) ? $currentMonth : 12;

for ($i = 1; $i <= $lastMonth; $i++) {
    $m = $i - 1;
    $rev = $monthlyIncome[$m] * 1000;
    $exp = $monthlyExpenses[$m] * 1000;
    $pft = $rev - $exp;
    $margin = $rev > 0 ? round($pft / $rev * 100, 1) : 0;

    $vsPrior = '—';
    if ($prevProfitK !== null) {
        $prevPftAmt = $prevProfitK * 1000;
        $chg = $prevPftAmt != 0 ? round(($pft - $prevPftAmt) / abs($prevPftAmt) * 100, 1) : 0;
        $vsPrior = $chg > 0 ? "▲ $chg%" : ($chg < 0 ? "▼ " . abs($chg) . "%" : "—");
    }

    $pnlSummary[] = [
        $monthNames[$i],
        '₱ ' . number_format($rev, 0),
        '₱ ' . number_format($exp, 0),
        '₱ ' . number_format($pft, 0),
        $margin . '%',
        $vsPrior,
    ];
    $prevProfitK = $pft / 1000;
}

echo json_encode([
    'success' => true,
    'financial_data' => [
        'revenue' => array_values($monthlyIncome),
        'expenses' => array_values($monthlyExpenses),
        'maintenance' => array_values($monthlyMaint),
        'utilities' => array_values($monthlyUtil),
        'salaries' => array_values($monthlySalaries),
        'admin' => array_values($monthlyAdmin),
        'revenue_mix' => $revMix,
        'pnl_summary' => $pnlSummary,
    ],
    'stats' => [
        'total_revenue' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_profit' => $netProfit,
        'roi' => $roi,
        'revenue_growth' => $revenueGrowth,
        'expense_growth' => $expenseGrowth,
        'profit_growth' => $profitGrowth,
    ],
]);