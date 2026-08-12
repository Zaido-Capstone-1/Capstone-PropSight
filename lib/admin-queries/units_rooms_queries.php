<?php
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
            COALESCE(NULLIF(TRIM(CONCAT(usr.first_name,' ',usr.last_name)),''), u.tenant_name) AS tenant_name,
            usr.email         AS tenant_email,
            usr.profile_photo AS tenant_photo,
            CASE
                WHEN b_active.booking_id  IS NOT NULL THEN 'occupied'
                WHEN b_booked.booking_id  IS NOT NULL THEN 'booked'
                WHEN u.status IN ('occupied','booked')  THEN 'vacant'
                ELSE u.status
            END AS real_status
     FROM units u
     LEFT JOIN properties p ON u.property_id = p.property_id
     LEFT JOIN bookings b_active
         ON u.unit_id = b_active.unit_id AND b_active.status = 'active'
         AND b_active.booking_id = (
             SELECT MAX(b2.booking_id) FROM bookings b2
             WHERE b2.unit_id = u.unit_id AND b2.status = 'active'
         )
     LEFT JOIN bookings b_booked
         ON u.unit_id = b_booked.unit_id AND b_booked.status = 'confirmed'
         AND b_booked.booking_id = (
             SELECT MAX(b3.booking_id) FROM bookings b3
             WHERE b3.unit_id = u.unit_id AND b3.status = 'confirmed'
         )
     LEFT JOIN users usr
         ON usr.user_id = COALESCE(b_active.user_id, b_booked.user_id)
     ORDER BY u.unit_id DESC"
);

// Prepared statement for unit images — bind $uid and execute inside the loop
$imgStmt = $conn->prepare(
    "SELECT image_path FROM unit_images WHERE unit_id=? ORDER BY sort_order ASC LIMIT 6"
);