<link rel="stylesheet" href="../../assets/css/admin-css/booking_reports-inline.css">
<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
  <script src="../../assets/js/responsive.js"></script>
  <script src="../../assets/js/admin/booking_reports-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Booking Reports';
$active_page = 'booking_reports';
include '../../includes/db.php';
include '../../includes/layout_open.php';
include '../../lib/admin-queries/booking_reports_queries.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Booking Reports</h1>
      <p class="dash-subtitle">Reservation trends, cancellations, and booking analytics.</p>
    </div>
    <div class="dash-header-actions">
      <form method="GET" style="display:flex;gap:8px;">
        <select name="range" onchange="this.form.submit()"
          style="padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
          <option value="30" <?= $range === '30' ? 'selected' : '' ?>>Last 30 days</option>
          <option value="60" <?= $range === '60' ? 'selected' : '' ?>>Last 60 days</option>
          <option value="365" <?= $range === '365' ? 'selected' : '' ?>>This year</option>
          <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All time</option>
        </select>
      </form>
      <button class="btn btn-secondary" onclick="exportCSV()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        Export
      </button>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Total Bookings</div>
          <div class="stat-value"><?= number_format($total) ?></div>
          <div class="stat-sub"><?= $rangeLbl ?></div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Confirmed</div>
          <div class="stat-value">
            <?= number_format((int) $stats['confirmed'] + (int) $stats['completed'] + (int) $stats['active_cnt']) ?>
          </div>
          <span class="stat-trend up"><?= $confirmRate ?>%</span>
          <div class="stat-sub">Conversion rate</div>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Cancelled</div>
          <div class="stat-value"><?= number_format((int) $stats['cancelled']) ?></div>
          <span class="stat-trend <?= $cancelRate > 15 ? 'down' : 'up' ?>"><?= $cancelRate ?>%</span>
          <div class="stat-sub">Cancellation rate</div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">Avg. Stay</div>
          <div class="stat-value"><?= $avgNights ?> <span style="font-size:16px;">nights</span></div>
          <span class="stat-trend neutral">Avg. lead: <?= $avgLead ?>d</span>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
        </div>
      </div>
    </div>

    <div class="two-col">
      <div class="card" style="flex:2;">
        <div class="card-header"><span class="card-title">Monthly Booking Volume (12 months)</span></div>
        <div class="chart-wrap" style="height:300px;">
          <canvas id="bookingVolChart"></canvas>
        </div>
      </div>
      <div class="card" style="flex:1;">
        <div class="card-header"><span class="card-title">Booking Status</span></div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:8px 0;">
          <div class="chart-wrap" style="height:160px;width:160px;">
            <canvas id="statusDonut"></canvas>
          </div>
          <div style="width:100%;">
            <?php foreach ($donutLabels as $di => $dl): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:10px;height:10px;border-radius:50%;background:<?= $donutColors[$di] ?>;"></div>
                  <span style="font-size:13px;"><?= $dl ?></span>
                </div>
                <strong><?= $donutData[$di] ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="two-col">
      <div class="card" style="flex:1;">
        <div class="card-header"><span class="card-title">Bookings by Property <small
              style="font-weight:400;color:var(--text-muted,#64748b);font-size:12px;">(excl. cancelled)</small></span>
        </div>
        <?php if (!empty($byProperty)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Property</th>
                  <th>Bookings</th>
                  <th>Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($byProperty as $bp): ?>
                  <tr>
                    <td><?= htmlspecialchars($bp['property_name']) ?></td>
                    <td><strong><?= $bp['total'] ?></strong></td>
                    <td>₱<?= number_format((float) $bp['revenue'], 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div style="padding:32px;text-align:center;color:#94a3b8;font-size:13px;">No property data available.</div>
        <?php endif; ?>
      </div>
      <div class="card" style="flex:1;">
        <div class="card-header"><span class="card-title">Payment Methods</span></div>
        <?php if (!empty($payLabels)): ?>
          <div class="chart-wrap" style="height:200px;"><canvas id="paymentChart"></canvas></div>
        <?php else: ?>
          <div style="padding:32px;text-align:center;color:#94a3b8;font-size:13px;">No payment data available.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Top Booked Units</span></div>
      <?php if (!empty($topUnits)): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Unit</th>
                <th>Property</th>
                <th>Bookings</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topUnits as $u): ?>
                <tr>
                  <td style="font-weight:600;"><?= htmlspecialchars($u['unit_label']) ?></td>
                  <td><?= htmlspecialchars($u['property_name']) ?></td>
                  <td><?= $u['total_bookings'] ?></td>
                  <td>₱<?= number_format((float) $u['revenue'], 0) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="padding:32px;text-align:center;color:#94a3b8;font-size:13px;">No unit booking data available.</div>
      <?php endif; ?>
    </div>

    <div class="two-col">
      <div class="card" style="flex:1;">
        <div class="card-header">
          <span class="card-title">Guest Demographics
            <small style="font-weight:400;color:var(--text-muted,#64748b);font-size:12px;">(by country)</small>
          </span>
        </div>
        <?php if (!empty($demographics)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Country</th>
                  <th>Guests</th>
                  <th>Bookings</th>
                  <th>Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $maxBookings = max(array_column($demographics, 'bookings')) ?: 1;
                foreach ($demographics as $i => $d):
                  $pct = round($d['bookings'] / $maxBookings * 100);
                  ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;">
                        <span
                          style="font-size:11px;font-weight:700;color:var(--text-muted,#64748b);min-width:18px;">#<?= $i + 1 ?></span>
                        <div>
                          <div style="font-weight:600;"><?= htmlspecialchars($d['nationality']) ?></div>
                          <div style="margin-top:4px;height:4px;border-radius:4px;background:#f1f5f9;width:120px;">
                            <div style="height:4px;border-radius:4px;background:var(--primary,#2563eb);width:<?= $pct ?>%;">
                            </div>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td><?= (int) $d['guests'] ?></td>
                    <td><strong><?= (int) $d['bookings'] ?></strong></td>
                    <td>₱<?= number_format((float) $d['revenue'], 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div style="padding:32px;text-align:center;color:#94a3b8;font-size:13px;">No guest demographic data available.
          </div>
        <?php endif; ?>
      </div>
      <div class="card" style="flex:1.4;">
        <div class="card-header">
          <span class="card-title">Country Breakdown</span>
          <span style="font-size:11px;color:var(--text-muted,#64748b);font-weight:400;">Hover a country to see
            details</span>
        </div>
        <div style="position:relative;">
          <div id="guestMap" style="height:280px;border-radius:0 0 var(--radius,10px) var(--radius,10px);z-index:1;">
          </div>
          <div id="guestMapLegend"
            style="position:absolute;bottom:12px;left:12px;background:rgba(255,255,255,.92);border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:11px;z-index:999;pointer-events:none;">
            <div style="font-weight:700;margin-bottom:5px;color:#1e293b;">Bookings</div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
              <div
                style="width:80px;height:10px;border-radius:3px;background:linear-gradient(to right,#bfdbfe,#1d4ed8);">
              </div>
              <span style="color:#475569;">Low → High</span>
            </div>
            <div style="color:#94a3b8;font-size:10px;">Grey = no bookings</div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($total <= 1 && empty($byProperty)): ?>
      <div class="card" style="text-align:center;padding:40px;">
        <div style="font-size:2rem;margin-bottom:12px;">📋</div>
        <div style="font-size:16px;font-weight:600;color:var(--text-dark);">No booking data yet</div>
        <div style="color:var(--text-soft);margin-top:8px;">Booking data will appear here once reservations are made.
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  window.__PS_BOOKING_REPORTS__ = {
    mLabels: <?= json_encode($monthlyLabels) ?>,
    mConfirmed: <?= json_encode($monthlyConfirmed) ?>,
    mCancelled: <?= json_encode($monthlyCancelled) ?>,
    mPending: <?= json_encode($monthlyPending) ?>,
    sourceLabels: <?= json_encode($sourceLabels) ?>,
    sourceCounts: <?= json_encode($sourceCounts) ?>,
    unitLabels: <?= json_encode($unitLabels) ?>,
    unitBookingCounts: <?= json_encode($unitBookingCounts) ?>,
    donutLabels: <?= json_encode($donutLabels) ?>,
    donutData: <?= json_encode($donutData) ?>,
    donutColors: <?= json_encode($donutColors) ?>,
    payLabels: <?= !empty($payLabels) ? json_encode($payLabels) : 'null' ?>,
    payData: <?= !empty($payLabels) ? json_encode($payData) : 'null' ?>,
    demographics: <?= json_encode(array_map(function ($d) {
      return ['nationality' => $d['nationality'], 'bookings' => (int) $d['bookings'], 'guests' => (int) $d['guests'], 'revenue' => (float) $d['revenue']];
    }, $demographics ?? [])) ?>
  };
</script>
<script src="../../assets/js/admin/booking_reports.js"></script>

<?php include '../../includes/layout_close.php'; ?>