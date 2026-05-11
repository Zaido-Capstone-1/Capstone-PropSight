<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/checkin_checkout-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Check-in / Check-out';
$active_page = 'checkin_checkout';
include '../../includes/db.php';
include '../../includes/layout_open.php';

if (isset($_GET['ajax_activity'])) {
    include '../../includes/db.php';
    header('Content-Type: application/json');
    $y = (int) ($_GET['year'] ?? date('Y'));
    $m = (int) ($_GET['month'] ?? date('m'));
    $start = sprintf('%04d-%02d-01', $y, $m);
    $end = date('Y-m-t', strtotime($start));
    $res = mysqli_query($conn, "
        SELECT DATE(checkin_date) AS ci, DATE(checkout_date) AS co
        FROM bookings WHERE status NOT IN ('cancelled')
        AND (checkin_date BETWEEN '$start' AND '$end'
          OR checkout_date BETWEEN '$start' AND '$end')
    ");
    $ci = [];
    $co = [];
    while ($r = mysqli_fetch_assoc($res)) {
        if ($r['ci'] >= $start && $r['ci'] <= $end)
            $ci[] = (int) date('j', strtotime($r['ci']));
        if ($r['co'] >= $start && $r['co'] <= $end)
            $co[] = (int) date('j', strtotime($r['co']));
    }
    echo json_encode(['ci' => array_values(array_unique($ci)), 'co' => array_values(array_unique($co))]);
    exit;
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!strtotime($selected_date))
    $selected_date = date('Y-m-d');
$dateEsc = mysqli_real_escape_string($conn, $selected_date);
$dateLabel = date('F j, Y', strtotime($selected_date));
$isToday = ($selected_date === date('Y-m-d'));

$ci_sql = "
    SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status, b.guests,
           CONCAT(u.first_name,' ',u.last_name) AS guest_name,
           u.email,
           COALESCE(un.unit_name, CONCAT(p.property_name,' — ',un.unit_number)) AS unit_label,
           p.property_name,
           b.checkin_status
    FROM   bookings b
    JOIN   users u  ON u.user_id  = b.user_id
    JOIN   units un ON un.unit_id = b.unit_id
    LEFT JOIN properties p ON p.property_id = un.property_id
    WHERE  b.checkin_date = '$dateEsc'
      AND  b.status NOT IN ('cancelled')
    ORDER  BY b.checkin_date ASC
";
$ci_res = mysqli_query($conn, $ci_sql);
$checkins = [];
while ($row = mysqli_fetch_assoc($ci_res))
    $checkins[] = $row;

$co_sql = "
    SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status, b.guests,
           CONCAT(u.first_name,' ',u.last_name) AS guest_name,
           u.email,
           COALESCE(un.unit_name, CONCAT(p.property_name,' — ',un.unit_number)) AS unit_label,
           p.property_name,
           b.checkout_status
    FROM   bookings b
    JOIN   users u  ON u.user_id  = b.user_id
    JOIN   units un ON un.unit_id = b.unit_id
    LEFT JOIN properties p ON p.property_id = un.property_id
    WHERE  b.checkout_date = '$dateEsc'
      AND  b.status NOT IN ('cancelled')
    ORDER  BY b.checkout_date ASC
";
$co_res = mysqli_query($conn, $co_sql);
$checkouts = [];
while ($row = mysqli_fetch_assoc($co_res))
    $checkouts[] = $row;

$stay_res = mysqli_query($conn, "
    SELECT COUNT(*) AS cnt FROM bookings
    WHERE  status NOT IN ('cancelled','completed')
      AND  checkin_date  <= '$dateEsc'
      AND  checkout_date >= '$dateEsc'
");
$staying = (int) mysqli_fetch_assoc($stay_res)['cnt'];

$ci_done = count(array_filter($checkins, fn($r) => ($r['checkin_status'] ?? '') === 'done'));
$co_done = count(array_filter($checkouts, fn($r) => ($r['checkout_status'] ?? '') === 'done'));
$overdue = count(array_filter($checkouts, fn($r) => ($r['checkout_status'] ?? '') !== 'done' && $selected_date > date('Y-m-d')));

$today_str = date('Y-m-d');
$overdue = count(array_filter(
    $checkouts,
    fn($r) =>
    ($r['checkout_status'] ?? '') !== 'done' && $selected_date < $today_str
));

$cal_year = date('Y', strtotime($selected_date));
$cal_month = date('m', strtotime($selected_date));
$cal_start = "$cal_year-$cal_month-01";
$cal_end = date('Y-m-t', strtotime($selected_date));

$act_sql = "
    SELECT
        DATE(checkin_date)  AS ci_date,
        DATE(checkout_date) AS co_date
    FROM bookings
    WHERE status NOT IN ('cancelled')
      AND (
          (checkin_date  BETWEEN '$cal_start' AND '$cal_end') OR
          (checkout_date BETWEEN '$cal_start' AND '$cal_end')
      )
";
$act_res = mysqli_query($conn, $act_sql);
$ci_days = [];
$co_days = [];
while ($row = mysqli_fetch_assoc($act_res)) {
    if ($row['ci_date'] >= $cal_start && $row['ci_date'] <= $cal_end)
        $ci_days[] = (int) date('j', strtotime($row['ci_date']));
    if ($row['co_date'] >= $cal_start && $row['co_date'] <= $cal_end)
        $co_days[] = (int) date('j', strtotime($row['co_date']));
}
$ci_days = array_unique($ci_days);
$co_days = array_unique($co_days);
function ciStatusLabel($row)
{
    $s = $row['checkin_status'] ?? '';
    return match ($s) {
        'done' => ['Done', 'success'],
        default => ['Expected', 'pending'],
    };
}
function coStatusLabel($row, $selectedDate)
{
    $s = $row['checkout_status'] ?? '';
    if ($s === 'done')
        return ['Done', 'success'];
    if ($selectedDate < date('Y-m-d'))
        return ['Overdue', 'danger'];
    return ['Pending', 'pending'];
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/checkin_checkout.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Check-in / Check-out</h1>
            <p class="dash-subtitle">
                <?= $isToday ? "Today's" : htmlspecialchars($dateLabel) ?> guest arrivals and departures.
            </p>
        </div>
        <div class="date-nav dash-header-actions">
            <?php
            $prev = date('Y-m-d', strtotime($selected_date . ' -1 day'));
            $next = date('Y-m-d', strtotime($selected_date . ' +1 day'));
            ?>
            <a href="?date=<?= $prev ?>" class="date-nav-btn" title="Previous day">‹</a>

            <div class="cal-picker-wrap">
                <div class="cal-picker-trigger" id="calTrigger" onclick="toggleCalPicker()">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    <?= date('M j, Y', strtotime($selected_date)) ?>
                    <span class="cal-trigger-arrow">▼</span>
                </div>
                <div class="cal-dropdown" id="calDropdown">
                    <div class="cal-drop-header">
                        <div class="cal-drop-month" id="calDropMonth"></div>
                        <div class="cal-drop-nav">
                            <button onclick="calNavMonth(-1)">‹</button>
                            <button onclick="calNavMonth(1)">›</button>
                        </div>
                    </div>
                    <div class="cal-drop-dow">
                        <?php foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $d): ?>
                            <span>
                                <?= $d ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="cal-drop-grid" id="calDropGrid"></div>
                </div>
            </div>

            <a href="?date=<?= $next ?>" class="date-nav-btn" title="Next day">›</a>
            <a href="?" class="date-today-btn <?= $isToday ? 'active' : '' ?>">Today</a>
        </div>
    </div>
    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Check-ins</div>
                    <div class="stat-value"><?= count($checkins) ?></div>
                    <div class="stat-sub"><?= $ci_done ?> completed</div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Check-outs</div>
                    <div class="stat-value"><?= count($checkouts) ?></div>
                    <div class="stat-sub"><?= $co_done ?> completed</div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Currently Staying</div>
                    <div class="stat-value"><?= $staying ?></div>
                </div>
                <div class="stat-icon-wrap blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Overdue Check-outs</div>
                    <div class="stat-value"><?= $overdue ?></div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="two-col">

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Check-ins</span>
                    <span class="badge" style="background:#dcfce7;color:#166534;">
                        <?= count($checkins) ?> arrival<?= count($checkins) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php if (empty($checkins)): ?>
                        <div class="section-empty">
                            <svg viewBox="0 0 24 24">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                <polyline points="10 17 15 12 10 7" />
                                <line x1="15" y1="12" x2="3" y2="12" />
                            </svg>
                            No check-ins for <?= htmlspecialchars($dateLabel) ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($checkins as $row):
                            [$label, $cls] = ciStatusLabel($row);
                            $isDone = ($row['checkin_status'] ?? '') === 'done';
                            $nights = (int) ((strtotime($row['checkout_date']) - strtotime($row['checkin_date'])) / 86400);
                            ?>
                            <div class="guest-row" id="ci-row-<?= $row['booking_id'] ?>">
                                <div class="guest-avatar-lg">
                                    <?= strtoupper(substr($row['guest_name'], 0, 1)) ?>
                                </div>
                                <div class="guest-info">
                                    <div class="guest-name"><?= htmlspecialchars($row['guest_name']) ?></div>
                                    <div class="guest-meta">
                                        <?= htmlspecialchars($row['unit_label']) ?>
                                        · <?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?>
                                        · <?= $row['guests'] ?> guest<?= $row['guests'] > 1 ? 's' : '' ?>
                                    </div>
                                </div>
                                <div class="guest-actions">
                                    <?php if (!$isDone): ?>
                                        <button class="act-btn act-btn-checkin"
                                            onclick="processAction(<?= $row['booking_id'] ?>, 'checkin')"
                                            id="ci-btn-<?= $row['booking_id'] ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            Check In
                                        </button>
                                    <?php else: ?>
                                        <span class="badge badge-success">✓ Done</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">Check-outs</span>
                    <span class="badge" style="background:#fef9c3;color:#854d0e;">
                        <?= count($checkouts) ?> departure<?= count($checkouts) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php if (empty($checkouts)): ?>
                        <div class="section-empty">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg>
                            No check-outs for <?= htmlspecialchars($dateLabel) ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($checkouts as $row):
                            [$label, $cls] = coStatusLabel($row, $selected_date);
                            $isDone = ($row['checkout_status'] ?? '') === 'done';
                            $nights = (int) ((strtotime($row['checkout_date']) - strtotime($row['checkin_date'])) / 86400);
                            ?>
                            <div class="guest-row" id="co-row-<?= $row['booking_id'] ?>">
                                <div class="guest-avatar-lg">
                                    <?= strtoupper(substr($row['guest_name'], 0, 1)) ?>
                                </div>
                                <div class="guest-info">
                                    <div class="guest-name"><?= htmlspecialchars($row['guest_name']) ?></div>
                                    <div class="guest-meta">
                                        <?= htmlspecialchars($row['unit_label']) ?>
                                        · <?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?>
                                        · <?= $row['guests'] ?> guest<?= $row['guests'] > 1 ? 's' : '' ?>
                                    </div>
                                </div>
                                <div class="guest-actions">
                                    <?php if (!$isDone): ?>
                                        <span class="badge badge-<?= $cls ?>"><?= $label ?></span>
                                        <button class="act-btn act-btn-extend"
                                            onclick="openExtendModal(<?= $row['booking_id'] ?>, '<?= htmlspecialchars($row['guest_name'], ENT_QUOTES) ?>', '<?= $row['checkout_date'] ?>')"
                                            id="ext-btn-<?= $row['booking_id'] ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                <line x1="5" y1="12" x2="19" y2="12" />
                                            </svg>
                                            Extend
                                        </button>
                                        <button class="act-btn act-btn-checkout"
                                            onclick="processAction(<?= $row['booking_id'] ?>, 'checkout')"
                                            id="co-btn-<?= $row['booking_id'] ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            Check Out
                                        </button>
                                    <?php else: ?>
                                        <span class="badge badge-success">✓ Done</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="cicoModal"
    style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div
        style="background:#fff;border-radius:12px;width:100%;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;margin:16px;">
        <div
            style="padding:18px 22px 14px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-weight:700;font-size:16px;color:#111827;" id="cicoModalTitle">Confirm Action</div>
            <button onclick="closeCicoModal()"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af;line-height:1;">×</button>
        </div>
        <div style="padding:20px 22px;">
            <p id="cicoModalBody" style="margin:0;font-size:14px;color:#374151;line-height:1.6;"></p>
        </div>
        <div
            style="padding:14px 22px 18px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #f3f4f6;">
            <button onclick="closeCicoModal()"
                style="padding:9px 18px;border-radius:7px;border:1.5px solid #e5e7eb;background:#fff;font-size:14px;font-weight:600;color:#6b7280;cursor:pointer;">Cancel</button>
            <button id="cicoModalConfirm"
                style="padding:9px 20px;border-radius:7px;border:none;color:#fff;font-size:14px;font-weight:600;cursor:pointer;"></button>
        </div>
    </div>
</div>

<!-- Extend Stay Modal -->
<div id="extendModal"
    style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div
        style="background:#fff;border-radius:12px;width:100%;max-width:400px;box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;margin:16px;">
        <div
            style="padding:18px 22px 14px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-weight:700;font-size:16px;color:#111827;">Extend Stay</div>
                <div id="extendGuestName" style="font-size:13px;color:#6b7280;margin-top:2px;"></div>
            </div>
            <button onclick="closeExtendModal()"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af;line-height:1;">×</button>
        </div>
        <div style="padding:20px 22px;">
            <div style="margin-bottom:14px;">
                <div
                    style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;">
                    Current Check-out Date</div>
                <div id="extendCurrentDate"
                    style="font-size:14px;font-weight:600;color:#374151;padding:8px 12px;background:#f9fafb;border-radius:7px;border:1px solid #e5e7eb;">
                </div>
            </div>
            <div>
                <div
                    style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px;">
                    New Check-out Date</div>
                <input type="date" id="extendNewDate"
                    style="width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;color:#111827;box-sizing:border-box;outline:none;"
                    onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#d1d5db'">
            </div>
        </div>
        <div
            style="padding:14px 22px 18px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid #f3f4f6;">
            <button onclick="closeExtendModal()"
                style="padding:9px 18px;border-radius:7px;border:1.5px solid #e5e7eb;background:#fff;font-size:14px;font-weight:600;color:#6b7280;cursor:pointer;">Cancel</button>
            <button onclick="submitExtend()"
                style="padding:9px 20px;border-radius:7px;border:none;background:#6366f1;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Save
                Extension</button>
        </div>
    </div>
</div>

<script>
    window.__PS_CHECKIN__ = {
        selectedDate: '<?= $selected_date ?>',
        ciDays: <?= json_encode(array_values($ci_days)) ?>,
        coDays: <?= json_encode(array_values($co_days)) ?>,
        todayStr: '<?= date('Y-m-d') ?>',
        calYear: <?= $cal_year ?>,
        calMonth: <?= (int) $cal_month ?>,
        calKey: '<?= "$cal_year-$cal_month" ?>',
        csrfToken: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
    };
</script>
<script>window.PS_RT_PAGE = 'checkin';</script>
<!-- inline file first -->
<script src="../../assets/js/admin/checkin_checkout-inline.js"></script>
<!-- then main file -->
<script src="../../assets/js/admin/checkin_checkout.js"></script>

<?php include '../../includes/layout_close.php'; ?>