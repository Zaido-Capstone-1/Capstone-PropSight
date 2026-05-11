<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>

<body>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/expenses-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Expenses';
$active_page = 'expenses';

include '../../includes/db.php';
include '../../includes/layout_open.php';

function db_query($conn, string $sql, array $params = []): array
{
  $st = $conn->prepare($sql);
  if (!$st)
    return [];
  if (!empty($params)) {
    $types = '';
    foreach ($params as $p) {
      $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    $st->bind_param($types, ...$params);
  }
  $st->execute();
  $rows = [];
  $res = $st->get_result();
  while ($row = $res->fetch_assoc())
    $rows[] = $row;
  $st->close();
  return $rows;
}

$month_filter = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = array_pad(explode('-', $month_filter), 2, '01');
$exp_cur_picker_year = (int) date('Y');
$exp_cur_picker_month = (int) $mo;
$date_from = "$yr-$mo-01";
$date_to = date('Y-m-t', strtotime($date_from));

$prev_month = date('Y-m', strtotime('-1 month', strtotime($date_from)));
$next_month = date('Y-m', strtotime('+1 month', strtotime($date_from)));
$next_disabled = $next_month > date('Y-m');

$properties = db_query($conn, "SELECT property_id, property_name FROM properties ORDER BY property_name");
$units = db_query($conn, "SELECT unit_id, unit_name, unit_number, property_id FROM units ORDER BY unit_name");
$all_cats = db_query($conn, "SELECT DISTINCT expense_category FROM expenses ORDER BY expense_category");
?>

<link rel="stylesheet" href="../../assets/css/admin-css/expenses.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
  
  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Expenses</h1>
      <p class="dash-subtitle">Monitor all property-related operational costs.</p>
    </div>
    <div class="dash-header-actions">
      <button class="btn-outline" id="btnExportCSV">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        Export CSV
      </button>
      <button class="btn btn-primary" id="btnOpenAdd">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Log Expense
      </button>
    </div>
  </div>

  <div class="cards-area">

    <div class="stat-row">

      <div class="stat-card sc-red">
        <div class="stat-card-left">
          <div class="stat-label">Total This Month</div>
          <div class="stat-value">
            ₱ <span id="statTotal">0</span>
            <span class="stat-percentage" id="statPercentage">0%</span>
          </div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="12" y1="1" x2="12" y2="23" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-gold">
        <div class="stat-card-left">
          <div class="stat-label">Maintenance</div>
          <div class="stat-value">
            ₱ <span id="statMaintenance">0</span>
            <span class="stat-percentage" id="statMaintenancePercent">0%</span>
          </div>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-blue">
        <div class="stat-card-left">
          <div class="stat-label">Utilities</div>
          <div class="stat-value">
            ₱ <span id="statUtilities">0</span>
            <span class="stat-percentage" id="statUtilitiesPercent">0%</span>
          </div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
          </svg>
        </div>
      </div>

      <div class="stat-card sc-green">
        <div class="stat-card-left">
          <div class="stat-label">Admin / Other</div>
          <div class="stat-value">
            ₱ <span id="statAdmin">0</span>
            <span class="stat-percentage" id="statAdminPercent">0%</span>
          </div>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="2" y="7" width="20" height="14" rx="2" />
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
          </svg>
        </div>
      </div>

    </div>

    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Expense Trend (6 months)</span>
        </div>
        <div class="chart-wrap" style="height:200px;">
          <canvas id="expTrendChart"></canvas>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">By Category</span>
        </div>
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
          <div class="chart-wrap" style="height:160px;width:160px;flex-shrink:0;">
            <canvas id="catDonut"></canvas>
          </div>
          <div class="legend-list" style="flex:1;min-width:130px;" id="legendContainer"></div>
        </div>
      </div>
    </div>

    <div class="card" style="overflow:visible;">
      <div class="card-header-with-filters">
        <div class="header-left">
          Expenses — <span id="expMonthPickerLabel"><?= date('F Y', strtotime($date_from)) ?></span>
        </div>
        <div class="header-right" style="overflow:visible;">

          <!-- ── Month Picker ── -->
          <div style="position:relative;" id="expMonthPickerWrap">
            <button type="button" id="expMonthPickerBtn" onclick="toggleExpMonthPicker()"
              style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;height:34px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--white);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14">
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
              </svg>
              <span id="expMonthPickerLabel2"><?= date('F Y', strtotime($date_from)) ?></span>
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>

            <input type="hidden" id="expMonthFilter"
              value="<?= htmlspecialchars($yr . '-' . str_pad($mo, 2, '0', STR_PAD_LEFT)) ?>">

            <div id="expMonthPickerDropdown"
              style="display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:999;background:var(--white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.13);padding:16px;min-width:248px;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <button type="button" onclick="changeExpPickerYear(-1)"
                  style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">‹</button>
                <span id="expPickerYear"
                  style="font-size:13.5px;font-weight:700;color:var(--text);"><?= $exp_cur_picker_year ?></span>
                <button type="button" onclick="changeExpPickerYear(1)"
                  style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">›</button>
              </div>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;">
                <?php
                $exp_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ($exp_months as $i => $mon):
                  ?>
                  <button type="button" data-month="<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>"
                    onclick="selectExpPickerMonth(this)" class="exp-picker-month-btn"
                    style="padding:6px 4px;border:1.5px solid var(--border);border-radius:7px;font-size:11.5px;font-weight:500;cursor:pointer;background:var(--white);color:var(--text);transition:all .15s;">
                    <?= $mon ?>
                  </button>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
                <button type="button" onclick="closeExpMonthPicker()"
                  style="padding:5px 11px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;background:none;cursor:pointer;color:var(--text-soft);">Cancel</button>
                <button type="button" onclick="applyExpMonthPicker()"
                  style="padding:5px 13px;border:none;border-radius:6px;font-size:12px;font-weight:600;background:var(--primary,#3b6ef5);color:white;cursor:pointer;">Apply</button>
              </div>
            </div>
          </div>
          <!-- ── end month picker ── -->

          <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="Search…">

            <!-- ── Custom Category Dropdown ── -->
            <div class="exp-cat-dropdown-wrap" id="expCatDropdownWrap">
              <button type="button" class="exp-cat-trigger" id="expCatTrigger" onclick="toggleExpCatDropdown()">
                <span id="expCatTriggerLabel">All Categories</span>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12" height="12"
                  id="expCatChevron">
                  <polyline points="6 9 12 15 18 9" />
                </svg>
              </button>
              <!-- Hidden input — expenses.js reads #categoryFilter -->
              <input type="hidden" id="categoryFilter" value="">
              <div class="exp-cat-menu" id="expCatMenu" style="display:none;">
                <button type="button" class="exp-cat-opt active" data-value="" onclick="selectExpCatOpt(this)">All
                  Categories</button>
                <?php foreach ($all_cats as $cat):
                  $cv = htmlspecialchars($cat['expense_category']);
                  ?>
                  <button type="button" class="exp-cat-opt" data-value="<?= $cv ?>" onclick="selectExpCatOpt(this)">
                    <span class="exp-cat-dot" data-cat="<?= $cv ?>"></span><?= $cv ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
            <!-- ── end custom dropdown ── -->

          </div>

        </div>
      </div>

      <div id="tableContainer" style="margin-top:15px;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table id="expensesTable">
          <thead>
            <tr>
              <th>DESCRIPTION</th>
              <th>PROPERTY</th>
              <th>UNIT</th>
              <th>DATE</th>
              <th>CATEGORY</th>
              <th>AMOUNT</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody id="expensesBody"></tbody>

          <tfoot id="expTableFoot" style="display:none;">
            <tr>
              <td colspan="7">
                <div class="exp-pagination">
                  <span class="exp-page-info" id="expPageInfo"></span>
                  <div class="exp-page-controls" id="expPageControls" style="display:none;">
                    <button type="button" id="expPrevBtn" class="exp-chevron-btn" onclick="expChangePage(-1)" disabled>
                      <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14"
                        height="14">
                        <polyline points="15 18 9 12 15 6" />
                      </svg>
                    </button>
                    <span id="expPageNumbers" class="exp-page-numbers"></span>
                    <button type="button" id="expNextBtn" class="exp-chevron-btn" onclick="expChangePage(1)" disabled>
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

        <div id="emptyState" class="table-empty" style="display:none;">
          <div class="table-empty-text">No expenses found.</div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ── Add / Edit Modal ── -->
<div class="modal-overlay" id="expenseModal">
  <div class="modal">
    <button class="modal-close" onclick="ExpenseModal.close()">&times;</button>

    <div class="modal-title">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="2" y="7" width="20" height="14" rx="2" />
        <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" />
      </svg>
      <span id="modalTitle">Log Expense</span>
    </div>

    <input type="hidden" id="editId">

    <div class="form-row">
      <div class="form-group">
        <label>Property</label>
        <select id="fProperty">
          <option value="">Select Property</option>
          <?php foreach ($properties as $p): ?>
            <option value="<?= $p['property_id'] ?>"><?= htmlspecialchars($p['property_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Unit <span style="color:var(--text-soft);font-size:11px;">(Optional)</span></label>
        <select id="fUnit">
          <option value="">Select Unit</option>
          <?php foreach ($units as $u): ?>
            <?php $unitLabel = $u['unit_name'] ?: $u['unit_number'] ?: 'Unit ' . $u['unit_id']; ?>
            <option value="<?= $u['unit_id'] ?>" data-property-id="<?= $u['property_id'] ?>">
              <?= htmlspecialchars($unitLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Category <span style="color:var(--danger);">*</span></label>
        <select id="fCategory">
          <option value="">Select Category</option>
          <option>Maintenance</option>
          <option>Utilities</option>
          <option>Salaries</option>
          <option>Admin</option>
          <option>Insurance</option>
          <option>Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Date <span style="color:var(--danger);">*</span></label>
        <input type="date" id="fDate">
      </div>
    </div>

    <div class="form-group">
      <label>Description <span style="color:var(--danger);">*</span></label>
      <input type="text" id="fDescription" placeholder="e.g. HVAC Repair — Unit 3B">
    </div>

    <div class="form-group">
      <label>Amount (₱) <span style="color:var(--danger);">*</span></label>
      <input type="number" id="fAmount" min="0.01" step="0.01" placeholder="0.00">
    </div>

    <div class="modal-actions">
      <button class="btn-outline" onclick="ExpenseModal.close()">Cancel</button>
      <button class="btn btn-primary" id="btnSave">Save Expense</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- ── Delete Confirmation Modal ── -->
<div class="modal-overlay" id="deleteConfirmModal">
  <div class="modal" style="max-width:420px;">
    <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
    <div class="modal-title">
      <svg width="18" height="18" fill="none" stroke="var(--danger,#e53e3e)" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6" />
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
        <path d="M10 11v6M14 11v6" />
        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
      </svg>
      <span>Delete Expense</span>
    </div>
    <p style="margin:0 0 24px;font-size:14.5px;color:var(--text-soft,#555);line-height:1.6;">
      Are you sure you want to delete this expense? This action cannot be undone.
    </p>
    <div class="modal-actions">
      <button class="btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-primary" id="btnConfirmDelete"
        style="background:var(--danger,#c0392b);border-color:var(--danger,#c0392b);">
        Yes, Delete
      </button>
    </div>
  </div>
</div>

<script>
  window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../../assets/js/admin/expenses.js"></script>
<?php include '../../includes/layout_close.php'; ?>