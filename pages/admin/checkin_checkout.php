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

// ── AJAX: calendar activity counts ───────────────────────────────────────────
if (isset($_GET['ajax_activity'])) {
    include '../../includes/db.php';
    $year = (int) ($_GET['year'] ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    $stmt = $conn->prepare(
        "SELECT DATE(checkin_date) AS ci_date, DATE(checkout_date) AS co_date
         FROM bookings
         WHERE status NOT IN ('cancelled')
           AND (checkin_date BETWEEN ? AND ? OR checkout_date BETWEEN ? AND ?)"
    );
    $stmt->bind_param('ssss', $start, $end, $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();

    $ci = [];
    $co = [];
    while ($row = $res->fetch_assoc()) {
        if ($row['ci_date'] >= $start && $row['ci_date'] <= $end) {
            $d = (int) date('j', strtotime($row['ci_date']));
            $ci[$d] = ($ci[$d] ?? 0) + 1;
        }
        if ($row['co_date'] >= $start && $row['co_date'] <= $end) {
            $d = (int) date('j', strtotime($row['co_date']));
            $co[$d] = ($co[$d] ?? 0) + 1;
        }
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['ci' => (object) $ci, 'co' => (object) $co]);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$page_title = 'Check-in / Check-out';
$active_page = 'checkin_checkout';
include '../../includes/db.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/checkin_checkout_queries.php';
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

        <!-- ── Voucher Lookup Trigger ── -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
            <button onclick="openVoucherModal()"
                style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:8px;border:1.5px solid #e5e7eb;background:#fff;font-size:.84rem;font-weight:600;color:#1e2533;cursor:pointer;transition:all .15s;box-shadow:0 1px 3px rgba(0,0,0,.06);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    style="width:16px;height:16px;">
                    <rect x="2" y="7" width="20" height="12" rx="2" />
                    <path d="M2 11h20" stroke-width="1.4" />
                    <circle cx="7" cy="14" r="1.5" fill="currentColor" opacity=".7" />
                    <circle cx="17" cy="14" r="1.5" fill="currentColor" opacity=".7" />
                </svg>
                Voucher Lookup
            </button>
        </div>

        <!-- ── Voucher Lookup Modal ── -->
        <div id="voucherModal"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
            <div
                style="background:#fff;border-radius:14px;width:100%;max-width:460px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">

                <!-- Header -->
                <div
                    style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                            style="width:18px;height:18px;color:#6b7280;">
                            <rect x="2" y="7" width="20" height="12" rx="2" />
                            <path d="M2 11h20" stroke-width="1.4" />
                            <circle cx="7" cy="14" r="1.5" fill="currentColor" opacity=".7" />
                            <circle cx="17" cy="14" r="1.5" fill="currentColor" opacity=".7" />
                        </svg>
                        <span style="font-weight:700;font-size:.95rem;color:#1e2533;">Voucher Lookup</span>
                    </div>
                    <button onclick="closeVoucherModal()"
                        style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:none;background:none;cursor:pointer;color:#9ca3af;border-radius:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            style="width:16px;height:16px;">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div style="padding:20px 22px;">
                    <p style="font-size:.8rem;color:#8a94a6;margin:0 0 14px;">Enter the guest's voucher code to validate
                        and mark it as used.</p>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="voucherLookupInput" placeholder="e.g. PS-R03-CSZYA428"
                            style="flex:1;padding:9px 13px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:.84rem;font-family:monospace;text-transform:uppercase;outline:none;transition:border-color .15s;"
                            onfocus="this.style.borderColor='#1e2533'" onblur="this.style.borderColor='#e5e7eb'"
                            onkeydown="if(event.key==='Enter') lookupVoucher()">
                        <button onclick="lookupVoucher()"
                            style="padding:9px 20px;border-radius:7px;border:none;background:#1e2533;color:#fff;font-size:.84rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:opacity .15s;"
                            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                            Validate
                        </button>
                    </div>

                    <!-- Result -->
                    <div id="voucherLookupResult" style="display:none;margin-top:14px;"></div>
                </div>

                <!-- Footer -->
                <div style="padding:12px 22px 18px;display:flex;justify-content:flex-end;">
                    <button onclick="closeVoucherModal()"
                        style="padding:8px 18px;border-radius:7px;border:1.5px solid #e5e7eb;background:#fff;font-size:.82rem;font-weight:600;color:#6b7280;cursor:pointer;">
                        Close
                    </button>
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
                                        <?php if ($isPast): ?>
                                            <button class="act-btn act-btn-checkin" disabled
                                                title="Cannot check in: this date has already passed."
                                                style="opacity:0.45;cursor:not-allowed;">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                                Check In
                                            </button>
                                        <?php else: ?>
                                            <button class="act-btn act-btn-checkin"
                                                onclick="processAction(<?= $row['booking_id'] ?>, 'checkin')"
                                                id="ci-btn-<?= $row['booking_id'] ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                                Check In
                                            </button>
                                        <?php endif; ?>
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
                                        <?php $notCheckedIn = ($row['checkin_status'] ?? '') !== 'done'; ?>
                                        <button class="act-btn act-btn-extend" <?php if ($notCheckedIn): ?> disabled
                                                title="Guest must be checked in before extending."
                                                style="opacity:0.45;cursor:not-allowed;" <?php else: ?>
                                                onclick="openExtendModal(<?= $row['booking_id'] ?>, '<?= htmlspecialchars($row['guest_name'], ENT_QUOTES) ?>', '<?= $row['checkout_date'] ?>')"
                                                id="ext-btn-<?= $row['booking_id'] ?>" <?php endif; ?>>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                <line x1="5" y1="12" x2="19" y2="12" />
                                            </svg>
                                            Extend
                                        </button>
                                        <button class="act-btn act-btn-checkout" <?php if ($notCheckedIn): ?> disabled
                                                title="Guest must be checked in before checking out."
                                                style="opacity:0.45;cursor:not-allowed;" <?php else: ?>
                                                onclick="processAction(<?= $row['booking_id'] ?>, 'checkout')"
                                                id="co-btn-<?= $row['booking_id'] ?>" <?php endif; ?>>
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
        ciDays: <?= json_encode((object) $ci_days) ?>,
        coDays: <?= json_encode((object) $co_days) ?>,
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