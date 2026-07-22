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
require_once '../../lib/admin-queries/financial_reports_queries.php';  // ← all SQL lives here
?>
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/report-generate.css">

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
      <button class="btn btn-secondary" id="repgenOpenBtn" type="button">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>Generate Report
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

      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Total Refunds (YTD)</div>
          <div class="stat-value" id="totalRefunds"><?php echo formatCurrency($stats['total_refunds']); ?></div>
          <span class="stat-trend neutral" id="refundsNote">Completed &amp; Processing</span>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="1 4 1 10 7 10" />
            <path d="M3.51 15a9 9 0 1 0 .49-3.67" />
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
              <th>Refunds</th>
              <th>Net Profit</th>
              <th>Margin</th>
              <th>vs Prior Month</th>
            </tr>
          </thead>
          <tbody id="pnlTableBody">
            <?php if (!empty($financial_data['pnl_summary'])): ?>
              <?php foreach ($financial_data['pnl_summary'] as $row):
                $isEmpty = $row[1] === '₱ 0' && $row[2] === '₱ 0';
                $margin = (float) $row[5];
                $marginColor = $margin >= 80 ? '#2ECC71' : ($margin >= 50 ? '#2563c4' : ($margin > 0 ? '#deaf37' : '#94a3b8'));
                ?>
                <tr style="<?= $isEmpty ? 'opacity:0.4;' : '' ?>">
                  <td style="font-weight:600;"><?= htmlspecialchars($row[0]) ?></td>
                  <td style="color:var(--success);font-weight:600;"><?= htmlspecialchars($row[1]) ?></td>
                  <td style="color:var(--danger);"><?= htmlspecialchars($row[2]) ?></td>
                  <td style="color:var(--danger);"><?= htmlspecialchars($row[3]) ?></td>
                  <td style="font-weight:700;"><?= htmlspecialchars($row[4]) ?></td>
                  <td>
                    <?php if (!$isEmpty): ?>
                      <span
                        style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;background:<?= $marginColor ?>18;color:<?= $marginColor ?>;">
                        <?= htmlspecialchars($row[5]) ?>
                      </span>
                    <?php else: ?>
                      <span style="color:#94a3b8;">—</span>
                    <?php endif; ?>
                  </td>
                  <td
                    style="color:<?= str_contains($row[6], '▲') ? 'var(--success)' : (str_contains($row[6], '▼') ? 'var(--danger)' : 'var(--text-soft)') ?>;font-weight:600;">
                    <?= htmlspecialchars($row[6]) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:var(--text-soft);">
                  No data available for <?= $selected_year ?>
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

<script>
  window.__PS_FINANCIAL__ = window.__PS_DATA__ || {};
  window.__PS_FINANCIAL__.chartData = <?php echo json_encode($financial_data); ?>;
  window.__PS_FINANCIAL__.selectedYear = <?php echo json_encode($selected_year); ?>;
  window.__PS_FINANCIAL__.years = <?php echo json_encode($available_years); ?>;
  window.__PS_FINANCIAL__.hasFinancialActivity = <?php echo json_encode($hasFinancialActivity); ?>;
  window.__PS_FINANCIAL__.hasRevenueMix = <?php echo json_encode($hasRevenueMix); ?>;
  window.__PS_FINANCIAL__.hasExpenseBreakdown = <?php echo json_encode($hasExpenseBreakdown); ?>;
</script>
<script src="../../assets/js/admin/financial_reports.js"></script>

<?php $repgen_title = 'Financial Report';
include '../../includes/_report_generate_modal.php'; ?>
<script src="../../assets/js/admin/report-generate.js"></script>
<script>
  initReportGenerator({ type: 'financial' });
</script>

<?php
$conn->close();
include '../../includes/layout_close.php';
?>