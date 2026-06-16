<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

<link rel="stylesheet" href="../../assets/css/user-css/bookings-inline.css">
</head>
<body>

<script src="../../assets/js/user-js/bookings-inline.js"></script>
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

$page_title = 'My Bookings';
$page_hero_html = 'My <em>Bookings</em>';
$page_hero_sub = 'View, manage, and track all your reservations.';
$page_hero_icon = '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>';
$page_hero_art_id = 'bookings';
$active_nav = 'bookings';
require '../../includes/_layout.php';

require_once '../../includes/db.php';
require_once '../../lib/user-queries/bookings_queries.php';

$uid = (int) $_SESSION['user_id'];

// Fetch data using separated functions
$bStats = getBookingStats($conn, $uid);
$bookings = getUserBookings($conn, $uid);

$status_map = [
    'upcoming' => ['label' => 'Upcoming', 'class' => 'badge-blue'],
    'active' => ['label' => 'Active', 'class' => 'badge-blue'],
    'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-blue'],
    'pending' => ['label' => 'Pending', 'class' => 'badge-gold'],
    'completed' => ['label' => 'Completed', 'class' => 'badge-green'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-red'],
];
?>

<link rel="stylesheet" href="../../assets/css/user-css/booking.css">
<link rel="stylesheet" href="../../assets/css/user-css/bookings-inline.css">

<div class="summary-strip reveal">
    <div class="sstat">
        <div class="sstat-num" data-rt-stat="upcoming"><?= (int) ($bStats['upcoming'] ?? 0) ?></div>
        <div class="sstat-lbl">Upcoming</div>
    </div>
    <div class="sstat">
        <div class="sstat-num" data-rt-stat="completed"><?= (int) ($bStats['completed'] ?? 0) ?></div>
        <div class="sstat-lbl">Completed</div>
    </div>
    <div class="sstat">
        <div class="sstat-num" data-rt-stat="cancelled"><?= (int) ($bStats['cancelled'] ?? 0) ?></div>
        <div class="sstat-lbl">Cancelled</div>
    </div>
    <div class="sstat">
        <div class="sstat-num">₱<?= number_format((float) ($bStats['total_spent'] ?? 0), 0) ?></div>
        <div class="sstat-lbl">Total Spent</div>
    </div>
</div>

<?php if ($_SESSION['is_blacklisted'] ?? false): ?>
    <div
        style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <svg fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"
            style="width:20px;height:20px;flex-shrink:0;">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <div>
            <div style="font-size:13px;font-weight:700;color:#dc2626;">Your account is suspended.</div>
            <div style="font-size:12px;color:#ef4444;margin-top:2px;">You can view your existing bookings but cannot make
                new ones. <a href="support.php?suspended=1" style="color:#dc2626;font-weight:700;">Contact support</a> to
                appeal.</div>
        </div>
    </div>
<?php endif; ?>

<div class="page-two-col">

    <div class="col-main">

        <div class="tab-bar reveal rd1" id="tabBar">
            <button class="tab-btn active" onclick="filterBookings('all',this)">All</button>
            <button class="tab-btn" onclick="filterBookings('completed',this)">Completed</button>
            <button class="tab-btn" onclick="filterBookings('cancelled',this)">Cancelled</button>
        </div>

        <div id="bookingsList">
            <?php if (empty($bookings)): ?>
                <div style="text-align:center;padding:60px 20px;">
                    <div style="font-size:3rem;margin-bottom:16px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
                            stroke="var(--ink-faint)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                            <line x1="9" y1="15" x2="10" y2="15" />
                            <line x1="14" y1="15" x2="15" y2="15" />
                        </svg>
                    </div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-dark);margin-bottom:8px;">No bookings yet
                    </div>
                    <div style="color:var(--text-soft);margin-bottom:24px;">You haven't made any reservations yet.</div>
                    <a href="user-dashboard.php#account" class="btn-primary"
                        style="display:inline-block;text-decoration:none;padding:12px 28px;">Browse Units</a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $i => $b):
                    $rawSt = $b['_raw_status'];
                    $dispSt = $b['_display_status'];
                    $sInfo = $status_map[$rawSt] ?? $status_map[$dispSt] ?? ['label' => ucfirst($rawSt), 'class' => 'badge-blue'];
                    $delay = $i < 3 ? " rd{$i}" : '';
                    $imgSrc = !empty($b['img_path']) ? '../../' . ltrim($b['img_path'], '/') : '';
                    $floorLabel = $b['floor']
                        ? 'Floor ' . $b['floor'] . ' · ' . htmlspecialchars($b['property_name'])
                        : htmlspecialchars($b['property_name']);
                    $checkinFmt = date('M d, Y', strtotime($b['checkin_date']));
                    $checkoutFmt = date('M d, Y', strtotime($b['checkout_date']));
                    $bookingRef = 'BK-' . str_pad($b['booking_id'], 6, '0', STR_PAD_LEFT);
                    $isCancelled = $rawSt === 'cancelled';

                    // Only pending and confirmed (within 48hrs) can be cancelled by guest
                    // Active bookings cannot be cancelled — guest is already checked in
                    $canCancel = false;
                    if ($rawSt === 'pending') {
                        $canCancel = true;
                    } elseif ($rawSt === 'confirmed') {
                        $confirmedAt = !empty($b['confirmed_at']) ? strtotime($b['confirmed_at']) : strtotime($b['updated_at']);
                        $hoursSinceConfirmed = (time() - $confirmedAt) / 3600;
                        $canCancel = $hoursSinceConfirmed <= 48;
                    }

                    $jsObj = json_encode([
                        'id' => $bookingRef,
                        'booking_id' => (int) $b['booking_id'],
                        'room' => $b['room_name'],
                        'floor' => $floorLabel,
                        'checkin' => $checkinFmt,
                        'checkout' => $checkoutFmt,
                        'nights' => (int) $b['nights'],
                        'total' => (float) $b['total_amount'],
                        'status' => $rawSt,
                        'reviewed' => !empty($b['review_rating']),
                        'review_rating' => (int) ($b['review_rating'] ?? 0),
                        'img' => $imgSrc,
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                    <div class="booking-card reveal<?= $delay ?><?= $isCancelled ? ' cancelled' : '' ?>"
                        data-status="<?= $dispSt ?>" data-booking-id="<?= $b['booking_id'] ?>" data-idx="<?= $i ?>"
                        data-raw-status="<?= $rawSt ?>" data-checkin="<?= $b['checkin_date'] ?>"
                        data-checkout="<?= $b['checkout_date'] ?>" data-nights="<?= (int) $b['nights'] ?>"
                        data-total="<?= (float) $b['total_amount'] ?>"
                        data-reviewed="<?= !empty($b['review_rating']) ? '1' : '0' ?>"
                        data-review-rating="<?= (int) ($b['review_rating'] ?? 0) ?>">
                        <div class="bc-top">
                            <div class="bc-img">
                                <!-- Mobile: status badge overlaid on upper-right of image -->
                                <span class="bc-img-badge badge <?= $sInfo['class'] ?>"
                                    data-status="<?= $rawSt ?>"><?= $sInfo['label'] ?></span>
                                <?php if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($b['room_name']) ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                <?php endif; ?>
                                <div class="bc-img-fallback"
                                    style="<?= $imgSrc ? 'display:none;' : '' ?>background:linear-gradient(145deg,#dbeafe,#3b82f6,#1a3d7c);">
                                </div>
                            </div>
                            <div class="bc-body">
                                <!-- Row 1: bc-head (desktop) -->
                                <div class="bc-head">
                                    <div>
                                        <div class="bc-room"><?= htmlspecialchars($b['room_name']) ?></div>
                                        <div class="bc-floor">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="11" height="11"
                                                fill="currentColor" stroke="none">
                                                <path
                                                    d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" />
                                            </svg>
                                            <?= $floorLabel ?>
                                        </div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                        <span class="badge <?= $sInfo['class'] ?> booking-status-badge"
                                            data-status="<?= $rawSt ?>"
                                            data-prev-status="<?= $rawSt ?>"><?= $sInfo['label'] ?></span>
                                        <span class="bc-id"><?= $bookingRef ?></span>
                                    </div>
                                </div>
                                <!-- Mobile row 1: location + booking ID -->
                                <div class="bc-location-row">
                                    <div class="bc-floor">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="10" height="10"
                                            fill="currentColor" stroke="none">
                                            <path
                                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" />
                                        </svg>
                                        <?= $floorLabel ?>
                                    </div>
                                    <span class="bc-id"><?= $bookingRef ?></span>
                                </div>
                                <!-- Row 2: dates -->
                                <div class="bc-dates">
                                    <div class="bc-date-item">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        <span class="bc-dl">Check-in:</span> <strong
                                            data-field="checkin"><?= $checkinFmt ?></strong>
                                    </div>
                                    <div class="bc-date-sep"></div>
                                    <div class="bc-date-item">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        <span class="bc-dl">Check-out:</span> <strong
                                            data-field="checkout"><?= $checkoutFmt ?></strong>
                                    </div>
                                    <span class="bc-nights" data-field="nights"><?= (int) $b['nights'] ?> nights</span>
                                </div>
                            </div>
                        </div>
                        <div class="bc-foot">
                            <div class="bc-price" data-field="price">₱<?= number_format((float) $b['total_amount']) ?>
                                <sub>total</sub>
                            </div>
                            <div class="bc-actions booking-actions">
                                <?php if (in_array($rawSt, ['confirmed', 'pending', 'active', 'completed'])): ?>
                                    <?php if (in_array($rawSt, ['confirmed', 'completed'])): ?>
                                        <button class="bc-btn-receipt" id="receipt-btn-<?= $b['booking_id'] ?>"
                                            onclick="downloadReceipt(<?= $b['booking_id'] ?>, this)" title="Download Receipt as PDF">
                                            <svg viewBox="0 0 24 24"
                                                style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0;">
                                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                                <polyline points="14 2 14 8 20 8" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <polyline points="10 9 9 9 8 9" />
                                            </svg>
                                            Receipt
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($rawSt === 'completed'): ?>
                                        <?php if (!empty($b['review_rating'])): ?>
                                            <button class="bc-btn-ghost" style="cursor:default;opacity:0.7;" disabled>
                                                Reviewed · <?= (int) $b['review_rating'] ?>/5
                                            </button>
                                        <?php else: ?>
                                            <button class="bc-btn-ghost"
                                                onclick="openReviewModal('<?= addslashes($b['room_name']) ?>', <?= (int) $b['booking_id'] ?>, <?= $i ?>)">Leave
                                                a Review</button>
                                        <?php endif; ?>
                                        <button class="bc-btn-primary"
                                            onclick="openRebookModal('<?= addslashes($b['room_name']) ?>')">Book Again</button>
                                    <?php else: ?>
                                        <?php if ($canCancel): ?>
                                            <button class="bc-btn-danger" data-action="cancel"
                                                onclick="openCancelModal(<?= $i ?>, '<?= $bookingRef ?>')">Cancel</button>
                                        <?php elseif ($rawSt === 'active'): ?>
                                            <span class="bc-btn-ghost" style="cursor:default;opacity:0.5;font-size:12px;"
                                                title="Cannot cancel an active booking">Active stay</span>
                                        <?php else: ?>
                                            <span class="bc-no-cancel" title="Cancellation window has passed">
                                                <svg viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                                </svg>
                                                No cancellation
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($rawSt === 'cancelled'): ?>
                                        <span class="bc-status-pill bc-status-cancelled">
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor"
                                                stroke-width="2.5">
                                                <circle cx="12" cy="12" r="10" />
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                            </svg>
                                            Cancelled
                                        </span>
                                    <?php elseif ($rawSt === 'completed'): ?>
                                        <span class="bc-status-pill bc-status-completed">
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor"
                                                stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            Completed
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <script>
                            window.__bookings = window.__bookings || [];
                            window.__bookings[<?= $i ?>] = <?= $jsObj ?>;
                        </script>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- <div id="bookingsEmptyState" style="display:none;text-align:center;padding:60px 20px;">
            <div style="margin-bottom:16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
                    stroke="var(--ink-faint)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                    <line x1="9" y1="15" x2="10" y2="15" />
                    <line x1="14" y1="15" x2="15" y2="15" />
                </svg>
            </div>
            <div style="font-size:18px;font-weight:700;color:var(--ink);margin-bottom:8px;">No bookings found</div>
            <div style="color:var(--ink-soft);margin-bottom:24px;">You don't have any bookings in this category yet.
            </div>
        </div> -->

        <div id="paginationBar"></div>
    </div><!-- /col-main -->

    <!-- ── Bookings Sidebar ── -->
    <div class="col-side">

        <div class="tip-card reveal rd1">
            <div class="tip-card-label">✈️ Need help?</div>
            <div class="tip-card-title">Modify or cancel anytime</div>
            <div class="tip-card-body">Free cancellation up to 48 hours before check-in. Modifications up to 72 hours
                before.</div>
            <a href="support.php" class="tip-card-cta">Contact Support →</a>
        </div>

        <div class="widget-card reveal rd2">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                Booking Summary
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Upcoming</span>
                <span class="mini-stat-val" data-rt-stat="upcoming"><?= (int) ($bStats['upcoming'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Completed</span>
                <span class="mini-stat-val" data-rt-stat="completed"><?= (int) ($bStats['completed'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Cancelled</span>
                <span class="mini-stat-val" data-rt-stat="cancelled"><?= (int) ($bStats['cancelled'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total Spent</span>
                <span class="mini-stat-val">₱<?= number_format((float) ($bStats['total_spent'] ?? 0), 0) ?></span>
            </div>
        </div>

        <div class="widget-card reveal rd3">
            <div class="widget-title">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Policies
            </div>
            <div class="activity-item">
                <div class="activity-dot green"></div>
                <div class="activity-desc"><strong>Free cancellation</strong> up to 48 hrs before check-in</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot gold"></div>
                <div class="activity-desc"><strong>50% fee</strong> for cancellations within 48 hrs</div>
            </div>
            <div class="activity-item">
                <div class="activity-dot red"></div>
                <div class="activity-desc"><strong>No-shows</strong> are charged in full</div>
            </div>
        </div>

    </div><!-- /col-side -->
</div><!-- /page-two-col -->

<div class="modal-overlay" id="detailsModal">
    <div class="modal-box" style="max-width:560px;">
        <button class="modal-close-btn" onclick="closeModal('detailsModal')">✕</button>
        <div class="modal-title" id="detailsRoomName"></div>
        <div class="modal-sub" id="detailsBookingId"></div>
        <div id="detailsBody" style="margin-bottom:22px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeModal('detailsModal')">Close</button>
            <button class="btn-primary" onclick="downloadInvoiceFromModal()">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Download Invoice
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="reviewModal">
    <div class="modal-box" style="max-width:480px;">
        <button class="modal-close-btn" onclick="closeModal('reviewModal')">✕</button>

        <!-- Header -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
            <div
                style="width:42px;height:42px;border-radius:12px;background:var(--teal-lt, #edf7f2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:#e8c882;stroke:#c9a84c;stroke-width:1.5;">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
            </div>
            <div>
                <div class="modal-title" style="margin-bottom:0;">Leave a Review</div>
                <div class="modal-sub" id="reviewRoomName" style="margin-bottom:0;"></div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border, #e2e8f0);margin:16px 0;">

        <!-- Star Rating -->
        <div style="margin-bottom:20px;">
            <div
                style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:10px;">
                Your Rating</div>
            <div id="starRating" style="display:flex;gap:8px;align-items:center;">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <svg data-val="<?= $s ?>" onclick="setRating(<?= $s ?>)" viewBox="0 0 24 24"
                        style="width:36px;height:36px;fill:#e2e8f0;stroke:#cbd5e1;stroke-width:1.5;cursor:pointer;transition:fill 0.15s,transform 0.15s,filter 0.15s;"
                        onmouseover="hoverRating(<?= $s ?>)" onmouseout="resetHover()">
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                <?php endfor; ?>
            </div>
            <div id="ratingLabel"
                style="font-size:0.78rem;color:var(--ink-soft,#64748b);margin-top:8px;min-height:18px;font-style:italic;">
            </div>
        </div>

        <!-- Review Text -->
        <div class="form-field" style="margin-bottom:18px;">
            <label
                style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);">Your
                Review</label>
            <textarea id="reviewText" placeholder="Share your experience — what did you love? Any suggestions?"
                style="min-height:110px;resize:vertical;"></textarea>
            <div style="text-align:right;font-size:0.7rem;color:var(--ink-faint,#94a3b8);margin-top:4px;">
                <span id="reviewCharCount">0</span> / 500
            </div>
        </div>

        <!-- Error -->
        <div id="reviewError"
            style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
            <button class="btn-primary" id="submitReviewBtn" onclick="submitReview()"
                style="display:flex;align-items:center;gap:6px;">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
                Submit Review
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="rebookModal">
    <div class="modal-box" style="max-width:460px;">
        <button class="modal-close-btn" onclick="closeModal('rebookModal')">✕</button>
        <div class="modal-title">Book Again</div>
        <div class="modal-sub" id="rebookRoomName"></div>
        <div class="form-grid" style="margin-bottom:14px;">
            <div class="form-field"><label>Check-in Date</label><input type="date" id="rebook_checkin"></div>
            <div class="form-field"><label>Check-out Date</label><input type="date" id="rebook_checkout"></div>
        </div>
        <div class="form-field" style="margin-bottom:18px;">
            <label>Guests</label>
            <select id="rebook_guests">
                <option value="1">1 Guest</option>
                <option value="2" selected>2 Guests</option>
                <option value="3">3 Guests</option>
                <option value="4">4 Guests</option>
            </select>
        </div>
        <div id="rebookError"
            style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeModal('rebookModal')">Cancel</button>
            <button class="btn-primary" id="rebookConfirmBtn" onclick="confirmRebook()">
                <!-- <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg> -->
                Confirm Booking
            </button>
        </div>
    </div>
</div>

<script src="../../assets/js/user-js/bookings.js"
    onerror="this.remove(); var s=document.createElement('script'); s.src='../../assets/js/user-js/booking.js'; document.body.appendChild(s);">
    </script>

<script>
    // ── Pagination ──
    const ITEMS_PER_PAGE = 4;
    let currentPage = 1;
    let currentFilter = 'all';

    function getAllCards() {
        return Array.from(document.querySelectorAll('.booking-card'));
    }

    function getFilteredCards() {
        return getAllCards().filter(card => {
            if (currentFilter === 'all') return true;
            return card.dataset.status === currentFilter;
        });
    }

    function renderPagination() {
        const filtered = getFilteredCards();
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / ITEMS_PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;

        // Show/hide cards
        getAllCards().forEach(c => c.style.display = 'none');
        const emptyState = document.getElementById('bookingsEmptyState');
        const bar = document.getElementById('paginationBar');
        if (total === 0) {
            if (emptyState) emptyState.style.display = '';
            if (bar) bar.innerHTML = '';
            return;
        }
        if (emptyState) emptyState.style.display = 'none';
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        filtered.slice(start, start + ITEMS_PER_PAGE).forEach(c => c.style.display = '');
        if (!bar) return;
        if (totalPages <= 1) { bar.innerHTML = ''; return; }

        let html = '<div class="pg-bar">';
        html += `<button class="pg-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&#8592;</button>`;
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pg-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
        }
        html += `<button class="pg-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>&#8594;</button>`;
        html += '</div>';
        html += `<div class="pg-info">Showing ${Math.min(start + 1, total)}–${Math.min(start + ITEMS_PER_PAGE, total)} of ${total}</div>`;
        bar.innerHTML = html;
    }

    function goPage(n) {
        const filtered = getFilteredCards();
        const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
        currentPage = Math.max(1, Math.min(n, totalPages));
        renderPagination();
        document.getElementById('bookingsList').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Override filterBookings (defined after bookings.js so this wins)
    function filterBookings(status, btn) {
        currentFilter = status;
        currentPage = 1;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        renderPagination();
    }

    // ── PDF Receipt Download (no new tab) ──
    let _pdfLibsLoaded = false;

    function _loadPdfLibs() {
        if (_pdfLibsLoaded) return Promise.resolve();
        return new Promise((resolve, reject) => {
            const s1 = document.createElement('script');
            s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            s1.onload = () => {
                const s2 = document.createElement('script');
                s2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                s2.onload = () => { _pdfLibsLoaded = true; resolve(); };
                s2.onerror = reject;
                document.head.appendChild(s2);
            };
            s1.onerror = reject;
            document.head.appendChild(s1);
        });
    }

    async function downloadReceipt(bookingId, btn) {
        const svgSpinner = `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;animation:spin .7s linear infinite;flex-shrink:0;"><circle cx="12" cy="12" r="10" stroke-opacity=".3"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>`;
        const svgDoc = `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
        const origHTML = btn ? btn.innerHTML : '';

        if (btn) { btn.disabled = true; btn.innerHTML = svgSpinner + ' Generating…'; }

        try {
            await _loadPdfLibs();

            // Fetch receipt HTML (view=1 suppresses auto-download inside that page)
            const res = await fetch('../../api/user/booking_receipt.php?booking_id=' + bookingId + '&view=1', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Fetch failed ' + res.status);
            const html = await res.text();

            // Parse into a detached DOM to extract receipt card
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const card = doc.getElementById('receiptCard');
            if (!card) throw new Error('receiptCard not found');

            // Collect all stylesheets from the receipt page and inline them
            const stylePromises = [];
            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                const href = link.getAttribute('href');
                if (!href) return;
                const base = new URL('../../api/user/booking_receipt.php', window.location.href);
                const absHref = new URL(href, base).href;
                stylePromises.push(
                    fetch(absHref).then(r => r.text()).catch(() => '')
                );
            });

            // Grab any inline styles from the receipt doc
            let inlineStyles = '';
            doc.querySelectorAll('style').forEach(s => { inlineStyles += s.textContent; });

            const sheetTexts = await Promise.all(stylePromises);
            const allCss = sheetTexts.join('\n') + '\n' + inlineStyles;

            // Mount card in a hidden iframe for accurate rendering
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:900px;height:1px;border:0;visibility:hidden;';
            document.body.appendChild(iframe);

            const idoc = iframe.contentDocument || iframe.contentWindow.document;
            idoc.open();
            idoc.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
                <style>${allCss}</style>
                </head><body style="margin:0;padding:24px;background:#f8fafc;">${card.outerHTML}</body></html>`);
            idoc.close();

            // Wait for fonts/images to render
            await new Promise(r => setTimeout(r, 600));

            const iCard = idoc.getElementById('receiptCard') || idoc.body.firstElementChild;
            const canvas = await html2canvas(iCard, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true,
            });

            document.body.removeChild(iframe);

            const { jsPDF } = window.jspdf;
            const imgData = canvas.toDataURL('image/png');
            const pdfW = 210;
            const pdfH = (canvas.height * pdfW) / canvas.width;
            const pdf = new jsPDF({
                orientation: pdfH > pdfW ? 'portrait' : 'landscape',
                unit: 'mm',
                format: pdfH <= 297 ? 'a4' : [pdfW, pdfH],
            });
            pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
            pdf.save('Receipt-BK-' + String(bookingId).padStart(6, '0') + '.pdf');

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = svgDoc + ' Downloaded!';
                setTimeout(() => { btn.innerHTML = svgDoc + ' Receipt'; }, 3000);
            }
        } catch (err) {
            console.error('Receipt download failed:', err);
            if (typeof showToast === 'function') showToast('Could not generate receipt PDF. Try again.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
        }
    }

    document.addEventListener('DOMContentLoaded', () => { renderPagination(); });
</script>
<script>window.PS_RT_PAGE = 'bookings';</script>
<?php require '../../includes/_layout_end.php'; ?>