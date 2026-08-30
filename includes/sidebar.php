<?php
function nav_active(string $key): string
{
  global $active_page;
  return ($active_page === $key) ? ' active' : '';
}

function sub_active(string $key): string
{
  global $active_page;
  return ($active_page === $key) ? ' active' : '';
}

function group_active(array $keys): string
{
  global $active_page;
  return in_array($active_page, $keys, true) ? ' active expanded' : '';
}
function group_sub_open(array $keys): string
{
  global $active_page;
  return in_array($active_page, $keys, true) ? ' open' : '';
}

// ── Profile photo / initials helper ──────────────────────────────────────────
$_sb_photo_raw = trim((string) ($_SESSION['profile_photo'] ?? ''));
if ($_sb_photo_raw === '' && isset($_SESSION['user_id']) && !empty($conn)) {
  $_sb_uid = (int) $_SESSION['user_id'];
  $_sb_r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$_sb_uid LIMIT 1"));
  $_sb_photo_raw = trim((string) ($_sb_r['profile_photo'] ?? ''));
  if ($_sb_photo_raw !== '') {
    $_SESSION['profile_photo'] = $_sb_photo_raw;
  }
}
// Build a URL two levels up from pages/admin/
$_sb_photo_url = '';
if ($_sb_photo_raw !== '') {
  $_sb_photo_url = preg_match('#^https?://#i', $_sb_photo_raw) ? $_sb_photo_raw : '../../' . ltrim($_sb_photo_raw, '/');
}
// Compute initials from session name
$_sb_name = trim((string) ($_SESSION['name'] ?? 'Admin'));
$_sb_parts = array_filter(explode(' ', $_sb_name));
$_sb_initials = '';
foreach (array_slice($_sb_parts, 0, 2) as $p) {
  $_sb_initials .= strtoupper(mb_substr($p, 0, 1));
}
if ($_sb_initials === '')
  $_sb_initials = 'A';
?>

<link rel="stylesheet" href="../../assets/css/admin-css/sidebar.css">

<div class="overlay" id="overlay"></div>

<button class="menu-toggle" id="menuToggle" aria-label="Open menu">
  <span></span><span></span><span></span>
</button>

<!-- Mobile notification bell — only visible when right-panel is hidden (≤1024px) -->
<button class="mobile-notif-btn" id="mobileNotifBtn" aria-label="Notifications" title="Notifications">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18" height="18">
    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
  </svg>
  <span class="mobile-notif-dot" id="mobileNotifDot" style="display:none;"></span>
</button>

<!-- Mobile notification dropdown -->
<div id="mobileNotifDropdown"
  style="display:none;position:fixed;top:62px;right:8px;left:auto;width:260px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 10px 32px rgba(15,23,42,.25);z-index:9999;flex-direction:column;font-family:'DM Sans',sans-serif;">
  <div
    style="padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;font-weight:700;color:#0f172a;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background-color:#ffffff;border-radius:14px 14px 0 0;">
    <span>Notifications</span>
    <button id="mobileNotifMarkAll" type="button"
      style="border:none;background:none;color:#2563eb;font-size:12px;font-weight:600;cursor:pointer;">Mark all
      read</button>
  </div>
  <div style="overflow-y:auto;max-height:60vh;">
    <div id="mobileNotifList">
      <div style="padding:24px 14px;text-align:center;color:#94a3b8;font-size:13px;">No notifications yet.</div>
    </div>
    <div id="mobileNotifViewMore" style="display:none;padding:0;text-align:center;border-top:1px solid #f1f5f9;">
      <button type="button" id="mobileNotifViewMoreBtn"
        style="display:block;width:100%;border:none;background:none;color:#2563eb;font-size:14px;font-weight:600;cursor:pointer;padding:12px 14px;border-radius:0 0 14px 14px;transition:background .12s;">View
        more</button>
    </div>
  </div>
</div>

<nav class="sidebar" id="sidebar" aria-label="Main navigation">

  <div class="sidebar-top">
    <div class="sidebar-logo">
      <img src="../../assets/images/final logo.png" alt="PropSight Logo"
        style="width:50px; height:50px; object-fit:contain;" />
    </div>
    <div style="display:flex; flex-direction:column; gap:2px;">
      <div class="brand-name">PropSight</div>
      <div class="brand-sub">Boracay Accommodation</div>
    </div>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">✕</button>
  </div>

  <div class="sidebar-nav">

    <div class="nav-section-label">Overview</div>

    <a href="index.php" class="nav-item<?= nav_active('dashboard') ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="3" width="7" height="7" rx="1.5" />
          <rect x="3" y="14" width="7" height="7" rx="1.5" />
          <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg>
      </div>
      <span class="nav-label">Dashboard</span>
    </a>

    <a href="analytics.php" class="nav-item<?= nav_active('analytics') ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
      </div>
      <span class="nav-label">Analytics</span>
    </a>

    <div class="nav-divider"></div>

    <div class="nav-section-label">Operations</div>

    <div class="nav-item has-sub<?= group_active(['properties_list', 'add_property', 'units_rooms', 'amenities']) ?>">
      <div class="nav-icon">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z" />
          <path d="M9 21V12h6v9" />
        </svg>
      </div>
      <span class="nav-label">Properties</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['properties_list', 'add_property', 'units_rooms', 'amenities']) ?>">
      <a href="properties_list.php" class="sub-item<?= sub_active('properties_list') ?>">
        <div class="sub-dot"></div>Properties List
      </a>
      <a href="add_property.php" class="sub-item<?= sub_active('add_property') ?>">
        <div class="sub-dot"></div>Add Property
      </a>
      <a href="units_rooms.php" class="sub-item<?= sub_active('units_rooms') ?>">
        <div class="sub-dot"></div>Units / Rooms
      </a>
      <a href="amenities.php" class="sub-item<?= sub_active('amenities') ?>">
        <div class="sub-dot"></div>Amenities
      </a>
    </div>

    <div class="nav-item has-sub<?= group_active(['reservations', 'calendar', 'checkin_checkout']) ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>
      <span class="nav-label">Bookings</span>
      <span class="nav-badge" data-rt="bookings" style="display:none;">0</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['reservations', 'calendar', 'checkin_checkout']) ?>">
      <a href="reservations.php" class="sub-item<?= sub_active('reservations') ?>">
        <div class="sub-dot"></div>Reservations
      </a>
      <a href="calendar.php" class="sub-item<?= sub_active('calendar') ?>">
        <div class="sub-dot"></div>Calendar / Availability
      </a>
      <a href="checkin_checkout.php" class="sub-item<?= sub_active('checkin_checkout') ?>">
        <div class="sub-dot"></div>Check-in / Check-out
      </a>
    </div>

    <div class="nav-item has-sub<?= group_active(['guests_clients', 'staff_roles']) ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
      </div>
      <span class="nav-label">Users</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['guests_clients', 'staff_roles']) ?>">
      <a href="guests_clients.php" class="sub-item<?= sub_active('guests_clients') ?>">
        <div class="sub-dot"></div>Guests / Clients
      </a>
      <a href="staff_roles.php" class="sub-item<?= sub_active('staff_roles') ?>">
        <div class="sub-dot"></div>Staff / Admin Roles
      </a>
    </div>

    <div
      class="nav-item has-sub<?= group_active(['payments', 'transactions', 'invoices_billing', 'expenses', 'refunds']) ?>">
      <div class="nav-icon">
        <!-- <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <line x1="12" y1="1" x2="12" y2="23" />
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg> -->

        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24" fill="none">
          <path fill="none" stroke="currentColor" stroke-width="2"
            d="M20 11H4m16-4H4m3 14V4a1 1 0 0 1 1-1h4a1 1 0 0 1 0 12H7" />
        </svg>
      </div>
      <span class="nav-label">Financial</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['payments', 'transactions', 'invoices_billing', 'expenses', 'refunds']) ?>">
      <a href="payments.php" class="sub-item<?= sub_active('payments') ?>">
        <div class="sub-dot"></div>Payments
      </a>
      <a href="transactions.php" class="sub-item<?= sub_active('transactions') ?>">
        <div class="sub-dot"></div>Transactions
      </a>
      <a href="invoices_billing.php" class="sub-item<?= sub_active('invoices_billing') ?>">
        <div class="sub-dot"></div>Invoices / Billing
      </a>
      <a href="expenses.php" class="sub-item<?= sub_active('expenses') ?>">
        <div class="sub-dot"></div>Expenses
      </a>
      <a href="refunds.php" class="sub-item<?= sub_active('refunds') ?>">
        <div class="sub-dot"></div>Refunds
      </a>
    </div>

    <div class="nav-item has-sub<?= group_active(['financial_reports', 'occupancy_reports', 'booking_reports']) ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="16" y1="13" x2="8" y2="13" />
          <line x1="16" y1="17" x2="8" y2="17" />
        </svg>
      </div>
      <span class="nav-label">Reports</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['financial_reports', 'occupancy_reports', 'booking_reports']) ?>">
      <a href="financial_reports.php" class="sub-item<?= sub_active('financial_reports') ?>">
        <div class="sub-dot"></div>Financial Reports
      </a>
      <a href="occupancy_reports.php" class="sub-item<?= sub_active('occupancy_reports') ?>">
        <div class="sub-dot"></div>Occupancy Reports
      </a>
      <a href="booking_reports.php" class="sub-item<?= sub_active('booking_reports') ?>">
        <div class="sub-dot"></div>Booking Reports
      </a>
    </div>

    <div class="nav-item has-sub<?= group_active(['support', 'task_summary']) ?>">
      <div class="nav-icon">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
      </div>
      <span class="nav-label">Support & Help</span>
      <span class="nav-badge" data-rt="support" style="display:none;">0</span>
      <span class="nav-arrow">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </span>
    </div>
    <div class="nav-sub<?= group_sub_open(['support', 'task_summary']) ?>">
      <a href="support.php" class="sub-item<?= sub_active('support') ?>">
        <div class="sub-dot"></div>Support Tickets
      </a>
      <a href="task_summary.php" class="sub-item<?= sub_active('task_summary') ?>">
        <div class="sub-dot"></div>Maintenance Requests
      </a>
    </div>

    <a href="loyalty_rewards.php" class="nav-item<?= nav_active('loyalty_rewards') ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="8" r="5" />
          <path d="M14.5 11.9L16 21l-4-2.4-4 2.4 1.5-9.1" />
        </svg>
      </div>
      <span class="nav-label">Loyalty Rewards</span>
    </a>

    <div class="nav-divider"></div>

    <div class="nav-section-label">System</div>

    <a href="settings.php" class="nav-item<?= nav_active('settings') ?>">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3" />
          <path
            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
      </div>
      <span class="nav-label">Settings</span>
    </a>

    <a href="../../process/logout.php" class="nav-item logout">
      <div class="nav-icon">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
      </div>
      <span class="nav-label">Logout</span>
    </a>

  </div>
  <div class="sidebar-bottom">
    <div class="sidebar-user">
      <div class="sidebar-avatar<?= $_sb_photo_url ? '' : ' sidebar-avatar-initials' ?>" id="sidebarAvatar">
        <?php if ($_sb_photo_url): ?>
          <img src="<?= htmlspecialchars($_sb_photo_url) ?>" alt="Profile"
            style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
            onerror="this.style.display='none';this.parentElement.classList.add('sidebar-avatar-initials');this.nextElementSibling.style.display='inline';">
        <?php endif; ?>
        <span<?= $_sb_photo_url ? ' style="display:none;"' : '' ?>><?= htmlspecialchars($_sb_initials) ?></span>
      </div>
      <div>
        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
        <div class="sidebar-user-role">Property Manager</div>
      </div>
    </div>
  </div>

</nav>

<?php include 'messages_widget.php'; ?>