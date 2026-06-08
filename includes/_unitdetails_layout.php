<?php
/**
 * User profile sidebar + mobile nav partial
 * Requires: $top_nav_items, $sidebarPhoto, $initials, $full_name, $email,
 *           $isVerifiedSidebar, $nav_items, $active_nav
 */
?>
<div class="mobile-nav" id="mobileNav">
    <?php foreach ($top_nav_items as $top_nav): ?>
        <a href="<?php echo htmlspecialchars($top_nav['href']); ?>">
            <?php echo htmlspecialchars($top_nav['label']); ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="profile-sidebar" id="profileSidebar">
    <div class="sidebar-hdr">
        <button class="sidebar-close" id="sidebarClose">✕</button>
        <div class="sb-avatar">
            <?php if ($sidebarPhoto): ?>
                <img src="<?php echo htmlspecialchars($sidebarPhoto); ?>" alt="Profile photo"
                    onerror="this.style.display='none';this.parentElement.classList.add('sb-avatar-fallback');">
            <?php else: ?>
                <?php echo $initials; ?>
            <?php endif; ?>
        </div>
        <div class="sb-name">
            <?php echo $full_name; ?>
        </div>
        <div class="sb-email">
            <?php echo $email; ?>
        </div>
        <div class="sb-badge <?php echo $isVerifiedSidebar ? 'sb-badge-verified' : 'sb-badge-unverified'; ?>">
            <span class="badge-dot"></span>
            <span class="sb-badge-label">
                <?php echo $isVerifiedSidebar ? 'Verified' : 'Not Verified'; ?>
            </span>
            <?php if (!$isVerifiedSidebar): ?>
                <a href="profile.php" class="sb-verify-link">Verify now</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="sidebar-body">
        <div class="sb-section-label">Account</div>
        <?php foreach ($nav_items as $key => $item):
            $isActive = ($active_nav === $key); ?>
            <a href="<?php echo $item['href']; ?>" class="sb-item<?php echo $isActive ? ' active-item' : ''; ?>">
                <div class="sb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <?php echo $item['icon']; ?>
                    </svg></div>
                <div class="sb-text">
                    <div class="sb-title">
                        <?php echo $item['label']; ?>
                    </div>
                    <div class="sb-sub">
                        <?php echo $item['sub']; ?>
                    </div>
                </div>
                <div class="sb-right">
                    <?php if ($item['badge']): ?>
                        <span class="sb-badge-pill"><?php echo $item['badge']; ?></span>
                    <?php elseif ($key === 'messages'): ?>
                        <span class="sb-badge-pill nav-badge" data-rt="messages"
                            style="display:none;background:#ef4444;"></span>
                        <span class="sb-chevron" data-msg-chevron>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </span>
                    <?php else: ?>
                        <span class="sb-chevron">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ($key === 'loyalty'): ?>
                <div class="sb-divider"></div>
                <div class="sb-section-label">Preferences</div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-foot">
        <a href="../../process/logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Sign Out
        </a>
    </div>
</aside>