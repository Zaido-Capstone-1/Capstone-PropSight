<?php
/**
 * Unit Detail Page — PropSight
 * Path: pages/user/unit_detail.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
include '../../includes/session.php';
require_not_blacklisted(false);

if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html><html><body><script>setTimeout(() => history.back(), 2000);</script></body></html>';
    exit;
}

require_once '../../lib/user-queries/unit_detail_queries.php';

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
        'refrigerator' => 'ti-device-floppy',
        'shower' => 'ti-droplets',
        'cable' => 'ti-device-tv',
        'heater' => 'ti-flame',
    ];
    $lower = strtolower($name);
    foreach ($map as $key => $icon) {
        if (str_contains($lower, trim($key)))
            return $icon;
    }
    return 'ti-circle-check';
}

// Title
$rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));
$unitTitle = !empty($unit['unit_name'])
    ? $unit['unit_name']
    : (!empty($unit['property_name']) && !empty($rawNum)
        ? $unit['property_name'] . ' — Unit ' . $rawNum
        : ($unit['unit_number'] ?? 'Unit #' . $unit_id));

$isVacant = $unit['status'] === 'vacant';
$priceNum = (float) $unit['rent_amount'];
$price = '₱' . number_format($priceNum, 0);
$ratingValue = isset($unit['rating']) && $unit['rating'] !== null
    ? round((float) $unit['rating'], 1) : null;
$cityPart = !empty($unit['city']) ? ', ' . $unit['city'] : '';
$locationStr = ($unit['property_name'] ?? '') . $cityPart;
$addressStr = trim(($unit['address'] ?? '') . $cityPart);

// JS payload for booking modal
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

// Session
$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$dashboardPhotoRaw = trim((string) ($_SESSION['profile_photo'] ?? ''));
$dashboardPhoto = $dashboardPhotoRaw !== '' ? '../../' . ltrim($dashboardPhotoRaw, '/') : '';

$top_nav_items = require '../../includes/user_top_nav.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ud_esc($unitTitle); ?> — PropSight</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet">

    <!-- Tabler Icons — required for amenity chips, breadcrumb, booking card icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <link rel="stylesheet" href="../../assets/css/user-css/layout.css">
    <link rel="stylesheet" href="../../assets/css/user-css/styles.css">
    <link rel="stylesheet" href="../../assets/css/user-css/user-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-css/unit_detail.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<body>

    <!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
    <header id="hdr">
        <a href="user-dashboard.php" class="logo">
            <img src="../../assets/images/logo.png" alt="PropSight Logo" class="logo-icon">
            <span style="font-family:'Cormorant Garamond',serif;font-weight:700;line-height:1.1;display:block;">
                Boracay <span class="brand-break">Accommodation</span>
            </span>
        </a>
        <nav>
            <?php foreach ($top_nav_items as $item): ?>
                <a href="<?php echo ud_esc($item['href']); ?>"><?php echo ud_esc($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <a href="user-dashboard.php#browse" class="btn-browse">Browse Rooms</a>
            <div class="btn-profile-wrap">
                <button class="btn-profile" id="profileBtn" aria-label="My Profile">
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
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <!-- ══ BREADCRUMB ══════════════════════════════════════════════════════════ -->
    <div class="ud-breadcrumb">
        <a href="user-dashboard.php">Dashboard</a>
        <i class="ti ti-chevron-right ud-bc-sep"></i>
        <a href="user-dashboard.php#browse">Browse Rooms</a>
        <i class="ti ti-chevron-right ud-bc-sep"></i>
        <span><?php echo ud_esc($unitTitle); ?></span>
    </div>

    <!-- ══ GALLERY ═════════════════════════════════════════════════════════════ -->
    <section class="ud-gallery" id="udGallery">

        <?php if (count($images) >= 3): ?>
            <!-- Hotel 3-up grid -->
            <div class="ud-gallery-grid">
                <div class="ud-gallery-main-cell">
                    <img src="<?php echo ud_esc($images[0]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                        onerror="this.parentElement.classList.add('ud-img-error')">
                </div>
                <div class="ud-gallery-side">
                    <div class="ud-gallery-side-cell">
                        <img src="<?php echo ud_esc($images[1]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                            onerror="this.parentElement.classList.add('ud-img-error')">
                    </div>
                    <div class="ud-gallery-side-cell">
                        <img src="<?php echo ud_esc($images[2]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                            onerror="this.parentElement.classList.add('ud-img-error')">
                        <?php if (count($images) > 3): ?>
                            <button class="ud-show-all-btn" id="udShowAllBtn">
                                <i class="ti ti-grid-dots"></i>
                                Show all <?php echo count($images); ?> photos
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif (count($images) === 2): ?>
            <!-- 2-image side-by-side -->
            <div class="ud-gallery-grid" style="grid-template-columns:1fr 1fr">
                <div class="ud-gallery-main-cell" style="border-radius:18px 0 0 18px">
                    <img src="<?php echo ud_esc($images[0]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                        onerror="this.parentElement.classList.add('ud-img-error')">
                </div>
                <div class="ud-gallery-main-cell" style="border-radius:0 18px 18px 0">
                    <img src="<?php echo ud_esc($images[1]); ?>" alt="<?php echo ud_esc($unitTitle); ?>"
                        onerror="this.parentElement.classList.add('ud-img-error')">
                </div>
            </div>

        <?php else: ?>
            <!-- Single image / slider -->
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
                    <button class="ud-gnav ud-gprev" id="udPrev" aria-label="Previous">
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <button class="ud-gnav ud-gnext" id="udNext" aria-label="Next">
                        <i class="ti ti-chevron-right"></i>
                    </button>
                    <div class="ud-gdots" id="udDots">
                        <?php foreach ($images as $i => $_): ?>
                            <button class="ud-gdot<?php echo $i === 0 ? ' active' : ''; ?>" data-idx="<?php echo $i; ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Overlay: badges -->
        <div class="ud-gallery-badges">
            <span class="ud-badge-type"><?php echo ud_esc(strtoupper($unit['unit_type'] ?? 'UNIT')); ?></span>
            <span class="ud-badge-avail <?php echo $isVacant ? 'avail-yes' : 'avail-no'; ?>">
                <?php echo $isVacant
                    ? '✓ Available'
                    : ($unit['status'] === 'maintenance' ? 'Maintenance' : 'Booked'); ?>
            </span>
        </div>

        <!-- Overlay: save button -->
        <button class="ud-gallery-save <?php echo $isSaved ? 'saved' : ''; ?>" id="udSaveBtn"
            onclick="toggleSaveRoom(<?php echo (int) $unit['unit_id']; ?>, this)"
            aria-label="<?php echo $isSaved ? 'Remove from saved' : 'Save'; ?>">
            <i class="ti <?php echo $isSaved ? 'ti-heart-filled' : 'ti-heart'; ?>"></i>
        </button>

    </section>

    <!-- Lightbox -->
    <div class="ud-lightbox" id="udLightbox" role="dialog" aria-modal="true">
        <button class="ud-lb-close" id="udLbClose" aria-label="Close lightbox">
            <i class="ti ti-x"></i>
        </button>
        <button class="ud-lb-nav ud-lb-prev" id="udLbPrev" aria-label="Previous photo">
            <i class="ti ti-chevron-left"></i>
        </button>
        <button class="ud-lb-nav ud-lb-next" id="udLbNext" aria-label="Next photo">
            <i class="ti ti-chevron-right"></i>
        </button>
        <div class="ud-lb-track" id="udLbTrack">
            <?php foreach ($images as $img): ?>
                <div class="ud-lb-slide">
                    <img src="<?php echo ud_esc($img); ?>" alt="<?php echo ud_esc($unitTitle); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ud-lb-counter">
            <span id="udLbCurrent">1</span> / <span><?php echo count($images); ?></span>
        </div>
    </div>

    <!-- ══ MAIN LAYOUT ══════════════════════════════════════════════════════════ -->
    <div class="ud-layout">

        <!-- ══ LEFT COLUMN ══════════════════════════════════════════════════════ -->
        <div class="ud-main">

            <!-- Title & meta -->
            <div class="ud-title-block">
                <h1 class="ud-title"><?php echo ud_esc($unitTitle); ?></h1>

                <div class="ud-meta-row">
                    <?php if ($ratingValue !== null): ?>
                        <div class="ud-rating-pill">
                            <span class="ud-stars">
                                <?php
                                $full = min(5, (int) round($ratingValue));
                                $empty = max(0, 5 - $full);
                                echo str_repeat('★', $full) . str_repeat('☆', $empty);
                                ?>
                            </span>
                            <strong><?php echo number_format($ratingValue, 1); ?></strong>
                            <span class="ud-review-link"
                                onclick="document.getElementById('reviews').scrollIntoView({behavior:'smooth'})">
                                (<?php echo $totalReviews; ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?>)
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="ud-no-rating-pill">No ratings yet</div>
                    <?php endif; ?>

                    <span class="ud-meta-sep">·</span>

                    <div class="ud-location-pill">
                        <i class="ti ti-map-pin"></i>
                        <?php echo ud_esc($locationStr); ?>
                        <?php if ($addressStr): ?>
                            <span class="ud-addr-sm">· <?php echo ud_esc($addressStr); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Facts strip -->
                <div class="ud-facts-strip">
                    <div class="ud-fact">
                        <div class="ud-fact-label">Rate</div>
                        <div class="ud-fact-val"><?php echo $price; ?><sub>/night</sub></div>
                    </div>
                    <div class="ud-fact-sep"></div>
                    <div class="ud-fact">
                        <div class="ud-fact-label">Type</div>
                        <div class="ud-fact-val"><?php echo ud_esc(ucfirst($unit['unit_type'] ?? 'Standard')); ?></div>
                    </div>
                    <div class="ud-fact-sep"></div>
                    <div class="ud-fact">
                        <div class="ud-fact-label">Beds</div>
                        <div class="ud-fact-val"><?php echo (int) ($unit['num_beds'] ?? 2); ?></div>
                    </div>
                    <div class="ud-fact-sep"></div>
                    <div class="ud-fact">
                        <div class="ud-fact-label">Baths</div>
                        <div class="ud-fact-val"><?php echo (int) ($unit['num_baths'] ?? 1); ?></div>
                    </div>
                    <?php if (!empty($unit['floor'])): ?>
                        <div class="ud-fact-sep"></div>
                        <div class="ud-fact">
                            <div class="ud-fact-label">Floor</div>
                            <div class="ud-fact-val"><?php echo (int) $unit['floor']; ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="ud-fact-sep"></div>
                    <div class="ud-fact">
                        <div class="ud-fact-label">Max Guests</div>
                        <div class="ud-fact-val"><?php echo (int) ($unit['max_guests'] ?? 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- About -->
            <section class="ud-section">
                <h2 class="ud-section-title">About this unit</h2>
                <p class="ud-desc">
                    <?php echo nl2br(ud_esc($unit['description'] ?? 'A comfortable and well-appointed unit.')); ?>
                </p>
            </section>

            <!-- Amenities -->
            <?php if (!empty($amenities)): ?>
                <section class="ud-section">
                    <h2 class="ud-section-title">Amenities</h2>
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
                </section>
            <?php endif; ?>

            <!-- Map -->
            <?php if (!empty($unit['latitude']) && (float) $unit['latitude'] !== 0.0): ?>
                <section class="ud-section">
                    <h2 class="ud-section-title">Where you'll be</h2>
                    <div class="ud-map-wrap">
                        <div id="udLeafletMap"></div>
                    </div>
                    <?php if ($addressStr): ?>
                        <p class="ud-map-caption">
                            <i class="ti ti-map-pin"></i>
                            <?php echo ud_esc($addressStr); ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Reviews -->
            <section class="ud-section" id="reviews">
                <div class="ud-reviews-header">
                    <h2 class="ud-section-title">
                        <?php if ($ratingValue !== null): ?>
                            <span class="ud-rv-star">★</span>
                            <?php echo number_format($ratingValue, 1); ?> ·
                        <?php endif; ?>
                        Guest Reviews
                        <?php if ($totalReviews > 0): ?>
                            <span class="ud-review-count-badge"><?php echo $totalReviews; ?></span>
                        <?php endif; ?>
                    </h2>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="ud-no-reviews">
                        <i class="ti ti-message-circle"></i>
                        <p>No reviews yet for this unit.</p>
                    </div>

                <?php else: ?>
                    <div class="ud-reviews-list">
                        <?php foreach ($reviews as $rv): ?>
                            <div class="ud-review-card">
                                <div class="ud-rv-top">
                                    <div class="ud-rv-avatar">
                                        <?php echo strtoupper(mb_substr($rv['reviewer'], 0, 1)); ?>
                                    </div>
                                    <div class="ud-rv-info">
                                        <div class="ud-rv-name"><?php echo ud_esc($rv['reviewer']); ?></div>
                                        <div class="ud-rv-date"><?php echo date('F Y', strtotime($rv['created_at'])); ?></div>
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
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalReviewPages > 1): ?>
                        <div class="ud-reviews-pager">
                            <?php if ($reviewPage > 1): ?>
                                <a href="?id=<?php echo $unit_id; ?>&rp=<?php echo $reviewPage - 1; ?>#reviews"
                                    class="ud-pager-btn">
                                    <i class="ti ti-arrow-left"></i> Previous
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
            </section>

        </div><!-- /ud-main -->

        <!-- ══ RIGHT COLUMN: BOOKING CARD ════════════════════════════════════════ -->
        <aside class="ud-aside" id="udAside">
            <div class="ud-booking-card" id="udBookingCard">

                <!-- Dark green header -->
                <div class="ud-bc-header">
                    <div class="ud-bc-price">
                        <?php echo $price; ?><span class="ud-bc-per"> / night</span>
                    </div>
                    <?php if ($ratingValue !== null): ?>
                        <div class="ud-bc-rating">
                            <span class="ud-bc-stars">★</span>
                            <strong><?php echo number_format($ratingValue, 1); ?></strong>
                            <span>· <?php echo $totalReviews; ?> review<?php echo $totalReviews !== 1 ? 's' : ''; ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="ud-bc-body">

                    <!-- Date + Guests unified block -->
                    <div class="ud-date-grid">
                        <div class="ud-date-cell ud-date-in">
                            <label for="udCheckin">Check-in</label>
                            <input type="date" id="udCheckin" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="ud-date-cell ud-date-out">
                            <label for="udCheckout">Check-out</label>
                            <input type="date" id="udCheckout" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        <div class="ud-date-cell ud-date-guests">
                            <div>
                                <label>Guests</label>
                                <div
                                    style="font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;color:#1a2332">
                                    <span id="udGCount">2</span> guest<span id="udGPlural">s</span>
                                </div>
                            </div>
                            <div class="ud-guests-ctl">
                                <button class="ud-g-btn" id="udGMinus" aria-label="Fewer guests">−</button>
                                <button class="ud-g-btn" id="udGPlus" aria-label="More guests">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Availability note -->
                    <div class="ud-avail-note<?php echo !$isVacant ? ' unavail' : ''; ?>" id="udAvailNote">
                        <?php if ($isVacant): ?>
                            <i class="ti ti-circle-check"></i>
                            <span>Unit is available from <?php echo date('M j, Y'); ?>.
                                Security deposit is 50% of total stay.</span>
                        <?php else: ?>
                            <i class="ti ti-circle-x"></i>
                            <span>This unit is currently <?php echo ud_esc($unit['status']); ?>.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Price breakdown (shown after dates chosen) -->
                    <div class="ud-price-breakdown" id="udPriceBreakdown" style="display:none">
                        <div class="ud-pb-row">
                            <span id="udNightsLabel">—</span>
                            <span id="udNightsTotal">—</span>
                        </div>
                        <div class="ud-pb-row ud-pb-muted">
                            <span>Security deposit (50%)</span>
                            <span id="udDeposit">—</span>
                        </div>
                        <div class="ud-pb-row ud-pb-muted">
                            <span>Cleaning fee</span>
                            <span>₱500</span>
                        </div>
                        <div class="ud-pb-divider"></div>
                        <div class="ud-pb-total">
                            <span>Total due today</span>
                            <span id="udTotalDue">—</span>
                        </div>
                        <div class="ud-pb-note" id="udRemainingNote"></div>
                    </div>

                    <!-- Reserve button -->
                    <?php if ($isVacant): ?>
                        <button class="ud-book-btn" id="udBookBtn"
                            onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                            Reserve this unit
                        </button>
                    <?php else: ?>
                        <button class="ud-book-btn ud-book-btn--disabled" disabled>
                            Currently Unavailable
                        </button>
                    <?php endif; ?>

                    <p class="ud-book-note">
                        <i class="ti ti-shield-check"></i>
                        You won't be charged yet — booking holds for 30 minutes
                    </p>

                </div><!-- /ud-bc-body -->

                <!-- Share / Save -->
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

            </div><!-- /ud-booking-card -->
        </aside>

    </div><!-- /ud-layout -->

    <!-- ══ TOAST ════════════════════════════════════════════════════════════════ -->
    <div id="toast" role="status" aria-live="polite" style="position:fixed;bottom:32px;left:50%;transform:translateX(-50%) translateY(80px);
            background:#1a2332;color:#fff;padding:13px 26px;border-radius:40px;
            font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;
            box-shadow:0 8px 32px rgba(10,22,40,.3);z-index:600;
            transition:transform .4s cubic-bezier(.4,0,.2,1),opacity .4s;
            opacity:0;white-space:nowrap;display:flex;align-items:center;gap:10px;">
        <i class="ti ti-check" style="font-size:15px;color:#6ee7b7"></i>
        <span id="toastMsg"></span>
    </div>

    <!-- ══ MOBILE FLOAT BAR ═════════════════════════════════════════════════════ -->
    <div class="ud-float-bar" id="udFloatBar">
        <div class="ud-float-left">
            <div class="ud-float-price"><?php echo $price; ?><sub>/night</sub></div>
            <div class="ud-float-dates" id="udFloatDates">Select dates</div>
        </div>
        <?php if ($isVacant): ?>
            <button class="ud-float-btn" id="udFloatBtn"
                onclick='openBookingModalFromDetail(<?php echo htmlspecialchars($roomJs, ENT_QUOTES); ?>)'>
                Book Now
            </button>
        <?php else: ?>
            <button class="ud-float-btn ud-float-btn--disabled" disabled>Unavailable</button>
        <?php endif; ?>
    </div>

    <!-- ══ BOOKING MODAL ════════════════════════════════════════════════════════ -->
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
                    <!-- Step 1 -->
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
                            <div class="bm-field"><label>Email address</label><input type="email" id="bm-email"
                                    placeholder="ana@email.com" autocomplete="email"></div>
                        </div>
                        <div class="bm-row full">
                            <div class="bm-field"><label>Contact number</label><input type="tel" id="bm-phone"
                                    placeholder="+63 912 345 6789" autocomplete="tel"></div>
                        </div>
                        <div class="bm-row">
                            <div class="bm-field"><label>Check-in date</label><input type="date" id="bm-checkin"></div>
                            <div class="bm-field"><label>Check-out date</label><input type="date" id="bm-lease"></div>
                        </div>
                    </div>
                    <!-- Step 2 -->
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
                            <div class="bm-review-row"><span class="bm-review-key">Cleaning fee</span><span
                                    class="bm-review-val">₱500</span></div>
                            <div class="bm-review-row" style="padding-top:10px">
                                <span class="bm-review-key" style="font-weight:700;color:var(--text-dark)">Total due
                                    now</span>
                                <span class="bm-review-val" id="rv-total"
                                    style="color:var(--teal);font-size:1.05rem">—</span>
                            </div>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div class="bm-panel" id="bm-panel-3">
                        <div class="bm-panel-title">Payment method</div>
                        <div class="bm-panel-sub">Choose how you'd like to pay the amount due today.</div>
                        <div class="bm-pay-methods" id="bmPayMethods">
                            <div class="bm-pay-option selected" data-method="GCash">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/gcash.png" alt="GCash">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">GCash</div>
                                    <div class="bm-pay-desc">Pay via GCash online transfer</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                            <div class="bm-pay-option" data-method="Maya">
                                <div class="bm-pay-icon"><img src="../../assets/images/logo-icon/maya1.png" alt="Maya">
                                </div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Maya</div>
                                    <div class="bm-pay-desc">Pay via Maya online transfer</div>
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
                                <div class="bm-pay-icon"
                                    style="height:45px;width:45px;font-size:28px;display:flex;align-items:center;justify-content:center">
                                    💵</div>
                                <div class="bm-pay-info">
                                    <div class="bm-pay-name">Cash (On-site)</div>
                                    <div class="bm-pay-desc">Pay at the front desk upon check-in</div>
                                </div>
                                <div class="bm-pay-radio"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Step 4 -->
                    <div class="bm-panel" id="bm-panel-4">
                        <div class="bm-confirm-check" id="bm-payment-waiting">
                            <div class="bm-check-ring" style="border-color:#c9a84c;animation:none">
                                <svg viewBox="0 0 24 24" style="stroke:#c9a84c">
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
                            <div class="bm-confirm-sub">Your payment was not completed. Please try again.</div>
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
                <!-- Modal sidebar -->
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
                                class="bm-summary-val" id="sb-rent">—</span></div>
                        <div class="bm-summary-row"><span class="bm-summary-key">Security deposit (50%)</span><span
                                class="bm-summary-val" id="sb-deposit">—</span></div>
                        <div class="bm-summary-row"><span class="bm-summary-key">Cleaning fee</span><span
                                class="bm-summary-val">₱500</span></div>
                    </div>
                    <div class="bm-summary-divider"></div>
                    <div class="bm-total-row">
                        <span class="bm-total-label">Total due now</span>
                        <span class="bm-total-amount" id="sb-total">—</span>
                    </div>
                    <div class="bm-hold-notice">
                        Your booking is held for <strong>30 minutes</strong>. Complete payment to confirm.
                    </div>
                </div>
                <!-- Modal footer -->
                <div class="bm-footer-wrap" id="bmFooter">
                    <button class="bm-btn bm-btn-back" id="bmBack" onclick="bmPrevStep()" style="display:none">
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
                    <button class="bm-btn bm-btn-confirm" id="bmConfirmBtn" style="display:none"
                        onclick="bmSubmitBooking()">
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Confirm Payment
                    </button>
                    <button class="bm-btn bm-btn-next" id="bmDoneBtn" style="display:none"
                        onclick="closeBookingModal();window.location.href='bookings.php'">
                        Done
                        <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ SCRIPTS ══════════════════════════════════════════════════════════════ -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/user-js/script.js"></script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/user-js/saved.js"></script>
    <script>
        /* ── Globals ── */
        window.PS_POPULAR_PAYMENT = <?php echo json_encode($popularPaymentMethod); ?>;
        window.hasActiveBooking = <?php echo json_encode($hasActiveBooking); ?>;
        window._psSessionFields = {
            fname: <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>,
            lname: <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>,
            email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
            phone: <?php echo json_encode($_SESSION['phone'] ?? ''); ?>,
        };
        window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        window.psGetCsrfToken = () => String(window.PS_CSRF_TOKEN || '');
        window.psAppendCsrf = t => { const k = window.psGetCsrfToken(); if (k && t?.append) t.append('csrf_token', k); return t; };
        window.PS_RT_PAGE = 'unit_detail';
        window.PS_RT_ROLE = 'user';
        window.PS_RT_API = '../../api/realtime.php';

        const UD_UNIT = {
            lat: <?php echo (float) ($unit['latitude'] ?? 0); ?>,
            lng: <?php echo (float) ($unit['longitude'] ?? 0); ?>,
            priceNum: <?php echo (float) $unit['rent_amount']; ?>,
            maxGuests: <?php echo (int) ($unit['max_guests'] ?? 6); ?>,
        };

        if (new URLSearchParams(location.search).get('book') === '1')
            window.addEventListener('load', () => document.getElementById('udBookBtn')?.click());
    </script>

    <script>
        (function () {
            'use strict';

            const CLEANING = 500;
            const fmt = n => '₱' + Math.round(n).toLocaleString('en-PH');
            const $ = id => document.getElementById(id);
            const set = (id, v) => { const e = $(id); if (e) e.textContent = v; };
            const val = id => ($(id)?.value || '').trim();

            /* ── Booking modal bridge ─────────────────────────────────────────────
               script.js defines modal functions inside a closure — not on window.
               We shim them here so PHP onclick attrs can reach them.
            ── */
            window.openBookingModal = window.openBookingModal || function (room) {
                if (window.hasActiveBooking) {
                    showToast?.('You already have an active booking for this unit.');
                    return;
                }

                // Sidebar defaults
                set('bmSbName', room.name || '—');
                set('bmSbLoc', room.location || '—');
                set('sb-rent', (room.price || '—') + ' / night');
                set('sb-deposit', '—');
                set('sb-total', '—');

                const img = $('bmUnitImg');
                if (img && room.image) { img.src = room.image; img.style.display = 'block'; }

                // Pre-fill tenant
                const s = window._psSessionFields || {};
                [['bm-fname', s.fname], ['bm-lname', s.lname],
                ['bm-email', s.email], ['bm-phone', s.phone]].forEach(([id, v]) => {
                    const el = $(id); if (el) el.value = v || '';
                });

                // Pre-fill dates
                const ci = $('bm-checkin'), co = $('bm-lease');
                if (ci) { ci.value = room._prefillCheckin || ''; ci.min = new Date().toISOString().split('T')[0]; }
                if (co) { co.value = room._prefillCheckout || ''; co.min = new Date(Date.now() + 86400000).toISOString().split('T')[0]; }

                // Popular badge
                document.querySelectorAll('#bmPayMethods .bm-pay-badge').forEach(b => b.remove());
                const pop = window.PS_POPULAR_PAYMENT || 'GCash';
                const popEl = document.querySelector(`#bmPayMethods [data-method="${pop}"]`);
                if (popEl && !popEl.querySelector('.bm-pay-badge')) {
                    const b = document.createElement('span');
                    b.className = 'bm-pay-badge'; b.textContent = 'Popular';
                    popEl.appendChild(b);
                }

                window._bmRoom = room;
                _goTo(1);

                const ov = $('bmOverlay');
                if (ov) {
                    ov.classList.add('active');
                    requestAnimationFrame(() => ov.classList.add('open'));
                    ov.onclick = e => { if (e.target === ov) closeBookingModal(); };
                }
            };

            window.closeBookingModal = window.closeBookingModal || function () {
                const ov = $('bmOverlay');
                if (!ov) return;
                ov.classList.remove('open');
                setTimeout(() => ov.classList.remove('active'), 350);
                clearInterval(window._bmPollInterval);
            };

            function _goTo(step) {
                step = Math.max(1, Math.min(4, step));
                window._bmCurrentStep = step;

                document.querySelectorAll('.bm-panel')
                    .forEach((p, i) => p.classList.toggle('active', i + 1 === step));
                document.querySelectorAll('.bm-step').forEach((s, i) => {
                    s.classList.toggle('active', i + 1 === step);
                    s.classList.toggle('done', i + 1 < step);
                });

                const back = $('bmBack'), next = $('bmNext'),
                    conf = $('bmConfirmBtn'), done = $('bmDoneBtn');
                if (back) back.style.display = (step > 1 && step < 4) ? '' : 'none';
                if (next) next.style.display = step < 3 ? '' : 'none';
                if (conf) conf.style.display = step === 3 ? '' : 'none';
                if (done) done.style.display = 'none';

                if (step === 2) {
                    const room = window._bmRoom || {};
                    const ci = val('bm-checkin'), co = val('bm-lease');
                    const nights = Math.max(0, Math.round((new Date(co) - new Date(ci)) / 86400000));
                    const subtot = nights * (room.priceNum || 0);
                    const deposit = subtot * 0.5;
                    const total = deposit + CLEANING;

                    set('rv-name', [val('bm-fname'), val('bm-lname')].join(' '));
                    set('rv-email', val('bm-email'));
                    set('rv-phone', val('bm-phone'));
                    set('rv-unit', room.name || '—');
                    set('rv-movein', ci);
                    set('rv-checkout', co);
                    set('rv-nights', nights);
                    set('rv-rent', fmt(room.priceNum || 0));
                    set('rv-deposit', fmt(deposit));
                    set('rv-total', fmt(total));
                    set('sb-deposit', fmt(deposit));
                    set('sb-total', fmt(total));
                }
            }

            window.bmNextStep = window.bmNextStep || function () {
                const step = window._bmCurrentStep || 1;
                if (step === 1) {
                    if (!val('bm-fname') || !val('bm-lname')) { showToast?.('Please enter your full name.'); return; }
                    if (!val('bm-email')) { showToast?.('Please enter your email.'); return; }
                    if (!val('bm-phone')) { showToast?.('Please enter your contact number.'); return; }
                    if (!val('bm-checkin')) { showToast?.('Please select a check-in date.'); return; }
                    if (!val('bm-lease')) { showToast?.('Please select a check-out date.'); return; }
                    if (val('bm-lease') <= val('bm-checkin')) { showToast?.('Check-out must be after check-in.'); return; }
                }
                _goTo(step + 1);
            };

            window.bmPrevStep = window.bmPrevStep || function () {
                if ((window._bmCurrentStep || 1) > 1) _goTo((window._bmCurrentStep || 1) - 1);
            };

            window.bmSubmitBooking = window.bmSubmitBooking || function () {
                const room = window._bmRoom || {};
                const method = document.querySelector('#bmPayMethods .bm-pay-option.selected')?.dataset.method || 'GCash';
                const ci = val('bm-checkin'), co = val('bm-lease');
                const nights = Math.max(0, Math.round((new Date(co) - new Date(ci)) / 86400000));
                const subtot = nights * (room.priceNum || 0);
                const deposit = subtot * 0.5;
                const total = deposit + CLEANING;

                showToast?.('Submitting your booking…');

                const fd = new FormData();
                fd.append('unit_id', room.id || '');
                fd.append('first_name', val('bm-fname'));
                fd.append('last_name', val('bm-lname'));
                fd.append('email', val('bm-email'));
                fd.append('phone', val('bm-phone'));
                fd.append('checkin_date', ci);
                fd.append('checkout_date', co);
                fd.append('payment_method', method);
                fd.append('total_amount', subtot);
                window.psAppendCsrf(fd);

                _goTo(4);
                ['bm-payment-waiting', 'bm-payment-success', 'bm-payment-cash',
                    'bm-payment-failed', 'bm-payment-expired'].forEach((id, i) => {
                        const el = $(id); if (el) el.style.display = i === 0 ? '' : 'none';
                    });
                $('bmFooter')?.querySelectorAll('button').forEach(b => b.style.display = 'none');

                fetch('../../api/user/book_unit.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            showToast?.(data.message || 'Booking failed.', 'error');
                            _goTo(3); return;
                        }
                        const bid = data.booking_id;
                        set('bmConfirmRef', `Ref #BK-${String(bid).padStart(4, '0')}`);

                        if (method === 'Cash') {
                            $('bm-payment-waiting').style.display = 'none';
                            $('bm-payment-cash').style.display = '';
                            set('cf-unit-cash', room.name || '—');
                            set('cf-movein-cash', ci);
                            set('cf-checkout-cash', co);
                            set('cf-method-cash', method);
                            set('cf-total-cash', fmt(total));
                            $('bmDoneBtn').style.display = '';
                            window.hasActiveBooking = true;
                            return;
                        }

                        if (data.payment_url) {
                            window._bmPayTab = window.open(data.payment_url, '_blank');
                            window._bmPayUrl = data.payment_url;
                            setTimeout(() => { const b = $('bmReopenPayBtn'); if (b) b.style.display = ''; }, 4000);
                        }

                        let polls = 0;
                        window._bmPollInterval = setInterval(() => {
                            if (++polls > 60) {
                                clearInterval(window._bmPollInterval);
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-expired').style.display = '';
                                set('bmExpiredRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                return;
                            }
                            fetch(`../../api/user/check_payment_status.php?booking_id=${bid}`)
                                .then(r => r.json())
                                .then(st => {
                                    if (st.status === 'confirmed') {
                                        clearInterval(window._bmPollInterval);
                                        $('bm-payment-waiting').style.display = 'none';
                                        $('bm-payment-success').style.display = '';
                                        set('cf-unit', room.name || '—');
                                        set('cf-movein', ci);
                                        set('cf-checkout', co);
                                        set('cf-method', method);
                                        set('cf-total', fmt(total));
                                        $('bmDoneBtn').style.display = '';
                                        window.hasActiveBooking = true;
                                        showToast?.('Payment confirmed!');
                                    } else if (st.status === 'failed') {
                                        clearInterval(window._bmPollInterval);
                                        $('bm-payment-waiting').style.display = 'none';
                                        $('bm-payment-failed').style.display = '';
                                        set('bmFailedRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                    }
                                }).catch(() => { });
                        }, 5000);
                    })
                    .catch(err => { showToast?.(err?.message || 'Network error.', 'error'); _goTo(3); });
            };

            window.bmReopenPaymongoTab = window.bmReopenPaymongoTab || function () {
                if (window._bmPayTab && !window._bmPayTab.closed) window._bmPayTab.focus();
                else if (window._bmPayUrl) window._bmPayTab = window.open(window._bmPayUrl, '_blank');
            };

            // Payment option clicks
            document.querySelectorAll('#bmPayMethods .bm-pay-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    document.querySelectorAll('#bmPayMethods .bm-pay-option')
                        .forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                });
            });

            /* ── Gallery slider (fallback) ───────────────────────────────────────── */
            const track = $('udTrack');
            const dots = $('udDots');
            let gCur = 0;
            const gSlides = track ? track.querySelectorAll('.ud-gallery-slide') : [];

            function goTo(idx) {
                if (!track || !gSlides.length) return;
                gCur = ((idx % gSlides.length) + gSlides.length) % gSlides.length;
                track.style.transform = `translateX(-${gCur * 100}%)`;
                dots?.querySelectorAll('.ud-gdot')
                    .forEach((d, i) => d.classList.toggle('active', i === gCur));
            }

            $('udPrev')?.addEventListener('click', () => goTo(gCur - 1));
            $('udNext')?.addEventListener('click', () => goTo(gCur + 1));
            dots?.addEventListener('click', e => {
                const b = e.target.closest('.ud-gdot');
                if (b) goTo(+b.dataset.idx);
            });
            if (track) {
                let tx = 0;
                track.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
                track.addEventListener('touchend', e => {
                    const dx = e.changedTouches[0].clientX - tx;
                    if (Math.abs(dx) > 50) goTo(gCur + (dx < 0 ? 1 : -1));
                });
            }

            /* ── Lightbox ─────────────────────────────────────────────────────────── */
            const lb = $('udLightbox');
            const lbTrack = $('udLbTrack');
            const lbCurEl = $('udLbCurrent');
            let lbIdx = 0;
            const lbSlides = lbTrack ? lbTrack.querySelectorAll('.ud-lb-slide') : [];

            function lbGoTo(idx) {
                if (!lbTrack || !lbSlides.length) return;
                lbIdx = ((idx % lbSlides.length) + lbSlides.length) % lbSlides.length;
                lbTrack.style.transform = `translateX(-${lbIdx * 100}%)`;
                if (lbCurEl) lbCurEl.textContent = lbIdx + 1;
            }

            function openLightbox(startIdx) {
                if (!lb) return;
                lb.classList.add('open');
                lbGoTo(startIdx || 0);
                document.body.style.overflow = 'hidden';
            }
            function closeLightbox() {
                lb?.classList.remove('open');
                document.body.style.overflow = '';
            }

            $('udShowAllBtn')?.addEventListener('click', () => openLightbox(0));

            // Also open lightbox on clicking main/side cells
            document.querySelector('.ud-gallery-main-cell')?.addEventListener('click', () => openLightbox(0));
            document.querySelectorAll('.ud-gallery-side-cell').forEach((el, i) => {
                el.addEventListener('click', e => {
                    if (e.target.closest('.ud-show-all-btn')) return;
                    openLightbox(i + 1);
                });
            });

            $('udLbClose')?.addEventListener('click', closeLightbox);
            $('udLbPrev')?.addEventListener('click', () => lbGoTo(lbIdx - 1));
            $('udLbNext')?.addEventListener('click', () => lbGoTo(lbIdx + 1));
            lb?.addEventListener('click', e => { if (e.target === lb) closeLightbox(); });

            document.addEventListener('keydown', e => {
                if (lb?.classList.contains('open')) {
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft') lbGoTo(lbIdx - 1);
                    if (e.key === 'ArrowRight') lbGoTo(lbIdx + 1);
                    return;
                }
                if ($('bmOverlay')?.classList.contains('active')) return;
                if (e.key === 'ArrowLeft') goTo(gCur - 1);
                if (e.key === 'ArrowRight') goTo(gCur + 1);
            });

            /* ── Leaflet map ─────────────────────────────────────────────────────── */
            if (UD_UNIT.lat && UD_UNIT.lng) {
                const map = L.map('udLeafletMap', { zoomControl: true, scrollWheelZoom: false })
                    .setView([UD_UNIT.lat, UD_UNIT.lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                L.marker([UD_UNIT.lat, UD_UNIT.lng]).addTo(map);
            }

            /* ── Date pickers + price breakdown ──────────────────────────────────── */
            const ciEl = $('udCheckin'), coEl = $('udCheckout');

            function updateBreakdown() {
                const bd = $('udPriceBreakdown');
                if (!ciEl || !coEl || !bd) return;

                const a = new Date(ciEl.value), b = new Date(coEl.value);
                if (!ciEl.value || !coEl.value || b <= a) { bd.style.display = 'none'; return; }

                const nights = Math.round((b - a) / 86400000);
                const subtot = nights * UD_UNIT.priceNum;
                const deposit = subtot * 0.5;            // 50% deposit charged at booking
                const total = deposit + CLEANING;      // deposit + cleaning = due today

                set('udNightsLabel', `${nights} night${nights !== 1 ? 's' : ''} × ${fmt(UD_UNIT.priceNum)}`);
                set('udNightsTotal', fmt(subtot));
                set('udDeposit', fmt(deposit));
                set('udTotalDue', fmt(total));
                set('udRemainingNote',
                    `Remaining ${fmt(subtot - deposit)} balance due at check-out`);

                bd.style.display = 'block';

                // Update float bar label
                const fd = $('udFloatDates');
                if (fd) {
                    const o = { month: 'short', day: 'numeric' };
                    fd.textContent = `${a.toLocaleDateString('en-PH', o)} – `
                        + `${b.toLocaleDateString('en-PH', o)} · `
                        + `${nights} night${nights !== 1 ? 's' : ''}`;
                }
            }

            ciEl?.addEventListener('change', () => {
                if (ciEl.value && coEl) {
                    const nd = new Date(ciEl.value);
                    nd.setDate(nd.getDate() + 1);
                    coEl.min = nd.toISOString().split('T')[0];
                    if (coEl.value && coEl.value <= ciEl.value) coEl.value = '';
                }
                updateBreakdown();
            });
            coEl?.addEventListener('change', updateBreakdown);

            /* ── Guests stepper ──────────────────────────────────────────────────── */
            let gCount = 2;
            const gCountEl = $('udGCount');
            const gPluralEl = $('udGPlural');

            function updateGuests() {
                if (gCountEl) gCountEl.textContent = gCount;
                if (gPluralEl) gPluralEl.textContent = gCount === 1 ? '' : 's';
            }

            $('udGMinus')?.addEventListener('click', () => {
                if (gCount > 1) { gCount--; updateGuests(); }
            });
            $('udGPlus')?.addEventListener('click', () => {
                if (gCount < UD_UNIT.maxGuests) { gCount++; updateGuests(); }
            });

            /* ── Book button — pass dates + guests to modal ──────────────────────── */
            window.openBookingModalFromDetail = function (roomData) {
                roomData._prefillCheckin = ciEl?.value || '';
                roomData._prefillCheckout = coEl?.value || '';
                roomData.guests = gCount;
                window.openBookingModal(roomData);
            };

            /* ── Disable buttons if already booked ──────────────────────────────── */
            if (window.hasActiveBooking) {
                const bb = $('udBookBtn');
                if (bb) {
                    bb.disabled = true;
                    bb.textContent = 'You already have an active booking';
                    bb.classList.add('ud-book-btn--disabled');
                }
                const fb = $('udFloatBtn');
                if (fb) { fb.disabled = true; fb.textContent = 'Already Booked'; }
            }

            /* ── Share ───────────────────────────────────────────────────────────── */
            window.shareUnit = function () {
                if (navigator.share) {
                    navigator.share({ title: document.title, url: location.href }).catch(() => { });
                } else {
                    navigator.clipboard?.writeText(location.href)
                        .then(() => showToast?.('Link copied to clipboard!'));
                }
            };

            /* ── Float bar IntersectionObserver ─────────────────────────────────── */
            const card = $('udBookingCard');
            if (card) {
                new IntersectionObserver(([e]) => {
                    document.body.classList.toggle('ud-card-offscreen', !e.isIntersecting);
                }, { threshold: 0 }).observe(card);
            }

            /* ── Amenities show-more ─────────────────────────────────────────────── */
            const showMoreBtn = $('udShowMoreAmenities');
            if (showMoreBtn) {
                // Hide chips after the 8th
                document.querySelectorAll('.ud-amenity-chip')
                    .forEach((c, i) => { if (i >= 8) c.classList.add('ud-am-hidden'); });

                showMoreBtn.addEventListener('click', () => {
                    document.querySelectorAll('.ud-amenity-chip.ud-am-hidden')
                        .forEach(c => c.classList.remove('ud-am-hidden'));
                    showMoreBtn.style.display = 'none';
                });
            }

        })();
    </script>

</body>
<?php require '../../includes/_layout_end.php'; ?>

</html>