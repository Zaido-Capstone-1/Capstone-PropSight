<?php
/* ═══════════════════════════════════════════════════════════════════════════
   pages/user/units.php  —  Browse All Units
   ═══════════════════════════════════════════════════════════════════════════ */

include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><body><script>setTimeout(() => history.back(), 2000);</script>
</body></html>';
    exit;
}

include '../../includes/fetch_units.php';
require_once '../../includes/db.php';

$_uid = (int) $_SESSION['user_id'];

// ── Saved unit IDs ──────────────────────────────────────────────────────────
$_savedRes = mysqli_query($conn, "SELECT unit_id FROM saved_units WHERE user_id=$_uid");
$savedUnitIds = [];
while ($_sr = mysqli_fetch_assoc($_savedRes))
    $savedUnitIds[] = (int) $_sr['unit_id'];

// ── User profile photo + initials ───────────────────────────────────────────
$_uRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT first_name, last_name, profile_photo FROM users WHERE user_id=$_uid"
));
$_photo = !empty($_uRow['profile_photo']) ? '../../' . ltrim($_uRow['profile_photo'], '/') : '';
$_initials = strtoupper(mb_substr($_uRow['first_name'] ?? '', 0, 1)
    . mb_substr($_uRow['last_name'] ?? '', 0, 1));

// ── Room type filter list ────────────────────────────────────────────────────
$roomTypeFilters = [];
foreach ($units as $u) {
    $t = trim((string) ($u['unit_type'] ?? ''));
    if (!$t)
        continue;
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($t));
    $roomTypeFilters[$slug] = $roomTypeFilters[$slug] ?? $t;
}

// ── All unique amenities across all units ────────────────────────────────────
$allAmenities = [];
foreach ($amenitiesMap ?? [] as $ams) {
    foreach ($ams as $a) {
        $n = is_array($a) ? ($a['name'] ?? '') : $a;
        if ($n && !in_array($n, $allAmenities))
            $allAmenities[] = $n;
    }
}
sort($allAmenities);

// ── Price range bounds ───────────────────────────────────────────────────────
$allPrices = array_column($units, 'rent_amount');
$priceMin = !empty($allPrices) ? (int) min($allPrices) : 0;
$priceMax = !empty($allPrices) ? (int) max($allPrices) : 99999;

// ── Helpers ──────────────────────────────────────────────────────────────────
function unitTypeToCategory(string $type): string
{
    $t = strtolower(trim($type));
    $map = ['studio', 'deluxe', 'suite', 'family', 'standard', 'penthouse'];
    $out = [];
    foreach ($map as $k) {
        if (strpos($t, $k) !== false)
            $out[] = $k;
    }
    return implode(' ', $out) ?: 'standard';
}

$seasonColor = ['Peak' => '#c0694a', 'High' => '#c9a84c', 'Low' => '#2ECC71'];
$totalUnits = count($units);
$vacantCount = count(array_filter($units, fn($u) => $u['status'] === 'vacant'));

// ── Per-type counts ──────────────────────────────────────────────────────────
$typeCounts = [];
foreach ($units as $u) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($u['unit_type'] ?? ''));
    $typeCounts[$slug] = ($typeCounts[$slug] ?? 0) + 1;
}

// ── Per-amenity counts ───────────────────────────────────────────────────────
$amenityCounts = [];
foreach ($amenitiesMap ?? [] as $ams) {
    foreach ($ams as $a) {
        $n = is_array($a) ? ($a['name'] ?? '') : $a;
        if ($n)
            $amenityCounts[$n] = ($amenityCounts[$n] ?? 0) + 1;
    }
}

// ── Per-season counts ────────────────────────────────────────────────────────
$seasonCounts = [];
foreach ($units as $u) {
    $s = $u['season'] ?? 'Low';
    $seasonCounts[$s] = ($seasonCounts[$s] ?? 0) + 1;
}

// ── Top nav + sidebar nav ────────────────────────────────────────────────────
$top_nav_items = require '../../includes/user_top_nav.php';

// Loyalty tier (lightweight — just enough for sidebar sub-label)
$_lRow2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$_uid"));
$_lPts = max(0, (int)($_lRow2['v'] ?? 0));
$_lTier = 'Silver';
if ($_lPts >= 5000) $_lTier = 'Diamond';
elseif ($_lPts >= 2000) $_lTier = 'Platinum';
elseif ($_lPts >= 500) $_lTier = 'Gold';

$_isVerified = (($_SESSION['verification_status'] ?? '') === 'Verified');
$_firstName  = htmlspecialchars($_SESSION['first_name'] ?? 'Guest');
$_lastName   = htmlspecialchars($_SESSION['last_name'] ?? '');
$_fullName   = trim($_firstName . ' ' . $_lastName);
$_email      = htmlspecialchars($_SESSION['email'] ?? '');
$_initials   = strtoupper(mb_substr($_firstName, 0, 1) . mb_substr($_lastName, 0, 1));

$nav_items = [
    'profile'  => ['label'=>'View Profile',    'sub'=>'Personal details & preferences','href'=>'profile.php',  'badge'=>null,'icon'=>'<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'bookings' => ['label'=>'My Bookings',     'sub'=>'View and manage reservations',  'href'=>'bookings.php', 'badge'=>(function() use ($conn,$_uid){ $r=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM bookings WHERE user_id=$_uid AND status IN('pending','confirmed','active')")); return $r['c']>0?(string)$r['c']:null; })(),'icon'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    'payment'  => ['label'=>'Payment History', 'sub'=>'View transactions & refunds',   'href'=>'payment.php',  'badge'=>null,'icon'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
    'saved'    => ['label'=>'Saved Rooms',     'sub'=>'Rooms on your wishlist',        'href'=>'saved.php',    'badge'=>count($savedUnitIds)>0?(string)count($savedUnitIds):null,'icon'=>'<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'],
    'loyalty'  => ['label'=>'Loyalty Points',  'sub'=>$_lPts.' pts · '.$_lTier.' tier','href'=>'loyalty.php', 'badge'=>null,'icon'=>'<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'],
    'settings' => ['label'=>'Settings',        'sub'=>'Notifications, privacy, security','href'=>'settings.php','badge'=>null,'icon'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    'messages' => ['label'=>'Messages',        'sub'=>'Chat with the property team',   'href'=>'messages.php', 'badge'=>null,'icon'=>'<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
    'support'  => ['label'=>'Support & Help',  'sub'=>'FAQs and contact staff',        'href'=>'support.php',  'badge'=>null,'icon'=>'<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
];
$active_nav = 'browse';

// ── Floor list ───────────────────────────────────────────────────────────────
$floors = [];
foreach ($units as $u) {
    if ($u['floor'])
        $floors[(int) $u['floor']] = true;
}
ksort($floors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Units — Boracay Accommodation</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link rel="stylesheet" href="../../assets/css/user-css/bottom-nav.css">
    <link rel="stylesheet" href="../../assets/css/user-css/floating-chat.css">
    <link rel="stylesheet" href="../../assets/css/user-css/styles.css">
    <link rel="stylesheet" href="../../assets/css/user-css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/units.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

    <!-- Pass PHP config to JS -->
    <script>
        window.UNITS_CONFIG = {
            priceMin: <?php echo $priceMin; ?>,
            priceMax: <?php echo $priceMax; ?>
        };
    </script>
</head>

<body>

<!-- ══ HEADER ════════════════════════════════════════════════════════════════ -->
<header id="hdr">
    <a href="user-dashboard.php" class="logo">
        <img src="../../assets/images/logo.png" alt="Boracay Accommodation" class="logo-icon">
        <div class="logo-wordmark">
            <strong>Boracay Accommodation</strong>
            <span>Boracay, Philippines</span>
        </div>
    </a>
    <nav>
        <?php foreach ($top_nav_items as $top_nav): ?>
            <a href="<?php echo $top_nav['href']; ?>"
               class="<?php echo $top_nav['key'] === 'browse' ? 'active' : ''; ?>">
                <?php echo $top_nav['label']; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="header-right">
        <!-- Notification bell -->
        <div style="position:relative;display:inline-flex;align-items:center;">
            <button id="notifBellBtn" aria-label="Notifications"
                style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;color:var(--ink-soft);display:flex;align-items:center;justify-content:center;transition:background .2s;"
                onmouseenter="this.style.background='var(--navy-50)'" onmouseleave="this.style.background='none'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span data-rt="notif-count"
                    style="display:none;position:absolute;top:2px;right:2px;font-size:.62rem;background:#ef4444;color:#fff;border-radius:99px;min-width:15px;height:15px;padding:0 3px;align-items:center;justify-content:center;font-weight:700;pointer-events:none;">0</span>
            </button>
        </div>
        <!-- Profile button -->
        <div class="btn-profile-wrap" style="position:relative;">
            <button class="btn-profile" id="profileBtn" aria-label="My Profile">
                <?php if ($_photo): ?>
                    <img src="<?php echo htmlspecialchars($_photo); ?>" alt="Profile"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
                <?php endif; ?>
                <span class="profile-initials" <?php echo $_photo ? 'style="display:none;"' : ''; ?>>
                    <?php echo $_initials; ?>
                </span>
            </button>
            <span id="profileActivityBadge" style="display:none;position:absolute;top:-4px;right:-4px;min-width:17px;height:17px;background:#ef4444;color:#fff;border-radius:99px;font-size:0.62rem;font-weight:700;padding:0 4px;align-items:center;justify-content:center;border:2px solid var(--surface,#fff);pointer-events:none;z-index:10;line-height:1;">0</span>
            <span class="profile-dot"></span>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<!-- ══ MOBILE NAV ═════════════════════════════════════════════════════════════ -->
<div class="mobile-nav" id="mobileNav">
    <?php foreach ($top_nav_items as $top_nav): ?>
        <a href="<?php echo $top_nav['href']; ?>" onclick="closeMob()"><?php echo $top_nav['label']; ?></a>
    <?php endforeach; ?>
</div>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="profile-sidebar" id="profileSidebar" style="display:flex;flex-direction:column;height:100dvh;overflow:hidden;">
    <div class="sidebar-hdr">
        <button class="sidebar-close" id="sidebarClose" onclick="closeProfileSidebar()">✕</button>
        <div class="sb-avatar">
            <?php if ($_photo): ?>
                <img src="<?php echo htmlspecialchars($_photo); ?>" alt="Profile photo"
                     onerror="this.style.display='none';this.parentElement.classList.add('sb-avatar-fallback');">
            <?php else: ?>
                <?php echo $_initials; ?>
            <?php endif; ?>
        </div>
        <div class="sb-name"><?php echo $_fullName; ?></div>
        <div class="sb-email"><?php echo $_email; ?></div>
        <div class="sb-badge <?php echo $_isVerified ? 'sb-badge-verified' : 'sb-badge-unverified'; ?>">
            <span class="badge-dot"></span>
            <span class="sb-badge-label"><?php echo $_isVerified ? 'Email Verified' : 'Email Not Verified'; ?></span>
            <?php if (!$_isVerified): ?>
                <a href="profile.php" class="sb-verify-link">Verify Email</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="sidebar-body" style="flex:1;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;">
        <div class="sb-section-label">Account</div>
        <?php foreach ($nav_items as $key => $item): ?>
            <a href="<?php echo $item['href']; ?>" class="sb-item">
                <div class="sb-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round"><?php echo $item['icon']; ?></svg>
                </div>
                <div class="sb-text">
                    <div class="sb-title"><?php echo $item['label']; ?></div>
                    <div class="sb-sub"><?php echo $item['sub']; ?></div>
                </div>
                <div class="sb-right">
                    <?php if ($item['badge'] && $key === 'saved'): ?>
                        <span class="sb-badge-pill" data-rt-user="saved_count"><?php echo $item['badge']; ?></span>
                    <?php elseif ($item['badge']): ?>
                        <span class="sb-badge-pill"><?php echo $item['badge']; ?></span>
                    <?php elseif ($key === 'saved'): ?>
                        <span class="sb-badge-pill" data-rt-user="saved_count" style="display:none;"></span>
                        <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
                    <?php elseif ($key === 'messages'): ?>
                        <span class="sb-badge-pill nav-badge" data-rt="messages" style="display:none;background:#ef4444;"></span>
                        <span class="sb-chevron" data-msg-chevron><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
                    <?php else: ?>
                        <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ($key === 'loyalty'): ?>
                <div class="sb-divider"></div>
                <div class="sb-section-label">Preferences</div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-foot" style="flex-shrink:0;">
        <a href="../../process/logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Sign Out
        </a>
    </div>
</aside>

<!-- ══ HERO ═════════════════════════════════════════════════════════════════ -->
<div class="page-hero" style="background-image:url('../../assets/images/hero.jpg');background-size:cover;background-position:center;background-repeat:no-repeat;">
    <div class="page-hero-rule"></div>
    <div class="page-hero-inner reveal">
        <div>
            <div class="breadcrumb">
                <a href="user-dashboard.php">My Account</a>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                <span>Browse Units</span>
            </div>
            <h1 class="page-hero-title">Browse Our <em>Units</em></h1>
            <p class="page-hero-sub">Filter, sort, and find the perfect unit for your Boracay stay.</p>
            <div class="units-hero-stats-row">
                <div class="uhsr-item">
                    <span class="uhsr-num"><?php echo $totalUnits; ?></span>
                    <span class="uhsr-lbl">Total Units</span>
                </div>
                <div class="uhsr-divider"></div>
                <div class="uhsr-item">
                    <span class="uhsr-num" style="color:#4ade80;"><?php echo $vacantCount; ?></span>
                    <span class="uhsr-lbl">Available Now</span>
                </div>
                <div class="uhsr-divider"></div>
                <div class="uhsr-item">
                    <span class="uhsr-num" style="color:#e8c882;"><?php echo count($roomTypeFilters); ?></span>
                    <span class="uhsr-lbl">Room Types</span>
                </div>
            </div>
        </div>
        <div class="page-hero-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
    </div>
</div>

<!-- ══ TOOLBAR ════════════════════════════════════════════════════════════════ -->

<!-- ══ PAGE BODY (sidebar + grid) ═══════════════════════════════════════════ -->
<div class="units-body">

    <!-- ── LEFT SIDEBAR ───────────────────────────────────────────────────── -->
    <aside class="units-sidebar" id="unitsSidebar">

        <div class="sb-head">
            <span class="sb-head-title">Filters</span>
            <button class="sb-clear-btn" id="sbClearBtn" onclick="clearAllFilters()">Clear all</button>
        </div>

        <!-- ① Availability ------------------------------------------------- -->
        <div class="sb-section">
            <div class="sb-section-label">Availability</div>
            <div class="sb-avail-btns">
                <button class="sb-avail-btn active" data-avail="all" onclick="setAvail('all',this)">
                    <span class="sb-avail-dot" style="background:var(--navy-300);"></span>
                    All Units
                    <span class="sb-avail-count"><?php echo $totalUnits; ?></span>
                </button>
                <button class="sb-avail-btn" data-avail="vacant" onclick="setAvail('vacant',this)">
                    <span class="sb-avail-dot" style="background:#4ade80;"></span>
                    Available Now
                    <span class="sb-avail-count"><?php echo $vacantCount; ?></span>
                </button>
                <button class="sb-avail-btn" data-avail="booked" onclick="setAvail('booked',this)">
                    <span class="sb-avail-dot" style="background:var(--terra);"></span>
                    Booked
                    <span class="sb-avail-count"><?php echo $totalUnits - $vacantCount; ?></span>
                </button>
            </div>
        </div>

        <!-- ② Price Range --------------------------------------------------- -->
        <div class="sb-section">
            <div class="sb-section-label">
                Price / Night
                <button onclick="resetPrice()">Reset</button>
            </div>
            <div class="sb-price-display">
                <span class="sb-price-val" id="priceMinDisplay">₱<?php echo number_format($priceMin); ?></span>
                <span class="sb-price-sep">—</span>
                <span class="sb-price-val" id="priceMaxDisplay">₱<?php echo number_format($priceMax); ?></span>
            </div>
            <div class="sb-range-wrap">
                <div class="sb-range-track"></div>
                <div class="sb-range-fill" id="rangeFill"></div>
                <input type="range" class="sb-range-input" id="priceRangeMin"
                       min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>"
                       value="<?php echo $priceMin; ?>" oninput="onPriceRange()">
                <input type="range" class="sb-range-input" id="priceRangeMax"
                       min="<?php echo $priceMin; ?>" max="<?php echo $priceMax; ?>"
                       value="<?php echo $priceMax; ?>" oninput="onPriceRange()">
            </div>
        </div>

        <!-- ③ Unit Type ----------------------------------------------------- -->
        <?php if (!empty($roomTypeFilters)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Unit Type
                    <button onclick="clearTypeFilters()">Clear</button>
                </div>
                <div class="sb-check-list">
                    <?php foreach ($roomTypeFilters as $slug => $label): ?>
                            <label class="sb-check-item">
                                <input type="checkbox" class="type-cb"
                                       value="<?php echo htmlspecialchars($slug); ?>"
                                       onchange="applyFilters()">
                                <span class="sb-check-label"><?php echo htmlspecialchars($label); ?></span>
                                <span class="sb-check-count"><?php echo $typeCounts[$slug] ?? 0; ?></span>
                            </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ④ Floor --------------------------------------------------------- -->
        <?php if (!empty($floors)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Floor
                    <button onclick="clearFloorFilters()">Clear</button>
                </div>
                <div class="sb-floor-btns">
                    <?php foreach (array_keys($floors) as $flr): ?>
                            <button class="sb-floor-btn" data-floor="<?php echo $flr; ?>"
                                    onclick="toggleFloor(<?php echo $flr; ?>, this)">
                                Floor <?php echo $flr; ?>
                            </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⑤ Season -------------------------------------------------------- -->
        <?php
        $seasonDots = ['Low' => '#4ade80', 'High' => '#c9a84c', 'Peak' => '#c0694a'];
        $hasSeasons = !empty(array_filter($seasonDots, fn($s) => ($seasonCounts[$s] ?? 0) > 0, ARRAY_FILTER_USE_KEY));
        if ($hasSeasons): ?>
            <div class="sb-section">
                <div class="sb-section-label">Season</div>
                <div class="sb-season-btns">
                    <?php foreach ($seasonDots as $s => $col):
                        $cnt = $seasonCounts[$s] ?? 0;
                        if (!$cnt)
                            continue; ?>
                            <button class="sb-season-btn" data-season="<?php echo $s; ?>"
                                    onclick="toggleSeason('<?php echo $s; ?>', this)">
                                <span class="sb-season-dot" style="background:<?php echo $col; ?>;"></span>
                                <?php echo $s; ?> Season
                                <span class="sb-avail-count"><?php echo $cnt; ?></span>
                            </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⑥ Amenities ----------------------------------------------------- -->
        <?php if (!empty($allAmenities)): ?>
            <div class="sb-section">
                <div class="sb-section-label">
                    Amenities
                    <button onclick="clearAmenityFilters()">Clear</button>
                </div>
                <div class="sb-amenity-list">
                    <?php foreach ($allAmenities as $am): ?>
                            <label class="sb-check-item">
                                <input type="checkbox" class="amenity-cb"
                                       value="<?php echo htmlspecialchars($am); ?>"
                                       onchange="applyFilters()">
                                <span class="sb-check-label"><?php echo htmlspecialchars($am); ?></span>
                                <span class="sb-check-count"><?php echo $amenityCounts[$am] ?? 0; ?></span>
                            </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </aside><!-- /units-sidebar -->

    <!-- ── RIGHT: RESULTS ─────────────────────────────────────────────────── -->
    <div class="units-main">
<div class="units-toolbar">
    <div class="units-toolbar-inner">

        <!-- Mobile-only filter button -->
        <button class="btn-filter-toggle" id="filterToggleBtn" onclick="openSidebar()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
                <line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            Filters
            <span class="f-badge" id="mobileFilterCount">0</span>
        </button>

        <!-- Search -->
        <div class="u-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="unitSearch" placeholder="Search units, properties…"
                   oninput="applyFilters()" autocomplete="off">
        </div>

        <!-- Sort -->
        <select class="u-sort-select" id="unitSort" onchange="applySort(this.value)">
            <option value="default">Sort: Default</option>
            <option value="price-asc">Price: Low → High</option>
            <option value="price-desc">Price: High → Low</option>
            <option value="name-asc">Name: A → Z</option>
            <option value="rating-desc">Top Rated</option>
        </select>

        <!-- View toggle -->
        <div class="u-view-toggle">
            <button class="u-view-btn active" id="viewGrid" aria-label="Grid view" onclick="setView('grid')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                </svg>
            </button>
            <button class="u-view-btn" id="viewList" aria-label="List view" onclick="setView('list')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Mobile backdrop for sidebar drawer -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>


        <!-- Meta row -->
        <div class="units-meta-row">
            <p class="units-result-count">
                Showing <strong id="unitsCountNum"><?php echo $totalUnits; ?></strong>
                of <strong><?php echo $totalUnits; ?></strong>
                unit<?php echo $totalUnits !== 1 ? 's' : ''; ?>
            </p>
            <div class="units-active-tags" id="activeTagsWrap"></div>
        </div>

        <!-- Card grid -->
        <div id="unitsGrid">

            <?php if (empty($units)): ?>
                    <div class="units-empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        <h3>No units yet</h3>
                        <p>There are no units available at the moment. Please check back later.</p>
                    </div>

            <?php else: ?>

                    <?php foreach ($units as $unit):
                        $isVacant = $unit['status'] === 'vacant';
                        $cats = unitTypeToCategory($unit['unit_type'] ?? '');
                        $rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));

                        if (!empty($unit['unit_name']))
                            $rawName = $unit['unit_name'];
                        elseif (!empty($unit['property_name']) && $rawNum !== '')
                            $rawName = $unit['property_name'] . ' — Unit ' . $rawNum;
                        elseif (!empty($unit['unit_number']))
                            $rawName = $unit['unit_number'];
                        else
                            $rawName = 'Unit #' . $unit['unit_id'];

                        $unitName = htmlspecialchars($rawName);
                        $propName = htmlspecialchars($unit['property_name'] ?? '');
                        $cityPart = !empty($unit['city']) ? ', ' . $unit['city'] : '';
                        $baseRate = (float) $unit['rent_amount'];
                        $unitSeason = $unit['season'] ?? 'Low';
                        $sColor = $seasonColor[$unitSeason] ?? '#2ECC71';

                        $unitAms = $amenitiesMap[$unit['unit_id']] ?? [];
                        $amNames = array_values(array_filter(
                            array_map(fn($a) => is_array($a) ? ($a['name'] ?? '') : $a, $unitAms)
                        ));

                        $imgSrc = $unit['image_path'] ? '../../' . ltrim($unit['image_path'], '/') : '';
                        $isSaved = in_array((int) $unit['unit_id'], $savedUnitIds);
                        $ratingValue = isset($unit['rating']) && $unit['rating'] !== null && $unit['rating'] !== ''
                            ? round((float) $unit['rating'], 1) : null;

                        $availClass = $isVacant ? 'avail-yes'
                            : ($unit['status'] === 'maintenance' ? 'avail-maintenance' : 'avail-no');
                        $detailUrl = 'unit_detail.php?id=' . (int) $unit['unit_id'];
                        $bookUrl = 'unit_detail.php?id=' . (int) $unit['unit_id'] . '&book=1';
                        $typeSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($unit['unit_type'] ?? ''));
                        $amAttr = htmlspecialchars(implode('||', $amNames), ENT_QUOTES);
                        ?>
                        <div class="room-card"
                             data-unit-id="<?php echo (int) $unit['unit_id']; ?>"
                             data-cat="<?php echo htmlspecialchars($cats, ENT_QUOTES); ?>"
                             data-type="<?php echo htmlspecialchars($typeSlug, ENT_QUOTES); ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($rawName . ' ' . ($unit['property_name'] ?? '')), ENT_QUOTES); ?>"
                             data-status="<?php echo htmlspecialchars($unit['status'], ENT_QUOTES); ?>"
                             data-rent="<?php echo $baseRate; ?>"
                             data-rating="<?php echo $ratingValue ?? 0; ?>"
                             data-floor="<?php echo (int) ($unit['floor'] ?? 0); ?>"
                             data-season="<?php echo htmlspecialchars($unitSeason, ENT_QUOTES); ?>"
                             data-amenities="<?php echo $amAttr; ?>"
                             onclick="location.href='<?php echo $detailUrl; ?>'">

                            <!-- Image -->
                            <div class="room-card-img">
                                <?php if ($imgSrc): ?>
                                        <img src="<?php echo $imgSrc; ?>" alt="<?php echo $unitName; ?>"
                                             class="room-img-placeholder"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <?php endif; ?>
                                <!-- Fallback -->
                                <div style="<?php echo $imgSrc ? 'display:none;' : ''; ?>width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--navy-700),var(--navy-500));color:rgba(255,255,255,.28);">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>

                                <!-- TOP ROW: type badge (left) + status badge (right) -->
                                <div class="card-badge-row">
                                    <span class="badge-type">
                                        <?php echo htmlspecialchars(strtoupper($unit['unit_type'] ?? 'UNIT')); ?>
                                    </span>
                                    <span class="badge-status <?php echo $availClass; ?>">
                                        <?php if ($isVacant): ?>✓ Available
                                        <?php elseif ($unit['status'] === 'maintenance'): ?>⚠ Maintenance
                                        <?php else: ?>✕ Booked<?php endif; ?>
                                    </span>
                                </div>

                                <button class="btn-save-room<?php echo $isSaved ? ' saved' : ''; ?>"
                                        onclick="event.stopPropagation(); toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)"
                                        aria-label="<?php echo $isSaved ? 'Remove from saved' : 'Save room'; ?>">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                    </svg>
                                </button>

                                <div class="room-season-vignette">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                    </svg>
                                    <?php echo ucfirst($unitSeason); ?> Season
                                    <span style="margin-left:auto;font-weight:700;color:<?php echo $sColor; ?>;">●</span>
                                </div>
                            </div><!-- /room-card-img -->

                            <!-- Body -->
                            <div class="room-card-body">
                                <div class="room-card-top">
                                    <div class="room-name"><?php echo $unitName; ?></div>
                                    <div class="room-rating<?php echo $ratingValue === null ? ' no-rating' : ''; ?>">
                                        <?php if ($ratingValue !== null): ?>
                                                <svg viewBox="0 0 24 24">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                                </svg>
                                                <span><?php echo number_format($ratingValue, 1); ?></span>
                                        <?php else: ?>
                                                <span>No ratings yet.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($propName || $cityPart): ?>
                                        <div class="room-location-chip">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            <?php echo $propName . htmlspecialchars($cityPart); ?>
                                        </div>
                                <?php endif; ?>

                                <div class="room-meta">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        </svg>
                                        <?php echo htmlspecialchars(ucfirst($unit['unit_type'] ?? 'Standard')); ?>
                                    </span>
                                    <?php if ($unit['floor']): ?>
                                            <span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                                                    <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
                                                </svg>
                                                Floor <?php echo (int) $unit['floor']; ?>
                                            </span>
                                    <?php endif; ?>
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        </svg>
                                        2 Guests
                                    </span>
                                </div>

                                <?php if (!empty($amNames)): ?>
                                        <div class="room-features">
                                            <?php foreach (array_slice($amNames, 0, 4) as $am): ?>
                                                    <span class="feature-chip"><?php echo htmlspecialchars($am); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($amNames) > 4): ?>
                                                    <span class="feature-chip" style="opacity:.55;">+<?php echo count($amNames) - 4; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                <?php endif; ?>

                                <div class="room-divider"></div>

                                <div class="room-price-row">
                                    <div class="room-price">
                                        ₱<?php echo number_format((int) $baseRate); ?> <sub>/ night</sub>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;" onclick="event.stopPropagation()">
                                        <button class="btn-view-details"
                                                onclick="location.href='<?php echo $detailUrl; ?>'">
                                            Details
                                        </button>
                                        <?php if ($isVacant): ?>
                                                <button class="btn-rent"
                                                        onclick="location.href='<?php echo $bookUrl; ?>'">
                                                    Book Now
                                                </button>
                                        <?php else: ?>
                                                <button class="btn-rent" disabled>Unavailable</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div><!-- /room-card-body -->

                        </div><!-- /room-card -->
                    <?php endforeach; ?>

                    <!-- Empty state when all filtered out -->
                    <div id="unitsEmptyFallback" class="units-empty-state" style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <h3>No units found</h3>
                        <p>No units match your current filters. Try adjusting them.</p>
                        <button onclick="clearAllFilters()">Clear All Filters</button>
                    </div>

            <?php endif; ?>

        </div><!-- /#unitsGrid -->
    </div><!-- /.units-main -->
</div><!-- /.units-body -->

<div id="toast" style="position:fixed;bottom:32px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--navy-800);color:var(--white);padding:14px 28px;border-radius:40px;font-size:.88rem;font-weight:500;box-shadow:0 8px 32px rgba(10,22,40,.35);z-index:600;transition:transform .4s cubic-bezier(.4,0,.2,1),opacity .4s;opacity:0;white-space:nowrap;display:flex;align-items:center;gap:10px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;stroke:var(--sand);flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg"></span>
</div>

<script>
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    window.PS_USER_ID = <?php echo json_encode((int) ($_SESSION['user_id'] ?? 0)); ?>;
    window.psGetCsrfToken = function() { return String(window.PS_CSRF_TOKEN || ''); };
    window.psAppendCsrf = function(target) {
        var token = window.psGetCsrfToken();
        if (!token || !target || typeof target.append !== 'function') return target;
        target.append('csrf_token', token);
        return target;
    };
    window.PS_RT_PAGE = 'units';
    window.PS_RT_ROLE = 'user';
    window.PS_RT_API = '../../api/realtime.php';
    window.hasActiveBooking = false;
    window._psSessionFields = {
        fname: <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>,
        lname: <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>,
        email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
        phone: <?php echo json_encode($_SESSION['phone'] ?? ''); ?>,
        idVerified: <?php echo json_encode($_SESSION['id_verified'] ?? 'none'); ?>,
    };
</script>
<script>
    /* Profile sidebar — separate from units.js filter sidebar */
    function openProfileSidebar() {
        document.getElementById('sidebarOverlay')?.classList.add('open');
        document.getElementById('profileSidebar')?.classList.add('open');
        document.body.classList.add('sidebar-open');
    }
    function closeProfileSidebar() {
        document.getElementById('sidebarOverlay')?.classList.remove('open');
        document.getElementById('profileSidebar')?.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Overlay click
        var ov = document.getElementById('sidebarOverlay');
        if (ov) ov.addEventListener('click', closeProfileSidebar);
        // Close button
        var sc = document.getElementById('sidebarClose');
        if (sc) sc.addEventListener('click', closeProfileSidebar);
        // Profile button — override script.js binding after all scripts load
        window.addEventListener('load', function() {
            var pb = document.getElementById('profileBtn');
            if (pb) {
                pb.replaceWith(pb.cloneNode(true)); // strip script.js listener
                document.getElementById('profileBtn').addEventListener('click', openProfileSidebar);
            }
        });
    });
</script>
<script src="../../assets/js/toast.js"></script>
<script src="../../assets/js/user-js/script.js"></script>
<script src="../../assets/js/user-js/units.js"></script>
<script src="../../assets/js/realtime.js"></script>
<script src="../../assets/js/user-js/user-realtime-pages.js"></script>
<script src="../../assets/js/user-js/floating-chat.js"></script>

<?php require '../../includes/_bottom_nav.php'; ?>
</body>
</html>