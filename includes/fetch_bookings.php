<?php
// ============================================================
//  includes/fetch_bookings.php
//  Fetches booking history for the currently logged-in user
// ============================================================
include_once __DIR__ . '/db.php';

$userId = (int) $_SESSION['user_id'];   // adjust key if your session uses a different name

// ── ACTIVE BOOKING (banner at top) ──────────────────────────
$activeSql = "
    SELECT
        b.booking_id,
        b.unit_id,
        b.checkin_date,
        b.checkout_date,
        b.status,
        b.guests,
        b.total_amount,
        u.unit_name,
        u.unit_number,
        p.property_name,
        p.address,
        p.latitude,
        p.longitude,
        (
            SELECT ui.image_path
            FROM unit_images ui
            WHERE ui.unit_id = b.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM   bookings b
    JOIN   units u      ON u.unit_id      = b.unit_id
    JOIN   properties p ON p.property_id  = u.property_id
    WHERE  b.user_id = $userId
      AND (
          b.status IN ('confirmed', 'active')
          OR (b.status = 'pending' AND b.payment_method = 'cash')
      )
    ORDER  BY b.checkin_date ASC
    LIMIT  1
";
$activeResult = mysqli_query($conn, $activeSql);
$activeBooking = mysqli_fetch_assoc($activeResult);

// ── BOOKING HISTORY ──────────────────────────────────────────
$historySql = "
    SELECT
        b.booking_id,
        b.checkin_date,
        b.checkout_date,
        b.status,
        b.total_amount,
        b.guests,
        u.unit_name,
        u.unit_number,
        p.property_name,
        (
            SELECT ui.image_path
            FROM   unit_images ui
            WHERE  ui.unit_id = b.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM   bookings b
    JOIN   units u      ON u.unit_id      = b.unit_id
    JOIN   properties p ON p.property_id  = u.property_id
    WHERE  b.user_id = $userId
    ORDER  BY b.created_at DESC
";
$historyResult = mysqli_query($conn, $historySql);
$bookingHistory = [];
while ($row = mysqli_fetch_assoc($historyResult)) {
    $bookingHistory[] = $row;
}

// ── BOOKING COUNTS ───────────────────────────────────────────
$countSql = "SELECT COUNT(*) AS total FROM bookings WHERE user_id = $userId";
$countResult = mysqli_query($conn, $countSql);
$bookingCount = mysqli_fetch_assoc($countResult)['total'] ?? 0;