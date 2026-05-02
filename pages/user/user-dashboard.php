<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><body><script>setTimeout(() => history.back(), 2000);</script></body></html>';
    exit;
}

include '../../includes/fetch_units.php';
include '../../includes/fetch_bookings.php';

require_once '../../includes/db.php';
$_uid = (int) $_SESSION['user_id'];
$top_nav_items = require '../../includes/user_top_nav.php';

// Loyalty points
$_lRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$_uid"));
$loyaltyPoints = max(0, (int) ($_lRow['v'] ?? 0));
$loyaltyTier = 'Silver';
if ($loyaltyPoints >= 5000)
    $loyaltyTier = 'Diamond';
elseif ($loyaltyPoints >= 2000)
    $loyaltyTier = 'Platinum';
elseif ($loyaltyPoints >= 500)
    $loyaltyTier = 'Gold';

$_pmRow = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT payment_method, COUNT(*) AS cnt
     FROM bookings
     WHERE payment_method IS NOT NULL
       AND payment_method != ''
       AND status != 'cancelled'
     GROUP BY payment_method
     ORDER BY cnt DESC
     LIMIT 1"
));
$popularPaymentMethod = $_pmRow['payment_method'] ?? 'GCash';

$bookingCount = $bookingCount ?? 0;
$activeBooking = $activeBooking ?? null;
$bookingHistory = $bookingHistory ?? [];

$units = $units ?? [];
$amenitiesMap = $amenitiesMap ?? [];
$imagesMap = $imagesMap ?? [];

// Saved unit IDs for this user
$_savedRes = mysqli_query($conn, "SELECT unit_id FROM saved_units WHERE user_id=$_uid");
$savedUnitIds = [];
while ($_sr = mysqli_fetch_assoc($_savedRes)) {
    $savedUnitIds[] = (int) $_sr['unit_id'];
}

function statusBadgeClass($status)
{
    return match (strtolower($status)) {
        'completed' => 'st-completed',
        'cancelled' => 'st-cancelled',
        'active', 'confirmed' => 'st-active',
        default => 'st-pending',
    };
}
function statusLabel($status)
{
    return match (strtolower($status)) {
        'completed' => 'Completed', 'cancelled' => 'Cancelled',
        'active' => 'Active', 'confirmed' => 'Confirmed',
        default => ucfirst($status),
    };
}
function nightsBetween($in, $out)
{
    return (new DateTime($in))->diff(new DateTime($out))->days;
}
function formatDate($d)
{
    return date('M j, Y', strtotime($d));
}
function unitTypeToCategory($type)
{
    $type = strtolower($type ?? '');
    $cats = [];
    if (str_contains($type, 'sea') || str_contains($type, 'ocean'))
        $cats[] = 'sea';
    if (str_contains($type, 'family') || str_contains($type, 'loft'))
        $cats[] = 'family';
    if (str_contains($type, 'premium') || str_contains($type, 'suite'))
        $cats[] = 'premium';
    $cats[] = 'available';
    return implode(' ', $cats);
}

// Layout vars — dashboard uses its own full-page hero, not the standard page-hero
$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$isVerifiedSidebar = (($_SESSION['verification_status'] ?? '') === 'Verified');
$dashboardPhotoRaw = trim((string) ($_SESSION['profile_photo'] ?? ''));
if ($dashboardPhotoRaw === '') {
    $_photoRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$_uid LIMIT 1"));
    $dashboardPhotoRaw = trim((string) ($_photoRow['profile_photo'] ?? ''));
    if ($dashboardPhotoRaw !== '') {
        $_SESSION['profile_photo'] = $dashboardPhotoRaw;
    }
}
$dashboardPhoto = $dashboardPhotoRaw !== '' ? '../../' . ltrim($dashboardPhotoRaw, '/') : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boracay Accommodation — My Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link rel="stylesheet" href="../../assets/css/user-css/styles.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/user-css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/user-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/user-dashboard-inline.css">
</head>

<body>

    <?php
    // ── Reuse the shared header + sidebar from _layout.php nav structure ──
    $nav_items = [
        'profile' => ['label' => 'View Profile', 'sub' => 'Personal details & preferences', 'href' => 'profile.php', 'badge' => null, 'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
        'bookings' => [
            'label' => 'My Bookings',
            'sub' => 'View and manage reservations',
            'href' => 'bookings.php',
            'badge' => (function () use ($conn, $_uid) {
                if (!$conn)
                    return null;
                $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$_uid AND status IN('pending','confirmed','active')"));
                return $r['c'] > 0 ? (string) $r['c'] : null; })(),
            'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
        ],
        'saved' => ['label' => 'Saved Rooms', 'sub' => 'Rooms on your wishlist', 'href' => 'saved.php', 'badge' => count($savedUnitIds) > 0 ? (string) count($savedUnitIds) : null, 'icon' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'],
        'loyalty' => ['label' => 'Loyalty Points', 'sub' => $loyaltyPoints . ' pts · ' . $loyaltyTier . ' tier', 'href' => 'loyalty.php', 'badge' => null, 'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'],
        'settings' => ['label' => 'Settings', 'sub' => 'Notifications, privacy, security', 'href' => 'settings.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
        'messages' => ['label' => 'Messages', 'sub' => 'Chat with the property team', 'href' => 'messages.php', 'badge' => null, 'icon' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
        'support' => ['label' => 'Support & Help', 'sub' => 'FAQs and contact staff', 'href' => 'support.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    ];
    $active_nav = 'dashboard';
    ?>

    <!-- ── HEADER ── -->
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
            <?php
            $dashboard_section_map = [
                'dashboard' => '#account',
                'bookings' => '#bookings',
                'support' => '#support',
            ];
            foreach ($top_nav_items as $top_nav):
                $topHref = $dashboard_section_map[$top_nav['key']] ?? $top_nav['href'];
                ?>
                <a href="<?php echo $topHref; ?>" class="<?php echo ($active_nav === $top_nav['key']) ? 'active' : ''; ?>">
                    <?php echo $top_nav['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <button class="btn-browse"
                onclick="document.querySelector('#browse').scrollIntoView({behavior:'smooth'})">Browse Rooms</button>
            <div style="position:relative;display:inline-flex;align-items:center;">
                <button id="notifBellBtn" aria-label="Notifications"
                    style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;color:var(--ink-soft);display:flex;align-items:center;justify-content:center;transition:background .2s;"
                    onmouseenter="this.style.background='var(--navy-50)'" onmouseleave="this.style.background='none'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:20px;height:20px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    <span data-rt="notif-count"
                        style="display:none;position:absolute;top:2px;right:2px;font-size:.62rem;background:#ef4444;color:#fff;border-radius:99px;min-width:15px;height:15px;padding:0 3px;align-items:center;justify-content:center;font-weight:700;pointer-events:none;">0</span>
                </button>
            </div>
            <div class="btn-profile-wrap">
                <button class="btn-profile" id="profileBtn" aria-label="My Profile">
                    <?php if ($dashboardPhoto): ?>
                        <img src="<?php echo htmlspecialchars($dashboardPhoto); ?>" alt="Profile photo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                    <?php endif; ?>
                    <span class="profile-initials" <?php echo $dashboardPhoto ? 'style="display:none;"' : ''; ?>>
                        <?php echo $initials; ?>
                    </span>
                </button>
                <span class="profile-dot"></span>
            </div>
            <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
        </div>
    </header>

    <div class="mobile-nav" id="mobileNav">
        <?php foreach ($top_nav_items as $top_nav): ?>
            <a href="<?php echo $top_nav['href']; ?>" onclick="closeMob()"><?php echo $top_nav['label']; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- ── SIDEBAR ── -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    <aside class="profile-sidebar" id="profileSidebar">
        <div class="sidebar-hdr">
            <button class="sidebar-close" id="sidebarClose">✕</button>
            <div class="sb-avatar">
                <?php if ($dashboardPhoto): ?>
                    <img src="<?php echo htmlspecialchars($dashboardPhoto); ?>" alt="Profile photo"
                        onerror="this.style.display='none';this.parentElement.classList.add('sb-avatar-fallback');">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="sb-name"><?php echo $full_name; ?></div>
            <div class="sb-email"><?php echo $email; ?></div>
            <div class="sb-badge <?php echo $isVerifiedSidebar ? 'sb-badge-verified' : 'sb-badge-unverified'; ?>">
                <span class="badge-dot"></span>
                <span
                    class="sb-badge-label"><?php echo $isVerifiedSidebar ? 'Email Verified' : 'Email Not Verified'; ?></span>
                <?php if (!$isVerifiedSidebar): ?>
                    <a href="profile.php" class="sb-verify-link">Verify Email</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar-body">
            <div class="sb-section-label">Account</div>
            <?php foreach ($nav_items as $key => $item): ?>
                <a href="<?php echo $item['href']; ?>"
                    class="sb-item<?php echo $key === 'dashboard' ? ' active-item' : ''; ?>">
                    <div class="sb-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round"><?php echo $item['icon']; ?></svg></div>
                    <div class="sb-text">
                        <div class="sb-title"><?php echo $item['label']; ?></div>
                        <div class="sb-sub"><?php echo $item['sub']; ?></div>
                    </div>
                    <div class="sb-right">
                        <?php if ($item['badge']): ?>
                            <span class="sb-badge-pill"><?php echo $item['badge']; ?></span>
                        <?php elseif ($key === 'messages'): ?>
                            <span class="sb-badge-pill nav-badge" data-rt="messages"
                                style="display:none;background:#ef4444;"></span>
                            <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg></span>
                        <?php else: ?>
                            <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg></span>
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ── TOAST ── -->
    <div id="toast"
        style="position:fixed;bottom:32px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--navy-800);color:var(--white);padding:14px 28px;border-radius:40px;font-size:.88rem;font-weight:500;box-shadow:0 8px 32px rgba(10,22,40,.35);z-index:600;transition:transform .4s cubic-bezier(.4,0,.2,1),opacity .4s;opacity:0;white-space:nowrap;display:flex;align-items:center;gap:10px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            style="width:16px;height:16px;stroke:var(--sand);flex-shrink:0;">
            <polyline points="20 6 9 17 4 12" />
        </svg>
        <span id="toastMsg"></span>
    </div>

    <!-- ══════════════════════════════════════════════════════════
     HERO SECTION
═══════════════════════════════════════════════════════════ -->
    <section class="user-hero" id="account">
        <div class="user-hero-inner">
            <div class="reveal">
                <div class="user-hero-greeting">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:13px;height:13px;stroke:var(--sand-dk);">
                        <path
                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    <?php echo $greeting; ?>
                </div>
                <h1>Welcome back, <em><?php echo htmlspecialchars($_SESSION['first_name']); ?></em>!</h1>
                <p class="user-hero-sub">Ready to plan your next Boracay getaway? Browse our rooms below.</p>
            </div>
            <div class="user-stats-strip reveal rd1">
                <div class="ustat">
                    <div class="ustat-num" data-rt-user="booking_total"><?php echo $bookingCount; ?></div>
                    <div class="ustat-lbl">Bookings</div>
                </div>
                <div class="ustat">
                    <div class="ustat-num" data-rt-user="loyalty_points"><?php echo number_format($loyaltyPoints); ?>
                    </div>
                    <div class="ustat-lbl">Points</div>
                </div>
                <div class="ustat">
                    <div class="ustat-num" data-rt-user="loyalty_tier"><?php echo $loyaltyTier; ?></div>
                    <div class="ustat-lbl">Tier</div>
                </div>
                <div class="ustat">
                    <div class="ustat-num" data-rt-user="saved_count"><?php echo count($savedUnitIds); ?></div>
                    <div class="ustat-lbl">Saved</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ QUICK ACTIONS ══ -->
    <section class="quick-actions">
        <div class="qa-grid">
            <div class="qa-card reveal" onclick="document.querySelector('#browse').scrollIntoView({behavior:'smooth'})">
                <div class="qa-icon gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg></div>
                <div>
                    <div class="qa-title">Browse Rooms</div>
                    <div class="qa-sub"><?php echo count($units); ?> available now</div>
                </div>
            </div>
            <div class="qa-card reveal rd1"
                onclick="document.querySelector('#bookings').scrollIntoView({behavior:'smooth'})">
                <div class="qa-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg></div>
                <div>
                    <div class="qa-title">My Bookings</div>
                    <div class="qa-sub"><?php echo $activeBooking ? '1 active stay' : 'No active stay'; ?></div>
                </div>
            </div>
            <div class="qa-card reveal rd2" onclick="window.location.href='loyalty.php'">
                <div class="qa-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="8" r="6" />
                        <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                    </svg></div>
                <div>
                    <div class="qa-title">My Rewards</div>
                    <div class="qa-sub" data-rt-user="loyalty_points_text"><?php echo number_format($loyaltyPoints); ?>
                        points</div>
                </div>
            </div>
            <div class="qa-card reveal rd3" onclick="window.location.href='saved.php'">
                <div class="qa-icon rose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path
                            d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                    </svg></div>
                <div>
                    <div class="qa-title">Saved Rooms</div>
                    <div class="qa-sub" data-rt-user="saved_count_text"><?php echo count($savedUnitIds); ?> on wishlist
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ ACTIVE BOOKING BANNER ══ -->
    <?php if ($activeBooking): ?>
        <div class="booking-banner" id="rt-active-booking-wrap"
            data-booking-id="<?php echo (int) $activeBooking['booking_id']; ?>"
            data-unit-id="<?php echo (int) $activeBooking['unit_id']; ?>">
            <div class="booking-banner-inner reveal">
                <div class="bbl">
                    <div class="bb-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </div>
                    <div>
                        <div class="bb-label">Active Reservation</div>
                        <div class="bb-room">
                            <?php echo htmlspecialchars($activeBooking['unit_name'] ?? $activeBooking['unit_number']); ?> —
                            <?php echo htmlspecialchars($activeBooking['property_name']); ?>
                        </div>
                        <div class="bb-dates">Check-in: <?php echo formatDate($activeBooking['checkin_date']); ?><span
                                class="bb-date-sep"> &nbsp;·&nbsp; </span>Check-out:
                            <?php echo formatDate($activeBooking['checkout_date']); ?>
                        </div>
                    </div>
                </div>
                <div class="bb-right">
                    <div class="bb-status <?php echo statusBadgeClass($activeBooking['status']); ?>"
                        id="rt-active-booking-status"><?php echo statusLabel($activeBooking['status']); ?></div>
                    <?php
                    $activeImgSrc = !empty($activeBooking['image_path']) ? '../../' . ltrim($activeBooking['image_path'], '/') : '';
                    $manageData = json_encode([
                        'booking_id' => $activeBooking['booking_id'],
                        'unit_name' => $activeBooking['unit_name'] ?? $activeBooking['unit_number'] ?? 'Unit',
                        'property_name' => $activeBooking['property_name'] ?? '',
                        'address' => $activeBooking['address'] ?? '',
                        'latitude' => (float) ($activeBooking['latitude'] ?? 0),
                        'longitude' => (float) ($activeBooking['longitude'] ?? 0),
                        'checkin' => formatDate($activeBooking['checkin_date']),
                        'checkout' => formatDate($activeBooking['checkout_date']),
                        'nights' => nightsBetween($activeBooking['checkin_date'], $activeBooking['checkout_date']),
                        'status' => statusLabel($activeBooking['status']),
                        'total_amount' => 'PHP ' . number_format((float) ($activeBooking['total_amount'] ?? 0), 0),
                        'guests' => (int) ($activeBooking['guests'] ?? 2),
                        'image' => $activeImgSrc,
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    ?>
                    <button class="btn-manage"
                        onclick="openManageModal(<?php echo htmlspecialchars($manageData, ENT_QUOTES); ?>)">Manage
                        Stay</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ══ ROOMS SECTION ══ -->
    <?php if (!$activeBooking): ?>
        <div class="booking-banner" id="rt-active-booking-wrap" data-booking-id="" data-unit-id=""
            style="display:none;opacity:0;max-height:0;overflow:hidden;">
            <div class="booking-banner-inner reveal">
                <div class="bbl">
                    <div class="bb-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </div>
                    <div>
                        <div class="bb-label">Active Reservation</div>
                        <div class="bb-room">No active reservation</div>
                        <div class="bb-dates"></div>
                    </div>
                </div>
                <div class="bb-right">
                    <div class="bb-status st-pending" id="rt-active-booking-status">Pending</div>
                    <button class="btn-manage" onclick="return false;">Manage Stay</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <section class="rooms-section" id="browse">
        <div class="section-header-row">
            <div>
                <div class="eyebrow">Available Rooms</div>
                <h2 class="section-heading">Find Your <em>Perfect</em> Stay</h2>
            </div>
        </div>

        <div class="filter-bar reveal">
            <button class="filter-pill active" onclick="filterRooms('all',this)">All Rooms</button>
            <button class="filter-pill" onclick="filterRooms('available',this)">Available Now</button>
            <button class="filter-pill" onclick="filterRooms('sea',this)">Sea View</button>
            <button class="filter-pill" onclick="filterRooms('family',this)">Family</button>
            <button class="filter-pill gold-pill" onclick="filterRooms('premium',this)">✦ Premium</button>
            <div class="filter-spacer"></div>
            <div class="search-bar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" placeholder="Search rooms…" id="roomSearch" oninput="searchRooms(this.value)">
            </div>
        </div>

        <div class="carousel-container" id="roomsCarousel">
            <button class="carousel-btn carousel-btn-prev" id="roomsPrev" onclick="scrollCarousel('rooms',-1)"
                aria-label="Previous">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
            <div class="rooms-grid" id="roomsGrid">
                <?php if (empty($units)): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--ink-faint);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                            style="width:48px;height:48px;margin-bottom:12px;">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        <p>No units available at the moment. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($units as $i => $unit):
                        $isVacant = $unit['status'] === 'vacant';
                        $cats = unitTypeToCategory($unit['unit_type']);
                        $rawUnitNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));
                        if (!empty($unit['unit_name']))
                            $rawName = $unit['unit_name'];
                        elseif (!empty($unit['property_name']) && !empty($rawUnitNum))
                            $rawName = $unit['property_name'] . ' — Unit ' . $rawUnitNum;
                        elseif (!empty($unit['unit_number']))
                            $rawName = $unit['unit_number'];
                        elseif (!empty($unit['property_name']))
                            $rawName = $unit['property_name'];
                        else
                            $rawName = 'Unit #' . $unit['unit_id'];
                        $unitName = htmlspecialchars($rawName);
                        $propName = htmlspecialchars($unit['property_name'] ?? '');
                        $cityPart = !empty($unit['city']) ? ', ' . $unit['city'] : '';
                        $price = '₱' . number_format((float) $unit['rent_amount'], 0);
                        $amenities = $amenitiesMap[$unit['unit_id']] ?? [];
                        $imgSrc = $unit['image_path']
                            ? '../../' . ltrim($unit['image_path'], '/')
                            : '../../assets/images/placeholder.jpg';

                        $unitImages = $imagesMap[$unit['unit_id']] ?? [];
                        if (empty($unitImages) && $imgSrc)
                            $unitImages = [$imgSrc];
                        if (empty($unitImages) && $imgSrc)
                            $unitImages = [$imgSrc];
                        $delayClass = ['', 'rd1', 'rd2', 'rd3'][$i % 4];
                        $isSaved = in_array((int) $unit['unit_id'], $savedUnitIds);
                        $ratingValue = isset($unit['rating']) && $unit['rating'] !== null && $unit['rating'] !== ''
                            ? round((float) $unit['rating'], 1)
                            : null;
                        $roomJs = json_encode([
                            'id' => $unit['unit_id'],
                            'name' => $rawName,
                            'location' => ($unit['property_name'] ?? '') . $cityPart,
                            'address' => trim(($unit['address'] ?? '') . $cityPart),
                            'city' => $unit['city'] ?? '',
                            'price' => $price,
                            'priceNum' => (float) $unit['rent_amount'],
                            'rating' => $ratingValue,
                            'guests' => 2,
                            'view' => $unit['unit_type'] ?? 'Standard',
                            'desc' => $unit['description'] ?? 'A comfortable and well-appointed unit.',
                            'amenities' => array_values($amenities),
                            'image' => $imgSrc,
                            'images' => array_values($unitImages),
                            'grad' => 'g' . (($i % 6) + 1),
                            'latitude' => (float) ($unit['latitude'] ?? 0),
                            'longitude' => (float) ($unit['longitude'] ?? 0),
                        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <div class="room-card reveal <?php echo $delayClass; ?>"
                            data-unit-id="<?php echo (int) $unit['unit_id']; ?>"
                            data-cat="<?php echo htmlspecialchars($cats); ?>"
                            data-name="<?php echo strtolower($unitName . ' ' . $propName); ?>"
                            data-status="<?php echo htmlspecialchars($unit['status']); ?>"
                            data-room-payload="<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>"
                            data-rent="<?php echo (float) $unit['rent_amount']; ?>">

                            <div class="room-card-img">
                                <img src="<?php echo $imgSrc; ?>" alt="<?php echo $unitName; ?>" class="room-img-placeholder"
                                    onerror="this.src='../../assets/images/placeholder.jpg'">
                                <span class="room-badge-img <?php echo $isVacant ? 'badge-gold' : 'badge-blue'; ?>">
                                    <?php echo htmlspecialchars(strtoupper($unit['unit_type'] ?? 'UNIT')); ?>
                                </span>
                                <span class="room-avail <?php echo $isVacant ? 'avail-yes' : 'avail-no'; ?>" data-avail-status>
                                    <?php echo $isVacant ? 'AVAILABLE' : ($unit['status'] === 'maintenance' ? 'MAINTENANCE' : 'BOOKED'); ?>
                                </span>
                                <button class="btn-save-room<?php echo $isSaved ? ' saved' : ''; ?>"
                                    onclick="event.stopPropagation(); toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)"
                                    aria-label="<?php echo $isSaved ? 'Remove from saved' : 'Save room'; ?>">
                                    <svg viewBox="0 0 24 24">
                                        <path
                                            d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="room-card-body">
                                <div class="room-card-top">
                                    <div class="room-name"><?php echo $unitName; ?></div>
                                    <div class="room-rating<?php echo $ratingValue === null ? ' no-rating' : ''; ?>"
                                        data-rating-unit-id="<?php echo (int) $unit['unit_id']; ?>">
                                        <?php if ($ratingValue !== null): ?>
                                            <svg viewBox="0 0 24 24">
                                                <polygon
                                                    points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                            </svg>
                                            <span data-rating-value><?php echo number_format($ratingValue, 1); ?></span>
                                        <?php else: ?>
                                            <span data-rating-value>No ratings yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="room-meta">
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                        </svg><?php echo htmlspecialchars(ucfirst($unit['unit_type'] ?? 'Standard')); ?></span>
                                    <?php if ($unit['floor']): ?>
                                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <rect x="2" y="7" width="20" height="14" rx="2" />
                                                <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                                            </svg>Floor <?php echo (int) $unit['floor']; ?></span>
                                    <?php endif; ?>
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                        </svg>2 Guests</span>
                                </div>

                                <?php if (!empty($amenities)): ?>
                                    <div class="room-features">
                                        <?php foreach (array_slice($amenities, 0, 4) as $am):
                                            $chipLabel = is_array($am) ? ($am['name'] ?? '') : $am; ?>
                                            <span class="feature-chip"><?php echo htmlspecialchars($chipLabel); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="room-divider"></div>

                                <div class="room-price-row">
                                    <div class="room-price"><?php echo $price; ?> <sub>/ night</sub></div>
                                    <div style="display:flex;gap:8px;align-items:center;" data-action-buttons>
                                        <button class="btn-view-details" onclick='openRoomModal(<?php echo $roomJs; ?>)'>View
                                            Details</button>
                                        <?php if ($isVacant): ?>
                                            <button class="btn-rent" data-book-btn
                                                onclick='openBookingModal(<?php echo $roomJs; ?>)'>Book
                                                Now</button>
                                        <?php else: ?>
                                            <button class="btn-rent" data-book-btn disabled>Unavailable</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="carousel-btn carousel-btn-next" id="roomsNext" onclick="scrollCarousel('rooms',1)"
                aria-label="Next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </button>
        </div>
        <div class="carousel-dots" id="roomsDots"></div>
    </section>

    <!-- ══ BOOKING HISTORY ══ -->
    <section class="history-section" id="bookings">
        <div class="history-inner">
            <div class="section-header-row" style="margin-bottom:24px;">
                <div>
                    <div class="eyebrow">Past Stays</div>
                    <h2 class="section-heading">Booking <em>History</em></h2>
                </div>
            </div>

            <div class="carousel-container" id="historyCarousel">
                <button class="carousel-btn carousel-btn-prev" id="historyPrev" onclick="scrollCarousel('history',-1)"
                    aria-label="Previous">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div class="history-list" id="historyList">
                    <?php if (empty($bookingHistory)): ?>
                        <div style="text-align:center;padding:48px 20px;color:var(--ink-faint);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                style="width:40px;height:40px;margin-bottom:12px;">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            <p>No booking history yet. Book your first stay above!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookingHistory as $bi => $bk):
                            $nights = nightsBetween($bk['checkin_date'], $bk['checkout_date']);
                            $bkImgSrc = $bk['image_path'] ? '../../' . ltrim($bk['image_path'], '/') : '../../assets/images/placeholder.jpg';
                            $rawBkNum = trim(preg_replace('/^unit\s*/i', '', $bk['unit_number'] ?? ''));
                            if (!empty($bk['unit_name']))
                                $bkUnitName = $bk['unit_name'];
                            elseif (!empty($bk['property_name']) && !empty($rawBkNum))
                                $bkUnitName = $bk['property_name'] . ' — Unit ' . $rawBkNum;
                            elseif (!empty($bk['unit_number']))
                                $bkUnitName = $bk['unit_number'];
                            elseif (!empty($bk['property_name']))
                                $bkUnitName = $bk['property_name'];
                            else
                                $bkUnitName = 'Booking #' . $bk['booking_id'];
                            $delayClass = ['', 'rd1', 'rd2', 'rd3'][$bi % 4];
                            ?>
                            <div class="history-item reveal <?php echo $delayClass; ?>"
                                data-booking-id="<?php echo $bk['booking_id']; ?>"
                                data-checkin="<?php echo $bk['checkin_date']; ?>"
                                data-checkout="<?php echo $bk['checkout_date']; ?>">
                                <div class="history-img">
                                    <img src="<?php echo $bkImgSrc; ?>" alt="<?php echo htmlspecialchars($bkUnitName); ?>"
                                        class="history-img-bg" onerror="this.src='../../assets/images/placeholder.jpg'">
                                </div>
                                <div class="history-info">
                                    <div class="history-room"><?php echo htmlspecialchars($bkUnitName); ?></div>
                                    <div class="history-dates">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        <span data-field="checkin"><?php echo formatDate($bk['checkin_date']); ?></span> –
                                        <span data-field="checkout"><?php echo formatDate($bk['checkout_date']); ?></span>
                                        &nbsp;·&nbsp; <span data-field="nights"><?php echo $nights; ?>
                                            night<?php echo $nights !== 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                                <div class="history-price-col">
                                    <div class="history-price" data-field="price">
                                        ₱<?php echo number_format((float) $bk['total_amount'], 0); ?>
                                    </div>
                                    <div class="history-total">Total paid</div>
                                </div>
                                <span class="history-status <?php echo statusBadgeClass($bk['status']); ?>" data-field="status"
                                    data-raw-status="<?php echo $bk['status']; ?>"><?php echo statusLabel($bk['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="carousel-btn carousel-btn-next" id="historyNext" onclick="scrollCarousel('history',1)"
                    aria-label="Next">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
            <div class="carousel-dots" id="historyDots"></div>
        </div>
    </section>

    <!-- ══ LOYALTY CTA BAND ══ -->
    <section class="loyalty-band">
        <div class="loyalty-band-orb loyalty-band-orb-lg"></div>
        <div class="loyalty-band-orb loyalty-band-orb-sm"></div>
        <div class="loyalty-band-inner reveal">
            <div>
                <div class="loyalty-eyebrow">🥇 Loyalty Program</div>
                <h2 class="loyalty-title">Earn points on every stay.<br><em>Redeem for free nights.</em></h2>
                <p class="loyalty-copy">Every booking earns you loyalty points. Reach Gold, Platinum or Diamond status
                    to unlock exclusive perks, room upgrades, and complimentary stays.</p>
                <div class="loyalty-actions">
                    <a href="loyalty.php" class="loyalty-btn loyalty-btn-primary"
                        onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <circle cx="12" cy="8" r="6" />
                            <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                        </svg>
                        View My Points
                    </a>
                    <a href="#browse" onclick="document.querySelector('#browse').scrollIntoView({behavior:'smooth'})"
                        class="loyalty-btn loyalty-btn-secondary"
                        onmouseenter="this.style.background='rgba(255,255,255,.22)'"
                        onmouseleave="this.style.background='rgba(255,255,255,.12)'">Browse Rooms →</a>
                </div>
            </div>
            <div class="loyalty-points-card">
                <div class="loyalty-points-emoji">🥇</div>
                <div class="loyalty-points-value"><?php echo number_format($loyaltyPoints); ?></div>
                <div class="loyalty-points-label">Points Balance</div>
                <div class="loyalty-points-tier"><?php echo $loyaltyTier; ?> Member</div>
            </div>
        </div>
    </section>

    <!-- ══ NEWSLETTER ══ -->
    <section id="support" class="newsletter-section">
        <div class="newsletter-inner reveal">
            <div>
                <div class="newsletter-eyebrow">📬 Stay in the loop</div>
                <h2 class="newsletter-title">Get exclusive deals<br>straight to your inbox</h2>
                <p class="newsletter-copy">Be the first to know about seasonal promotions, new property listings, and
                    member-only offers.</p>
            </div>
            <div>
                <div class="newsletter-form">
                    <input type="email" class="newsletter-input" placeholder="Enter your email address"
                        onfocus="this.style.borderColor='var(--navy-400)'"
                        onblur="this.style.borderColor='var(--border)'">
                    <button onclick="showToast('Thanks! You\'re subscribed. 🎉')" class="newsletter-btn"
                        onmouseenter="this.style.background='var(--navy-900)'"
                        onmouseleave="this.style.background='var(--navy-800)'">Subscribe</button>
                </div>
                <p class="newsletter-note">No spam, ever. Unsubscribe at any time.</p>
            </div>
        </div>
    </section>

    <!-- ══ ROOM BOOKING MODAL ══ -->
    <!-- ══ PROPERTY DETAIL MODAL ══ -->
    <div class="modal-overlay" id="roomModal">
        <div class="pd-modal">
            <button class="pd-close" id="roomModalClose" aria-label="Close">✕</button>

            <!-- Hero Gallery -->
            <div class="pd-hero" id="pdHero" style="position:relative;height:260px;overflow:hidden;background:#0b3d35;">
                <div id="pdGalleryTrack"
                    style="display:flex;height:100%;transition:transform .35s ease;will-change:transform;"></div>
                <div id="modalImgFallback" class="pd-hero-fallback" style="display:none;position:absolute;inset:0;">
                </div>
                <button id="pdGalleryPrev" onclick="pdGalleryNav(-1)" aria-label="Previous image"
                    style="display:none;position:absolute;left:12px;top:50%;transform:translateY(-50%);z-index:10;background:rgba(0,0,0,.45);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:18px;line-height:1;padding:0;">‹</button>
                <button id="pdGalleryNext" onclick="pdGalleryNav(1)" aria-label="Next image"
                    style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);z-index:10;background:rgba(0,0,0,.45);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:18px;line-height:1;padding:0;">›</button>
                <div id="pdGalleryDots"
                    style="position:absolute;bottom:52px;left:0;right:0;display:flex;justify-content:center;gap:5px;z-index:10;pointer-events:none;">
                </div>
                <div class="pd-hero-overlay">
                    <div class="pd-hero-badge" id="pdHeroBadge"></div>
                    <div class="pd-hero-title" id="modalRoomName"></div>
                    <div class="pd-hero-loc">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        <span id="modalRoomLoc"></span>
                    </div>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="pd-stats">
                <div class="pd-stat">
                    <div class="pd-stat-label">RENT</div>
                    <div class="pd-stat-value" id="modalRoomPrice"></div>
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-label">BEDS</div>
                    <div class="pd-stat-value" id="pdBeds">2</div>
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-label">BATHS</div>
                    <div class="pd-stat-value" id="pdBaths">1</div>
                </div>
                <div class="pd-stat">
                    <div class="pd-stat-label">RATING</div>
                    <div class="pd-stat-value pd-stat-rating" id="modalRoomRating">—</div>
                </div>
            </div>

            <!-- Two-column body -->
            <div class="pd-body">
                <!-- Left column -->
                <div class="pd-left">
                    <!-- About -->
                    <div class="pd-section">
                        <div class="pd-section-title">About this property</div>
                        <p class="pd-desc" id="modalRoomDesc"></p>
                        <div class="pd-amenities" id="modalAmenities"></div>
                    </div>

                    <!-- Tenant reviews -->
                    <div class="pd-section">
                        <div class="pd-section-title">Tenant Reviews <span id="pdReviewCount"
                                style="font-size:.8rem;font-weight:500;color:#8aa4c0;"></span></div>
                        <div id="pdReviews" class="pd-reviews">
                            <div id="pdReviewsLoading"
                                style="padding:20px 0;text-align:center;color:#8aa4c0;font-size:.83rem;">Loading
                                reviews…</div>
                        </div>
                        <div id="pdReviewsPager"
                            style="display:none;display:flex;gap:8px;align-items:center;justify-content:center;margin-top:14px;">
                            <button id="pdReviewsPrev" onclick="pdReviewsNav(-1)"
                                style="padding:6px 14px;border-radius:99px;border:1.5px solid #e2e8f0;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;">←
                                Prev</button>
                            <span id="pdReviewsPageLabel" style="font-size:12px;color:#6b7280;"></span>
                            <button id="pdReviewsNext" onclick="pdReviewsNav(1)"
                                style="padding:6px 14px;border-radius:99px;border:1.5px solid #e2e8f0;background:#fff;font-size:12px;font-weight:600;color:#374151;cursor:pointer;">Next
                                →</button>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="pd-right">
                    <!-- Real Leaflet Map -->
                    <div class="pd-map" id="pdMap"
                        style="padding:0;overflow:hidden;border-radius:16px;min-height:220px;position:relative;">
                        <div id="pdLeafletMap" style="width:100%;height:220px;border-radius:16px;z-index:1;"></div>
                        <div id="pdMapLoadingOverlay"
                            style="position:absolute;inset:0;background:rgba(248,245,240,.85);display:flex;align-items:center;justify-content:center;border-radius:16px;z-index:10;font-size:.8rem;color:#7a6652;gap:8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                style="width:18px;height:18px;animation:spin 1s linear infinite">
                                <circle cx="12" cy="12" r="10" stroke-opacity=".25" />
                                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round" />
                            </svg>
                            Locating property…
                        </div>
                    </div>


                    <!-- Availability -->
                    <div class="pd-panel">
                        <div class="pd-panel-title">Availability</div>
                        <div class="pd-fields">
                            <div class="pd-field">
                                <label>Check-in date</label>
                                <input type="date" id="modalCheckin">
                            </div>
                            <div class="pd-field">
                                <label>Check-out date</label>
                                <input type="date" id="modalGuests">
                            </div>
                        </div>
                        <div class="pd-avail-note" id="pdAvailNote"></div>
                        <button class="pd-book-btn" id="bookConfirmBtn" onclick="confirmBooking()">Book this unit
                            →</button>
                        <!-- <div id="modalTotal" class="pd-total"></div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ MULTI-STEP BOOKING MODAL ══ -->
    <div class="bm-overlay" id="bmOverlay">
        <div class="bm-box" id="bmBox">
            <button class="bm-close" id="bmClose" onclick="closeBookingModal()">✕</button>

            <div class="bm-content">

                <!-- Step indicator -->
                <div class="bm-stepper-wrap">
                    <div class="bm-stepper">
                        <div class="bm-steps">
                            <div class="bm-step active" id="bm-step-1">
                                <div class="bm-step-circle" id="bm-circle-1">1</div>
                                <div class="bm-step-label">Details</div>
                            </div>
                            <div class="bm-step" id="bm-step-2">
                                <div class="bm-step-circle" id="bm-circle-2">2</div>
                                <div class="bm-step-label">Review</div>
                            </div>
                            <div class="bm-step" id="bm-step-3">
                                <div class="bm-step-circle" id="bm-circle-3">3</div>
                                <div class="bm-step-label">Payment</div>
                            </div>
                            <div class="bm-step" id="bm-step-4">
                                <div class="bm-step-circle" id="bm-circle-4">4</div>
                                <div class="bm-step-label">Confirm</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step panels -->
                <div class="bm-panels-wrap">

                    <!-- STEP 1: Tenant Details -->
                    <div class="bm-panel active" id="bm-panel-1">
                        <div class="bm-panel-title">Tenant information</div>
                        <div class="bm-panel-sub">We'll use these details for your reservation.</div>

                        <div class="bm-row">
                            <div class="bm-field">
                                <label>First name</label>
                                <input type="text" id="bm-fname" placeholder="Ana" autocomplete="given-name">
                            </div>
                            <div class="bm-field">
                                <label>Last name</label>
                                <input type="text" id="bm-lname" placeholder="Jimenez" autocomplete="family-name">
                            </div>
                        </div>
                        <div class="bm-row full">
                            <div class="bm-field">
                                <label>Email address</label>
                                <input type="email" id="bm-email" placeholder="ana@email.com" autocomplete="email">
                            </div>
                        </div>
                        <div class="bm-row full">
                            <div class="bm-field">
                                <label>Contact number</label>
                                <input type="tel" id="bm-phone" placeholder="+63 912 345 6789" autocomplete="tel">
                            </div>
                        </div>
                        <div class="bm-row">
                            <div class="bm-field">
                                <label>Check-in date</label>
                                <input type="date" id="bm-checkin">
                            </div>
                            <div class="bm-field">
                                <label>Check-out date</label>
                                <input type="date" id="bm-lease">
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: Review -->
                    <div class="bm-panel" id="bm-panel-2">
                        <div class="bm-panel-title">Review your booking</div>
                        <div class="bm-panel-sub">Check all details before proceeding to payment.</div>

                        <div class="bm-review-block">
                            <div class="bm-review-label">Tenant</div>
                            <div class="bm-review-row"><span class="bm-review-key">Name</span><span
                                    class="bm-review-val" id="rv-name">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Email</span><span
                                    class="bm-review-val" id="rv-email">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Contact</span><span
                                    class="bm-review-val" id="rv-phone">—</span></div>
                        </div>

                        <div class="bm-review-block">
                            <div class="bm-review-label">Reservation</div>
                            <div class="bm-review-row"><span class="bm-review-key">Unit</span><span
                                    class="bm-review-val" id="rv-unit">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Check-in</span><span
                                    class="bm-review-val" id="rv-movein">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Check-out</span><span
                                    class="bm-review-val" id="rv-checkout">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Nights</span><span
                                    class="bm-review-val" id="rv-nights">—</span></div>
                            <div class="bm-review-row"><span class="bm-review-key">Price per night</span><span
                                    class="bm-review-val" id="rv-rent">—</span></div>
                        </div>

                        <div class="bm-review-block">
                            <div class="bm-review-label">Charges due today</div>
                            <div class="bm-review-row"><span class="bm-review-key">Security deposit (50%)</span><span
                                    class="bm-review-val" id="rv-deposit">—</span></div>
                            <div class="bm-review-row" style="padding-top:10px;">
                                <span class="bm-review-key" style="font-weight:700;color:var(--text-dark);">Total due
                                    now</span>
                                <span class="bm-review-val" id="rv-total"
                                    style="color:var(--teal);font-size:1.05rem;">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Payment -->
                    <div class="bm-panel" id="bm-panel-3">
                        <div class="bm-panel-title">Payment method</div>
                        <div class="bm-panel-sub">Choose how you'd like to pay the amount due today.</div>

                        <div class="bm-pay-methods" id="bmPayMethods">
                            <div class="bm-pay-option selected" data-method="GCash">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/gcash.png"></div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">GCash</div>
                                    <div class="bm-pay-desc">Pay via GCash online transfer</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                                <span class="bm-pay-badge">Popular</span>
                            </div>
                            <div class="bm-pay-option" data-method="Maya">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/maya1.png"></div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Maya</div>
                                    <div class="bm-pay-desc">Pay via Maya online transfer</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Bank">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/mobile-banking.png">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Bank Transfer</div>
                                    <div class="bm-pay-desc">Transfer via online banking</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Cash">
                                <div class="bm-pay-icon" style="height:45px; width: 45px;">💵</div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Cash (On-site)</div>
                                    <div class="bm-pay-desc">Pay at the front desk upon check-in</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                        </div>

                        <div id="bmQrBox" class="bm-qr-wrap"
                            style="background:#f8fafc;border-radius:10px;padding:16px 18px;border:1px solid #e2e8f0;">
                            <div class="bm-qr-meta" style="width:100%;">
                                <div class="bm-qr-title" id="bmQrTitle"
                                    style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:6px;">Pay via
                                    GCash</div>
                                <div class="bm-qr-sub" id="bmQrSub"
                                    style="font-size:13px;color:#64748b;line-height:1.6;">Send payment to <strong>+63
                                        912 345 6789</strong> (Juan dela Cruz) and use your booking reference as the
                                    note. Upload your proof of payment via the Messages page after paying.</div>
                                <div class="bm-qr-amount" id="bmQrAmount"
                                    style="margin-top:10px;font-size:18px;font-weight:700;color:#1e293b;">₱0</div>
                                <div class="bm-timer">
                                    <div class="bm-timer-dot"></div>
                                    <span id="bmTimerText">Your booking is held for <strong
                                            id="bmCountdown">30:00</strong> — complete payment to confirm.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cash instruction -->
                        <div id="bmCashBox" class="bm-cash-box" style="display:none;">
                            <div class="bm-cash-icon">💵</div>
                            <div>
                                <div class="bm-cash-title">Pay in cash upon check-in</div>
                                <div class="bm-cash-sub">
                                    Please prepare <strong id="bmCashAmount" style="color:#92400e;"></strong> in cash on
                                    your check-in date.
                                    Our property manager will collect payment and issue an official receipt.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Confirmed -->
                    <div class="bm-panel" id="bm-panel-4">
                        <div class="bm-confirm-check">
                            <div class="bm-check-ring">
                                <svg viewBox="0 0 24 24">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            </div>
                            <div class="bm-confirm-title">Booking submitted!</div>
                            <div class="bm-confirm-sub">Your reservation request has been sent. We'll confirm within 24
                                hours.</div>
                            <div class="bm-confirm-ref" id="bmConfirmRef">Ref #BK-0000</div>

                            <div class="bm-confirm-details">
                                <div class="bm-confirm-row"><span>Unit</span><span id="cf-unit">—</span></div>
                                <div class="bm-confirm-row"><span>Check-in</span><span id="cf-movein">—</span></div>
                                <div class="bm-confirm-row"><span>Check-out</span><span id="cf-checkout">—</span></div>
                                <div class="bm-confirm-row"><span>Payment method</span><span id="cf-method">—</span>
                                </div>
                                <div class="bm-confirm-row"><span>Total paid / due</span><span id="cf-total"
                                        style="color:var(--teal);">—</span></div>
                            </div>
                        </div>
                    </div>

                </div><!-- /panels-wrap -->

                <!-- Sidebar summary -->
                <div class="bm-sidebar">
                    <div class="bm-unit-card">
                        <div class="bm-unit-img-fallback" id="bmUnitImgWrap">
                            <img id="bmUnitImg" class="bm-unit-img" src="" alt="" style="display:none;"
                                onerror="this.style.display='none'">
                        </div>
                        <div class="bm-unit-info">
                            <div class="bm-unit-name" id="bmSbName">—</div>
                            <div class="bm-unit-loc" id="bmSbLoc">—</div>
                        </div>
                    </div>

                    <div class="bm-summary-title">Booking summary</div>
                    <div class="bm-summary-rows">
                        <div class="bm-summary-row"><span class="bm-summary-key">Price per night</span><span
                                class="bm-summary-val" id="sb-rent">—</span></div>
                        <div class="bm-summary-row"><span class="bm-summary-key">Security deposit (50%)</span><span
                                class="bm-summary-val" id="sb-deposit">—</span></div>
                    </div>
                    <div class="bm-summary-divider"></div>
                    <div class="bm-total-row">
                        <span class="bm-total-label">Total due now</span>
                        <span class="bm-total-amount" id="sb-total">—</span>
                    </div>

                    <div class="bm-hold-notice">
                        Your booking is held for <strong>30 minutes</strong>.
                        Complete payment to confirm your reservation.
                    </div>
                </div>

                <!-- Footer nav -->
                <div class="bm-footer-wrap" id="bmFooter">
                    <button class="bm-btn bm-btn-back" id="bmBack" onclick="bmPrevStep()" style="display:none;">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Back
                    </button>
                    <div style="flex:1"></div>
                    <button class="bm-btn bm-btn-next" id="bmNext" onclick="bmNextStep()">
                        Continue
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                    <button class="bm-btn bm-btn-confirm" id="bmConfirmBtn" style="display:none;"
                        onclick="bmSubmitBooking()">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Confirm Payment
                    </button>
                    <button class="bm-btn bm-btn-next" id="bmDoneBtn" style="display:none;"
                        onclick="closeBookingModal();_onBookingDoneFromDashboard()">
                        Done
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>

            </div><!-- /bm-content -->
        </div><!-- /bm-box -->
    </div><!-- /bm-overlay -->

    <!-- ══ MANAGE STAY MODAL ══ -->
    <div class="manage-modal-overlay" id="manageModal">
        <div class="manage-modal-box">
            <div class="mm-hero">
                <div class="mm-hero-img" id="manageHeroImg"></div>
                <div class="mm-hero-content">
                    <div class="mm-hero-top">
                        <div class="mm-booking-ref">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            Booking <span id="manageBookingRef">#—</span>
                        </div>
                        <button class="mm-close" onclick="closeManageModal()">✕</button>
                    </div>
                    <div class="mm-unit" id="manageUnitName">—</div>
                    <div class="mm-prop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        <span id="manageProperty">—</span>
                    </div>
                    <div class="mm-meta-row">
                        <div class="mm-status" id="manageStatusPill"><span class="mm-status-dot"></span><span
                                id="manageStatusText">—</span></div>
                    </div>
                </div>
            </div>
            <div class="mm-body">
                <div class="mm-timeline">
                    <div class="mm-tl-side">
                        <div class="mm-tl-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>Check-in</div>
                        <div class="mm-tl-date" id="manageCheckin">—</div>
                        <div class="mm-tl-day" id="manageCheckinDay"></div>
                    </div>
                    <div class="mm-tl-mid">
                        <div class="mm-nights-num" id="manageNightsNum">—</div>
                        <div class="mm-nights-lbl">nights</div>
                    </div>
                    <div class="mm-tl-side">
                        <div class="mm-tl-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>Check-out</div>
                        <div class="mm-tl-date" id="manageCheckout">—</div>
                        <div class="mm-tl-day" id="manageCheckoutDay"></div>
                    </div>
                </div>
                <div class="mm-stats">
                    <div class="mm-stat">
                        <div class="mm-stat-icon ic-gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                            </svg></div>
                        <div class="mm-stat-lbl">Guests</div>
                        <div class="mm-stat-val" id="manageGuests">—</div>
                    </div>
                    <div class="mm-stat">
                        <div class="mm-stat-icon ic-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
                            </svg></div>
                        <div class="mm-stat-lbl">Total</div>
                        <div class="mm-stat-val" id="manageTotal">—</div>
                    </div>
                </div>

                <!-- Property location map -->
                <div class="mm-map-wrap" id="manageMapWrap" style="display:none;">
                    <div class="mm-map-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            style="width:13px;height:13px;flex-shrink:0;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        Property Location
                    </div>
                    <div id="manageMap"
                        style="width:100%;height:200px;border-radius:12px;border:1px solid rgba(0,0,0,.08);overflow:hidden;">
                    </div>
                    <div class="mm-map-address" id="manageMapAddress"></div>
                </div>

                <div class="mm-progress-wrap" id="manageProgressWrap">
                    <div class="mm-progress-label"><span>Stay progress</span><strong id="manageProgressText">0%</strong>
                    </div>
                    <div class="mm-progress-track">
                        <div class="mm-progress-fill" id="manageProgressFill" style="width:0%"></div>
                    </div>
                </div>
                <div class="mm-actions">
                    <button class="mm-btn mm-btn-primary" onclick="goToSupportFromManage()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                        </svg>
                        Contact Support
                    </button>
                    <button class="mm-btn mm-btn-secondary"
                        onclick="closeManageModal();document.querySelector('#bookings').scrollIntoView({behavior:'smooth'})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        View History
                    </button>
                    <button class="mm-btn mm-btn-danger" id="manageCancelBtn" onclick="cancelBooking()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="15" y1="9" x2="9" y2="15" />
                            <line x1="9" y1="9" x2="15" y2="15" />
                        </svg>
                        Cancel This Reservation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        window.PS_POPULAR_PAYMENT = <?php echo json_encode($popularPaymentMethod); ?>;
        window.hasActiveBooking = <?php echo $activeBooking ? 'true' : 'false'; ?>;
        window._psSessionFields = {
            fname: <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>,
            lname: <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>,
            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
            phone: <?php echo json_encode($_SESSION['phone'] ?? ''); ?>,
        };
        window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        window.psGetCsrfToken = function () {
            return String(window.PS_CSRF_TOKEN || '');
        };
        window.psAppendCsrf = function (target) {
            const token = window.psGetCsrfToken();
            if (!token || !target || typeof target.append !== 'function') return target;
            target.append('csrf_token', token);
            return target;
        };
        window.PS_RT_PAGE = 'dashboard';
        window.PS_RT_ROLE = 'user';
        window.PS_RT_API = '../../api/realtime.php';
    </script>
    <script src="../../assets/js/user-js/script.js"></script>
    <script src="../../assets/js/toast.js"></script>

    <script src="../../assets/js/realtime.js"></script>
    <script src="../../assets/js/user-js/user-realtime-pages.js"></script>

</body>

</html>