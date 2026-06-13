<?php
/**
 * Unit Detail — SQL Queries
 * Path: pages/user/unit_detail_queries.php
 *
 * Requires: $conn, $unit_id, $_uid, $reviewPage, $reviewLimit, $reviewOffset
 * All variables are made available to the including file via the shared scope.
 */

// ── 1. Unit (with property info + average rating) ─────────────────────────────
$unit = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT u.*, p.property_name, p.address, p.city, p.latitude, p.longitude,
            ROUND(AVG(r.rating),1) AS rating,
            CASE
                WHEN EXISTS (SELECT 1 FROM bookings b WHERE b.unit_id = u.unit_id AND b.status = 'active'      LIMIT 1) THEN 'occupied'
                WHEN EXISTS (SELECT 1 FROM bookings b WHERE b.unit_id = u.unit_id AND b.status = 'confirmed'   LIMIT 1) THEN 'booked'
                ELSE u.status
            END AS real_status
     FROM units u
     LEFT JOIN properties p ON p.property_id = u.property_id
     LEFT JOIN booking_reviews r ON r.unit_id = u.unit_id
     WHERE u.unit_id = $unit_id
     GROUP BY u.unit_id LIMIT 1"
));
if (!$unit) {
    header('Location: user-dashboard.php');
    exit;
}
// Use real_status as the working status throughout this page
$unit['status'] = $unit['real_status'] ?? $unit['status'];

// ── 2. Unit images ────────────────────────────────────────────────────────────
$_imagesRes = mysqli_query(
    $conn,
    "SELECT image_path FROM unit_images WHERE unit_id=$unit_id ORDER BY image_id ASC"
);
$images = [];
while ($row = mysqli_fetch_assoc($_imagesRes))
    $images[] = '../../' . ltrim($row['image_path'], '/');

// ── 3. Amenities ──────────────────────────────────────────────────────────────
$_amenRes = mysqli_query(
    $conn,
    "SELECT a.name FROM unit_amenities ua
     JOIN amenities a ON a.amenity_id = ua.amenity_id
     WHERE ua.unit_id = $unit_id ORDER BY a.name ASC"
);
$amenities = [];
while ($row = mysqli_fetch_assoc($_amenRes))
    $amenities[] = $row['name'];

// ── 4. Total review count (for pagination) ────────────────────────────────────
$totalReviews = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM booking_reviews WHERE unit_id=$unit_id"
))['c'] ?? 0);
$totalReviewPages = max(1, (int) ceil($totalReviews / $reviewLimit));

// ── 5. Paginated reviews ──────────────────────────────────────────────────────
$_reviewsRes = mysqli_query(
    $conn,
    "SELECT r.rating, r.comment, r.created_at,
            CONCAT(u.first_name,' ',LEFT(u.last_name,1),'.') AS reviewer,
            u.profile_photo AS reviewer_photo
     FROM booking_reviews r
     JOIN users u ON u.user_id = r.user_id
     WHERE r.unit_id = $unit_id ORDER BY r.created_at DESC
     LIMIT $reviewLimit OFFSET $reviewOffset"
);
$reviews = [];
while ($row = mysqli_fetch_assoc($_reviewsRes))
    $reviews[] = $row;

// ── 6. Is unit saved by current user? ────────────────────────────────────────
$isSaved = (bool) mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT 1 FROM saved_units WHERE user_id=$_uid AND unit_id=$unit_id LIMIT 1"
));

// ── 7. Does user have an active booking for this unit? ────────────────────────
$hasActiveBooking = (bool) mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT 1 FROM bookings WHERE user_id=$_uid AND unit_id=$unit_id
     AND status NOT IN ('cancelled','completed') LIMIT 1"
));

// ── 8. Booked date ranges (to disable in calendar) ───────────────────────────
$_bookedDatesRes = mysqli_query(
    $conn,
    "SELECT checkin_date, checkout_date FROM bookings
     WHERE unit_id=$unit_id AND status NOT IN ('cancelled','completed')
     AND checkout_date >= CURDATE()
     ORDER BY checkin_date ASC"
);
$bookedRanges = [];
while ($bdr = mysqli_fetch_assoc($_bookedDatesRes))
    $bookedRanges[] = ['from' => $bdr['checkin_date'], 'to' => $bdr['checkout_date']];

// ── 9. Admin-blocked dates ────────────────────────────────────────────────────
$_blockedRes = mysqli_query(
    $conn,
    "SELECT blocked_date FROM blocked_dates
     WHERE blocked_date >= CURDATE()
     ORDER BY blocked_date ASC"
);
$adminBlockedDates = [];
while ($bdr = mysqli_fetch_assoc($_blockedRes))
    $adminBlockedDates[] = $bdr['blocked_date'];

// ── 10. Most popular payment method ──────────────────────────────────────────
$_pmRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT payment_method FROM bookings
     WHERE payment_method IS NOT NULL AND payment_method!='' AND status!='cancelled'
     GROUP BY payment_method ORDER BY COUNT(*) DESC LIMIT 1"
));
$popularPaymentMethod = $_pmRow['payment_method'] ?? null;

// ── 11. Booking count for this unit (social proof) ───────────────────────────
$_bookingCountRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings
     WHERE unit_id = $unit_id AND status NOT IN ('cancelled') LIMIT 1"
));
$bookingCount = (int) ($_bookingCountRow['c'] ?? 0);

// ── 12. Overall rating breakdown ─────────────────────────────────────────────
$ratingBreakdown = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT ROUND(AVG(rating), 1) AS overall FROM booking_reviews WHERE unit_id = $unit_id"
));

// ── Star distribution (for filter UI) ────────────────────────────────────────
$_starRes = mysqli_query(
    $conn,
    "SELECT ROUND(rating) AS star, COUNT(*) AS cnt
     FROM booking_reviews WHERE unit_id = $unit_id
     GROUP BY ROUND(rating) ORDER BY star DESC"
);
$starDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
while ($sr = mysqli_fetch_assoc($_starRes))
    $starDist[(int) $sr['star']] = (int) $sr['cnt'];

// ── 13. Similar units ─────────────────────────────────────────────────────────
$_similarRes = mysqli_query(
    $conn,
    "SELECT u.unit_id, u.unit_name, u.unit_number, u.unit_type, u.rent_amount,
        u.bedrooms AS num_beds, u.bathrooms AS num_baths, u.max_guests, u.status,
        u.description,
        p.property_name,
        (SELECT image_path FROM unit_images WHERE unit_id=u.unit_id ORDER BY image_id ASC LIMIT 1) AS img,
        ROUND(AVG(r.rating),1) AS rating,
        (SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR '||' )
         FROM unit_amenities ua JOIN amenities a ON a.amenity_id = ua.amenity_id
         WHERE ua.unit_id = u.unit_id LIMIT 4) AS amenities_list
     FROM units u
     LEFT JOIN properties p ON p.property_id = u.property_id
     LEFT JOIN booking_reviews r ON r.unit_id = u.unit_id
     WHERE u.unit_id != $unit_id
     GROUP BY u.unit_id
     ORDER BY
       CASE WHEN u.property_id = {$unit['property_id']} THEN 0 ELSE 1 END,
       CASE WHEN u.status = 'vacant' THEN 0 ELSE 1 END,
       u.rent_amount ASC
     LIMIT 3"
);
$similarUnits = [];
while ($row = mysqli_fetch_assoc($_similarRes))
    $similarUnits[] = $row;

// ── 14. Sidebar nav — active booking count ───────────────────────────────────
$_activeBookingCount = (function () use ($conn, $_uid) {
    if (!$conn)
        return null;
    $r = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$_uid AND status IN('pending','confirmed','active')"
    ));
    return $r['c'] > 0 ? (string) $r['c'] : null;
})();

// ── 15. Sidebar nav — saved units count ──────────────────────────────────────
$_savedCount = (function () use ($conn, $_uid) {
    if (!$conn)
        return null;
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM saved_units WHERE user_id=$_uid"));
    return $r['c'] > 0 ? (string) $r['c'] : null;
})();

// ── 16. Sidebar nav — loyalty points + tier ──────────────────────────────────
$_loyaltySub = (function () use ($conn, $_uid) {
    if (!$conn)
        return 'Earn points every stay';
    $r = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$_uid"
    ));
    $pts = max(0, (int) $r['v']);
    $tier = $pts >= 5000 ? 'Diamond' : ($pts >= 2000 ? 'Platinum' : ($pts >= 500 ? 'Gold' : 'Silver'));
    return number_format($pts) . ' pts · ' . $tier . ' tier';
})();