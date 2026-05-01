<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html><html><body>  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/task_summary-inline.js"></script>
</body></html>';
  exit;
}

$page_title = 'Task Summary';
$active_page = 'task_summary';

include '../../includes/db.php';
include '../../includes/layout_open.php';

$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if ($statusFilter !== 'all') {
  $statusEsc = mysqli_real_escape_string($conn, $statusFilter);
  $where .= " AND m.request_status = '$statusEsc'";
}
if ($search !== '') {
  $searchEsc = mysqli_real_escape_string($conn, $search);
  $where .= " AND (m.issue_description LIKE '%$searchEsc%' OR p.property_name LIKE '%$searchEsc%')";
}

$tasksRes = mysqli_query($conn, "
  SELECT
    m.request_id,
    m.issue_description,
    m.priority,
    m.request_status,
    m.request_date,
    p.property_name
  FROM maintenance_requests m
  LEFT JOIN units u ON u.unit_id = m.unit_id
  LEFT JOIN properties p ON p.property_id = u.property_id
  $where
  ORDER BY m.request_date DESC
");

$tasks = [];
while ($tasksRes && ($r = mysqli_fetch_assoc($tasksRes))) {
  $tasks[] = $r;
}

$statsRes = mysqli_query($conn, "
  SELECT
    COUNT(*) AS total,
    SUM(request_status='open') AS open_cnt,
    SUM(request_status='in_progress') AS in_progress_cnt,
    SUM(request_status='pending') AS pending_cnt,
    SUM(request_status IN ('completed','closed')) AS done_cnt
  FROM maintenance_requests
");
$stats = mysqli_fetch_assoc($statsRes) ?: ['total' => 0, 'open_cnt' => 0, 'in_progress_cnt' => 0, 'pending_cnt' => 0, 'done_cnt' => 0];

function taskBadge(string $status): array
{
  return match ($status) {
    'open' => ['label' => 'Open', 'bg' => 'var(--danger-light)', 'color' => 'var(--danger)'],
    'in_progress' => ['label' => 'Progress', 'bg' => 'var(--blue-50)', 'color' => 'var(--blue-500)'],
    'completed' => ['label' => 'Done', 'bg' => 'var(--success-light)', 'color' => 'var(--success)'],
    'closed' => ['label' => 'Closed', 'bg' => 'var(--success-light)', 'color' => 'var(--success)'],
    default => ['label' => 'Pending', 'bg' => 'var(--pending-light)', 'color' => 'var(--accent-dk)'],
  };
}
?>

<div class="page-header">
  <div class="top-header">
    <h2>Task Summary</h2>
    <div class="page-header-sub">View and monitor all maintenance requests.</div>
  </div>
</div>

<div class="page-inner">
  <div class="cards-area">
    <div class="stat-row">
      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Total Tasks</div>
          <div class="stat-value"><span id="rt-task-total"><?= (int)$stats['total'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Urgent</div>
          <div class="stat-value"><span id="rt-task-open"><?= (int)$stats['open_cnt'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">In Progress</div>
          <div class="stat-value"><span id="rt-task-progress"><?= (int)$stats['in_progress_cnt'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Done</div>
          <div class="stat-value"><span id="rt-task-done"><?= (int)$stats['done_cnt'] ?></span></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:nowrap;overflow-x:auto;">
        <span class="card-title">Maintenance Requests</span>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:nowrap;justify-content:flex-end;width:100%;max-width:140px;min-width:0;">
          <div style="flex:1 1 240px;min-width:220px;">
            <input
              type="text"
              name="search"
              value="<?= htmlspecialchars($search) ?>"
              placeholder="Search task or property..."
              style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;">
          </div>
          <select name="status" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;min-width:140px;">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Urgent</option>
            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Progress</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Done</option>
            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
          </select>
        </form>
      </div>

      <div style="overflow:auto;">
        <table style="width:100%;border-collapse:separate;border-spacing:0 8px;">
          <thead>
            <tr style="text-align:left;color:#64748b;font-size:12px;">
              <th style="padding:8px 12px;">Task</th>
              <th style="padding:8px 12px;">Property</th>
              <th style="padding:8px 12px;">Priority</th>
              <th style="padding:8px 12px;">Status</th>
              <th style="padding:8px 12px;">Requested</th>
            </tr>
          </thead>
          <tbody id="rt-task-table">
            <?php if (empty($tasks)): ?>
              <tr>
                <td colspan="5" style="padding:20px 12px;color:#94a3b8;text-align:center;">No tasks found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($tasks as $task):
                $badge = taskBadge((string)($task['request_status'] ?? 'pending'));
                ?>
                <tr style="background:#f8fafc;">
                  <td style="padding:12px;border-radius:10px 0 0 10px;font-weight:600;">
                    <?= htmlspecialchars((string)($task['issue_description'] ?: 'Maintenance Task')) ?>
                  </td>
                  <td style="padding:12px;"><?= htmlspecialchars((string)($task['property_name'] ?: '—')) ?></td>
                  <td style="padding:12px;"><?= htmlspecialchars(ucfirst((string)($task['priority'] ?: 'Normal'))) ?></td>
                  <td style="padding:12px;">
                    <span style="padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;">
                      <?= htmlspecialchars($badge['label']) ?>
                    </span>
                  </td>
                  <td style="padding:12px;border-radius:0 10px 10px 0;">
                    <?= !empty($task['request_date']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($task['request_date']))) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>window.PS_RT_PAGE = 'task_summary';</script>
<?php include '../../includes/layout_close.php'; ?>
