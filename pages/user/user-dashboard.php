<?php
include '../../includes/session.php';
require_not_blacklisted(false);

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

// Next tier progress
$loyaltyTierThresholds = ['Silver' => 0, 'Gold' => 500, 'Platinum' => 2000, 'Diamond' => 5000];
$loyaltyTierList = ['Silver', 'Gold', 'Platinum', 'Diamond'];
$loyaltyTierIndex = array_search($loyaltyTier, $loyaltyTierList);
$loyaltyNextTier = $loyaltyTierIndex < 3 ? $loyaltyTierList[$loyaltyTierIndex + 1] : null;
$loyaltyCurrentMin = $loyaltyTierThresholds[$loyaltyTier];
$loyaltyNextMin = $loyaltyNextTier ? $loyaltyTierThresholds[$loyaltyNextTier] : null;
$loyaltyProgressPct = $loyaltyNextTier
    ? min(100, round(($loyaltyPoints - $loyaltyCurrentMin) / ($loyaltyNextMin - $loyaltyCurrentMin) * 100))
    : 100;
$loyaltyPtsToNext = $loyaltyNextTier ? max(0, $loyaltyNextMin - $loyaltyPoints) : 0;

// Redeemable rewards — show up to 3, sorted by points_cost ASC
$_rewardsRes = mysqli_query($conn, "SELECT name, description, points_cost FROM loyalty_rewards WHERE is_active=1 ORDER BY points_cost ASC LIMIT 3");
$loyaltyRewards = [];
if ($_rewardsRes) {
    while ($rw = mysqli_fetch_assoc($_rewardsRes))
        $loyaltyRewards[] = $rw;
}

// Tier badge colors (inline styles)
$loyaltyTierStyles = [
    'Silver' => 'background:rgba(180,182,185,.15);border:1px solid rgba(180,182,185,.3);color:#b4b6b9',
    'Gold' => 'background:rgba(232,200,130,.15);border:1px solid rgba(232,200,130,.35);color:#e8c882',
    'Platinum' => 'background:rgba(100,180,220,.12);border:1px solid rgba(100,180,220,.3);color:#7ec8e3',
    'Diamond' => 'background:rgba(160,120,255,.15);border:1px solid rgba(160,120,255,.3);color:#c4a8ff',
];
$loyaltyTierDotColors = [
    'Silver' => '#b4b6b9',
    'Gold' => '#e8c882',
    'Platinum' => '#7ec8e3',
    'Diamond' => '#c4a8ff',
];
$loyaltyTierStyle = $loyaltyTierStyles[$loyaltyTier];
$loyaltyTierDotColor = $loyaltyTierDotColors[$loyaltyTier];

$_pmRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT payment_method, COUNT(*) AS cnt
     FROM bookings
     WHERE payment_method IS NOT NULL
       AND payment_method != ''
       AND status != 'cancelled'
     GROUP BY payment_method
     ORDER BY cnt DESC
     LIMIT 1"
));
$popularPaymentMethod = $_pmRow['payment_method'] ?? null;

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

$roomTypeFilters = [];
foreach ($units as $unit) {
    $typeName = trim((string) ($unit['unit_type'] ?? ''));
    if ($typeName === '') {
        continue;
    }
    $slug = normalizeRoomType($typeName);
    if ($slug === '') {
        continue;
    }
    if (!isset($roomTypeFilters[$slug])) {
        $roomTypeFilters[$slug] = $typeName;
    }
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
    if (empty($d) || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($d);
    if ($ts === false || $ts <= 0) return '—';
    return date('M j, Y', $ts);
}
function unitTypeToCategory($type)
{
    $type = trim((string) ($type ?? ''));
    if ($type === '') {
        return 'available';
    }

    $normalized = strtolower($type);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    $normalized = trim($normalized, '-');

    return 'available ' . ($normalized !== '' ? $normalized : 'other');
}

function normalizeRoomType($type)
{
    $type = trim((string) ($type ?? ''));
    if ($type === '') {
        return '';
    }

    $normalized = strtolower($type);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);
    return trim($normalized, '-');
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

// Sync id_verified from DB so session is always fresh
$_idRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_verified FROM users WHERE user_id=$_uid LIMIT 1"));
$_SESSION['id_verified'] = $_idRow['id_verified'] ?? 'none';
$dashIdVerified = $_SESSION['id_verified'];

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
        'payment' => ['label' => 'Payment History', 'sub' => 'View transactions & refunds', 'href' => 'payment.php', 'badge' => null, 'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
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
            <img src="../../assets/images/logo.png" alt="Boracay Accommodation" class="logo-icon">
            <div class="logo-wordmark">
                <strong>Boracay Accommodation</strong>
                <span>Boracay, Philippines</span>
            </div>
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
            <div class="btn-profile-wrap" style="position:relative;">
                <button class="btn-profile" id="profileBtn" aria-label="My Profile">
                    <?php if ($dashboardPhoto): ?>
                        <img src="<?php echo htmlspecialchars($dashboardPhoto); ?>" alt="Profile photo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                    <?php endif; ?>
                    <span class="profile-initials" <?php echo $dashboardPhoto ? 'style="display:none;"' : ''; ?>>
                        <?php echo $initials; ?>
                    </span>
                </button>
                <span id="profileActivityBadge" style="
                    display:none;
                    position:absolute;
                    top:-4px;right:-4px;
                    min-width:17px;height:17px;
                    background:#ef4444;color:#fff;
                    border-radius:99px;
                    font-size:0.62rem;font-weight:700;
                    padding:0 4px;
                    align-items:center;justify-content:center;
                    border:2px solid var(--surface,#fff);
                    pointer-events:none;
                    z-index:10;
                    line-height:1;
                ">0</span>
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
                            <span class="sb-chevron" data-msg-chevron><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
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
            data-unit-id="<?php echo (int) $activeBooking['unit_id']; ?>"
            data-checkin="<?php echo htmlspecialchars($activeBooking['checkin_date'] ?? ''); ?>"
            data-checkout="<?php echo htmlspecialchars($activeBooking['checkout_date'] ?? ''); ?>">
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
                        <div class="bb-dates"
                            data-checkin="<?php echo htmlspecialchars($activeBooking['checkin_date'] ?? ''); ?>"
                            data-checkout="<?php echo htmlspecialchars($activeBooking['checkout_date'] ?? ''); ?>">Check-in: <?php echo formatDate($activeBooking['checkin_date']); ?><span
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
            <?php foreach ($roomTypeFilters as $slug => $label): ?>
                <button class="filter-pill"
                    onclick="filterRooms('<?php echo htmlspecialchars($slug); ?>', this)"><?php echo htmlspecialchars($label); ?></button>
            <?php endforeach; ?>
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
                        $baseRate = (float) $unit['rent_amount'];
                        $seasonality = [0 => 1.30, 1 => 1.30, 2 => 1.10, 3 => 1.15, 4 => 1.15, 5 => 0.80, 6 => 0.80, 7 => 0.80, 8 => 0.80, 9 => 0.80, 10 => 1.15, 11 => 1.30];
                        $seasonLabel = [0 => 'Peak', 1 => 'Peak', 2 => 'High', 3 => 'High', 4 => 'High', 5 => 'Low', 6 => 'Low', 7 => 'Low', 8 => 'Low', 9 => 'Low', 10 => 'High', 11 => 'Peak'];
                        $seasonColor = ['Peak' => '#E74C3C', 'High' => '#deaf37', 'Low' => '#2ECC71'];
                        $curMonth = (int) date('n') - 1; // 0-indexed
                        $multiplier = $seasonality[$curMonth];
                        $adjRate = (int) round($baseRate * $multiplier);
                        $price = '₱' . number_format($adjRate);
                        $curLabel = $seasonLabel[$curMonth];
                        $curColor = $seasonColor[$curLabel];
                        $amenities = $amenitiesMap[$unit['unit_id']] ?? [];
                        $imgSrc = $unit['image_path']
                            ? '../../' . ltrim($unit['image_path'], '/')
                            : '';

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
                            'price' => '₱' . number_format($adjRate),
                            'priceNum' => $baseRate, // keep base for seasonal calc in JS
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
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div
                                    style="display:none;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(145deg,#dbeafe,#3b82f6);color:#fff;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path d="M21 15l-5-5L5 21" />
                                    </svg>
                                </div>
                                <span class="room-badge-img badge-blue">
                                    <?php echo htmlspecialchars(strtoupper($unit['unit_type'] ?? 'UNIT')); ?>
                                </span>
                                <?php
                                $availClass = $isVacant ? 'avail-yes' : ($unit['status'] === 'maintenance' ? 'avail-maintenance' : 'avail-no');
                                ?>
                                <span class="room-avail <?php echo $availClass; ?>" data-avail-status>
                                    <?php echo $isVacant ? '✓ Available' : ($unit['status'] === 'maintenance' ? 'Maintenance' : 'Booked'); ?>
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
                                    <div class="room-price">
                                        <?php echo $price; ?> <sub>/ night</sub>
                                        <span
                                            style="background:<?php echo $curColor; ?>20;color:<?php echo $curColor; ?>;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;margin-left:6px;vertical-align:middle;">
                                            <?php echo $curLabel; ?>
                                        </span>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;" data-action-buttons>
                                        <button class="btn-view-details"
                                            onclick="window.location.href='unit_detail.php?id=<?php echo (int) $unit['unit_id']; ?>'">
                                            View Details
                                        </button>
                                        <?php if ($isVacant): ?>
                                            <button class="btn-rent" data-book-btn
                                                onclick="window.location.href='unit_detail.php?id=<?php echo (int) $unit['unit_id']; ?>&book=1'">
                                                Book Now
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-rent" data-book-btn disabled>Unavailable</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div id="roomsEmptyFallback" class="room-empty-state"
                    style="display:none;grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--ink-faint);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                        style="width:48px;height:48px;margin-bottom:12px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    <p>No rooms match your current filter. Try another category or search term.</p>
                </div>
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
                            $bkImgSrc = $bk['image_path'] ? '../../' . ltrim($bk['image_path'], '/') : '';
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
                                        class="history-img-bg"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div
                                        style="display:none;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(145deg,#dbeafe,#3b82f6);color:#fff;">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.5">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <path d="M21 15l-5-5L5 21" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="history-info">
                                    <div class="history-room"><?php echo htmlspecialchars($bkUnitName); ?></div>
                                    <div class="history-prop">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                                            <circle cx="12" cy="10" r="3" />
                                        </svg>
                                        <?php echo htmlspecialchars($bk['property_name'] ?? ''); ?>
                                    </div>
                                    <div class="history-dates">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        <span data-field="checkin"><?php echo formatDate($bk['checkin_date']); ?></span> –
                                        <span data-field="checkout"><?php echo formatDate($bk['checkout_date']); ?></span>
                                        <span class="history-nights">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                                            </svg>
                                            <span data-field="nights"><?php echo $nights; ?>
                                                night<?php echo $nights !== 1 ? 's' : ''; ?></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="history-right">
                                    <div class="history-right-meta">
                                        <div class="history-price" data-field="price">
                                            ₱<?php echo number_format((float) $bk['total_amount'], 0); ?></div>
                                        <div class="history-total">Total paid</div>
                                        <div class="history-bid">
                                            #BK-<?php echo str_pad($bk['booking_id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                    <span class="history-status <?php echo statusBadgeClass($bk['status']); ?>"
                                        data-field="status"
                                        data-raw-status="<?php echo $bk['status']; ?>"><?php echo statusLabel($bk['status']); ?></span>
                                </div>
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
            <div class="loyalty-left">
                <div class="loyalty-eyebrow">🥇 Loyalty Program</div>
                <h2 class="loyalty-title">Earn points on every stay.<br><em>Redeem for free nights.</em></h2>
                <p class="loyalty-copy">Every booking earns you loyalty points. Reach Gold, Platinum or Diamond status
                    to unlock exclusive perks, room upgrades, and complimentary stays.</p>
                <div class="loyalty-tier-row">
                    <span class="loyalty-tier-badge" style="<?php echo $loyaltyTierStyle; ?>">
                        <span class="loyalty-tier-dot" style="background:<?php echo $loyaltyTierDotColor; ?>"></span>
                        <?php echo $loyaltyTier; ?> Member
                    </span>
                    <?php if ($loyaltyNextTier): ?>
                        <span class="loyalty-pts-to-next"><?php echo number_format($loyaltyPtsToNext); ?> pts to
                            <?php echo $loyaltyNextTier; ?></span>
                    <?php endif; ?>
                </div>
                <div class="loyalty-actions">
                    <a href="loyalty.php" class="loyalty-btn loyalty-btn-primary"
                        onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
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
            <div class="loyalty-right">
                <div class="loyalty-points-card">
                    <div class="loyalty-points-top">
                        <div>
                            <div class="loyalty-points-value" data-rt-user="loyalty_points">
                                <?php echo number_format($loyaltyPoints); ?>
                            </div>
                            <div class="loyalty-points-label">Points Balance</div>
                        </div>
                        <span class="loyalty-tier-pill"
                            style="<?php echo $loyaltyTierStyle; ?>"><?php echo $loyaltyTier; ?></span>
                    </div>
                    <?php if ($loyaltyNextTier): ?>
                        <div class="loyalty-progress-wrap">
                            <div class="loyalty-progress-labels">
                                <span><?php echo $loyaltyTier; ?> · <?php echo number_format($loyaltyCurrentMin); ?>
                                    pts</span>
                                <span><?php echo $loyaltyNextTier; ?> · <?php echo number_format($loyaltyNextMin); ?>
                                    pts</span>
                            </div>
                            <div class="loyalty-progress-bar">
                                <div class="loyalty-progress-fill" style="width:<?php echo $loyaltyProgressPct; ?>%"></div>
                            </div>
                            <div class="loyalty-progress-note"><?php echo number_format($loyaltyPtsToNext); ?> more points
                                to reach <?php echo $loyaltyNextTier; ?></div>
                        </div>
                    <?php else: ?>
                        <div class="loyalty-progress-note" style="margin-top:10px;">🏆 You've reached the highest tier!
                        </div>
                    <?php endif; ?>
                </div>
                <div class="loyalty-perks-card">
                    <div class="loyalty-perks-title">Rewards You Can Redeem</div>
                    <?php if (empty($loyaltyRewards)): ?>
                        <div class="loyalty-perk-row">
                            <span class="loyalty-perk-text" style="color:rgba(255,255,255,.35);font-style:italic;">No
                                rewards available yet.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($loyaltyRewards as $rw):
                            $canAfford = $loyaltyPoints >= (int) $rw['points_cost'];
                            ?>
                            <div class="loyalty-perk-row">
                                <span class="loyalty-perk-check"
                                    style="color:<?php echo $canAfford ? $loyaltyTierDotColor : 'rgba(255,255,255,.2)'; ?>">
                                    <?php if ($canAfford): ?>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="3">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                    <?php else: ?>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                    <?php endif; ?>
                                </span>
                                <span class="loyalty-perk-text"
                                    style="<?php echo $canAfford ? '' : 'color:rgba(255,255,255,.35);'; ?>;flex:1;">
                                    <?php echo htmlspecialchars($rw['name']); ?>
                                    <span
                                        style="font-size:.65rem;margin-left:4px;opacity:.6;"><?php echo number_format((int) $rw['points_cost']); ?>
                                        pts</span>
                                </span>
                                <?php if ($canAfford): ?>
                                    <a href="loyalty.php" class="loyalty-redeem-badge">Redeem</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ NEWSLETTER ══ -->
    <section id="support" class="newsletter-section">
        <div class="newsletter-inner reveal">
            <div>
                <div class="newsletter-eyebrow">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="flex-shrink:0;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    Stay in the loop
                </div>
                <h2 class="newsletter-title">Deals &amp; updates,<br><em>straight to you.</em></h2>
                <p class="newsletter-copy">Be the first to know about seasonal promos, new rooms, and member-only perks
                    curated for Boracay stays.</p>
                <div class="newsletter-perks">
                    <div class="newsletter-perk">
                        <span class="newsletter-perk-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polygon
                                    points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                            </svg>
                        </span>
                        Tips and guides for your Boracay stay
                    </div>
                    <div class="newsletter-perk">
                        <span class="newsletter-perk-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                        </span>
                        First look at new property listings
                    </div>
                    <div class="newsletter-perk">
                        <span class="newsletter-perk-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </span>
                        Updates on your bookings and account
                    </div>
                </div>
            </div>
            <div class="newsletter-card">
                <div class="newsletter-form-label">Your email address</div>
                <div class="newsletter-form">
                    <input type="email" class="newsletter-input" placeholder="you@example.com"
                        onfocus="this.style.borderColor='var(--navy-800)'"
                        onblur="this.style.borderColor='var(--border)'">
                    <button onclick="showToast('Thanks! You\'re subscribed. 🎉')"
                        class="newsletter-btn">Subscribe</button>
                </div>
                <p class="newsletter-note">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                    No spam, ever. Unsubscribe anytime.
                </p>
                <div class="newsletter-divider"></div>
                <div class="newsletter-social">
                    <span class="newsletter-social-lbl">Follow us</span>
                    <a href="https://facebook.com" target="_blank" class="newsletter-social-btn" aria-label="Facebook">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg>
                    </a>
                    <a href="https://instagram.com" target="_blank" class="newsletter-social-btn"
                        aria-label="Instagram">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" />
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" />
                        </svg>
                    </a>
                    <a href="https://tiktok.com" target="_blank" class="newsletter-social-btn" aria-label="TikTok">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M9 12a4 4 0 104 4V4a5 5 0 005 5" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>


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
            idVerified: <?php echo json_encode($_SESSION['id_verified'] ?? 'none'); ?>,
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