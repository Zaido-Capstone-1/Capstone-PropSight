<?php
/**
 * Mobile bottom navigation bar
 * Requires $active_nav to be set before including.
 * Pages: dashboard, bookings, support, profile
 */
$_bn_active = $active_nav ?? '';

// Active/upcoming bookings count (pending, confirmed, active) for the
// Bookings tab badge. Reuse $nav_items['bookings']['badge'] if the
// including page already computed it (units.php, unit_detail.php,
// user-dashboard.php, and pages using _layout.php all do); otherwise
// fall back to running the same query directly.
$_bn_bookings_badge = $nav_items['bookings']['badge'] ?? null;
if ($_bn_bookings_badge === null && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    if (!empty($conn)) {
        $_bn_uid = (int) $_SESSION['user_id'];
        $_bn_r = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$_bn_uid AND status IN('pending','confirmed','active')"
        ));
        $_bn_bookings_badge = (!empty($_bn_r['c']) && $_bn_r['c'] > 0) ? (string) $_bn_r['c'] : null;
    }
}
?>
<div class="user-bottom-nav" id="userBottomNav" role="navigation" aria-label="Main navigation">
    <a href="user-dashboard.php" class="ubn-item<?php echo $_bn_active === 'dashboard' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
            stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;">
            <path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z" />
            <path d="M9 21V12h6v9" />
        </svg>
        <span>Home</span>
    </a>
    <a href="bookings.php" class="ubn-item<?php echo $_bn_active === 'bookings' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
            stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <span>Bookings</span>
        <span class="ubn-badge" data-rt="bookings-count"
            style="<?php echo $_bn_bookings_badge ? '' : 'display:none;'; ?>"><?php echo htmlspecialchars($_bn_bookings_badge ?? ''); ?></span>
    </a>
    <a href="units.php" class="ubn-item<?php echo $_bn_active === 'browse' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
            stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;">
            <circle cx="11" cy="11" r="7" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Browse</span>
    </a>
    <a href="payment.php" class="ubn-item<?php echo $_bn_active === 'payment' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
            stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;">
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <path d="M2 10h20" />
            <path d="M6 15h4" />
            <path d="M14 15h4" />
        </svg>
        <span>Payments</span>
    </a>
    <a href="profile.php" class="ubn-item<?php echo $_bn_active === 'profile' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
            stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
        <span>Profile</span>
    </a>
</div>