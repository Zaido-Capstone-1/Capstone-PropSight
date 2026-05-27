<?php
include '../../includes/session.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sessionToken = $_SESSION['csrf_token'] ?? '';
  $requestToken = $_POST['csrf_token'] ?? '';

  if (empty($sessionToken) || !hash_equals($sessionToken, $requestToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
    exit;
  }
}

include_once '../../includes/db.php';

$page_title = 'Properties List';
$active_page = 'properties_list';
include '../../includes/layout_open.php';
include '../../includes/properties.php';
include '../../lib/admin-queries/properties_list_queries.php';

?>

<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">All Properties</h1>
      <p class="dash-subtitle">Manage and monitor all registered properties.</p>
    </div>
    <div class="dash-header-actions">
      <a href="add_property.php" class="btn btn-primary">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Property
      </a>
    </div>
  </div>
  <div class="cards-area">
    <div class="stat-row">

      <div class="stat-card">
        <div>
          <div class="stat-label">Total Properties</div>
          <div class="stat-value" id="stat-total"><?= $total ?></div>
          <div class="stat-sub">+<span id="stat-new-month"><?= $new_month ?></span> this month</div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M3 9.5L12 3l9 6.5V21H3V9.5z" />
          </svg>
        </div>
      </div>

      <div class="stat-card">
        <div>
          <div class="stat-label">Occupied</div>
          <div class="stat-value" id="stat-occupied"><?= $occupied ?></div>
          <div class="stat-sub"><span id="stat-occ-pct"><?= $occ_pct ?></span>% occupancy</div>
        </div>
        <div class="stat-icon-wrap green">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </div>
      </div>

      <div class="stat-card">
        <div>
          <div class="stat-label">Vacant</div>
          <div class="stat-value" id="stat-vacant"><?= $vacant ?></div>
          <div class="stat-sub">Available now</div>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
        </div>
      </div>

      <div class="stat-card">
        <div>
          <div class="stat-label">Under Maintenance</div>
          <div class="stat-value" id="stat-maintenance"><?= $maintenance ?></div>
          <div class="stat-sub">Properties flagged</div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
        </div>
      </div>

    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Property Directory</span>
        <div style="display:flex;gap:8px;">
          <form method="GET" action="" style="margin:0;">
            <select name="type" onchange="this.form.submit()" id="typeSelect"
              style="padding:7px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:13px;color:var(--text-soft);background:var(--white);">
              <option value="">All Types</option>
              <?php foreach ($allowed_types as $t): ?>
                <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Property</th>
              <th>Type</th>
              <th>Location</th>
              <th>Units</th>
              <th>Occupancy</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>

            <?php if (mysqli_num_rows($result) === 0): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:32px;color:var(--text-soft);">
                  No properties
                  found<?= $filter_type ? ' for type <strong>' . htmlspecialchars($filter_type) . '</strong>' : '' ?>.
                </td>
              </tr>

            <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php
                $pid = (int) $row['property_id'];
                $unitStmt->bind_param('i', $pid);
                $unitStmt->execute();
                $unit_data = $unitStmt->get_result()->fetch_assoc() ?? [];
                $total_units = (int) ($unit_data['total_units'] ?? 0);
                $occupied_units = (int) ($unit_data['occupied_units'] ?? 0);
                $row_pct = $total_units > 0 ? round(($occupied_units / $total_units) * 100) : 0;
                $bar_col = bar_color($row_pct);
                $prop_id = 'P-' . str_pad($pid, 3, '0', STR_PAD_LEFT);
                ?>
                <tr>
                  <td>
                    <div>
                      <div style="font-weight:600;"><?= htmlspecialchars($row['property_name']) ?></div>
                      <div style="font-size:11px;color:var(--text-soft);">ID #<?= $prop_id ?></div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($row['property_type']) ?></td>
                  <td><?= htmlspecialchars($row['address']) ?></td>
                  <td><?= $total_units ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div style="flex:1;height:5px;background:var(--blue-50);border-radius:3px;">
                        <div style="width:<?= $row_pct ?>%;height:100%;background:<?= $bar_col ?>;border-radius:3px;"></div>
                      </div>
                      <span style="font-size:12px;font-weight:600;"><?= $row_pct ?>%</span>
                    </div>
                  </td>
                  <td><?= status_badge($row['status']) ?></td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <button class="btn btn-secondary view-property-btn" style="padding:5px 12px;font-size:12px;"
                        data-id="<?= $pid ?>" data-name="<?= htmlspecialchars($row['property_name'], ENT_QUOTES) ?>"
                        data-propid="<?= htmlspecialchars($prop_id) ?>"
                        data-type="<?= htmlspecialchars($row['property_type'], ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($row['address'], ENT_QUOTES) ?>" data-units="<?= $total_units ?>"
                        data-occupied="<?= $occupied_units ?>" data-pct="<?= $row_pct ?>"
                        data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>">View</button>
                      <button class="btn btn-danger delete-property-btn" style="padding:5px 12px;font-size:12px;"
                        data-id="<?= $pid ?>"
                        data-name="<?= htmlspecialchars($row['property_name'], ENT_QUOTES) ?>">Delete</button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php $unitStmt->close(); ?>
            <?php endif; ?>

          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Property View Modal -->
<div id="property-view-overlay"
  style="display:none;visibility:hidden;opacity:0;pointer-events:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;padding:1rem;">
  <div id="property-view-modal"
    style="background:var(--white);border-radius:16px;border:1px solid var(--border);width:100%;max-width:500px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.12);">

    <!-- Header -->
    <div
      style="background:var(--bg-soft, #f8f8f8);padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
      <div>
        <p id="pvm-name" style="margin:0;font-size:15px;font-weight:600;color:var(--text);">—</p>
        <p id="pvm-meta" style="margin:2px 0 0;font-size:12px;color:var(--text-soft);">—</p>
      </div>
      <button id="pvm-close"
        style="background:none;border:none;cursor:pointer;padding:2px;color:var(--text-soft);line-height:1;flex-shrink:0;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </div>

    <!-- Metric strip -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid var(--border);">
      <div style="padding:1rem;text-align:center;border-right:1px solid var(--border);">
        <p style="margin:0;font-size:11px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em;">Units
        </p>
        <p id="pvm-units" style="margin:4px 0 0;font-size:22px;font-weight:600;color:var(--text);">—</p>
      </div>
      <div style="padding:1rem;text-align:center;border-right:1px solid var(--border);">
        <p style="margin:0;font-size:11px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em;">
          Occupied</p>
        <p id="pvm-occupied" style="margin:4px 0 0;font-size:22px;font-weight:600;color:var(--text);">—</p>
      </div>
      <div style="padding:1rem;text-align:center;">
        <p style="margin:0;font-size:11px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.05em;">
          Occupancy</p>
        <p id="pvm-pct" style="margin:4px 0 0;font-size:22px;font-weight:600;color:var(--text);">—</p>
      </div>
    </div>

    <!-- Detail rows -->
    <div style="padding:0 1.4rem;">
      <div
        style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
        <span style="font-size:12px;color:var(--text-soft);">Address</span>
        <span id="pvm-address" style="font-size:13px;color:var(--text);text-align:right;max-width:65%;">—</span>
      </div>
      <div
        style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
        <span style="font-size:12px;color:var(--text-soft);">Type</span>
        <span id="pvm-type" style="font-size:13px;color:var(--text);">—</span>
      </div>
      <div
        style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
        <span style="font-size:12px;color:var(--text-soft);">Status</span>
        <span id="pvm-status">—</span>
      </div>
      <div style="padding:10px 0;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <span style="font-size:12px;color:var(--text-soft);">Occupancy rate</span>
          <span id="pvm-bar-label" style="font-size:12px;font-weight:600;color:var(--text);">0%</span>
        </div>
        <div style="height:5px;background:var(--blue-50);border-radius:3px;overflow:hidden;">
          <div id="pvm-bar" style="height:100%;width:0%;border-radius:3px;transition:width .4s ease;"></div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div style="padding:1rem 1.4rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;">
      <button id="pvm-close-btn" class="btn btn-secondary" style="padding:6px 16px;font-size:13px;">Close</button>
    </div>

  </div>
</div>

<style>
  #typeSelect:focus {
    border-color: #4f8ef7 !important;
    outline: none;
  }
</style>

<script>
  window.PS_CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/properties_list.js"></script>

<?php
mysqli_close($conn);
include '../../includes/layout_close.php';
?>