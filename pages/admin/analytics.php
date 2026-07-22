<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>

<body>
  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/analytics-inline.js"></script>
</body></html>';
  exit;
}

$page_title = 'Analytics';
$active_page = 'analytics';
include '../../includes/db.php';
include '../../includes/layout_open.php';
include '../../lib/admin-queries/analytics_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/header.css">
<link rel="stylesheet" href="../../assets/css/admin-css/analytics.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Analytics</h1>
      <p class="dash-subtitle">Performance insights across all properties — <strong>
          <?= $year ?>.
        </strong></p>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Total Revenue</div>
          <div class="stat-value">
            ₱ <?= number_format($totalRevenue / 1000, 0) ?>K
            <span class="stat-trend <?= $revGrowth >= 0 ? 'up' : 'down' ?>">
              <?= $revGrowth >= 0 ? '↑' : '↓' ?> <?= abs($revGrowth) ?>%
            </span>
          </div>
          <div class="stat-sub">vs ₱<?= number_format($lastYearRevenue / 1000, 0) ?>K last year</div>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="12" y1="1" x2="12" y2="23" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Avg. Occupancy</div>
          <div class="stat-value"><?= $occupancyRate ?>%</div>
          <div class="stat-sub"><?= $occupiedUnits ?> of <?= $totalUnits ?> units occupied</div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">Total Bookings</div>
          <div class="stat-value">
            <?= number_format($totalBookings) ?>
            <span class="stat-trend <?= $bookingGrowth >= 0 ? 'up' : 'down' ?>">
              <?= $bookingGrowth >= 0 ? '↑' : '↓' ?> <?= abs($bookingGrowth) ?>%
            </span>
          </div>
          <div class="stat-sub">vs <?= $lastYearBookings ?> last year</div>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Cancellation Rate</div>
          <div class="stat-value"><?= $cancelRate ?>%</div>
          <div class="stat-sub"><?= $cancelledBookings ?> cancelled this year</div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
        </div>
      </div>
    </div>

    <div class="two-col">
      <div class="card">
        <div class="card-header"><span class="card-title">Revenue by Property</span></div>
        <div class="chart-wrap" style="height:220px;"><canvas id="revByPropChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Monthly Bookings (<?= $year ?>)</span></div>
        <div class="chart-wrap" style="height:220px;"><canvas id="monthlyOccChart"></canvas></div>
      </div>
    </div>

    <div class="two-col">
      <div class="card">
        <div class="card-header"><span class="card-title">Booking Status Breakdown</span></div>
        <div id="sourceRow" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
          <div class="chart-wrap" style="height:180px;width:180px;flex-shrink:0;">
            <canvas id="sourceDonut"></canvas>
          </div>
          <div class="legend-list" style="flex:1;min-width:140px;" id="sourceLegend"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Revenue Trend (<?= $year ?>)</span></div>
        <div class="chart-wrap" style="height:180px;"><canvas id="revTrendChart"></canvas></div>
      </div>
    </div>

    <div class="two-col" style="margin-top:24px;">

      <div class="card">
        <div class="card-header">
          <span class="card-title">Monthly Bookings (
            <?= $year ?>)
          </span>
        </div>
        <table class="analytics-table">
          <thead>
            <tr>
              <th>Month</th>
              <th>Bookings</th>
              <th>Forecast</th>
              <th>Δ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthLabels as $i => $mon):
              $actual = $monthBookings[$i] ?? 0;
              $forecast = $forecastBookings[$i] ?? null;
              $delta = ($forecast !== null && $i < $currentMonth) ? $actual - round($forecast) : null;
              ?>
              <tr>
                <td>
                  <?= $mon ?>
                </td>
                <td>
                  <?= $i < $currentMonth ? $actual : '<span class="muted">—</span>' ?>
                </td>
                <td>
                  <?= $forecast !== null ? round($forecast) : '<span class="muted">—</span>' ?>
                </td>
                <td>
                  <?php if ($delta !== null): ?>
                    <span class="trend-badge <?= $delta >= 0 ? 'up' : 'down' ?>">
                      <?= $delta >= 0 ? '+' : '' ?>
                      <?= $delta ?>
                    </span>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Revenue Trend (
            <?= $year ?>)
          </span>
        </div>
        <table class="analytics-table">
          <thead>
            <tr>
              <th>Month</th>
              <th>Actual</th>
              <th>Forecast</th>
              <th>Δ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($monthLabels as $i => $mon):
              $actual = $revActual[$i];
              $forecast = $forecastRev[$i] ?? null;
              $delta = ($actual !== null && $forecast !== null) ? $actual - $forecast : null;
              ?>
              <tr>
                <td>
                  <?= $mon ?>
                </td>
                <td>
                  <?= $actual !== null ? '₱' . number_format($actual, 0) : '<span class="muted">—</span>' ?>
                </td>
                <td>
                  <?= $forecast !== null ? '₱' . number_format($forecast, 0) : '<span class="muted">—</span>' ?>
                </td>
                <td>
                  <?php if ($delta !== null): ?>
                    <span class="trend-badge <?= $delta >= 0 ? 'up' : 'down' ?>">
                      <?= $delta >= 0 ? '+' : '' ?>₱
                      <?= number_format(abs($delta), 0) ?>
                    </span>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  window.__PS_ANALYTICS__ = {
    revByPropLabels: <?= json_encode($revByPropLabels) ?>,
    revByPropData: <?= json_encode($revByPropData) ?>,
    revByPropPct: <?= json_encode($revByPropPct) ?>,
    activeMonthLabels: <?= json_encode($monthLabels) ?>,
    activeMonthBookings: <?= json_encode($monthBookings) ?>,
    forecastBookings: <?= json_encode(array_values($forecastBookings)) ?>,
    srcLabels: <?= json_encode($sourceLabels) ?>,
    srcData: <?= json_encode($sourceData) ?>,
    monthLabels: <?= json_encode($monthLabels) ?>,
    revActual: <?= json_encode(array_values($revActual)) ?>,
    forecastRev: <?= json_encode(array_values($forecastRev)) ?>,
    currentMonth: <?= $currentMonth ?>,
    hasRevenueData: <?= json_encode($hasRevenueData) ?>,
    hasBookingData: <?= json_encode($hasBookingData) ?>
  };
</script>
<script src="../../assets/js/admin/analytics.js"></script>

<?php include '../../includes/layout_close.php'; ?>