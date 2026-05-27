<?php
/**
 * lib/admin/financial_reports_data.php
 * Data layer for pages/admin/financial_reports.php
 * Requires: $conn (mysqli)
 */

$selected_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

function getAvailableYears(mysqli $conn): array
{
    $res = $conn->query(
        "SELECT DISTINCT YEAR(transaction_date) AS y FROM transactions
         UNION SELECT DISTINCT YEAR(expense_date) FROM expenses
         ORDER BY y DESC LIMIT 10"
    );
    $years = [];
    while ($r = $res->fetch_assoc())
        $years[] = (int) $r['y'];
    if (!in_array((int) date('Y'), $years))
        $years[] = (int) date('Y');
    rsort($years);
    return $years;
}

function getFinancialDataFromDB(mysqli $conn, int $year): array
{
    $monthlyIncome = array_fill(0, 12, 0.0);
    $monthlyExpenses = array_fill(0, 12, 0.0);
    $monthlyMaint = array_fill(0, 12, 0.0);
    $monthlyUtil = array_fill(0, 12, 0.0);
    $monthlySal = array_fill(0, 12, 0.0);
    $monthlyAdm = array_fill(0, 12, 0.0);

    $stmt = $conn->prepare(
        "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
         FROM transactions WHERE type='Income' AND YEAR(transaction_date)=? GROUP BY m"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc())
        $monthlyIncome[(int) $r['m']] = (float) $r['v'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT MONTH(expense_date)-1 AS m,
                COALESCE(SUM(amount),0) AS total,
                COALESCE(SUM(CASE WHEN expense_category='Maintenance' THEN amount END),0) AS maint,
                COALESCE(SUM(CASE WHEN expense_category='Utilities'   THEN amount END),0) AS util,
                COALESCE(SUM(CASE WHEN expense_category='Salaries'    THEN amount END),0) AS sal,
                COALESCE(SUM(CASE WHEN expense_category='Admin'       THEN amount END),0) AS adm
         FROM expenses WHERE YEAR(expense_date)=? GROUP BY m"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $m = (int) $r['m'];
        $monthlyExpenses[$m] = (float) $r['total'];
        $monthlyMaint[$m] = (float) $r['maint'];
        $monthlyUtil[$m] = (float) $r['util'];
        $monthlySal[$m] = (float) $r['sal'];
        $monthlyAdm[$m] = (float) $r['adm'];
    }
    $stmt->close();

    $totalIncome = array_sum($monthlyIncome);
    $revenue_mix = [];
    $stmt = $conn->prepare(
        "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
         FROM properties p
         LEFT JOIN transactions t ON t.property_id=p.property_id
           AND t.type='Income' AND YEAR(t.transaction_date)=?
         GROUP BY p.property_id, p.property_name HAVING total>0 ORDER BY total DESC"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $pct = $totalIncome > 0 ? (int) round((float) $r['total'] / $totalIncome * 100) : 0;
        $revenue_mix[$r['property_name']] = $pct;
    }
    $stmt->close();

    $month_names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $pnl_summary = [];
    $prev_profit = null;
    $last_month = ($year === (int) date('Y')) ? (int) date('n') : 12;

    for ($i = 1; $i <= $last_month; $i++) {
        $m = $i - 1;
        $rev = $monthlyIncome[$m];
        $exp = $monthlyExpenses[$m];
        $pft = $rev - $exp;
        $margin = $rev > 0 ? round($pft / $rev * 100, 1) : 0;
        $vs_prior = '—';
        if ($prev_profit !== null && $prev_profit != 0) {
            $chg = round(($pft - $prev_profit) / abs($prev_profit) * 100, 1);
            $vs_prior = $chg > 0 ? "▲ $chg%" : ($chg < 0 ? "▼ " . abs($chg) . "%" : "—");
        }
        $pnl_summary[] = [
            $month_names[$i],
            '₱ ' . number_format($rev, 0),
            '₱ ' . number_format($exp, 0),
            '₱ ' . number_format($pft, 0),
            $margin . '%',
            $vs_prior,
        ];
        $prev_profit = $pft;
    }

    return [
        'revenue' => array_values($monthlyIncome),
        'expenses' => array_values($monthlyExpenses),
        'maintenance' => array_values($monthlyMaint),
        'utilities' => array_values($monthlyUtil),
        'salaries' => array_values($monthlySal),
        'admin' => array_values($monthlyAdm),
        'revenue_mix' => $revenue_mix,
        'pnl_summary' => $pnl_summary,
    ];
}

function calculateStatsFromDB(mysqli $conn, int $year): array
{
    $prevYear = $year - 1;

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE type='Income' AND YEAR(transaction_date)=?"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $totalIncome = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE YEAR(expense_date)=?"
    );
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $totalExpenses = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $netProfit = $totalIncome - $totalExpenses;
    $roi = $totalIncome > 0 ? round($netProfit / $totalIncome * 100, 1) : 0;

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE type='Income' AND YEAR(transaction_date)=?"
    );
    $stmt->bind_param('i', $prevYear);
    $stmt->execute();
    $prevInc = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS v FROM expenses WHERE YEAR(expense_date)=?"
    );
    $stmt->bind_param('i', $prevYear);
    $stmt->execute();
    $prevExp = (float) ($stmt->get_result()->fetch_assoc()['v'] ?? 0);
    $stmt->close();

    $prevNet = $prevInc - $prevExp;
    return [
        'total_revenue' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_profit' => $netProfit,
        'roi' => $roi,
        'revenue_growth' => $prevInc > 0 ? round(($totalIncome - $prevInc) / $prevInc * 100, 1) : 0,
        'expense_growth' => $prevExp > 0 ? round(($totalExpenses - $prevExp) / $prevExp * 100, 1) : 0,
        'profit_growth' => $prevNet > 0 ? round(($netProfit - $prevNet) / $prevNet * 100, 1) : 0,
    ];
}

function formatCurrency(float $amount): string
{
    if ($amount >= 1_000_000)
        return '₱ ' . number_format($amount / 1_000_000, 2) . 'M';
    return '₱ ' . number_format($amount, 0);
}

$available_years = getAvailableYears($conn);
$financial_data = getFinancialDataFromDB($conn, $selected_year);
$stats = calculateStatsFromDB($conn, $selected_year);

if (!$financial_data)
    $financial_data = ['revenue' => [], 'expenses' => [], 'maintenance' => [], 'utilities' => [], 'salaries' => [], 'admin' => [], 'revenue_mix' => [], 'pnl_summary' => []];