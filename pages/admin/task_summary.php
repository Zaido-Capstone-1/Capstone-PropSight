<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html><html><body>
  <script src="../../assets/js/responsive.js"></script>
  <script src="../../assets/js/admin/task_summary-inline.js"></script>
  </body></html>';
  exit;
}

$page_title = 'Task Summary';
$active_page = 'task_summary';

include '../../includes/db.php';
include '../../includes/layout_open.php';
require_once '../../lib/admin-queries/task_summary_queries.php';
?>
<link rel="stylesheet" href="../../assets/css/admin-css/task_summary.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Task Summary</h1>
      <p class="dash-subtitle">View and monitor all maintenance requests.</p>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Total Tasks</div>
          <div class="stat-value"><span id="rt-task-total"><?= (int) $stats['total'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Open</div>
          <div class="stat-value"><span id="rt-task-open"><?= (int) $stats['open_cnt'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">In Progress</div>
          <div class="stat-value"><span id="rt-task-progress"><?= (int) $stats['in_progress_cnt'] ?></span></div>
        </div>
      </div>
      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Done</div>
          <div class="stat-value"><span id="rt-task-done"><?= (int) $stats['done_cnt'] ?></span></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="task-card-header">
        <div class="task-header-left">
          <span class="card-title">Maintenance Requests</span>
        </div>
        <div class="task-header-right">
          <div class="task-search-wrap">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" id="tsSearch" placeholder="Search task or property…">
          </div>

          <!-- Status dropdown -->
          <div class="ur-drop-wrap" id="tsStatusWrap">
            <button type="button" class="ur-drop-trigger" onclick="toggleUrDrop('tsStatusWrap')">
              <span id="tsStatusLabel">All Status</span>
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>
            <input type="hidden" id="tsStatusVal" value="">
            <div class="ur-drop-menu" id="tsStatusMenu" style="display:none; right:0; left:auto;">
              <button type="button" class="ur-drop-opt active" data-value="" onclick="selectTsStatus(this)">All
                Status</button>
              <button type="button" class="ur-drop-opt" data-value="open" onclick="selectTsStatus(this)">
                <span class="ur-drop-dot" style="background:#ef4444;"></span>Open
              </button>
              <button type="button" class="ur-drop-opt" data-value="in_progress" onclick="selectTsStatus(this)">
                <span class="ur-drop-dot" style="background:#3b82f6;"></span>In Progress
              </button>
              <button type="button" class="ur-drop-opt" data-value="pending" onclick="selectTsStatus(this)">
                <span class="ur-drop-dot" style="background:#f59e0b;"></span>Pending
              </button>
              <button type="button" class="ur-drop-opt" data-value="completed" onclick="selectTsStatus(this)">
                <span class="ur-drop-dot" style="background:#22c55e;"></span>Done
              </button>
              <button type="button" class="ur-drop-opt" data-value="closed" onclick="selectTsStatus(this)">
                <span class="ur-drop-dot" style="background:#6b7280;"></span>Closed
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="task-table-wrap">
        <table class="task-table" style="border-collapse:collapse;">
          <thead>
            <tr>
              <th>#</th>
              <th>Task</th>
              <th>Property</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Requested</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="tsTableBody">
            <?php if (empty($tasks)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:#94a3b8;">No tasks found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($tasks as $task):
                $badge = taskBadge((string) ($task['request_status'] ?? 'pending'));
                $pri = priorityBadge((string) ($task['priority'] ?? 'normal'));
                $rid = (int) $task['request_id'];
                ?>
                <tr data-id="<?= $rid ?>" data-status="<?= htmlspecialchars($task['request_status']) ?>"
                  data-search="<?= strtolower(htmlspecialchars(($task['issue_description'] ?? '') . ' ' . ($task['property_name'] ?? ''))) ?>">
                  <td class="ts-id muted">#<?= str_pad($rid, 4, '0', STR_PAD_LEFT) ?></td>
                  <td class="ts-desc">
                    <?= htmlspecialchars(mb_strimwidth($task['issue_description'] ?? 'Maintenance Task', 0, 60, '…')) ?>
                  </td>
                  <td class="muted"><?= htmlspecialchars($task['property_name'] ?? '—') ?></td>
                  <td><span class="badge <?= $pri['cls'] ?>"><?= $pri['label'] ?></span></td>
                  <td><span class="badge <?= $badge['cls'] ?>" id="badge-<?= $rid ?>"><?= $badge['label'] ?></span></td>
                  <td class="muted" style="white-space:nowrap;">
                    <?= !empty($task['request_date']) ? date('M j, Y', strtotime($task['request_date'])) : '—' ?>
                  </td>
                  <td>
                    <div class="ts-actions">
                      <button class="ts-btn ts-btn-view"
                        onclick="openTaskModal(<?= $rid ?>, <?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>)"
                        title="View & Update">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                          <circle cx="12" cy="12" r="3" />
                        </svg>
                        View
                      </button>
                      <button class="ts-btn ts-btn-delete" onclick="deleteTask(<?= $rid ?>)" title="Delete">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13">
                          <polyline points="3 6 5 6 21 6" />
                          <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>

          <tfoot id="tsFoot" style="display:none;">
            <tr>
              <td colspan="7">
                <div class="txn-pagination">
                  <span class="txn-page-info" id="tsPageInfo"></span>
                  <div class="txn-page-controls" id="tsPageControls" style="display:none;">
                    <button type="button" id="tsPrevBtn" class="txn-chevron-btn" onclick="tsChangePage(-1)" disabled>
                      <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                        height="14">
                        <polyline points="15 18 9 12 15 6" />
                      </svg>
                    </button>
                    <span id="tsPageNumbers" class="txn-page-numbers"></span>
                    <button type="button" id="tsNextBtn" class="txn-chevron-btn" onclick="tsChangePage(1)" disabled>
                      <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                        height="14">
                        <polyline points="9 18 15 12 9 6" />
                      </svg>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div id="tsEmpty" style="display:none;text-align:center;padding:48px 16px;">
        <svg width="36" height="36" fill="none" stroke="#ccc" stroke-width="1.5" viewBox="0 0 24 24"
          style="margin:0 auto 10px;display:block;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <div style="color:#aaa;font-size:14px;">No tasks match your filters.</div>
      </div>
    </div>

  </div>
</div>

<!-- Task Modal -->
<div class="sm-modal-overlay" id="taskModal">
  <div class="sm-modal">
    <div class="sm-modal-head">
      <div>
        <div class="sm-modal-title" id="taskModalTitle">Task Details</div>
        <div class="sm-modal-sub" id="taskModalSub"></div>
      </div>
      <button class="sm-modal-close" onclick="closeTaskModal()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>
    <div class="sm-modal-meta" id="taskDetailGrid"></div>
    <div class="sm-modal-footer">
      <div class="sm-footer-row">
        <div class="sm-status-wrap">
          <label class="sm-status-lbl">Update Status</label>
          <select id="taskStatusSelect" class="sm-select">
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="pending">Pending</option>
            <option value="completed">Done</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div class="sm-footer-btns">
          <button class="sm-btn-primary" onclick="updateTaskStatus()">Save Status</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>window.PS_RT_PAGE = 'task_summary';</script>
<script>window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;</script>
<script src="../../assets/js/toast.js"></script>
<script src="../../assets/js/admin/task_summary.js"></script>
<?php include '../../includes/layout_close.php'; ?>