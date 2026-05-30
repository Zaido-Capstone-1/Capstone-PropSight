<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><body><script>setTimeout(()=>history.back(),2000);</script></body></html>';
    exit;
}
require_once '../../includes/db.php';

$unit_id = (int) ($_GET['id'] ?? 0);
if ($unit_id <= 0) {
    header('Location: user-dashboard.php');
    exit;
}
$_uid = (int) $_SESSION['user_id'];

$_idRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_verified FROM users WHERE user_id=$_uid LIMIT 1"));
$_SESSION['id_verified'] = $_idRow['id_verified'] ?? 'none';

// ── Review pagination params (needed before queries file) ─────────────────────
$reviewPage = max(1, (int) ($_GET['rp'] ?? 1));
$reviewLimit = 4;
$reviewOffset = ($reviewPage - 1) * $reviewLimit;

// ── All SQL queries ───────────────────────────────────────────────────────────
require '../../lib/user-queries/unit_detail_queries.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function ud_esc($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function getAmenityIcon($name)
{
    $map = [
        'air conditioning' => 'ti-air-conditioning',
        'wifi' => 'ti-wifi',
        'wi-fi' => 'ti-wifi',
        'pool' => 'ti-swimming',
        'parking' => 'ti-parking',
        'television' => 'ti-tv',
        ' tv' => 'ti-tv',
        'kitchen' => 'ti-toolbox',
        'washer' => 'ti-shirt',
        'laundry' => 'ti-shirt',
        'housekeeping' => 'ti-home-check',
        'security' => 'ti-lock',
        'elevator' => 'ti-arrow-up',
        'gym' => 'ti-barbell',
        'balcony' => 'ti-sun',
        'hot water' => 'ti-droplet',
        'shower' => 'ti-droplets',
        'cable' => 'ti-device-tv',
        'heater' => 'ti-flame',
        'refrigerator' => 'ti-device-floppy',
    ];
    $lower = strtolower($name);
    foreach ($map as $k => $v) {
        if (str_contains($lower, trim($k)))
            return $v;
    }
    return 'ti-circle-check';
}

$rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));
$unitTitle = !empty($unit['unit_name']) ? $unit['unit_name']
    : (!empty($unit['property_name']) && !empty($rawNum)
        ? $unit['property_name'] . ' — Unit ' . $rawNum
        : ($unit['unit_number'] ?? 'Unit #' . $unit_id));

$isVacant = $unit['status'] === 'vacant';
$isBooked = $unit['status'] === 'booked';
$isOccupied = $unit['status'] === 'occupied';
$isMaintenance = $unit['status'] === 'maintenance';

$statusLabel = match ($unit['status']) {
    'vacant' => '✓ Available',
    'booked' => '📅 Booked',
    'occupied' => 'Occupied',
    'maintenance' => 'Maintenance',
    default => ucfirst($unit['status'] ?? 'Unavailable'),
};
$statusBadgeClass = $isVacant ? 'avail-yes' : ($isBooked ? 'avail-booked' : 'avail-no');

$priceNum = (float) $unit['rent_amount'];
$seasonality = [0 => 1.30, 1 => 1.30, 2 => 1.10, 3 => 1.15, 4 => 1.15, 5 => 0.80, 6 => 0.80, 7 => 0.80, 8 => 0.80, 9 => 0.80, 10 => 1.15, 11 => 1.30];
$seasonLabel = [0 => 'Peak', 1 => 'Peak', 2 => 'High', 3 => 'High', 4 => 'High', 5 => 'Low', 6 => 'Low', 7 => 'Low', 8 => 'Low', 9 => 'Low', 10 => 'High', 11 => 'Peak'];
$seasonColor = ['Peak' => '#E74C3C', 'High' => '#deaf37', 'Low' => '#2ECC71'];
$curMonth = (int) date('n') - 1;
$multiplier = $seasonality[$curMonth];
$adjRate = (int) round($priceNum * $multiplier);
$price = '₱' . number_format($adjRate);
$curLabel = $seasonLabel[$curMonth];
$curColor = $seasonColor[$curLabel];
$ratingValue = isset($unit['rating']) && $unit['rating'] !== null ? round((float) $unit['rating'], 1) : null;
$cityPart = !empty($unit['city']) ? ', ' . $unit['city'] : '';
$locationStr = ($unit['property_name'] ?? '') . $cityPart;
$addressStr = trim(($unit['address'] ?? '') . $cityPart);

$roomJs = json_encode([
    'id' => $unit['unit_id'],
    'name' => $unitTitle,
    'location' => $locationStr,
    'address' => $addressStr,
    'city' => $unit['city'] ?? '',
    'price' => $price,
    'priceNum' => $priceNum,
    'rating' => $ratingValue,
    'guests' => 2,
    'view' => $unit['unit_type'] ?? 'Standard',
    'desc' => $unit['description'] ?? '',
    'amenities' => $amenities,
    'image' => $images[0] ?? '',
    'images' => $images,
    'latitude' => (float) ($unit['latitude'] ?? 0),
    'longitude' => (float) ($unit['longitude'] ?? 0),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$dashboardPhotoRaw = trim((string) ($_SESSION['profile_photo'] ?? ''));
$dashboardPhoto = $dashboardPhotoRaw !== '' ? '../../' . ltrim($dashboardPhotoRaw, '/') : '';
$top_nav_items = require '../../includes/user_top_nav.php';

$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$isVerifiedSidebar = (($_SESSION['verification_status'] ?? '') === 'Verified');
$sidebarPhoto = $dashboardPhoto;
$active_nav = 'dashboard';

$nav_items = [
    'profile' => [
        'label' => 'View Profile',
        'sub' => 'Personal details & preferences',
        'href' => 'profile.php',
        'badge' => null,
        'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    ],
    'bookings' => [
        'label' => 'My Bookings',
        'sub' => 'View and manage reservations',
        'href' => 'bookings.php',
        'badge' => $_activeBookingCount,
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ],
    'payment' => [
        'label' => 'Payment History',
        'sub' => 'View transactions & refunds',
        'href' => 'payment.php',
        'badge' => null,
        'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>',
    ],
    'saved' => [
        'label' => 'Saved Rooms',
        'sub' => 'Rooms on your wishlist',
        'href' => 'saved.php',
        'badge' => $_savedCount,
        'icon' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
    ],
    'loyalty' => [
        'label' => 'Loyalty Points',
        'sub' => $_loyaltySub,
        'href' => 'loyalty.php',
        'badge' => null,
        'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>',
    ],
    'settings' => [
        'label' => 'Settings',
        'sub' => 'Notifications, privacy, security',
        'href' => 'settings.php',
        'badge' => null,
        'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
    ],
    'messages' => [
        'label' => 'Messages',
        'sub' => 'Chat with the property team',
        'href' => 'messages.php',
        'badge' => null,
        'icon' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
    ],
    'support' => [
        'label' => 'Support & Help',
        'sub' => 'FAQs and contact staff',
        'href' => 'support.php',
        'badge' => null,
        'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ud_esc($unitTitle); ?> — PropSight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link rel="stylesheet" href="../../assets/css/user-css/styles.css">
    <link rel="stylesheet" href="../../assets/css/user-css/user-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/unit_detail.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>

<body>

    <!-- HEADER -->
    <header id="hdr">
        <a href="user-dashboard.php" class="logo">
            <img src="../../assets/images/logo.png" alt="Boracay Accommodation" class="logo-icon">
            <div class="logo-wordmark">
                <strong>Boracay Accommodation</strong>
                <span>Boracay, Philippines</span>
            </div>
        </a>
        <nav>
            <?php foreach ($top_nav_items as $item): ?>
                <a href="<?php echo ud_esc($item['href']); ?>"><?php echo ud_esc($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <a href="user-dashboard.php#browse" class="btn-browse" style="text-decoration:none;">Browse Rooms</a>
            <div class="btn-profile-wrap">
                <button class="btn-profile" id="profileBtn">
                    <?php if ($dashboardPhoto): ?>
                        <img src="<?php echo ud_esc($dashboardPhoto); ?>" alt="Profile"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                    <?php endif; ?>
                    <span class="profile-initials" <?php echo $dashboardPhoto ? 'style="display:none"' : ''; ?>>
                        <?php echo $initials; ?>
                    </span>
                </button>
                <span class="profile-dot"></span>
            </div>
            <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
        </div>
    </header>

    <?php require '../../includes/_unitdetails_layout.php'; ?>

    <!-- BREADCRUMB -->
    <div class="ud-breadcrumb">
        <button class="ud-back-btn" onclick="history.back()" title="Go back">
            <i class="ti ti-arrow-left"></i>
            <span>Back</span>
        </button>
        <a href="user-dashboard.php">Dashboard</a>
        <i class="ti ti-chevron-right ud-bc-sep"></i>
        <a href="user-dashboard.php#browse">Browse Rooms</a>
        <i class="ti ti-chevron-right ud-bc-sep"></i>
        <span><?php echo ud_esc($unitTitle); ?></span>
    </div>

    <!-- LIGHTBOX -->
    <div class="ud-lightbox" id="udLightbox" role="dialog" aria-modal="true">
        <button class="ud-lb-close" id="udLbClose"><i class="ti ti-x"></i></button>
        <button class="ud-lb-nav ud-lb-prev" id="udLbPrev"><i class="ti ti-chevron-left"></i></button>
        <button class="ud-lb-nav ud-lb-next" id="udLbNext"><i class="ti ti-chevron-right"></i></button>
        <div class="ud-lb-track" id="udLbTrack">
            <?php foreach ($images as $img): ?>
                <div class="ud-lb-slide">
                    <img src="<?php echo ud_esc($img); ?>" alt="<?php echo ud_esc($unitTitle); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ud-lb-counter"><span id="udLbCurrent">1</span> / <span><?php echo count($images); ?></span></div>
    </div>

    <!-- VIRTUAL TOUR MODAL -->
    <div class="ud-tour-modal" id="udTourModal" role="dialog" aria-modal="true">
        <button class="ud-tour-close" onclick="closeTour()"><i class="ti ti-x"></i></button>
        <div class="ud-tour-inner">
            <div class="ud-tour-placeholder">
                <i class="ti ti-360" style="font-size:40px;color:var(--ud-green);margin-bottom:10px"></i>
                <div style="font-size:.9rem;font-weight:600;color:var(--ud-text)">360° Virtual Tour</div>
                <div style="font-size:.75rem;color:var(--ud-text-soft);margin-top:6px">
                    Virtual tour not yet available for this unit.<br>Contact the host to schedule a viewing.
                </div>
                <a href="messages.php" class="ud-tour-cta" style="margin-top:16px">
                    <i class="ti ti-message-circle"></i> Message the host
                </a>
            </div>
        </div>
    </div>

    <!-- MAP MODAL -->
    <div class="ud-map-modal" id="udMapModal" role="dialog" aria-modal="true">
        <div class="ud-map-modal-inner">
            <div class="ud-map-modal-header">
                <div class="ud-map-modal-title">
                    <i class="ti ti-map-pin"></i>
                    <?php echo ud_esc($addressStr ?: $locationStr); ?>
                </div>
                <button class="ud-map-modal-close" id="udMapModalClose">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="ud-map-modal-map" id="udMapModalMap"></div>
        </div>
    </div>

    <!-- ══ 2-COLUMN PAGE WRAP ══ -->
    <div class="ud-page-wrap">

        <!-- ══ LEFT COLUMN ══ -->
        <div class="ud-left">

            <!-- Gallery -->
            <div style="position:relative">
                <?php if (count($images) >= 3): ?>
                    <div class="ud-gallery-grid" id="udGalleryGrid">
                        <div class="ud-gallery-main-cell" style="position:relative;">
                            <img id="udMainGalleryImg" src="<?php echo ud_esc($images[0]); ?>"
                                alt="<?php echo ud_esc($unitTitle); ?>"
                                onerror="this.parentElement.classList.add('ud-img-error')" onclick="openLb(0)"
                                style="cursor:zoom-in;">
                            <button class="ud-main-nav ud-main-prev" id="udMainPrev" aria-label="Previous">
                                &#xea64;
                            </button>
                            <button class="ud-main-nav ud-main-next" id="udMainNext" aria-label="Next">
                                &#xea65;
                            </button>
                        </div>
                        <div class="ud-gallery-side">
                            <div class="ud-gallery-side-cell" id="udSideCell0" onclick="openLb(1)">
                                <img src="<?php echo ud_esc($images[1]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                                    onerror="this.parentElement.classList.add('ud-img-error')">
                            </div>
                            <div class="ud-gallery-side-cell" id="udSideCell1" onclick="openLb(2)">
                                <img src="<?php echo ud_esc($images[2]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                                    onerror="this.parentElement.classList.add('ud-img-error')">
                            </div>
                        </div>
                    </div>
                <?php elseif (count($images) === 2): ?>
                    <div class="ud-gallery-grid" style="grid-template-columns:1fr 1fr; height: 300px;">
                        <div class="ud-gallery-main-cell" style="border-radius:12px 0 0 12px; grid-row: 1;"
                            onclick="openLb(0)">
                            <img src="<?php echo ud_esc($images[0]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                                onerror="this.parentElement.classList.add('ud-img-error')">
                        </div>
                        <div class="ud-gallery-main-cell" style="border-radius:0 12px 12px 0; grid-row: 1;"
                            onclick="openLb(1)">
                            <img src="<?php echo ud_esc($images[1]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                                onerror="this.parentElement.classList.add('ud-img-error')">
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Single image fallback -->
                    <div class="ud-gallery-slider">
                        <div class="ud-gallery-track" id="udTrack">
                            <?php if (empty($images)): ?>
                                <div class="ud-gallery-slide ud-img-error"></div>
                            <?php else: ?>
                                <?php foreach ($images as $img): ?>
                                    <div class="ud-gallery-slide">
                                        <img src="<?php echo ud_esc($img); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                                            onerror="this.parentElement.classList.add('ud-img-error')">
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <button class="ud-main-nav ud-main-prev" id="udMainPrev" aria-label="Previous">
                                &#xea64;
                            </button>
                            <button class="ud-main-nav ud-main-next" id="udMainNext" aria-label="Next">
                                &#xea65;
                            </button>
                            <div class="ud-gdots" id="udDots">
                                <?php foreach ($images as $i => $_): ?>
                                    <button class="ud-gdot<?php echo $i === 0 ? ' active' : ''; ?>"
                                        data-idx="<?php echo $i; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Badges -->
                <div class="ud-gallery-badges">
                    <span class="ud-badge-type"><?php echo ud_esc(strtoupper($unit['unit_type'] ?? 'UNIT')); ?></span>
                    <span class="ud-badge-avail <?php echo $statusBadgeClass; ?>">
                        <?php echo $statusLabel; ?>
                    </span>
                </div>

                <!-- Save -->
                <button class="ud-gallery-save <?php echo $isSaved ? 'saved' : ''; ?>" id="udSaveBtn"
                    onclick="toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)">
                    <i class="ti <?php echo $isSaved ? 'ti-heart-filled' : 'ti-heart'; ?>"></i>
                </button>
            </div><!-- /gallery wrapper -->

            <!-- Price + CTA block -->
            <div class="ud-price-block">
                <div class="ud-price-top">
                    <div class="ud-price-left">
                        <div class="ud-price-label">Nightly rate</div>
                        <div class="ud-price-amount">
                            <?php echo $price; ?><sub>/night</sub>
                            <span
                                style="background:<?php echo $curColor; ?>20;color:<?php echo $curColor; ?>;font-size:11px;font-weight:700;padding:2px 10px;border-radius:99px;margin-left:8px;vertical-align:middle;">
                                <?php echo $curLabel; ?>
                            </span>
                        </div>
                        <div class="ud-price-meta">
                            <i class="ti ti-map-pin"></i>
                            <?php echo ud_esc($locationStr); ?>
                            <?php if ($addressStr): ?>
                                · <span style="color:#636b7d"><?php echo ud_esc($addressStr); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /ud-price-top -->

                <div class="ud-price-stats">
                    <span><i class="ti ti-bed"></i> <?php echo (int) ($unit['bedrooms'] ?? 2); ?>
                        Bed<?php echo (int) ($unit['bedrooms'] ?? 2) !== 1 ? 's' : ''; ?></span>
                    <span><i class="ti ti-bath"></i> <?php echo (int) ($unit['bathrooms'] ?? 1); ?>
                        Bath<?php echo (int) ($unit['bathrooms'] ?? 1) !== 1 ? 's' : ''; ?></span>
                    <?php if (!empty($unit['floor'])): ?>
                        <span><i class="ti ti-building"></i> Floor <?php echo (int) $unit['floor']; ?></span>
                    <?php endif; ?>
                    <span><i class="ti ti-users"></i> Up to <?php echo (int) ($unit['max_guests'] ?? 2); ?>
                        guest<?php echo (int) ($unit['max_guests'] ?? 2) !== 1 ? 's' : ''; ?></span>
                    <?php if ($ratingValue !== null): ?>
                        <span><i class="ti ti-star-filled" style="color:#d97706"></i>
                            <?php echo number_format($ratingValue, 1); ?> <span
                                style="color:var(--ud-text-soft);font-weight:400">(<?php echo $totalReviews; ?>
                                review<?php echo $totalReviews !== 1 ? 's' : ''; ?>)</span></span>
                    <?php endif; ?>
                </div>

            </div><!-- /ud-price-block -->

            <!-- Quick stats -->


            <!-- Info card -->
            <div class="ud-info-card">

                <!-- About -->
                <div class="ud-info-section">
                    <div class="ud-info-label">Property information</div>
                    <p class="ud-desc">
                        <?php echo nl2br(ud_esc($unit['description'] ?? 'A comfortable and well-appointed unit.')); ?>
                    </p>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                    <div class="ud-info-section">
                        <div class="ud-info-label">Amenities</div>
                        <div class="ud-amenities-grid">
                            <?php foreach ($amenities as $am): ?>
                                <div class="ud-amenity-chip">
                                    <i class="ti <?php echo getAmenityIcon($am); ?>"></i>
                                    <?php echo ud_esc($am); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($amenities) > 8): ?>
                            <button class="ud-show-more-amenities" id="udShowMoreAmenities">
                                Show all <?php echo count($amenities); ?> amenities
                                <i class="ti ti-chevron-down"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Nearby Attractions -->
                <div class="ud-info-section">
                    <div class="ud-info-label">
                        Nearby attractions
                        <span class="ud-nearby-radius-badge">within 1 km</span>
                    </div>
                    <div id="udNearbyList" class="ud-nearby-list">
                        <!-- skeleton -->
                        <div class="ud-nearby-skeleton"></div>
                        <div class="ud-nearby-skeleton"></div>
                        <div class="ud-nearby-skeleton"></div>
                    </div>
                </div>

                <!-- Reviews -->
                <div class="ud-info-section" id="reviews">
                    <div class="ud-info-label">
                        Guest Reviews
                        <?php if ($totalReviews > 0): ?>
                            <span style="background:rgba(76,175,133,0.15);color:#4caf85;font-size:0.58rem;
                                    padding:1px 7px;border-radius:99px;margin-left:6px;font-weight:700">
                                <?php echo $totalReviews; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($reviews)): ?>
                        <div class="ud-no-reviews">
                            <i class="ti ti-message-circle"></i>
                            <p>No reviews yet for this unit.</p>
                        </div>
                    <?php else: ?>

                        <!-- Rating breakdown -->
                        <?php if ($ratingValue !== null): ?>
                            <div class="ud-rating-summary">
                                <div class="ud-rating-big">
                                    <span class="ud-rating-num"><?php echo number_format((float) $ratingValue, 1); ?></span>
                                    <div>
                                        <div class="ud-rating-stars-lg">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <span
                                                    class="<?php echo $s <= round((float) $ratingValue) ? 'sf' : 'se'; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="ud-rating-total">
                                            <?php echo $totalReviews; ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ud-rating-bars">
                                    <?php
                                    foreach ($catAverages as $cat):
                                        $pct = $cat['avg'] > 0 ? round(($cat['avg'] / 5) * 100) : 0; ?>
                                        <div class="ud-rbar-row">
                                            <span class="ud-rbar-label">
                                                <span
                                                    style="margin-right:4px;"><?php echo $cat['icon']; ?></span><?php echo $cat['label']; ?>
                                            </span>
                                            <div class="ud-rbar-track">
                                                <div class="ud-rbar-fill" style="width:<?php echo $pct; ?>%"></div>
                                            </div>
                                            <span
                                                class="ud-rbar-pct"><?php echo $cat['avg'] > 0 ? number_format($cat['avg'], 1) : '–'; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="ud-reviews-list">
                            <?php foreach ($reviews as $rv): ?>
                                <div class="ud-review-card">
                                    <div class="ud-rv-top">
                                        <div class="ud-rv-avatar">
                                            <?php echo strtoupper(mb_substr($rv['reviewer'], 0, 1)); ?>
                                        </div>
                                        <div class="ud-rv-info">
                                            <div class="ud-rv-name"><?php echo ud_esc($rv['reviewer']); ?></div>
                                            <div class="ud-rv-date">
                                                <?php echo date('F Y', strtotime($rv['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="ud-rv-stars">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <span class="<?php echo $s <= (int) $rv['rating'] ? 'sf' : 'se'; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($rv['comment'])): ?>
                                        <p class="ud-rv-body"><?php echo ud_esc($rv['comment']); ?></p>
                                    <?php endif; ?>
                                    <?php
                                    $rvCats = [
                                        ['label' => 'Cleanliness', 'val' => $rv['cleanliness'] ?? null],
                                        ['label' => 'Location', 'val' => $rv['location_rating'] ?? null],
                                        ['label' => 'Value', 'val' => $rv['value_rating'] ?? null],
                                        ['label' => 'Comfort', 'val' => $rv['comfort'] ?? null],
                                    ];
                                    $hasAnyCat = array_filter($rvCats, fn($c) => $c['val'] !== null);
                                    if ($hasAnyCat): ?>
                                        <div class="ud-rv-cats">
                                            <?php foreach ($rvCats as $c):
                                                if ($c['val'] === null)
                                                    continue;
                                                $v = round((float) $c['val']); ?>
                                                <div class="ud-rv-cat-pill">
                                                    <span class="ud-rv-cat-label"><?php echo $c['label']; ?></span>
                                                    <span class="ud-rv-cat-stars">
                                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                                            <span class="<?php echo $s <= $v ? 'sf' : 'se'; ?>">★</span>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span
                                                        class="ud-rv-cat-num"><?php echo number_format((float) $c['val'], 1); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalReviewPages > 1): ?>
                            <div class="ud-reviews-pager">
                                <?php if ($reviewPage > 1): ?>
                                    <a href="?id=<?php echo $unit_id; ?>&rp=<?php echo $reviewPage - 1; ?>#reviews"
                                        class="ud-pager-btn">
                                        <i class="ti ti-arrow-left"></i> Prev
                                    </a>
                                <?php endif; ?>
                                <span class="ud-pager-label">
                                    Page <?php echo $reviewPage; ?> of <?php echo $totalReviewPages; ?>
                                </span>
                                <?php if ($reviewPage < $totalReviewPages): ?>
                                    <a href="?id=<?php echo $unit_id; ?>&rp=<?php echo $reviewPage + 1; ?>#reviews"
                                        class="ud-pager-btn">
                                        Next <i class="ti ti-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>

            </div><!-- /ud-info-card -->

        </div><!-- /ud-left -->

        <!-- ══ RIGHT COLUMN ══ -->
        <div class="ud-right">

            <!-- Card 1: Booking -->
            <div class="ud-booking-card" id="udBookingCard">
                <div class="ud-bc-header">
                    <div class="ud-bc-price">
                        <?php echo $price; ?><span class="ud-bc-per"> / night</span>
                        <span
                            style="background:<?php echo $curColor; ?>20;color:<?php echo $curColor; ?>;font-size:11px;font-weight:700;padding:2px 10px;border-radius:99px;margin-left:8px;vertical-align:middle;">
                            <?php echo $curLabel; ?>
                        </span>
                    </div>
                    <?php if ($ratingValue !== null): ?>
                        <div class="ud-bc-rating">
                            <span class="ud-bc-stars">★★★★★</span>
                            <strong><?php echo number_format($ratingValue, 1); ?></strong>
                            <span>· <?php echo $totalReviews; ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="ud-bc-rating">
                            <span style="opacity:0.5;font-size:10px;">★★★★★</span>
                            <span style="font-size:0.65rem;opacity:0.6;margin-left:4px;">No ratings yet</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ud-bc-body">
                    <div class="ud-date-grid">
                        <div class="ud-date-cell ud-date-in">
                            <label for="udCheckin">Check-in</label>
                            <input type="text" id="udCheckin" placeholder="Select date" readonly>
                        </div>
                        <div class="ud-date-cell ud-date-out">
                            <label for="udCheckout">Check-out</label>
                            <input type="text" id="udCheckout" placeholder="Select date" readonly>
                        </div>
                        <div class="ud-date-cell ud-date-guests">
                            <div>
                                <label>Guests</label>
                                <div
                                    style="font-family:'DM Sans',sans-serif;font-size:.8rem;font-weight:600;color:#0c1a2e">
                                    <span id="udGCount">2</span> guest<span id="udGPlural">s</span>
                                </div>
                            </div>
                            <div class="ud-guests-ctl">
                                <button class="ud-g-btn" id="udGMinus">−</button>
                                <button class="ud-g-btn" id="udGPlus">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="ud-avail-note<?php echo !$isVacant ? ' unavail' : ''; ?>" id="udAvailNote">
                        <?php if ($isVacant): ?>
                            <i class="ti ti-circle-check"></i>
                            <span>Available from <?php echo date('M j, Y'); ?>. Deposit is 50% of stay.</span>
                        <?php elseif ($isBooked): ?>
                            <i class="ti ti-calendar-event"></i>
                            <span>This unit has been booked and is awaiting check-in.</span>
                        <?php elseif ($isOccupied): ?>
                            <i class="ti ti-home"></i>
                            <span>This unit is currently occupied by a guest.</span>
                        <?php elseif ($isMaintenance): ?>
                            <i class="ti ti-tool"></i>
                            <span>This unit is under maintenance.</span>
                        <?php else: ?>
                            <i class="ti ti-circle-x"></i>
                            <span>Unit is currently <?php echo ud_esc($unit['status']); ?>.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Minimum stay + cancellation policy -->
                    <div class="ud-policy-row">
                        <div class="ud-policy-item">
                            <i class="ti ti-moon"></i>
                            <div>
                                <div class="ud-policy-label">Minimum stay</div>
                                <div class="ud-policy-val">3 nights</div>
                            </div>
                        </div>
                        <div class="ud-policy-item">
                            <i class="ti ti-shield-check"></i>
                            <div>
                                <div class="ud-policy-label">Cancellation</div>
                                <div class="ud-policy-val">Free · 48h before</div>
                            </div>
                        </div>
                    </div>

                    <div class="ud-price-breakdown" id="udPriceBreakdown" style="display:none">
                        <div id="udSeasonRows"></div>
                        <div class="ud-pb-divider"></div>
                        <div class="ud-pb-row ud-pb-demand">
                            <span id="udDemandBadge"></span>
                        </div>
                        <div class="ud-pb-total"><span>Total</span><span id="udTotalDue">—</span></div>
                    </div>

                    <!-- Social proof nudge -->
                    <?php if ($bookingCount >= 5): ?>
                        <div class="ud-social-nudge">
                            <i class="ti ti-flame"></i>
                            <span>Booked <strong><?php echo $bookingCount; ?> times</strong> — popular stay!</span>
                        </div>
                    <?php elseif ($isVacant && $bookingCount >= 2): ?>
                        <div class="ud-social-nudge ud-social-nudge--rare"
                            onclick="document.getElementById('reviews').scrollIntoView({behavior:'smooth'})"
                            style="cursor:pointer;">
                            <i class="ti ti-star"></i>
                            <span><strong>Rare find</strong> — this unit is usually taken.
                                <span style="text-decoration:underline;opacity:0.8;">See reviews</span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($isVacant && !$hasActiveBooking): ?>
                        <button class="ud-book-btn" id="udBookBtn2"
                            onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                            Book Now
                        </button>
                    <?php elseif ($hasActiveBooking): ?>
                        <button class="ud-book-btn" disabled
                            style="background:#112240!important;color:#e8c882!important;cursor:default;">
                            <i class="ti ti-circle-check"></i> Already Booked
                        </button>
                    <?php elseif ($isBooked || $isOccupied): ?>
                        <button class="ud-book-btn" id="udBookBtn2"
                            onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                            <i class="ti ti-calendar-plus"></i> Book a Future Date
                        </button>
                    <?php elseif ($isMaintenance): ?>
                        <button class="ud-book-btn ud-book-btn--disabled" disabled
                            style="background:#9ca3af!important;color:#fff!important;">
                            <i class="ti ti-tool"></i> Under Maintenance
                        </button>
                    <?php else: ?>
                        <button class="ud-book-btn ud-book-btn--disabled" disabled>
                            Unavailable
                        </button>
                    <?php endif; ?>

                    <p class="ud-book-note">
                        <i class="ti ti-shield-check"></i>
                        No charge yet — holds for 30 minutes
                    </p>
                </div>
                <div class="ud-bc-actions">
                    <button class="ud-bc-action" onclick="shareUnit()">
                        <i class="ti ti-share"></i> Share
                    </button>
                    <button class="ud-bc-action <?php echo $isSaved ? 'saved' : ''; ?>" id="udSaveBtn2"
                        onclick="toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)">
                        <i class="ti <?php echo $isSaved ? 'ti-heart-filled' : 'ti-heart'; ?>"></i>
                        <span id="udSaveLabel2"><?php echo $isSaved ? 'Saved' : 'Save'; ?></span>
                    </button>
                </div>
            </div>

            <!-- Card 2: Location / Map -->
            <?php if (!empty($unit['latitude']) && (float) $unit['latitude'] !== 0.0): ?>
                <div class="ud-side-card">
                    <span class="ud-side-label">Location</span>
                    <?php if ($addressStr): ?>
                        <div class="ud-map-addr">
                            <i class="ti ti-map-pin"></i>
                            <?php echo ud_esc($addressStr); ?>
                        </div>
                    <?php endif; ?>
                    <div class="ud-map-wrap" style="position: relative;">
                        <div id="udLeafletMap"></div>
                        <button class="ud-map-view-btn" onclick="window.openMapModal()">
                            <i class="ti ti-maximize"></i>
                            View Map
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /ud-right -->

    </div><!-- /ud-page-wrap -->

    <!-- SIMILAR UNITS -->
    <?php if (!empty($similarUnits)): ?>
        <section class="ud-similar-section">
            <div class="ud-similar-inner">
                <div class="ud-similar-header">
                    <div class="ud-similar-header-left">
                        <span class="ud-similar-eyebrow">Explore more</span>
                        <h2 class="ud-similar-title">More units you might like</h2>
                    </div>
                    <a href="user-dashboard.php#browse" class="ud-similar-all">
                        Browse all <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
                <div class="ud-similar-grid">
                    <?php foreach ($similarUnits as $su):
                        $suImg = !empty($su['img']) ? '../../' . ltrim($su['img'], '/') : '';
                        $suTitle = !empty($su['unit_name']) ? $su['unit_name']
                            : ($su['property_name'] . ' — Unit ' . preg_replace('/^unit\s*/i', '', $su['unit_number'] ?? ''));
                        $suPrice = '₱' . number_format((float) $su['rent_amount'], 0);
                        ?>
                        <a href="unit_detail.php?id=<?php echo (int) $su['unit_id']; ?>" class="ud-similar-card">
                            <div class="ud-similar-img-wrap">
                                <?php if ($suImg): ?>
                                    <img src="<?php echo ud_esc($suImg); ?>" alt="<?php echo ud_esc($suTitle); ?>"
                                        onerror="this.parentElement.classList.add('ud-img-error')">
                                <?php else: ?>
                                    <div class="ud-similar-img-placeholder"><i class="ti ti-building"></i></div>
                                <?php endif; ?>
                                <span
                                    class="ud-similar-badge <?php echo $su['status'] === 'vacant' ? 'avail-yes' : 'avail-no'; ?>">
                                    <?php echo $su['status'] === 'vacant' ? '✓ Available' : ucfirst($su['status']); ?>
                                </span>
                            </div>
                            <div class="ud-similar-info">
                                <div class="ud-similar-name"><i class="ti ti-map-pin"></i><?php echo ud_esc($suTitle); ?></div>
                                <div class="ud-similar-meta">
                                    <span><i class="ti ti-users"></i> <?php echo (int) ($su['max_guests'] ?? 2); ?> Guests
                                        max</span>
                                </div>
                                <?php if (!empty($su['description'])): ?>
                                    <div class="ud-similar-desc"><?php echo ud_esc(mb_substr($su['description'], 0, 90)); ?>...
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($su['amenities_list'])): ?>
                                    <div class="ud-similar-amenities">
                                        <?php foreach (array_slice(explode('||', $su['amenities_list']), 0, 3) as $am): ?>
                                            <span class="ud-similar-amenity-chip">
                                                <i class="ti <?php echo getAmenityIcon(trim($am)); ?>"></i>
                                                <?php echo ud_esc(trim($am)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ud-similar-footer">
                                    <div class="ud-similar-price"><?php echo $suPrice; ?><span>/night</span></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- TOAST -->
    <div id="toast" role="status" aria-live="polite" style="position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(80px);
                background:#282c35;border:1px solid rgba(255,255,255,0.1);color:#e8eaf0;
                padding:10px 20px;border-radius:40px;font-family:'DM Sans',sans-serif;
                font-size:.8rem;font-weight:500;box-shadow:0 6px 24px rgba(0,0,0,.4);z-index:600;
                transition:transform .38s cubic-bezier(.4,0,.2,1),opacity .38s;
                opacity:0;white-space:nowrap;display:flex;align-items:center;gap:8px;">
        <i class="ti ti-check" style="font-size:13px;color:#4caf85"></i>
        <span id="toastMsg"></span>
    </div>

    <!-- MOBILE + DESKTOP STICKY FLOAT BAR -->
    <div class="ud-float-bar" id="udFloatBar">
        <div class="ud-float-left">
            <div class="ud-float-price">
                <?php echo $price; ?><sub>/night</sub>
                <span
                    style="background:<?php echo $curColor; ?>20;color:<?php echo $curColor; ?>;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;margin-left:6px;vertical-align:middle;">
                    <?php echo $curLabel; ?>
                </span>
            </div>
            <div class="ud-float-dates" id="udFloatDates">Select dates to see total</div>
        </div>
        <div class="ud-float-right">
            <button class="ud-float-share" onclick="shareUnit()" title="Share">
                <i class="ti ti-share"></i>
            </button>
            <?php if ($isVacant && !$hasActiveBooking): ?>
                <button class="ud-float-btn" id="udFloatBtn"
                    onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                    Book Now
                </button>
            <?php elseif ($hasActiveBooking): ?>
                <button class="ud-float-btn" disabled style="background:#112240;color:#e8c882;">Booked</button>
            <?php elseif ($isBooked || $isOccupied): ?>
                <button class="ud-float-btn" id="udFloatBtn"
                    onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                    Book Future Date
                </button>
            <?php elseif ($isMaintenance): ?>
                <button class="ud-float-btn" disabled style="background:#9ca3af">Maintenance</button>
            <?php else: ?>
                <button class="ud-float-btn" disabled>Unavailable</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOOKING MODAL -->
    <div class="bm-overlay" id="bmOverlay">
        <div class="bm-box" id="bmBox">
            <button class="bm-close" id="bmClose" onclick="closeBookingModal()">✕</button>
            <div class="bm-content">
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
                <div class="bm-panels-wrap">
                    <div class="bm-panel active" id="bm-panel-1">
                        <div class="bm-panel-title">Tenant information</div>
                        <div class="bm-panel-sub">We'll use these details for your reservation.</div>
                        <div class="bm-row">
                            <div class="bm-field"><label>First name</label><input type="text" id="bm-fname"
                                    placeholder="Ana" autocomplete="given-name"></div>
                            <div class="bm-field"><label>Last name</label><input type="text" id="bm-lname"
                                    placeholder="Jimenez" autocomplete="family-name"></div>
                        </div>
                        <div class="bm-row full">
                            <div class="bm-field"><label>Email</label><input type="email" id="bm-email"
                                    placeholder="ana@email.com" autocomplete="email"></div>
                        </div>
                        <div class="bm-row full">
                            <div class="bm-field"><label>Contact number</label><input type="tel" id="bm-phone"
                                    placeholder="+63 912 345 6789" autocomplete="tel"></div>
                        </div>
                        <div class="bm-row">
                            <div class="bm-field"><label>Check-in</label><input type="text" id="bm-checkin"
                                    placeholder="Select date" readonly></div>
                            <div class="bm-field"><label>Check-out</label><input type="text" id="bm-lease"
                                    placeholder="Select date" readonly></div>
                        </div>
                        <div class="fp-legend">
                            <span><span class="fp-legend-dot avail"></span> Selected</span>
                            <span><span class="fp-legend-dot inrange"></span> Stay range</span>
                            <span><span class="fp-legend-dot booked"></span> Already booked</span>
                        </div>
                    </div>
                    <input type="hidden" id="bm-computed-total" value="0">
                    <div class="bm-panel" id="bm-panel-2">
                        <div class="bm-panel-title">Review your booking</div>
                        <div class="bm-panel-sub">Check all details before proceeding.</div>
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
                                    class="bm-review-val" id="rv-unit">—</span>
                            </div>
                            <div class="bm-review-row"><span class="bm-review-key">Check-in</span><span
                                    class="bm-review-val" id="rv-movein">—</span>
                            </div>
                            <div class="bm-review-row"><span class="bm-review-key">Check-out</span><span
                                    class="bm-review-val" id="rv-checkout">—</span>
                            </div>
                            <div class="bm-review-row"><span class="bm-review-key">Nights</span><span
                                    class="bm-review-val" id="rv-nights">—</span>
                            </div>
                            <div class="bm-review-row"><span class="bm-review-key">Price/night</span><span
                                    class="bm-review-val" id="rv-rent">—</span>
                            </div>
                            <div class="bm-review-row" id="rv-season-row" style="display:none">
                                <span class="bm-review-key">Season</span>
                                <span class="bm-review-val" id="rv-season">—</span>
                            </div>
                        </div>
                        <div class="bm-review-block">
                            <div class="bm-review-label">Charges due today</div>
                            <div class="bm-review-row" style="padding-top:8px">
                                <span class="bm-review-key" style="font-weight:700;color:var(--text-dark)">Total due
                                    now</span>
                                <span class="bm-review-val" id="rv-total"
                                    style="color:var(--teal);font-size:1.05rem">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="bm-panel" id="bm-panel-3">
                        <div class="bm-panel-title">Payment method</div>
                        <div class="bm-panel-sub">Choose how to pay the amount due today.</div>
                        <div class="bm-pay-methods" id="bmPayMethods">
                            <div class="bm-pay-option selected" data-method="GCash">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/gcash.png" alt="GCash">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">GCash</div>
                                    <div class="bm-pay-desc">Pay via GCash transfer</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Maya">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/maya1.png" alt="Maya">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Maya</div>
                                    <div class="bm-pay-desc">Pay via Maya transfer</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Bank">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/mobile-banking.png"
                                        alt="Bank"></div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Bank Transfer</div>
                                    <div class="bm-pay-desc">Transfer via online banking</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Cash">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/dollar.png" alt="Cash">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Cash (On-site)</div>
                                    <div class="bm-pay-desc">Pay at check-in</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bm-panel" id="bm-panel-4">
                        <div class="bm-confirm-check" id="bm-payment-waiting">
                            <div class="bm-check-ring" style="border-color:#4caf85;animation:none">
                                <svg viewBox="0 0 24 24" style="stroke:#4caf85">
                                    <circle cx="12" cy="12" r="10" stroke-width="2" fill="none" />
                                    <polyline points="12 6 12 12 16 14" stroke-width="2" />
                                </svg>
                            </div>
                            <div class="bm-confirm-title">Complete your payment</div>
                            <div class="bm-confirm-sub">A PayMongo payment page has opened in a new tab.</div>
                            <div class="bm-confirm-ref" id="bmConfirmRef">Ref #BK-0000</div>
                            <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px;align-items:center">
                                <button class="bm-btn bm-btn-confirm" id="bmReopenPayBtn" style="display:none"
                                    onclick="bmReopenPaymongoTab()">Reopen payment page</button>
                                <div id="bmPaymentPolling"
                                    style="font-size:.8rem;color:var(--ink-soft);display:flex;align-items:center;gap:6px">
                                    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none"
                                        style="width:14px;height:14px;stroke-width:2;animation:spin 1.2s linear infinite">
                                        <circle cx="12" cy="12" r="10" stroke-opacity=".3" />
                                        <path d="M12 2a10 10 0 0110 10" />
                                    </svg>
                                    Waiting for payment confirmation…
                                </div>
                            </div>
                        </div>
                        <div class="bm-confirm-check" id="bm-payment-success" style="display:none">
                            <div class="bm-check-ring"><svg viewBox="0 0 24 24">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg></div>
                            <div class="bm-confirm-title">Payment confirmed!</div>
                            <div class="bm-confirm-sub">Your booking is confirmed.</div>
                            <div class="bm-confirm-details">
                                <div class="bm-confirm-row"><span>Unit</span><span id="cf-unit">—</span></div>
                                <div class="bm-confirm-row"><span>Check-in</span><span id="cf-movein">—</span></div>
                                <div class="bm-confirm-row"><span>Check-out</span><span id="cf-checkout">—</span></div>
                                <div class="bm-confirm-row"><span>Payment</span><span id="cf-method">—</span></div>
                                <div class="bm-confirm-row"><span>Total paid</span><span id="cf-total"
                                        style="color:var(--teal)">—</span></div>
                            </div>
                        </div>
                        <div class="bm-confirm-check" id="bm-payment-cash" style="display:none">
                            <div class="bm-check-ring"><svg viewBox="0 0 24 24">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg></div>
                            <div class="bm-confirm-title">Booking submitted!</div>
                            <div class="bm-confirm-sub">We'll confirm within 24 hours.</div>
                            <div class="bm-confirm-details">
                                <div class="bm-confirm-row"><span>Unit</span><span id="cf-unit-cash">—</span></div>
                                <div class="bm-confirm-row"><span>Check-in</span><span id="cf-movein-cash">—</span>
                                </div>
                                <div class="bm-confirm-row"><span>Check-out</span><span id="cf-checkout-cash">—</span>
                                </div>
                                <div class="bm-confirm-row"><span>Payment</span><span id="cf-method-cash">—</span></div>
                                <div class="bm-confirm-row"><span>Total due</span><span id="cf-total-cash"
                                        style="color:var(--teal)">—</span></div>
                            </div>
                        </div>
                        <div class="bm-confirm-check" id="bm-payment-failed" style="display:none">
                            <div class="bm-check-ring" style="border-color:#ef4444;animation:none">
                                <svg viewBox="0 0 24 24" style="stroke:#ef4444">
                                    <circle cx="12" cy="12" r="10" stroke-width="2" fill="none" />
                                    <line x1="15" y1="9" x2="9" y2="15" stroke-width="2" />
                                    <line x1="9" y1="9" x2="15" y2="15" stroke-width="2" />
                                </svg>
                            </div>
                            <div class="bm-confirm-title" style="color:#ef4444">Payment failed</div>
                            <div class="bm-confirm-sub">Please try again.</div>
                            <div class="bm-confirm-ref" id="bmFailedRef">Ref #BK-0000</div>
                        </div>
                        <div class="bm-confirm-check" id="bm-payment-expired" style="display:none">
                            <div class="bm-check-ring" style="border-color:#c9a84c;animation:none">
                                <svg viewBox="0 0 24 24" style="stroke:#c9a84c">
                                    <circle cx="12" cy="12" r="10" stroke-width="2" fill="none" />
                                    <polyline points="12 6 12 12 16 14" stroke-width="2" />
                                </svg>
                            </div>
                            <div class="bm-confirm-title" style="color:#c9a84c">Payment link expired</div>
                            <div class="bm-confirm-sub">Your 30-minute hold has expired. Please start a new booking.
                            </div>
                            <div class="bm-confirm-ref" id="bmExpiredRef">Ref #BK-0000</div>
                        </div>
                    </div>
                </div>
                <div class="bm-sidebar">
                    <div class="bm-unit-card">
                        <div class="bm-unit-img-fallback" id="bmUnitImgWrap">
                            <img id="bmUnitImg" class="bm-unit-img" src="" alt="" style="display:none"
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
                                class="bm-summary-val" id="sb-rent">—</span>
                        </div>
                    </div>
                    <div class="bm-summary-divider"></div>
                    <div class="bm-total-row"><span class="bm-total-label">Total due now</span><span
                            class="bm-total-amount" id="sb-total">—</span>
                    </div>
                    <div style="font-size:0.72rem;color:#6b7280;margin-top:8px;font-style:italic">Price seasonality
                        applies</div>
                    <div id="sb-season-breakdown" style="margin-top:4px"></div>
                    <div class="bm-hold-notice">Your booking is held for <strong>30 minutes</strong>.</div>
                </div>
                <div class="bm-footer-wrap" id="bmFooter">
                    <button class="bm-btn bm-btn-back" id="bmBack" onclick="bmPrevStep()" style="display:none">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>Back
                    </button>
                    <div style="flex:1"></div>
                    <button class="bm-btn bm-btn-next" id="bmNext" onclick="bmNextStep()">Continue<svg
                            viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg></button>
                    <button class="bm-btn bm-btn-confirm" id="bmConfirmBtn" style="display:none"
                        onclick="bmSubmitBooking()"><svg viewBox="0 0 24 24" stroke="currentColor" fill="none"
                            stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>Confirm Payment</button>
                    <button class="bm-btn bm-btn-next" id="bmDoneBtn" style="display:none"
                        onclick="closeBookingModal();window.location.href='bookings.php'">Done<svg viewBox="0 0 24 24"
                            stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        window.PS_POPULAR_PAYMENT = <?php echo json_encode($popularPaymentMethod); ?>;
        window.UD_ROOM_DATA = <?php echo $roomJs; ?>;
        window.UD_BOOKED_RANGES = <?php echo json_encode($bookedRanges, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        window.UD_BLOCKED_DATES = <?php echo json_encode($adminBlockedDates, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        window._psSessionFields = {
            fname: <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>,
            lname: <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>,
            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
            phone: <?php echo json_encode($_SESSION['phone'] ?? ''); ?>,
            idVerified: <?php echo json_encode($_SESSION['id_verified'] ?? 'none'); ?>,
        };
        window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        window.psGetCsrfToken = () => String(window.PS_CSRF_TOKEN || '');
        window.psAppendCsrf = t => { const k = window.psGetCsrfToken(); if (k && t?.append) t.append('csrf_token', k); return t; };
        window.PS_RT_PAGE = 'unit_detail'; window.PS_RT_ROLE = 'user'; window.PS_RT_API = '../../api/realtime.php';
        window.UD_IMAGES = <?php echo json_encode($images, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.UD_UNIT = {
            lat: <?php echo (float) ($unit['latitude'] ?? 0); ?>,
            lng: <?php echo (float) ($unit['longitude'] ?? 0); ?>,
            priceNum: <?php echo (float) $unit['rent_amount']; ?>,
            maxGuests: <?php echo (int) ($unit['max_guests'] ?? 6); ?>,
            seasonality: { 0: 1.30, 1: 1.30, 2: 1.10, 3: 1.15, 4: 1.15, 5: 0.80, 6: 0.80, 7: 0.80, 8: 0.80, 9: 0.80, 10: 1.15, 11: 1.30 },
            seasonLabel: { 0: 'Peak', 1: 'Peak', 2: 'High', 3: 'High', 4: 'High', 5: 'Low', 6: 'Low', 7: 'Low', 8: 'Low', 9: 'Low', 10: 'High', 11: 'Peak' },
        };
        fetch('../../api/user/sync_unit_statuses.php').catch(() => { });
    </script>

    <script>
        (function () {
            const lat = <?php echo json_encode((float) ($unit['latitude'] ?? 0)); ?>;
            const lng = <?php echo json_encode((float) ($unit['longitude'] ?? 0)); ?>;

            const CATEGORIES = [
                { amenity: 'restaurant', label: 'Dining', icon: 'ti-tools-kitchen-2' },
                { amenity: 'cafe', label: 'Café', icon: 'ti-coffee' },
                { amenity: 'bar', label: 'Bar', icon: 'ti-glass-full' },
                { amenity: 'bank', label: 'Bank', icon: 'ti-building-bank' },
                { amenity: 'atm', label: 'ATM', icon: 'ti-credit-card' },
                { amenity: 'pharmacy', label: 'Pharmacy', icon: 'ti-pill' },
                { amenity: 'hospital', label: 'Hospital', icon: 'ti-building-hospital' },
                { amenity: 'supermarket', label: 'Grocery', icon: 'ti-shopping-cart' },
                { amenity: 'school', label: 'School', icon: 'ti-school' },
                { amenity: 'place_of_worship', label: 'Church', icon: 'ti-building-church' },
                { tourism: 'attraction', label: 'Attraction', icon: 'ti-star' },
                { tourism: 'hotel', label: 'Hotel', icon: 'ti-building' },
                { leisure: 'park', label: 'Park', icon: 'ti-trees' },
                { leisure: 'beach', label: 'Beach', icon: 'ti-wave-saw-tool' },
            ];

            function getLabel(tags) {
                for (const cat of CATEGORIES) {
                    const key = cat.amenity ? 'amenity' : cat.tourism ? 'tourism' : 'leisure';
                    const val = cat.amenity || cat.tourism || cat.leisure;
                    if (tags[key] === val) return { label: cat.label, icon: cat.icon };
                }
                return { label: 'Place', icon: 'ti-map-pin' };
            }

            function metersToText(m) {
                if (m < 100) return `${Math.round(m)}m · 1 min walk`;
                if (m < 500) return `~${Math.round(m)}m · ${Math.round(m / 80)} min walk`;
                if (m < 2000) return `~${(m / 1000).toFixed(1)} km · ${Math.round(m / 80)} min walk`;
                return `~${(m / 1000).toFixed(1)} km · ${Math.round(m / 400)} min drive`;
            }

            function haversine(lat1, lng1, lat2, lng2) {
                const R = 6371000, r = Math.PI / 180;
                const dLat = (lat2 - lat1) * r, dLng = (lng2 - lng1) * r;
                const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * r) * Math.cos(lat2 * r) * Math.sin(dLng / 2) ** 2;
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }

            function renderNearby(places, refLat, refLng) {
                const list = document.getElementById('udNearbyList');
                if (!places.length) {
                    list.innerHTML = '<p class="ud-nearby-empty">No nearby places found.</p>';
                    return;
                }
                list.innerHTML = places.map(p => {
                    const name = p.tags.name || p.tags['name:en'] || 'Unnamed Place';
                    const { label, icon } = getLabel(p.tags);
                    const dist = haversine(refLat, refLng, p.lat ?? p.center?.lat, p.lon ?? p.center?.lon);
                    return `
            <div class="ud-nearby-item">
                <span class="ud-nearby-icon"><i class="ti ${icon}"></i></span>
                <div class="ud-nearby-body">
                    <div class="ud-nearby-name">${name}</div>
                    <div class="ud-nearby-dist">${metersToText(dist)}</div>
                </div>
                <span class="ud-nearby-tag">${label}</span>
            </div>`;
                }).join('');
            }

            const radius = 1000;
            const address = <?php echo json_encode(trim(($unit['address'] ?? '') . ' ' . ($unit['city'] ?? ''))); ?>;

            function doNearbyFetch(refLat, refLng) {
                const query = `[out:json][timeout:10];(node(around:${radius},${refLat},${refLng})[amenity~"restaurant|cafe|bar|bank|atm|pharmacy|hospital|supermarket|school|place_of_worship"];node(around:${radius},${refLat},${refLng})[tourism~"attraction|hotel"];node(around:${radius},${refLat},${refLng})[leisure~"park|beach"];way(around:${radius},${refLat},${refLng})[amenity~"restaurant|cafe|bar|bank|pharmacy|hospital|supermarket"];way(around:${radius},${refLat},${refLng})[leisure~"park|beach"];);out center 20;`;
                fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: 'data=' + encodeURIComponent(query) })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.elements) throw new Error('no elements');
                        const named = data.elements.filter(e => e.tags?.name || e.tags?.['name:en']);
                        const seen = new Set();
                        const unique = named.filter(e => {
                            const n = (e.tags.name || e.tags['name:en']).toLowerCase();
                            if (seen.has(n)) return false;
                            seen.add(n);
                            return true;
                        });
                        unique.sort((a, b) => {
                            const da = haversine(refLat, refLng, a.lat ?? a.center?.lat, a.lon ?? a.center?.lon);
                            const db = haversine(refLat, refLng, b.lat ?? b.center?.lat, b.lon ?? b.center?.lon);
                            return da - db;
                        });
                        renderNearby(unique.slice(0, 8), refLat, refLng);
                    })
                    .catch(() => {
                        document.getElementById('udNearbyList').innerHTML =
                            '<p class="ud-nearby-empty">Could not load nearby places.</p>';
                    });
            }

            if (lat && lng) {
                doNearbyFetch(lat, lng);
            } else if (address) {
                fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`, {
                    headers: { 'User-Agent': 'PropSight/1.0' }
                })
                    .then(r => r.json())
                    .then(results => {
                        if (!results.length) throw new Error('no results');
                        doNearbyFetch(parseFloat(results[0].lat), parseFloat(results[0].lon));
                    })
                    .catch(() => {
                        document.getElementById('udNearbyList').innerHTML =
                            '<p class="ud-nearby-empty">📍 No location set for this property.</p>';
                    });
            } else {
                document.getElementById('udNearbyList').innerHTML =
                    '<p class="ud-nearby-empty">📍 No location set for this property.</p>';
            }
        })();
    </script>
    <script src="../../assets/js/user-js/script.js"></script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/user-js/saved.js"></script>
    <script src="../../assets/js/user-js/unit_detail_additions.js"></script>
</body>

</html>