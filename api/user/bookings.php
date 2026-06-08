<?php
include '../../includes/session.php';
if ($_SESSION['role'] !== 'user') {
    echo '<!DOCTYPE html>
<html>
<head>

</head>
<body>

<script src="../../assets/js/user-js/api-bookings-inline.js"></script>
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
$active_nav = 'bookings';
require '../../includes/_layout.php';

require_once '../../includes/db.php';
$uid = (int) $_SESSION['user_id'];

// Stats
$bStats = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT
        SUM(status IN('confirmed','pending'))                AS upcoming,
        SUM(status='active')                                 AS active_cnt,
        SUM(status='completed')                              AS completed,
        SUM(status='cancelled')                              AS cancelled,
        COALESCE(SUM(CASE WHEN status NOT IN('cancelled') THEN total_amount END),0) AS total_spent
     FROM bookings WHERE user_id=$uid"
));

// Bookings
$bRes = mysqli_query(
    $conn,
    "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.payment_method, b.created_at,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            COALESCE(u.unit_name, u.unit_number, 'Unit') AS room_name,
            COALESCE(p.property_name,'') AS property_name,
            u.floor,
            br.rating AS review_rating,
            br.comment AS review_comment,
            (SELECT ui.image_path FROM unit_images ui
             WHERE ui.unit_id=u.unit_id ORDER BY ui.sort_order, ui.image_id LIMIT 1) AS img_path
     FROM bookings b
     JOIN units u ON u.unit_id=b.unit_id
     LEFT JOIN properties p ON p.property_id=u.property_id
     LEFT JOIN booking_reviews br ON br.booking_id=b.booking_id AND br.user_id=$uid
     WHERE b.user_id=$uid
     ORDER BY b.created_at DESC"
);

$bookings = [];
while ($r = mysqli_fetch_assoc($bRes)) {
    $st = $r['status'];
    if (in_array($st, ['confirmed', 'pending', 'active']))
        $st = 'upcoming';
    $r['_display_status'] = $st;
    $r['_raw_status'] = $r['status'];
        fmt_dt_row($r);
    $bookings[] = $r;
}

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

<div class="page-two-col">
    <div class="col-main">

        <div class="tab-bar reveal rd1" id="tabBar">
            <button class="tab-btn active" onclick="filterBookings('all',this)">All</button>
            <button class="tab-btn" onclick="filterBookings('upcoming',this)">Upcoming</button>
            <button class="tab-btn" onclick="filterBookings('completed',this)">Completed</button>
            <button class="tab-btn" onclick="filterBookings('cancelled',this)">Cancelled</button>
        </div>

        <div id="bookingsList">
            <?php if (empty($bookings)): ?>
                <div style="text-align:center;padding:60px 20px;">
                    <div style="font-size:3rem;margin-bottom:16px;">🗓️</div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-dark);margin-bottom:8px;">No bookings yet</div>
                    <div style="color:var(--text-soft);margin-bottom:24px;">You haven't made any reservations yet.</div>
                    <a href="../../index.php" class="btn-primary"
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
                        'review_rating' => (int)($b['review_rating'] ?? 0),
                        'img' => $imgSrc,
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                    <div class="booking-card reveal<?= $delay ?><?= $isCancelled ? ' cancelled' : '' ?>" data-status="<?= $dispSt ?>"
                        data-booking-id="<?= $b['booking_id'] ?>"
                        data-idx="<?= $i ?>">
                        <div class="bc-top">
                            <div class="bc-img">
                                <?php if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($b['room_name']) ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                <?php endif; ?>
                                <div class="bc-img-fallback"
                                    style="<?= $imgSrc ? 'display:none;' : '' ?>background:linear-gradient(145deg,#dbeafe,#3b82f6,#1a3d7c);">
                                </div>
                            </div>
                            <div class="bc-body">
                                <div class="bc-head">
                                    <div>
                                        <div class="bc-room"><?= htmlspecialchars($b['room_name']) ?></div>
                                        <div class="bc-floor"><?= $floorLabel ?></div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span class="badge <?= $sInfo['class'] ?> booking-status-badge" data-status="<?= $rawSt ?>"><?= $sInfo['label'] ?></span>
                                        <span class="bc-id"><?= $bookingRef ?></span>
                                    </div>
                                </div>
                                <div class="bc-dates">
                                    <div class="bc-date-item">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        Check-in: <strong><?= $checkinFmt ?></strong>
                                    </div>
                                    <div class="bc-date-sep"></div>
                                    <div class="bc-date-item">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="4" width="18" height="18" rx="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                        Check-out: <strong><?= $checkoutFmt ?></strong>
                                    </div>
                                    <span class="bc-nights"><?= (int) $b['nights'] ?> nights</span>
                                </div>
                            </div>
                        </div>
                        <div class="bc-foot">
                            <div class="bc-price">₱<?= number_format((float) $b['total_amount']) ?> <sub>total</sub></div>
                            <div class="bc-actions booking-actions">
                                <?php if (in_array($rawSt, ['confirmed', 'pending', 'active'])): ?>
                                    <!-- <button class="bc-btn-ghost" onclick="openDetailsModal(<?= $i ?>)">View Details</button> -->
                                    <a href="../../api/user/booking_receipt.php?booking_id=<?= $b['booking_id'] ?>" target="_blank" class="bc-btn-ghost">🧾 Receipt</a>
                                    <button class="bc-btn-danger" data-action="cancel" onclick="openCancelModal(<?= $i ?>, '<?= $bookingRef ?>')">Cancel</button>
                                <?php elseif ($rawSt === 'completed'): ?>
                                    <a href="../../api/user/booking_receipt.php?booking_id=<?= $b['booking_id'] ?>" target="_blank" class="bc-btn-ghost">🧾 Receipt</a>
                                    <?php if (!empty($b['review_rating'])): ?>
                                        <button class="bc-btn-ghost" style="cursor:default;opacity:0.7;" disabled>
                                            Reviewed · <?= (int)$b['review_rating'] ?>/5
                                        </button>
                                    <?php else: ?>
                                        <button class="bc-btn-ghost" onclick="openReviewModal('<?= addslashes($b['room_name']) ?>', <?= (int)$b['booking_id'] ?>, <?= $i ?>)">Leave a
                                            Review</button>
                                    <?php endif; ?>
                                    <button class="bc-btn-primary" onclick="openRebookModal('<?= addslashes($b['room_name']) ?>')">Book
                                        Again</button>
                                <?php else: ?>
                                    <button class="bc-btn-ghost" style="cursor:default;opacity:0.45;" disabled>Cancelled</button>
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

    </div><!-- /col-main -->

    <!-- ── Bookings Sidebar ── -->
    <div class="col-side">

        <div class="tip-card reveal rd1">
            <div class="tip-card-label">✈️ Need help?</div>
            <div class="tip-card-title">Modify or cancel anytime</div>
            <div class="tip-card-body">Free cancellation up to 48 hours before check-in. Modifications up to 72 hours before.</div>
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
                <span class="mini-stat-val" data-rt-stat="upcoming"><?= (int)($bStats['upcoming'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Completed</span>
                <span class="mini-stat-val" data-rt-stat="completed"><?= (int)($bStats['completed'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Cancelled</span>
                <span class="mini-stat-val" data-rt-stat="cancelled"><?= (int)($bStats['cancelled'] ?? 0) ?></span>
            </div>
            <div class="mini-stat-row">
                <span class="mini-stat-label">Total Spent</span>
                <span class="mini-stat-val">₱<?= number_format((float)($bStats['total_spent'] ?? 0), 0) ?></span>
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
            <div class="activity-item">
                <div class="activity-dot"></div>
                <div class="activity-desc">Check-in: <strong>2:00 PM</strong> · Check-out: <strong>12:00 PM</strong></div>
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
        <div class="modal-title">Leave a Review</div>
        <div class="modal-sub" id="reviewRoomName"></div>
        <div style="margin-bottom:18px;">
            <div
                style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:10px;">
                Your Rating</div>
            <div id="starRating" style="display:flex;gap:6px;">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <svg data-val="<?= $s ?>" onclick="setRating(<?= $s ?>)" viewBox="0 0 24 24"
                        style="width:32px;height:32px;fill:var(--blue-100);stroke:var(--blue-200);stroke-width:1.5;cursor:pointer;transition:fill 0.15s,transform 0.15s;"
                        onmouseover="hoverRating(<?= $s ?>)" onmouseout="resetHover()">
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                <?php endfor; ?>
            </div>
        </div>
        <div class="form-field" style="margin-bottom:18px;">
            <label>Your Review</label>
            <textarea id="reviewText" placeholder="Share your experience…"></textarea>
        </div>
        <div id="reviewError"
            style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
            <button class="btn-primary" id="submitReviewBtn" onclick="submitReview()">
                <!-- <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg> -->
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

<?php require '../../includes/_layout_end.php'; ?>