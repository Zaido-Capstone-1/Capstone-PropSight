<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
  echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"><link rel="stylesheet" href="../../assets/css/admin-css/add_property-inline.css">
</head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/add_property-inline.js"></script>
</body>
</html>';
  exit;
}

$page_title = 'Add Property';
$active_page = 'add_property';
include '../../includes/layout_open.php';

$old = $_SESSION['form_old'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
$success = $_SESSION['form_success'] ?? false;

unset($_SESSION['form_old'], $_SESSION['form_errors'], $_SESSION['form_success']);

function old($key, $default = '')
{
  global $old;
  return htmlspecialchars($old[$key] ?? $default);
}

function err($key)
{
  global $errors;
  if (!isset($errors[$key]))
    return '';
  return '<div class="form-error">' . htmlspecialchars($errors[$key]) . '</div>';
}

function errClass($key)
{
  global $errors;
  return isset($errors[$key]) ? ' input-error' : '';
}
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="../../assets/css/admin-css/add_property-inline.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">
  <div class="dash-page-header">
    <div class="dash-header-left">
      <h1 class="dash-title">Add New Property</h1>
      <p class="dash-subtitle">Fill in the details to register a new property.</p>
    </div>
    <div class="dash-header-actions">
      <a href="properties_list.php" class="btn btn-secondary">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="15 18 9 12 15 6" />
        </svg>
        Back to List
      </a>
    </div>
  </div>
  <div class="cards-area">

    <?php if ($success): ?>

    <?php endif; ?>

    <?php if (isset($errors['db'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($errors['db']) ?></div>
    <?php elseif (!empty($errors)): ?>
      <div class="alert alert-danger">Please fix the highlighted fields before saving.</div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><span class="card-title">Property Information</span></div>

      <form method="POST" action="../../endpoints/admin/add_property.php" novalidate>

        <input type="hidden" name="csrf_token"
          value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>Property Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name" id="fieldName" class="<?= errClass('name') ?>"
              placeholder="e.g. Skyline Apartments" value="<?= old('name') ?>" required />
            <?= err('name') ?>
          </div>

          <div class="form-group">
            <label>Street Address <span style="color:var(--danger)">*</span></label>
            <input type="text" name="address" id="fieldAddress" class="<?= errClass('address') ?>"
              placeholder="e.g. 12 Oak Street" value="<?= old('address') ?>" />
            <?= err('address') ?>
          </div>

          <div class="form-group">
            <label>City <span style="color:var(--danger)">*</span></label>
            <input type="text" name="city" id="fieldCity" class="<?= errClass('city') ?>" placeholder="e.g. Boracay"
              value="<?= old('city') ?>" />
            <?= err('city') ?>
          </div>

          <div class="form-group">
            <label>State / Province</label>
            <input type="text" name="state" id="fieldState" placeholder="e.g. Aklan" value="<?= old('state') ?>" />
          </div>

          <div class="form-group">
            <label>ZIP / Postal Code</label>
            <input type="text" name="zip" id="fieldZip" placeholder="e.g. 5608" value="<?= old('zip') ?>" />
          </div>
        </div>

        <!-- Hidden lat/lng -->
        <input type="hidden" name="latitude" id="fieldLat" value="<?= old('latitude') ?>">
        <input type="hidden" name="longitude" id="fieldLng" value="<?= old('longitude') ?>">

        <!-- ── Map ── -->
        <div class="map-section">
          <div class="map-section-label">
            <svg viewBox="0 0 24 24">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
            Pin Location on Map
          </div>

          <div class="map-search-row">
            <input type="text" id="mapSearchInput" class="map-search-input"
              placeholder="Search an address or place name…"
              onkeydown="if(event.key==='Enter'){event.preventDefault();mapSearch();}">
            <button type="button" class="map-search-btn" onclick="mapSearch()">
              <svg viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              Search
            </button>
            <button type="button" class="map-search-btn" style="background:#64748b;" onclick="mapGeolocate()"
              title="Use current location">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
              </svg>
              My Location
            </button>
          </div>

          <div id="propertyMap"></div>

          <div class="map-hint">
            <svg viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            Click anywhere on the map to drop a pin, or search above. Address fields will auto-fill from the pin.
          </div>

          <div class="map-coords-row">
            <div class="map-coord-box">
              <strong>Latitude</strong>
              <div class="map-coord-val" id="displayLat">Not set</div>
            </div>
            <div class="map-coord-box">
              <strong>Longitude</strong>
              <div class="map-coord-val" id="displayLng">Not set</div>
            </div>
          </div>

          <div class="map-autofill-notice" id="autofillNotice">
            <svg viewBox="0 0 24 24">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            Address fields have been auto-filled from the map pin.
          </div>
        </div>

        <div class="form-actions" style="margin-top:24px;">
          <button type="reset" class="btn btn-secondary" onclick="setTimeout(mapReset,0)">Reset</button>
          <button type="submit" class="btn btn-primary">Save Property</button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- Leaflet JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
  window.__PS_ADD_PROPERTY__ = {
    oldLat: parseFloat('<?= old('latitude') ?>') || null,
    oldLng: parseFloat('<?= old('longitude') ?>') || null,
    success: <?= json_encode($success) ?>
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (window.__PS_ADD_PROPERTY__.success) {
      showToast('Property has been successfully added.', 'success', 'Property Saved');
    }
  });
</script>
<script src="../../assets/js/admin/add_property.js"></script>

<?php include '../../includes/layout_close.php'; ?>