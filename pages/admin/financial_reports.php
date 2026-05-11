<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/financial_reports-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Financial Reports';
$active_page = 'financial_reports';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

function getAvailableYears($conn)
{
  $res = mysqli_query(
    $conn,
    "SELECT DISTINCT YEAR(transaction_date) AS y FROM transactions
     UNION SELECT DISTINCT YEAR(expense_date) FROM expenses
     ORDER BY y DESC LIMIT 10"
  );
  $years = [];
  while ($r = mysqli_fetch_assoc($res))
    $years[] = (int) $r['y'];
  if (!in_array((int) date('Y'), $years))
    $years[] = (int) date('Y');
  rsort($years);
  return $years;
}

function getFinancialDataFromDB($conn, $year)
{
  $monthlyIncome = array_fill(0, 12, 0.0);
  $monthlyExpenses = array_fill(0, 12, 0.0);
  $monthlyMaint = array_fill(0, 12, 0.0);
  $monthlyUtil = array_fill(0, 12, 0.0);
  $monthlySal = array_fill(0, 12, 0.0);
  $monthlyAdm = array_fill(0, 12, 0.0);

  $incRes = mysqli_query(
    $conn,
    "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
     FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$year GROUP BY m"
  );
  while ($r = mysqli_fetch_assoc($incRes))
    $monthlyIncome[(int) $r['m']] = (float) $r['v'] / 1000;

  $expRes = mysqli_query(
    $conn,
    "SELECT MONTH(expense_date)-1 AS m,
            COALESCE(SUM(amount),0) AS total,
            COALESCE(SUM(CASE WHEN expense_category='Maintenance' THEN amount END),0) AS maint,
            COALESCE(SUM(CASE WHEN expense_category='Utilities'   THEN amount END),0) AS util,
            COALESCE(SUM(CASE WHEN expense_category='Salaries'    THEN amount END),0) AS sal,
            COALESCE(SUM(CASE WHEN expense_category='Admin'       THEN amount END),0) AS adm
     FROM expenses WHERE YEAR(expense_date)=$year GROUP BY m"
  );
  while ($r = mysqli_fetch_assoc($expRes)) {
    $m = (int) $r['m'];
    $monthlyExpenses[$m] = (float) $r['total'] / 1000;
    $monthlyMaint[$m] = (float) $r['maint'] / 1000;
    $monthlyUtil[$m] = (float) $r['util'] / 1000;
    $monthlySal[$m] = (float) $r['sal'] / 1000;
    $monthlyAdm[$m] = (float) $r['adm'] / 1000;
  }

  $totalIncome = array_sum($monthlyIncome) * 1000;
  $revenue_mix = [];
  $propRes = mysqli_query(
    $conn,
    "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t
       ON t.property_id=p.property_id AND t.type='Income' AND YEAR(t.transaction_date)=$year
     GROUP BY p.property_id, p.property_name HAVING total>0 ORDER BY total DESC"
  );
  while ($r = mysqli_fetch_assoc($propRes)) {
    $pct = $totalIncome > 0 ? (int) round((float) $r['total'] / $totalIncome * 100) : 0;
    $revenue_mix[$r['property_name']] = $pct;
  }

  $month_names = [
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
  $pnl_summary = [];
  $prev_profit = null;
  $last_month = ($year === (int) date('Y')) ? (int) date('n') : 12;

  for ($i = 1; $i <= $last_month; $i++) {
    $m = $i - 1;
    $rev = $monthlyIncome[$m] * 1000;
    $exp = $monthlyExpenses[$m] * 1000;
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

function calculateStatsFromDB($conn, $year)
{
  $prevYear = $year - 1;
  $totalIncome = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=$year"
  ))['v'] ?? 0);
  $totalExpenses = (float) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount),0) AS v FROM expenses
     WHERE YEAR(expense_date)=$year"
  ))['v'] ?? 0);
  $netProfit = $totalIncome - $totalExpenses;
  $roi = $totalIncome > 0 ? round($netProfit / $totalIncome * 100, 1) : 0;

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

function formatCurrency($amount)
{
  if ($amount >= 1000000)
    return '₱ ' . number_format($amount / 1000000, 2) . 'M';
  if ($amount >= 1000)
    return '₱ ' . number_format($amount / 1000, 2) . 'K';
  return '₱ ' . number_format($amount, 0);
}

$available_years = getAvailableYears($conn);
$financial_data = getFinancialDataFromDB($conn, $selected_year);
$stats = calculateStatsFromDB($conn, $selected_year);

if (!$financial_data) {
  $financial_data = [
    'revenue' => [],
    'expenses' => [],
    'maintenance' => [],
    'utilities' => [],
    'salaries' => [],
    'admin' => [],
    'revenue_mix' => [],
    'pnl_summary' => []
  ];
}
?>
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Financial Reports</h1>
      <p class="dash-subtitle">Income, expenses and profitability overview.</p>
    </div>
    <div class="dash-header-actions">
      <select id="yearSelect" onchange="handleYearChange(this.value)"
        style="padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
        <?php foreach ($available_years as $year): ?>
          <option value="<?php echo $year; ?>" <?php echo $selected_year === (int) $year ? 'selected' : ''; ?>>
            <?php echo $year; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-secondary" onclick="exportPDF()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>Export PDF
      </button>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Total Revenue (YTD)</div>
          <div class="stat-value" id="totalRevenue"><?php echo formatCurrency($stats['total_revenue']); ?></div>
          <span class="stat-trend up" id="revenueGrowth">↑ <?php echo $stats['revenue_growth']; ?>%</span>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
            <polyline points="17 6 23 6 23 12" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Total Expenses (YTD)</div>
          <div class="stat-value" id="totalExpenses"><?php echo formatCurrency($stats['total_expenses']); ?></div>
          <span class="stat-trend down" id="expenseGrowth">↑ <?php echo $stats['expense_growth']; ?>%</span>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
            <polyline points="17 18 23 18 23 12" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Net Profit (YTD)</div>
          <div class="stat-value" id="netProfit"><?php echo formatCurrency($stats['net_profit']); ?></div>
          <span class="stat-trend up" id="profitGrowth"><?php echo $stats['profit_growth']; ?>%</span>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="12" y1="1" x2="12" y2="23" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">ROI</div>
          <div class="stat-value" id="roi"><?php echo $stats['roi']; ?>%</div>
          <span class="stat-trend up">↑ 3.2%</span>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
          </svg>
        </div>
      </div>
    </div>

    <div class="two-col">
      <div class="card" style="flex:2;">
        <div class="card-header"><span class="card-title">Monthly Profit & Loss</span></div>
        <div class="chart-wrap" style="height:220px;">
          <canvas id="plChart"></canvas>
        </div>
      </div>

      <div class="card" style="flex:1;">
        <div class="card-header"><span class="card-title">Revenue Mix</span></div>
        <div class="chart-wrap" style="height:160px;">
          <canvas id="revMixDonut"></canvas>
        </div>
        <div class="legend-list" style="margin-left:12px;" id="revenueMixLegend">
          <?php
          $colors = ['#2563c4', '#2ECC71', '#deaf37'];
          $index = 0;
          if (!empty($financial_data['revenue_mix'])):
            foreach ($financial_data['revenue_mix'] as $property => $percentage):
              $color = $colors[$index % count($colors)];
              $index++;
              ?>
              <div class="legend-item">
                <div class="legend-dot" style="background:<?php echo $color; ?>;"></div>
                <span class="legend-label"><?php echo htmlspecialchars($property); ?></span>
                <span class="legend-val"><?php echo $percentage; ?>%</span>
              </div>
            <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Expense Breakdown by Category</span></div>
      <div class="chart-wrap" style="height:180px;">
        <canvas id="expBreakChart"></canvas>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Monthly Profit & Loss Summary</span></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Month</th>
              <th>Revenue</th>
              <th>Expenses</th>
              <th>Net Profit</th>
              <th>Margin</th>
              <th>vs Prior Month</th>
            </tr>
          </thead>
          <tbody id="pnlTableBody">
            <?php if (!empty($financial_data['pnl_summary'])): ?>
              <?php foreach ($financial_data['pnl_summary'] as $row): ?>
                <tr>
                  <td style="font-weight:600;"><?php echo htmlspecialchars($row[0]); ?></td>
                  <td style="color:var(--success);font-weight:600;"><?php echo htmlspecialchars($row[1]); ?></td>
                  <td style="color:var(--danger);"><?php echo htmlspecialchars($row[2]); ?></td>
                  <td style="font-weight:700;"><?php echo htmlspecialchars($row[3]); ?></td>
                  <td><?php echo htmlspecialchars($row[4]); ?></td>
                  <td
                    style="color:<?php echo str_contains($row[5], '▲') ? 'var(--success)' : (str_contains($row[5], '▼') ? 'var(--danger)' : 'var(--text-soft)'); ?>; font-weight:600;">
                    <?php echo htmlspecialchars($row[5]); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align:center;padding:20px;color:var(--text-soft);">
                  No data available for <?php echo $selected_year; ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
  window.__PS_FINANCIAL__ = window.__PS_DATA__ || {};
  window.__PS_FINANCIAL__.chartData = <?php echo json_encode($financial_data); ?>;
  window.__PS_FINANCIAL__.selectedYear = <?php echo json_encode($selected_year); ?>;
  window.__PS_FINANCIAL__.years = <?php echo json_encode($available_years); ?>;
</script>
<script src="../../assets/js/admin/financial_reports.js"></script>

<?php
$conn->close();
include '../../includes/layout_close.php';
?>