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

$year = (int) date('Y');
$currentMonth = (int) date('n');

$totalRevenue = (float) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=$year"
))['v'] ?? 0);

$lastYearRevenue = (float) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=" . ($year - 1)
))['v'] ?? 0);

$revGrowth = $lastYearRevenue > 0
  ? round((($totalRevenue - $lastYearRevenue) / $lastYearRevenue) * 100, 1)
  : 0;

$totalUnits = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM units"
))['c'] ?? 1);
$occupiedUnits = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM units WHERE status='occupied'"
))['c'] ?? 0);
$vacantUnits = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM units WHERE status='vacant'"
))['c'] ?? 0);
$maintenanceUnits = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM units WHERE status='maintenance'"
))['c'] ?? 0);
$occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;

$totalBookings = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=$year"
))['c'] ?? 0);
$lastYearBookings = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=" . ($year - 1)
))['c'] ?? 0);
$bookingGrowth = $lastYearBookings > 0
  ? round((($totalBookings - $lastYearBookings) / $lastYearBookings) * 100, 1)
  : 0;

$cancelledBookings = (int) (mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c FROM bookings WHERE status='cancelled' AND YEAR(created_at)=$year"
))['c'] ?? 0);
$cancelRate = $totalBookings > 0 ? round(($cancelledBookings / $totalBookings) * 100, 1) : 0;

$revByPropRes = mysqli_query(
  $conn,
  "SELECT p.property_name, COALESCE(SUM(t.amount),0) AS total
     FROM properties p
     LEFT JOIN transactions t ON t.property_id=p.property_id
         AND t.type='Income' AND YEAR(t.transaction_date)=$year
     GROUP BY p.property_id
     ORDER BY total DESC
     LIMIT 6"
);
$revByPropLabels = [];
$revByPropData = [];
while ($r = mysqli_fetch_assoc($revByPropRes)) {
  $revByPropLabels[] = $r['property_name'];
  $revByPropData[] = (float) $r['total'];
}

$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthBookings = array_fill(0, 12, 0);
$mbRes = mysqli_query(
  $conn,
  "SELECT MONTH(checkin_date)-1 AS m, COUNT(*) AS c
     FROM bookings
     WHERE status NOT IN('cancelled') AND YEAR(checkin_date)=$year
     GROUP BY m"
);
while ($r = mysqli_fetch_assoc($mbRes))
  $monthBookings[(int) $r['m']] = (int) $r['c'];

$activeMonthLabels = array_slice($monthLabels, 0, $currentMonth);
$activeMonthBookings = array_slice($monthBookings, 0, $currentMonth);

$revTrendData = array_fill(0, 12, null);
$rtRes = mysqli_query(
  $conn,
  "SELECT MONTH(transaction_date)-1 AS m, SUM(amount) AS v
     FROM transactions
     WHERE type='Income' AND YEAR(transaction_date)=$year
     GROUP BY m"
);
while ($r = mysqli_fetch_assoc($rtRes)) {
  $revTrendData[(int) $r['m']] = (float) $r['v'];
}

$revActual = [];
$revProjection = [];
for ($i = 0; $i < 12; $i++) {
  if ($i < $currentMonth) {
    $revActual[] = $revTrendData[$i];
    $revProjection[] = null;
  } else {
    $revActual[] = null;
    $revProjection[] = null;
  }
}

$sourceRes = mysqli_query(
  $conn,
  "SELECT COALESCE(NULLIF(booking_source,''),'Direct') AS source, COUNT(*) AS total
     FROM bookings
     WHERE YEAR(created_at)=$year
     GROUP BY source
     ORDER BY total DESC"
);
$sourceLabels = [];
$sourceData = [];
$sourceColors = ['#2563c4', '#2ECC71', '#deaf37', '#1a3d7c', '#93c5fd', '#E74C3C'];
$si = 0;
while ($r = mysqli_fetch_assoc($sourceRes)) {
  $sourceLabels[] = $r['source'];
  $sourceData[] = (int) $r['total'];
  $si++;
}
if (empty($sourceLabels)) {
  $payRes = mysqli_query(
    $conn,
    "SELECT COALESCE(NULLIF(payment_method,''),'Unknown') AS source, COUNT(*) AS total
         FROM bookings WHERE YEAR(created_at)=$year
         GROUP BY source ORDER BY total DESC"
  );
  while ($r = mysqli_fetch_assoc($payRes)) {
    $sourceLabels[] = $r['source'];
    $sourceData[] = (int) $r['total'];
  }
}

$sourceTotal = array_sum($sourceData) ?: 1;
?>

<div class="page-header">
  <div class="top-header">
    <h2>Analytics</h2>
    <div class="page-header-sub">Performance insights across all properties — <?= $year ?></div>
  </div>
  <div style="display:flex;gap:8px;">
    <button class="btn btn-primary" onclick="window.print()">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
      </svg>
      Export
    </button>
  </div>
</div>

<div class="page-inner">
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
        <div class="card-header"><span class="card-title">Booking Source Breakdown</span></div>
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
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

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  window.__PS_ANALYTICS__ = {
    revByPropLabels: <?= json_encode($revByPropLabels) ?>,
    revByPropData: <?= json_encode($revByPropData) ?>,
    activeMonthLabels: <?= json_encode($activeMonthLabels) ?>,
    activeMonthBookings: <?= json_encode($activeMonthBookings) ?>,
    srcLabels: <?= json_encode($sourceLabels) ?>,
    srcData: <?= json_encode($sourceData) ?>,
    monthLabels: <?= json_encode($monthLabels) ?>,
    revActual: <?= json_encode(array_values($revActual)) ?>
  };
</script>
<script src="../../assets/js/admin/analytics.js"></script>

<?php include '../../includes/layout_close.php'; ?>