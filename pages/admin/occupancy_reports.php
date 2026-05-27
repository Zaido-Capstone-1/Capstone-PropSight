<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/occupancy_reports-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Occupancy Reports';
$active_page = 'occupancy_reports';
include '../../includes/db.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/occupancy_reports_queries.php';  // ← all SQL lives here
?>
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Occupancy Reports</h1>
            <p class="dash-subtitle">Track unit occupancy rates across all properties.</p>
        </div>
        <div class="dash-header-actions">
            <form method="GET" style="display:flex;gap:8px;">
                <select name="month" onchange="this.form.submit()"
                    style="padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:var(--white);">
                    <?php foreach ($avMonths as $am): ?>
                        <option value="<?= $am ?>" <?= $am === $selectedMonth ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($am . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button class="btn btn-secondary" onclick="exportCSV()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    style="width:14px;height:14px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>Export
            </button>
        </div>
    </div>

    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card sc-blue">
                <div class="stat-card-left">
                    <div class="stat-label">Overall Occupancy</div>
                    <div class="stat-value"><?= $overallRate ?>%</div>
                    <span class="stat-trend <?= $rateChange >= 0 ? 'up' : 'down' ?>"><?= $rateChange >= 0 ? '↑' : '↓' ?>
                        <?= abs($rateChange) ?>%</span>
                    <div class="stat-sub">vs <?= $prevRate ?>% last month</div>
                </div>
                <div class="stat-icon-wrap blue"><svg fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path d="M3 9.5L12 3l9 6.5V21H3V9.5z" />
                    </svg></div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-card-left">
                    <div class="stat-label">Occupied Units</div>
                    <div class="stat-value"><?= $occupiedNow ?></div>
                    <span class="stat-trend neutral">–</span>
                    <div class="stat-sub">of <?= $totalUnits ?> total</div>
                </div>
                <div class="stat-icon-wrap green"><svg fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg></div>
            </div>
            <div class="stat-card sc-gold">
                <div class="stat-card-left">
                    <div class="stat-label">Vacant Units</div>
                    <div class="stat-value"><?= $vacantNow ?></div>
                    <span class="stat-trend neutral">–</span>
                    <div class="stat-sub">Available now</div>
                </div>
                <div class="stat-icon-wrap gold"><svg fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg></div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-card-left">
                    <div class="stat-label">Avg. Stay Duration</div>
                    <div class="stat-value"><?= $avgNights ?> nights</div>
                    <span class="stat-trend neutral">–</span>
                    <div class="stat-sub">Per booking</div>
                </div>
                <div class="stat-icon-wrap blue"><svg fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg></div>
            </div>
        </div>

        <div class="two-col">
            <div class="card" style="flex:3;">
                <div class="card-header"><span class="card-title">Occupancy Trend by Property (Last 6 Months)</span>
                </div>
                <div class="chart-wrap" style="height:220px;"><canvas id="occTrendChart"></canvas></div>
            </div>
            <div class="card" style="flex:2;">
                <div class="card-header"><span class="card-title"><?= $monthLabel ?></span></div>
                <div class="chart-wrap" style="height:220px;"><canvas id="occBarChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">12-Month Occupancy Trend</span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Occupied</th>
                            <th>Total Units</th>
                            <th>Rate</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trend as $t):
                            $v = (int) $t['rate'];
                            $bc = $v >= 80 ? 'var(--success)' : ($v >= 60 ? 'var(--blue-400)' : 'var(--gold)');
                            $badgeColor = $v >= 80 ? '#2ECC71' : ($v >= 50 ? '#2563c4' : '#deaf37');
                            ?>
                            <tr style="<?= $v === 0 ? 'opacity:0.4;' : '' ?>">
                                <td style="font-weight:600;"><?= htmlspecialchars($t['label']) ?></td>
                                <td><?= $t['occupied'] ?></td>
                                <td><?= $totalUnits ?></td>
                                <td>
                                    <span
                                        style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;background:<?= $badgeColor ?>18;color:<?= $badgeColor ?>;">
                                        <?= $v ?>%
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div
                                            style="flex:1;height:6px;background:var(--blue-50);border-radius:3px;max-width:80px;">
                                            <div
                                                style="width:<?= $v ?>%;height:100%;border-radius:3px;background:<?= $bc ?>;">
                                            </div>
                                        </div>
                                        <span style="font-size:12px;font-weight:600;"><?= $v ?>%</span>
                                    </div>
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
    window.__PS_OCCUPANCY__ = {
        propTrendDatasets: <?= json_encode($propTrendDatasets) ?>,
        last6Labels: <?= json_encode($last6Labels) ?>,
        overallDatasets: <?= json_encode($overallDatasets) ?>,
        avgLabels: <?= json_encode($avgLabels) ?>,
        avgData: <?= json_encode($avgData) ?>,
        perPropLabels: <?= json_encode(array_column($perProperty, 'property_name')) ?>,
        perPropRates: <?= json_encode(array_column($perProperty, 'rate')) ?>,
        trendRows: <?= json_encode(array_map(function ($t) use ($totalUnits) {
            return [$t['label'], $t['occupied'], $totalUnits, $t['rate']];
        }, $trend)) ?>,
        selectedMonth: '<?= $selectedMonth ?>'
    };
</script>
<script src="../../assets/js/admin/occupancy_reports.js"></script>
<?php include '../../includes/layout_close.php'; ?>