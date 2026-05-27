<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/calendar-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Calendar / Availability';
$active_page = 'calendar';
include '../../includes/db.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/calendar_queries.php'; 
?>

<link rel="stylesheet" href="../../assets/css/admin-css/calendar.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner" id="calPageInner" style="overflow-y:auto;padding-bottom:90px;">
    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Calendar / Availability</h1>
            <p class="dash-subtitle">Manage property availability and track reservations by date.</p>
        </div>
        <div class="dash-header-actions">
            <button class="btn btn-secondary" onclick="exportMonthReport()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Export
            </button>
            <button class="btn btn-primary" onclick="openBlockModal()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                Block Dates
            </button>
        </div>
    </div>
    <div class="block-modal-overlay" id="blockModalOverlay">
        <div class="block-modal">
            <div class="block-modal-header">
                <div class="block-modal-title" id="blockModalTitle">Block Date</div>
                <button class="block-modal-close" onclick="closeBlockModal()">✕</button>
            </div>
            <div class="block-modal-body">
                <div class="block-field">
                    <div class="block-label">Date</div>
                    <input type="date" class="block-input" id="blockDateInput">
                </div>
                <div class="block-field" id="blockReasonField">
                    <div class="block-label">Reason (optional)</div>
                    <input type="text" class="block-input" id="blockReasonInput"
                        placeholder="e.g. Maintenance, Staff holiday…">
                </div>
            </div>
            <div class="block-modal-footer">
                <button class="block-btn-cancel" onclick="closeBlockModal()">Cancel</button>
                <button class="block-btn-unblock" id="unblockBtn" style="display:none;"
                    onclick="submitUnblock()">Unblock
                    Date</button>
                <button class="block-btn-confirm" id="blockBtn" onclick="submitBlock()">Block Date</button>
            </div>

            <div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
        </div>
    </div>
    <div class="cal-page-wrap">

        <div class="cal-main">

            <div class="cal-stats">
                <div class="cal-stat-card">
                    <div class="cal-stat-icon booked">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <div>
                        <div class="cal-stat-val"><?= $total_booked ?></div>
                        <div class="cal-stat-lbl">Fully Booked Days</div>
                    </div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-icon partial">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div>
                        <div class="cal-stat-val"><?= $total_partial ?></div>
                        <div class="cal-stat-lbl">Partially Booked</div>
                    </div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-icon free">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div>
                        <div class="cal-stat-val"><?= $total_free ?></div>
                        <div class="cal-stat-lbl">Available Days</div>
                    </div>
                </div>
                <div class="cal-stat-card">
                    <div class="cal-stat-icon rate">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                    </div>
                    <div>
                        <div class="cal-stat-val"><?= $occ_rate ?>%</div>
                        <div class="cal-stat-lbl">Occupancy Rate</div>
                    </div>
                </div>
            </div>

            <div class="cal-card">
                <div class="cal-card-header">
                    <div class="cal-card-header-left">
                        <div class="cal-month-title"><?= $month_name ?></div>
                        <span class="cal-year-badge"><?= $year ?></span>
                    </div>
                    <div class="cal-nav-group">
                        <a href="?year=<?= $prev_year ?>&month=<?= $prev_month ?>" class="cal-nav-btn"
                            style="text-decoration: none;" title="Previous month">‹</a>
                        <a href="?year=<?= $now->format('Y') ?>&month=<?= $now->format('m') ?>" class="cal-today-btn"
                            style="text-decoration: none;">Today</a>
                        <a href="?year=<?= $next_year ?>&month=<?= $next_month ?>" class="cal-nav-btn"
                            style="text-decoration: none;" title="Next month">›</a>
                    </div>
                </div>

                <div class="cal-filter-bar">
                    <button class="prop-filter-pill active" onclick="setFilter(this,'all')">All Properties</button>
                    <?php
                    $props_res = mysqli_query($conn, "SELECT property_id, property_name FROM properties ORDER BY property_name");
                    while ($pr = mysqli_fetch_assoc($props_res)):
                        ?>
                        <button class="prop-filter-pill" onclick="setFilter(this, <?= $pr['property_id'] ?>)">
                            <?= htmlspecialchars($pr['property_name']) ?>
                        </button>
                    <?php endwhile; ?>
                </div>

                <div class="cal-grid-wrap">
                    <div class="cal-dow-row">
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>
                            <div class="cal-dow"><?= $wd ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cal-day-grid" id="calGrid">
                        <?php
                        for ($i = 0; $i < $start_dow; $i++) {
                            echo '<div class="cal-day-cell empty"></div>';
                        }
                        for ($d = 1; $d <= $days_in_month; $d++):
                            $info = $day_data[$d];
                            $s = $info['status'];
                            $cnt = $info['count'];
                            $total = $info['total'];
                            $isToday = ($d === $today_day);
                            $isSelected = ($d === $selected_day);
                            $classes = "cal-day-cell $s" . ($isToday ? ' today' : '') . ($isSelected ? ' selected' : '');
                            $isBlocked = ($s === 'blocked');
                            ?>
                            <div class="<?= $classes ?>" onclick="selectDay(<?= $d ?>, this)" data-day="<?= $d ?>"
                                data-props="<?= implode(',', $day_data[$d]['props'] ?? []) ?>">
                                <div class="cal-day-num"><?= $d ?></div>
                                <?php if ($s === 'booked' || $s === 'partial'): ?>
                                    <div class="cal-day-pill"><?= $cnt ?>/<?= $total ?></div>
                                <?php elseif ($s === 'free'): ?>
                                    <div class="cal-day-pill">Open</div>
                                <?php else: ?>
                                    <div class="cal-day-pill">—</div>
                                <?php endif; ?>
                                <?php if ($s === 'booked' || $s === 'partial'): ?>
                                    <div class="cal-day-dots">
                                        <?php for ($j = 0; $j < min($cnt, 3); $j++)
                                            echo '<span></span>'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <button class="drawer-toggle-btn btn btn-primary" onclick="openDrawer()"
                style="position:fixed;bottom:24px;right:24px;z-index:498;border-radius:40px;padding:12px 20px;box-shadow:0 6px 20px rgba(37,99,196,.4);gap:8px;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    style="width:16px;height:16px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                View Bookings
            </button>
        </div>

        <div class="cal-detail-panel" id="calDetailPanel">
            <div class="cal-detail-panel-inner">

                <div class="drawer-handle">
                    <div class="drawer-handle-bar"></div>
                </div>

                <div class="legend-card">
                    <div class="legend-title">Availability Legend</div>
                    <div class="legend-row">
                        <div class="legend-swatch free"><svg fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg></div>
                        <div class="legend-text-wrap">
                            <div class="legend-name">Available</div>
                            <div class="legend-desc">All units open for booking</div>
                        </div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-swatch partial"><svg fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg></div>
                        <div class="legend-text-wrap">
                            <div class="legend-name">Partially Booked</div>
                            <div class="legend-desc">Some units still available</div>
                        </div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-swatch booked"><svg fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg></div>
                        <div class="legend-text-wrap">
                            <div class="legend-name">Fully Booked</div>
                            <div class="legend-desc">No units available</div>
                        </div>
                    </div>
                    <div class="legend-row">
                        <div class="legend-swatch blocked"><svg fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                            </svg></div>
                        <div class="legend-text-wrap">
                            <div class="legend-name">Blocked</div>
                            <div class="legend-desc">Manually closed / maintenance</div>
                        </div>
                    </div>
                </div>

                <div class="day-detail-card" id="dayDetailCard">
                    <div class="day-detail-header">
                        <div class="day-detail-date"><?= $month_short ?> · <?= $year ?></div>
                        <div class="day-detail-num" id="detailDayNum"><?= $selected_day ?></div>
                        <div class="day-detail-dow" id="detailDayDow">
                            <?php
                            $dows = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            echo $dows[($start_dow + $selected_day - 1) % 7];
                            ?>
                        </div>
                        <div class="day-detail-chips">
                            <span class="day-chip" id="detailBookCount">
                                <?= count($selected_bookings) ?>
                                Booking<?= count($selected_bookings) !== 1 ? 's' : '' ?>
                            </span>
                            <span class="day-chip" id="detailStatus">
                                <?php
                                $sm = ['booked' => 'Fully Booked', 'partial' => 'Partially Booked', 'free' => 'Available', 'blocked' => 'Blocked'];
                                echo $sm[$day_data[$selected_day]['status']] ?? 'Available';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="day-detail-body" id="dayDetailBody">
                        <?php if (empty($selected_bookings)): ?>
                            <div class="day-detail-empty">
                                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <p>No bookings on <?= $month_short ?>     <?= $selected_day ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($selected_bookings as $bk): ?>
                                <div class="booking-entry <?= $bk['status'] ?>">
                                    <div class="be-top">
                                        <span class="be-ref">#BK-<?= str_pad($bk['booking_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                        <span class="be-badge <?= $bk['status'] ?>"><?= ucfirst($bk['status']) ?></span>
                                    </div>
                                    <div class="be-name"><?= htmlspecialchars($bk['guest_name']) ?></div>
                                    <div class="be-unit"><?= htmlspecialchars($bk['unit_label']) ?></div>
                                    <div class="be-time">
                                        <svg viewBox="0 0 24 24"
                                            style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;">
                                            <circle cx="12" cy="12" r="10" />
                                            <polyline points="12 6 12 12 16 14" />
                                        </svg>
                                        <?= date('M j', strtotime($bk['checkin_date'])) ?> →
                                        <?= date('M j', strtotime($bk['checkout_date'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cal-actions">
                    <div class="cal-actions-title">Quick Actions</div>
                    <button class="cal-action-btn" onclick="openBlockForSelected()">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                        </svg>
                        Block / Unblock Selected Date
                    </button>
                    <button class="cal-action-btn" onclick="exportMonthReport()">
                        <svg viewBox="0 0 24 24">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Export Month Report
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    window.__PS_CALENDAR__ = {
        dayData: <?= json_encode($day_data) ?>,
        bookingsByDay: <?= json_encode($bookings_by_day) ?>,
        blockedDates: <?= json_encode($blocked_dates) ?>,
        monthShort: '<?= $month_short ?>',
        monthNum: <?= $month_num ?>,
        yearNum: <?= $year ?>,
        startDow: <?= $start_dow ?>,
        selectedDay: <?= $selected_day ?>,
        currentMonth: '<?= $year ?>-<?= str_pad($month_num, 2, '0', STR_PAD_LEFT) ?>',
        prevUrl: '?year=<?= $prev_year ?>&month=<?= $prev_month ?>',
        nextUrl: '?year=<?= $next_year ?>&month=<?= $next_month ?>',
        csrfToken: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
    };
</script>
<script src="../../assets/js/admin/calendar.js"></script>

<?php include '../../includes/layout_close.php'; ?>