<?php
/**
 * Mobile bottom navigation bar
 * Requires $active_nav to be set before including.
 * Pages: dashboard, bookings, support, profile
 */
$_bn_active = $active_nav ?? '';
?>
<div class="user-bottom-nav" id="userBottomNav" role="navigation" aria-label="Main navigation">
    <a href="user-dashboard.php" class="ubn-item<?php echo $_bn_active === 'dashboard' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Home</span>
    </a>
    <a href="bookings.php" class="ubn-item<?php echo $_bn_active === 'bookings' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Bookings</span>
        <span class="ubn-badge" data-rt="bookings-count" style="display:none;"></span>
    </a>
    <a href="units.php" class="ubn-item ubn-center">
        <div class="ubn-fab">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
        <span>Browse</span>
    </a>
    <a href="profile.php" class="ubn-item<?php echo $_bn_active === 'profile' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Profile</span>
    </a>
    <a href="support.php" class="ubn-item<?php echo $_bn_active === 'support' ? ' ubn-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="width:20px;height:20px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Support</span>
    </a>
</div>