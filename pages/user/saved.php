<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/saved-inline.js"></script>
</body>
</html>';
    exit;
}

$first_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Guest');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);
$email = htmlspecialchars($_SESSION['email'] ?? '');
$initials = strtoupper(mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$page_title = 'Saved Rooms';
$page_hero_html = '<em>Saved</em> Rooms';
$page_hero_sub = 'Your personal wishlist of favorite rooms and suites.';
$page_hero_icon = '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>';
$active_nav = 'saved';
require '../../includes/_layout.php';

require_once '../../includes/db.php';
$userId = (int) $_SESSION['user_id'];

$sort = $_GET['sort'] ?? 'date_desc';
$orderBy = match ($sort) {
    'price_asc' => 'u.rent_amount ASC',
    'price_desc' => 'u.rent_amount DESC',
    default => 's.created_at DESC',
};

$savedRatingExpr = "NULL AS rating";
$savedHasBookingRating = false;
$savedHasUnitRating = false;

if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'rating'")) {
    $savedHasBookingRating = mysqli_num_rows($tbl) > 0;
}
if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM units LIKE 'rating'")) {
    $savedHasUnitRating = mysqli_num_rows($tbl) > 0;
}

if ($savedHasBookingRating) {
    $savedRatingExpr = "(
            SELECT ROUND(AVG(br.rating), 1)
            FROM bookings br
            WHERE br.unit_id = u.unit_id
              AND br.rating IS NOT NULL
        ) AS rating";
} elseif ($savedHasUnitRating) {
    $savedRatingExpr = "u.rating AS rating";
}

$res = mysqli_query($conn, "
    SELECT s.id AS saved_id, s.created_at AS saved_at,
           u.unit_id, u.unit_number, u.unit_name, u.unit_type,
           u.floor, u.rent_amount, u.status, u.description, u.max_guests,
           p.property_name, p.city, p.address,
           (SELECT ui.image_path FROM unit_images ui
            WHERE ui.unit_id=u.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC LIMIT 1) AS image_path,
           $savedRatingExpr
    FROM saved_units s
    JOIN units u ON u.unit_id = s.unit_id
    LEFT JOIN properties p ON p.property_id = u.property_id
    WHERE s.user_id=$userId
    ORDER BY $orderBy
");
$saved_rooms = [];
while ($row = mysqli_fetch_assoc($res))
    $saved_rooms[] = $row;
?>

<link rel="stylesheet" href="../../assets/css/user-css/saved.css" />

<div class="saved-header-bar reveal">
    <div class="saved-count">Showing <strong><?php echo count($saved_rooms); ?></strong> saved rooms</div>
    <form method="GET" style="display:inline;">
        <select class="sort-select" name="sort" onchange="this.form.submit()">
            <option value="date_desc" <?php echo ($sort === 'date_desc' ? 'selected' : ''); ?>>Sort by: Date Saved
            </option>
            <option value="price_asc" <?php echo ($sort === 'price_asc' ? 'selected' : ''); ?>>Sort by: Price (Low → High)
            </option>
            <option value="price_desc" <?php echo ($sort === 'price_desc' ? 'selected' : ''); ?>>Sort by: Price (High →
                Low)
            </option>
        </select>
    </form>
</div>

<div class="page-two-col">
    <div class="col-main">

        <?php if (empty($saved_rooms)): ?>
            <div style="text-align:center;padding:60px 20px;">
                <div style="font-size:18px;font-weight:700;color:var(--text-dark);margin-bottom:8px;">No saved rooms yet
                </div>
                <div style="color:var(--text-soft);margin-bottom:24px;">Browse units and tap the heart to save your
                    favorites.</div>
                <a href="user-dashboard.php" style="display:inline-block;text-decoration:none;" class="btn-primary">Browse
                    Rooms</a>
            </div>
        <?php else: ?>
            <div class="saved-grid">
                <?php foreach ($saved_rooms as $i => $r):
                    $d = $i < 3 ? " rd{$i}" : '';
                    $imgSrc = !empty($r['image_path']) ? '../../' . ltrim($r['image_path'], '/') : '';
                    $unitName = htmlspecialchars($r['unit_name'] ?: $r['unit_number'] ?: 'Unit');
                    $propLoc = htmlspecialchars(trim(($r['property_name'] ?? '') . ($r['city'] ? ', ' . $r['city'] : '')));
                    $avail = $r['status'] === 'vacant';
                    $price = (float) $r['rent_amount'];
                    $guests = (int) $r['max_guests'];
                    $ratingValue = isset($r['rating']) && $r['rating'] !== null && $r['rating'] !== ''
                        ? round((float) $r['rating'], 1)
                        : null;
                    $savedOn = date('M j, Y', strtotime($r['saved_at']));
                    $unitType = htmlspecialchars(ucfirst($r['unit_type'] ?? 'Room'));
                    $floor = $r['floor'] ? 'Floor ' . $r['floor'] : '';
                    ?>
                    <div class="saved-card reveal<?php echo $d; ?>" data-unit-id="<?php echo $r['unit_id']; ?>">
                        <div class="sc-img">
                            <?php if ($imgSrc): ?>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo $unitName; ?>"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                            <?php endif; ?>
                            <div class="sc-img-fallback"
                                style="<?php echo $imgSrc ? 'display:none;' : '' ?>background:linear-gradient(145deg,#dbeafe,#3b82f6,#1a3d7c);">
                            </div>
                            <span
                                class="sc-badge <?php echo $avail ? 'badge-gold' : 'badge-blue'; ?>"><?php echo $unitType; ?></span>
                            <span
                                class="sc-avail <?php echo $avail ? 'avail-yes' : 'avail-no'; ?>"><?php echo $avail ? 'Available' : 'Booked'; ?></span>
                            <button class="sc-heart active"
                                onclick="removeSaved(<?php echo $r['saved_id']; ?>, <?php echo $r['unit_id']; ?>, this)"
                                title="Remove from saved">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                                </svg>
                            </button>
                        </div>
                        <div class="sc-body">
                            <div class="sc-name"><?php echo $unitName; ?></div>
                            <div class="sc-meta">
                                <?php if ($propLoc): ?>
                                    <span><svg viewBox="0 0 24 24">
                                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                        </svg><?php echo $propLoc; ?></span>
                                <?php endif; ?>
                                <?php if ($floor): ?>
                                    <span><svg viewBox="0 0 24 24">
                                            <rect x="2" y="7" width="20" height="14" rx="2" />
                                            <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                                        </svg><?php echo $floor; ?></span>
                                <?php endif; ?>
                                <span><svg viewBox="0 0 24 24">
                                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </svg><?php echo $guests; ?> Guests max</span>
                            </div>
                            <?php if (!empty($r['description'])): ?>
                                <div
                                    style="font-size:0.8rem;color:var(--text-soft);margin:6px 0;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                                    <?php echo htmlspecialchars($r['description']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="sc-saved-on"><svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg> Saved on <?php echo $savedOn; ?></div>
                            <div class="sc-foot">
                                <div>
                                    <div class="sc-price">₱<?php echo number_format($price); ?> <sub>/ night</sub></div>
                                </div>
                                <?php
                                $modalRoomData = [
                                    'id' => (int) $r['unit_id'],
                                    'unit_id' => (int) $r['unit_id'],
                                    'name' => html_entity_decode($unitName, ENT_QUOTES),
                                    'location' => html_entity_decode($propLoc, ENT_QUOTES),
                                    'price' => '₱' . number_format($price) . ' / night',
                                    'priceNum' => $price,
                                    'description' => trim((string) ($r['description'] ?? '')) ?: 'A comfortable and well-appointed unit.',
                                    'image' => $imgSrc,
                                    'guests' => max(1, $guests),
                                    'rating' => $ratingValue,
                                    'amenities' => ['Water', 'Wi-Fi', 'Air Conditioning', 'Rooftop'],
                                ];
                                ?>
                                <button class="btn-book-sc" <?php echo !$avail ? 'disabled' : ''; ?>         <?php if ($avail): ?>
                                        onclick='openBookingModal(<?php echo htmlspecialchars(json_encode($modalRoomData), ENT_QUOTES); ?>)'
                                    <?php endif; ?>>
                                    <?php echo $avail ? 'Book Now' : 'Unavailable'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div><!-- /col-main -->

    <!-- ── Saved Sidebar ── -->
    <div class="col-side">

        <div class="tip-card reveal rd1">
            <div class="tip-card-label">❤️ Your Wishlist</div>
            <div class="tip-card-title">Ready to book your favorites?</div>
            <div class="tip-card-body">You have <strong><?php echo count($saved_rooms); ?> saved
                    room<?php echo count($saved_rooms) !== 1 ? 's' : ''; ?></strong>. Book now before they're taken!
            </div>
            <a href="user-dashboard.php" class="tip-card-cta">Browse More →</a>
        </div>

        <div class="widget-card reveal rd2">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                Wishlist Stats
            </div>
            <?php
            $availCount = count(array_filter($saved_rooms, fn($r) => $r['status'] === 'vacant'));
            $avgPrice = count($saved_rooms) ? array_sum(array_column($saved_rooms, 'rent_amount')) / count($saved_rooms) : 0;
            $minPrice = count($saved_rooms) ? min(array_column($saved_rooms, 'rent_amount')) : 0;
            $maxPrice = count($saved_rooms) ? max(array_column($saved_rooms, 'rent_amount')) : 0;
            ?>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total Saved</span>
                <span class="mini-stat-val"><?php echo count($saved_rooms); ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Available Now</span>
                <span class="mini-stat-val" style="color:#16a34a;"><?php echo $availCount; ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Avg Price/Night</span>
                <span class="mini-stat-val">₱<?php echo number_format($avgPrice); ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Price Range</span>
                <span
                    class="mini-stat-val">₱<?php echo number_format($minPrice); ?>–<?php echo number_format($maxPrice); ?></span>
            </div>
        </div>

        <div class="widget-card reveal rd3">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Booking Tips
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc">Book early for the best rates — popular rooms fill fast</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot gold"></div>
                <div class="activity-desc">Earn <strong>loyalty points</strong> on every booking</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-desc">Free cancellation up to 48 hrs before check-in</div>
            </div>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<script src="../../assets/js/user-js/saved.js"></script>

<link rel="stylesheet" href="../../assets/css/user-css/user-dashboard.css" />

<script>
    window._psSessionFields = {
        fname: <?php echo json_encode($_SESSION['first_name'] ?? ''); ?>,
        lname: <?php echo json_encode($_SESSION['last_name'] ?? ''); ?>,
        email: <?php echo json_encode($_SESSION['email'] ?? ''); ?>,
        phone: <?php echo json_encode($_SESSION['phone'] ?? ''); ?>,
    };
    window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    window.psGetCsrfToken = function () { return String(window.PS_CSRF_TOKEN || ''); };
    window.psAppendCsrf = function (target) {
        const token = window.psGetCsrfToken();
        if (!token || !target || typeof target.append !== 'function') return target;
        target.append('csrf_token', token);
        return target;
    };
    window.hasActiveBooking = false;
</script>

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
                        <div class="bm-review-row"><span class="bm-review-key">Name</span><span class="bm-review-val"
                                id="rv-name">—</span></div>
                        <div class="bm-review-row"><span class="bm-review-key">Email</span><span class="bm-review-val"
                                id="rv-email">—</span></div>
                        <div class="bm-review-row"><span class="bm-review-key">Contact</span><span class="bm-review-val"
                                id="rv-phone">—</span></div>
                    </div>

                    <div class="bm-review-block">
                        <div class="bm-review-label">Reservation</div>
                        <div class="bm-review-row"><span class="bm-review-key">Unit</span><span class="bm-review-val"
                                id="rv-unit">—</span></div>
                        <div class="bm-review-row"><span class="bm-review-key">Check-in</span><span
                                class="bm-review-val" id="rv-movein">—</span></div>
                        <div class="bm-review-row"><span class="bm-review-key">Check-out</span><span
                                class="bm-review-val" id="rv-checkout">—</span></div>
                        <div class="bm-review-row"><span class="bm-review-key">Nights</span><span class="bm-review-val"
                                id="rv-nights">—</span></div>
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
                        <div class="bm-pay-option selected" data-method="gcash">
                            <div class="bm-pay-icon">📱</div>
                            <div class="bm-pay-info">
                                <div class="bm-pay-name">GCash</div>
                                <div class="bm-pay-desc">Pay via GCash online transfer</div>
                            </div>
                            <div class="bm-pay-radio"></div>
                            <span class="bm-pay-badge">Popular</span>
                        </div>
                        <div class="bm-pay-option" data-method="maya">
                            <div class="bm-pay-icon">💳</div>
                            <div class="bm-pay-info">
                                <div class="bm-pay-name">Maya</div>
                                <div class="bm-pay-desc">Pay via Maya online transfer</div>
                            </div>
                            <div class="bm-pay-radio"></div>
                        </div>
                        <div class="bm-pay-option" data-method="bank">
                            <div class="bm-pay-icon">🏦</div>
                            <div class="bm-pay-info">
                                <div class="bm-pay-name">Bank Transfer</div>
                                <div class="bm-pay-desc">Transfer via online banking</div>
                            </div>
                            <div class="bm-pay-radio"></div>
                        </div>
                        <div class="bm-pay-option" data-method="cash">
                            <div class="bm-pay-icon">💵</div>
                            <div class="bm-pay-info">
                                <div class="bm-pay-name">Cash (On-site)</div>
                                <div class="bm-pay-desc">Pay at the front desk upon check-in</div>
                            </div>
                            <div class="bm-pay-radio"></div>
                        </div>
                    </div>

                    <!-- Payment instruction area (shown for non-cash methods) -->
                    <div id="bmQrBox" class="bm-qr-wrap"
                        style="background:#f8fafc;border-radius:10px;padding:16px 18px;border:1px solid #e2e8f0;">
                        <div class="bm-qr-meta" style="width:100%;">
                            <div class="bm-qr-title" id="bmQrTitle"
                                style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:6px;">Pay via GCash
                            </div>
                            <div class="bm-qr-sub" id="bmQrSub" style="font-size:13px;color:#64748b;line-height:1.6;">
                                Send payment to <strong>+63 912 345 6789</strong> (Juan dela Cruz) and use your booking
                                reference as the note. Upload your proof of payment via the Messages page after paying.
                            </div>
                            <div class="bm-qr-amount" id="bmQrAmount"
                                style="margin-top:10px;font-size:18px;font-weight:700;color:#1e293b;">₱0</div>
                            <div class="bm-timer">
                                <div class="bm-timer-dot"></div>
                                <span id="bmTimerText">Your booking is held for <strong id="bmCountdown">30:00</strong>
                                    — complete payment to confirm.</span>
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
                    onclick="closeBookingModal();_onBookingDoneFromSaved()">
                    Done
                    <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>

        </div><!-- /bm-content -->
    </div><!-- /bm-box -->
</div><!-- /bm-overlay -->

<script>window.PS_RT_PAGE = 'saved';</script>
<?php require '../../includes/_layout_end.php'; ?>