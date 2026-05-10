<?php
require_once __DIR__ . '/../includes/db.php';
$top_nav_items = require __DIR__ . '/user_top_nav.php';
$account_nav_keys = ['dashboard', 'profile', 'saved', 'loyalty', 'settings', 'payment'];
$isVerifiedSidebar = (($_SESSION['verification_status'] ?? '') === 'Verified');
$sidebarPhotoRaw = trim((string) ($_SESSION['profile_photo'] ?? ''));
if ($sidebarPhotoRaw === '' && isset($_SESSION['user_id']) && $conn) {
    $uid = (int) $_SESSION['user_id'];
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$uid LIMIT 1"));
    $sidebarPhotoRaw = trim((string) ($r['profile_photo'] ?? ''));
    if ($sidebarPhotoRaw !== '') {
        $_SESSION['profile_photo'] = $sidebarPhotoRaw;
    }
}
$sidebarPhoto = $sidebarPhotoRaw !== '' ? '../../' . ltrim($sidebarPhotoRaw, '/') : '';
$nav_items = [
    'profile' => ['label' => 'View Profile', 'sub' => 'Personal details & preferences', 'href' => 'profile.php', 'badge' => null, 'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'bookings' => [
        'label' => 'My Bookings',
        'sub' => 'View and manage reservations',
        'href' => 'bookings.php',
        'badge' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return null;
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$uid AND status IN('pending','confirmed','active')"));
            return $r['c'] > 0 ? (string) $r['c'] : null;
        })(),
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
    ],
    'payment' => [
        'label' => 'Payment History',
        'sub' => 'View transactions & refunds',
        'href' => 'payment.php',
        'badge' => null,
        'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'
    ],
    'saved' => [
        'label' => 'Saved Rooms',
        'sub' => 'Rooms on your wishlist',
        'href' => 'saved.php',
        'badge' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return null;
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM saved_units WHERE user_id=$uid"));
            return $r['c'] > 0 ? (string) $r['c'] : null;
        })(),
        'icon' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'
    ],
    'loyalty' => [
        'label' => 'Loyalty Points',
        'sub' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return 'Earn points every stay';
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$uid"));
            $pts = max(0, (int) $r['v']);
            $tier = 'Silver';
            if ($pts >= 5000)
                $tier = 'Diamond';
            elseif ($pts >= 2000)
                $tier = 'Platinum';
            elseif ($pts >= 500)
                $tier = 'Gold';
            return number_format($pts) . ' pts · ' . $tier . ' tier';
        })(),
        'href' => 'loyalty.php',
        'badge' => null,
        'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'
    ],
    'settings' => ['label' => 'Settings', 'sub' => 'Notifications, privacy, security', 'href' => 'settings.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    'messages' => ['label' => 'Messages', 'sub' => 'Chat with the property team', 'href' => 'messages.php', 'badge' => null, 'icon' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
    'support' => ['label' => 'Support & Help', 'sub' => 'FAQs and contact staff', 'href' => 'support.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <title>Boracay Accommodation — <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet">
</head>

<body>
    <script>
        window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        window.psGetCsrfToken = function () {
            if (window.PS_CSRF_TOKEN) return String(window.PS_CSRF_TOKEN);
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? String(meta.getAttribute('content') || '') : '';
        };
        window.psAppendCsrf = function (target) {
            const token = window.psGetCsrfToken();
            if (!token || !target || typeof target.append !== 'function') return target;
            target.append('csrf_token', token);
            return target;
        };
    </script>
    <header id="hdr">
        <a href="user-dashboard.php" class="logo">
            <img src="../../assets/images/logo.png" alt="Boracay Accommodation Logo" class="logo-icon">
            <span>
                <span
                    style="display:block;font-family:'Playfair Display',serif;font-weight:700;line-height:1.1;">Boracay
                    <span class="brand-break">Accommodation</span></span>
            </span>
        </a>
        <nav>
            <?php foreach ($top_nav_items as $top_nav): ?>
                <?php
                $isTopActive = ($active_nav === $top_nav['key']) ||
                    ($top_nav['key'] === 'dashboard' && in_array($active_nav, $account_nav_keys, true));
                ?>
                <a href="<?php echo $top_nav['href']; ?>" class="<?php echo $isTopActive ? 'active' : ''; ?>">
                    <?php echo $top_nav['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <a href="user-dashboard.php#browse" class="btn-browse" style="text-decoration: none;">Browse Rooms</a>
            <!-- ── Live notification bell ── -->
            <div style="position:relative;display:inline-flex;align-items:center;">
                <button id="notifBellBtn" aria-label="Notifications" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;
                           color:var(--text-soft);display:flex;align-items:center;justify-content:center;
                           transition:background 0.2s;" onmouseenter="this.style.background='var(--blue-50,#eff6ff)'"
                    onmouseleave="this.style.background='none'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:20px;height:20px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    <span data-rt="notif-count" style="display:none;position:absolute;top:2px;right:2px;
                           font-size:0.62rem;background:#ef4444;color:#fff;border-radius:99px;
                           min-width:15px;height:15px;padding:0 3px;
                           align-items:center;justify-content:center;font-weight:700;pointer-events:none;">
                        0
                    </span>
                </button>
            </div>
            <div class="btn-profile-wrap">
                <button class="btn-profile" id="profileBtn" aria-label="My Profile">
                    <?php if ($sidebarPhoto): ?>
                        <img src="<?php echo htmlspecialchars($sidebarPhoto); ?>" alt="Profile photo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                    <?php endif; ?>
                    <span class="profile-initials" <?php echo $sidebarPhoto ? 'style="display:none;"' : ''; ?>>
                        <?php echo $initials; ?>
                    </span>
                </button>
                <span class="profile-dot"></span>
            </div>
            <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        </div>
    </header>

    <div class="mobile-nav" id="mobileNav">
        <?php foreach ($top_nav_items as $top_nav): ?>
            <a href="<?php echo $top_nav['href']; ?>"><?php echo $top_nav['label']; ?></a>
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
            <div class="sb-name"><?php echo $full_name; ?></div>
            <div class="sb-email"><?php echo $email; ?></div>
            <div class="sb-badge <?php echo $isVerifiedSidebar ? 'sb-badge-verified' : 'sb-badge-unverified'; ?>">
                <span class="badge-dot"></span>
                <span class="sb-badge-label"><?php echo $isVerifiedSidebar ? 'Verified' : 'Not Verified'; ?></span>
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
                            stroke-linecap="round" stroke-linejoin="round"><?php echo $item['icon']; ?></svg></div>
                    <div class="sb-text">
                        <div class="sb-title"><?php echo $item['label']; ?></div>
                        <div class="sb-sub"><?php echo $item['sub']; ?></div>
                    </div>
                    <div class="sb-right">
                        <?php if ($key === 'bookings'): ?>
                            <!-- Real-time badge for bookings -->
                            <span class="sb-badge-pill nav-badge" data-rt="bookings"
                                style="<?php echo $item['badge'] ? '' : 'display:none;'; ?>background:#ef4444;">
                                <?php echo $item['badge'] ?? '0'; ?>
                            </span>
                            <span class="sb-chevron" <?php echo $item['badge'] ? 'style="display:none;"' : ''; ?>
                                data-bookings-chevron>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </span>
                        <?php elseif ($key === 'messages'): ?>
                            <!-- Real-time badge for messages -->
                            <span class="sb-badge-pill nav-badge" data-rt="messages"
                                style="display:none;background:#ef4444;"></span>
                            <span class="sb-chevron" data-msg-chevron>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </span>
                        <?php elseif ($item['badge']): ?>
                            <!-- Static badge for other items -->
                            <span class="sb-badge-pill"><?php echo $item['badge']; ?></span>
                        <?php else: ?>
                            <!-- Default chevron -->
                            <span class="sb-chevron">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
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

    <div id="toast"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12" />
        </svg>
        <span id="toastMsg"></span>
    </div>

    <div class="page-shell">
        <?php
        echo '<div class="page-hero"><div class="page-hero-inner reveal">';
        echo '<div>';
        echo '<div class="breadcrumb"><a href="user-dashboard.php">My Account</a><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>' . htmlspecialchars($page_title) . '</span></div>';
        echo '<h1 class="page-hero-title">' . ($page_hero_html ?? htmlspecialchars($page_title)) . '</h1>';
        echo '<p class="page-hero-sub">' . ($page_hero_sub ?? '') . '</p>';
        echo '</div>';
        echo '<div class="page-hero-icon"><svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="currentColor" stroke-width="1.8">' . ($page_hero_icon ?? '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>') . '</svg></div>';
        echo '</div></div>';
        ?>
        <?php if (!$isVerifiedSidebar): ?>
            <div class="verify-lock-banner">
                <div class="verify-lock-text">
                    Your account is not fully verified yet. Booking and support actions are disabled until you verify your
                    Gmail.
                </div>
                <a href="profile.php" class="btn-secondary">Verify now</a>
            </div>
        <?php endif; ?>
        <div class="page-content">