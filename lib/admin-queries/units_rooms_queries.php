<?php
/**
 * lib/admin/units_rooms_data.php
 * Data layer for pages/admin/units_rooms.php
 * Requires: $conn (mysqli)
 *
 * Exposes:
 *   $stats          - unit count snapshot
 *   $total, $occupied, $vacant, $maintenance
 *   $properties     - array for filter dropdown
 *   $units_result   - mysqli_result for the main unit loop
 *   $imgStmt        - prepared statement to fetch unit images per unit
 *                     Usage in loop:
 *                       $imgStmt->bind_param('i', $uid);
 *                       $imgStmt->execute();
 *                       $imgs = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
 *                     Close after loop: $imgStmt->close();
 */

// Stat snapshot (no user input)
$stats = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='occupied')    AS occupied,
            SUM(status='vacant')      AS vacant,
            SUM(status='maintenance') AS maintenance
     FROM units"
)->fetch_assoc();

$total = (int) $stats['total'];
$occupied = (int) $stats['occupied'];
$vacant = (int) $stats['vacant'];
$maintenance = (int) $stats['maintenance'];

// Properties dropdown (no user input)
$properties = $conn->query(
    "SELECT property_id, property_name FROM properties ORDER BY property_name ASC"
)->fetch_all(MYSQLI_ASSOC);

// Full unit list (no user input)
$units_result = $conn->query(
    "SELECT u.*, p.property_name,
            NULLIF(TRIM(CONCAT(usr.first_name,' ',usr.last_name)),'') AS tenant_name,
            usr.email         AS tenant_email,
            usr.profile_photo AS tenant_photo,
            CASE WHEN b.booking_id IS NOT NULL THEN 'occupied' ELSE u.status END AS real_status
     FROM units u
     LEFT JOIN properties p ON u.property_id = p.property_id
     LEFT JOIN bookings b
         ON u.unit_id=b.unit_id AND b.status IN('confirmed','active')
         AND b.checkin_date <= CURDATE() AND b.checkout_date > CURDATE()
     LEFT JOIN users usr ON b.user_id=usr.user_id
     ORDER BY u.unit_id DESC"
);

// Prepared statement for unit images — bind $uid and execute inside the loop
$imgStmt = $conn->prepare(
    "SELECT image_path FROM unit_images WHERE unit_id=? ORDER BY sort_order ASC LIMIT 6"
);