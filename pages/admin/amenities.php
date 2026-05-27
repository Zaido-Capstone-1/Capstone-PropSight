<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/amenities-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Amenities';
$active_page = 'amenities';
include '../../includes/db.php';
include '../../includes/layout_open.php';
include '../../lib/admin-queries/amenities_queries.php';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/amenities.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Amenities</h1>
      <p class="dash-subtitle">Manage amenity offerings across all properties.</p>
    </div>
  </div>
  <div class="cards-area">

    <div class="stat-row">
      <div class="stat-card">
        <div>
          <div class="stat-label">Total</div>
          <div class="stat-value" id="stat-total"><?= $total ?></div>
        </div>
        <div class="stat-icon-wrap blue">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">Available</div>
          <div class="stat-value" id="stat-available"><?= $available ?></div>
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
          <div class="stat-label">Unavailable</div>
          <div class="stat-value" id="stat-unavailable"><?= $unavailable ?></div>
        </div>
        <div class="stat-icon-wrap red">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div>
          <div class="stat-label">Maintenance</div>
          <div class="stat-value" id="stat-maintenance"><?= $maintenance ?></div>
        </div>
        <div class="stat-icon-wrap gold">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
          </svg>
        </div>
      </div>
    </div>

    <div class="amenity-toolbar">
      <div class="tb-left">
        <span class="tb-title">All Amenities</span>
        <span class="tb-count" id="am-count"></span>
      </div>
      <div class="tb-right">
        <div class="am-search-wrap">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input id="am-search" type="text" placeholder="Search amenities..." class="am-input">
        </div>
        <div class="ur-drop-wrap" id="amStatusWrap">
          <button type="button" class="ur-drop-trigger" onclick="toggleUrDrop('amStatusWrap')">
            <span id="amStatusLabel">All Statuses</span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <input type="hidden" id="am-filter-status" value="">
          <div class="ur-drop-menu" id="amStatusMenu" style="display:none;">
            <button type="button" class="ur-drop-opt active" data-value=""
              onclick="selectUrDrop('amStatusWrap','amStatusLabel','am-filter-status',this)">All Statuses</button>
            <button type="button" class="ur-drop-opt" data-value="available"
              onclick="selectUrDrop('amStatusWrap','amStatusLabel','am-filter-status',this)">
              <span class="ur-drop-dot" style="background:#22c55e;"></span>Available
            </button>
            <button type="button" class="ur-drop-opt" data-value="unavailable"
              onclick="selectUrDrop('amStatusWrap','amStatusLabel','am-filter-status',this)">
              <span class="ur-drop-dot" style="background:#ef4444;"></span>Unavailable
            </button>
            <button type="button" class="ur-drop-opt" data-value="maintenance"
              onclick="selectUrDrop('amStatusWrap','amStatusLabel','am-filter-status',this)">
              <span class="ur-drop-dot" style="background:#f59e0b;"></span>Maintenance
            </button>
          </div>
        </div>

        <div class="ur-drop-wrap" id="amPropertyWrap">
          <button type="button" class="ur-drop-trigger" onclick="toggleUrDrop('amPropertyWrap')">
            <span id="amPropertyLabel">All Properties</span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11" height="11">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <input type="hidden" id="am-filter-property" value="">
          <div class="ur-drop-menu" id="amPropertyMenu" style="display:none; right:0; left:auto;">
            <button type="button" class="ur-drop-opt active" data-value=""
              onclick="selectUrDrop('amPropertyWrap','amPropertyLabel','am-filter-property',this)">All
              Properties</button>
            <?php foreach ($properties as $p): ?>
              <button type="button" class="ur-drop-opt" data-value="<?= (int) $p['property_id'] ?>"
                onclick="selectUrDrop('amPropertyWrap','amPropertyLabel','am-filter-property',this)">
                <?= htmlspecialchars($p['property_name']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div id="amenities-container">
      <?php if (empty($properties)): ?>
        <div class="no-properties">
          <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
            style="width:44px;height:44px;margin:0 auto 14px;display:block;opacity:.25;">
            <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z" />
            <path d="M9 21V12h6v9" />
          </svg>
          <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No properties found</div>
          <div style="font-size:13px;opacity:.7;">Add a property first to manage its amenities.</div>
        </div>
      <?php else: ?>
        <?php foreach ($grouped as $pid => $group):
          $propName = $group['property_name'];
          $items = $group['items'];
          // $propIcons = ['🏠', '🏢', '🏬', '🏗️', '🏡', '🌆'];
          // $propIcon = $propIcons[abs(crc32($propName)) % count($propIcons)];
          ?>
          <div class="prop-section" data-property-id="<?= $pid ?>">
            <div class="prop-section-header">
              <div class="prop-section-title">

                <div>
                  <div class="prop-name"><?= htmlspecialchars($propName) ?></div>
                  <div class="prop-count"><?= count($items) ?> amenit<?= count($items) !== 1 ? 'ies' : 'y' ?></div>
                </div>
              </div>
              <button class="prop-add-btn open-add-amenity" data-pid="<?= $pid ?>"
                data-pname="<?= htmlspecialchars($propName, ENT_QUOTES) ?>">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Add Amenity
              </button>
            </div>

            <div class="amenity-grid" id="grid-<?= $pid ?>">
              <?php if (empty($items)): ?>
                <div class="am-empty" id="empty-<?= $pid ?>">
                  <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                    style="width:32px;height:32px;">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2z" />
                  </svg>
                  No amenities added yet for this property.
                </div>
              <?php else: ?>
                <?php foreach ($items as $am):
                  $s = $am['status'];
                  $lbl = ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Under Maintenance'][$s] ?? ucfirst($s);
                  $safeN = htmlspecialchars($am['name'], ENT_QUOTES);
                  ?>
                  <div class="amenity-card" data-id="<?= (int) $am['amenity_id'] ?>" data-status="<?= $s ?>"
                    data-property-id="<?= $pid ?>" data-search="<?= strtolower(htmlspecialchars($am['name'])) ?>">
                    <div class="am-icon-wrap <?= $s ?>"><?= am_svg($am['icon']) ?></div>
                    <div class="am-info">
                      <div class="am-name"><?= htmlspecialchars($am['name']) ?></div>
                      <div class="am-status <?= $s ?>">● <?= $lbl ?></div>
                    </div>
                    <div class="am-actions">
                      <button class="am-btn edit-btn" title="Edit" data-id="<?= (int) $am['amenity_id'] ?>"
                        data-name="<?= $safeN ?>" data-icon="<?= htmlspecialchars($am['icon']) ?>" data-status="<?= $s ?>"
                        data-pid="<?= $pid ?>">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                          style="width:13px;height:13px;">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                      </button>
                      <button class="am-btn del delete-btn" title="Delete" data-id="<?= (int) $am['amenity_id'] ?>"
                        data-name="<?= $safeN ?>" data-pid="<?= $pid ?>">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                          style="width:13px;height:13px;">
                          <polyline points="3 6 5 6 21 6" />
                          <path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2" />
                        </svg>
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php include '../../includes/layout_close.php'; ?>
<script>
  window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
  window.__AMENITY_DATA__ = { properties: <?= json_encode($properties) ?> };
</script>
<script src="../../assets/js/admin/amenities.js"></script>