<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>

<body>
  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/index-inline.js"></script>
</body></html>';
  exit;
}

$page_title = 'Dashboard';
$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — PropSight</title>
  <link rel="stylesheet" href="../../assets/css/admin-css/style.css" />
  <link rel="stylesheet" href="../../assets/css/admin-css/responsive-enhanced.css" />
  <link rel="icon" type="image/png" href="../../assets/images/final logo.png" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="../../assets/css/admin-css/index-inline.css">
</head>

<body>

  <?php
  include '../../includes/sidebar.php';
  include '../../includes/db.php';

  $year = (int) date('Y');
  $thisMonth = date('Y-m-01');
  $lastMonthFrom = date('Y-m-01', strtotime('-1 month'));
  $lastMonthTo = date('Y-m-t', strtotime('-1 month'));

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

  $totalUnits = max(1, (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM units"
  ))['c'] ?? 1));
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
  $occupancyRate = round(($occupiedUnits / $totalUnits) * 100);

  $totalBookings = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=$year"
  ))['c'] ?? 0);
  $pendingBookings = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE status='pending'"
  ))['c'] ?? 0);
  $lastYearBookings = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=" . ($year - 1)
  ))['c'] ?? 0);
  $bookingGrowth = $lastYearBookings > 0
    ? round((($totalBookings - $lastYearBookings) / $lastYearBookings) * 100, 1)
    : 0;

  $cancelledThisMonth = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings
     WHERE status='cancelled' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
  ))['c'] ?? 0);
  $totalThisMonth = max(1, (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings
     WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
  ))['c'] ?? 1));
  $cancelRate = round(($cancelledThisMonth / $totalThisMonth) * 100, 1);

  $revData = [];
  $expData = [];
  $chartLabels = [];
  for ($i = 7; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $ty = (int) date('Y', $ts);
    $tm = (int) date('n', $ts);
    $chartLabels[] = date('M', $ts);

    $rv = (float) (mysqli_fetch_assoc(mysqli_query(
      $conn,
      "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
         WHERE type='Income' AND YEAR(transaction_date)=$ty AND MONTH(transaction_date)=$tm"
    ))['v'] ?? 0);

    $ex = (float) (mysqli_fetch_assoc(mysqli_query(
      $conn,
      "SELECT COALESCE(SUM(amount),0) AS v FROM expenses
         WHERE YEAR(expense_date)=$ty AND MONTH(expense_date)=$tm"
    ))['v'] ?? 0);

    $revData[] = round($rv / 1000, 1);
    $expData[] = round($ex / 1000, 1);
  }

  $propRes = mysqli_query(
    $conn,
    "SELECT p.property_id, p.property_name, p.address,
            COUNT(u.unit_id) AS total_units,
            SUM(u.status='occupied') AS occupied
     FROM properties p
     LEFT JOIN units u ON u.property_id = p.property_id
     GROUP BY p.property_id
     ORDER BY occupied DESC
     LIMIT 4"
  );
  $properties = [];
  while ($r = mysqli_fetch_assoc($propRes))
    $properties[] = $r;

  $taskRes = mysqli_query(
    $conn,
    "SELECT 
         m.issue_description AS title, 
         p.property_name, 
         m.priority, 
         m.request_status AS status
     FROM maintenance_requests m
     LEFT JOIN units u ON u.unit_id = m.unit_id
     LEFT JOIN properties p ON p.property_id = u.property_id
     ORDER BY m.request_date DESC
     LIMIT 5"
  );
  $tasks = [];
  while ($r = mysqli_fetch_assoc($taskRes))
    $tasks[] = $r;

  $taskOpenCount = 0;
  $taskInProgressCount = 0;
  foreach ($tasks as $t) {
    $taskStatus = strtolower(trim((string) ($t['status'] ?? '')));
    if ($taskStatus === 'open')
      $taskOpenCount++;
    if ($taskStatus === 'in_progress')
      $taskInProgressCount++;
  }

  $taskStatusMap = [
    'open' => ['bg' => 'var(--danger-light)', 'color' => 'var(--danger)', 'label' => 'Urgent'],
    'in_progress' => ['bg' => 'var(--blue-50)', 'color' => 'var(--blue-500)', 'label' => 'In Progress'],
    'pending' => ['bg' => 'var(--pending-light)', 'color' => 'var(--accent-dk)', 'label' => 'Pending'],
    'completed' => ['bg' => 'var(--success-light)', 'color' => 'var(--success)', 'label' => 'Done'],
    'closed' => ['bg' => 'var(--success-light)', 'color' => 'var(--success)', 'label' => 'Closed'],
  ];
  $taskDotColor = [
    'open' => 'var(--danger)',
    'in_progress' => 'var(--blue-400)',
    'pending' => 'var(--gold)',
    'completed' => 'var(--success)',
    'closed' => 'var(--success)',
  ];

  $taskPriorityMap = [
    'high' => ['bg' => 'var(--danger-light)', 'color' => 'var(--danger)', 'label' => 'High'],
    'medium' => ['bg' => 'var(--pending-light)', 'color' => 'var(--accent-dk)', 'label' => 'Medium'],
    'low' => ['bg' => 'var(--blue-50)', 'color' => 'var(--blue-500)', 'label' => 'Low'],
  ];

  $years = [];
  $yrRes = mysqli_query(
    $conn,
    "SELECT y FROM (
      SELECT DISTINCT YEAR(transaction_date) AS y FROM transactions
      UNION
      SELECT DISTINCT YEAR(expense_date) AS y FROM expenses
    ) z
    WHERE y IS NOT NULL
    ORDER BY y DESC"
  );
  while ($yrRes && ($r = mysqli_fetch_assoc($yrRes))) {
    $years[] = (int) $r['y'];
  }
  $currentYear = (int) date('Y');
  if (!in_array($currentYear, $years, true)) {
    array_unshift($years, $currentYear);
  }
  ?>

  <div class="main">
    <div class="content" style="margin-top:5px;">
      <div class="page-inner">
        <div class="dash-page-header">
          <div class="dash-header-left">
            <h1 class="dash-title">Dashboard</h1>
            <p class="dash-subtitle">Welcome back,
              <strong><?= htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Admin') ?></strong> — here's
              what's happening with your properties today.</p>
          </div>
        </div>
        <div class="cards-area">

          <div class="stat-row">

            <div class="stat-card sc-green">
              <div class="stat-card-left">
                <div class="stat-label">Total Revenue (YTD)</div>
                <div class="stat-value"><span data-rt-kpi="total_revenue">₱
                    <?= number_format($totalRevenue / 1000, 0) ?>K</span></div>
                <span data-rt-kpi="revenue_growth" class="stat-trend <?= $revGrowth >= 0 ? 'up' : 'down' ?>">
                  <?= $revGrowth >= 0 ? '↑' : '↓' ?> <?= abs($revGrowth) ?>%
                </span>
                <div class="stat-sub">vs <span
                    data-rt-kpi="last_year_revenue">₱<?= number_format($lastYearRevenue / 1000, 0) ?>K</span> last year
                </div>
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
                <div class="stat-value"><span data-rt-kpi="occupancy_rate"><?= $occupancyRate ?>%</span></div>
                <div class="stat-sub"><span data-rt-kpi="occupied_units"><?= $occupiedUnits ?></span> of
                  <?= $totalUnits ?> units occupied
                </div>
              </div>
              <div class="stat-icon-wrap blue">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M3 9.5L12 3l9 6.5V21H3V9.5z" />
                </svg>
              </div>
            </div>

            <div class="stat-card sc-gold">
              <div class="stat-card-left">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><span data-rt-kpi="total_bookings"><?= number_format($totalBookings) ?></span>
                </div>
                <span data-rt-kpi="booking_growth" class="stat-trend <?= $bookingGrowth >= 0 ? 'up' : 'down' ?>">
                  <?= $bookingGrowth >= 0 ? '↑' : '↓' ?> <?= abs($bookingGrowth) ?>%
                </span>
                <div class="stat-sub">This year · <span data-rt-kpi="pending_bookings"><?= $pendingBookings ?></span>
                  pending</div>
              </div>
              <div class="stat-icon-wrap gold">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
              </div>
            </div>

            <div class="stat-card sc-red">
              <div class="stat-card-left">
                <div class="stat-label">Cancellation Rate</div>
                <div class="stat-value"><span data-rt-kpi="cancel_rate"><?= $cancelRate ?>%</span></div>
                <div class="stat-sub"><span data-rt-kpi="cancelled_this_month"><?= $cancelledThisMonth ?></span>
                  cancelled this month</div>
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
            <div class="card" style="flex:2;">
              <div class="card-header">
                <span class="card-title">Monthly Revenue vs Expenses</span>
                <div class="chart-controls" style="display:flex;align-items:center;gap:10px;">
                  <select id="revenueYearSelect"
                    style="height:30px;padding:4px 8px;border:1px solid #dbe2ee;border-radius:8px;background:#fff;color:#334155;font-size:12px;font-weight:600;">
                    <?php foreach ($years as $y): ?>
                      <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="toggle">
                    <div class="dot income"></div> Revenue
                  </div>
                  <div class="toggle">
                    <div class="dot expense"></div> Expenses
                  </div>
                </div>
              </div>
              <div class="chart-wrap" style="height:200px;"><canvas id="revenueChart"></canvas></div>
            </div>

            <div class="card" style="flex:1;">
              <div class="card-header"><span class="card-title">Occupancy Split</span></div>
              <div style="display:flex;flex-direction:column;align-items:center;gap:16px;">
                <div class="chart-wrap" style="height:150px;width:150px;">
                  <canvas id="occupancyDonut"></canvas>
                </div>
                <div class="legend-list" style="width:100%;">
                  <div class="legend-item">
                    <div class="legend-dot" style="background:var(--blue-400);"></div>
                    <span class="legend-label">Occupied</span>
                    <span class="legend-val" data-rt-kpi="occupied_units"><?= $occupiedUnits ?></span>
                    <span class="legend-pct" data-rt-kpi="occupied_pct">(<?= $occupancyRate ?>%)</span>
                  </div>
                  <div class="legend-item">
                    <div class="legend-dot" style="background:var(--blue-100);"></div>
                    <span class="legend-label">Vacant</span>
                    <span class="legend-val" data-rt-kpi="vacant_units"><?= $vacantUnits ?></span>
                    <span class="legend-pct"
                      data-rt-kpi="vacant_pct">(<?= $totalUnits > 0 ? round($vacantUnits / $totalUnits * 100) : 0 ?>%)</span>
                  </div>
                  <div class="legend-item">
                    <div class="legend-dot" style="background:var(--danger);"></div>
                    <span class="legend-label">Maintenance</span>
                    <span class="legend-val" data-rt-kpi="maintenance_units"><?= $maintenanceUnits ?></span>
                    <span class="legend-pct"
                      data-rt-kpi="maintenance_pct">(<?= $totalUnits > 0 ? round($maintenanceUnits / $totalUnits * 100) : 0 ?>%)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="two-col">
            <div class="card">
              <div class="card-header">
                <div class="card-head-main">
                  <span class="card-title">Properties</span>
                  <span class="card-head-meta"><?= count($properties) ?> shown</span>
                </div>
              </div>
              <div class="prop-list" id="rt-properties-list">
                <?php if (empty($properties)): ?>
                  <div class="dashboard-empty">No properties found.
                  </div>
                <?php else: ?>
                  <?php foreach ($properties as $prop):
                    $propOcc = $prop['total_units'] > 0 ? round($prop['occupied'] / $prop['total_units'] * 100) : 0;
                    $barColor = $propOcc >= 80 ? 'var(--success)' : ($propOcc >= 50 ? 'var(--gold)' : 'var(--blue-400)');
                    ?>
                    <div class="prop-item">
                      <div class="prop-thumb"
                        style="background:var(--blue-50);color:var(--blue-400);display:flex;align-items:center;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                          stroke-linecap="round" stroke-linejoin="round">
                          <rect x="3" y="3" width="7" height="7" rx="1" />
                          <rect x="14" y="3" width="7" height="7" rx="1" />
                          <rect x="14" y="14" width="7" height="7" rx="1" />
                          <rect x="3" y="14" width="7" height="7" rx="1" />
                        </svg>
                      </div>
                      <div class="prop-info">
                        <div class="name"><?= htmlspecialchars($prop['property_name']) ?></div>
                        <div class="addr"><?= htmlspecialchars($prop['address'] ?? '') ?></div>
                        <div class="prop-bar-wrap">
                          <div class="prop-bar" style="width:<?= $propOcc ?>%;background:<?= $barColor ?>;"></div>
                        </div>
                      </div>
                      <div class="prop-score">
                        <div class="prop-score-main"><?= $propOcc ?>%</div>
                        <div class="prop-score-sub"><?= (int) $prop['occupied'] ?>/<?= (int) $prop['total_units'] ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="card">
              <div class="task-header">
                <div class="card-head-main">
                  <span class="card-title">Task Summary</span>
                  <span class="card-head-meta"><?= $taskOpenCount ?> urgent · <?= $taskInProgressCount ?> in
                    progress</span>
                </div>
              </div>
              <div class="task-list" id="rt-task-list">
                <?php if (empty($tasks)): ?>
                  <div class="dashboard-empty">No tasks found.</div>
                <?php else: ?>
                  <?php foreach ($tasks as $task):
                    $st = $task['status'] ?? 'pending';
                    $style = $taskStatusMap[$st] ?? $taskStatusMap['pending'];
                    $dot = $taskDotColor[$st] ?? 'var(--gold)';
                    $priorityKey = strtolower(trim((string) ($task['priority'] ?? 'medium')));
                    $priorityStyle = $taskPriorityMap[$priorityKey] ?? $taskPriorityMap['medium'];
                    ?>
                    <div class="task-item">
                      <div class="task-dot" style="background:<?= $dot ?>;"></div>
                      <div class="task-info">
                        <div class="tname"><?= htmlspecialchars($task['title']) ?></div>
                        <div class="tmeta">
                          <span class="tprop"><?= htmlspecialchars($task['property_name'] ?? '—') ?></span>
                          <span class="task-priority"
                            style="background:<?= $priorityStyle['bg'] ?>;color:<?= $priorityStyle['color'] ?>;">
                            <?= $priorityStyle['label'] ?>
                          </span>
                        </div>
                      </div>
                      <div class="task-status" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;">
                        <?= $style['label'] ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- ── Live Booking Activity Feed ── -->
            <div class="card" style="flex:1; display:flex; flex-direction:column;">
              <div class="task-header">
                <div class="card-head-main">
                  <span class="card-title">
                    Live Activity
                  </span>
                  <span class="card-head-meta">New bookings appear here in real time</span>
                </div>
              </div>
              <div id="rt-activity-feed" class="rt-activity-feed" style="min-height:60px;">
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <?php include '../../includes/right_panel.php'; ?>

  <script>
    window.__PS_DASHBOARD__ = {
      currentYear: <?= (int) $currentYear ?>,
      chartLabels: <?= json_encode($chartLabels) ?>,
      revData: <?= json_encode($revData) ?>,
      expData: <?= json_encode($expData) ?>,
      occupiedUnits: <?= $occupiedUnits ?>,
      vacantUnits: <?= $vacantUnits ?>,
      maintenanceUnits: <?= $maintenanceUnits ?>
    };
  </script>
  <script>window.PS_RT_PAGE = 'dashboard';</script>
  <script src="../../assets/js/admin/dashboard.js"></script>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>

  <?php endif; ?>

  <?php include '../../includes/layout_close_noclose.php'; ?>