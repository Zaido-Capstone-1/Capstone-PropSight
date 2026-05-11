<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/units_rooms-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Units / Rooms';
$active_page = 'units_rooms';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$stats = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(status='occupied')    AS occupied,
           SUM(status='vacant')      AS vacant,
           SUM(status='maintenance') AS maintenance
    FROM units
")->fetch_assoc();

$total = (int) $stats['total'];
$occupied = (int) $stats['occupied'];
$vacant = (int) $stats['vacant'];
$maintenance = (int) $stats['maintenance'];

$properties = [];
$pr = $conn->query("SELECT property_id, property_name FROM properties ORDER BY property_name ASC");
while ($p = $pr->fetch_assoc())
  $properties[] = $p;

$units_result = $conn->query("
    SELECT u.*, p.property_name,
        NULLIF(TRIM(CONCAT(usr.first_name, ' ', usr.last_name)), '') AS tenant_name,
        usr.email AS tenant_email,
        usr.profile_photo AS tenant_photo,
        CASE WHEN b.booking_id IS NOT NULL THEN 'occupied' ELSE u.status END AS real_status
    FROM units u
    LEFT JOIN properties p ON u.property_id = p.property_id
    LEFT JOIN bookings b
        ON u.unit_id = b.unit_id
        AND b.status IN ('confirmed', 'completed')
    LEFT JOIN users usr
        ON b.user_id = usr.user_id
    ORDER BY u.unit_id DESC
");
?>

<link rel="stylesheet" href="../../assets/css/admin-css/unit_rooms.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Units & Rooms</h1>
      <p class="dash-subtitle">View and manage individual units across all properties.</p>
    </div>
    <div class="dash-header-actions">
      <button class="btn btn-primary" id="open-add-unit-modal">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Unit
      </button>
    </div>
  </div>
  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card">
        <div>
          <div class="stat-label">Total Units</div>
          <div class="stat-value" id="stat-total"><?= $total ?></div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M3 9h18M9 21V9" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">Occupied</div>
          <div class="stat-value" id="stat-occupied"><?= $occupied ?></div>
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
          <div class="stat-label">Maintenance</div>
          <div class="stat-value" id="stat-maintenance"><?= $maintenance ?></div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
        </div>
      </div>
    </div>

    <div class="toolbar-card">
      <div class="tc-left">
        <span class="tc-title">Unit Directory</span>
        <span class="tc-count" id="units-count"></span>
      </div>
      <div class="tc-right">
        <div style="position:relative;">
          <input id="search-units" type="text" placeholder="Search units..." class="tc-input">
        </div>
        <select id="filter-status" class="tc-select" style="width:138px;">
          <option value="">All Statuses</option>
          <option value="occupied">Occupied</option>
          <option value="vacant">Vacant</option>
          <option value="maintenance">Maintenance</option>
        </select>
        <select id="filter-property" class="tc-select" style="width:165px;">
          <option value="">All Properties</option>
          <?php foreach ($properties as $p): ?>
            <option value="<?= (int) $p['property_id'] ?>">
              <?= htmlspecialchars($p['property_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div class="ur-drop-wrap" id="urStatusWrap">
          <button type="button" class="ur-drop-trigger" onclick="toggleUrDrop('urStatusWrap')">
            <span id="urStatusLabel">All Statuses</span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <input type="hidden" id="filter-status" value="">
          <div class="ur-drop-menu" id="urStatusMenu" style="display:none; right:0; left:auto;">
            <button type="button" class="ur-drop-opt active" data-value=""
              onclick="selectUrDrop('urStatusWrap','urStatusLabel','filter-status',this)">All Statuses</button>
            <button type="button" class="ur-drop-opt" data-value="occupied"
              onclick="selectUrDrop('urStatusWrap','urStatusLabel','filter-status',this)">
              <span class="ur-drop-dot" style="background:#22c55e;"></span>Occupied
            </button>
            <button type="button" class="ur-drop-opt" data-value="vacant"
              onclick="selectUrDrop('urStatusWrap','urStatusLabel','filter-status',this)">
              <span class="ur-drop-dot" style="background:#3b82f6;"></span>Vacant
            </button>
            <button type="button" class="ur-drop-opt" data-value="maintenance"
              onclick="selectUrDrop('urStatusWrap','urStatusLabel','filter-status',this)">
              <span class="ur-drop-dot" style="background:#f59e0b;"></span>Maintenance
            </button>
          </div>
        </div>

        <div class="ur-drop-wrap" id="urPropertyWrap">
          <button type="button" class="ur-drop-trigger" onclick="toggleUrDrop('urPropertyWrap')">
            <span id="urPropertyLabel">All Properties</span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <input type="hidden" id="filter-property" value="">
          <div class="ur-drop-menu" id="urPropertyMenu" style="display:none;">
            <button type="button" class="ur-drop-opt active" data-value=""
              onclick="selectUrDrop('urPropertyWrap','urPropertyLabel','filter-property',this)">All Properties</button>
            <?php foreach ($properties as $p): ?>
              <button type="button" class="ur-drop-opt" data-value="<?= (int) $p['property_id'] ?>"
                onclick="selectUrDrop('urPropertyWrap','urPropertyLabel','filter-property',this)">
                <?= htmlspecialchars($p['property_name']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div id="units-grid">

      <?php if ($units_result->num_rows === 0): ?>
        <div id="empty-state-row" style="grid-column:1/-1;text-align:center;padding:72px 32px;color:var(--text-soft);">
          <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
            style="width:44px;height:44px;margin:0 auto 14px;display:block;opacity:.25;">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M3 9h18M9 21V9" />
          </svg>
          <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No units yet</div>
          <div style="font-size:13px;opacity:.7;">Click "Add Unit" to get started.</div>
        </div>
      <?php else: ?>
        <?php while ($unit = $units_result->fetch_assoc()):
          $uid = (int) $unit['unit_id'];
          $status = strtolower($unit['real_status'] ?? $unit['status'] ?? 'vacant');

          $imgs = [];
          $ir = $conn->query("SELECT image_path FROM unit_images WHERE unit_id=$uid ORDER BY sort_order ASC LIMIT 6");
          while ($ir && $row = $ir->fetch_assoc())
            $imgs[] = $row['image_path'];
          $thumb = $imgs[0] ?? null;

          $unit_json = htmlspecialchars(json_encode([
            'unit_id' => $uid,
            'unit_number' => $unit['unit_number'] ?? '',
            'unit_name' => $unit['unit_name'] ?? '',
            'property_id' => (int) $unit['property_id'],
            'property_name' => $unit['property_name'] ?? '',
            'unit_type' => $unit['unit_type'] ?? '',
            'floor' => $unit['floor'] ?? '',
            'rent_amount' => $unit['rent_amount'] ?? 0,
            'status' => $unit['real_status'] ?? $unit['status'] ?? '',
            'tenant_name' => $unit['tenant_name'] ?? '',
            'tenant_email' => $unit['tenant_email'] ?? '',
            'tenant_photo' => $unit['tenant_photo'] ?? '',
            'images' => $imgs,
          ]), ENT_QUOTES);

          $search_str = strtolower(implode(' ', [
            $unit['unit_number'] ?? '',
            $unit['unit_name'] ?? '',
            $unit['property_name'] ?? '',
            $unit['unit_type'] ?? '',
            $unit['tenant_name'] ?? '',
          ]));

          $initials = '';
          if (!empty($unit['tenant_name'])) {
            $parts = array_slice(explode(' ', trim($unit['tenant_name'])), 0, 2);
            $initials = strtoupper(implode('', array_map(fn($w) => $w[0], $parts)));
          }
          ?>
          <div class="unit-listing-card" data-property-id="<?= (int) $unit['property_id'] ?>"
            data-status="<?= htmlspecialchars($status) ?>" data-search="<?= htmlspecialchars($search_str) ?>">

            <div class="photo-wrap">
              <?php if ($thumb): ?>
                <img src="<?= rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/') ?>/<?= htmlspecialchars($thumb) ?>"
                  alt="Unit photo">
                <div class="overlay"></div>
                <?php if (count($imgs) > 1): ?>
                  <span class="photo-count-pill">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:11px;height:11px;display:inline;vertical-align:middle;margin-right:3px;">
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" />
                      <polyline points="21 15 16 10 5 21" />
                    </svg>
                    <?= count($imgs) ?> photos
                  </span>
                <?php endif; ?>
              <?php else: ?>
                <div class="no-photo">
                  <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                    style="width:36px;height:36px;">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                  </svg>
                  <span style="font-size:12px;">No photos added</span>
                </div>
              <?php endif; ?>
              <span class="status-pill <?= $status ?>"><?= ucfirst($status) ?></span>
            </div>

            <div class="body">

              <div>
                <div class="unit-title"><?= htmlspecialchars($unit['unit_number'] ?? '—') ?></div>
                <div class="unit-sub">
                  <?php
                  $sub_parts = array_filter([
                    $unit['property_name'] ?? '',
                    $unit['unit_name'] ?? '',
                  ]);
                  echo htmlspecialchars(implode(' · ', $sub_parts));
                  ?>
                </div>
              </div>

              <div class="meta-row">
                <?php if (!empty($unit['unit_type'])): ?>
                  <span class="meta-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:13px;height:13px;">
                      <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z" />
                      <path d="M9 21V12h6v9" />
                    </svg>
                    <?= htmlspecialchars($unit['unit_type']) ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($unit['floor'])): ?>
                  <span class="meta-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:13px;height:13px;">
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <path d="M3 9h18M9 21V9" />
                    </svg>
                    Floor <?= htmlspecialchars((string) $unit['floor']) ?>
                  </span>
                <?php endif; ?>
                <?php if ($status === 'occupied' && !empty($unit['tenant_name'])): ?>
                  <span class="meta-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:13px;height:13px;">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                    <?= htmlspecialchars($unit['tenant_name']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="tags">
                <?php
                $tags = array_filter([
                  $unit['unit_type'] ?? '',
                  (!empty($unit['floor'])) ? 'Floor ' . $unit['floor'] : '',
                  !empty($unit['tenant_name']) ? 'Occupied' : '',
                ]);
                $tag_list = [];
                if (!empty($unit['unit_type']))
                  $tag_list[] = $unit['unit_type'];
                if (!empty($unit['floor']))
                  $tag_list[] = 'Floor ' . $unit['floor'];
                if ($status === 'vacant')
                  $tag_list[] = 'Available';
                if ($status === 'maintenance')
                  $tag_list[] = 'Under Maintenance';
                foreach (array_slice($tag_list, 0, 4) as $tag):
                  ?>
                  <span class="tag"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>

              <div class="footer">
                <div class="price">
                  <span class="price-value">₱<?= number_format((float) $unit['rent_amount'], 0) ?></span>
                  <span class="price-label">/ Night</span>
                </div>
                <div class="card-actions">
                  <button class="btn-view view-unit-btn" data-unit="<?= $unit_json ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:13px;height:13px;">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    View
                  </button>
                  <button class="btn-del delete-unit-btn" data-id="<?= $uid ?>"
                    data-name="<?= htmlspecialchars($unit['unit_number'] ?? '') ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                      style="width:13px;height:13px;">
                      <polyline points="3 6 5 6 21 6" />
                      <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                    Delete
                  </button>
                </div>
              </div>

            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>

    </div>

  </div>
</div>

<?php include '../../includes/layout_close.php'; ?>
<script>
  window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
  window.__UNITS_DATA__ = { propertiesList: <?= json_encode($properties) ?> };
</script>

<script>
  window.APP_BASE = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME'], 3), '/') ?>';
</script>
<script src="../../assets/js/admin/unit-rooms.js"></script>