<?php

include_once __DIR__ . '/db.php';

// Ensure unit availability stays synced with actual bookings, including future pending/confirmed/active reservations.
mysqli_query($conn, "
    UPDATE units u
    SET u.status = CASE
        WHEN u.status = 'maintenance' THEN 'maintenance'
        WHEN EXISTS (
            SELECT 1 FROM bookings b
            WHERE b.unit_id = u.unit_id
              AND b.status IN ('pending', 'confirmed', 'active')
              AND b.checkout_date > CURDATE()
        ) THEN 'occupied'
        ELSE 'vacant'
    END
");

$ratingExpr = "NULL AS rating";
$hasBookingsRating = false;
$hasUnitsRating = false;
$hasBookingReviews = false;

if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'rating'")) {
    $hasBookingsRating = mysqli_num_rows($tbl) > 0;
}
if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM units LIKE 'rating'")) {
    $hasUnitsRating = mysqli_num_rows($tbl) > 0;
}
if ($tbl = mysqli_query($conn, "SHOW TABLES LIKE 'booking_reviews'")) {
    $hasBookingReviews = mysqli_num_rows($tbl) > 0;
}

if ($hasBookingReviews) {
    $ratingExpr = "(
            SELECT ROUND(AVG(br.rating), 1)
            FROM booking_reviews br
            WHERE br.unit_id = u.unit_id
        ) AS rating";
} elseif ($hasBookingsRating) {
    $ratingExpr = "(
            SELECT ROUND(AVG(br.rating), 1)
            FROM bookings br
            WHERE br.unit_id = u.unit_id
              AND br.rating IS NOT NULL
        ) AS rating";
} elseif ($hasUnitsRating) {
    $ratingExpr = "u.rating AS rating";
}

$unitsSql = "
    SELECT
        u.unit_id,
        u.unit_number,
        u.unit_name,
        u.unit_type,
        u.floor,
        u.rent_amount,
        u.status,
        u.season,
        u.max_guests,
        u.description,
        p.property_name,
        p.city,
        p.address,
        p.latitude,
        p.longitude,
        (
            SELECT ui.image_path
            FROM   unit_images ui
            WHERE  ui.unit_id = u.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC
            LIMIT 1
        ) AS image_path,
        {$ratingExpr}
    FROM  units u
    LEFT JOIN properties p ON p.property_id = u.property_id
    ORDER BY u.unit_id ASC
";

$unitsResult = mysqli_query($conn, $unitsSql);
$units = [];
while ($row = mysqli_fetch_assoc($unitsResult)) {
    $units[] = $row;
}

$amenitiesSql = "
    SELECT ua.unit_id, a.name AS amenity_name, a.icon AS amenity_icon
    FROM   unit_amenities ua
    JOIN   amenities a ON a.amenity_id = ua.amenity_id
    ORDER  BY ua.unit_id
";
$amenResult = mysqli_query($conn, $amenitiesSql);
$amenitiesMap = [];
while ($row = mysqli_fetch_assoc($amenResult)) {
    $amenitiesMap[$row['unit_id']][] = [
        'name' => $row['amenity_name'],
        'icon' => $row['amenity_icon'],
    ];
}

// All images per unit (for gallery)
$imagesResult = mysqli_query($conn, "
    SELECT unit_id, image_path
    FROM unit_images
    ORDER BY unit_id, sort_order ASC, image_id ASC
");
$imagesMap = [];
while ($row = mysqli_fetch_assoc($imagesResult)) {
    $imagesMap[$row['unit_id']][] = '../../' . ltrim($row['image_path'], '/');
}