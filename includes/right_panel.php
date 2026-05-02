<?php
/**
 * right_panel.php — Shared right panel (calendar + schedule + activity)
 * Included on pages that show the right-hand panel (dashboard, etc.)
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
  require_once __DIR__ . '/db.php';
}

// ── Profile photo / initials ──────────────────────────────────────────────
$_rp_photo_raw = trim((string)($_SESSION['profile_photo'] ?? ''));
if ($_rp_photo_raw === '' && isset($_SESSION['user_id']) && !empty($conn)) {
  $_rp_uid = (int)$_SESSION['user_id'];
  $_rp_r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$_rp_uid LIMIT 1"));
  $_rp_photo_raw = trim((string)($_rp_r['profile_photo'] ?? ''));
  if ($_rp_photo_raw !== '') {
    $_SESSION['profile_photo'] = $_rp_photo_raw;
  }
}
$_rp_photo_url = $_rp_photo_raw !== '' ? '../../' . ltrim($_rp_photo_raw, '/') : '';
$_rp_name      = trim((string)($_SESSION['name'] ?? 'Admin'));
$_rp_parts     = array_filter(explode(' ', $_rp_name));
$_rp_initials  = '';
foreach (array_slice($_rp_parts, 0, 2) as $p) {
  $_rp_initials .= strtoupper(mb_substr($p, 0, 1));
}
if ($_rp_initials === '') $_rp_initials = 'A';

// ── Calendar / schedule / activity data ──────────────────────────────────
$today = new DateTime('today');
$monthLabel = $today->format('F');
$calendarDays = [];
for ($i = -3; $i <= 3; $i++) {
  $d = (clone $today)->modify(($i >= 0 ? '+' : '') . $i . ' day');
  $calendarDays[] = [
    'day' => $d->format('j'),
    'date' => $d->format('Y-m-d'),
    'is_today' => $i === 0,
  ];
}

$schedule = [];
$scheduleRes = mysqli_query(
  $conn,
  "SELECT m.request_id, m.issue_description, m.priority, m.request_status, m.request_date, p.property_name
   FROM maintenance_requests m
   LEFT JOIN units u ON u.unit_id = m.unit_id
   LEFT JOIN properties p ON p.property_id = u.property_id
   WHERE m.request_status IN ('open','pending','in_progress')
   ORDER BY m.request_date ASC
   LIMIT 4"
);
while ($scheduleRes && ($row = mysqli_fetch_assoc($scheduleRes))) {
  $schedule[] = $row;
}

$activities = [];
$activityRes = mysqli_query(
  $conn,
  "SELECT description, amount, type, transaction_date
   FROM transactions
   ORDER BY transaction_date DESC, id DESC
   LIMIT 5"
);
while ($activityRes && ($row = mysqli_fetch_assoc($activityRes))) {
  $activities[] = $row;
}

$adminId = (int)($_SESSION['user_id'] ?? 0);
$notifications = [];

$notifMsgRes = mysqli_query(
  $conn,
  "SELECT m.message_id AS id, m.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor
   FROM messages m
   JOIN users u ON u.user_id = m.from_user
   WHERE m.to_user = $adminId AND m.is_read = 0
   ORDER BY m.created_at DESC
   LIMIT 5"
);
while ($notifMsgRes && ($n = mysqli_fetch_assoc($notifMsgRes))) {
  $notifications[] = [
    'id' => 'msg-' . (int)$n['id'],
    'type' => 'message',
    'text' => 'New message from ' . trim((string)($n['actor'] ?? 'User')),
    'ts' => (string)($n['created_at'] ?? date('Y-m-d H:i:s')),
    'path' => 'messages.php',
  ];
}

$notifBookingRes = mysqli_query(
  $conn,
  "SELECT booking_id, created_at
   FROM bookings
   WHERE status = 'pending'
   ORDER BY created_at DESC
   LIMIT 5"
);
while ($notifBookingRes && ($n = mysqli_fetch_assoc($notifBookingRes))) {
  $notifications[] = [
    'id' => 'booking-' . (int)$n['booking_id'],
    'type' => 'booking',
    'text' => 'Pending booking #' . str_pad((string)$n['booking_id'], 4, '0', STR_PAD_LEFT),
    'ts' => (string)($n['created_at'] ?? date('Y-m-d H:i:s')),
    'path' => 'reservations.php?status=pending',
  ];
}

$notifTaskRes = mysqli_query(
  $conn,
  "SELECT request_id, issue_description, request_date
   FROM maintenance_requests
   WHERE request_status IN ('open','in_progress')
   ORDER BY request_date DESC
   LIMIT 5"
);
while ($notifTaskRes && ($n = mysqli_fetch_assoc($notifTaskRes))) {
  $notifications[] = [
    'id' => 'task-' . (int)$n['request_id'],
    'type' => 'task',
    'text' => 'Task: ' . trim((string)($n['issue_description'] ?: 'Maintenance request')),
    'ts' => (string)($n['request_date'] ?? date('Y-m-d H:i:s')),
    'path' => 'task_summary.php?status=open',
  ];
}

usort($notifications, function ($a, $b) {
  return strtotime($b['ts']) <=> strtotime($a['ts']);
});
$notifications = array_slice($notifications, 0, 5);

function rp_activity_icon_svg(string $desc, bool $isExpense): string
{
  $descLower = strtolower($desc);
  if (strpos($descLower, 'booking') !== false || strpos($descLower, 'reservation') !== false) {
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
  }
  if (strpos($descLower, 'water') !== false || strpos($descLower, 'electric') !== false || strpos($descLower, 'bill') !== false) {
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3s-6 6.5-6 10a6 6 0 0 0 12 0c0-3.5-6-10-6-10z"/></svg>';
  }
  if ($isExpense) {
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
  }
  return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
}
?>
<section class="right-panel">
  <div class="right-header">
    <div class="notif-btn" id="adminNotifBtn" title="Notifications">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
      </svg>
      <div class="notif-dot" id="adminNotifDot" style="<?= empty($notifications) ? 'display:none;' : '' ?>"></div>
      <div id="adminNotifDropdown" style="display:none;position:absolute;top:44px;left:0;width:280px;max-height:340px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 10px 28px rgba(15,23,42,.18);z-index:9999;">
        <div style="padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:12px;font-weight:700;color:#0f172a;display:flex;align-items:center;justify-content:space-between;gap:8px;">
          <span>Notifications</span>
          <button id="adminNotifMarkAll" type="button" style="border:none;background:none;color:#2563eb;font-size:11px;font-weight:600;cursor:pointer;">Mark all as read</button>
        </div>
        <div id="adminNotifList">
          <?php if (empty($notifications)): ?>
            <div style="padding:14px 12px;color:#94a3b8;font-size:12px;">No new notifications.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <div class="rp-notif-item" data-notif-id="<?= htmlspecialchars($n['id']) ?>" data-path="<?= htmlspecialchars($n['path'] ?? '') ?>" style="padding:10px 12px;border-bottom:1px solid #f8fafc;cursor:pointer;">
                <div style="font-size:12px;color:#0f172a;line-height:1.35;"><?= htmlspecialchars($n['text']) ?></div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars(date('M j, g:i A', strtotime($n['ts']))) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="user-info">
      <div>
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
        <div class="user-role">Property Manager</div>
      </div>
      <div class="user-avatar<?= $_rp_photo_url ? '' : ' user-avatar-initials' ?>">
        <?php if ($_rp_photo_url): ?>
          <img src="<?= htmlspecialchars($_rp_photo_url) ?>" alt="Profile"
               style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
               onerror="this.style.display='none';this.parentElement.classList.add('user-avatar-initials');this.parentElement.insertAdjacentText('beforeend','<?= htmlspecialchars($_rp_initials, ENT_QUOTES) ?>');">
        <?php else: ?>
          <?= htmlspecialchars($_rp_initials) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="right-content">
    <div class="cal-header">
      <span class="cal-month" id="rt-cal-month"><?= htmlspecialchars($monthLabel) ?></span>
      <div class="cal-nav">
        <button class="cal-nav-btn" id="rt-cal-prev" type="button" aria-label="Previous period">‹</button>
        <button class="cal-nav-btn" id="rt-cal-next" type="button" aria-label="Next period">›</button>
      </div>
    </div>
    <div class="cal-days" id="rt-cal-days">
      <?php foreach ($calendarDays as $day): ?>
        <div class="cal-day<?= $day['is_today'] ? ' active' : '' ?>" data-cal-date="<?= htmlspecialchars($day['date']) ?>">
          <?= htmlspecialchars($day['day']) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="topbar-divider"></div>

    <div class="schedule-list" id="rt-right-schedule">
      <?php if (empty($schedule)): ?>
        <div class="schedule-slot">
          <div class="time-col" style="opacity:.35;">&nbsp;</div>
          <div style="flex:1;" class="empty-slot">No open tasks</div>
        </div>
      <?php else: ?>
        <?php foreach ($schedule as $slot):
          $slotTs = !empty($slot['request_date']) ? strtotime($slot['request_date']) : false;
          $timeLabel = $slotTs ? date('g:i a', $slotTs) : '--';
          $prio = strtolower((string)($slot['priority'] ?? 'pending'));
          $eventClass = $prio === 'high' || $prio === 'urgent' || ($slot['request_status'] ?? '') === 'open'
            ? 'coral'
            : (($slot['request_status'] ?? '') === 'in_progress' ? 'teal' : 'dark');
          ?>
          <div class="schedule-slot">
            <div class="time-col"><?= htmlspecialchars($timeLabel) ?></div>
            <div class="event-card <?= $eventClass ?>">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <?= htmlspecialchars($slot['issue_description'] ?: 'Maintenance Task') ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="topbar-divider"></div>

    <div class="section-title">Recently Activity</div>
    <div class="activity-list" id="rt-right-activity">
      <?php if (empty($activities)): ?>
        <div style="padding:14px 6px;color:#94a3b8;font-size:12px;">No recent transactions.</div>
      <?php else: ?>
        <?php foreach ($activities as $a):
          $amount = (float)($a['amount'] ?? 0);
          $isExpense = strtolower((string)($a['type'] ?? '')) === 'expense';
          $sign = $isExpense ? '-' : '+';
          $name = trim((string)($a['description'] ?? 'Transaction'));
          ?>
          <div class="activity-item">
            <div class="activity-avatar">
              <?= rp_activity_icon_svg($name, $isExpense) ?>
            </div>
            <div class="activity-info">
              <div class="activity-name"><?= htmlspecialchars($name) ?></div>
              <div class="activity-date"><?= htmlspecialchars(date('d F Y', strtotime($a['transaction_date'] ?? 'now'))) ?></div>
            </div>
            <div class="activity-amount" style="<?= $isExpense ? 'color:var(--danger);' : '' ?>">
              <?= $sign ?>₱ <?= number_format($amount, 2) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  window.__PS_RIGHT_PANEL__ = {
    notifications: (<?= json_encode($notifications, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS) ?> || []),
    tasks: <?= json_encode($schedule, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS) ?> || []
  };
</script>
<script src="../../assets/js/right-panel.js"></script>